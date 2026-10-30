<?php

namespace App\Http\Controllers;

use App\Enums\WarehouseType;
use App\Models\AuditLog;
use App\Models\District;
use App\Models\Region;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\InsightService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WarehouseController extends Controller
{
    public function index(Request $request, InsightService $insights): View
    {
        $stock = $insights->stockByWarehouse()->keyBy('id');

        $warehouses = Warehouse::with(['region', 'manager'])
            ->where('type', '!=', WarehouseType::AgroDealer)
            ->when($this->warehouseScope(), fn ($q, $ids) => $q->whereIn('id', $ids))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('region'), fn ($q, $region) => $q->where('region_id', $region))
            ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('name', 'like', "%{$term}%")->orWhere('code', 'like', "%{$term}%")))
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return view('warehouses.index', [
            'warehouses' => $warehouses,
            'stock' => $stock,
            'regions' => Region::orderBy('name')->get(),
            'types' => collect(WarehouseType::options())->except('agro_dealer'),
        ]);
    }

    public function show(Warehouse $warehouse): View
    {
        $this->authorizeWarehouse($warehouse);

        $balances = StockBalance::with('batch.product')
            ->where('warehouse_id', $warehouse->id)
            ->where('bags', '>', 0)
            ->get()
            ->sortBy(fn ($b) => [$b->batch->product->id, $b->batch->expires_on]);

        return view('warehouses.show', [
            'warehouse' => $warehouse->load(['region', 'district', 'manager']),
            'balances' => $balances,
            'byProduct' => $balances->groupBy(fn ($b) => $b->batch->product->code)->map(fn ($rows) => [
                'product' => $rows->first()->batch->product,
                'bags' => $rows->sum('bags'),
                'tonnes' => $rows->first()->batch->product->bagsToTonnes($rows->sum('bags')),
            ]),
            'movements' => StockMovement::with(['batch.product', 'user'])->where('warehouse_id', $warehouse->id)->latest('id')->take(15)->get(),
        ]);
    }

    public function create(): View
    {
        return view('warehouses.form', $this->formData(new Warehouse(['type' => WarehouseType::Zonal, 'is_active' => true, 'is_public' => true])));
    }

    public function store(Request $request): RedirectResponse
    {
        $warehouse = Warehouse::create($this->validated($request));
        AuditLog::record('warehouse.created', "Created warehouse {$warehouse->name}", $warehouse);

        return redirect()->route('warehouses.show', $warehouse)->with('success', __('Warehouse saved.'));
    }

    public function edit(Warehouse $warehouse): View
    {
        return view('warehouses.form', $this->formData($warehouse));
    }

    public function update(Request $request, Warehouse $warehouse): RedirectResponse
    {
        $warehouse->update($this->validated($request, $warehouse));
        AuditLog::record('warehouse.updated', "Updated warehouse {$warehouse->name}", $warehouse, $warehouse->getChanges());

        return redirect()->route('warehouses.show', $warehouse)->with('success', __('Warehouse updated.'));
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Warehouse $warehouse = null): array
    {
        $data = $request->validate([
            'code' => ['required', 'string', 'max:30', Rule::unique('warehouses', 'code')->ignore($warehouse)],
            'name' => ['required', 'string', 'max:150'],
            'type' => ['required', Rule::enum(WarehouseType::class)->except([WarehouseType::AgroDealer])],
            'region_id' => ['required', 'exists:regions,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'tfra_number' => ['nullable', 'string', 'max:50'],
            'capacity_tonnes' => ['required', 'integer', 'min:0'],
            'address' => ['nullable', 'string', 'max:255'],
            'lat' => ['required', 'numeric', 'between:-12,0'],
            'lng' => ['required', 'numeric', 'between:29,41'],
            'contact_phone' => ['nullable', 'string', 'max:20'],
            'manager_id' => ['nullable', 'exists:users,id'],
        ], ['lat.between' => __('Latitude must be inside Tanzania (between -12 and 0).'), 'lng.between' => __('Longitude must be inside Tanzania (between 29 and 41).')]);

        return [...$data, 'is_active' => $request->boolean('is_active'), 'is_public' => $request->boolean('is_public')];
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Warehouse $warehouse): array
    {
        return [
            'warehouse' => $warehouse,
            'regions' => Region::orderBy('name')->get(),
            'districts' => District::orderBy('name')->get(['id', 'region_id', 'name']),
            'managers' => User::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'types' => collect(WarehouseType::options())->except('agro_dealer'),
        ];
    }
}
