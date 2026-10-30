<?php

namespace App\Services;

use App\Enums\MovementType;
use App\Enums\ShipmentStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Shipment;
use App\Models\StockBatch;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class ShipmentService
{
    public function __construct(private StockService $stock) {}

    /**
     * Offload part (or all) of a vessel into a warehouse. Can be called several times.
     */
    public function receive(Shipment $shipment, Warehouse $warehouse, int $goodBags, int $damagedBags, string $batchNumber, ?string $expiresOn): StockBatch
    {
        if (in_array($shipment->status, [ShipmentStatus::Completed, ShipmentStatus::Cancelled], true)) {
            throw new BusinessRuleException(__('This shipment is already closed.'));
        }

        return DB::transaction(function () use ($shipment, $warehouse, $goodBags, $damagedBags, $batchNumber, $expiresOn) {
            $unitCost = $shipment->unit_cost_per_tonne
                ? round((float) $shipment->unit_cost_per_tonne * $shipment->product->package_kg / 1000, 2)
                : null;

            $batch = StockBatch::firstOrCreate(
                ['product_id' => $shipment->product_id, 'batch_number' => $batchNumber],
                ['source' => 'import', 'shipment_id' => $shipment->id, 'expires_on' => $expiresOn, 'unit_cost' => $unitCost],
            );

            if ($goodBags > 0) {
                $this->stock->add($warehouse, $batch, $goodBags, MovementType::Receipt, $shipment, __('Offloaded from :vessel', ['vessel' => $shipment->vessel_name ?? $shipment->reference]));
            }

            $shipment->update([
                'bags_received' => $shipment->bags_received + $goodBags,
                'bags_damaged' => $shipment->bags_damaged + $damagedBags,
                'receiving_warehouse_id' => $warehouse->id,
                'batch_number' => $batchNumber,
                'expires_on' => $expiresOn,
                'arrived_on' => $shipment->arrived_on ?? now(),
                'status' => ShipmentStatus::Offloading,
            ]);

            AuditLog::record('shipment.received', "Received {$goodBags} bags ({$damagedBags} damaged) from {$shipment->reference} into {$warehouse->name}", $shipment);

            return $batch;
        });
    }

    public function updateStatus(Shipment $shipment, ShipmentStatus $status): void
    {
        $attributes = ['status' => $status];
        if ($status === ShipmentStatus::AtPort && $shipment->arrived_on === null) {
            $attributes['arrived_on'] = now();
        }

        $shipment->update($attributes);
        AuditLog::record('shipment.status', "Shipment {$shipment->reference} marked {$status->label()}", $shipment);
    }
}
