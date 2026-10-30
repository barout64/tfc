<?php

namespace App\Services;

use App\Enums\VoucherStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\AuditLog;
use App\Models\Entitlement;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Season;
use App\Models\Voucher;
use App\Models\Warehouse;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;

class FarmerService
{
    public function __construct(private SmsService $sms, private StockService $stock) {}

    /**
     * @param  array<string, mixed>  $data
     */
    public function register(array $data, string $channel = 'web'): Farmer
    {
        $data['phone'] = Farmer::normalisePhone($data['phone']);

        if (Farmer::where('phone', $data['phone'])->exists()) {
            throw new BusinessRuleException(__('A farmer with this phone number is already registered.'));
        }

        return DB::transaction(function () use ($data, $channel) {
            $farmer = Farmer::create([
                ...$data,
                'farmer_number' => NumberGenerator::next(Farmer::class, 'farmer_number', 'TFC-F'),
                'registered_via' => $channel,
                'status' => 'active',
            ]);

            $this->generateEntitlements($farmer);
            AuditLog::record('farmer.registered', "Registered farmer {$farmer->farmer_number} via {$channel}", $farmer);

            $this->sms->send($farmer->phone, __('Karibu TFC! Umesajiliwa. Namba yako ya mkulima: :number. Piga :ussd kuangalia mgao wako wa mbolea ya ruzuku.', [
                'number' => $farmer->farmer_number,
                'ussd' => config('kilimo.ussd_code'),
            ]), 'registration', $farmer);

            return $farmer;
        });
    }

    /**
     * Allowance = farm size (capped) × agronomic recommendation for the farmer's main crop.
     *
     * @return Collection<int, Entitlement>
     */
    public function generateEntitlements(Farmer $farmer, ?Season $season = null): Collection
    {
        $season ??= Season::current();
        $acres = min((float) $farmer->farm_size_acres, config('kilimo.max_subsidised_acres'));
        $recommendations = $farmer->mainCrop?->recommendations()->get() ?? collect();

        return $recommendations->map(function ($recommendation) use ($farmer, $season, $acres) {
            $entitlement = Entitlement::firstOrNew([
                'farmer_id' => $farmer->id,
                'season_id' => $season->id,
                'product_id' => $recommendation->product_id,
            ]);
            $entitlement->bags_allowed = max((int) ceil($acres * (float) $recommendation->bags_per_acre), $entitlement->bags_used);
            $entitlement->save();

            return $entitlement;
        });
    }

    public function remainingBags(Farmer $farmer, Product $product, ?Season $season = null): int
    {
        $season ??= Season::current();

        $entitlement = Entitlement::where(['farmer_id' => $farmer->id, 'season_id' => $season->id, 'product_id' => $product->id])->first();
        if (! $entitlement) {
            return 0;
        }

        $outstandingVouchers = (int) Voucher::where(['farmer_id' => $farmer->id, 'season_id' => $season->id, 'product_id' => $product->id])
            ->where('status', VoucherStatus::Issued)
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->sum('bags');

        $pendingReservations = (int) Reservation::where(['farmer_id' => $farmer->id, 'product_id' => $product->id, 'status' => 'pending'])
            ->where('expires_at', '>', now())
            ->sum('bags');

        return max(0, $entitlement->remainingBags() - $outstandingVouchers - $pendingReservations);
    }

    public function issueVoucher(Farmer $farmer, Product $product, int $bags): Voucher
    {
        if ($bags < 1) {
            throw new BusinessRuleException(__('Number of bags must be at least 1.'));
        }

        $remaining = $this->remainingBags($farmer, $product);
        if ($bags > $remaining) {
            throw new BusinessRuleException(__('Farmer can only receive :bags more subsidised bags of :product this season.', ['bags' => $remaining, 'product' => $product->name]));
        }

        do {
            $code = NumberGenerator::code(8);
        } while (Voucher::where('code', $code)->exists());

        $voucher = Voucher::create([
            'code' => $code,
            'farmer_id' => $farmer->id,
            'season_id' => Season::current()->id,
            'product_id' => $product->id,
            'bags' => $bags,
            'status' => VoucherStatus::Issued,
            'expires_at' => now()->addDays(config('kilimo.voucher_validity_days')),
        ]);

        $this->sms->send($farmer->phone, __('TFC: Vocha yako :code ni ya mifuko :bags ya :product. Itumie kwa wakala au kituo cha TFC kabla ya :date.', [
            'code' => $code,
            'bags' => $bags,
            'product' => $product->name,
            'date' => $voucher->expires_at->format('d/m/Y'),
        ]), 'voucher', $farmer);

        AuditLog::record('voucher.issued', "Issued voucher {$code} ({$bags} bags {$product->code}) to {$farmer->farmer_number}", $voucher);

        return $voucher;
    }

    public function reserve(Farmer $farmer, Warehouse $warehouse, Product $product, int $bags): Reservation
    {
        if ($bags > $this->remainingBags($farmer, $product)) {
            throw new BusinessRuleException(__('Reservation exceeds your remaining subsidised allowance.'));
        }

        if ($this->stock->productBalance($warehouse, $product) < $bags) {
            throw new BusinessRuleException(__(':place does not have enough :product right now.', ['place' => $warehouse->name, 'product' => $product->name]));
        }

        $reservation = Reservation::create([
            'code' => NumberGenerator::code(6),
            'farmer_id' => $farmer->id,
            'warehouse_id' => $warehouse->id,
            'product_id' => $product->id,
            'bags' => $bags,
            'status' => 'pending',
            'expires_at' => now()->addHours(config('kilimo.reservation_hours')),
        ]);

        $this->sms->send($farmer->phone, __('TFC: Umehifadhi mifuko :bags ya :product katika :place. Namba: :code. Ichukue ndani ya saa :hours.', [
            'bags' => $bags,
            'product' => $product->name,
            'place' => $warehouse->name,
            'code' => $reservation->code,
            'hours' => config('kilimo.reservation_hours'),
        ]), 'reservation', $farmer);

        return $reservation;
    }
}
