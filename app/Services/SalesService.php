<?php

namespace App\Services;

use App\Enums\DealerStatus;
use App\Enums\MovementType;
use App\Enums\PaymentMethod;
use App\Enums\VoucherStatus;
use App\Enums\WarehouseType;
use App\Exceptions\BusinessRuleException;
use App\Models\AgroDealer;
use App\Models\AuditLog;
use App\Models\Cooperative;
use App\Models\Entitlement;
use App\Models\Farmer;
use App\Models\Product;
use App\Models\Reservation;
use App\Models\Sale;
use App\Models\Season;
use App\Models\User;
use App\Models\Voucher;
use App\Models\Warehouse;
use Illuminate\Support\Facades\DB;

class SalesService
{
    public function __construct(
        private StockService $stock,
        private FarmerService $farmers,
        private PaymentService $payments,
        private SmsService $sms,
    ) {}

    /**
     * Record a sale from a warehouse / sale point / dealer outlet.
     *
     * @param  list<array{product_id: int, bags: int}>  $lines
     */
    public function sell(
        Warehouse $warehouse,
        string $customerType,
        Farmer|AgroDealer|Cooperative $customer,
        array $lines,
        PaymentMethod $method,
        User $seller,
        ?string $payerPhone = null,
        ?Voucher $voucher = null,
        ?Reservation $reservation = null,
    ): Sale {
        $season = Season::current();
        $this->guardCustomer($customerType, $customer, $method);

        $sale = DB::transaction(function () use ($warehouse, $customerType, $customer, $lines, $method, $seller, $season, $voucher, $reservation) {
            $sale = Sale::create([
                'number' => NumberGenerator::next(Sale::class, 'number', 'SL'),
                'season_id' => $season->id,
                'warehouse_id' => $warehouse->id,
                'customer_type' => $customerType,
                'farmer_id' => $customer instanceof Farmer ? $customer->id : null,
                'agro_dealer_id' => $customer instanceof AgroDealer ? $customer->id : null,
                'cooperative_id' => $customer instanceof Cooperative ? $customer->id : null,
                'total_bags' => 0,
                'total_amount' => 0,
                'subsidy_amount' => 0,
                'payment_method' => $method,
                'sold_by' => $seller->id,
                'sold_at' => now(),
            ]);

            // Subsidy is booked once, when TFC sells. A dealer reselling to a farmer only consumes entitlement.
            $bookSubsidy = $warehouse->type !== WarehouseType::AgroDealer;
            $totalBags = 0;
            $totalAmount = 0.0;
            $totalSubsidy = 0.0;

            foreach ($lines as $line) {
                $product = Product::findOrFail($line['product_id']);
                $bags = (int) $line['bags'];
                if ($bags < 1) {
                    continue;
                }

                if ($customer instanceof Farmer) {
                    $this->consumeEntitlement($customer, $product, $bags, $season, $voucher, $reservation);
                }

                $price = $product->priceFor($warehouse->region_id, $season)
                    ?? throw new BusinessRuleException(__('No price is set for :product this season.', ['product' => $product->name]));
                $subsidyPerBag = $bookSubsidy ? $price->subsidyPerBag() : 0;

                foreach ($this->stock->allocate($warehouse, $product, $bags) as $allocation) {
                    $lineTotal = $allocation['bags'] * (float) $price->subsidy_price;
                    $sale->items()->create([
                        'product_id' => $product->id,
                        'stock_batch_id' => $allocation['batch']->id,
                        'bags' => $allocation['bags'],
                        'unit_price' => $price->subsidy_price,
                        'subsidy_per_bag' => $subsidyPerBag,
                        'line_total' => $lineTotal,
                    ]);

                    $this->stock->remove($warehouse, $allocation['batch'], $allocation['bags'], MovementType::Sale, $sale);

                    // Stock sold to a dealer moves into the dealer's own outlet.
                    if ($customer instanceof AgroDealer && $customer->outlet) {
                        $this->stock->add($customer->outlet, $allocation['batch'], $allocation['bags'], MovementType::TransferIn, $sale, "Purchased from {$warehouse->name}");
                    }

                    $totalAmount += $lineTotal;
                    $totalSubsidy += $allocation['bags'] * $subsidyPerBag;
                }

                $totalBags += $bags;
            }

            if ($totalBags === 0) {
                throw new BusinessRuleException(__('Add at least one product line.'));
            }

            $sale->update(['total_bags' => $totalBags, 'total_amount' => $totalAmount, 'subsidy_amount' => $totalSubsidy]);

            if ($voucher) {
                $voucher->update(['status' => VoucherStatus::Redeemed, 'redeemed_at' => now(), 'sale_id' => $sale->id, 'redeemed_at_warehouse_id' => $warehouse->id]);
            }
            if ($reservation) {
                $reservation->update(['status' => 'collected']);
            }

            AuditLog::record('sale.created', "Sale {$sale->number}: {$totalBags} bags to {$customerType}", $sale);

            return $sale;
        });

        $this->payments->collect($sale, $method, $payerPhone ?? $this->customerPhone($customer));

        if ($customer instanceof Farmer) {
            $this->sms->send($customer->phone, __('TFC: Umenunua mifuko :bags kwa TZS :amount (:number). Asante kwa kutumia mbolea ya ruzuku.', [
                'bags' => $sale->total_bags,
                'amount' => number_format((float) $sale->total_amount),
                'number' => $sale->number,
            ]), 'receipt', $customer);
        }

        return $sale->fresh(['items.product', 'payments']);
    }

    private function consumeEntitlement(Farmer $farmer, Product $product, int $bags, Season $season, ?Voucher $voucher, ?Reservation $reservation): void
    {
        if ($farmer->status !== 'active') {
            throw new BusinessRuleException(__('Farmer account is not active.'));
        }

        $entitlement = Entitlement::where(['farmer_id' => $farmer->id, 'season_id' => $season->id, 'product_id' => $product->id])
            ->lockForUpdate()
            ->first();

        // Bags already promised by this voucher/reservation are part of the allowance being used now.
        $alreadyHeld = ($voucher?->product_id === $product->id ? $voucher->bags : 0)
            + ($reservation?->product_id === $product->id ? $reservation->bags : 0);
        $available = $this->farmers->remainingBags($farmer, $product, $season) + $alreadyHeld;

        if (! $entitlement || $bags > $available) {
            throw new BusinessRuleException(__(':name can only buy :bags more subsidised bags of :product this season.', [
                'name' => $farmer->fullName(),
                'bags' => $available,
                'product' => $product->name,
            ]));
        }

        $entitlement->increment('bags_used', $bags);
    }

    private function guardCustomer(string $customerType, Farmer|AgroDealer|Cooperative $customer, PaymentMethod $method): void
    {
        if ($customer instanceof AgroDealer && $customer->status !== DealerStatus::Approved) {
            throw new BusinessRuleException(__('Agro-dealer is not approved.'));
        }

        if ($customer instanceof AgroDealer && $customer->isLicenseExpired()) {
            throw new BusinessRuleException(__('Agro-dealer TFRA licence has expired.'));
        }

        if ($method === PaymentMethod::Credit && $customerType === 'farmer') {
            throw new BusinessRuleException(__('Credit sales are only allowed for dealers and cooperatives.'));
        }
    }

    private function customerPhone(Farmer|AgroDealer|Cooperative $customer): ?string
    {
        return $customer->phone ?? null;
    }
}
