<?php

namespace App\Http\Controllers;

use App\Enums\VoucherStatus;
use App\Models\AuditLog;
use App\Models\Cooperative;
use App\Models\Crop;
use App\Models\District;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\Region;
use App\Models\Reservation;
use App\Models\Season;
use App\Models\Voucher;
use App\Services\FarmerService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class FarmerController extends Controller
{
    public function index(Request $request): View
    {
        $farmers = Farmer::with(['region', 'mainCrop', 'cooperative'])
            ->search($request->query('q'))
            ->when($request->integer('region'), fn ($q, $id) => $q->where('region_id', $id))
            ->when($request->integer('crop'), fn ($q, $id) => $q->where('main_crop_id', $id))
            ->when($request->query('via'), fn ($q, $via) => $q->where('registered_via', $via))
            ->latest()
            ->paginate(25)
            ->withQueryString();

        return view('farmers.index', [
            'farmers' => $farmers,
            'regions' => Region::orderBy('name')->get(),
            'crops' => Crop::orderBy('name')->get(),
            'stats' => [
                'total' => Farmer::count(),
                'female' => Farmer::where('gender', 'female')->count(),
                'ussd' => Farmer::where('registered_via', 'ussd')->count(),
                'served' => Farmer::whereHas('sales', fn ($q) => $q->where('season_id', Season::current()->id))->count(),
            ],
        ]);
    }

    public function show(Farmer $farmer, FarmerService $farmers): View
    {
        $season = Season::current();
        $farmer->load(['region', 'district', 'mainCrop', 'cooperative', 'entitlements' => fn ($q) => $q->where('season_id', $season->id)->with('product')]);

        return view('farmers.show', [
            'farmer' => $farmer,
            'remaining' => $farmer->entitlements->mapWithKeys(fn ($e) => [$e->product_id => $farmers->remainingBags($farmer, $e->product, $season)]),
            'vouchers' => $farmer->vouchers()->with('product')->latest()->get(),
            'sales' => $farmer->sales()->with(['warehouse', 'items.product'])->latest('sold_at')->get(),
            'reservations' => $farmer->reservations()->with(['warehouse', 'product'])->latest()->get(),
            'complaints' => $farmer->complaints()->latest()->get(),
            'messages' => $farmer->smsMessages()->latest()->take(10)->get(),
            'products' => Product::active()->get(),
        ]);
    }

    public function create(): View
    {
        return view('farmers.form', $this->formData(new Farmer(['farm_size_acres' => 1])));
    }

    public function store(Request $request, FarmerService $farmers): RedirectResponse
    {
        $farmer = $farmers->register($this->validated($request), 'officer');

        return redirect()->route('farmers.show', $farmer)->with('success', __('Farmer registered. Allowance generated.'));
    }

    public function edit(Farmer $farmer): View
    {
        return view('farmers.form', $this->formData($farmer));
    }

    public function update(Request $request, Farmer $farmer, FarmerService $farmers): RedirectResponse
    {
        $data = $this->validated($request, $farmer);
        $data['phone'] = Farmer::normalisePhone($data['phone']);
        $farmer->update([...$data, 'status' => $request->input('status', $farmer->status)]);
        $farmers->generateEntitlements($farmer->fresh('mainCrop'));
        AuditLog::record('farmer.updated', "Updated farmer {$farmer->farmer_number}", $farmer, $farmer->getChanges());

        return redirect()->route('farmers.show', $farmer)->with('success', __('Farmer updated.'));
    }

    public function issueVoucher(Request $request, Farmer $farmer, FarmerService $farmers): RedirectResponse
    {
        $data = $request->validate([
            'product_id' => ['required', 'exists:products,id'],
            'bags' => ['required', 'integer', 'min:1', 'max:1000'],
        ]);

        $voucher = $farmers->issueVoucher($farmer, Product::findOrFail($data['product_id']), (int) $data['bags']);

        return back()->with('success', __('Voucher :code issued and sent by SMS.', ['code' => $voucher->code]));
    }

    public function refreshEntitlements(Farmer $farmer, FarmerService $farmers): RedirectResponse
    {
        $farmers->generateEntitlements($farmer->load('mainCrop'));

        return back()->with('success', __('Allowance recalculated.'));
    }

    public function vouchers(Request $request): View
    {
        return view('farmers.vouchers', [
            'vouchers' => Voucher::with(['farmer', 'product', 'redeemedAt'])
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->when($request->query('q'), fn ($q, $code) => $q->where('code', 'like', '%'.strtoupper($code).'%'))
                ->latest()
                ->paginate(30)
                ->withQueryString(),
            'statuses' => VoucherStatus::options(),
        ]);
    }

    public function reservations(): View
    {
        return view('farmers.reservations', [
            'reservations' => Reservation::with(['farmer', 'product', 'warehouse'])
                ->when($this->warehouseScope(), fn ($q, $ids) => $q->whereIn('warehouse_id', $ids))
                ->latest()
                ->paginate(30),
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request, ?Farmer $farmer = null): array
    {
        return $request->validate([
            'first_name' => ['required', 'string', 'max:60'],
            'last_name' => ['required', 'string', 'max:60'],
            'gender' => ['nullable', Rule::in(['male', 'female'])],
            'date_of_birth' => ['nullable', 'date', 'before:-15 years'],
            'phone' => ['required', 'string', 'regex:/^(\+?255|0)?[67]\d{8}$/', Rule::unique('farmers', 'phone')->ignore($farmer)],
            'nida_number' => ['nullable', 'string', 'max:30', Rule::unique('farmers', 'nida_number')->ignore($farmer)],
            'region_id' => ['required', 'exists:regions,id'],
            'district_id' => ['nullable', 'exists:districts,id'],
            'ward' => ['nullable', 'string', 'max:100'],
            'village' => ['nullable', 'string', 'max:100'],
            'farm_size_acres' => ['required', 'numeric', 'min:0.25', 'max:5000'],
            'main_crop_id' => ['required', 'exists:crops,id'],
            'cooperative_id' => ['nullable', 'exists:cooperatives,id'],
            'lat' => ['nullable', 'numeric', 'between:-12,0'],
            'lng' => ['nullable', 'numeric', 'between:29,41'],
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function formData(Farmer $farmer): array
    {
        return [
            'farmer' => $farmer,
            'regions' => Region::orderBy('name')->get(),
            'districts' => District::orderBy('name')->get(['id', 'region_id', 'name']),
            'crops' => Crop::orderBy('name')->get(),
            'cooperatives' => Cooperative::where('is_active', true)->orderBy('name')->get(),
        ];
    }
}
