<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Services\UssdService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class UssdController extends Controller
{
    public function __invoke(Request $request, UssdService $ussd): Response
    {
        $data = $request->validate([
            'sessionId' => ['required', 'string', 'max:100'],
            'phoneNumber' => ['required', 'string', 'max:20'],
            'text' => ['nullable', 'string', 'max:500'],
        ]);

        $reply = $ussd->handle($data['sessionId'], $data['phoneNumber'], (string) ($data['text'] ?? ''));

        return response($reply, 200)->header('Content-Type', 'text/plain');
    }
}
