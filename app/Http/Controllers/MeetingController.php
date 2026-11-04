<?php

namespace App\Http\Controllers;

use App\Services\Meetings;
use App\Support\MeetingUi;
use Carbon\CarbonImmutable;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** One meeting: planning it (alone or as a series), running it live, and everything recorded in it. */
class MeetingController extends Controller
{
    public function __construct(private Meetings $m) {}

    private function detail(int $id, string $tab = ''): string
    {
        return route('meetings.meeting_detail', ['meeting_id' => $id]).($tab ? "#{$tab}" : '');
    }

    public function index(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $scope = (string) $request->query('scope', 'upcoming');
        $status = (string) $request->query('status', '');
        $mtype = (string) $request->query('type', '');
        $meetings = $this->m->visible(DB::table('meetings as m'))
            ->leftJoin('meeting_rooms as r', 'r.id', '=', 'm.room_id')->join('users as u', 'u.id', '=', 'm.organizer_id')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('m.title', 'like', "%{$q}%")->orWhere('m.description', 'like', "%{$q}%")))
            ->when($scope === 'upcoming', fn ($w) => $w->where('m.end_time', '>=', Meetings::now()))
            ->when($scope === 'past', fn ($w) => $w->where('m.end_time', '<', Meetings::now()))
            ->when($scope === 'mine', fn ($w) => $w->where('m.organizer_id', auth()->id()))
            ->when(isset(MeetingUi::MEETING_STATUSES[$status]), fn ($w) => $w->where('m.status', $status))
            ->when(in_array($mtype, MeetingUi::MEETING_TYPES, true), fn ($w) => $w->where('m.meeting_type', $mtype))
            ->orderBy('m.start_time', $scope === 'past' ? 'desc' : 'asc')->limit(500)
            ->select('m.*', 'r.name as room_name', 'u.name as organizer_name')
            ->selectSub(fn ($s) => $s->selectRaw('count(*)')->from('meeting_participants')->whereColumn('meeting_id', 'm.id'), 'participant_count')
            ->get()->all();

        return view('meetings.list', compact('meetings', 'q', 'scope', 'status', 'mtype'));
    }

    /** @return array{0: array<string, mixed>, 1: list<string>} */
    private function readForm(Request $request): array
    {
        $errors = [];
        $link = trim((string) $request->input('online_link', ''));
        $roomId = (string) $request->input('room_id', '');
        $quorum = $request->input('quorum', 50);
        $type = (string) $request->input('meeting_type', 'Team Meeting');
        $data = [
            'title' => trim((string) $request->input('title', '')),
            'description' => trim((string) $request->input('description', '')),
            'meeting_type' => in_array($type, MeetingUi::MEETING_TYPES, true) ? $type : 'Other',
            'room_id' => ctype_digit($roomId) ? (int) $roomId : null,
            'online_link' => $link,
            'quorum' => is_numeric($quorum) ? max(0, min(100, (int) $quorum)) : 50,
        ];
        is_numeric($quorum) || $errors[] = 'Quorum must be a number between 0 and 100.';
        $data['title'] === '' && $errors[] = 'Please enter a meeting title.';
        $link !== '' && ! preg_match('#^https?://#', $link) && $errors[] = 'The online meeting link must start with http:// or https://';
        $day = (string) $request->input('date', '');
        $start = Meetings::dateTime($day.' '.$request->input('start', ''));
        $end = Meetings::dateTime($day.' '.$request->input('end', ''));
        if (! $start || ! $end) {
            $errors[] = 'Please enter a valid date, start time and end time.';
        } elseif ($end <= $start) {
            $errors[] = 'The end time must be after the start time.';
        }
        $data += ['start' => $start, 'end' => $end, 'departments' => $this->m->intList($request, 'departments'), 'groups' => $this->m->intList($request, 'groups')];
        $data['participants'] = $this->m->expandAudience($this->m->intList($request, 'participants'), $data['departments'], $data['groups']);

        return [$data, $errors];
    }

    private function form(array $meeting, bool $isNew, array $selected, ?int $id = null, int $seriesCount = 0): View
    {
        $blank = ['title' => null, 'description' => null, 'meeting_type' => 'Team Meeting', 'quorum' => 50, 'date' => null, 'start' => null, 'end' => null, 'room_id' => null,
            'online_link' => null, 'repeat' => 'none', 'end_mode' => 'year_end', 'occurrences' => 12, 'until' => null, 'departments' => [], 'groups' => []];
        $meeting = array_merge($blank, array_intersect_key($meeting, $blank));

        return view('meetings.form', [
            'meeting' => (object) $meeting, 'rooms' => DB::table('meeting_rooms')->orderBy('name')->get()->all(), 'people_data' => $this->m->pickerData(),
            'selected' => $selected, 'is_new' => $isNew, 'meeting_id' => $id, 'series_count' => $seriesCount,
            'sel_departments' => array_map('intval', (array) $meeting['departments']), 'sel_groups' => array_map('intval', (array) $meeting['groups']),
        ]);
    }

    public function create(Request $request): View|RedirectResponse
    {
        abort_unless($this->m->canCreate(), 403);
        if ($request->isMethod('post')) {
            return $this->store($request);
        }
        $defaults = ['date' => $request->query('date', Meetings::today()), 'start' => '09:00', 'end' => '10:00', 'quorum' => 50, 'meeting_type' => $request->query('type', 'Team Meeting'),
            'repeat' => 'none', 'end_mode' => 'year_end', 'occurrences' => 12];
        if ($request->query('plan') === 'year') {
            $defaults = array_merge($defaults, ['repeat' => 'monthly_weekday', 'end_mode' => 'year_end']);
        }
        if (ctype_digit((string) $request->query('group', ''))) {
            $defaults['groups'] = [(int) $request->query('group')];
        }

        return $this->form($defaults, true, $this->m->expandAudience([], [], $defaults['groups'] ?? []));
    }

    private function store(Request $request): View|RedirectResponse
    {
        [$data, $errors] = $this->readForm($request);
        [$repeat, $endMode, $count, $until] = Meetings::readRecurrence($request);
        $dates = [];
        if (! $errors) {
            $first = CarbonImmutable::parse(substr($data['start'], 0, 10), Meetings::TZ);
            $dates = Meetings::recurrenceDates($first, $repeat, $endMode, $count, $until);
            $time = fn (CarbonImmutable $d, string $at) => $d->format('Y-m-d').' '.substr($at, 11);
            $conflict = $this->m->roomConflict($data['room_id'], $time($dates[0], $data['start']), $time($dates[0], $data['end']));
            $conflict && $errors[] = "The room is already booked for “{$conflict->title}” (".substr($conflict->start_time, 11).'–'.substr($conflict->end_time, 11).'). Choose another room or time.';
        }
        if ($errors) {
            foreach ($errors as $e) {
                Meetings::flash($e, 'error');
            }
            session()->now('meeting_flash', session('meeting_flash'));

            return $this->form($request->all(), true, $data['participants']);
        }

        $me = auth()->id();
        $seriesId = count($dates) > 1 ? Str::random(32) : null;
        $invitees = collect($data['participants'])->push($me)->unique()->values()->all();
        $created = $skipped = [];
        DB::transaction(function () use ($dates, $data, $time, $seriesId, $invitees, $me, &$created, &$skipped) {
            foreach ($dates as $d) {
                $s = $time($d, $data['start']);
                $e = $time($d, $data['end']);
                if ($this->m->roomConflict($data['room_id'], $s, $e)) {
                    $skipped[] = $d->format('d M Y');

                    continue;
                }
                $id = DB::table('meetings')->insertGetId(['title' => $data['title'], 'description' => $data['description'], 'meeting_type' => $data['meeting_type'], 'start_time' => $s, 'end_time' => $e,
                    'room_id' => $data['room_id'], 'online_link' => $data['online_link'], 'organizer_id' => $me, 'quorum' => $data['quorum'], 'series_id' => $seriesId, 'created_at' => Meetings::now('Y-m-d H:i:s')]);
                foreach ($invitees as $uid) {
                    DB::table('meeting_participants')->insert(['meeting_id' => $id, 'user_id' => $uid, 'rsvp' => $uid === $me ? 'accepted' : 'pending']);
                }
                $this->m->saveAudience($id, $data['departments'], $data['groups']);
                $created[] = [$id, $s, $e];
            }
        });

        [$firstId, $firstS, $firstE] = $created[0];
        $when = date('D d M Y \a\t H:i', strtotime($firstS));
        $summary = Meetings::repeatSummary($dates[0], $repeat);
        $last = date('d M Y', strtotime($created[array_key_last($created)][1]));
        $note = count($created) > 1 ? ' ('.lcfirst($summary).', '.count($created)." meetings until {$last})" : '';
        $this->m->notify($invitees, auth()->user()->name." invited you to “{$data['title']}” on {$when}{$note}", $this->detail($firstId));
        if ($data['room_id']) {
            $cap = (int) DB::table('meeting_rooms')->where('id', $data['room_id'])->value('capacity');
            count($invitees) > $cap && Meetings::flash('Note: '.count($invitees)." people are invited but the room holds {$cap}.", 'warning');
        }
        if ($busy = $this->m->peopleConflicts($invitees, $firstS, $firstE, [$firstId])) {
            Meetings::flash('Heads-up: '.implode(', ', $busy).' already have another meeting at that time.', 'warning');
        }
        $skipped && Meetings::flash(count($skipped).' date(s) skipped because the room was already booked: '.implode(', ', $skipped), 'warning');
        Meetings::flash(count($created) > 1 ? count($created)." meetings scheduled: {$summary}, until {$last}." : 'Meeting created. Now add the agenda.');

        return redirect()->to($this->detail($firstId, 'agenda'));
    }

    /** The dates a recurrence would create, with room clashes (for the form). */
    public function preview(Request $request): JsonResponse
    {
        $start = Meetings::date((string) $request->query('date', ''));
        if (! $start) {
            return response()->json(['items' => [], 'summary' => 'Choose a start date']);
        }
        [$repeat, $endMode, $count, $until] = Meetings::readRecurrence($request);
        $dates = Meetings::recurrenceDates($start, $repeat, $endMode, $count, $until);
        $room = (string) $request->query('room_id', '');
        $from = (string) $request->query('start', '09:00');
        $to = (string) $request->query('end', '10:00');
        $items = array_map(function (CarbonImmutable $d) use ($room, $from, $to) {
            $iso = $d->format('Y-m-d');
            $clash = ctype_digit($room) && Meetings::dateTime("{$iso} {$from}") && Meetings::dateTime("{$iso} {$to}") ? $this->m->roomConflict((int) $room, "{$iso} {$from}", "{$iso} {$to}") : null;

            return ['date' => $iso, 'label' => $d->format('D d M Y'), 'conflict' => $clash?->title, 'weekend' => $d->dayOfWeekIso >= 6];
        }, $dates);

        return response()->json(['items' => $items, 'summary' => Meetings::repeatSummary($start, $repeat), 'capped' => count($dates) >= Meetings::MAX_OCCURRENCES]);
    }

    public function edit(Request $request, int $meeting_id): View|RedirectResponse
    {
        $meeting = $this->m->meeting($meeting_id, 'manage');
        $following = $this->m->series($meeting, true);
        if (! $request->isMethod('post')) {
            $audience = DB::table('meeting_audience')->where('meeting_id', $meeting_id)->get();

            return $this->form((array) $meeting + ['date' => substr($meeting->start_time, 0, 10), 'start' => substr($meeting->start_time, 11, 5), 'end' => substr($meeting->end_time, 11, 5),
                'departments' => $audience->where('kind', 'department')->pluck('ref_id')->all(), 'groups' => $audience->where('kind', 'group')->pluck('ref_id')->all()],
                false, $this->m->participantIds($meeting_id), $meeting_id, count($following));
        }

        [$data, $errors] = $this->readForm($request);
        $targets = $request->input('scope') === 'following' && count($following) > 1 ? $following : [$meeting];
        $plan = [];
        if (! $errors) {
            foreach ($targets as $t) {
                $day = $t->id === $meeting_id ? substr($data['start'], 0, 10) : substr($t->start_time, 0, 10);
                $s = $day.' '.substr($data['start'], 11);
                $e = $day.' '.substr($data['end'], 11);
                if ($conflict = $this->m->roomConflict($data['room_id'], $s, $e, [$t->id])) {
                    $errors[] = date('d M Y', strtotime($day)).": the room is already booked for “{$conflict->title}”.";
                }
                $plan[] = [$t, $s, $e];
            }
        }
        if ($errors) {
            foreach (array_slice($errors, 0, 6) as $e) {
                Meetings::flash($e, 'error');
            }
            session()->now('meeting_flash', session('meeting_flash'));

            return $this->form($request->all(), false, $data['participants'], $meeting_id, count($following));
        }

        $wanted = collect($data['participants'])->push($meeting->organizer_id)->unique()->values()->all();
        $added = [];
        $rescheduled = false;
        DB::transaction(function () use ($plan, $data, $wanted, &$added, &$rescheduled) {
            foreach ($plan as [$t, $s, $e]) {
                DB::table('meetings')->where('id', $t->id)->update(['title' => $data['title'], 'description' => $data['description'], 'meeting_type' => $data['meeting_type'], 'start_time' => $s, 'end_time' => $e,
                    'room_id' => $data['room_id'], 'online_link' => $data['online_link'], 'quorum' => $data['quorum']]);
                $have = $this->m->participantIds($t->id);
                foreach (array_diff($wanted, $have) as $uid) {
                    DB::table('meeting_participants')->insert(['meeting_id' => $t->id, 'user_id' => $uid]);
                }
                DB::table('meeting_participants')->where('meeting_id', $t->id)->whereIn('user_id', array_diff($have, $wanted))->delete();
                $added = array_merge($added, array_diff($wanted, $have));
                $this->m->saveAudience($t->id, $data['departments'], $data['groups']);
                if ($s !== $t->start_time || $e !== $t->end_time || $data['room_id'] !== $t->room_id) {
                    $rescheduled = true;
                }
            }
        });

        $added = array_values(array_unique($added));
        $link = $this->detail($meeting_id);
        $when = date('D d M Y \a\t H:i', strtotime($data['start']));
        $scope = count($plan) > 1 ? ' (and '.(count($plan) - 1).' following meetings)' : '';
        $added && $this->m->notify($added, auth()->user()->name." invited you to “{$data['title']}” on {$when}{$scope}", $link);
        $rescheduled && $this->m->notify(array_diff($wanted, $added), "“{$data['title']}” has been rescheduled/moved: {$when}{$scope}", $link);
        Meetings::flash("Meeting updated{$scope}.");

        return redirect()->to($link);
    }

    public function status(Request $request, int $meeting_id): RedirectResponse
    {
        $meeting = $this->m->meeting($meeting_id, 'record');
        $status = (string) $request->input('status');
        abort_unless(isset(MeetingUi::MEETING_STATUSES[$status]), 400);
        abort_if($status === 'cancelled' && ! $this->m->canManage($meeting), 403);
        $targets = [$meeting];
        if ($status === 'cancelled' && $request->input('scope') === 'following') {
            $targets = array_filter($this->m->series($meeting, true), fn ($t) => $t->status !== 'completed');
        }
        DB::table('meetings')->whereIn('id', array_column($targets, 'id'))->update(['status' => $status]);
        if ($status === 'in_progress') {
            DB::table('meetings')->where('id', $meeting_id)->update(['started_at' => $meeting->started_at ?? Meetings::now()]);
        } elseif (in_array($status, ['completed', 'cancelled'], true) && $meeting->status === 'in_progress') {
            $this->m->closeCurrentItem($meeting);
            DB::table('meetings')->where('id', $meeting_id)->update(['ended_at' => $meeting->ended_at ?? Meetings::now(), 'current_agenda_id' => null]);
        }
        if ($status === 'cancelled') {
            $extra = count($targets) > 1 ? ' and '.(count($targets) - 1).' following meetings' : '';
            $this->m->notify($this->m->participantIds($meeting_id), "“{$meeting->title}” on ".MeetingUi::datetime($meeting->start_time)."{$extra} has been cancelled", $this->detail($meeting_id));
            Meetings::flash('Cancelled '.count($targets).' meeting(s).');
        } else {
            Meetings::flash('Meeting marked as '.strtolower(MeetingUi::MEETING_STATUSES[$status]).'.');
        }

        return redirect()->to($this->detail($meeting_id));
    }

    public function start(int $meeting_id): RedirectResponse
    {
        $meeting = $this->m->meeting($meeting_id, 'record');
        if ($meeting->status !== 'scheduled') {
            Meetings::flash('Only a scheduled meeting can be started.', 'error');

            return redirect()->to($this->detail($meeting_id));
        }
        DB::table('meetings')->where('id', $meeting_id)->update(['status' => 'in_progress', 'started_at' => Meetings::now(), 'ended_at' => null]);
        $first = DB::table('meeting_agenda_items')->where('meeting_id', $meeting_id)->orderBy('position')->orderBy('id')->value('id');
        $first && $this->m->openItem($meeting_id, $first);
        $this->m->notify($this->m->participantIds($meeting_id), "“{$meeting->title}” has started — follow the agenda live", $this->detail($meeting_id, 'agenda'));
        Meetings::flash('Meeting started.'.($first ? ' Now discussing the first agenda item.' : ''));

        return redirect()->to($this->detail($meeting_id, 'agenda'));
    }

    public function end(int $meeting_id): RedirectResponse
    {
        $meeting = $this->m->meeting($meeting_id, 'record');
        if ($meeting->status !== 'in_progress') {
            Meetings::flash('This meeting is not running.', 'error');

            return redirect()->to($this->detail($meeting_id));
        }
        $this->m->closeCurrentItem($meeting);
        DB::table('meetings')->where('id', $meeting_id)->update(['status' => 'completed', 'ended_at' => Meetings::now(), 'current_agenda_id' => null]);
        Meetings::flash('Meeting ended.');

        return redirect()->to($this->detail($meeting_id, 'minutes'));
    }

    /** Move the meeting to this agenda item. */
    public function agendaNow(int $item_id): RedirectResponse
    {
        $item = $this->m->child('meeting_agenda_items', $item_id);
        $meeting = $this->m->meeting($item->meeting_id, 'record');
        if ($meeting->status !== 'in_progress') {
            Meetings::flash('Start the meeting first.', 'error');
        } elseif ($meeting->current_agenda_id !== $item_id) {
            $this->m->closeCurrentItem($meeting);
            $this->m->openItem($meeting->id, $item_id);
        }

        return redirect()->to($this->detail($meeting->id, 'agenda'));
    }

    /** What the meeting is on right now; open meeting pages ask every few seconds. */
    public function live(int $meeting_id): JsonResponse
    {
        $meeting = $this->m->meeting($meeting_id);
        $current = null;
        if ($meeting->current_agenda_id) {
            $items = DB::table('meeting_agenda_items')->where('meeting_id', $meeting_id)->orderBy('position')->orderBy('id')->pluck('id')->all();
            $row = DB::table('meeting_agenda_items')->find($meeting->current_agenda_id);
            $at = array_search($meeting->current_agenda_id, $items);
            if ($row && $at !== false) {
                $current = ['id' => $meeting->current_agenda_id, 'number' => $at + 1, 'title' => $row->title, 'since' => MeetingUi::time($row->started_at)];
            }
        }

        return response()->json(['status' => $meeting->status, 'current' => $current]);
    }

    public function destroy(Request $request, int $meeting_id): RedirectResponse
    {
        $meeting = $this->m->meeting($meeting_id, 'manage');
        $targets = $request->input('scope') === 'following' ? $this->m->series($meeting, true) : [$meeting];
        foreach ($targets as $t) {
            foreach (DB::table('meeting_attachments')->where('meeting_id', $t->id)->pluck('stored_name') as $stored) {
                Storage::disk('local')->delete("meetings/{$stored}");
            }
            DB::table('meetings')->where('id', $t->id)->delete();
        }
        Meetings::flash('Deleted '.count($targets)." meeting(s): “{$meeting->title}”.");

        return redirect()->route('meetings.meetings_list');
    }

    public function show(int $meeting_id): View
    {
        $meeting = $this->m->meeting($meeting_id);
        $records = $this->m->records($meeting_id);
        $me = collect($records['participants'])->firstWhere('user_id', auth()->id());
        $needed = (int) ceil($records['total'] * $meeting->quorum / 100);
        $series = $meeting->series_id ? $this->m->series($meeting) : [];
        $index = (int) collect($series)->search(fn ($s) => $s->id === $meeting_id);
        $mi = $records['minutes'];

        return view('meetings.detail', $records + [
            'meeting' => $meeting, 'me' => $me, 'people_data' => $this->m->pickerData(), 'manage' => $this->m->canManage($meeting), 'record' => $this->m->canRecord($meeting),
            'audience' => $this->audience($meeting_id), 'series' => $series, 'series_index' => $index, 'arising' => $this->m->mattersArising($meeting),
            'steps' => [['Scheduled', true], ['Agenda', (bool) $records['agenda']], ['Attendance', $records['attendance_counts']->unknown < $records['total']],
                ['Minutes', $mi && in_array($mi->status, ['submitted', 'approved'], true)], ['Approved', $mi && $mi->status === 'approved']],
            'quorum_needed' => $needed, 'quorum_met' => $records['present'] >= $needed && $records['present'] > 0,
        ]);
    }

    /** @return list<\stdClass> */
    private function audience(int $meetingId): array
    {
        $departments = DB::table('departments')->pluck('name', 'id');
        $groups = DB::table('meeting_groups')->get()->keyBy('id');

        return DB::table('meeting_audience')->where('meeting_id', $meetingId)->get()->map(fn ($a) => (object) ['kind' => $a->kind, 'ref_id' => $a->ref_id,
            'name' => $a->kind === 'department' ? ($departments[$a->ref_id] ?? null) : ($groups[$a->ref_id]->name ?? null),
            'color' => $a->kind === 'group' ? ($groups[$a->ref_id]->color ?? null) : null])
            ->filter(fn ($a) => $a->name !== null)->sortBy([['kind', 'desc'], ['name', 'asc']])->values()->all();
    }

    public function rsvp(Request $request, int $meeting_id): RedirectResponse
    {
        $meeting = $this->m->meeting($meeting_id);
        $rsvp = (string) $request->input('rsvp');
        abort_unless(in_array($rsvp, ['accepted', 'declined', 'tentative'], true) && $this->m->isParticipant($meeting_id), 400);
        DB::table('meeting_participants')->where('meeting_id', $meeting_id)->where('user_id', auth()->id())->update(['rsvp' => $rsvp]);
        $label = MeetingUi::RSVP_STATUSES[$rsvp];
        $this->m->notify([$meeting->organizer_id], auth()->user()->name." replied “{$label}” to “{$meeting->title}”", $this->detail($meeting_id));
        Meetings::flash("Your reply was saved: {$label}.");

        return redirect()->to(Meetings::safeBack($request, $this->detail($meeting_id)));
    }

    public function attendance(Request $request, int $meeting_id): RedirectResponse
    {
        $this->m->meeting($meeting_id, 'record');
        foreach ($this->m->participantIds($meeting_id) as $uid) {
            $value = $request->input("att_{$uid}");
            if (is_string($value) && isset(MeetingUi::ATTENDANCE_STATUSES[$value])) {
                DB::table('meeting_participants')->where('meeting_id', $meeting_id)->where('user_id', $uid)->update(['attendance' => $value]);
            }
        }
        Meetings::flash('Attendance saved.');

        return redirect()->to($this->detail($meeting_id, 'participants'));
    }

    public function participantRemove(int $meeting_id, int $user_id): RedirectResponse
    {
        $meeting = $this->m->meeting($meeting_id, 'manage');
        if ($user_id === $meeting->organizer_id) {
            Meetings::flash('The organizer cannot be removed from the meeting.', 'error');
        } else {
            DB::table('meeting_participants')->where('meeting_id', $meeting_id)->where('user_id', $user_id)->delete();
            Meetings::flash('Participant removed.');
        }

        return redirect()->to($this->detail($meeting_id, 'participants'));
    }

    public function participantAdd(Request $request, int $meeting_id): RedirectResponse
    {
        $meeting = $this->m->meeting($meeting_id, 'manage');
        $departments = $this->m->intList($request, 'departments');
        $groups = $this->m->intList($request, 'groups');
        $new = $this->m->expandAudience($this->m->intList($request, 'participants'), $departments, $groups);
        $targets = $request->input('scope') === 'following' ? $this->m->series($meeting, true) : [$meeting];
        $added = [];
        foreach ($targets as $t) {
            $missing = array_diff($new, $this->m->participantIds($t->id));
            foreach ($missing as $uid) {
                DB::table('meeting_participants')->insert(['meeting_id' => $t->id, 'user_id' => $uid]);
            }
            $added = array_merge($added, $missing);
            foreach ([['department', $departments], ['group', $groups]] as [$kind, $ids]) {
                foreach ($ids as $ref) {
                    DB::table('meeting_audience')->insertOrIgnore(['meeting_id' => $t->id, 'kind' => $kind, 'ref_id' => $ref]);
                }
            }
        }
        $added = array_values(array_unique($added));
        if ($added) {
            $extra = count($targets) > 1 ? ' (and '.(count($targets) - 1).' following meetings)' : '';
            $this->m->notify($added, auth()->user()->name." invited you to “{$meeting->title}” on ".MeetingUi::datetime($meeting->start_time).$extra, $this->detail($meeting_id));
            Meetings::flash(count($added).' participant(s) invited.');
        } else {
            Meetings::flash('Everyone you selected is already invited.', 'info');
        }

        return redirect()->to($this->detail($meeting_id, 'participants'));
    }

    private function optionalInt(Request $request, string $name): ?int
    {
        $value = trim((string) $request->input($name, ''));

        return ctype_digit($value) ? (int) $value : null;
    }

    private function nextPosition(int $meetingId): int
    {
        return (int) (DB::table('meeting_agenda_items')->where('meeting_id', $meetingId)->max('position') ?? -1) + 1;
    }

    public function agendaAdd(Request $request, int $meeting_id): RedirectResponse
    {
        $this->m->meeting($meeting_id, 'record');
        $title = trim((string) $request->input('title', ''));
        if ($title === '') {
            Meetings::flash('Please enter an agenda item title.', 'error');
        } else {
            DB::table('meeting_agenda_items')->insert(['meeting_id' => $meeting_id, 'title' => $title, 'description' => trim((string) $request->input('description', '')),
                'presenter_id' => $this->optionalInt($request, 'presenter_id'), 'duration' => $this->optionalInt($request, 'duration'), 'position' => $this->nextPosition($meeting_id)]);
            Meetings::flash('Agenda item added.');
        }

        return redirect()->to($this->detail($meeting_id, 'agenda'));
    }

    /** Copy the agenda of the previous meeting in the same series. */
    public function agendaCopy(int $meeting_id): RedirectResponse
    {
        $meeting = $this->m->meeting($meeting_id, 'record');
        $prev = DB::table('meetings as m')->where('m.id', '!=', $meeting_id)->where('m.start_time', '<', $meeting->start_time)
            ->where(fn ($q) => $q->where('m.title', $meeting->title)->when($meeting->series_id, fn ($s) => $s->orWhere('m.series_id', $meeting->series_id)))
            ->whereExists(fn ($e) => $e->selectRaw('1')->from('meeting_agenda_items as i')->whereColumn('i.meeting_id', 'm.id'))->orderByDesc('m.start_time')->value('m.id');
        if (! $prev) {
            Meetings::flash('No earlier meeting with an agenda was found.', 'info');
        } else {
            $start = $this->nextPosition($meeting_id);
            $items = DB::table('meeting_agenda_items')->where('meeting_id', $prev)->orderBy('position')->orderBy('id')->get();
            foreach ($items->values() as $i => $a) {
                DB::table('meeting_agenda_items')->insert(['meeting_id' => $meeting_id, 'title' => $a->title, 'description' => $a->description, 'presenter_id' => $a->presenter_id, 'duration' => $a->duration, 'position' => $start + $i]);
            }
            Meetings::flash('Copied '.count($items).' agenda item(s) from the previous meeting.');
        }

        return redirect()->to($this->detail($meeting_id, 'agenda'));
    }

    public function agendaDelete(int $item_id): RedirectResponse
    {
        $item = $this->m->child('meeting_agenda_items', $item_id);
        $this->m->meeting($item->meeting_id, 'record');
        DB::table('meetings')->where('current_agenda_id', $item_id)->update(['current_agenda_id' => null]);
        DB::table('meeting_agenda_items')->where('id', $item_id)->delete();
        Meetings::flash('Agenda item removed.');

        return redirect()->to($this->detail($item->meeting_id, 'agenda'));
    }

    public function agendaMove(int $item_id, string $direction): RedirectResponse
    {
        $item = $this->m->child('meeting_agenda_items', $item_id);
        $this->m->meeting($item->meeting_id, 'record');
        $items = DB::table('meeting_agenda_items')->where('meeting_id', $item->meeting_id)->orderBy('position')->orderBy('id')->pluck('id')->all();
        $i = array_search($item_id, $items);
        $j = $direction === 'up' ? $i - 1 : $i + 1;
        if (isset($items[$j])) {
            [$items[$i], $items[$j]] = [$items[$j], $items[$i]];
            foreach ($items as $pos => $id) {
                DB::table('meeting_agenda_items')->where('id', $id)->update(['position' => $pos]);
            }
        }

        return redirect()->to($this->detail($item->meeting_id, 'agenda'));
    }

    public function minutes(Request $request, int $meeting_id): RedirectResponse
    {
        $meeting = $this->m->meeting($meeting_id);
        $existing = DB::table('meeting_minutes')->where('meeting_id', $meeting_id)->first();
        $action = (string) $request->input('action', 'save');
        $link = $this->detail($meeting_id, 'minutes');

        if (in_array($action, ['approve', 'reopen'], true)) {
            abort_unless($this->m->canManage($meeting) && $existing, 403);
            if ($action === 'approve') {
                DB::table('meeting_minutes')->where('meeting_id', $meeting_id)->update(['status' => 'approved', 'approved_by' => auth()->id(), 'approved_at' => Meetings::now()]);
                $this->m->notify($this->m->participantIds($meeting_id), "Minutes of “{$meeting->title}” have been approved", $link);
                Meetings::flash('Minutes approved and shared with all participants.');
            } else {
                DB::table('meeting_minutes')->where('meeting_id', $meeting_id)->update(['status' => 'draft', 'approved_by' => null, 'approved_at' => null]);
                Meetings::flash('Minutes returned to draft for editing.', 'info');
            }

            return redirect()->to($link);
        }

        abort_unless($this->m->canRecord($meeting), 403);
        if ($existing && $existing->status === 'approved') {
            Meetings::flash('Approved minutes are locked. Ask the organizer to reopen them.', 'error');

            return redirect()->to($link);
        }
        $status = $action === 'submit' ? 'submitted' : 'draft';
        DB::table('meeting_minutes')->updateOrInsert(['meeting_id' => $meeting_id], ['content' => (string) $request->input('content', ''), 'status' => $status, 'updated_by' => auth()->id(), 'updated_at' => Meetings::now()]);
        if ($status === 'submitted') {
            $this->m->notify([$meeting->organizer_id], "Minutes of “{$meeting->title}” are ready for your approval", $link);
            Meetings::flash('Minutes submitted for approval.');
        } else {
            Meetings::flash('Minutes saved as draft.');
        }

        return redirect()->to($link);
    }

    public function print(int $meeting_id): View
    {
        return view('meetings.print', ['meeting' => $this->m->meeting($meeting_id)] + $this->m->records($meeting_id));
    }

    public function decisionAdd(Request $request, int $meeting_id): RedirectResponse
    {
        $this->m->meeting($meeting_id, 'record');
        $text = trim((string) $request->input('text', ''));
        $outcome = (string) $request->input('outcome', '');
        if ($text !== '') {
            DB::table('meeting_decisions')->insert(['meeting_id' => $meeting_id, 'agenda_item_id' => $this->optionalInt($request, 'agenda_item_id'), 'text' => $text,
                'proposer_id' => $this->optionalInt($request, 'proposer_id'), 'seconder_id' => $this->optionalInt($request, 'seconder_id'), 'votes_for' => $this->optionalInt($request, 'votes_for'),
                'votes_against' => $this->optionalInt($request, 'votes_against'), 'votes_abstain' => $this->optionalInt($request, 'votes_abstain'),
                'outcome' => isset(MeetingUi::DECISION_OUTCOMES[$outcome]) ? $outcome : null, 'created_at' => Meetings::now('Y-m-d H:i:s')]);
            Meetings::flash('Decision recorded.');
        }

        return redirect()->to($this->detail($meeting_id, 'decisions'));
    }

    public function decisionDelete(int $item_id): RedirectResponse
    {
        $item = $this->m->child('meeting_decisions', $item_id);
        $this->m->meeting($item->meeting_id, 'record');
        DB::table('meeting_decisions')->where('id', $item_id)->delete();
        Meetings::flash('Decision removed.');

        return redirect()->to($this->detail($item->meeting_id, 'decisions'));
    }

    public function actionAdd(Request $request, int $meeting_id): RedirectResponse
    {
        $meeting = $this->m->meeting($meeting_id, 'record');
        $title = trim((string) $request->input('title', ''));
        $due = trim((string) $request->input('due_date', '')) ?: null;
        if ($title === '') {
            Meetings::flash('Please describe the action item.', 'error');
        } else {
            $assignee = $this->optionalInt($request, 'assignee_id');
            DB::table('meeting_action_items')->insert(['meeting_id' => $meeting_id, 'title' => $title, 'description' => trim((string) $request->input('description', '')),
                'assignee_id' => $assignee, 'due_date' => $due, 'created_at' => Meetings::now('Y-m-d H:i:s')]);
            if ($assignee) {
                $this->m->notify([$assignee], "New task from “{$meeting->title}”: {$title}".($due ? ' (due '.MeetingUi::date($due).')' : ''), route('meetings.tasks'));
            }
            Meetings::flash('Action item added.');
        }

        return redirect()->to($this->detail($meeting_id, 'actions'));
    }

    public function actionStatus(Request $request, int $item_id): RedirectResponse
    {
        $item = $this->m->child('meeting_action_items', $item_id);
        $meeting = $this->m->meeting($item->meeting_id, null);
        abort_unless($item->assignee_id === auth()->id() || $this->m->canRecord($meeting), 403);
        $status = (string) $request->input('status');
        abort_unless(isset(MeetingUi::ACTION_STATUSES[$status]), 400);
        DB::table('meeting_action_items')->where('id', $item_id)->update(['status' => $status, 'completed_at' => $status === 'done' ? Meetings::now() : null]);
        if ($status === 'done' && $item->assignee_id === auth()->id()) {
            $this->m->notify([$meeting->organizer_id], auth()->user()->name." completed: {$item->title}", $this->detail($meeting->id, 'actions'));
        }
        Meetings::flash('Task updated.');

        return redirect()->to(Meetings::safeBack($request, route('meetings.tasks')));
    }

    public function actionDelete(int $item_id): RedirectResponse
    {
        $item = $this->m->child('meeting_action_items', $item_id);
        $this->m->meeting($item->meeting_id, 'record');
        DB::table('meeting_action_items')->where('id', $item_id)->delete();
        Meetings::flash('Action item removed.');

        return redirect()->to($this->detail($item->meeting_id, 'actions'));
    }

    public function attachmentUpload(Request $request, int $meeting_id): RedirectResponse
    {
        $meeting = $this->m->meeting($meeting_id);
        abort_unless($this->m->canRecord($meeting) || $this->m->isParticipant($meeting_id), 403);
        $item = null;
        if (ctype_digit((string) $request->input('agenda_item_id', ''))) {
            $item = DB::table('meeting_agenda_items')->where('id', $request->input('agenda_item_id'))->where('meeting_id', $meeting_id)->first(['id', 'title']);
            abort_unless($item, 400);
        }
        $link = $this->detail($meeting_id, $request->input('back') === 'agenda' ? 'agenda' : 'files');
        $file = $request->file('file');
        if (! $file || ! $file->isValid()) {
            Meetings::flash('Please choose a file to upload (maximum 20 MB).', 'error');

            return redirect()->to($link);
        }
        if ($file->getSize() > 20 * 1024 * 1024) {
            Meetings::flash('The file is too large (maximum 20 MB).', 'error');

            return redirect()->to($link);
        }
        $original = trim(preg_replace('/[^A-Za-z0-9._-]+/', '_', $file->getClientOriginalName()), '._') ?: 'file';
        $ext = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        if (! in_array($ext, Meetings::ALLOWED_EXTENSIONS, true)) {
            $allowed = Meetings::ALLOWED_EXTENSIONS;
            sort($allowed);
            Meetings::flash('That file type is not allowed. Allowed: '.implode(', ', $allowed), 'error');

            return redirect()->to($link);
        }
        $stored = Str::random(32).".{$ext}";
        $size = $file->getSize();
        $file->storeAs('meetings', $stored, 'local');
        DB::table('meeting_attachments')->insert(['meeting_id' => $meeting_id, 'filename' => $original, 'stored_name' => $stored, 'size' => $size, 'uploaded_by' => auth()->id(),
            'agenda_item_id' => $item?->id, 'uploaded_at' => Meetings::now('Y-m-d H:i:s')]);
        $this->m->notify([$meeting->organizer_id], auth()->user()->name." uploaded “{$original}”".($item ? " for “{$item->title}”" : '')." in “{$meeting->title}”", $link);
        Meetings::flash("Uploaded {$original}".($item ? " for “{$item->title}”." : '.'));

        return redirect()->to($link);
    }

    public function attachmentDownload(int $item_id): Response
    {
        $item = $this->m->child('meeting_attachments', $item_id);
        $this->m->meeting($item->meeting_id);
        abort_unless(Storage::disk('local')->exists("meetings/{$item->stored_name}"), 404);

        return Storage::disk('local')->download("meetings/{$item->stored_name}", $item->filename);
    }

    public function attachmentDelete(Request $request, int $item_id): RedirectResponse
    {
        $item = $this->m->child('meeting_attachments', $item_id);
        $meeting = $this->m->meeting($item->meeting_id);
        abort_unless($this->m->canRecord($meeting) || $item->uploaded_by === auth()->id(), 403);
        Storage::disk('local')->delete("meetings/{$item->stored_name}");
        DB::table('meeting_attachments')->where('id', $item_id)->delete();
        Meetings::flash('File deleted.');

        return redirect()->to($this->detail($item->meeting_id, $request->input('back') === 'agenda' ? 'agenda' : 'files'));
    }

    public function ics(Request $request, int $meeting_id): Response
    {
        $m = $this->m->meeting($meeting_id);
        $events = [$m];
        if ($request->query('series') && $m->series_id) {
            $events = DB::table('meetings as m')->leftJoin('meeting_rooms as r', 'r.id', '=', 'm.room_id')->where('m.series_id', $m->series_id)->orderBy('m.start_time')->get(['m.*', 'r.name as room_name'])->all();
        }

        return MeetingUi::icsResponse($events, Str::slug($m->title, '_') ?: 'meeting');
    }
}
