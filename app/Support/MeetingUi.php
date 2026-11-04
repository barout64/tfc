<?php

namespace App\Support;

use App\Services\Meetings;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\HtmlString;
use Symfony\Component\HttpFoundation\Response;

/** What the TFC Meetings pages show: labels, dates, badges and icons. Available in the views as MUi. */
class MeetingUi
{
    public const ROLES = ['admin' => 'Administrator', 'organizer' => 'Organizer', 'secretary' => 'Secretary', 'member' => 'Member'];

    public const TYPE_COLORS = ['Board Meeting' => '#7048e8', 'Management Meeting' => '#3b5bdb', 'Department Meeting' => '#1098ad', 'Team Meeting' => '#0ca678', 'Committee Meeting' => '#f08c00',
        'Project Meeting' => '#d6336c', 'General Assembly' => '#e03131', 'Training' => '#66a80f', 'Other' => '#868e96'];

    public const MEETING_TYPES = ['Board Meeting', 'Management Meeting', 'Department Meeting', 'Team Meeting', 'Committee Meeting', 'Project Meeting', 'General Assembly', 'Training', 'Other'];

    public const MEETING_STATUSES = ['scheduled' => 'Scheduled', 'in_progress' => 'In progress', 'completed' => 'Completed', 'cancelled' => 'Cancelled'];

    public const RSVP_STATUSES = ['pending' => 'No reply', 'accepted' => 'Accepted', 'tentative' => 'Maybe', 'declined' => 'Declined'];

    public const ATTENDANCE_STATUSES = ['unknown' => 'Not recorded', 'present' => 'Present', 'absent' => 'Absent', 'excused' => 'Excused'];

    public const ACTION_STATUSES = ['open' => 'Open', 'in_progress' => 'In progress', 'done' => 'Done'];

    public const DECISION_OUTCOMES = ['carried' => 'Carried', 'defeated' => 'Defeated', 'deferred' => 'Deferred', 'noted' => 'Noted'];

    public const REPEAT_OPTIONS = ['none' => 'Does not repeat', 'weekly' => 'Every week', 'biweekly' => 'Every 2 weeks', 'monthly' => 'Every month (same date)',
        'monthly_weekday' => 'Every month (same weekday, e.g. 2nd Tuesday)', 'quarterly' => 'Every 3 months (quarterly)', 'yearly' => 'Every year'];

    public const END_OPTIONS = ['year_end' => 'Until the end of the year', 'twelve_months' => 'For 12 months (one full year)', 'count' => 'After a number of meetings', 'until' => 'Until a specific date'];

    public const GROUP_COLORS = ['#3b5bdb', '#7048e8', '#0ca678', '#f08c00', '#d6336c', '#1098ad', '#e03131', '#495057'];

    private const ICONS = [
        'home' => '<path d="M3 9l9-7 9 7v11a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2z"/><polyline points="9 22 9 12 15 12 15 22"/>',
        'calendar' => '<rect x="3" y="4" width="18" height="18" rx="2"/><line x1="16" y1="2" x2="16" y2="6"/><line x1="8" y1="2" x2="8" y2="6"/><line x1="3" y1="10" x2="21" y2="10"/>',
        'year' => '<rect x="3" y="3" width="7" height="7" rx="1.5"/><rect x="14" y="3" width="7" height="7" rx="1.5"/><rect x="3" y="14" width="7" height="7" rx="1.5"/><rect x="14" y="14" width="7" height="7" rx="1.5"/>',
        'list' => '<line x1="8" y1="6" x2="21" y2="6"/><line x1="8" y1="12" x2="21" y2="12"/><line x1="8" y1="18" x2="21" y2="18"/><line x1="3" y1="6" x2="3.01" y2="6"/><line x1="3" y1="12" x2="3.01" y2="12"/><line x1="3" y1="18" x2="3.01" y2="18"/>',
        'users' => '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>',
        'user' => '<path d="M20 21v-2a4 4 0 0 0-4-4H8a4 4 0 0 0-4 4v2"/><circle cx="12" cy="7" r="4"/>',
        'layers' => '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>',
        'tasks' => '<polyline points="9 11 12 14 22 4"/><path d="M21 12v7a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h11"/>',
        'chart' => '<line x1="12" y1="20" x2="12" y2="10"/><line x1="18" y1="20" x2="18" y2="4"/><line x1="6" y1="20" x2="6" y2="16"/>',
        'bell' => '<path d="M18 8A6 6 0 0 0 6 8c0 7-3 9-3 9h18s-3-2-3-9"/><path d="M13.73 21a2 2 0 0 1-3.46 0"/>',
        'settings' => '<line x1="4" y1="21" x2="4" y2="14"/><line x1="4" y1="10" x2="4" y2="3"/><line x1="12" y1="21" x2="12" y2="12"/><line x1="12" y1="8" x2="12" y2="3"/><line x1="20" y1="21" x2="20" y2="16"/><line x1="20" y1="12" x2="20" y2="3"/><line x1="1" y1="14" x2="7" y2="14"/><line x1="9" y1="8" x2="15" y2="8"/><line x1="17" y1="16" x2="23" y2="16"/>',
        'pin' => '<path d="M21 10c0 7-9 13-9 13s-9-6-9-13a9 9 0 0 1 18 0z"/><circle cx="12" cy="10" r="3"/>',
        'plus' => '<line x1="12" y1="5" x2="12" y2="19"/><line x1="5" y1="12" x2="19" y2="12"/>',
        'clock' => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'file' => '<path d="M13 2H6a2 2 0 0 0-2 2v16a2 2 0 0 0 2 2h12a2 2 0 0 0 2-2V9z"/><polyline points="13 2 13 9 20 9"/>',
        'briefcase' => '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>',
        'check' => '<path d="M22 11.08V12a10 10 0 1 1-5.93-9.14"/><polyline points="22 4 12 14.01 9 11.01"/>',
        'tick' => '<polyline points="20 6 9 17 4 12"/>',
        'video' => '<polygon points="23 7 16 12 23 17 23 7"/><rect x="1" y="5" width="15" height="14" rx="2"/>',
        'logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" y1="12" x2="9" y2="12"/>',
        'menu' => '<line x1="3" y1="12" x2="21" y2="12"/><line x1="3" y1="6" x2="21" y2="6"/><line x1="3" y1="18" x2="21" y2="18"/>',
        'printer' => '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>',
        'download' => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" y1="15" x2="12" y2="3"/>',
        'edit' => '<path d="M11 4H4a2 2 0 0 0-2 2v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2v-7"/><path d="M18.5 2.5a2.121 2.121 0 0 1 3 3L12 15l-4 1 1-4 9.5-9.5z"/>',
        'trash' => '<polyline points="3 6 5 6 21 6"/><path d="M19 6v14a2 2 0 0 1-2 2H7a2 2 0 0 1-2-2V6m3 0V4a2 2 0 0 1 2-2h4a2 2 0 0 1 2 2v2"/>',
        'search' => '<circle cx="11" cy="11" r="8"/><line x1="21" y1="21" x2="16.65" y2="16.65"/>',
        'left' => '<polyline points="15 18 9 12 15 6"/>',
        'right' => '<polyline points="9 18 15 12 9 6"/>',
        'up' => '<polyline points="18 15 12 9 6 15"/>',
        'down' => '<polyline points="6 9 12 15 18 9"/>',
        'x' => '<line x1="18" y1="6" x2="6" y2="18"/><line x1="6" y1="6" x2="18" y2="18"/>',
        'repeat' => '<polyline points="17 1 21 5 17 9"/><path d="M3 11V9a4 4 0 0 1 4-4h14"/><polyline points="7 23 3 19 7 15"/><path d="M21 13v2a4 4 0 0 1-4 4H3"/>',
        'sun' => '<circle cx="12" cy="12" r="5"/><line x1="12" y1="1" x2="12" y2="3"/><line x1="12" y1="21" x2="12" y2="23"/><line x1="4.22" y1="4.22" x2="5.64" y2="5.64"/><line x1="18.36" y1="18.36" x2="19.78" y2="19.78"/><line x1="1" y1="12" x2="3" y2="12"/><line x1="21" y1="12" x2="23" y2="12"/><line x1="4.22" y1="19.78" x2="5.64" y2="18.36"/><line x1="18.36" y1="5.64" x2="19.78" y2="4.22"/>',
        'moon' => '<path d="M21 12.79A9 9 0 1 1 11.21 3 7 7 0 0 0 21 12.79z"/>',
        'alert' => '<path d="M10.29 3.86L1.82 18a2 2 0 0 0 1.71 3h16.94a2 2 0 0 0 1.71-3L13.71 3.86a2 2 0 0 0-3.42 0z"/><line x1="12" y1="9" x2="12" y2="13"/><line x1="12" y1="17" x2="12.01" y2="17"/>',
        'flag' => '<path d="M4 15s1-1 4-1 5 2 8 2 4-1 4-1V3s-1 1-4 1-5-2-8-2-4 1-4 1z"/><line x1="4" y1="22" x2="4" y2="15"/>',
        'gavel' => '<path d="M14 13l-7.5 7.5a2.12 2.12 0 0 1-3-3L11 10"/><path d="M16 16l6-6"/><path d="M8 8l6-6"/><path d="M9 7l8 8"/><path d="M21 11l-8-8"/>',
        'arrow-right' => '<line x1="5" y1="12" x2="19" y2="12"/><polyline points="12 5 19 12 12 19"/>',
        'play' => '<polygon points="6 4 20 12 6 20 6 4"/>',
        'stop' => '<rect x="6" y="6" width="12" height="12" rx="1.5"/>',
    ];

    public static function today(): string
    {
        return Meetings::today();
    }

    public static function now(): string
    {
        return Meetings::now();
    }

    public static function org(): string
    {
        return (string) config('tfc.organisation', 'Tanzania Fertilizer Company');
    }

    public static function unread(): int
    {
        static $count = null;

        return $count ??= DB::table('meeting_notifications')->where('user_id', auth()->id())->where('is_read', false)->count();
    }

    public static function canCreate(): bool
    {
        return app(Meetings::class)->canCreate();
    }

    public static function canManageGroups(): bool
    {
        return app(Meetings::class)->canManageGroups();
    }

    public static function isAdmin(): bool
    {
        return app(Meetings::class)->isAdmin();
    }

    public static function slice(mixed $value, ?int $from, ?int $to): mixed
    {
        $from ??= 0;
        if (is_array($value)) {
            return array_slice(array_values($value), $from, $to === null ? null : $to - $from);
        }

        return mb_substr((string) $value, $from, $to === null ? null : $to - $from);
    }

    /** Counting down, as the year pickers do: the last number is left out. */
    public static function pyRange(int $from, int $to, int $step = 1): array
    {
        return range($from, $to - $step, abs($step));
    }

    private static function fmt(?string $value, string $format): string
    {
        $t = $value ? strtotime($value) : false;

        return $t === false ? (string) $value : date($format, $t);
    }

    public static function datetime(?string $value): string
    {
        return self::fmt($value, 'D d M Y, H:i');
    }

    public static function date(?string $value): string
    {
        return $value ? self::fmt(substr($value, 0, 10), 'd M Y') : '';
    }

    public static function longdate(?string $value): string
    {
        return $value ? self::fmt(substr($value, 0, 10), 'l, d F Y') : '';
    }

    public static function time(?string $value): string
    {
        return $value && strlen($value) >= 16 ? substr($value, 11, 5) : '';
    }

    public static function mon(?string $value): string
    {
        return $value ? self::fmt(substr($value, 0, 10), 'M') : '';
    }

    public static function filesize(?int $size): string
    {
        $size = (float) ($size ?? 0);
        foreach (['B', 'KB', 'MB'] as $unit) {
            if ($size < 1024) {
                return round($size).' '.$unit;
            }
            $size /= 1024;
        }

        return number_format($size, 1).' GB';
    }

    public static function ext(string $filename): string
    {
        return mb_substr((string) pathinfo($filename, PATHINFO_EXTENSION), 0, 4);
    }

    public static function typeColor(?string $type): string
    {
        return self::TYPE_COLORS[$type] ?? self::TYPE_COLORS['Other'];
    }

    public static function relativeDay(?string $value): string
    {
        if (! $value || ! ($d = Meetings::date(substr($value, 0, 10)))) {
            return '';
        }
        $delta = (int) round(($d->getTimestamp() - Meetings::date(Meetings::today())->getTimestamp()) / 86400);

        return match (true) {
            $delta === 0 => 'Today',
            $delta === 1 => 'Tomorrow',
            $delta === -1 => 'Yesterday',
            $delta > 1 && $delta < 7 => "In {$delta} days",
            default => '',
        };
    }

    public static function icon(string $name, int $size = 18): HtmlString
    {
        return new HtmlString('<svg class="icon" width="'.$size.'" height="'.$size.'" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">'.(self::ICONS[$name] ?? '').'</svg>');
    }

    public static function statusBadge(string $status): HtmlString
    {
        return new HtmlString('<span class="badge badge-'.e($status).'"><span class="bdot"></span>'.e(self::MEETING_STATUSES[$status] ?? $status).'</span>');
    }

    public static function typeChip(string $type): HtmlString
    {
        return new HtmlString('<span class="type-chip" style="--c: '.self::typeColor($type).'"><span class="tdot"></span>'.e($type).'</span>');
    }

    public static function rsvpBadge(string $rsvp): HtmlString
    {
        return new HtmlString('<span class="badge rsvp-'.e($rsvp).'">'.e(self::RSVP_STATUSES[$rsvp] ?? $rsvp).'</span>');
    }

    public static function actionBadge(object $item): HtmlString
    {
        return new HtmlString($item->status !== 'done' && $item->due_date && $item->due_date < self::today()
            ? '<span class="badge badge-overdue">Overdue</span>'
            : '<span class="badge action-'.e($item->status).'">'.e(self::ACTION_STATUSES[$item->status] ?? $item->status).'</span>');
    }

    public static function outcomeBadge(?string $outcome): HtmlString
    {
        return new HtmlString($outcome ? '<span class="badge outcome-'.e($outcome).'">'.e(self::DECISION_OUTCOMES[$outcome] ?? $outcome).'</span>' : '');
    }

    public static function avatar(?string $name, string $size = ''): HtmlString
    {
        $name = (string) $name;
        $hue = array_sum(array_map('mb_ord', mb_str_split($name) ?: [])) * 37 % 360;
        $initials = mb_strtoupper(implode('', array_map(fn ($p) => mb_substr($p, 0, 1), array_slice(preg_split('/\s+/', trim($name) ?: '?'), 0, 2))));

        return new HtmlString('<span class="avatar '.e($size).'" title="'.e($name).'" style="--h: '.$hue.'">'.e($initials).'</span>');
    }

    public static function avatarStack(array $names, int $max = 5): HtmlString
    {
        $out = '<span class="avatar-stack">'.implode('', array_map(fn ($n) => self::avatar($n, 'sm'), array_slice(array_values($names), 0, $max)));
        if (count($names) > $max) {
            $out .= '<span class="avatar sm more">+'.(count($names) - $max).'</span>';
        }

        return new HtmlString($out.'</span>');
    }

    public static function when(object $m): string
    {
        return self::date($m->start_time).' · '.self::time($m->start_time).'–'.self::time($m->end_time);
    }

    /** A calendar file of these meetings. @param  list<object>  $events */
    public static function icsResponse(array $events, string $filename): Response
    {
        $esc = fn (?string $t) => str_replace(['\\', ';', ',', "\n"], ['\\\\', '\\;', '\\,', '\\n'], (string) $t);
        $stamp = fn (string $s) => date('Ymd\THis', strtotime($s));
        $lines = ['BEGIN:VCALENDAR', 'VERSION:2.0', 'PRODID:-//TFC Meetings//EN'];
        foreach ($events as $m) {
            $location = (string) ($m->room_name ?? '');
            if ($m->online_link) {
                $location = $location !== '' ? "{$location} / {$m->online_link}" : $m->online_link;
            }
            array_push($lines, 'BEGIN:VEVENT', "UID:meeting-{$m->id}@tfc", 'DTSTAMP:'.now(Meetings::TZ)->format('Ymd\THis'), 'DTSTART:'.$stamp($m->start_time), 'DTEND:'.$stamp($m->end_time),
                'SUMMARY:'.$esc($m->title), 'DESCRIPTION:'.$esc($m->description), 'LOCATION:'.$esc($location), 'STATUS:'.($m->status === 'cancelled' ? 'CANCELLED' : 'CONFIRMED'), 'END:VEVENT');
        }
        $lines[] = 'END:VCALENDAR';

        return response(implode("\r\n", $lines), 200, ['Content-Type' => 'text/calendar', 'Content-Disposition' => "attachment; filename={$filename}.ics"]);
    }
}
