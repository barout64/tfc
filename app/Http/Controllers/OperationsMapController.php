<?php

namespace App\Http\Controllers;

use App\Enums\DispatchStatus;
use App\Models\Dispatch;
use App\Services\InsightService;
use Illuminate\Http\JsonResponse;
use Illuminate\View\View;

class OperationsMapController extends Controller
{
    public function index(): View
    {
        return view('map.index');
    }

    /**
     * Warehouses with stock + trucks on the road. Polled by the map every 30 s.
     */
    public function data(InsightService $insights): JsonResponse
    {
        $warehouses = $insights->stockByWarehouse()
            ->filter(fn ($w) => $w->lat && $w->lng)
            ->map(fn ($w) => [
                'name' => $w->name,
                'type' => $w->type,
                'lat' => (float) $w->lat,
                'lng' => (float) $w->lng,
                'tonnes' => round((float) $w->tonnes, 1),
                'capacity' => (int) $w->capacity_tonnes,
                'url' => route('warehouses.show', $w->id),
            ])->values();

        $trucks = Dispatch::with(['fromWarehouse', 'toWarehouse', 'vehicle', 'driver', 'items.batch.product'])
            ->where('status', DispatchStatus::InTransit)
            ->get()
            ->map(fn (Dispatch $d) => [
                'number' => $d->number,
                'plate' => $d->vehicle?->plate_number,
                'driver' => $d->driver?->name,
                'from' => [$d->fromWarehouse->lat, $d->fromWarehouse->lng, $d->fromWarehouse->name],
                'to' => [$d->toWarehouse->lat, $d->toWarehouse->lng, $d->toWarehouse->name],
                'lat' => $d->last_lat ?? $d->fromWarehouse->lat,
                'lng' => $d->last_lng ?? $d->fromWarehouse->lng,
                'tonnes' => $d->totalTonnes(),
                'eta' => $d->expected_arrival_at?->format('d M H:i'),
                'late' => $d->isLate(),
                'silent' => $d->isSilent(config('kilimo.silent_truck_hours')),
                'last_seen' => ($d->last_ping_at ?? $d->dispatched_at)?->diffForHumans(),
                'url' => route('dispatches.show', $d),
            ]);

        return response()->json(['warehouses' => $warehouses, 'trucks' => $trucks]);
    }
}
