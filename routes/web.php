<?php

use App\Http\Controllers\Admin\ActivityController;
use App\Http\Controllers\Admin\UserController;
use App\Http\Controllers\AuthController;
use App\Http\Controllers\DutyController;
use App\Http\Controllers\HelpdeskController;
use App\Http\Controllers\PortalController;
use Illuminate\Support\Facades\Route;

Route::middleware('guest')->group(function () {
    Route::get('/login', [AuthController::class, 'create'])->name('login');
    Route::post('/login', [AuthController::class, 'store'])->middleware('throttle:6,1');
});

Route::middleware('auth')->group(function () {
    Route::get('/', [PortalController::class, 'index'])->name('home');
    Route::redirect('/dashboard', '/');   // old bookmarks
    Route::redirect('/home', '/');
    Route::get('/open/{system}', [PortalController::class, 'open'])->name('open')->middleware('throttle:30,1');
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

    Route::middleware('can:admin')->prefix('admin')->name('admin.')->group(function () {
        Route::resource('users', UserController::class)->except(['show', 'destroy']);
        Route::get('/activity', ActivityController::class)->name('activity');
    });
});
