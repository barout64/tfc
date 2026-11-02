<?php

namespace Tests\Feature;

use App\Models\DutyTask;
use App\Models\DutyWarning;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;

/** Uwajibikaji: a task with a deadline, handed in and approved; a missed deadline, explained and decided; five warnings. */
class DutyTest extends TestCase
{
    use RefreshDatabase;

    private User $leader;

    private User $worker;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('local');
        // Monday 2 Nov 2026, 09:00 in Dar es Salaam
        $this->travelTo(Carbon::create(2026, 11, 2, 9, 0, 0, DutyTask::TZ));
        $this->leader = User::factory()->create(['name' => 'Neema Leader']);
        $this->worker = User::factory()->create(['name' => 'Juma Worker']);
    }

    private function give(string $title = 'Sales report for last week', string $due = '2026-11-03T10:00'): DutyTask
    {
        $this->actingAs($this->leader)->post(route('duty.store'), ['assigned_to' => $this->worker->id, 'title' => $title,
            'details' => 'By region, in Excel', 'due_at' => $due])->assertRedirect();

        return DutyTask::query()->latest('id')->firstOrFail();
    }

    public function test_a_task_from_given_to_approved(): void
    {
        $this->actingAs($this->worker)->get(route('home'))->assertOk()->assertSee('Uwajibikaji');
        $task = $this->give();
        $this->assertSame('2026-11-03 07:00:00', $task->due_at->utc()->format('Y-m-d H:i:s'), 'typed in Tanzania time');
        $this->assertSame(['new', $this->leader->id, $this->worker->id], [$task->status, $task->assigned_by, $task->assigned_to]);

        // Someone else cannot see it
        $other = User::factory()->create();
        $this->actingAs($other)->get(route('duty.show', $task))->assertNotFound();
        $this->actingAs($other)->get(route('duty.index'))->assertDontSee('Sales report for last week');

        // The worker sees it; opening it marks it seen
        $this->actingAs($this->worker)->get(route('duty.index'))->assertOk()->assertSee('Sales report for last week');
        $this->actingAs($this->worker)->get(route('duty.show', $task))->assertOk()->assertSee('Hand in');
        $this->assertSame('seen', $task->fresh()->status);
        $this->assertNotNull($task->fresh()->seen_at);

        // A day before: the reminder shows on every page
        $this->travelTo(Carbon::create(2026, 11, 3, 8, 0, 0, DutyTask::TZ));
        $this->actingAs($this->worker)->get(route('home'))->assertSee('Reminder:')->assertSee('is due Tue 03 Nov 2026, 10:00', false);

        $this->actingAs($this->worker)->post(route('duty.status', $task), ['status' => 'on_it'])->assertRedirect();
        $this->assertSame('on_it', $task->fresh()->status);
        // Only the worker hands in; only the leader approves
        $this->actingAs($this->leader)->post(route('duty.submit', $task), ['body' => 'x'])->assertForbidden();
        $this->actingAs($this->worker)->post(route('duty.submit', $task), ['body' => 'Here it is', 'file' => UploadedFile::fake()->create('sales.xlsx', 20)])->assertRedirect();
        $this->assertSame('submitted', $task->fresh()->status);
        $this->actingAs($this->worker)->post(route('duty.review', $task), ['decision' => 'approve'])->assertForbidden();

        // On time: no warning when the deadline passes
        $this->travelTo(Carbon::create(2026, 11, 3, 11, 0, 0, DutyTask::TZ));
        DutyTask::sweep();
        $this->assertSame(0, DutyWarning::query()->count());

        $this->actingAs($this->leader)->get(route('duty.show', $task))->assertSee('handed in the work');
        $file = $task->messages()->whereNotNull('attachment_path')->firstOrFail();
        $this->actingAs($this->leader)->get(route('duty.file', $file))->assertOk();
        $this->actingAs($other)->get(route('duty.file', $file))->assertNotFound();
        $this->actingAs($this->leader)->post(route('duty.review', $task), ['decision' => 'approve'])->assertRedirect();
        $this->assertSame('approved', $task->fresh()->status);
    }

    public function test_a_missed_deadline_locks_until_explained_and_the_leader_decides(): void
    {
        $task = $this->give();
        $next = $this->give('Stock count', '2026-11-10T10:00');

        // 3 Nov 10:01: missed — a warning, and the worker is locked
        $this->travelTo(Carbon::create(2026, 11, 3, 10, 1, 0, DutyTask::TZ));
        $this->actingAs($this->worker)->get(route('duty.index'))->assertOk()->assertSee('You missed the deadline of “Sales report for last week”', false);
        $warning = DutyWarning::query()->sole();
        $this->assertSame(['explain', 1, $this->worker->id], [$warning->status, $warning->number, $warning->user_id]);
        $this->assertSame('explain', DutyWarning::lockFor($this->worker));
        DutyTask::sweep();
        $this->assertSame(1, DutyWarning::query()->count(), 'once per deadline');

        // Locked: cannot hand in, reply, start, or give tasks — on any task
        $this->actingAs($this->worker)->post(route('duty.submit', $next), ['body' => 'done'])->assertRedirect(route('duty.index'))->assertSessionHas('error');
        $this->actingAs($this->worker)->post(route('duty.reply', $task), ['body' => 'hi'])->assertSessionHas('error');
        $this->actingAs($this->worker)->post(route('duty.status', $next), ['status' => 'on_it'])->assertSessionHas('error');
        $this->actingAs($this->worker)->post(route('duty.store'), ['assigned_to' => $this->leader->id, 'title' => 'x', 'due_at' => '2026-11-20T10:00'])->assertSessionHas('error');
        $this->assertSame('new', $next->fresh()->status);
        $this->actingAs($this->worker)->get(route('home'))->assertSee('you missed a deadline');

        // Only the worker explains; then they can work again
        $this->actingAs($this->leader)->post(route('duty.explain', $warning), ['explanation' => 'Not me'])->assertForbidden();
        $this->actingAs($this->worker)->post(route('duty.explain', $warning), ['explanation' => 'The system was down all Monday.'])->assertRedirect();
        $this->assertSame('review', $warning->fresh()->status);
        $this->assertNull(DutyWarning::lockFor($this->worker));
        $this->actingAs($this->worker)->post(route('duty.submit', $task), ['body' => 'Late, sorry'])->assertRedirect();
        $this->assertSame('submitted', $task->fresh()->status);

        // The leader decides: accepted, warning struck off — it no longer counts
        $this->actingAs($this->worker)->post(route('duty.decide', $warning), ['decision' => 'accepted', 'warning' => 'remove'])->assertForbidden();
        $this->actingAs($this->leader)->get(route('duty.index'))->assertSee('Explanations waiting for your decision')->assertSee('The system was down all Monday.');
        $this->actingAs($this->leader)->post(route('duty.decide', $warning), ['decision' => 'accepted', 'warning' => 'remove', 'note' => 'OK, it was down'])->assertRedirect();
        $this->assertSame(['removed', 'accepted', $this->leader->id], [$warning->fresh()->status, $warning->fresh()->decision, $warning->fresh()->decided_by]);
        $this->assertSame(0, DutyWarning::countFor($this->worker));
    }

    public function test_a_rejected_explanation_keeps_the_warning_and_a_new_deadline_can_be_set(): void
    {
        $task = $this->give();
        $this->travelTo(Carbon::create(2026, 11, 3, 12, 0, 0, DutyTask::TZ));
        DutyTask::sweep();
        $warning = DutyWarning::query()->sole();
        $this->actingAs($this->worker)->post(route('duty.explain', $warning), ['explanation' => 'I forgot about it, sorry.'])->assertRedirect();
        $this->actingAs($this->leader)->post(route('duty.decide', $warning), ['decision' => 'rejected', 'warning' => 'keep'])->assertRedirect();
        $this->assertSame('kept', $warning->fresh()->status);
        $this->assertSame(1, DutyWarning::countFor($this->worker));

        // A new deadline: in the past refused; tomorrow 10:00 accepted; missing it again is a second warning
        $this->actingAs($this->leader)->post(route('duty.deadline', $task), ['due_at' => '2026-11-03T09:00'])->assertSessionHasErrors('due_at');
        $this->actingAs($this->leader)->post(route('duty.deadline', $task), ['due_at' => '2026-11-04T10:00'])->assertRedirect();
        $this->assertNull($task->fresh()->missed_at);
        $this->travelTo(Carbon::create(2026, 11, 4, 10, 30, 0, DutyTask::TZ));
        DutyTask::sweep();
        $this->assertSame([1, 2], DutyWarning::query()->orderBy('id')->pluck('number')->all());
        $this->assertSame(2, DutyWarning::countFor($this->worker));
    }

    public function test_at_five_warnings_no_more_explaining_until_the_administrator_clears_them(): void
    {
        $admin = User::factory()->create(['is_admin' => true]);
        foreach (range(1, 4) as $i) {
            $this->give("Task {$i}", '2026-11-02T10:00');
        }
        $this->travelTo(Carbon::create(2026, 11, 2, 10, 5, 0, DutyTask::TZ));
        DutyTask::sweep();
        foreach (DutyWarning::query()->get() as $w) {
            $this->actingAs($this->worker)->post(route('duty.explain', $w), ['explanation' => 'Too much work this week.'])->assertRedirect();
        }
        $this->assertSame(4, DutyWarning::countFor($this->worker));
        $this->assertNull(DutyWarning::lockFor($this->worker));

        // The fifth: final — kept straight away, cannot be explained, the worker stays locked
        $fifth = $this->give('Task 5', '2026-11-02T11:00');
        $this->travelTo(Carbon::create(2026, 11, 2, 11, 5, 0, DutyTask::TZ));
        DutyTask::sweep();
        $w5 = DutyWarning::query()->where('task_id', $fifth->id)->sole();
        $this->assertSame([5, true, 'kept'], [$w5->number, $w5->final, $w5->status]);
        $this->assertSame('five', DutyWarning::lockFor($this->worker));
        $this->actingAs($this->worker)->post(route('duty.explain', $w5), ['explanation' => 'Please let me explain this.'])->assertSessionHas('error');
        $this->actingAs($this->worker)->post(route('duty.submit', $fifth), ['body' => 'done'])->assertSessionHas('error');
        $this->actingAs($this->worker)->get(route('duty.index'))->assertSee('You can no longer explain missed deadlines');

        // Only the administrator clears (with a reason); then the worker can work again
        $this->actingAs($this->leader)->post(route('duty.clear', $this->worker), ['reason' => 'x'])->assertForbidden();
        $this->actingAs($admin)->get(route('duty.warnings'))->assertOk()->assertSee('Juma Worker')->assertSee('Locked');
        $this->actingAs($admin)->post(route('duty.clear', $this->worker), [])->assertSessionHasErrors('reason');
        $this->actingAs($admin)->post(route('duty.clear', $this->worker), ['reason' => 'Talked with Juma; a new start.'])->assertRedirect();
        $this->assertSame(0, DutyWarning::countFor($this->worker));
        $this->assertSame(5, DutyWarning::query()->where('status', 'cleared')->count());
        $this->assertNull(DutyWarning::lockFor($this->worker));
        $this->actingAs($this->leader)->post(route('duty.deadline', $fifth), ['due_at' => '2026-11-05T10:00'])->assertRedirect();
        $this->actingAs($this->worker)->post(route('duty.submit', $fifth), ['body' => 'done'])->assertSessionHasNoErrors()->assertSessionMissing('error');
        $this->assertSame('submitted', $fifth->fresh()->status);
    }

    public function test_anyone_gives_anyone_a_task_but_not_to_themselves_nor_in_the_past(): void
    {
        $this->actingAs($this->worker)->post(route('duty.store'), ['assigned_to' => $this->leader->id, 'title' => 'Please sign the letters', 'due_at' => '2026-11-02T15:00'])->assertRedirect();
        $this->assertSame($this->leader->id, DutyTask::query()->sole()->assigned_to);
        $this->actingAs($this->worker)->post(route('duty.store'), ['assigned_to' => $this->worker->id, 'title' => 'x', 'due_at' => '2026-11-02T15:00'])->assertSessionHasErrors('assigned_to');
        $this->actingAs($this->worker)->post(route('duty.store'), ['assigned_to' => $this->leader->id, 'title' => 'x', 'due_at' => '2026-11-02T08:00'])->assertSessionHasErrors('due_at');
        $this->assertSame(1, DutyTask::query()->count());
        $this->actingAs($this->worker)->get(route('duty.create'))->assertOk()->assertSee('Neema Leader')->assertDontSee('Juma Worker —');
    }
}
