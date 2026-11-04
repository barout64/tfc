<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** One TFC sign-in: every part of the system opens right here, for everyone active. */
class PortalTest extends TestCase
{
    use RefreshDatabase;

    public function test_one_sign_in_opens_every_part_of_the_system(): void
    {
        $amina = User::factory()->create(['name' => 'Amina Juma', 'email' => 'amina@tfc.go.tz']);
        $this->get('/')->assertOk()->assertDontSee('Sign out');
        $this->post('/login', ['email' => 'amina@tfc.go.tz', 'password' => 'wrong'])->assertSessionHasErrors('email');
        $this->post('/login', ['email' => 'amina@tfc.go.tz', 'password' => 'password'])->assertRedirect();
        $this->assertAuthenticatedAs($amina);

        $home = $this->get('/')->assertOk();
        foreach (['Helpdesk', 'Uwajibikaji', 'TFC Meetings', 'TFC Protocol'] as $part) {
            $home->assertSee($part);
        }
        foreach ([route('helpdesk.index'), route('duty.index'), route('meetings.dashboard'), route('protocol.index')] as $url) {
            $home->assertSee($url, false);
            $this->get($url)->assertOk();
        }
        // Nothing leaves for another system
        $home->assertDontSee('meeting.bosscreativegroup.com')->assertDontSee('protocol.bosscreativegroup.com');
    }

    public function test_the_administrator_sets_department_and_roles_for_everything(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        $department = Department::query()->create(['name' => 'Finance', 'code' => 'FIN']);
        $this->actingAs($admin)->get(route('admin.users.create'))->assertOk()->assertSee('Meetings role')->assertSee('Protocol role')->assertSee('Finance');
        $this->actingAs($admin)->post(route('admin.users.store'), ['name' => 'Neema', 'email' => 'neema@tfc.go.tz', 'password' => 'Str0ngPass!', 'password_confirmation' => 'Str0ngPass!', 'is_active' => '1',
            'department_id' => $department->id, 'job_title' => 'Director', 'meeting_role' => 'organizer', 'protocol_role' => 'head'])->assertRedirect();
        $neema = User::query()->where('email', 'neema@tfc.go.tz')->firstOrFail();
        $this->assertSame(['organizer', 'head', 'Finance'], [$neema->meetingRole(), $neema->protocolRole(), $neema->departmentName()]);
        $this->actingAs($admin)->get(route('admin.users.index'))->assertOk()->assertSee('Neema')->assertSee('Finance');
        $this->actingAs($admin)->get(route('admin.activity'))->assertOk();
        $this->actingAs($neema)->get(route('meetings.meeting_new'))->assertOk();
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
        $this->get('/')->assertOk()->assertDontSee('Sign out');
    }

    public function test_the_old_systems_data_comes_in_once_people_matched_and_history_kept(): void
    {
        Storage::fake('local');
        $dir = sys_get_temp_dir().'/tfc-import-'.uniqid();
        mkdir("{$dir}/up", 0777, true);
        mkdir("{$dir}/pup", 0777, true);
        file_put_contents("{$dir}/up/abc.pdf", 'PDF');
        file_put_contents("{$dir}/pup/dead-1x1.png", 'PNG');
        $db = new \PDO("sqlite:{$dir}/m.db");
        $db->exec(file_get_contents(base_path('tests/fixtures/meeting-schema.sql')));
        $db->exec("INSERT INTO departments (id, name) VALUES (7, 'finance');
            INSERT INTO users (id, name, email, password_hash, role, department_id, job_title) VALUES (50, 'Amina', 'AMINA@tfc.go.tz', 'x', 'organizer', 7, 'Director'), (51, 'Linked', 'old@x.y', 'x', 'secretary', NULL, NULL), (52, 'Unknown', 'unknown@x.y', 'x', 'member', NULL, NULL);
            INSERT INTO rooms (id, name, capacity) VALUES (3, 'Board', 10);
            INSERT INTO meetings (id, title, meeting_type, start_time, end_time, room_id, organizer_id, status, series_id) VALUES (9, 'Old meeting', 'Other', '2026-10-01 09:00', '2026-10-01 10:00', 3, 50, 'completed', NULL);
            INSERT INTO participants (meeting_id, user_id, attendance) VALUES (9, 50, 'present'), (9, 52, 'absent');
            INSERT INTO agenda_items (id, meeting_id, title, position) VALUES (11, 9, 'Item', 0);
            INSERT INTO minutes (meeting_id, content, status, updated_by) VALUES (9, 'Said', 'approved', 51);
            INSERT INTO decisions (id, meeting_id, text, proposer_id) VALUES (1, 9, 'Decided', 50);
            INSERT INTO user_groups (id, name) VALUES (2, 'Board'); INSERT INTO user_group_members (group_id, user_id) VALUES (2, 50);
            INSERT INTO meeting_audience (meeting_id, kind, ref_id) VALUES (9, 'department', 7);
            INSERT INTO action_items (id, meeting_id, title, assignee_id) VALUES (1, 9, 'Do', 51);
            INSERT INTO attachments (id, meeting_id, filename, stored_name, size, uploaded_by) VALUES (1, 9, 'a.pdf', 'abc.pdf', 3, 50);
            INSERT INTO notifications (id, user_id, message, link) VALUES (1, 50, 'Hi', '/tasks'), (2, 50, 'Hi', '/meetings/9');");
        unset($db);
        file_put_contents("{$dir}/p.json", json_encode(['settings' => ['orgName' => 'TFC', 'orgCode' => 'TFC', 'refFormat' => '{SEQ}', 'logo' => '', 'showDemoLogins' => true],
            'departments' => [['id' => 'd1', 'name' => 'Finance', 'code' => 'FIN'], ['id' => 'd2', 'name' => 'Legal', 'code' => 'LEG']],
            'users' => [['id' => 'u1', 'name' => 'Amina', 'username' => 'amina', 'role' => 'head', 'departmentId' => 'd1', 'tfcEmail' => 'amina@tfc.go.tz'], ['id' => 'u2', 'name' => 'Sample', 'username' => 'hr.head', 'role' => 'secretary', 'departmentId' => 'd2']],
            'templates' => [['id' => 't1', 'departmentId' => 'd2', 'createdBy' => 'u2', 'createdAt' => 'x', 'archived' => false, 'draft' => null, 'versions' => [['approvedBy' => 'u1', 'version' => 1]], 'published' => ['title' => 'Letter', 'approvedBy' => 'u1', 'version' => 1]]],
            'documents' => [], 'protocol' => [['id' => 'r1', 'title' => 'Rule', 'body' => '', 'updatedBy' => 'u1', 'acks' => ['u1', 'u2', 'gone']]],
            'notifications' => [['userId' => 'u1', 'text' => 'Hello', 'link' => '#/x', 'at' => 'x', 'read' => false]], 'audit' => [['at' => 'x', 'userId' => 'u1', 'action' => 'Signed in', 'detail' => '']],
            'counters' => ['FIN-2026' => 4], 'uploads' => [['id' => 'dead', 'file' => 'dead-1x1.png', 'name' => 'dot', 'type' => 'image/png', 'size' => 3, 'by' => 'u2', 'at' => 'x']]]));

        $amina = User::factory()->create(['email' => 'amina@tfc.go.tz']);
        $linked = User::factory()->create(['email' => 'someone@tfc.go.tz']);
        DB::table('system_links')->insert(['user_id' => $linked->id, 'system' => 'meeting', 'remote_id' => '51', 'remote_name' => 'Linked', 'created_at' => now(), 'updated_at' => now()]);
        $paths = ['--meeting-db' => "{$dir}/m.db", '--meeting-uploads' => "{$dir}/up", '--protocol-db' => "{$dir}/p.json", '--protocol-uploads' => "{$dir}/pup"];

        $this->artisan('tfc:import-old-systems', $paths)->expectsOutputToContain('2 people had no TFC login')->assertSuccessful();

        // People: by email, by link, or added switched off; one department list, with Protocol's code
        $this->assertSame('organizer', $amina->fresh()->meeting_role);
        $this->assertSame('head', $amina->fresh()->protocol_role);
        $this->assertSame('secretary', $linked->fresh()->meeting_role);
        $this->assertFalse(User::query()->where('email', 'unknown@x.y')->first()->is_active);
        $this->assertSame('FIN', Department::query()->find($amina->fresh()->department_id)->code);
        $this->assertSame(2, Department::query()->count());

        // Everything is here, under the right people, and opens
        $this->assertSame($amina->id, DB::table('meetings')->find(9)->organizer_id);
        $this->assertSame($linked->id, DB::table('meeting_action_items')->first()->assignee_id);
        $this->assertSame($amina->department_id ?? $amina->fresh()->department_id, DB::table('meeting_audience')->first()->ref_id);
        $this->assertSame('/meetings/tasks', DB::table('meeting_notifications')->find(1)->link);
        $this->assertSame('/meetings/9', DB::table('meeting_notifications')->find(2)->link);
        Storage::disk('local')->assertExists('meetings/abc.pdf');
        Storage::disk('local')->assertExists('protocol/dead-1x1.png');
        $this->actingAs($amina->fresh())->get(route('meetings.meeting_detail', ['meeting_id' => 9]))->assertOk()->assertSee('Old meeting')->assertSee('Decided');

        $template = json_decode(DB::table('protocol_templates')->value('data'), true);
        $this->assertSame((string) $amina->id, $template['published']['approvedBy']);
        $this->assertSame((string) $amina->id, $template['versions'][0]['approvedBy']);
        $rule = json_decode(DB::table('protocol_rules')->value('data'), true);
        $this->assertCount(2, $rule['acks']);
        $this->actingAs($amina->fresh())->getJson('/protocol/api/templates')->assertOk()->assertJsonPath('0.published.title', 'Letter');
        $this->actingAs($amina->fresh())->getJson('/protocol/api/bootstrap')->assertJsonPath('settings.showDemoLogins', false);

        // Not twice by accident; --fresh reads it all again
        $this->artisan('tfc:import-old-systems', $paths)->assertFailed();
        $this->artisan('tfc:import-old-systems', $paths + ['--fresh' => true])->assertSuccessful();
        $this->assertSame(1, DB::table('meetings')->count());
    }
}
