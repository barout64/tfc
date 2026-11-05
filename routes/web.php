<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\StationController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DailyReportController;
use App\Http\Controllers\StationStockController;
use App\Http\Controllers\DutyController;
use App\Http\Controllers\HelpdeskController;
use App\Http\Controllers\MeetingController;
use App\Http\Controllers\MeetingSpaceController;
use App\Http\Controllers\PortalController;
use App\Http\Controllers\ProtocolController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1');
});

// Public welcome page when signed out; the real dashboard when signed in — one route, decided in the controller.
Route::get('/', [PortalController::class, 'index'])->name('home');

Route::middleware('auth')->group(function () {
    Route::redirect('/dashboard', '/');   // old bookmarks
    Route::redirect('/home', '/');
    Route::post('/logout', [AuthController::class, 'destroy'])->name('logout');
    Route::get('/password', [AuthController::class, 'editPassword'])->name('password.edit');
    Route::put('/password', [AuthController::class, 'updatePassword'])->name('password.update');

    // TFC Helpdesk: everyone asks for help; support (and administrators) fix
    Route::prefix('helpdesk')->name('helpdesk.')->group(function () {
        Route::get('/', [HelpdeskController::class, 'index'])->name('index');
        Route::get('/new', [HelpdeskController::class, 'create'])->name('create');
        Route::post('/', [HelpdeskController::class, 'store'])->middleware('throttle:20,1')->name('store');
        Route::get('/file/{message}', [HelpdeskController::class, 'file'])->name('file');
        Route::get('/{ticket}', [HelpdeskController::class, 'show'])->name('show');
        Route::post('/{ticket}/reply', [HelpdeskController::class, 'reply'])->middleware('throttle:30,1')->name('reply');
        Route::post('/{ticket}/confirm', [HelpdeskController::class, 'confirm'])->name('confirm');
        Route::middleware('can:support')->group(function () {
            Route::post('/{ticket}/assign', [HelpdeskController::class, 'assign'])->name('assign');
            Route::post('/{ticket}/status', [HelpdeskController::class, 'status'])->name('status');
        });
    });

    // Uwajibikaji: anyone gives anyone a task with a deadline; a missed deadline is a warning (five: locked)
    Route::prefix('uwajibikaji')->name('duty.')->group(function () {
        Route::get('/', [DutyController::class, 'index'])->name('index');
        Route::get('/new', [DutyController::class, 'create'])->name('create');
        Route::post('/', [DutyController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::get('/warnings', [DutyController::class, 'warnings'])->name('warnings');
        Route::post('/warnings/{warning}/explain', [DutyController::class, 'explain'])->name('explain');
        Route::post('/warnings/{warning}/decide', [DutyController::class, 'decide'])->name('decide');
        Route::post('/people/{user}/clear', [DutyController::class, 'clear'])->middleware('can:admin')->name('clear');
        Route::get('/file/{message}', [DutyController::class, 'file'])->name('file');
        Route::get('/{task}', [DutyController::class, 'show'])->name('show');
        Route::post('/{task}/status', [DutyController::class, 'status'])->name('status');
        Route::post('/{task}/submit', [DutyController::class, 'submit'])->middleware('throttle:30,1')->name('submit');
        Route::post('/{task}/reply', [DutyController::class, 'reply'])->middleware('throttle:30,1')->name('reply');
        Route::post('/{task}/review', [DutyController::class, 'review'])->name('review');
        Route::post('/{task}/deadline', [DutyController::class, 'deadline'])->name('deadline');
        Route::post('/{task}/cancel', [DutyController::class, 'cancel'])->name('cancel');
    });

    // Daily Report (Taarifa ya Mauzo ya Kila Siku): each station reports what it sold each day
    Route::middleware('can:reports')->prefix('reports')->name('reports.')->group(function () {
        Route::get('/', [DailyReportController::class, 'index'])->name('index');
        Route::get('/new', [DailyReportController::class, 'create'])->name('create');
        Route::post('/', [DailyReportController::class, 'store'])->middleware('throttle:30,1')->name('store');
        Route::get('/stock', [StationStockController::class, 'index'])->name('stock');
        Route::post('/stock', [StationStockController::class, 'store'])->middleware('throttle:30,1')->name('stock.store');
        Route::get('/{dailyReport}', [DailyReportController::class, 'show'])->name('show');
    });

    // TFC Meetings: plan meetings, invite, run the agenda, keep minutes, decisions and action items
    Route::prefix('meetings')->name('meetings.')->group(function () {
        Route::get('/', [MeetingSpaceController::class, 'dashboard'])->name('dashboard');
        Route::get('/list', [MeetingController::class, 'index'])->name('meetings_list');
        Route::match(['get', 'post'], '/new', [MeetingController::class, 'create'])->name('meeting_new');
        Route::get('/preview', [MeetingController::class, 'preview'])->name('meeting_preview');
        Route::get('/profile', [MeetingSpaceController::class, 'profile'])->name('profile');
        Route::get('/tasks', [MeetingSpaceController::class, 'tasks'])->name('tasks');
        Route::get('/decisions', [MeetingSpaceController::class, 'decisions'])->name('decisions_list');
        Route::get('/calendar', [MeetingSpaceController::class, 'calendar'])->name('calendar_view');
        Route::get('/planner', [MeetingSpaceController::class, 'planner'])->name('planner');
        Route::get('/groups', [MeetingSpaceController::class, 'groups'])->name('groups');
        Route::match(['get', 'post'], '/groups/form/{group_id?}', [MeetingSpaceController::class, 'groupForm'])->whereNumber('group_id')->name('group_form');
        Route::post('/groups/{group_id}/delete', [MeetingSpaceController::class, 'groupDelete'])->whereNumber('group_id')->name('group_delete');
        Route::match(['get', 'post'], '/rooms', [MeetingSpaceController::class, 'rooms'])->name('rooms');
        Route::post('/rooms/{room_id}/delete', [MeetingSpaceController::class, 'roomDelete'])->whereNumber('room_id')->name('room_delete');
        Route::get('/notifications', [MeetingSpaceController::class, 'notifications'])->name('notifications');
        Route::post('/notifications/read-all', [MeetingSpaceController::class, 'notificationsReadAll'])->name('notifications_read_all');
        Route::get('/notifications/{item_id}/open', [MeetingSpaceController::class, 'notificationOpen'])->whereNumber('item_id')->name('notification_open');
        Route::get('/reports', [MeetingSpaceController::class, 'reports'])->name('reports');
        Route::match(['get', 'post'], '/departments', [MeetingSpaceController::class, 'departments'])->name('departments');

        Route::post('/agenda/{item_id}/now', [MeetingController::class, 'agendaNow'])->whereNumber('item_id')->name('agenda_now');
        Route::post('/agenda/{item_id}/delete', [MeetingController::class, 'agendaDelete'])->whereNumber('item_id')->name('agenda_delete');
        Route::post('/agenda/{item_id}/move/{direction}', [MeetingController::class, 'agendaMove'])->whereNumber('item_id')->whereIn('direction', ['up', 'down'])->name('agenda_move');
        Route::post('/decisions/{item_id}/delete', [MeetingController::class, 'decisionDelete'])->whereNumber('item_id')->name('decision_delete');
        Route::post('/actions/{item_id}/status', [MeetingController::class, 'actionStatus'])->whereNumber('item_id')->name('action_status');
        Route::post('/actions/{item_id}/delete', [MeetingController::class, 'actionDelete'])->whereNumber('item_id')->name('action_delete');
        Route::get('/attachments/{item_id}', [MeetingController::class, 'attachmentDownload'])->whereNumber('item_id')->name('attachment_download');
        Route::post('/attachments/{item_id}/delete', [MeetingController::class, 'attachmentDelete'])->whereNumber('item_id')->name('attachment_delete');

        Route::prefix('{meeting_id}')->whereNumber('meeting_id')->group(function () {
            Route::get('/', [MeetingController::class, 'show'])->name('meeting_detail');
            Route::match(['get', 'post'], '/edit', [MeetingController::class, 'edit'])->name('meeting_edit');
            Route::post('/status', [MeetingController::class, 'status'])->name('meeting_status');
            Route::post('/start', [MeetingController::class, 'start'])->name('meeting_start');
            Route::post('/end', [MeetingController::class, 'end'])->name('meeting_end');
            Route::get('/live', [MeetingController::class, 'live'])->name('meeting_live');
            Route::post('/delete', [MeetingController::class, 'destroy'])->name('meeting_delete');
            Route::post('/rsvp', [MeetingController::class, 'rsvp'])->name('meeting_rsvp');
            Route::post('/attendance', [MeetingController::class, 'attendance'])->name('meeting_attendance');
            Route::post('/participants', [MeetingController::class, 'participantAdd'])->name('participant_add');
            Route::post('/participants/{user_id}/remove', [MeetingController::class, 'participantRemove'])->whereNumber('user_id')->name('participant_remove');
            Route::post('/agenda', [MeetingController::class, 'agendaAdd'])->name('agenda_add');
            Route::post('/agenda/copy', [MeetingController::class, 'agendaCopy'])->name('agenda_copy');
            Route::post('/minutes', [MeetingController::class, 'minutes'])->name('minutes_save');
            Route::get('/print', [MeetingController::class, 'print'])->name('meeting_print');
            Route::post('/decisions', [MeetingController::class, 'decisionAdd'])->name('decision_add');
            Route::post('/actions', [MeetingController::class, 'actionAdd'])->name('action_add');
            Route::post('/attachments', [MeetingController::class, 'attachmentUpload'])->name('attachment_upload');
            Route::get('/ics', [MeetingController::class, 'ics'])->name('meeting_ics');
        });
    });

    // TFC Protocol: approved letter templates, documents written from them, and the protocol handbook
    Route::prefix('protocol')->name('protocol.')->group(function () {
        Route::get('/', [ProtocolController::class, 'index'])->name('index');
        Route::get('/uploads/{file}', [ProtocolController::class, 'upload'])->name('upload');
        Route::prefix('api')->controller(ProtocolController::class)->group(function () {
            Route::get('/public', 'publicInfo');
            Route::post('/logout', 'logout');
            Route::post('/password', 'password');
            Route::get('/bootstrap', 'bootstrap');
            Route::get('/summary', 'summary');
            Route::post('/notifications/read', 'notificationsRead');

            Route::get('/templates', 'templates');
            Route::post('/templates', 'templateStore');
            Route::get('/templates/{id}', 'template');
            Route::put('/templates/{id}', 'templateUpdate');
            Route::post('/templates/{id}/submit', 'templateSubmit');
            Route::post('/templates/{id}/approve', 'templateApprove');
            Route::post('/templates/{id}/return', 'templateReturn');
            Route::post('/templates/{id}/restore-version', 'templateRestore');
            Route::delete('/templates/{id}/draft', 'templateDiscardDraft');
            Route::post('/templates/{id}/archive', 'templateArchive');

            Route::get('/documents', 'documents');
            Route::post('/documents', 'documentStore');
            Route::get('/documents/{id}', 'document');
            Route::put('/documents/{id}', 'documentUpdate');
            Route::post('/documents/{id}/approve', 'documentApprove');
            Route::post('/documents/{id}/return', 'documentReturn');
            Route::delete('/documents/{id}', 'documentDelete');
            Route::get('/documents/{id}/export', 'documentExport');

            Route::get('/protocol', 'rules');
            Route::post('/protocol', 'ruleStore');
            Route::put('/protocol/{id}', 'ruleUpdate');
            Route::delete('/protocol/{id}', 'ruleDelete');
            Route::post('/protocol/{id}/ack', 'ruleAck');

            Route::get('/reports', 'reports');
            Route::get('/audit', 'audit');
            Route::get('/uploads', 'uploads');
            Route::post('/uploads', 'uploadStore');
            Route::get('/users', 'users');
            Route::post('/users', 'userStore');
            Route::put('/users/{id}', 'userUpdate');
            Route::post('/departments', 'departmentStore');
            Route::put('/departments/{id}', 'departmentUpdate');
            Route::put('/settings', 'settingsUpdate');
        });
    });

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::resource('stations', StationController::class)->except(['show', 'destroy']);
        Route::get('/activity', ActivityController::class)->name('activity');
    });
});
