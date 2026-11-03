<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\DailyReport;
use App\Models\DutyTask;
use App\Models\DutyWarning;
use App\Models\HelpdeskTicket;
use App\Models\Station;
use App\Services\Systems;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;
use Throwable;

class PortalController extends Controller
{
    public function __construct(private Systems $systems) {}

    /** The TFC home page: signed out, a simple public welcome; signed in, every portal —
     *  Helpdesk, Uwajibikaji and Daily Report natively, Meeting and Protocol by sign-in link —
     *  in one simple, equal-weight grid. */
    public function index(Request $request): View
    {
        if (! $request->user()) {
            return view('welcome');
        }

        $user = $request->user()->load('links');
        $support = $user->isSupport();
        $canReport = $user->isReporter();

        return view('home', [
            'canReport' => $canReport,
            'reports' => $canReport ? ($user->is_admin ? [
                'admin' => true,
                'missing_today' => Station::query()->where('is_active', true)
                    ->whereDoesntHave('dailyReports', fn ($q) => $q->whereDate('report_date', now()->toDateString()))->count(),
                'stations' => Station::query()->where('is_active', true)->count(),
            ] : [
                'admin' => false,
                'reported_today' => DailyReport::query()->where('station_id', $user->station_id)
                    ->whereDate('report_date', now()->toDateString())->exists(),
            ]) : null,
            'support' => $support,
            'helpdesk' => [
                'mine_open' => HelpdeskTicket::query()->where('user_id', $user->id)->whereIn('status', HelpdeskTicket::OPEN)->count(),
                'assigned_to_me' => $support ? HelpdeskTicket::query()->where('assigned_to', $user->id)->whereIn('status', HelpdeskTicket::OPEN)->count() : 0,
                'unassigned' => $support ? HelpdeskTicket::query()->where('status', 'open')->count() : 0,
                'urgent' => $support ? HelpdeskTicket::query()->whereIn('status', HelpdeskTicket::OPEN)->where('priority', 'urgent')->count() : 0,
            ],
            'duty' => [
                'to_me' => DutyTask::query()->where('assigned_to', $user->id)->whereIn('status', DutyTask::ACTIVE)->count(),
                'to_approve' => DutyTask::query()->where('assigned_by', $user->id)->where('status', 'submitted')->count(),
                'warnings' => DutyWarning::countFor($user),
                'to_decide' => DutyWarning::query()->where('status', 'review')
                    ->when(! $user->is_admin, fn ($q) => $q->whereHas('task', fn ($t) => $t->where('assigned_by', $user->id)))->count(),
                'lock' => DutyWarning::lockFor($user),
            ],
            // Only what the page shows: never the system's signing key
            'systems' => collect($this->systems->all())->map(fn ($s, $id) => [
                'id' => $id, 'name' => $s['name'], 'about' => $s['about'], 'link' => $user->linkTo($id),
            ]),
        ]);
    }

    /** Into the system, already signed in as their account there. */
    public function open(Request $request, string $system): RedirectResponse
    {
        abort_unless($this->systems->has($system), 404);
        $link = $request->user()->links()->where('system', $system)->first();
        if (! $link) {
            return redirect()->route('home')->with('error', "You do not have access to {$this->systems->name($system)}. Ask the TFC administrator.");
        }

        try {
            $url = $this->systems->signInLink($system, $link->remote_id);
        } catch (Throwable $e) {
            report($e);

            return redirect()->route('home')->with('error', "{$this->systems->name($system)} cannot be opened from the portal right now.");
        }
        Activity::record('opened', $system, $link->remote_name);

        return redirect()->away($url);
    }
}
