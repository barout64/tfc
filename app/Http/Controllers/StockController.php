<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockMovement;
use App\Services\InsightService;
use Illuminate\Http\Request;
use Illuminate\View\View;

class StockController extends Controller
{
    public function index(Request $request, InsightService $insights): View
    {
        $scope = $this->warehouseScope();
        $warehouseId = $request->integer('warehouse') ?: null;

        $balances = StockBalance::with(['warehouse.region', 'batch.product'])
            ->where('bags', '>', 0)
            ->whereHas('warehouse', fn ($q) => $q->where('type', '!=', 'agro_dealer'))
            ->when($scope, fn ($q) => $q->whereIn('warehouse_id', $scope))
            ->when($warehouseId, fn ($q) => $q->where('warehouse_id', $warehouseId))
            ->when($request->integer('product'), fn ($q, $id) => $q->whereHas('batch', fn ($b) => $b->where('product_id', $id)))
            ->when($request->boolean('expiring'), fn ($q) => $q->whereHas('batch', fn ($b) => $b->where('expires_on', '<=', now()->addDays(config('kilimo.expiry_warning_days')))))
            ->orderBy('warehouse_id')
            ->paginate(30)
            ->withQueryString();

        return view('stock.index', [
            'balances' => $balances,
            'summary' => $insights->stockByProduct($warehouseId ? [$warehouseId] : $scope),
            'warehouses' => $this->scopedWarehouses(),
            'products' => Product::active()->get(),
        ]);
    }

    public function ledger(Request $request): View
    {
        $scope = $this->warehouseScope();

        $movements = StockMovement::with(['warehouse', 'batch.product', 'user', 'reference'])
            ->when($scope, fn ($q) => $q->whereIn('warehouse_id', $scope))
            ->when($request->integer('warehouse'), fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->query('type'), fn ($q, $type) => $q->where('type', $type))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('created_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('created_at', '<=', $to))
            ->latest('id')
            ->paginate(40)
            ->withQueryString();

        return view('stock.ledger', [
            'movements' => $movements,
            'warehouses' => $this->scopedWarehouses(),
            'types' => MovementType::options(),
        ]);
    }
}
