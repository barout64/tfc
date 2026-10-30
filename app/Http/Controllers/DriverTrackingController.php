<?php

namespace App\Http\Controllers;

use App\Enums\DispatchStatus;
use App\Models\Dispatch;
use App\Services\DispatchService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Mobile page for drivers: no login, protected by the unguessable trip token.
 */
class DriverTrackingController extends Controller
{
    public function show(string $token): View
    {
        $dispatch = Dispatch::with(['fromWarehouse', 'toWarehouse', 'vehicle', 'driver', 'items.batch.product', 'events'])
            ->where('tracking_token', $token)
            ->firstOrFail();

        return view('public.trip', compact('dispatch'));
    }

    public function update(Request $request, string $token, DispatchService $dispatches): JsonResponse|RedirectResponse
    {
        $dispatch = Dispatch::where('tracking_token', $token)->firstOrFail();

        $data = $request->validate([
            'event' => ['required', Rule::in(['checkpoint', 'rest', 'delay', 'breakdown', 'arrived'])],
            'lat' => ['nullable', 'numeric', 'between:-90,90'],
            'lng' => ['nullable', 'numeric', 'between:-180,180'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        abort_if($dispatch->status !== DispatchStatus::InTransit, 422, __('This trip is not in transit.'));

        $dispatches->track($dispatch, $data['event'], $data['lat'] ?? null, $data['lng'] ?? null, $data['note'] ?? null);

        if ($request->expectsJson()) {
            return response()->json(['ok' => true]);
        }

        return back()->with('success', __('Update sent. Thank you!'));
    }
}
