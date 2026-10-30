<?php

namespace App\Http\Controllers;

use Illuminate\Http\Request;
use Illuminate\View\View;

class TransporterPortalController extends Controller
{
    public function index(Request $request): View
    {
        $transporter = $request->user()->transporter()->firstOrFail();

        return view('transporter-portal.index', [
            'transporter' => $transporter->load(['vehicles', 'drivers']),
            'trips' => $transporter->dispatches()->with(['fromWarehouse', 'toWarehouse', 'vehicle', 'driver', 'items.batch.product'])->latest()->take(25)->get(),
            'payments' => $transporter->payments()->with('dispatch')->latest()->take(25)->get(),
        ]);
    }
}
