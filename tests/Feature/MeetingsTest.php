<?php

namespace Tests\Feature;

use App\Models\Department;
use App\Models\User;
use App\Services\Meetings;
use Carbon\CarbonImmutable;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** TFC Meetings inside the one TFC system: same sign-in, from planning a meeting to approved minutes. */
class MeetingsTest extends TestCase
{
    use RefreshDatabase;

    private User $organizer;

    private User $member;

    private User $outsider;

    private int $room;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        $this->travelTo(CarbonImmutable::create(2026, 11, 4, 9, 0, 0, Meetings::TZ));
        $finance = Department::query()->create(['name' => 'Finance']);
        $this->organizer = User::factory()->create(['name' => 'Neema Organizer', 'meeting_role' => 'organizer', 'department_id' => $finance->id, 'job_title' => 'Director']);
        $this->member = User::factory()->create(['name' => 'Juma Member', 'department_id' => $finance->id]);
        $this->outsider = User::factory()->create(['name' => 'Asha Outsider']);
        $this->room = DB::table('meeting_rooms')->insertGetId(['name' => 'Board Room', 'capacity' => 1, 'location' => '3rd Floor']);
    }

    private function plan(array $extra = []): int
    {
        $this->actingAs($this->organizer)->post(route('meetings.meeting_new'), $extra + ['title' => 'Monthly Management Meeting', 'description' => 'Review', 'meeting_type' => 'Management Meeting', 'quorum' => 50,
            'date' => '2026-11-10', 'start' => '09:00', 'end' => '11:00', 'room_id' => $this->room, 'participants' => [$this->member->id], 'repeat' => 'none'])->assertRedirect();

        return (int) DB::table('meetings')->orderByDesc('id')->value('id');
    }

    public function test_a_meeting_from_planning_to_approved_minutes(): void
    {
        $id = $this->plan();
        $meeting = DB::table('meetings')->find($id);
        $this->assertSame(['2026-11-10 09:00', '2026-11-10 11:00', $this->organizer->id], [$meeting->start_time, $meeting->end_time, $meeting->organizer_id]);
        $this->assertSame(2, DB::table('meeting_participants')->where('meeting_id', $id)->count());
        $this->assertSame(1, DB::table('meeting_notifications')->where('user_id', $this->member->id)->count());

        // The person invited sees it and replies; someone not invited cannot open it
        $this->actingAs($this->member)->get(route('meetings.dashboard'))->assertOk()->assertSee('Monthly Management Meeting')->assertSee('will you attend?');
        $this->actingAs($this->member)->post(route('meetings.meeting_rsvp', ['meeting_id' => $id]), ['rsvp' => 'accepted'])->assertRedirect();
        $this->actingAs($this->outsider)->get(route('meetings.meeting_detail', ['meeting_id' => $id]))->assertForbidden();
        $this->actingAs($this->member)->get(route('meetings.meeting_edit', ['meeting_id' => $id]))->assertForbidden();

        // Agenda, starting it, moving through the agenda, live status
        $agenda = fn ($title) => $this->actingAs($this->organizer)->post(route('meetings.agenda_add', ['meeting_id' => $id]), ['title' => $title, 'presenter_id' => $this->member->id, 'duration' => 30])->assertRedirect();
        $agenda('Financial report');
        $agenda('Any other business');
        [$first, $second] = DB::table('meeting_agenda_items')->where('meeting_id', $id)->orderBy('position')->pluck('id')->all();
        $this->actingAs($this->organizer)->post(route('meetings.agenda_move', ['item_id' => $second, 'direction' => 'up']))->assertRedirect();
        $this->assertSame(0, DB::table('meeting_agenda_items')->find($second)->position);
        $this->actingAs($this->organizer)->post(route('meetings.meeting_start', ['meeting_id' => $id]))->assertRedirect();
        $this->assertSame($second, DB::table('meetings')->find($id)->current_agenda_id);
        $this->actingAs($this->organizer)->post(route('meetings.agenda_now', ['item_id' => $first]))->assertRedirect();
        $this->actingAs($this->member)->getJson(route('meetings.meeting_live', ['meeting_id' => $id]))->assertOk()->assertJsonPath('status', 'in_progress')->assertJsonPath('current.title', 'Financial report')->assertJsonPath('current.number', 2);
        $this->actingAs($this->organizer)->get(route('meetings.meeting_detail', ['meeting_id' => $id]))->assertOk()->assertSee('Now discussing');

        // Attendance, a report file, a decision, an action item
        $this->actingAs($this->organizer)->post(route('meetings.meeting_attendance', ['meeting_id' => $id]), ["att_{$this->member->id}" => 'present', "att_{$this->organizer->id}" => 'present'])->assertRedirect();
        $this->actingAs($this->member)->post(route('meetings.attachment_upload', ['meeting_id' => $id]), ['file' => UploadedFile::fake()->create('report.pdf', 10, 'application/pdf'), 'agenda_item_id' => $first, 'back' => 'agenda'])->assertRedirect();
        $file = DB::table('meeting_attachments')->first();
        $this->assertSame([$first, 'report.pdf'], [$file->agenda_item_id, $file->filename]);
        Storage::disk('local')->assertExists("meetings/{$file->stored_name}");
        $this->actingAs($this->member)->get(route('meetings.attachment_download', ['item_id' => $file->id]))->assertOk();
        $this->actingAs($this->outsider)->get(route('meetings.attachment_download', ['item_id' => $file->id]))->assertForbidden();
        $this->actingAs($this->member)->post(route('meetings.attachment_upload', ['meeting_id' => $id]), ['file' => UploadedFile::fake()->create('virus.exe', 1)])->assertRedirect();
        $this->assertSame(1, DB::table('meeting_attachments')->count());
        $this->actingAs($this->organizer)->post(route('meetings.decision_add', ['meeting_id' => $id]), ['text' => 'Budget approved', 'proposer_id' => $this->member->id, 'votes_for' => 2, 'votes_against' => 0, 'outcome' => 'carried'])->assertRedirect();
        $this->actingAs($this->organizer)->post(route('meetings.action_add', ['meeting_id' => $id]), ['title' => 'Send the quotations', 'assignee_id' => $this->member->id, 'due_date' => '2026-11-01'])->assertRedirect();
        $action = DB::table('meeting_action_items')->first();
        $this->actingAs($this->member)->get(route('meetings.tasks'))->assertOk()->assertSee('Send the quotations')->assertSee('Overdue');
        $this->actingAs($this->member)->post(route('meetings.action_status', ['item_id' => $action->id]), ['status' => 'done'])->assertRedirect();
        $this->assertNotNull(DB::table('meeting_action_items')->first()->completed_at);

        // Ending it, minutes: saved, submitted, approved and then locked
        $this->actingAs($this->organizer)->post(route('meetings.meeting_end', ['meeting_id' => $id]))->assertRedirect();
        $this->actingAs($this->member)->post(route('meetings.minutes_save', ['meeting_id' => $id]), ['content' => 'x'])->assertForbidden();
        $this->actingAs($this->organizer)->post(route('meetings.minutes_save', ['meeting_id' => $id]), ['content' => 'What was said', 'action' => 'submit'])->assertRedirect();
        $this->actingAs($this->organizer)->post(route('meetings.minutes_save', ['meeting_id' => $id]), ['action' => 'approve'])->assertRedirect();
        $this->assertSame('approved', DB::table('meeting_minutes')->first()->status);
        $this->actingAs($this->organizer)->post(route('meetings.minutes_save', ['meeting_id' => $id]), ['content' => 'changed'])->assertRedirect();
        $this->assertSame('What was said', DB::table('meeting_minutes')->first()->content);

        foreach (['meeting_detail', 'meeting_print', 'meeting_ics', 'meeting_edit'] as $page) {
            $this->actingAs($this->organizer)->get(route("meetings.{$page}", ['meeting_id' => $id]))->assertOk();
        }
        $this->actingAs($this->organizer)->get(route('meetings.meeting_print', ['meeting_id' => $id]))->assertSee('Minutes of the')->assertSee('Budget approved');
    }

    public function test_a_year_of_meetings_skips_a_room_already_booked_and_the_series_can_be_changed_together(): void
    {
        DB::table('meetings')->insert(['title' => 'Other', 'start_time' => '2026-12-08 09:00', 'end_time' => '2026-12-08 10:00', 'room_id' => $this->room, 'organizer_id' => $this->organizer->id]);

        // 2nd Tuesday of each month, to the end of the year: 10 Nov and (8 Dec: room taken)
        $this->actingAs($this->organizer)->getJson(route('meetings.meeting_preview', ['date' => '2026-11-10', 'start' => '09:00', 'end' => '10:00', 'room_id' => $this->room, 'repeat' => 'monthly_weekday', 'end_mode' => 'twelve_months']))
            ->assertOk()->assertJsonPath('summary', 'Every month on the 2nd Tuesday')->assertJsonPath('items.1.date', '2026-12-08')->assertJsonPath('items.1.conflict', 'Other')->assertJsonCount(12, 'items');
        $id = $this->plan(['repeat' => 'monthly_weekday', 'end_mode' => 'twelve_months']);
        $series = DB::table('meetings')->whereNotNull('series_id')->orderBy('start_time')->pluck('start_time')->all();
        $this->assertCount(11, $series);
        $this->assertSame('2026-11-10 09:00', $series[0]);
        $this->assertSame('2027-01-12 09:00', $series[1]);

        // Whole series: new time, each keeps its date
        $first = (int) DB::table('meetings')->where('start_time', '2026-11-10 09:00')->value('id');
        $this->actingAs($this->organizer)->post(route('meetings.meeting_edit', ['meeting_id' => $first]), ['title' => 'Renamed', 'date' => '2026-11-10', 'start' => '14:00', 'end' => '15:00', 'scope' => 'following', 'meeting_type' => 'Other', 'quorum' => 50])->assertRedirect();
        $this->assertSame('2027-01-12 14:00', DB::table('meetings')->where('title', 'Renamed')->orderBy('start_time')->skip(1)->value('start_time'));

        // A room clash on a single meeting is refused, with the form kept
        $this->actingAs($this->organizer)->post(route('meetings.meeting_new'), ['title' => 'Clash', 'date' => '2026-12-08', 'start' => '09:30', 'end' => '09:45', 'room_id' => $this->room, 'meeting_type' => 'Other', 'quorum' => 50])
            ->assertOk()->assertSee('already booked')->assertSee('value="Clash"', false);

        // Cancel this and following
        $this->actingAs($this->organizer)->post(route('meetings.meeting_status', ['meeting_id' => $first]), ['status' => 'cancelled', 'scope' => 'following'])->assertRedirect();
        $this->assertSame(11, DB::table('meetings')->where('status', 'cancelled')->count());
        $this->actingAs($this->organizer)->post(route('meetings.meeting_delete', ['meeting_id' => $first]), ['scope' => 'following'])->assertRedirect();
        $this->assertSame(1, DB::table('meetings')->count());
    }

    public function test_every_page_opens_and_who_may_do_what(): void
    {
        $this->plan();
        $admin = User::factory()->create(['is_admin' => true]);
        $group = DB::table('meeting_groups')->insertGetId(['name' => 'Board', 'color' => '#3b5bdb']);
        DB::table('meeting_group_members')->insert(['group_id' => $group, 'user_id' => $this->member->id]);
        $this->actingAs($admin)->post(route('meetings.rooms'), ['name' => 'Hall', 'capacity' => 40])->assertRedirect();

        foreach (['dashboard', 'meetings_list', 'meeting_new', 'profile', 'tasks', 'decisions_list', 'calendar_view', 'planner', 'groups', 'group_form', 'rooms', 'notifications', 'reports', 'departments'] as $page) {
            $this->actingAs($admin)->get(route("meetings.{$page}"))->assertOk();
        }
        $this->actingAs($admin)->get(route('meetings.group_form', ['group_id' => $group]))->assertOk()->assertSee('Board');
        $this->actingAs($admin)->get(route('meetings.calendar_view', ['month' => '2026-11']))->assertOk()->assertSee('November 2026')->assertSee('Monthly Management Meeting');
        $this->actingAs($admin)->get(route('meetings.planner', ['year' => 2026]))->assertOk()->assertSee('Monthly Management Meeting');
        $this->actingAs($admin)->get(route('meetings.planner', ['year' => 2026, 'format' => 'ics']))->assertOk()->assertSee('BEGIN:VEVENT');
        $this->actingAs($admin)->get(route('meetings.reports', ['year' => 2026]))->assertOk()->assertSee('Board Room');

        // A member: no new meetings, groups, rooms or reports
        $m = $this->member;
        $this->actingAs($m)->get(route('meetings.meeting_new'))->assertForbidden();
        $this->actingAs($m)->get(route('meetings.group_form'))->assertForbidden();
        $this->actingAs($m)->get(route('meetings.reports'))->assertForbidden();
        $this->actingAs($m)->get(route('meetings.departments'))->assertForbidden();
        $this->actingAs($m)->post(route('meetings.rooms'), ['name' => 'Mine'])->assertForbidden();
        $this->actingAs($m)->get(route('meetings.meetings_list', ['scope' => 'all']))->assertOk()->assertSee('Monthly Management Meeting');
        $this->actingAs($this->outsider)->get(route('meetings.meetings_list', ['scope' => 'all']))->assertOk()->assertDontSee('Monthly Management Meeting');

        // A group or a department invites its members; signed out, nothing opens
        $id = (int) DB::table('meetings')->value('id');
        $this->actingAs($this->organizer)->post(route('meetings.participant_add', ['meeting_id' => $id]), ['groups' => [$group], 'departments' => [Department::query()->value('id')]])->assertRedirect();
        $this->assertSame(1, DB::table('meeting_audience')->where('kind', 'group')->count());
        $this->actingAs($this->organizer)->get(route('meetings.meeting_detail', ['meeting_id' => $id]))->assertOk()->assertSee('Board');
        auth()->logout();
        $this->get(route('meetings.dashboard'))->assertRedirect(route('login'));

        // A notification only opens for its owner and only goes inside the site
        $note = DB::table('meeting_notifications')->insertGetId(['user_id' => $m->id, 'message' => 'x', 'link' => 'https://evil.example']);
        $this->actingAs($m)->get(route('meetings.notification_open', ['item_id' => $note]))->assertRedirect(route('meetings.notifications'));
        $this->actingAs($this->outsider)->get(route('meetings.notification_open', ['item_id' => $note]))->assertNotFound();
    }
}
