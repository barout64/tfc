<?php

namespace App\Http\Controllers;

use App\Enums\ShipmentStatus;
use App\Models\AuditLog;
use App\Models\Product;
use App\Models\Season;
use App\Models\Shipment;
use App\Models\StockMovement;
use App\Models\Supplier;
use App\Models\Warehouse;
use App\Services\NumberGenerator;
use App\Services\ShipmentService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ShipmentController extends Controller
{
    public function index(Request $request): View
    {
        $shipments = Shipment::with(['product', 'supplier', 'receivingWarehouse'])
            ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
            ->latest('eta')
            ->paginate(20)
            ->withQueryString();

        $open = Shipment::with('product')->whereNotIn('status', [ShipmentStatus::Completed, ShipmentStatus::Cancelled])->get();

        return view('shipments.index', [
            'shipments' => $shipments,
            'statuses' => ShipmentStatus::options(),
            'pipelineTonnes' => round($open->sum(fn ($s) => (float) $s->ordered_tonnes - $s->receivedTonnes()), 1),
            'openCount' => $open->count(),
        ]);
    }

    public function create(): View
    {
        return view('shipments.create', [
            'products' => Product::active()->get(),
            'suppliers' => Supplier::orderBy('name')->get(),
            'warehouses' => Warehouse::active()->whereIn('type', ['port', 'hq', 'zonal'])->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'supplier_id' => ['nullable', 'exists:suppliers,id'],
            'product_id' => ['required', 'exists:products,id'],
            'vessel_name' => ['nullable', 'string', 'max:100'],
            'port' => ['required', 'string', 'max:50'],
            'ordered_tonnes' => ['required', 'numeric', 'min:1'],
            'unit_cost_per_tonne' => ['nullable', 'numeric', 'min:0'],
            'eta' => ['required', 'date'],
            'receiving_warehouse_id' => ['nullable', 'exists:warehouses,id'],
            'notes' => ['nullable', 'string', 'max:1000'],
        ]);

        $shipment = Shipment::create([
            ...$data,
            'reference' => NumberGenerator::next(Shipment::class, 'reference', 'IMP'),
            'season_id' => Season::current()->id,
            'status' => ShipmentStatus::Ordered,
            'created_by' => $request->user()->id,
        ]);
        AuditLog::record('shipment.created', "Registered shipment {$shipment->reference}", $shipment);

        return redirect()->route('shipments.show', $shipment)->with('success', __('Shipment registered.'));
    }

    public function show(Shipment $shipment): View
    {
        return view('shipments.show', [
            'shipment' => $shipment->load(['product', 'supplier', 'receivingWarehouse', 'season']),
            'receipts' => StockMovement::with(['warehouse', 'batch', 'user'])->where('reference_type', $shipment->getMorphClass())->where('reference_id', $shipment->id)->latest('id')->get(),
            'warehouses' => Warehouse::active()->whereIn('type', ['port', 'hq', 'zonal'])->orderBy('name')->get(),
            'statuses' => ShipmentStatus::options(),
        ]);
    }

    public function status(Request $request, Shipment $shipment, ShipmentService $shipments): RedirectResponse
    {
        $data = $request->validate(['status' => ['required', Rule::enum(ShipmentStatus::class)->except([ShipmentStatus::Completed])]]);
        $shipments->updateStatus($shipment, ShipmentStatus::from($data['status']));

        return back()->with('success', __('Shipment status updated.'));
    }

    public function receive(Request $request, Shipment $shipment, ShipmentService $shipments): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'good_bags' => ['required', 'integer', 'min:0'],
            'damaged_bags' => ['required', 'integer', 'min:0'],
            'batch_number' => ['required', 'string', 'max:60'],
            'expires_on' => ['required', 'date', 'after:today'],
        ]);

        $shipments->receive($shipment, Warehouse::findOrFail($data['warehouse_id']), (int) $data['good_bags'], (int) $data['damaged_bags'], $data['batch_number'], $data['expires_on']);

        return back()->with('success', __(':bags bags received into stock.', ['bags' => number_format($data['good_bags'])]));
    }

    public function complete(Shipment $shipment, ShipmentService $shipments): RedirectResponse
    {
        $shipments->updateStatus($shipment, ShipmentStatus::Completed);

        return back()->with('success', __('Shipment closed.'));
    }
}
