<?php

namespace Tests\Feature;

use App\Models\Activity;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;
use PDO;
use Tests\TestCase;

/** One TFC sign-in; every active person gets TFC Meetings and TFC Protocol, opened already signed in there. */
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
        $meeting->exec("insert into users values (7, 'Amina Juma', 'amina@tfc.go.tz', 'organizer', 1)");
        file_put_contents("{$this->dir}/protocol.json", json_encode(['users' => [['id' => 'ab12', 'name' => 'Amina Juma', 'username' => 'amina', 'role' => 'head']]]));

        config([
            'tfc.systems.meeting.users.path' => "{$this->dir}/meeting.db", 'tfc.systems.meeting.key' => 'meeting-key',
            'tfc.systems.protocol.users.path' => "{$this->dir}/protocol.json", 'tfc.systems.protocol.key' => 'protocol-key',
        ]);

        // Each system's /tfc/provision answers with the account it created (or found) there
        Http::fake([
            'meeting.bosscreativegroup.com/tfc/provision' => Http::response(['id' => 7]),
            'protocol.bosscreativegroup.com/tfc/provision' => Http::response(['id' => 'ab12']),
        ]);
    }

    public function test_one_sign_in_opens_meetings_and_protocol_already_signed_in_there(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);

        // The administrator adds Amina: she gets Meetings and Protocol automatically
        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()
            ->assertSee('Everyone who can sign in already has:')->assertSee('TFC Meetings, TFC Protocol')
            ->assertDontSee('Kilimo');
        $this->actingAs($admin)->post(route('admin.users.store'), [
            'name' => 'Amina Juma', 'email' => 'Amina@TFC.go.tz', 'password' => 'long-password-1', 'password_confirmation' => 'long-password-1',
            'is_active' => '1', 'is_admin' => '0',
        ])->assertRedirect(route('admin.users.index'));
        Http::assertSent(fn ($r) => $r->url() === 'https://meeting.bosscreativegroup.com/tfc/provision' && $r->header('X-Tfc-Key') === ['meeting-key'] && $r['email'] === 'amina@tfc.go.tz');
        $amina = User::query()->where('email', 'amina@tfc.go.tz')->firstOrFail();
        $this->assertEqualsCanonicalizing(['meeting', 'protocol'], $amina->links()->pluck('system')->all());
        auth()->logout();

        // Amina signs in once
        $this->post('/login', ['email' => 'amina@tfc.go.tz', 'password' => 'long-password-1'])->assertRedirect(route('home'));
        $this->get(route('home'))->assertOk()
            ->assertSee('Open TFC Meetings')->assertSee('Open TFC Protocol')->assertDontSee('Kilimo')
            ->assertDontSee('People &amp; access', false)
            // The buttons go to /open/meeting and /open/protocol, and no signing key ever reaches the page
            ->assertSee('href="'.route('open', 'meeting').'"', false)->assertSee('href="'.route('open', 'protocol').'"', false)
            ->assertDontSee('meeting-key')->assertDontSee('protocol-key');
        $this->get('/dashboard')->assertRedirect('/');

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

        // Kilimo is not part of the portal at all
        $this->get(route('open', 'kilimo'))->assertNotFound();
        $this->get('/kilimo/dashboard')->assertNotFound();
        // Not for the administrator's pages
        $this->get(route('admin.users.index'))->assertForbidden();
    }

    public function test_switched_off_people_cannot_sign_in_and_the_administrator_cannot_lock_themself_out(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $amina = User::factory()->create(['email' => 'amina@tfc.go.tz']);

        $this->actingAs($admin)->put(route('admin.users.update', $admin), ['name' => $admin->name, 'email' => $admin->email, 'is_active' => '1', 'is_admin' => '0'])
            ->assertSessionHasErrors('is_admin');

        $this->actingAs($admin)->put(route('admin.users.update', $amina), ['name' => 'Amina', 'email' => 'amina@tfc.go.tz', 'is_active' => '0'])->assertRedirect();
        auth()->logout();
        $this->post('/login', ['email' => 'amina@tfc.go.tz', 'password' => 'password'])->assertSessionHasErrors('email');
        $this->assertGuest();
        $this->get('/')->assertRedirect(route('login'));
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
