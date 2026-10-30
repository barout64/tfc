<?php

namespace App\Http\Controllers;

use App\Enums\DealerStatus;
use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\AgroDealer;
use App\Models\Cooperative;
use App\Models\Farmer;
use App\Models\Payment;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\Voucher;
use App\Models\Warehouse;
use App\Services\FarmerService;
use App\Services\PaymentService;
use App\Services\SalesService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SaleController extends Controller
{
    public function index(Request $request): View
    {
        $scope = $this->warehouseScope();

        $query = Sale::with(['warehouse', 'farmer', 'agroDealer', 'cooperative'])
            ->when($scope, fn ($q) => $q->whereIn('warehouse_id', $scope))
            ->when($request->integer('warehouse'), fn ($q, $id) => $q->where('warehouse_id', $id))
            ->when($request->query('customer_type'), fn ($q, $type) => $q->where('customer_type', $type))
            ->when($request->query('payment_status'), fn ($q, $status) => $q->where('payment_status', $status))
            ->when($request->query('from'), fn ($q, $from) => $q->whereDate('sold_at', '>=', $from))
            ->when($request->query('to'), fn ($q, $to) => $q->whereDate('sold_at', '<=', $to))
            ->when($request->query('q'), fn ($q, $term) => $q->where('number', 'like', "%{$term}%"));

        return view('sales.index', [
            'sales' => (clone $query)->latest('sold_at')->paginate(25)->withQueryString(),
            'totals' => (clone $query)->selectRaw('COUNT(*) as count, COALESCE(SUM(total_bags),0) as bags, COALESCE(SUM(total_amount),0) as amount, COALESCE(SUM(subsidy_amount),0) as subsidy')->first(),
            'warehouses' => $this->scopedWarehouses(),
        ]);
    }

    public function create(): View
    {
        $warehouses = $this->scopedWarehouses()->whereIn('type.value', ['zonal', 'sale_point', 'hq']);

        return view('sales.create', [
            'warehouses' => $warehouses->isEmpty() ? $this->scopedWarehouses() : $warehouses,
            'products' => Product::active()->get()->map(fn (Product $p) => [
                'id' => $p->id,
                'code' => $p->code,
                'name' => $p->name,
                'price' => (float) ($p->priceFor()?->subsidy_price ?? 0),
            ]),
            'dealers' => AgroDealer::where('status', DealerStatus::Approved)->orderBy('business_name')->get(['id', 'business_name', 'dealer_number']),
            'cooperatives' => Cooperative::where('is_active', true)->orderBy('name')->get(['id', 'name']),
            'methods' => PaymentMethod::options(),
        ]);
    }

    /**
     * POS lookup: find a farmer (phone, NIDA, number), voucher or reservation code.
     */
    public function lookup(Request $request, FarmerService $farmers): JsonResponse
    {
        $term = trim((string) $request->query('q'));
        if (strlen($term) < 4) {
            return response()->json(['found' => false]);
        }

        $voucher = Voucher::with(['farmer', 'product'])->where('code', strtoupper($term))->first();
        $reservation = $voucher ? null : Reservation::with(['farmer', 'product'])->where('code', strtoupper($term))->where('status', 'pending')->first();

        $farmer = $voucher?->farmer ?? $reservation?->farmer ?? Farmer::with('entitlements.product')
            ->where('farmer_number', strtoupper($term))
            ->orWhere('nida_number', $term)
            ->orWhere('phone', Farmer::normalisePhone($term))
            ->first();

        if (! $farmer) {
            return response()->json(['found' => false]);
        }

        return response()->json([
            'found' => true,
            'farmer' => [
                'id' => $farmer->id,
                'name' => $farmer->fullName(),
                'number' => $farmer->farmer_number,
                'phone' => $farmer->phone,
                'status' => $farmer->status,
                'acres' => (float) $farmer->farm_size_acres,
            ],
            'allowance' => $farmer->entitlements()->with('product')->whereHas('season', fn ($q) => $q->where('is_active', true))->get()
                ->map(fn ($e) => ['product_id' => $e->product_id, 'code' => $e->product->code, 'remaining' => $farmers->remainingBags($farmer, $e->product), 'allowed' => $e->bags_allowed]),
            'voucher' => $voucher ? ['code' => $voucher->code, 'product_id' => $voucher->product_id, 'bags' => $voucher->bags, 'usable' => $voucher->isUsable(), 'status' => $voucher->status->label()] : null,
            'reservation' => $reservation ? ['code' => $reservation->code, 'product_id' => $reservation->product_id, 'bags' => $reservation->bags, 'warehouse_id' => $reservation->warehouse_id] : null,
        ]);
    }

    public function store(Request $request, SalesService $sales): RedirectResponse
    {
        $data = $request->validate([
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'customer_type' => ['required', Rule::in(['farmer', 'agro_dealer', 'cooperative'])],
            'farmer_id' => ['required_if:customer_type,farmer', 'nullable', 'exists:farmers,id'],
            'agro_dealer_id' => ['required_if:customer_type,agro_dealer', 'nullable', 'exists:agro_dealers,id'],
            'cooperative_id' => ['required_if:customer_type,cooperative', 'nullable', 'exists:cooperatives,id'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)],
            'payer_phone' => ['nullable', 'string', 'max:20'],
            'voucher_code' => ['nullable', 'string', 'max:20'],
            'reservation_code' => ['nullable', 'string', 'max:20'],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.bags' => ['required', 'integer', 'min:0', 'max:100000'],
        ]);

        $warehouse = Warehouse::findOrFail($data['warehouse_id']);
        $this->authorizeWarehouse($warehouse);

        $customer = match ($data['customer_type']) {
            'farmer' => Farmer::findOrFail($data['farmer_id']),
            'agro_dealer' => AgroDealer::with('outlet')->findOrFail($data['agro_dealer_id']),
            'cooperative' => Cooperative::findOrFail($data['cooperative_id']),
        };

        [$voucher, $reservation] = $this->resolveCodes($data, $customer, $warehouse);

        $sale = $sales->sell(
            $warehouse,
            $data['customer_type'],
            $customer,
            array_values(array_filter($data['lines'], fn ($line) => (int) $line['bags'] > 0)),
            PaymentMethod::from($data['payment_method']),
            $request->user(),
            $data['payer_phone'] ?? null,
            $voucher,
            $reservation,
        );

        return redirect()->route('sales.show', $sale)->with('success', __('Sale :number recorded.', ['number' => $sale->number]));
    }

    public function show(Sale $sale): View
    {
        $this->authorizeWarehouse($sale->warehouse);

        return view('sales.show', ['sale' => $sale->load(['warehouse.region', 'farmer', 'agroDealer', 'cooperative', 'items.product', 'items.batch', 'payments', 'seller'])]);
    }

    public function confirmPayment(Payment $payment, PaymentService $payments): RedirectResponse
    {
        abort_unless($payment->status === 'pending', 422);
        $payments->confirm($payment, ['confirmed_manually_by' => auth()->id()]);

        return back()->with('success', __('Payment confirmed.'));
    }

    /**
     * @param  array<string, mixed>  $data
     * @return array{0: ?Voucher, 1: ?Reservation}
     */
    private function resolveCodes(array $data, Farmer|AgroDealer|Cooperative $customer, Warehouse $warehouse): array
    {
        $voucher = null;
        $reservation = null;

        if (! empty($data['voucher_code'])) {
            $voucher = Voucher::where('code', strtoupper($data['voucher_code']))->first();
            if (! $voucher || ! $voucher->isUsable() || ! ($customer instanceof Farmer) || $voucher->farmer_id !== $customer->id) {
                throw new BusinessRuleException(__('Voucher is not valid for this farmer.'));
            }
        }

        if (! empty($data['reservation_code'])) {
            $reservation = Reservation::where('code', strtoupper($data['reservation_code']))->where('status', 'pending')->first();
            if (! $reservation || ! ($customer instanceof Farmer) || $reservation->farmer_id !== $customer->id || $reservation->warehouse_id !== $warehouse->id) {
                throw new BusinessRuleException(__('Reservation is not valid for this farmer at this sale point.'));
            }
        }

        return [$voucher, $reservation];
    }
}
