<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Exceptions\InsufficientStockException;
use App\Models\Product;
use App\Models\StockBalance;
use App\Models\StockBatch;
use App\Models\StockMovement;
use App\Models\Warehouse;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * The only place that changes stock. Every change writes a ledger row and updates the balance.
 */
class StockService
{
    public function add(Warehouse $warehouse, StockBatch $batch, int $bags, MovementType $type, ?Model $reference = null, ?string $notes = null): StockMovement
    {
        return $this->apply($warehouse, $batch, abs($bags), $type, $reference, $notes);
    }

    public function remove(Warehouse $warehouse, StockBatch $batch, int $bags, MovementType $type, ?Model $reference = null, ?string $notes = null): StockMovement
    {
        return $this->apply($warehouse, $batch, -abs($bags), $type, $reference, $notes);
    }

    public function balance(Warehouse $warehouse, StockBatch $batch): int
    {
        return (int) StockBalance::where('warehouse_id', $warehouse->id)
            ->where('stock_batch_id', $batch->id)
            ->value('bags');
    }

    /**
     * Total bags of a product at a warehouse, across batches.
     */
    public function productBalance(Warehouse $warehouse, Product $product): int
    {
        return (int) StockBalance::where('warehouse_id', $warehouse->id)
            ->whereHas('batch', fn ($q) => $q->where('product_id', $product->id))
            ->sum('bags');
    }

    /**
     * Pick batches First-Expiry-First-Out for the requested quantity.
     *
     * @return Collection<int, array{batch: StockBatch, bags: int}>
     *
     * @throws InsufficientStockException
     */
    public function allocate(Warehouse $warehouse, Product $product, int $bags): Collection
    {
        $balances = StockBalance::query()
            ->with('batch')
            ->where('warehouse_id', $warehouse->id)
            ->where('bags', '>', 0)
            ->whereHas('batch', fn ($q) => $q->where('product_id', $product->id))
            ->get()
            ->sortBy(fn (StockBalance $b) => $b->batch->expires_on?->timestamp ?? PHP_INT_MAX);

        $available = (int) $balances->sum('bags');
        if ($available < $bags) {
            throw InsufficientStockException::for($product->name, $warehouse->name, $bags, $available);
        }

        $allocation = collect();
        $remaining = $bags;
        foreach ($balances as $balance) {
            if ($remaining <= 0) {
                break;
            }
            $take = min($remaining, $balance->bags);
            $allocation->push(['batch' => $balance->batch, 'bags' => $take]);
            $remaining -= $take;
        }

        return $allocation;
    }

    private function apply(Warehouse $warehouse, StockBatch $batch, int $delta, MovementType $type, ?Model $reference, ?string $notes): StockMovement
    {
        return DB::transaction(function () use ($warehouse, $batch, $delta, $type, $reference, $notes) {
            $balance = StockBalance::query()
                ->where('warehouse_id', $warehouse->id)
                ->where('stock_batch_id', $batch->id)
                ->lockForUpdate()
                ->first()
                ?? StockBalance::create(['warehouse_id' => $warehouse->id, 'stock_batch_id' => $batch->id, 'bags' => 0]);

            $newBalance = $balance->bags + $delta;
            if ($newBalance < 0) {
                throw InsufficientStockException::for($batch->product->name, $warehouse->name, abs($delta), $balance->bags);
            }

            $balance->update(['bags' => $newBalance]);

            return StockMovement::create([
                'warehouse_id' => $warehouse->id,
                'stock_batch_id' => $batch->id,
                'type' => $type,
                'bags' => $delta,
                'balance_after' => $newBalance,
                'reference_type' => $reference?->getMorphClass(),
                'reference_id' => $reference?->getKey(),
                'user_id' => auth()->id(),
                'notes' => $notes,
            ]);
        });
    }
}
