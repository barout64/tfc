<?php

namespace App\Http\Controllers;

use App\Enums\DispatchStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Dispatch;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\Transporter;
use App\Models\Vehicle;
use App\Models\Warehouse;
use App\Services\DispatchService;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DispatchController extends Controller
{
    public function index(Request $request): View
    {
        $scope = $this->warehouseScope();

        $dispatches = Dispatch::with(['fromWarehouse', 'toWarehouse', 'transporter', 'vehicle', 'items.batch.product'])
            ->when($scope, fn ($q) => $q->where(fn ($w) => $w->whereIn('from_warehouse_id', $scope)->orWhereIn('to_warehouse_id', $scope)))
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->when($request->integer('transporter'), fn ($q, $id) => $q->where('transporter_id', $id))
            ->when($request->query('q'), fn ($q, $term) => $q->where('number', 'like', "%{$term}%"))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('dispatches.index', [
            'dispatches' => $dispatches,
            'statuses' => DispatchStatus::options(),
            'transporters' => Transporter::orderBy('name')->get(['id', 'name']),
            'counts' => Dispatch::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
        ]);
    }

    public function create(): View
    {
        $warehouses = Warehouse::active()->where('type', '!=', 'agro_dealer')->orderBy('name')->get();

        // Stock per warehouse & product, so the form can show what is available.
        $available = StockBalance::query()
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_balances.stock_batch_id')
            ->groupBy('stock_balances.warehouse_id', 'stock_batches.product_id')
            ->selectRaw('stock_balances.warehouse_id, stock_batches.product_id, SUM(stock_balances.bags) as bags')
            ->get()
            ->groupBy('warehouse_id')
            ->map(fn ($rows) => $rows->pluck('bags', 'product_id'));

        return view('dispatches.create', [
            'warehouses' => $warehouses,
            'products' => Product::active()->get(),
            'transporters' => Transporter::where('status', 'active')->with(['vehicles' => fn ($q) => $q->where('is_active', true), 'drivers' => fn ($q) => $q->where('is_active', true)])->orderBy('name')->get(),
            'available' => $available,
        ]);
    }

    public function store(Request $request, DispatchService $dispatches): RedirectResponse
    {
        $data = $request->validate([
            'from_warehouse_id' => ['required', 'exists:warehouses,id'],
            'to_warehouse_id' => ['required', 'exists:warehouses,id', 'different:from_warehouse_id'],
            'transporter_id' => ['nullable', 'exists:transporters,id'],
            'vehicle_id' => ['nullable', Rule::exists('vehicles', 'id')->where('transporter_id', $request->input('transporter_id'))],
            'driver_id' => ['nullable', Rule::exists('drivers', 'id')->where('transporter_id', $request->input('transporter_id'))],
            'distance_km' => ['required', 'integer', 'min:1', 'max:3000'],
            'planned_date' => ['required', 'date'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.bags' => ['required', 'integer', 'min:1'],
        ]);

        $this->guardCapacity($data);

        $lines = $data['lines'];
        unset($data['lines']);
        $dispatch = $dispatches->create($data, array_values($lines), $request->user());

        return redirect()->route('dispatches.show', $dispatch)->with('success', __('Dispatch :number created. Approve it to start loading.', ['number' => $dispatch->number]));
    }

    public function show(Dispatch $dispatch): View
    {
        return view('dispatches.show', [
            'dispatch' => $dispatch->load(['fromWarehouse', 'toWarehouse', 'transporter', 'vehicle', 'driver', 'items.batch.product', 'events.user', 'payment', 'creator']),
        ]);
    }

    public function waybill(Dispatch $dispatch): View
    {
        return view('dispatches.waybill', ['dispatch' => $dispatch->load(['fromWarehouse.region', 'toWarehouse.region', 'transporter', 'vehicle', 'driver', 'items.batch.product'])]);
    }

    public function approve(Request $request, Dispatch $dispatch, DispatchService $dispatches): RedirectResponse
    {
        $dispatches->approve($dispatch, $request->user());

        return back()->with('success', __('Dispatch approved.'));
    }

    public function depart(Request $request, Dispatch $dispatch, DispatchService $dispatches, SmsService $sms): RedirectResponse
    {
        $dispatch->load(['items.batch.product', 'fromWarehouse', 'toWarehouse', 'vehicle', 'driver']);
        $dispatches->depart($dispatch, $request->user());

        if ($dispatch->driver) {
            $sms->send($dispatch->driver->phone, __('TFC trip :number to :to. Send location updates here: :url', [
                'number' => $dispatch->number,
                'to' => $dispatch->toWarehouse->name,
                'url' => route('trip.show', $dispatch->tracking_token),
            ]), 'trip');
        }

        return back()->with('success', __('Truck departed. The driver has received the tracking link by SMS.'));
    }

    public function track(Request $request, Dispatch $dispatch, DispatchService $dispatches): RedirectResponse
    {
        $data = $request->validate([
            'event' => ['required', Rule::in(['checkpoint', 'rest', 'delay', 'breakdown', 'arrived'])],
            'lat' => ['nullable', 'numeric'],
            'lng' => ['nullable', 'numeric'],
            'note' => ['nullable', 'string', 'max:255'],
        ]);

        $dispatches->track($dispatch, $data['event'], $data['lat'] ?? null, $data['lng'] ?? null, $data['note'] ?? null, $request->user());

        return back()->with('success', __('Tracking update saved.'));
    }

    public function receiveForm(Dispatch $dispatch): View
    {
        $this->authorizeWarehouse($dispatch->toWarehouse);

        return view('dispatches.receive', ['dispatch' => $dispatch->load(['fromWarehouse', 'toWarehouse', 'vehicle', 'driver', 'items.batch.product'])]);
    }

    public function receive(Request $request, Dispatch $dispatch, DispatchService $dispatches): RedirectResponse
    {
        $this->authorizeWarehouse($dispatch->toWarehouse);

        $data = $request->validate([
            'receiver_name' => ['required', 'string', 'max:100'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'photo' => ['nullable', 'image', 'max:5120'],
            'items' => ['required', 'array'],
            'items.*.received' => ['required', 'integer', 'min:0'],
            'items.*.damaged' => ['required', 'integer', 'min:0'],
        ]);

        $dispatch->load(['items.batch.product', 'fromWarehouse', 'toWarehouse', 'transporter']);
        $dispatches->receive($dispatch, $data['items'], $data['receiver_name'], $data['notes'] ?? null, $request->file('photo'), $request->user());

        return redirect()->route('dispatches.show', $dispatch)->with('success', __('Delivery confirmed and stock added to :warehouse.', ['warehouse' => $dispatch->toWarehouse->name]));
    }

    public function cancel(Request $request, Dispatch $dispatch, DispatchService $dispatches): RedirectResponse
    {
        $data = $request->validate(['reason' => ['required', 'string', 'max:255']]);
        $dispatches->cancel($dispatch, $request->user(), $data['reason']);

        return back()->with('success', __('Dispatch cancelled.'));
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function guardCapacity(array $data): void
    {
        if (empty($data['vehicle_id'])) {
            return;
        }

        $vehicle = Vehicle::findOrFail($data['vehicle_id']);
        $tonnes = collect($data['lines'])->sum(fn ($line) => Product::find($line['product_id'])->bagsToTonnes((int) $line['bags']));

        if ($tonnes > (float) $vehicle->capacity_tonnes) {
            throw new BusinessRuleException(__('Load of :load t exceeds vehicle capacity of :capacity t.', ['load' => $tonnes, 'capacity' => $vehicle->capacity_tonnes]));
        }
    }
}
