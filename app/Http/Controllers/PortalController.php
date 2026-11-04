<?php

namespace App\Http\Controllers;

use App\Models\Activity;
use App\Models\DailyReport;
use App\Models\DutyTask;
use App\Models\DutyWarning;
use App\Models\HelpdeskTicket;
use App\Models\Station;
use Illuminate\Support\Facades\DB;
use Illuminate\Http\Request;
use Illuminate\View\View;

class PortalController extends Controller
{
    /** The TFC home page: signed out, a simple public welcome; signed in, every part of
     *  the one TFC system, in one simple, equal-weight grid. */
    public function index(Request $request): View
    {
        if (! $request->user()) {
            return view('welcome');
        }

        $user = $request->user();
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
            'meetings' => [
                'today' => DB::table('meetings as m')->join('meeting_participants as p', 'p.meeting_id', '=', 'm.id')->where('p.user_id', $user->id)
                    ->whereRaw('substr(m.start_time, 1, 10) = ?', [now('Africa/Dar_es_Salaam')->format('Y-m-d')])->where('m.status', '!=', 'cancelled')->count(),
                'tasks' => DB::table('meeting_action_items')->where('assignee_id', $user->id)->where('status', '!=', 'done')->count(),
            ],
            'protocol_unread' => DB::table('protocol_notifications')->where('user_id', $user->id)->where('read', false)->count(),
        ]);
    }
}
