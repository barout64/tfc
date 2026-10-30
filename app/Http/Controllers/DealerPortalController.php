<?php

namespace App\Http\Controllers;

use App\Enums\PaymentMethod;
use App\Exceptions\BusinessRuleException;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\Voucher;
use App\Services\SalesService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Agro-dealers see their own shop stock and sell to farmers (consuming farmer allowance / vouchers).
 */
class DealerPortalController extends Controller
{
    public function index(Request $request): View
    {
        $dealer = $request->user()->agroDealer()->with('outlet')->firstOrFail();
        $outlet = $dealer->outlet;

        return view('dealer-portal.index', [
            'dealer' => $dealer,
            'stock' => $outlet ? StockBalance::with('batch.product')->where('warehouse_id', $outlet->id)->where('bags', '>', 0)->get() : collect(),
            'sales' => $outlet ? Sale::with(['farmer', 'items.product'])->where('warehouse_id', $outlet->id)->latest('sold_at')->take(20)->get() : collect(),
            'purchases' => $dealer->sales()->with('warehouse')->latest('sold_at')->take(10)->get(),
            'farmersServed' => $outlet ? Sale::where('warehouse_id', $outlet->id)->distinct()->count('farmer_id') : 0,
        ]);
    }

    public function sellForm(Request $request): View
    {
        return view('dealer-portal.sell', [
            'products' => Product::active()->get()->map(fn ($p) => ['id' => $p->id, 'code' => $p->code, 'name' => $p->name, 'price' => (float) ($p->priceFor()?->subsidy_price ?? 0)]),
            'methods' => collect(PaymentMethod::options())->except(['credit', 'gepg']),
        ]);
    }

    public function sell(Request $request, SalesService $sales): RedirectResponse
    {
        $dealer = $request->user()->agroDealer()->with('outlet')->firstOrFail();
        abort_unless($dealer->outlet, 403, __('Your outlet is not set up yet.'));

        $data = $request->validate([
            'farmer' => ['required', 'string'],
            'voucher_code' => ['nullable', 'string', 'max:20'],
            'payment_method' => ['required', Rule::enum(PaymentMethod::class)->except([PaymentMethod::Credit, PaymentMethod::Gepg])],
            'lines' => ['required', 'array', 'min:1'],
            'lines.*.product_id' => ['required', 'exists:products,id'],
            'lines.*.bags' => ['required', 'integer', 'min:0', 'max:1000'],
        ]);

        $farmer = Farmer::where('farmer_number', strtoupper(trim($data['farmer'])))
            ->orWhere('phone', Farmer::normalisePhone($data['farmer']))
            ->first() ?? throw new BusinessRuleException(__('Farmer not found. Ask them to register first (USSD :code).', ['code' => config('kilimo.ussd_code')]));

        $voucher = null;
        if (! empty($data['voucher_code'])) {
            $voucher = Voucher::where('code', strtoupper($data['voucher_code']))->where('farmer_id', $farmer->id)->first();
            if (! $voucher?->isUsable()) {
                throw new BusinessRuleException(__('Voucher is not valid for this farmer.'));
            }
        }

        $sale = $sales->sell(
            $dealer->outlet,
            'farmer',
            $farmer,
            array_values(array_filter($data['lines'], fn ($l) => (int) $l['bags'] > 0)),
            PaymentMethod::from($data['payment_method']),
            $request->user(),
            null,
            $voucher,
        );

        return redirect()->route('dealer.home')->with('success', __('Sale :number recorded for :farmer.', ['number' => $sale->number, 'farmer' => $farmer->fullName()]));
    }
}
