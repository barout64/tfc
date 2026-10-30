<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use PDO;
use Tests\TestCase;

/** One TFC sign-in; each person opens only the systems the administrator linked them to, already signed in there. */
class PortalTest extends TestCase
{
    use RefreshDatabase;

    private string $dir;

    protected function setUp(): void
    {
        parent::setUp();
        $this->dir = sys_get_temp_dir().'/tfc-test-'.bin2hex(random_bytes(4));
        mkdir($this->dir);

        // Each system's own user list, as it is stored there
        $meeting = new PDO("sqlite:{$this->dir}/meeting.db");
        $meeting->exec('create table users (id integer primary key, name text, email text, role text, active integer)');
        $meeting->exec("insert into users values (7, 'Amina Juma', 'amina@tfc.go.tz', 'organizer', 1), (8, 'Old Account', 'old@tfc.go.tz', 'member', 0)");
        $kilimo = new PDO("sqlite:{$this->dir}/kilimo.db");
        $kilimo->exec('create table users (id integer primary key, name text, email text, role text, is_active integer)');
        $kilimo->exec("insert into users values (3, 'Amina J.', 'amina@kilimo.tz', 'admin', 1)");
        file_put_contents("{$this->dir}/protocol.json", json_encode(['users' => [['id' => 'ab12', 'name' => 'Amina Juma', 'username' => 'amina', 'role' => 'head']]]));

        config([
            'tfc.systems.meeting.users.path' => "{$this->dir}/meeting.db", 'tfc.systems.meeting.key' => 'meeting-key',
            'tfc.systems.kilimo.users.path' => "{$this->dir}/kilimo.db", 'tfc.systems.kilimo.key' => 'kilimo-key',
            'tfc.systems.protocol.users.path' => "{$this->dir}/protocol.json", 'tfc.systems.protocol.key' => 'protocol-key',
        ]);
    }

    public function test_one_sign_in_opens_only_the_linked_systems_already_signed_in_there(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // The administrator adds Amina: Meeting and Protocol, not Kilimo
        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()
            ->assertSee('Amina Juma — amina@tfc.go.tz · organizer')->assertSee('Old Account — old@tfc.go.tz · member (switched off there)')
            ->assertSee('Amina J. — amina@kilimo.tz · admin');
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Amina Juma', 'email' => 'Amina@TFC.go.tz', 'password' => 'long-password-1', 'password_confirmation' => 'long-password-1',
            'is_active' => '1', 'is_admin' => '0', 'links' => ['meeting' => '7', 'protocol' => 'ab12', 'kilimo' => ''],
        ])->assertRedirect(route('admin.users.index'));
        $this->actingAs($admin)->get(route('admin.users.index'))->assertSee('amina@tfc.go.tz');
        auth()->logout();

        // Amina signs in once
        $this->post('/login', ['email' => 'amina@tfc.go.tz', 'password' => 'long-password-1'])->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk()
            ->assertSee('Open TFC Meetings')->assertSee('Open TFC Protocol')->assertDontSee('Open TFC Kilimo')
            ->assertSee('Ask the TFC administrator for access')->assertDontSee('People &amp; access', false)
            // The buttons go to /open/meeting and /open/protocol, and no signing key ever reaches the page
            ->assertSee('href="'.route('open', 'meeting').'"', false)->assertSee('href="'.route('open', 'protocol').'"', false)
            ->assertDontSee('meeting-key')->assertDontSee('protocol-key')->assertDontSee('kilimo-key');

        // Opening Meeting: a signed one-minute link for her account there (id 7)
        $to = $this->get(route('open', 'meeting'))->assertRedirect()->headers->get('Location');
        $this->assertStringStartsWith('https://meeting.bosscreativegroup.com/tfc/sso?t=', $to);
        [$body, $sig] = explode('.', substr($to, strpos($to, '?t=') + 3));
        $b64 = fn ($s) => rtrim(strtr(base64_encode($s), '+/', '-_'), '=');
        $this->assertSame($b64(hash_hmac('sha256', $body, 'meeting-key', true)), $sig);
        $data = json_decode(base64_decode(strtr($body, '-_', '+/')), true);
        $this->assertSame(['meeting', '7'], [$data['s'], $data['u']]);
        $this->assertEqualsWithDelta(time() + 60, $data['e'], 2);
        $this->assertSame(1, Activity::query()->where('action', 'opened')->where('system', 'meeting')->count());

        // Kilimo: no access, nothing signed
        $this->get(route('open', 'kilimo'))->assertRedirect(route('home'))->assertSessionHas('error');
        // Not for the administrator's pages
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_an_account_must_exist_in_that_system_and_switched_off_people_cannot_sign_in(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $amina = User::factory()->create(['email' => 'amina@tfc.go.tz']);

        $this->actingAs($admin)->put(route('admin.users.update', $amina), ['name' => 'Amina', 'email' => 'amina@tfc.go.tz', 'is_active' => '1', 'links' => ['meeting' => '999']])
            ->assertSessionHasErrors('links.meeting');
        // The administrator cannot lock themself out
        $this->actingAs($admin)->put(route('admin.users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'is_active' => '1', 'is_admin' => '0'])
            ->assertSessionHasErrors('is_admin');

        $this->actingAs($admin)->put(route('admin.users.update', $amina), ['name' => 'Amina', 'email' => 'amina@tfc.go.tz', 'is_active' => '0'])->assertRedirect();
        auth()->logout();
        $this->post('/login', ['email' => 'amina@tfc.go.tz', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
    }

    public function test_a_system_without_its_key_cannot_be_opened(): void
    {
        config(['tfc.systems.protocol.key' => null]);
        $user = User::factory()->create();
        $user->links()->create(['system' => 'protocol', 'remote_id' => 'ab12', 'remote_name' => 'Amina Juma']);

        $this->actingAs($user)->get(route('open', 'protocol'))->assertRedirect(route('home'))->assertSessionHas('error');
        $this->actingAs($user)->get(route('open', 'nothing'))->assertNotFound();
        $this->get('/password')->assertOk();
    }
}
