<?php

namespace App\Http\Controllers;

use App\Enums\ComplaintCategory;
use App\Enums\DealerStatus;
use App\Models\Advisory;
use App\Models\AgroDealer;
use App\Models\Complaint;
use App\Models\Crop;
use App\Models\District;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\Region;
use App\Models\Season;
use App\Models\StockBalance;
use App\Models\Warehouse;
use App\Services\FarmerService;
use App\Services\InsightService;
use App\Services\NumberGenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class PublicController extends Controller
{
    public function home(InsightService $insights): View
    {
        $season = Season::current();

        return view('public.home', [
            'season' => $season,
            'sales' => $insights->salesTotals($season),
            'farmers' => Farmer::count(),
            'salePoints' => Warehouse::active()->sellsToFarmers()->where('is_public', true)->count(),
            'prices' => $this->priceList(),
            'advisories' => Advisory::published()->latest('published_at')->take(3)->get(),
        ]);
    }

    public function salePoints(Request $request): View
    {
        $regionId = $request->integer('region') ?: null;
        $productId = $request->integer('product') ?: null;

        $points = Warehouse::with(['region', 'district', 'agroDealer'])
            ->active()
            ->sellsToFarmers()
            ->where('is_public', true)
            ->when($regionId, fn ($q) => $q->where('region_id', $regionId))
            ->orderBy('name')
            ->get()
            ->map(function (Warehouse $warehouse) use ($productId) {
                $warehouse->setAttribute('stock', StockBalance::query()
                    ->join('stock_batches', 'stock_batches.id', '=', 'stock_balances.stock_batch_id')
                    ->join('products', 'products.id', '=', 'stock_batches.product_id')
                    ->where('stock_balances.warehouse_id', $warehouse->id)
                    ->when($productId, fn ($q) => $q->where('products.id', $productId))
                    ->groupBy('products.code', 'products.color')
                    ->selectRaw('products.code, products.color, SUM(stock_balances.bags - stock_balances.reserved_bags) as bags')
                    ->havingRaw('SUM(stock_balances.bags - stock_balances.reserved_bags) > 0')
                    ->get());

                return $warehouse;
            })
            ->when($productId, fn ($c) => $c->filter(fn ($w) => $w->stock->isNotEmpty()))
            ->sortByDesc(fn ($w) => $w->stock->sum('bags'))
            ->values();

        return view('public.sale-points', [
            'points' => $points,
            'regions' => Region::orderBy('name')->get(),
            'products' => Product::active()->get(),
            'regionId' => $regionId,
            'productId' => $productId,
        ]);
    }

    public function prices(): View
    {
        return view('public.prices', ['prices' => $this->priceList(), 'crops' => Crop::with('recommendations.product')->get()]);
    }

    public function advice(): View
    {
        return view('public.advice', ['advisories' => Advisory::with(['crop', 'region'])->published()->latest('published_at')->paginate(9)]);
    }

    public function registerForm(): View
    {
        return view('public.register', $this->locationOptions());
    }

    public function register(Request $request, FarmerService $farmers): RedirectResponse
    {
        $data = $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'phone' => ['required', 'string', 'regex:/^(\+?255|0)?[67]\d{8}$/'],
            'nida_number' => ['nullable', 'string', 'max:30', 'unique:farmers,nida_number'],
            'region_id' => ['required', 'exists:regions,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'village' => ['required', 'string', 'max:100'],
            'farm_size_acres' => ['required', 'numeric', 'min:0.25', 'max:5000'],
            'main_crop_id' => ['required', 'exists:crops,id'],
        ], ['phone.regex' => __('Enter a valid Tanzanian mobile number, e.g. 0712345678.')]);

        $farmer = $farmers->register($data, 'web');

        return redirect()->route('public.allowance')->with('success', __('Registration complete! Your farmer number is :number. An SMS has been sent to you.', ['number' => $farmer->farmer_number]));
    }

    public function allowanceForm(): View
    {
        return view('public.allowance');
    }

    public function allowance(Request $request, FarmerService $farmers): View|RedirectResponse
    {
        $data = $request->validate(['phone' => ['required', 'string'], 'farmer_number' => ['required', 'string']]);

        $farmer = Farmer::with(['entitlements.product', 'vouchers.product', 'region', 'mainCrop'])
            ->where('phone', Farmer::normalisePhone($data['phone']))
            ->where('farmer_number', strtoupper(trim($data['farmer_number'])))
            ->first();

        if (! $farmer) {
            return back()->withInput()->with('error', __('No farmer found with that phone number and farmer number.'));
        }

        $season = Season::current();
        $remaining = $farmer->entitlements->where('season_id', $season->id)
            ->mapWithKeys(fn ($e) => [$e->product_id => $farmers->remainingBags($farmer, $e->product, $season)]);

        $nearby = Warehouse::active()->sellsToFarmers()->where('is_public', true)->where('region_id', $farmer->region_id)->orderBy('name')->get();

        return view('public.allowance', compact('farmer', 'season', 'remaining', 'nearby'));
    }

    public function reserve(Request $request, FarmerService $farmers): RedirectResponse
    {
        $data = $request->validate([
            'phone' => ['required', 'string'],
            'farmer_number' => ['required', 'string'],
            'warehouse_id' => ['required', 'exists:warehouses,id'],
            'product_id' => ['required', 'exists:products,id'],
            'bags' => ['required', 'integer', 'min:1', 'max:500'],
        ]);

        $farmer = Farmer::where('phone', Farmer::normalisePhone($data['phone']))->where('farmer_number', $data['farmer_number'])->firstOrFail();
        $reservation = $farmers->reserve($farmer, Warehouse::findOrFail($data['warehouse_id']), Product::findOrFail($data['product_id']), (int) $data['bags']);

        return redirect()->route('public.allowance')->with('success', __('Reserved! Your reservation code is :code. Collect within :hours hours.', ['code' => $reservation->code, 'hours' => config('kilimo.reservation_hours')]));
    }

    public function complaintForm(): View
    {
        return view('public.complaint', [
            'regions' => Region::orderBy('name')->get(),
            'categories' => ComplaintCategory::options(),
        ]);
    }

    public function complaint(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'name' => ['nullable', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'category' => ['required', Rule::enum(ComplaintCategory::class)],
            'region_id' => ['nullable', 'exists:regions,id'],
            'description' => ['required', 'string', 'min:10', 'max:2000'],
        ]);

        $phone = Farmer::normalisePhone($data['phone']);
        $complaint = Complaint::create([
            ...$data,
            'phone' => $phone,
            'farmer_id' => Farmer::where('phone', $phone)->value('id'),
            'ticket_number' => NumberGenerator::next(Complaint::class, 'ticket_number', 'TCK'),
            'channel' => 'web',
        ]);

        return back()->with('success', __('Thank you. Your complaint number is :ticket. We will contact you.', ['ticket' => $complaint->ticket_number]));
    }

    public function dealerForm(): View
    {
        return view('public.dealer', $this->locationOptions());
    }

    public function dealerApply(Request $request): RedirectResponse
    {
        $data = $request->validate([
            'business_name' => ['required', 'string', 'max:150'],
            'owner_name' => ['required', 'string', 'max:100'],
            'phone' => ['required', 'string', 'max:20'],
            'email' => ['nullable', 'email'],
            'tin' => ['required', 'string', 'max:30'],
            'tfra_license_no' => ['required', 'string', 'max:50'],
            'license_expiry' => ['required', 'date', 'after:today'],
            'region_id' => ['required', 'exists:regions,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'ward' => ['nullable', 'string', 'max:100'],
            'address' => ['required', 'string', 'max:255'],
            'lat' => ['nullable', 'numeric', 'between:-12,0'],
            'lng' => ['nullable', 'numeric', 'between:29,41'],
        ]);

        $dealer = AgroDealer::create([
            ...$data,
            'phone' => Farmer::normalisePhone($data['phone']),
            'dealer_number' => NumberGenerator::next(AgroDealer::class, 'dealer_number', 'AGD'),
            'status' => DealerStatus::Pending,
        ]);

        return back()->with('success', __('Application received. Reference: :ref. TFC will verify your TFRA licence and contact you.', ['ref' => $dealer->dealer_number]));
    }

    public function locale(Request $request, string $locale): RedirectResponse
    {
        abort_unless(in_array($locale, ['en', 'sw'], true), 404);
        $request->session()->put('locale', $locale);
        $request->user()?->update(['locale' => $locale]);

        return back();
    }

    /**
     * @return array{regions: mixed, districts: mixed, crops: mixed}
     */
    private function locationOptions(): array
    {
        return [
            'regions' => Region::orderBy('name')->get(),
            'districts' => District::orderBy('name')->get(['id', 'region_id', 'name']),
            'crops' => Crop::orderBy('name')->get(),
        ];
    }

    private function priceList()
    {
        return Product::active()->get()->map(fn (Product $p) => ['product' => $p, 'price' => $p->priceFor()])->filter(fn ($row) => $row['price']);
    }
}
