<?php

namespace App\Services;

use App\Enums\ComplaintCategory;
use App\Enums\VoucherStatus;
use App\Exceptions\BusinessRuleException;
use App\Models\Complaint;
use App\Models\Crop;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\Region;
use App\Models\StockBalance;
use App\Models\UssdSession;
use App\Models\Warehouse;

/**
 * USSD menu in Kiswahili. Input "text" is the whole path so far, e.g. "4*Juma*Mussa".
 * Responses start with "CON" (continue) or "END" (close session).
 */
class UssdService
{
    public function __construct(private FarmerService $farmers) {}

    public function handle(string $sessionId, string $phone, string $text): string
    {
        UssdSession::updateOrCreate(['session_id' => $sessionId], ['phone' => $phone, 'last_input' => $text]);

        $steps = $text === '' ? [] : explode('*', $text);
        $farmer = Farmer::where('phone', Farmer::normalisePhone($phone))->first();

        if ($steps === []) {
            return $this->mainMenu($farmer);
        }

        return match ($steps[0]) {
            '1' => $this->entitlement($farmer),
            '2' => $this->prices(),
            '3' => $this->salePoints($steps),
            '4' => $this->register($steps, $phone, $farmer),
            '5' => $this->vouchers($farmer),
            '6' => $this->complaint($steps, $phone, $farmer),
            default => 'END Chaguo si sahihi. Jaribu tena.',
        };
    }

    private function mainMenu(?Farmer $farmer): string
    {
        $greeting = $farmer ? "Karibu {$farmer->first_name}" : 'Karibu TFC Kilimo';

        return "CON {$greeting}\n1. Mgao wangu wa ruzuku\n2. Bei za mbolea\n3. Vituo vyenye mbolea\n4. Jisajili\n5. Vocha zangu\n6. Toa malalamiko";
    }

    private function entitlement(?Farmer $farmer): string
    {
        if (! $farmer) {
            return 'END Hujasajiliwa. Chagua 4 kwenye menyu kujisajili.';
        }

        $lines = $farmer->entitlements()->with('product')->whereHas('season', fn ($q) => $q->where('is_active', true))->get()
            ->map(fn ($e) => "{$e->product->code}: {$this->farmers->remainingBags($farmer, $e->product)}/{$e->bags_allowed} mifuko");

        if ($lines->isEmpty()) {
            return 'END Huna mgao kwa msimu huu. Wasiliana na afisa ugani.';
        }

        return "END Mgao uliobaki ({$farmer->farmer_number}):\n".$lines->implode("\n");
    }

    private function prices(): string
    {
        $lines = Product::active()->get()
            ->map(fn (Product $p) => [$p, $p->priceFor()])
            ->filter(fn ($pair) => $pair[1] !== null)
            ->map(fn ($pair) => "{$pair[0]->code} {$pair[0]->package_kg}kg: TZS ".number_format((float) $pair[1]->subsidy_price));

        return "END Bei ya ruzuku kwa mfuko:\n".$lines->implode("\n");
    }

    /**
     * @param  list<string>  $steps
     */
    private function salePoints(array $steps): string
    {
        if (! isset($steps[1])) {
            return 'CON Andika jina la mkoa wako (mf. Mbeya):';
        }

        $region = Region::where('name', 'like', '%'.trim($steps[1]).'%')->first();
        if (! $region) {
            return 'END Mkoa haujapatikana. Jaribu tena.';
        }

        $points = Warehouse::active()->sellsToFarmers()->where('region_id', $region->id)->get()
            ->map(function (Warehouse $w) {
                $bags = (int) StockBalance::where('warehouse_id', $w->id)->sum('bags');

                return $bags > 0 ? "{$w->name}: ".number_format($bags).' mifuko' : null;
            })
            ->filter()
            ->take(5);

        if ($points->isEmpty()) {
            return "END Hakuna kituo chenye mbolea {$region->name} kwa sasa. Piga ".config('kilimo.hotline');
        }

        return "END Vituo {$region->name}:\n".$points->implode("\n");
    }

    /**
     * Steps: 4 * first name * last name * region * acres * crop number
     *
     * @param  list<string>  $steps
     */
    private function register(array $steps, string $phone, ?Farmer $farmer): string
    {
        if ($farmer) {
            return "END Tayari umesajiliwa. Namba yako: {$farmer->farmer_number}";
        }

        $crops = Crop::orderBy('id')->take(8)->get();

        return match (count($steps)) {
            1 => 'CON Andika jina lako la kwanza:',
            2 => 'CON Andika jina lako la mwisho:',
            3 => 'CON Andika jina la mkoa wako:',
            4 => 'CON Ukubwa wa shamba (ekari):',
            5 => "CON Zao kuu:\n".$crops->values()->map(fn ($c, $i) => ($i + 1).'. '.($c->name_sw ?? $c->name))->implode("\n"),
            default => $this->completeRegistration($steps, $phone, $crops),
        };
    }

    /**
     * @param  list<string>  $steps
     */
    private function completeRegistration(array $steps, string $phone, $crops): string
    {
        $region = Region::where('name', 'like', '%'.trim($steps[3]).'%')->first();
        $crop = $crops->values()->get(((int) $steps[5]) - 1);
        $acres = (float) str_replace(',', '.', $steps[4]);

        if (! $region || ! $crop || $acres <= 0) {
            return 'END Taarifa si sahihi. Tafadhali anza upya.';
        }

        try {
            $farmer = $this->farmers->register([
                'first_name' => ucfirst(trim($steps[1])),
                'last_name' => ucfirst(trim($steps[2])),
                'phone' => $phone,
                'region_id' => $region->id,
                'farm_size_acres' => $acres,
                'main_crop_id' => $crop->id,
            ], 'ussd');
        } catch (BusinessRuleException $e) {
            return 'END '.$e->getMessage();
        }

        return "END Hongera! Umesajiliwa. Namba yako: {$farmer->farmer_number}. Utapokea SMS.";
    }

    private function vouchers(?Farmer $farmer): string
    {
        if (! $farmer) {
            return 'END Hujasajiliwa. Chagua 4 kujisajili.';
        }

        $vouchers = $farmer->vouchers()->with('product')->where('status', VoucherStatus::Issued)->where('expires_at', '>', now())->get();
        if ($vouchers->isEmpty()) {
            return 'END Huna vocha hai kwa sasa.';
        }

        return "END Vocha zako:\n".$vouchers->map(fn ($v) => "{$v->code} - {$v->bags} {$v->product->code} hadi {$v->expires_at->format('d/m')}")->implode("\n");
    }

    /**
     * @param  list<string>  $steps
     */
    private function complaint(array $steps, string $phone, ?Farmer $farmer): string
    {
        $categories = [
            '1' => ComplaintCategory::Overpricing,
            '2' => ComplaintCategory::StockOut,
            '3' => ComplaintCategory::Quality,
            '4' => ComplaintCategory::DealerConduct,
            '5' => ComplaintCategory::Other,
        ];

        if (! isset($steps[1])) {
            return "CON Aina ya lalamiko:\n1. Bei juu ya ruzuku\n2. Mbolea haipatikani\n3. Ubora wa mbolea\n4. Tabia ya wakala\n5. Mengineyo";
        }

        if (! isset($categories[$steps[1]])) {
            return 'END Chaguo si sahihi.';
        }

        if (! isset($steps[2])) {
            return 'CON Eleza kwa ufupi (jina la wakala/kituo na tatizo):';
        }

        $complaint = Complaint::create([
            'ticket_number' => NumberGenerator::next(Complaint::class, 'ticket_number', 'TCK'),
            'farmer_id' => $farmer?->id,
            'name' => $farmer?->fullName(),
            'phone' => Farmer::normalisePhone($phone),
            'category' => $categories[$steps[1]],
            'region_id' => $farmer?->region_id,
            'description' => implode(' ', array_slice($steps, 2)),
            'channel' => 'ussd',
        ]);

        return "END Asante. Lalamiko lako limepokelewa. Namba: {$complaint->ticket_number}";
    }
}
