<?php

namespace App\Http\Controllers;

use App\Models\SmsMessage;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SmsController extends Controller
{
    public function index(Request $request): View
    {
        return view('sms.index', [
            'messages' => SmsMessage::query()
                ->when($request->query('category'), fn ($q, $c) => $q->where('category', $c))
                ->when($request->query('phone'), fn ($q, $p) => $q->where('phone', 'like', "%{$p}%"))
                ->latest()
                ->paginate(40)
                ->withQueryString(),
            'categories' => SmsMessage::distinct()->pluck('category')->filter(),
            'driver' => config('kilimo.sms.driver'),
        ]);
    }
}
