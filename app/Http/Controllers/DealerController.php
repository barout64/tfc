<?php

namespace App\Http\Controllers;

use App\Enums\DealerStatus;
use App\Enums\Role;
use App\Enums\WarehouseType;
use App\Models\AgroDealer;
use App\Models\AuditLog;
use App\Models\Region;
use App\Models\Sale;
use App\Models\StockBalance;
use App\Models\User;
use App\Models\Warehouse;
use App\Services\SmsService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class DealerController extends Controller
{
    public function index(Request $request): View
    {
        return view('dealers.index', [
            'dealers' => AgroDealer::with(['region', 'outlet'])
                ->when($request->query('status'), fn ($q, $status) => $q->where('status', $status))
                ->when($request->integer('region'), fn ($q, $id) => $q->where('region_id', $id))
                ->when($request->query('q'), fn ($q, $term) => $q->where(fn ($w) => $w->where('business_name', 'like', "%{$term}%")->orWhere('dealer_number', 'like', "%{$term}%")->orWhere('tfra_license_no', 'like', "%{$term}%")))
                ->latest()
                ->paginate(25)
                ->withQueryString(),
            'counts' => AgroDealer::selectRaw('status, COUNT(*) as total')->groupBy('status')->pluck('total', 'status'),
            'statuses' => DealerStatus::options(),
            'regions' => Region::orderBy('name')->get(),
        ]);
    }

    public function show(AgroDealer $dealer): View
    {
        $dealer->load(['region', 'district', 'outlet']);

        return view('dealers.show', [
            'dealer' => $dealer,
            'stock' => $dealer->outlet
                ? StockBalance::with('batch.product')->where('warehouse_id', $dealer->outlet->id)->where('bags', '>', 0)->get()
                : collect(),
            'purchases' => $dealer->sales()->with('warehouse')->latest('sold_at')->take(15)->get(),
            'farmerSales' => $dealer->outlet ? Sale::with('farmer')->where('warehouse_id', $dealer->outlet->id)->latest('sold_at')->take(15)->get() : collect(),
            'user' => User::where('agro_dealer_id', $dealer->id)->first(),
        ]);
    }

    /**
     * Approve / reject / suspend. Approval creates the dealer's outlet (for stock) and a portal login.
     */
    public function review(Request $request, AgroDealer $dealer, SmsService $sms): RedirectResponse
    {
        $data = $request->validate([
            'status' => ['required', Rule::enum(DealerStatus::class)->except([DealerStatus::Pending])],
            'review_notes' => ['nullable', 'string', 'max:1000'],
            'credit_limit' => ['nullable', 'numeric', 'min:0'],
        ]);
        $status = DealerStatus::from($data['status']);

        $password = null;
        DB::transaction(function () use ($dealer, $data, $status, $request, &$password) {
            $dealer->update([
                'status' => $status,
                'review_notes' => $data['review_notes'] ?? null,
                'credit_limit' => $data['credit_limit'] ?? $dealer->credit_limit,
                'approved_at' => $status === DealerStatus::Approved ? now() : $dealer->approved_at,
                'approved_by' => $status === DealerStatus::Approved ? $request->user()->id : $dealer->approved_by,
            ]);

            if ($status !== DealerStatus::Approved) {
                $dealer->outlet?->update(['is_active' => false, 'is_public' => false]);
                User::where('agro_dealer_id', $dealer->id)->update(['is_active' => false]);

                return;
            }

            $outlet = Warehouse::firstOrNew(['agro_dealer_id' => $dealer->id]);
            $outlet->fill([
                'code' => 'OUT-'.$dealer->dealer_number,
                'name' => $dealer->business_name,
                'type' => WarehouseType::AgroDealer,
                'region_id' => $dealer->region_id,
                'district_id' => $dealer->district_id,
                'address' => $dealer->address,
                'lat' => $dealer->lat,
                'lng' => $dealer->lng,
                'contact_phone' => $dealer->phone,
                'tfra_number' => $dealer->tfra_license_no,
                'is_active' => true,
                'is_public' => true,
            ])->save();

            $user = User::where('agro_dealer_id', $dealer->id)->first();
            if (! $user) {
                $password = Str::password(10, symbols: false);
                User::create([
                    'name' => $dealer->owner_name,
                    'email' => $dealer->email ?: strtolower($dealer->dealer_number).'@dealers.tfc.go.tz',
                    'phone' => $dealer->phone,
                    'role' => Role::AgroDealer,
                    'agro_dealer_id' => $dealer->id,
                    'warehouse_id' => $outlet->id,
                    'password' => $password,
                    'locale' => 'sw',
                ]);
            } else {
                $user->update(['is_active' => true]);
            }
        });

        AuditLog::record('dealer.reviewed', "Dealer {$dealer->dealer_number} marked {$status->value}", $dealer);

        if ($status === DealerStatus::Approved && $password) {
            $login = $dealer->email ?: strtolower($dealer->dealer_number).'@dealers.tfc.go.tz';
            $sms->send($dealer->phone, __('TFC: Ombi lako la uwakala limekubaliwa. Ingia :url kwa :login, nenosiri: :password', [
                'url' => route('login'),
                'login' => $login,
                'password' => $password,
            ]), 'dealer');

            return back()->with('success', __('Dealer approved. Login details sent by SMS (:login).', ['login' => $login]));
        }

        return back()->with('success', __('Dealer status updated.'));
    }
}
