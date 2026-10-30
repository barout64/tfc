<?php

namespace App\Http\Controllers;

use App\Enums\DispatchStatus;
use App\Models\Complaint;
use App\Models\Dispatch;
use App\Models\DistributionTarget;
use App\Models\Farmer;
use App\Models\Sale;
use App\Models\Season;
use App\Models\Transporter;
use App\Models\Warehouse;
use App\Services\InsightService;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function __invoke(InsightService $insights): View
    {
        $season = Season::current();
        $scope = $this->warehouseScope();
        $stock = $insights->stockByProduct($scope);
        $target = (float) DistributionTarget::where('season_id', $season->id)->sum('target_tonnes');
        $sales = $insights->salesTotals($season, $scope);

        return view('dashboard', [
            'season' => $season,
            'stockReport' => $stockReport = \App\Models\StockReport::latestReport()?->load(['lines', 'bulkSales.product']),
            'stockReportTotals' => $stockReport?->totals(),
            'stock' => $stock,
            'stockTonnes' => round($stock->sum('tonnes'), 1),
            'sales' => $sales,
            'target' => $target,
            'targetPercent' => $target > 0 ? min(100, (int) round($sales['tonnes'] / $target * 100)) : 0,
            'logistics' => $insights->logisticsStats($season),
            'salesByMonth' => $insights->salesByMonth($season, $scope),
            'targetProgress' => $insights->targetProgress($season)->sortByDesc('target')->take(10)->values(),
            'alerts' => $insights->alerts(),
            'counts' => [
                'warehouses' => Warehouse::active()->whereIn('type', ['port', 'hq', 'zonal', 'sale_point'])->count(),
                'farmers' => Farmer::count(),
                'transporters' => Transporter::where('status', 'active')->count(),
                'complaints' => Complaint::where('status', 'open')->count(),
            ],
            'activeTrips' => Dispatch::with(['fromWarehouse', 'toWarehouse', 'vehicle', 'items.batch.product'])
                ->where('status', DispatchStatus::InTransit)
                ->orderBy('expected_arrival_at')
                ->take(6)
                ->get(),
            'recentSales' => Sale::with(['farmer', 'agroDealer', 'cooperative', 'warehouse'])
                ->when($scope, fn ($q) => $q->whereIn('warehouse_id', $scope))
                ->latest('sold_at')
                ->take(6)
                ->get(),
        ]);
    }
}
