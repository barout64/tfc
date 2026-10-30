<?php

namespace App\Http\Controllers;

use App\Models\Complaint;
use App\Models\Dispatch;
use App\Models\Farmer;
use App\Models\Sale;
use App\Models\Season;
use App\Models\StockBalance;
use App\Services\InsightService;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ReportController extends Controller
{
    /**
     * @var array<string, array{title: string, description: string, icon: string, dated: bool}>
     */
    private const REPORTS = [
        'stock-position' => ['title' => 'Stock position', 'description' => 'Bags and tonnes per warehouse, product and batch, with expiry.', 'icon' => 'warehouse', 'dated' => false],
        'sales-register' => ['title' => 'Sales register', 'description' => 'Every sale with customer, quantity, amount and payment status.', 'icon' => 'cart', 'dated' => true],
        'sales-by-region' => ['title' => 'Sales by region', 'description' => 'Tonnes, value and farmers served per region this season.', 'icon' => 'map', 'dated' => false],
        'target-vs-actual' => ['title' => 'Target vs actual', 'description' => 'Season distribution targets against tonnes sold, by region.', 'icon' => 'target', 'dated' => false],
        'subsidy' => ['title' => 'Subsidy utilisation', 'description' => 'Farmer payments and government subsidy per product.', 'icon' => 'coins', 'dated' => true],
        'dispatches' => ['title' => 'Dispatch & delivery', 'description' => 'Trips, transit time, on-time delivery and losses.', 'icon' => 'truck', 'dated' => true],
        'transporters' => ['title' => 'Transporter performance', 'description' => 'Trips, tonnes, on-time rate, losses and cost per transporter.', 'icon' => 'star', 'dated' => false],
        'farmers' => ['title' => 'Farmers reached', 'description' => 'Registered vs served farmers per region, with gender split.', 'icon' => 'users', 'dated' => false],
        'vouchers' => ['title' => 'Voucher redemption', 'description' => 'Issued, redeemed and expired e-vouchers per product.', 'icon' => 'ticket', 'dated' => false],
        'complaints' => ['title' => 'Complaints', 'description' => 'Complaints by category and status, with resolution time.', 'icon' => 'chat', 'dated' => true],
    ];

    public function index(): View
    {
        return view('reports.index', ['reports' => self::REPORTS]);
    }

    public function show(Request $request, string $report, InsightService $insights): View|StreamedResponse
    {
        abort_unless(isset(self::REPORTS[$report]), 404);

        $from = $request->date('from') ?? Season::current()->starts_on;
        $to = $request->date('to') ?? now();
        [$columns, $rows] = $this->{str($report)->camel()->toString()}($insights, $from, $to->copy()->endOfDay());

        if ($request->query('export') === 'csv') {
            return $this->csv($report, $columns, $rows);
        }

        return view('reports.show', [
            'key' => $report,
            'report' => self::REPORTS[$report],
            'columns' => $columns,
            'rows' => $rows,
            'from' => $from,
            'to' => $to,
        ]);
    }

    private function stockPosition(): array
    {
        $rows = StockBalance::with(['warehouse.region', 'batch.product'])
            ->where('bags', '>', 0)
            ->when($this->warehouseScope(), fn ($q, $ids) => $q->whereIn('warehouse_id', $ids))
            ->get()
            ->sortBy(fn ($b) => [$b->warehouse->name, $b->batch->product->code])
            ->map(fn ($b) => [
                $b->warehouse->name,
                $b->warehouse->region->name,
                $b->batch->product->code,
                $b->batch->batch_number,
                $b->batch->expires_on?->format('Y-m-d'),
                $b->bags,
                $b->batch->product->bagsToTonnes($b->bags),
            ]);

        return [['Warehouse', 'Region', 'Product', 'Batch', 'Expires', 'Bags', 'Tonnes'], $rows->values()];
    }

    private function salesRegister(InsightService $insights, Carbon $from, Carbon $to): array
    {
        $rows = Sale::with(['warehouse', 'farmer', 'agroDealer', 'cooperative'])
            ->whereBetween('sold_at', [$from, $to])
            ->when($this->warehouseScope(), fn ($q, $ids) => $q->whereIn('warehouse_id', $ids))
            ->latest('sold_at')
            ->get()
            ->map(fn (Sale $s) => [
                $s->number,
                $s->sold_at->format('Y-m-d H:i'),
                $s->warehouse->name,
                str($s->customer_type)->replace('_', ' ')->title(),
                $s->customerName(),
                $s->total_bags,
                (float) $s->total_amount,
                (float) $s->subsidy_amount,
                $s->payment_method->label(),
                ucfirst($s->payment_status),
            ]);

        return [['Sale', 'Date', 'Point of sale', 'Customer type', 'Customer', 'Bags', 'Amount (TZS)', 'Subsidy (TZS)', 'Method', 'Payment'], $rows];
    }

    private function salesByRegion(InsightService $insights): array
    {
        $rows = $insights->salesByRegion(Season::current())->map(fn ($r) => [$r->name, (int) $r->bags, round((float) $r->tonnes, 2), (float) $r->amount, (int) $r->farmers]);

        return [['Region', 'Bags', 'Tonnes', 'Amount (TZS)', 'Farmers served'], $rows];
    }

    private function targetVsActual(InsightService $insights): array
    {
        $rows = $insights->targetProgress(Season::current())->map(fn ($r) => [$r->name, $r->target, $r->sold, round($r->target - $r->sold, 1), $r->percent.'%']);

        return [['Region', 'Target (t)', 'Sold (t)', 'Gap (t)', 'Achieved'], $rows];
    }

    private function subsidy(InsightService $insights, Carbon $from, Carbon $to): array
    {
        $rows = DB::table('sale_items')
            ->join('sales', 'sales.id', '=', 'sale_items.sale_id')
            ->join('products', 'products.id', '=', 'sale_items.product_id')
            ->whereBetween('sales.sold_at', [$from, $to])
            ->groupBy('products.code', 'products.package_kg')
            ->selectRaw('products.code, products.package_kg, SUM(sale_items.bags) as bags, SUM(sale_items.line_total) as paid, SUM(sale_items.bags * sale_items.subsidy_per_bag) as subsidy')
            ->get()
            ->map(fn ($r) => [$r->code, (int) $r->bags, round($r->bags * $r->package_kg / 1000, 2), (float) $r->paid, (float) $r->subsidy, (float) $r->paid + (float) $r->subsidy]);

        return [['Product', 'Bags', 'Tonnes', 'Paid by buyers (TZS)', 'Government subsidy (TZS)', 'Full value (TZS)'], $rows];
    }

    private function dispatches(InsightService $insights, Carbon $from, Carbon $to): array
    {
        $rows = Dispatch::with(['fromWarehouse', 'toWarehouse', 'transporter', 'vehicle', 'items.batch.product'])
            ->whereBetween('created_at', [$from, $to])
            ->latest()
            ->get()
            ->map(fn (Dispatch $d) => [
                $d->number,
                $d->fromWarehouse->name,
                $d->toWarehouse->name,
                $d->transporter?->name,
                $d->vehicle?->plate_number,
                $d->totalTonnes(),
                $d->status->label(),
                $d->dispatched_at?->format('Y-m-d H:i'),
                $d->delivered_at?->format('Y-m-d H:i'),
                $d->dispatched_at && $d->delivered_at ? round($d->dispatched_at->diffInHours($d->delivered_at), 1) : null,
                $d->isLate() ? 'Late' : 'On time',
                $d->lostBags(),
            ]);

        return [['Dispatch', 'From', 'To', 'Transporter', 'Vehicle', 'Tonnes', 'Status', 'Departed', 'Delivered', 'Transit (h)', 'Timeliness', 'Lost bags'], $rows];
    }

    private function transporters(InsightService $insights): array
    {
        $rows = $insights->transporterPerformance(Season::current())->map(fn ($r) => [
            $r->transporter->name, $r->trips, $r->tonnes, $r->on_time_percent.'%', $r->lost_bags, $r->loss_rate.'%', $r->cost,
        ]);

        return [['Transporter', 'Trips', 'Tonnes', 'On time', 'Lost bags', 'Loss rate', 'Cost (TZS)'], $rows];
    }

    private function farmers(): array
    {
        $season = Season::current();
        $served = DB::table('sales')->join('farmers', 'farmers.id', '=', 'sales.farmer_id')
            ->where('sales.season_id', $season->id)
            ->groupBy('farmers.region_id')
            ->selectRaw('farmers.region_id, COUNT(DISTINCT sales.farmer_id) as served, SUM(sales.total_bags) as bags')
            ->get()->keyBy('region_id');

        $rows = Farmer::query()
            ->join('regions', 'regions.id', '=', 'farmers.region_id')
            ->groupBy('regions.id', 'regions.name')
            ->selectRaw("regions.id, regions.name, COUNT(*) as registered, SUM(CASE WHEN gender = 'female' THEN 1 ELSE 0 END) as female")
            ->orderBy('regions.name')
            ->get()
            ->map(fn ($r) => [
                $r->name,
                (int) $r->registered,
                (int) $r->female,
                $r->registered ? round($r->female / $r->registered * 100).'%' : '0%',
                (int) ($served[$r->id]->served ?? 0),
                $r->registered ? round(($served[$r->id]->served ?? 0) / $r->registered * 100).'%' : '0%',
                (int) ($served[$r->id]->bags ?? 0),
            ]);

        return [['Region', 'Registered', 'Female', 'Female %', 'Served this season', 'Reach %', 'Bags bought'], $rows];
    }

    private function vouchers(): array
    {
        $rows = DB::table('vouchers')
            ->join('products', 'products.id', '=', 'vouchers.product_id')
            ->groupBy('products.code')
            ->selectRaw("products.code, COUNT(*) as issued, SUM(CASE WHEN status = 'redeemed' THEN 1 ELSE 0 END) as redeemed, SUM(CASE WHEN status = 'expired' THEN 1 ELSE 0 END) as expired, SUM(bags) as bags, SUM(CASE WHEN status = 'redeemed' THEN bags ELSE 0 END) as redeemed_bags")
            ->get()
            ->map(fn ($r) => [$r->code, (int) $r->issued, (int) $r->redeemed, (int) $r->expired, $r->issued ? round($r->redeemed / $r->issued * 100).'%' : '0%', (int) $r->bags, (int) $r->redeemed_bags]);

        return [['Product', 'Issued', 'Redeemed', 'Expired', 'Redemption rate', 'Bags issued', 'Bags redeemed'], $rows];
    }

    private function complaints(InsightService $insights, Carbon $from, Carbon $to): array
    {
        $rows = Complaint::whereBetween('created_at', [$from, $to])->get()
            ->groupBy(fn ($c) => $c->category->label())
            ->map(fn ($group, $category) => [
                $category,
                $group->count(),
                $group->where('status.value', 'open')->count(),
                $group->where('status.value', 'in_progress')->count(),
                $group->whereIn('status.value', ['resolved', 'closed'])->count(),
                round((float) $group->whereNotNull('resolved_at')->avg(fn ($c) => $c->created_at->diffInHours($c->resolved_at) / 24), 1),
            ])
            ->values();

        return [['Category', 'Total', 'Open', 'In progress', 'Resolved', 'Avg days to resolve'], $rows];
    }

    /**
     * @param  list<string>  $columns
     */
    private function csv(string $report, array $columns, $rows): StreamedResponse
    {
        return response()->streamDownload(function () use ($columns, $rows) {
            $out = fopen('php://output', 'w');
            fwrite($out, "\xEF\xBB\xBF"); // Excel-friendly UTF-8
            fputcsv($out, $columns);
            foreach ($rows as $row) {
                fputcsv($out, array_values((array) $row));
            }
            fclose($out);
        }, "tfc-{$report}-".now()->format('Ymd-His').'.csv', ['Content-Type' => 'text/csv']);
    }
}
