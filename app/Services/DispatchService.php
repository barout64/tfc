<?php

namespace App\Services;

use App\Enums\DispatchStatus;
use App\Enums\MovementType;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Dispatch;
use App\Models\Product;
use App\Models\Season;
use App\Models\TransporterPayment;
use App\Models\User;
use App\Models\Warehouse;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

/**
 * Lifecycle of a truck trip: draft → approved → in transit → delivered.
 */
class DispatchService
{
    public function __construct(private StockService $stock, private SmsService $sms) {}

    /**
     * @param  array<string, mixed>  $data
     * @param  list<array{product_id: int, bags: int}>  $lines
     */
    public function create(array $data, array $lines, User $user): Dispatch
    {
        $from = Warehouse::findOrFail($data['from_warehouse_id']);

        if ((int) $data['from_warehouse_id'] === (int) $data['to_warehouse_id']) {
            throw new BusinessRuleException(__('Origin and destination must be different.'));
        }

        return DB::transaction(function () use ($data, $lines, $from, $user) {
            $dispatch = Dispatch::create([
                ...$data,
                'number' => NumberGenerator::next(Dispatch::class, 'number', 'DSP'),
                'season_id' => Season::current()->id,
                'status' => DispatchStatus::Draft,
                'tracking_token' => Str::random(32),
                'created_by' => $user->id,
            ]);

            foreach ($lines as $line) {
                $product = Product::findOrFail($line['product_id']);
                // Choose batches now (FEFO) so the waybill shows exact batch numbers.
                foreach ($this->stock->allocate($from, $product, (int) $line['bags']) as $allocation) {
                    $dispatch->items()->create([
                        'stock_batch_id' => $allocation['batch']->id,
                        'bags_sent' => $allocation['bags'],
                    ]);
                }
            }

            $dispatch->load('items.batch.product');
            $dispatch->update(['transport_cost' => $this->estimateCost($dispatch)]);
            $this->event($dispatch, 'created', __('Dispatch created'), $user);
            AuditLog::record('dispatch.created', "Created dispatch {$dispatch->number}", $dispatch);

            return $dispatch;
        });
    }

    public function approve(Dispatch $dispatch, User $user): void
    {
        $this->assertStatus($dispatch, DispatchStatus::Draft);

        if (! $dispatch->transporter_id || ! $dispatch->vehicle_id || ! $dispatch->driver_id) {
            throw new BusinessRuleException(__('Assign a transporter, vehicle and driver before approving.'));
        }

        $dispatch->update(['status' => DispatchStatus::Approved, 'approved_at' => now(), 'approved_by' => $user->id]);
        $this->event($dispatch, 'approved', __('Approved for loading'), $user);
        AuditLog::record('dispatch.approved', "Approved dispatch {$dispatch->number}", $dispatch);
    }

    /**
     * Truck is loaded and leaves: stock leaves the origin warehouse.
     */
    public function depart(Dispatch $dispatch, User $user): void
    {
        $this->assertStatus($dispatch, DispatchStatus::Approved);

        DB::transaction(function () use ($dispatch, $user) {
            foreach ($dispatch->items as $item) {
                $this->stock->remove($dispatch->fromWarehouse, $item->batch, $item->bags_sent, MovementType::DispatchOut, $dispatch, "To {$dispatch->toWarehouse->name}");
            }

            $dispatch->update([
                'status' => DispatchStatus::InTransit,
                'dispatched_at' => now(),
                'expected_arrival_at' => now()->addMinutes($this->travelMinutes($dispatch->distance_km)),
                'last_lat' => $dispatch->fromWarehouse->lat,
                'last_lng' => $dispatch->fromWarehouse->lng,
                'last_ping_at' => now(),
            ]);

            $this->event($dispatch, 'departed', __('Left :place', ['place' => $dispatch->fromWarehouse->name]), $user, $dispatch->fromWarehouse->lat, $dispatch->fromWarehouse->lng);
        });

        if ($dispatch->toWarehouse->contact_phone) {
            $this->sms->send($dispatch->toWarehouse->contact_phone, __('TFC: Truck :plate is on the way with :bags bags (:number). Expected: :eta.', [
                'plate' => $dispatch->vehicle?->plate_number,
                'bags' => number_format($dispatch->totalBags()),
                'number' => $dispatch->number,
                'eta' => $dispatch->expected_arrival_at->format('d M H:i'),
            ]), 'alert');
        }

        AuditLog::record('dispatch.departed', "Dispatch {$dispatch->number} departed", $dispatch);
    }

    /**
     * GPS ping or status note from the driver.
     */
    public function track(Dispatch $dispatch, string $event, ?float $lat, ?float $lng, ?string $note = null, ?User $user = null): void
    {
        if ($dispatch->status !== DispatchStatus::InTransit) {
            throw new BusinessRuleException(__('Only trucks in transit can send updates.'));
        }

        if ($lat !== null && $lng !== null) {
            $dispatch->update(['last_lat' => $lat, 'last_lng' => $lng, 'last_ping_at' => now()]);
        }

        $this->event($dispatch, $event, $note, $user, $lat, $lng);
    }

    /**
     * Proof of delivery: receiver confirms counts per line. Good bags enter the destination.
     *
     * @param  array<int, array{received: int, damaged: int}>  $counts  keyed by dispatch_item id
     */
    public function receive(Dispatch $dispatch, array $counts, string $receiverName, ?string $notes, ?UploadedFile $photo, User $user): void
    {
        $this->assertStatus($dispatch, DispatchStatus::InTransit);

        DB::transaction(function () use ($dispatch, $counts, $receiverName, $notes, $photo, $user) {
            foreach ($dispatch->items as $item) {
                $received = (int) ($counts[$item->id]['received'] ?? $item->bags_sent);
                $damaged = (int) ($counts[$item->id]['damaged'] ?? 0);

                if ($received > $item->bags_sent) {
                    throw new BusinessRuleException(__('Received bags cannot exceed bags sent for batch :batch.', ['batch' => $item->batch->batch_number]));
                }
                if ($damaged > $received) {
                    throw new BusinessRuleException(__('Damaged bags cannot exceed bags received.'));
                }

                $item->update(['bags_received' => $received, 'bags_damaged' => $damaged]);

                $good = $received - $damaged;
                if ($good > 0) {
                    $this->stock->add($dispatch->toWarehouse, $item->batch, $good, MovementType::TransferIn, $dispatch, "From {$dispatch->fromWarehouse->name}");
                }
            }

            $dispatch->update([
                'status' => DispatchStatus::Delivered,
                'delivered_at' => now(),
                'receiver_name' => $receiverName,
                'pod_notes' => $notes,
                'pod_photo' => $photo?->store('pod', 'public'),
                'received_by' => $user->id,
                'last_lat' => $dispatch->toWarehouse->lat,
                'last_lng' => $dispatch->toWarehouse->lng,
            ]);

            $this->event($dispatch, 'delivered', __('Received by :name', ['name' => $receiverName]), $user, $dispatch->toWarehouse->lat, $dispatch->toWarehouse->lng);
            $this->createTransporterPayment($dispatch->fresh(['items.batch.product', 'transporter']));
        });

        AuditLog::record('dispatch.delivered', "Dispatch {$dispatch->number} delivered", $dispatch);
    }

    public function cancel(Dispatch $dispatch, User $user, string $reason): void
    {
        if (! in_array($dispatch->status, [DispatchStatus::Draft, DispatchStatus::Approved], true)) {
            throw new BusinessRuleException(__('Only draft or approved dispatches can be cancelled.'));
        }

        $dispatch->update(['status' => DispatchStatus::Cancelled]);
        $this->event($dispatch, 'cancelled', $reason, $user);
        AuditLog::record('dispatch.cancelled', "Cancelled dispatch {$dispatch->number}: {$reason}", $dispatch);
    }

    public function estimateCost(Dispatch $dispatch): float
    {
        $rate = (float) ($dispatch->transporter?->rate_per_tonne_km ?? 0);

        return round($dispatch->totalTonnes() * $dispatch->distance_km * $rate, 2);
    }

    public function travelMinutes(int $distanceKm): int
    {
        $speed = max(1, config('kilimo.average_truck_speed_kmh'));
        $drivingHours = $distanceKm / $speed;
        $restDays = (int) floor($drivingHours / max(1, config('kilimo.driving_hours_per_day')));

        return (int) round(($drivingHours + $restDays * (24 - config('kilimo.driving_hours_per_day'))) * 60) + 60;
    }

    private function createTransporterPayment(Dispatch $dispatch): void
    {
        if (! $dispatch->transporter) {
            return;
        }

        $gross = $this->estimateCost($dispatch);
        $deduction = round($dispatch->lostBags() * (float) $dispatch->transporter->loss_penalty_per_bag, 2);

        TransporterPayment::updateOrCreate(['dispatch_id' => $dispatch->id], [
            'transporter_id' => $dispatch->transporter_id,
            'gross_amount' => $gross,
            'loss_deduction' => min($deduction, $gross),
            'net_amount' => max(0, $gross - $deduction),
            'status' => 'pending',
        ]);
    }

    private function assertStatus(Dispatch $dispatch, DispatchStatus $expected): void
    {
        if ($dispatch->status !== $expected) {
            throw new BusinessRuleException(__('This dispatch is :status; action not allowed.', ['status' => $dispatch->status->label()]));
        }
    }

    private function event(Dispatch $dispatch, string $event, ?string $note, ?User $user, ?float $lat = null, ?float $lng = null): void
    {
        $dispatch->events()->create([
            'event' => $event,
            'note' => $note,
            'lat' => $lat,
            'lng' => $lng,
            'user_id' => $user?->id,
            'recorded_at' => now(),
        ]);
    }
}
