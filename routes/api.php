<?php

use App\Http\Controllers\Api\PaymentCallbackController;
use App\Http\Controllers\Api\UssdController;
use Illuminate\Support\Facades\Route;

// USSD gateway (Africa's Talking-compatible): sessionId, phoneNumber, text
Route::post('/ussd', UssdController::class)->name('api.ussd');

// Mobile money / GePG callback
Route::post('/payments/callback', PaymentCallbackController::class)->name('api.payments.callback');
