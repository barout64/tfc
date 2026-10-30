<?php

namespace App\Services;

use App\Enums\ComplaintStatus;
use App\Enums\DealerStatus;
use App\Enums\DispatchStatus;
use App\Enums\ShipmentStatus;
use App\Models\AgroDealer;
use App\Models\Complaint;
use App\Models\Dispatch;
use App\Models\Season;
use App\Models\Shipment;
use App\Models\StockBalance;
use Illuminate\Database\Query\Builder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

/**
 * Read-only aggregates for dashboards, alerts and reports.
 */
class InsightService
{
    /**
     * @param  list<int>|null  $warehouseIds
     */
    public function stockByProduct(?array $warehouseIds = null): Collection
    {
        return DB::table('stock_balances')
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_balances.stock_batch_id')
            ->join('products', 'products.id', '=', 'stock_batches.product_id')
            ->when($warehouseIds, fn (Builder $q) => $q->whereIn('stock_balances.warehouse_id', $warehouseIds))
            ->groupBy('products.id', 'products.code', 'products.name', 'products.color', 'products.package_kg')
            ->orderBy('products.id')
            ->selectRaw('products.id, products.code, products.name, products.color, SUM(stock_balances.bags) as bags, SUM(stock_balances.bags * products.package_kg) / 1000.0 as tonnes')
            ->get();
    }

    public function stockByWarehouse(): Collection
    {
        return DB::table('warehouses')
            ->leftJoin('stock_balances', 'stock_balances.warehouse_id', '=', 'warehouses.id')
            ->leftJoin('stock_batches', 'stock_batches.id', '=', 'stock_balances.stock_batch_id')
            ->leftJoin('products', 'products.id', '=', 'stock_batches.product_id')
            ->where('warehouses.is_active', true)
            ->where('warehouses.type', '!=', 'agro_dealer')
            ->groupBy('warehouses.id', 'warehouses.name', 'warehouses.code', 'warehouses.type', 'warehouses.capacity_tonnes', 'warehouses.lat', 'warehouses.lng')
            ->selectRaw('warehouses.id, warehouses.name, warehouses.code, warehouses.type, warehouses.capacity_tonnes, warehouses.lat, warehouses.lng, COALESCE(SUM(stock_balances.bags * products.package_kg), 0) / 1000.0 as tonnes, COALESCE(SUM(stock_balances.bags), 0) as bags')
            ->orderByDesc('tonnes')
            ->get();
    }

    /**
     * @return array{bags: int, tonnes: float, amount: float, subsidy: float, farmers: int, sales: int}
     */
    public function salesTotals(Season $season, ?array $warehouseIds = null): array
    {
        $base = DB::table('sales')->where('season_id', $season->id)
            ->when($warehouseIds, fn (Builder $q) => $q->whereIn('warehouse_id', $warehouseIds));

        $tonnes = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->where('sales.season_id', $season->id)
            ->when($warehouseIds, fn (Builder $q) => $q->whereIn('sales.warehouse_id', $warehouseIds))
            ->selectRaw('COALESCE(SUM(sale_items.bags * products.package_kg), 0) / 1000.0 as tonnes')
            ->value('tonnes');

        return [
            'bags' => (int) (clone $base)->sum('total_bags'),
            'tonnes' => round((float) $tonnes, 1),
            'amount' => (float) (clone $base)->sum('total_amount'),
            'subsidy' => (float) (clone $base)->sum('subsidy_amount'),
            'farmers' => (int) (clone $base)->whereNotNull('farmer_id')->distinct()->count('farmer_id'),
            'sales' => (int) (clone $base)->count(),
        ];
    }

    /**
     * Monthly sales (bags & amount) for the season.
     */
    public function salesByMonth(Season $season, ?array $warehouseIds = null): Collection
    {
        $month = $this->monthExpression('sold_at');

        return DB::table('sales')
            ->where('season_id', $season->id)
            ->when($warehouseIds, fn (Builder $q) => $q->whereIn('warehouse_id', $warehouseIds))
            ->selectRaw("{$month} as month, SUM(total_bags) as bags, SUM(total_amount) as amount")
            ->groupByRaw($month)
            ->orderBy('month')
            ->get();
    }

    public function salesByRegion(Season $season): Collection
    {
        return DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'sales.warehouse_id')
            ->join('regions', 'regions.id', '=', 'warehouses.region_id')
            ->where('sales.season_id', $season->id)
            ->groupBy('regions.id', 'regions.name')
            ->selectRaw('regions.id, regions.name, SUM(sale_items.bags) as bags, SUM(sale_items.bags * products.package_kg) / 1000.0 as tonnes, SUM(sale_items.line_total) as amount, COUNT(DISTINCT sales.farmer_id) as farmers')
            ->orderByDesc('tonnes')
            ->get();
    }

    /**
     * Target vs sold, per region (tonnes).
     */
    public function targetProgress(Season $season): Collection
    {
        $sold = $this->salesByRegion($season)->keyBy('id');

        return DB::table('distribution_targets')
            ->join('regions', 'regions.id', '=', 'distribution_targets.region_id')
            ->where('distribution_targets.season_id', $season->id)
            ->groupBy('regions.id', 'regions.name')
            ->selectRaw('regions.id, regions.name, SUM(distribution_targets.target_tonnes) as target')
            ->orderBy('regions.name')
            ->get()
            ->map(function ($row) use ($sold) {
                $row->sold = round((float) ($sold[$row->id]->tonnes ?? 0), 1);
                $row->target = (float) $row->target;
                $row->percent = $row->target > 0 ? (int) round($row->sold / $row->target * 100) : 0;

                return $row;
            });
    }

    /**
     * @return array{in_transit: int, delivered: int, on_time_percent: int, lost_bags: int, late: int, tonnes_moved: float}
     */
    public function logisticsStats(Season $season): array
    {
        $delivered = Dispatch::with('items.batch.product')
            ->where('season_id', $season->id)
            ->where('status', DispatchStatus::Delivered)
            ->get();

        $onTime = $delivered->filter(fn (Dispatch $d) => ! $d->isLate())->count();
        $inTransit = Dispatch::where('status', DispatchStatus::InTransit)->get();

        return [
            'in_transit' => $inTransit->count(),
            'late' => $inTransit->filter(fn (Dispatch $d) => $d->isLate())->count(),
            'delivered' => $delivered->count(),
            'on_time_percent' => $delivered->count() > 0 ? (int) round($onTime / $delivered->count() * 100) : 100,
            'lost_bags' => $delivered->sum(fn (Dispatch $d) => $d->lostBags()),
            'tonnes_moved' => round($delivered->sum(fn (Dispatch $d) => $d->totalTonnes()), 1),
        ];
    }

    public function transporterPerformance(Season $season): Collection
    {
        return Dispatch::with(['transporter', 'items.batch.product'])
            ->where('season_id', $season->id)
            ->where('status', DispatchStatus::Delivered)
            ->whereNotNull('transporter_id')
            ->get()
            ->groupBy('transporter_id')
            ->map(function (Collection $trips) {
                $onTime = $trips->filter(fn (Dispatch $d) => ! $d->isLate())->count();
                $sent = $trips->sum(fn (Dispatch $d) => $d->totalBags());
                $lost = $trips->sum(fn (Dispatch $d) => $d->lostBags());

                return (object) [
                    'transporter' => $trips->first()->transporter,
                    'trips' => $trips->count(),
                    'tonnes' => round($trips->sum(fn (Dispatch $d) => $d->totalTonnes()), 1),
                    'on_time_percent' => (int) round($onTime / $trips->count() * 100),
                    'lost_bags' => $lost,
                    'loss_rate' => $sent > 0 ? round($lost / $sent * 100, 2) : 0,
                    'cost' => (float) $trips->sum('transport_cost'),
                ];
            })
            ->sortByDesc('tonnes')
            ->values();
    }

    /**
     * Things someone should act on now.
     *
     * @return list<array{level: string, title: string, detail: string, url: string|null}>
     */
    public function alerts(): array
    {
        $alerts = [];

        $lowStock = DB::table('stock_balances')
            ->join('stock_batches', 'stock_batches.id', '=', 'stock_balances.stock_batch_id')
            ->join('products', 'products.id', '=', 'stock_batches.product_id')
            ->join('warehouses', 'warehouses.id', '=', 'stock_balances.warehouse_id')
            ->whereIn('warehouses.type', ['zonal', 'sale_point'])
            ->where('warehouses.is_active', true)
            ->groupBy('warehouses.id', 'warehouses.name', 'products.id', 'products.code', 'products.low_stock_bags')
            ->havingRaw('SUM(stock_balances.bags) < products.low_stock_bags')
            ->selectRaw('warehouses.id as warehouse_id, warehouses.name as warehouse, products.code, SUM(stock_balances.bags) as bags')
            ->limit(6)
            ->get();
        foreach ($lowStock as $row) {
            $alerts[] = ['level' => 'warning', 'title' => __('Low stock: :product', ['product' => $row->code]), 'detail' => "{$row->warehouse} — ".number_format($row->bags).' '.__('bags left'), 'url' => route('warehouses.show', $row->warehouse_id)];
        }

        $expiring = StockBalance::with(['batch.product', 'warehouse'])
            ->where('bags', '>', 0)
            ->whereHas('batch', fn ($q) => $q->whereNotNull('expires_on')->where('expires_on', '<=', now()->addDays(config('kilimo.expiry_warning_days'))))
            ->limit(5)
            ->get();
        foreach ($expiring as $balance) {
            $alerts[] = ['level' => 'danger', 'title' => __('Expiring: :product batch :batch', ['product' => $balance->batch->product->code, 'batch' => $balance->batch->batch_number]), 'detail' => "{$balance->warehouse->name} — ".number_format($balance->bags).' '.__('bags, expires').' '.$balance->batch->expires_on->format('d M Y'), 'url' => route('warehouses.show', $balance->warehouse_id)];
        }

        foreach (Dispatch::with(['fromWarehouse', 'toWarehouse', 'vehicle'])->where('status', DispatchStatus::InTransit)->get() as $dispatch) {
            if ($dispatch->isLate()) {
                $alerts[] = ['level' => 'danger', 'title' => __('Truck overdue: :number', ['number' => $dispatch->number]), 'detail' => "{$dispatch->vehicle?->plate_number} {$dispatch->fromWarehouse->name} → {$dispatch->toWarehouse->name}", 'url' => route('dispatches.show', $dispatch)];
            } elseif ($dispatch->isSilent(config('kilimo.silent_truck_hours'))) {
                $alerts[] = ['level' => 'warning', 'title' => __('No GPS update: :number', ['number' => $dispatch->number]), 'detail' => __('Last seen :time', ['time' => ($dispatch->last_ping_at ?? $dispatch->dispatched_at)->diffForHumans()]), 'url' => route('dispatches.show', $dispatch)];
            }
        }

        foreach (Shipment::whereNotIn('status', [ShipmentStatus::Completed, ShipmentStatus::Cancelled])->get() as $shipment) {
            if ($shipment->isDelayed()) {
                $alerts[] = ['level' => 'warning', 'title' => __('Vessel delayed: :vessel', ['vessel' => $shipment->vessel_name ?? $shipment->reference]), 'detail' => __('ETA was :date', ['date' => $shipment->eta->format('d M Y')]), 'url' => route('shipments.show', $shipment)];
            }
        }

        $oldComplaints = Complaint::where('status', ComplaintStatus::Open)->where('created_at', '<', now()->subDays(3))->count();
        if ($oldComplaints > 0) {
            $alerts[] = ['level' => 'warning', 'title' => __(':count complaints unanswered > 3 days', ['count' => $oldComplaints]), 'detail' => __('Farmers are waiting for a response'), 'url' => route('complaints.index', ['status' => 'open'])];
        }

        $pendingDealers = AgroDealer::where('status', DealerStatus::Pending)->count();
        if ($pendingDealers > 0) {
            $alerts[] = ['level' => 'info', 'title' => __(':count agro-dealer applications to review', ['count' => $pendingDealers]), 'detail' => __('New applications from the public portal'), 'url' => route('dealers.index', ['status' => 'pending'])];
        }

        return $alerts;
    }

    private function monthExpression(string $column): string
    {
        return match (DB::connection()->getDriverName()) {
            'sqlite' => "strftime('%Y-%m', {$column})",
            'pgsql' => "to_char({$column}, 'YYYY-MM')",
            default => "DATE_FORMAT({$column}, '%Y-%m')",
        };
    }
}
