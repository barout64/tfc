<?php

namespace App\Http\Controllers;

use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\StockBalance;
use App\Models\StockCount;
use App\Models\Warehouse;
use App\Services\NumberGenerator;
use App\Services\StockService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;

/**
 * Physical stock take: officer counts bags, a manager approves, differences become adjustments.
 */
class StockCountController extends Controller
{
    public function index(): View
    {
        return view('stock-counts.index', [
            'counts' => StockCount::with(['warehouse', 'counter', 'items'])
                ->when($this->warehouseScope(), fn ($q, $ids) => $q->whereIn('warehouse_id', $ids))
                ->latest()
                ->paginate(20),
        ]);
    }

    public function create(Request $request): View
    {
        $warehouse = $request->integer('warehouse') ? Warehouse::findOrFail($request->integer('warehouse')) : null;
        if ($warehouse) {
            $this->authorizeWarehouse($warehouse);
        }

        return view('stock-counts.create', [
            'warehouses' => $this->scopedWarehouses(),
            'warehouse' => $warehouse,
            'balances' => $warehouse
                ? StockBalance::with('batch.product')->where('warehouse_id', $warehouse->id)->where('bags', '>', 0)->get()
                : collect(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'counted_on' => ['required', 'date', 'before_or_equal:today'],
            'notes' => ['nullable', 'string', 'max:1000'],
            'counts' => ['required', 'array', 'min:1'],
            'counts.*' => ['required', 'integer', 'min:0'],
        ]);

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $this->authorizeWarehouse($warehouse);

        $count = DB::transaction(function () use ($data, $warehouse, $request) {
            $count = StockCount::create([
                'number' => NumberGenerator::next(StockCount::class, 'number', 'CNT'),
                'warehouse_id' => $warehouse->id,
                'counted_on' => $data['counted_on'],
                'status' => 'submitted',
                'counted_by' => $request->user()->id,
                'notes' => $data['notes'] ?? null,
            ]);

            foreach ($data['counts'] as $batchId => $counted) {
                $count->items()->create([
                    'stock_batch_id' => $batchId,
                    'system_bags' => (int) StockBalance::where(['warehouse_id' => $warehouse->id, 'stock_batch_id' => $batchId])->value('bags'),
                    'counted_bags' => $counted,
                ]);
            }

            return $count;
        });

        return redirect()->route('stock-counts.show', $count)->with('success', __('Stock count submitted for approval.'));
    }

    public function show(StockCount $stockCount): View
    {
        $this->authorizeWarehouse($stockCount->warehouse);

        return view('stock-counts.show', ['count' => $stockCount->load(['warehouse', 'items.batch.product', 'counter', 'approver'])]);
    }

    public function approve(Request $request, StockCount $stockCount, StockService $stock): RedirectResponse
    {
        if ($stockCount->status !== 'submitted') {
            throw new BusinessRuleException(__('Only submitted counts can be approved.'));
        }

        if ($request->input('decision') === 'reject') {
            $stockCount->update(['status' => 'rejected', 'approved_by' => $request->user()->id, 'approved_at' => now()]);

            return back()->with('success', __('Stock count rejected.'));
        }

        DB::transaction(function () use ($stockCount, $stock, $request) {
            // Apply the variance found at count time, so movements recorded since then are kept.
            foreach ($stockCount->items as $item) {
                $variance = $item->variance();
                if ($variance > 0) {
                    $stock->add($stockCount->warehouse, $item->batch, $variance, MovementType::Adjustment, $stockCount, "Stock count {$stockCount->number}");
                } elseif ($variance < 0) {
                    $stock->remove($stockCount->warehouse, $item->batch, -$variance, MovementType::Adjustment, $stockCount, "Stock count {$stockCount->number}");
                }
            }
            $stockCount->update(['status' => 'approved', 'approved_by' => $request->user()->id, 'approved_at' => now()]);
        });

        AuditLog::record('stock_count.approved', "Approved stock count {$stockCount->number}", $stockCount);

        return back()->with('success', __('Stock count approved and balances adjusted.'));
    }
}
