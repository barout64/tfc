<?php

namespace App\Services;

use Carbon\CarbonImmutable;
use Illuminate\Database\Query\Builder;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use stdClass;

/** TFC Meetings: who may do what, and what the pages share. Times are Tanzania local text ('YYYY-MM-DD HH:MM'). */
class Meetings
{
    public const TZ = 'Africa/Dar_es_Salaam';

    public const MAX_OCCURRENCES = 120;

    public const ALLOWED_EXTENSIONS = ['pdf', 'doc', 'docx', 'xls', 'xlsx', 'ppt', 'pptx', 'txt', 'csv', 'png', 'jpg', 'jpeg', 'gif', 'zip'];

    public static function now(string $format = 'Y-m-d H:i'): string
    {
        return now(self::TZ)->format($format);
    }

    public static function today(): string
    {
        return self::now('Y-m-d');
    }

    /** A message on the next page: success, error, warning or info. */
    public static function flash(string $message, string $category = 'success'): void
    {
        session()->flash('meeting_flash', [...session('meeting_flash', []), [$category, $message]]);
    }

    public function user(): \App\Models\User
    {
        return auth()->user();
    }

    public function role(): string
    {
        return $this->user()->meetingRole();
    }

    public function isAdmin(): bool
    {
        return $this->role() === 'admin';
    }

    public function canCreate(): bool
    {
        return in_array($this->role(), ['admin', 'organizer', 'secretary'], true);
    }

    public function canManageGroups(): bool
    {
        return in_array($this->role(), ['admin', 'organizer'], true);
    }

    public function isParticipant(int $meetingId, ?int $userId = null): bool
    {
        return DB::table('meeting_participants')->where('meeting_id', $meetingId)->where('user_id', $userId ?? $this->user()->id)->exists();
    }

    public function canView(stdClass $meeting): bool
    {
        return $this->isAdmin() || $meeting->organizer_id === $this->user()->id || $this->isParticipant($meeting->id);
    }

    /** Edit details, invite people, approve minutes, cancel. */
    public function canManage(stdClass $meeting): bool
    {
        return $this->isAdmin() || $meeting->organizer_id === $this->user()->id;
    }

    /** Record attendance, minutes, decisions, action items, agenda. */
    public function canRecord(stdClass $meeting): bool
    {
        return $this->canManage($meeting) || ($this->role() === 'secretary' && $this->isParticipant($meeting->id));
    }

    /** The meeting, if this person may reach it: 'view', 'manage', 'record' or null (no check). */
    public function meeting(int $id, ?string $check = 'view'): stdClass
    {
        $meeting = DB::table('meetings as m')->leftJoin('meeting_rooms as r', 'r.id', '=', 'm.room_id')->join('users as u', 'u.id', '=', 'm.organizer_id')
            ->where('m.id', $id)->select('m.*', 'r.name as room_name', 'r.location as room_location', 'r.capacity as room_capacity', 'u.name as organizer_name')->first();
        abort_unless($meeting, 404);
        abort_if($check && ! $this->{'can'.ucfirst($check)}($meeting), 403);

        return $meeting;
    }

    public function child(string $table, int $id): stdClass
    {
        $row = DB::table($table)->find($id);
        abort_unless($row, 404);

        return $row;
    }

    /** Limit a query on "meetings as m" to the meetings this person may see. */
    public function visible(Builder $query): Builder
    {
        if ($this->isAdmin()) {
            return $query;
        }
        $id = $this->user()->id;

        return $query->where(fn ($q) => $q->where('m.organizer_id', $id)
            ->orWhereExists(fn ($e) => $e->selectRaw('1')->from('meeting_participants as p2')->whereColumn('p2.meeting_id', 'm.id')->where('p2.user_id', $id)));
    }

    /** @param  iterable<int>  $userIds */
    public function notify(iterable $userIds, string $message, ?string $link = null): void
    {
        $me = auth()->id();
        foreach (collect($userIds)->unique() as $id) {
            if ($id !== $me) {
                DB::table('meeting_notifications')->insert(['user_id' => $id, 'message' => $message, 'link' => $link, 'is_read' => false, 'created_at' => self::now('Y-m-d H:i:s')]);
            }
        }
    }

    /** @return list<int> */
    public function participantIds(int $meetingId): array
    {
        return DB::table('meeting_participants')->where('meeting_id', $meetingId)->pluck('user_id')->map(fn ($id) => (int) $id)->all();
    }

    /** @return list<int> */
    public function intList(Request $request, string $name): array
    {
        return collect((array) $request->input($name, []))->filter(fn ($v) => ctype_digit((string) $v))->map(fn ($v) => (int) $v)->values()->all();
    }

    /** People, departments and groups turned into the active people they mean. @return list<int> */
    public function expandAudience(array $userIds, array $departmentIds, array $groupIds): array
    {
        $ids = collect($userIds);
        if ($departmentIds) {
            $ids = $ids->merge(DB::table('users')->where('is_active', true)->whereIn('department_id', $departmentIds)->pluck('id'));
        }
        if ($groupIds) {
            $ids = $ids->merge(DB::table('meeting_group_members as gm')->join('users as u', 'u.id', '=', 'gm.user_id')->where('u.is_active', true)->whereIn('gm.group_id', $groupIds)->pluck('gm.user_id'));
        }

        return $ids->map(fn ($id) => (int) $id)->unique()->values()->all();
    }

    /** People, departments and groups for the participant picker. */
    public function pickerData(): stdClass
    {
        $users = DB::table('users as u')->leftJoin('departments as d', 'd.id', '=', 'u.department_id')->where('u.is_active', true)
            ->orderBy('u.name')->get(['u.id', 'u.name', 'u.job_title', 'u.department_id', 'd.name as department']);
        $active = $users->pluck('id')->all();
        $departments = DB::table('departments')->orderBy('name')->get()->map(fn ($d) => (object) ['id' => $d->id, 'name' => $d->name,
            'members' => $users->where('department_id', $d->id)->pluck('id')->values()->all()])->all();
        $groups = DB::table('meeting_groups')->orderBy('name')->get()->map(fn ($g) => (object) ['id' => $g->id, 'name' => $g->name, 'color' => $g->color, 'description' => $g->description,
            'members' => DB::table('meeting_group_members')->where('group_id', $g->id)->whereIn('user_id', $active)->pluck('user_id')->all()])->all();

        return (object) ['users' => $users->all(), 'departments' => $departments, 'groups' => $groups];
    }

    public function saveAudience(int $meetingId, array $departmentIds, array $groupIds): void
    {
        DB::table('meeting_audience')->where('meeting_id', $meetingId)->delete();
        foreach (array_unique($departmentIds) as $d) {
            DB::table('meeting_audience')->insert(['meeting_id' => $meetingId, 'kind' => 'department', 'ref_id' => $d]);
        }
        foreach (array_unique($groupIds) as $g) {
            DB::table('meeting_audience')->insert(['meeting_id' => $meetingId, 'kind' => 'group', 'ref_id' => $g]);
        }
    }

    /** Names of people who already have another meeting overlapping this time. @return list<string> */
    public function peopleConflicts(array $userIds, string $start, string $end, array $excludeIds = []): array
    {
        if (! $userIds) {
            return [];
        }

        return DB::table('meeting_participants as p')->join('meetings as m', 'm.id', '=', 'p.meeting_id')->join('users as u', 'u.id', '=', 'p.user_id')
            ->whereIn('p.user_id', $userIds)->where('m.status', '!=', 'cancelled')->where('m.start_time', '<', $end)->where('m.end_time', '>', $start)
            ->whereNotIn('m.id', $excludeIds ?: [0])->orderBy('u.name')->distinct()->pluck('u.name')->all();
    }

    public function roomConflict(?int $roomId, string $start, string $end, array $excludeIds = []): ?stdClass
    {
        if (! $roomId) {
            return null;
        }

        return DB::table('meetings')->where('room_id', $roomId)->where('status', '!=', 'cancelled')->where('start_time', '<', $end)->where('end_time', '>', $start)
            ->whereNotIn('id', $excludeIds ?: [0])->first(['id', 'title', 'start_time', 'end_time']);
    }

    /** The meetings of its series (or just itself). @return list<stdClass> */
    public function series(stdClass $meeting, bool $followingOnly = false): array
    {
        if (! $meeting->series_id) {
            return [$meeting];
        }

        return DB::table('meetings')->where('series_id', $meeting->series_id)->when($followingOnly, fn ($q) => $q->where('start_time', '>=', $meeting->start_time))->orderBy('start_time')->get()->all();
    }

    public function closeCurrentItem(stdClass $meeting): void
    {
        if ($meeting->current_agenda_id) {
            DB::table('meeting_agenda_items')->where('id', $meeting->current_agenda_id)->where('meeting_id', $meeting->id)->update(['ended_at' => self::now()]);
        }
    }

    /** Start discussing an item (coming back to one keeps its first start time). */
    public function openItem(int $meetingId, int $itemId): void
    {
        $item = DB::table('meeting_agenda_items')->find($itemId);
        DB::table('meeting_agenda_items')->where('id', $itemId)->update(['started_at' => $item->started_at ?? self::now(), 'ended_at' => null]);
        DB::table('meetings')->where('id', $meetingId)->update(['current_agenda_id' => $itemId]);
    }

    /** Everything recorded for a meeting. */
    public function records(int $meetingId): array
    {
        $participants = DB::table('meeting_participants as p')->join('users as u', 'u.id', '=', 'p.user_id')->leftJoin('departments as d', 'd.id', '=', 'u.department_id')
            ->where('p.meeting_id', $meetingId)->orderBy('u.name')->get(['p.*', 'u.name', 'u.email', 'u.job_title', 'd.name as department'])->all();
        $agenda = DB::table('meeting_agenda_items as a')->leftJoin('users as u', 'u.id', '=', 'a.presenter_id')->where('a.meeting_id', $meetingId)
            ->orderBy('a.position')->orderBy('a.id')->get(['a.*', 'u.name as presenter_name'])->all();
        $minutes = DB::table('meeting_minutes as mi')->leftJoin('users as u1', 'u1.id', '=', 'mi.updated_by')->leftJoin('users as u2', 'u2.id', '=', 'mi.approved_by')
            ->where('mi.meeting_id', $meetingId)->first(['mi.*', 'u1.name as updated_by_name', 'u2.name as approved_by_name']);
        $decisions = DB::table('meeting_decisions as d')->leftJoin('meeting_agenda_items as a', 'a.id', '=', 'd.agenda_item_id')->leftJoin('users as pu', 'pu.id', '=', 'd.proposer_id')->leftJoin('users as su', 'su.id', '=', 'd.seconder_id')
            ->where('d.meeting_id', $meetingId)->orderBy('d.id')->get(['d.*', 'a.title as agenda_title', 'pu.name as proposer_name', 'su.name as seconder_name'])->all();
        $actions = DB::table('meeting_action_items as a')->leftJoin('users as u', 'u.id', '=', 'a.assignee_id')->where('a.meeting_id', $meetingId)
            ->orderByRaw("a.status = 'done'")->orderBy('a.due_date')->get(['a.*', 'u.name as assignee_name'])->all();
        $attachments = DB::table('meeting_attachments as at')->leftJoin('users as u', 'u.id', '=', 'at.uploaded_by')->leftJoin('meeting_agenda_items as ai', 'ai.id', '=', 'at.agenda_item_id')
            ->where('at.meeting_id', $meetingId)->orderByDesc('at.uploaded_at')->get(['at.*', 'u.name as uploader', 'ai.title as agenda_title'])->all();
        foreach ($agenda as $a) {
            $a->reports = array_values(array_filter($attachments, fn ($f) => $f->agenda_item_id === $a->id));
            $a->spent = $a->started_at && $a->ended_at ? (int) floor((strtotime($a->ended_at) - strtotime($a->started_at)) / 60) : null;
        }
        $count = fn (string $field, array $keys) => (object) collect($keys)->mapWithKeys(fn ($k) => [$k => count(array_filter($participants, fn ($p) => $p->{$field} === $k))])->all();
        $attendance = $count('attendance', ['unknown', 'present', 'absent', 'excused']);

        return ['participants' => $participants, 'agenda' => $agenda, 'minutes' => $minutes, 'decisions' => $decisions, 'actions' => $actions, 'attachments' => $attachments,
            'attendance_counts' => $attendance, 'rsvp_counts' => $count('rsvp', ['pending', 'accepted', 'tentative', 'declined']),
            'total' => count($participants), 'present' => $attendance->present, 'agenda_minutes' => (int) array_sum(array_map(fn ($a) => $a->duration ?? 0, $agenda))];
    }

    /** Open action items from earlier meetings of the same series (or with the same title). @return list<stdClass> */
    public function mattersArising(stdClass $meeting): array
    {
        return DB::table('meeting_action_items as a')->join('meetings as pm', 'pm.id', '=', 'a.meeting_id')->leftJoin('users as u', 'u.id', '=', 'a.assignee_id')
            ->where('a.status', '!=', 'done')->where('pm.id', '!=', $meeting->id)->where('pm.start_time', '<', $meeting->start_time)
            ->where(fn ($q) => $q->where('pm.title', $meeting->title)->when($meeting->series_id, fn ($s) => $s->orWhere('pm.series_id', $meeting->series_id)))
            ->orderBy('pm.start_time')->orderBy('a.id')->get(['a.*', 'u.name as assignee_name', 'pm.start_time as from_date', 'pm.id as from_id'])->all();
    }

    // ---- Recurrence (full-year planning) ----

    public static function weekOfMonth(CarbonImmutable $d): int
    {
        $n = (int) floor(($d->day - 1) / 7) + 1;

        return $n >= 5 ? -1 : $n;
    }

    /** $weekday: 0 = Monday. */
    public static function nthWeekday(int $year, int $month, int $weekday, int $n): CarbonImmutable
    {
        if ($n === -1) {
            $last = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TZ)->endOfMonth()->startOfDay();

            return $last->subDays((($last->dayOfWeekIso - 1) - $weekday + 7) % 7);
        }
        $first = CarbonImmutable::create($year, $month, 1, 0, 0, 0, self::TZ);

        return $first->addDays(($weekday - ($first->dayOfWeekIso - 1) + 7) % 7 + 7 * ($n - 1));
    }

    public static function repeatSummary(CarbonImmutable $start, string $repeat): string
    {
        $day = $start->format('l');
        $ordinal = [1 => '1st', 2 => '2nd', 3 => '3rd', 4 => '4th', -1 => 'last'];

        return match ($repeat) {
            'weekly' => "Every week on {$day}",
            'biweekly' => "Every 2 weeks on {$day}",
            'monthly' => "Every month on day {$start->day}",
            'monthly_weekday' => 'Every month on the '.$ordinal[self::weekOfMonth($start)]." {$day}",
            'quarterly' => "Every 3 months on day {$start->day}",
            'yearly' => 'Every year on '.$start->format('d F'),
            default => 'Does not repeat',
        };
    }

    /** @return list<CarbonImmutable> */
    public static function recurrenceDates(CarbonImmutable $start, string $repeat, string $endMode = 'count', int $count = 1, ?CarbonImmutable $until = null): array
    {
        if ($repeat === 'none' || ! isset(\App\Support\MeetingUi::REPEAT_OPTIONS[$repeat])) {
            return [$start];
        }
        $until = match ($endMode) {
            'year_end' => CarbonImmutable::create($start->year, 12, 31, 0, 0, 0, self::TZ),
            // 12 calendar months: a series starting in March runs to the end of next February
            'twelve_months' => $start->startOfMonth()->addMonthsNoOverflow(12)->subDay(),
            'until' => $until ?? $start,
            default => null,
        };
        $limit = $until ? self::MAX_OCCURRENCES : max(1, min($count, self::MAX_OCCURRENCES));
        $n = self::weekOfMonth($start);
        $dates = [];
        for ($i = 0; count($dates) < $limit; $i++) {
            $d = match ($repeat) {
                'weekly' => $start->addWeeks($i),
                'biweekly' => $start->addWeeks(2 * $i),
                'monthly' => $start->addMonthsNoOverflow($i),
                'monthly_weekday' => self::nthOf($start->startOfMonth()->addMonthsNoOverflow($i), $start, $n),
                'quarterly' => $start->addMonthsNoOverflow(3 * $i),
                default => $start->addMonthsNoOverflow(12 * $i),
            };
            if ($until && $d->greaterThan($until)) {
                break;
            }
            $dates[] = $d;
        }

        return $dates;
    }

    private static function nthOf(CarbonImmutable $month, CarbonImmutable $start, int $n): CarbonImmutable
    {
        return self::nthWeekday($month->year, $month->month, $start->dayOfWeekIso - 1, $n);
    }

    /** @return array{0: string, 1: string, 2: int, 3: ?CarbonImmutable} */
    public static function readRecurrence(Request $request): array
    {
        $count = $request->input('occurrences', 4);

        return [(string) $request->input('repeat', 'none'), (string) $request->input('end_mode', 'year_end'), is_numeric($count) ? (int) $count : 4, self::date((string) $request->input('until', ''))];
    }

    public static function date(string $value): ?CarbonImmutable
    {
        if (! preg_match('/^\d{4}-\d{2}-\d{2}$/', $value) || ! checkdate((int) substr($value, 5, 2), (int) substr($value, 8, 2), (int) substr($value, 0, 4))) {
            return null;
        }

        return CarbonImmutable::parse($value, self::TZ)->startOfDay();
    }

    /** A valid 'YYYY-MM-DD HH:MM', or null. */
    public static function dateTime(string $value): ?string
    {
        return preg_match('/^\d{4}-\d{2}-\d{2} ([01]\d|2[0-3]):[0-5]\d$/', $value) && self::date(substr($value, 0, 10)) ? $value : null;
    }

    /** The form's 'back' address, only if it points inside this site. */
    public static function safeBack(Request $request, string $default): string
    {
        $back = (string) $request->input('back', '');

        return str_starts_with($back, '/') && ! str_starts_with($back, '//') ? $back : $default;
    }
}
