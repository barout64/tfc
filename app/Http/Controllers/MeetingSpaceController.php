<?php

namespace App\Http\Controllers;

use App\Services\Meetings;
use App\Support\MeetingUi;
use Carbon\CarbonImmutable;
use Illuminate\Database\QueryException;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\Response;

/** Everything around the meetings: the dashboard, tasks, decisions, calendar, year planner, groups, rooms, reports. */
class MeetingSpaceController extends Controller
{
    public function __construct(private Meetings $m) {}

    private function withCounts($query)
    {
        return $query->leftJoin('meeting_rooms as r', 'r.id', '=', 'm.room_id')->select('m.*', 'r.name as room_name')
            ->selectSub(fn ($s) => $s->select('rsvp')->from('meeting_participants')->whereColumn('meeting_id', 'm.id')->where('user_id', auth()->id()), 'my_rsvp')
            ->selectSub(fn ($s) => $s->selectRaw('count(*)')->from('meeting_participants')->whereColumn('meeting_id', 'm.id'), 'participant_count');
    }

    public function dashboard(): View
    {
        $me = auth()->user();
        $today = Meetings::today();
        $now = Meetings::now();
        $visible = fn () => $this->m->visible(DB::table('meetings as m'));
        $todayMeetings = $this->withCounts($visible())->whereRaw('substr(m.start_time, 1, 10) = ?', [$today])->where('m.status', '!=', 'cancelled')->orderBy('m.start_time')->get()->all();
        $upcoming = $this->withCounts($visible())->whereRaw('substr(m.start_time, 1, 10) > ?', [$today])->where('m.status', '!=', 'cancelled')->orderBy('m.start_time')->limit(6)->get()->all();
        $tasks = fn () => DB::table('meeting_action_items as a')->where('a.assignee_id', $me->id)->where('a.status', '!=', 'done');
        $myTasks = $tasks()->join('meetings as m', 'm.id', '=', 'a.meeting_id')->orderByRaw('a.due_date is null')->orderBy('a.due_date')->limit(6)->get(['a.*', 'm.title as meeting_title'])->all();
        $pending = DB::table('meetings as m')->join('meeting_participants as p', 'p.meeting_id', '=', 'm.id')->where('p.user_id', $me->id)->where('p.rsvp', 'pending')
            ->where('m.start_time', '>=', $now)->where('m.status', 'scheduled')->orderBy('m.start_time')->limit(5)->get(['m.*'])->all();
        $toApprove = in_array($this->m->role(), ['admin', 'organizer'], true)
            ? DB::table('meeting_minutes as mi')->join('meetings as m', 'm.id', '=', 'mi.meeting_id')->where('mi.status', 'submitted')
                ->when(! $this->m->isAdmin(), fn ($q) => $q->where('m.organizer_id', $me->id))->get(['m.id', 'm.title', 'm.start_time'])->all()
            : [];
        $hour = (int) now(Meetings::TZ)->format('G');

        return view('meetings.dashboard', [
            'today_meetings' => $todayMeetings, 'upcoming' => $upcoming, 'my_tasks' => $myTasks, 'pending_rsvp' => $pending, 'minutes_to_approve' => $toApprove,
            'greeting' => $hour < 12 ? 'Good morning' : ($hour < 17 ? 'Good afternoon' : 'Good evening'),
            'stats' => (object) [
                'today' => count($todayMeetings),
                'week' => $visible()->where('m.status', '!=', 'cancelled')->whereRaw('substr(m.start_time, 1, 10) between ? and ?', [$today, now(Meetings::TZ)->addDays(7)->format('Y-m-d')])->count(),
                'year_left' => $visible()->where('m.status', 'scheduled')->whereRaw('substr(m.start_time, 1, 4) = ?', [substr($today, 0, 4)])->where('m.start_time', '>=', $now)->count(),
                'open_tasks' => $tasks()->count(),
                'overdue' => $tasks()->where('a.due_date', '<', $today)->count(),
            ],
        ]);
    }

    public function profile(): View
    {
        $id = auth()->id();

        return view('meetings.profile', [
            'stats' => (object) [
                'meetings' => DB::table('meeting_participants')->where('user_id', $id)->count(),
                'attended' => DB::table('meeting_participants')->where('user_id', $id)->where('attendance', 'present')->count(),
                'open_tasks' => DB::table('meeting_action_items')->where('assignee_id', $id)->where('status', '!=', 'done')->count(),
            ],
            'my_groups' => DB::table('meeting_groups as g')->join('meeting_group_members as gm', 'gm.group_id', '=', 'g.id')->where('gm.user_id', $id)->orderBy('g.name')->get(['g.*'])->all(),
        ]);
    }

    public function decisions(Request $request): View
    {
        $q = trim((string) $request->query('q', ''));
        $outcome = (string) $request->query('outcome', '');
        $decisions = $this->m->visible(DB::table('meetings as m'))->join('meeting_decisions as d', 'd.meeting_id', '=', 'm.id')
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('d.text', 'like', "%{$q}%")->orWhere('m.title', 'like', "%{$q}%")))
            ->when(isset(MeetingUi::DECISION_OUTCOMES[$outcome]), fn ($w) => $w->where('d.outcome', $outcome))
            ->orderByDesc('m.start_time')->orderBy('d.id')->get(['d.*', 'm.title as meeting_title', 'm.start_time', 'm.meeting_type'])->all();

        return view('meetings.decisions', compact('decisions', 'q', 'outcome'));
    }

    public function tasks(Request $request): View
    {
        $scope = (string) $request->query('scope', 'mine');
        $status = (string) $request->query('status', 'pending');
        $today = Meetings::today();
        $all = $scope === 'all' && $this->m->canCreate();
        $scope = $all ? 'all' : 'mine';
        $items = ($all ? $this->m->visible(DB::table('meetings as m')) : DB::table('meetings as m')->where('a.assignee_id', auth()->id()))
            ->join('meeting_action_items as a', 'a.meeting_id', '=', 'm.id')->leftJoin('users as u', 'u.id', '=', 'a.assignee_id')
            ->when($status === 'pending', fn ($q) => $q->where('a.status', '!=', 'done'))
            ->when($status === 'overdue', fn ($q) => $q->where('a.status', '!=', 'done')->where('a.due_date', '<', $today))
            ->when(isset(MeetingUi::ACTION_STATUSES[$status]), fn ($q) => $q->where('a.status', $status))
            ->orderByRaw("a.status = 'done'")->orderByRaw('a.due_date is null')->orderBy('a.due_date')
            ->get(['a.*', 'm.title as meeting_title', 'm.organizer_id', 'u.name as assignee_name'])->all();
        foreach ($items as $a) {
            $a->editable = $a->assignee_id === auth()->id() || $this->m->canRecord((object) ['id' => $a->meeting_id, 'organizer_id' => $a->organizer_id]);
        }

        return view('meetings.tasks', compact('items', 'scope', 'status'));
    }

    /** Monday-first weeks covering a month. @return list<list<CarbonImmutable>> */
    private function weeks(CarbonImmutable $first): array
    {
        $day = $first->subDays($first->dayOfWeekIso - 1);
        $last = $first->endOfMonth()->startOfDay();
        $weeks = [];
        while ($day->lessThanOrEqualTo($last)) {
            $week = [];
            for ($i = 0; $i < 7; $i++) {
                $week[] = $day->addDays($i);
            }
            $weeks[] = $week;
            $day = $day->addDays(7);
        }

        return $weeks;
    }

    public function calendar(Request $request): View
    {
        $month = (string) $request->query('month', '');
        $first = preg_match('/^\d{4}-(0[1-9]|1[0-2])$/', $month) ? CarbonImmutable::parse("{$month}-01", Meetings::TZ) : now(Meetings::TZ)->toImmutable()->startOfMonth()->startOfDay();
        $weeks = $this->weeks($first);
        $meetings = $this->m->visible(DB::table('meetings as m'))
            ->whereRaw('substr(m.start_time, 1, 10) between ? and ?', [$weeks[0][0]->format('Y-m-d'), $weeks[array_key_last($weeks)][6]->format('Y-m-d')])
            ->orderBy('m.start_time')->get(['m.id', 'm.title', 'm.start_time', 'm.end_time', 'm.status', 'm.meeting_type']);

        return view('meetings.calendar', ['weeks' => $weeks, 'first' => $first, 'by_day' => $meetings->groupBy(fn ($m) => substr($m->start_time, 0, 10))->map->all()->all(),
            'prev_month' => $first->subMonthNoOverflow()->format('Y-m'), 'next_month' => $first->addMonthNoOverflow()->format('Y-m')]);
    }

    public function planner(Request $request): View|Response
    {
        $year = (int) $request->query('year', 0) ?: (int) now(Meetings::TZ)->format('Y');
        $mtype = (string) $request->query('type', '');
        $query = fn () => $this->m->visible(DB::table('meetings as m'))->leftJoin('meeting_rooms as r', 'r.id', '=', 'm.room_id')->whereRaw('substr(m.start_time, 1, 4) = ?', [(string) $year])
            ->when(in_array($mtype, MeetingUi::MEETING_TYPES, true), fn ($q) => $q->where('m.meeting_type', $mtype))->orderBy('m.start_time');
        if ($request->query('format') === 'ics') {
            return MeetingUi::icsResponse($query()->where('m.status', '!=', 'cancelled')->get(['m.*', 'r.name as room_name'])->all(), "meeting-plan-{$year}");
        }
        $meetings = $query()->get(['m.id', 'm.title', 'm.start_time', 'm.end_time', 'm.status', 'm.meeting_type', 'm.series_id', 'r.name as room_name']);
        $live = $meetings->where('status', '!=', 'cancelled');
        $months = collect(range(1, 12))->map(fn ($i) => (object) ['num' => $i, 'name' => date('F', mktime(0, 0, 0, $i, 1)), 'weeks' => $this->weeks(CarbonImmutable::create($year, $i, 1, 0, 0, 0, Meetings::TZ)),
            'meetings' => $meetings->filter(fn ($m) => (int) substr($m->start_time, 5, 2) === $i)->values()->all()])->all();

        return view('meetings.planner', ['year' => $year, 'months' => $months, 'mtype' => $mtype, 'by_day' => $meetings->groupBy(fn ($m) => substr($m->start_time, 0, 10))->map->all()->all(),
            'type_counts' => $live->countBy('meeting_type')->all(), 'total' => $live->count()]);
    }

    public function groups(): View
    {
        $items = DB::table('meeting_groups')->orderBy('name')->get()->map(function ($g) {
            $members = DB::table('meeting_group_members as gm')->join('users as u', 'u.id', '=', 'gm.user_id')->where('gm.group_id', $g->id)->where('u.is_active', true)->orderBy('u.name')->get(['u.id', 'u.name', 'u.job_title'])->all();

            return (object) ['group' => $g, 'members' => $members, 'is_member' => in_array(auth()->id(), array_column($members, 'id')),
                'upcoming' => DB::table('meeting_audience as a')->join('meetings as m', 'm.id', '=', 'a.meeting_id')->where('a.kind', 'group')->where('a.ref_id', $g->id)->where('m.start_time', '>=', Meetings::now())->where('m.status', 'scheduled')->count()];
        })->all();

        return view('meetings.groups', compact('items'));
    }

    public function groupForm(Request $request, ?int $group_id = null): View|RedirectResponse
    {
        abort_unless($this->m->canManageGroups(), 403);
        $group = $group_id ? DB::table('meeting_groups')->find($group_id) : null;
        abort_if($group_id && ! $group, 404);
        $colors = MeetingUi::GROUP_COLORS;
        if ($request->isMethod('post')) {
            $name = trim((string) $request->input('name', ''));
            $color = in_array($request->input('color'), $colors, true) ? $request->input('color') : $colors[0];
            $description = trim((string) $request->input('description', ''));
            $members = $this->m->expandAudience($this->m->intList($request, 'members'), $this->m->intList($request, 'departments'), []);
            if ($name === '') {
                Meetings::flash('Please give the group a name.', 'error');
            } else {
                try {
                    DB::transaction(function () use ($group, &$group_id, $name, $description, $color, $members) {
                        if ($group) {
                            DB::table('meeting_groups')->where('id', $group_id)->update(['name' => $name, 'description' => $description, 'color' => $color]);
                        } else {
                            $group_id = DB::table('meeting_groups')->insertGetId(['name' => $name, 'description' => $description, 'color' => $color, 'created_by' => auth()->id(), 'created_at' => Meetings::now('Y-m-d H:i:s')]);
                        }
                        DB::table('meeting_group_members')->where('group_id', $group_id)->delete();
                        foreach ($members as $uid) {
                            DB::table('meeting_group_members')->insert(['group_id' => $group_id, 'user_id' => $uid]);
                        }
                    });
                    Meetings::flash("Group “{$name}” saved with ".count($members).' member(s).');

                    return redirect()->route('meetings.groups');
                } catch (QueryException) {
                    Meetings::flash('A group with that name already exists.', 'error');
                }
            }
            session()->now('meeting_flash', session('meeting_flash'));
            $form = (object) ['name' => $name, 'description' => $description, 'color' => $color];
            $selected = $members;
        } else {
            $form = $group ?? (object) ['name' => null, 'description' => null, 'color' => $colors[0]];
            $selected = $group ? DB::table('meeting_group_members')->where('group_id', $group_id)->pluck('user_id')->all() : [];
        }

        return view('meetings.group_form', ['form' => $form, 'editing' => (bool) $group, 'group_id' => $group_id, 'people_data' => $this->m->pickerData(), 'selected' => $selected, 'colors' => $colors]);
    }

    public function groupDelete(int $group_id): RedirectResponse
    {
        abort_unless($this->m->canManageGroups(), 403);
        DB::table('meeting_audience')->where('kind', 'group')->where('ref_id', $group_id)->delete();
        DB::table('meeting_groups')->where('id', $group_id)->delete();
        Meetings::flash('Group deleted. Past invitations are kept.');

        return redirect()->route('meetings.groups');
    }

    public function rooms(Request $request): View|RedirectResponse
    {
        if ($request->isMethod('post')) {
            abort_unless($this->m->isAdmin(), 403);
            $name = trim((string) $request->input('name', ''));
            $roomId = (string) $request->input('room_id', '');
            $capacity = $request->input('capacity', 10);
            $values = ['name' => $name, 'capacity' => is_numeric($capacity) ? max(1, (int) $capacity) : 10, 'location' => trim((string) $request->input('location', '')), 'equipment' => trim((string) $request->input('equipment', ''))];
            if ($name === '') {
                Meetings::flash('Room name is required.', 'error');
            } else {
                try {
                    if (ctype_digit($roomId)) {
                        DB::table('meeting_rooms')->where('id', $roomId)->update($values);
                        Meetings::flash('Room updated.');
                    } else {
                        DB::table('meeting_rooms')->insert($values);
                        Meetings::flash('Room added.');
                    }
                } catch (QueryException) {
                    Meetings::flash('A room with that name already exists.', 'error');
                }
            }

            return redirect()->route('meetings.rooms');
        }
        $day = Meetings::date((string) $request->query('day', '')) ?? CarbonImmutable::parse(Meetings::today(), Meetings::TZ);
        $bookings = DB::table('meetings as m')->join('users as u', 'u.id', '=', 'm.organizer_id')->whereNotNull('m.room_id')->where('m.status', '!=', 'cancelled')
            ->whereRaw('substr(m.start_time, 1, 10) = ?', [$day->format('Y-m-d')])->orderBy('m.start_time')->get(['m.id', 'm.title', 'm.start_time', 'm.end_time', 'm.room_id', 'm.meeting_type', 'u.name as organizer_name']);

        return view('meetings.rooms', ['rooms' => DB::table('meeting_rooms')->orderBy('name')->get()->all(), 'by_room' => $bookings->groupBy('room_id')->map->all()->all(), 'day' => $day->format('Y-m-d'),
            'prev_day' => $day->subDay()->format('Y-m-d'), 'next_day' => $day->addDay()->format('Y-m-d')]);
    }

    public function roomDelete(int $room_id): RedirectResponse
    {
        abort_unless($this->m->isAdmin(), 403);
        DB::table('meeting_rooms')->where('id', $room_id)->delete();
        Meetings::flash('Room deleted. Meetings that used it now have no room.');

        return redirect()->route('meetings.rooms');
    }

    public function notifications(): View
    {
        return view('meetings.notifications', ['items' => DB::table('meeting_notifications')->where('user_id', auth()->id())->orderByDesc('id')->limit(100)->get()->all()]);
    }

    public function notificationOpen(int $item_id): RedirectResponse
    {
        $item = DB::table('meeting_notifications')->where('id', $item_id)->where('user_id', auth()->id())->first();
        abort_unless($item, 404);
        DB::table('meeting_notifications')->where('id', $item_id)->update(['is_read' => true]);
        $link = (string) $item->link;
        $inside = str_starts_with($link, '/') && ! str_starts_with($link, '//') || str_starts_with($link, url('/').'/');

        return redirect()->to($inside ? $link : route('meetings.notifications'));
    }

    public function notificationsReadAll(): RedirectResponse
    {
        DB::table('meeting_notifications')->where('user_id', auth()->id())->update(['is_read' => true]);

        return redirect()->route('meetings.notifications');
    }

    public function reports(Request $request): View
    {
        abort_unless($this->m->canCreate(), 403);
        $year = (string) $request->query('year', '');
        $year = preg_match('/^\d{4}$/', $year) ? $year : substr(Meetings::today(), 0, 4);
        $base = fn () => $this->m->visible(DB::table('meetings as m'))->whereRaw('substr(m.start_time, 1, 4) = ?', [$year]);
        $byStatus = $base()->groupBy('m.status')->selectRaw('m.status, count(*) as n')->pluck('n', 'status')->all();
        $byMonth = $base()->where('m.status', '!=', 'cancelled')->selectRaw('substr(m.start_time, 6, 2) as mon, count(*) as n')->groupByRaw('substr(m.start_time, 6, 2)')->pluck('n', 'mon')->all();
        $months = array_map(fn ($i) => [date('M', mktime(0, 0, 0, $i, 1)), (int) ($byMonth[sprintf('%02d', $i)] ?? 0)], range(1, 12));
        $byType = $base()->where('m.status', '!=', 'cancelled')->selectRaw('m.meeting_type as label, count(*) as n')->groupBy('m.meeting_type')->orderByDesc('n')->get()->all();
        $attendance = $base()->join('meeting_participants as p', 'p.meeting_id', '=', 'm.id')->join('users as u', 'u.id', '=', 'p.user_id')->leftJoin('departments as d', 'd.id', '=', 'u.department_id')
            ->where('p.attendance', '!=', 'unknown')->selectRaw("coalesce(d.name, 'No department') as label, sum(p.attendance = 'present') as present, sum(p.attendance = 'absent') as absent, sum(p.attendance = 'excused') as excused, count(*) as recorded")
            ->groupByRaw("coalesce(d.name, 'No department')")->orderBy('label')->get()->all();
        $actions = $base()->join('meeting_action_items as a', 'a.meeting_id', '=', 'm.id')->leftJoin('users as u', 'u.id', '=', 'a.assignee_id')
            ->selectRaw("coalesce(u.name, 'Unassigned') as label, count(*) as total, sum(a.status = 'done') as done, sum(a.status != 'done' and a.due_date < ?) as overdue", [Meetings::today()])
            ->groupByRaw("coalesce(u.name, 'Unassigned')")->orderByDesc('total')->get()->all();
        $roomUsage = $base()->join('meeting_rooms as r', 'r.id', '=', 'm.room_id')->where('m.status', '!=', 'cancelled')->get(['r.id', 'r.name', 'm.start_time', 'm.end_time'])->groupBy('id')
            ->map(fn ($g) => (object) ['label' => $g->first()->name, 'n' => $g->count(), 'hours' => $g->sum(fn ($m) => (strtotime($m->end_time) - strtotime($m->start_time)) / 3600)])->sortByDesc('n')->values()->all();

        return view('meetings.reports', ['year' => $year, 'months' => $months, 'by_type' => $byType, 'attendance' => $attendance, 'actions' => $actions, 'room_usage' => $roomUsage,
            'max_month' => max([...array_column($months, 1), 1]),
            'totals' => (object) ['meetings' => array_sum($byStatus), 'completed' => $byStatus['completed'] ?? 0, 'cancelled' => $byStatus['cancelled'] ?? 0,
                'actions' => array_sum(array_column($actions, 'total')), 'actions_done' => array_sum(array_column($actions, 'done')),
                'present' => array_sum(array_column($attendance, 'present')), 'recorded' => array_sum(array_column($attendance, 'recorded'))]]);
    }

    public function departments(Request $request): View|RedirectResponse
    {
        abort_unless($this->m->isAdmin(), 403);
        if ($request->isMethod('post')) {
            $action = $request->input('action');
            $id = (string) $request->input('id', '');
            $name = trim((string) $request->input('name', ''));
            try {
                if ($action === 'add' && $name !== '') {
                    DB::table('departments')->insert(['name' => $name, 'created_at' => now(), 'updated_at' => now()]);
                    Meetings::flash('Department added.');
                } elseif ($action === 'rename' && $name !== '' && ctype_digit($id)) {
                    DB::table('departments')->where('id', $id)->update(['name' => $name, 'updated_at' => now()]);
                    Meetings::flash('Department renamed.');
                } elseif ($action === 'delete' && ctype_digit($id)) {
                    DB::table('meeting_audience')->where('kind', 'department')->where('ref_id', $id)->delete();
                    DB::table('departments')->where('id', $id)->delete();
                    Meetings::flash('Department deleted.');
                }
            } catch (QueryException) {
                Meetings::flash('A department with that name already exists.', 'error');
            }

            return redirect()->route('meetings.departments');
        }
        $items = DB::table('departments as d')->orderBy('d.name')->select('d.*')->selectSub(fn ($s) => $s->selectRaw('count(*)')->from('users as u')->whereColumn('u.department_id', 'd.id'), 'members')->get()->all();

        return view('meetings.departments', compact('items'));
    }
}
