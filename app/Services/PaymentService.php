<?php

namespace App\Services;

use App\Enums\PaymentMethod;
use App\Models\Payment;
use App\Models\Sale;
use Illuminate\Support\Facades\DB;

/**
 * Collects payment for a sale. The "sandbox" driver simulates the mobile-money aggregator / GePG.
 */
class PaymentService
{
    public function __construct(private SmsService $sms) {}

    public function collect(Sale $sale, PaymentMethod $method, ?string $payerPhone = null, ?string $reference = null): ?Payment
    {
        if ($method === PaymentMethod::Credit) {
            $sale->update(['payment_status' => 'credit']);

            return null;
        }

        if ($method === PaymentMethod::Cash || $method === PaymentMethod::Bank) {
            $payment = $sale->payments()->create([
                'amount' => $sale->total_amount,
                'method' => $method,
                'reference' => $reference,
                'status' => 'confirmed',
                'paid_at' => now(),
            ]);
            $sale->update(['payment_status' => 'paid']);

            return $payment;
        }

        if ($method === PaymentMethod::Gepg) {
            $controlNumber = '9930'.str_pad((string) random_int(0, 99999999), 8, '0', STR_PAD_LEFT);
            $sale->update(['control_number' => $controlNumber, 'payment_status' => 'pending']);

            $payment = $sale->payments()->create([
                'amount' => $sale->total_amount,
                'method' => $method,
                'reference' => $controlNumber,
                'payer_phone' => $payerPhone,
                'status' => 'pending',
            ]);

            if ($payerPhone) {
                $this->sms->send($payerPhone, __('TFC: Pay TZS :amount using control number :cn for :sale.', [
                    'amount' => number_format((float) $sale->total_amount),
                    'cn' => $controlNumber,
                    'sale' => $sale->number,
                ]), 'payment');
            }

            return $payment;
        }

        // Mobile money: push a USSD/STK prompt to the payer's phone and wait for the callback.
        $payment = $sale->payments()->create([
            'amount' => $sale->total_amount,
            'method' => $method,
            'reference' => 'MM'.strtoupper(bin2hex(random_bytes(5))),
            'payer_phone' => $payerPhone,
            'status' => 'pending',
        ]);
        $sale->update(['payment_status' => 'pending']);

        return $payment;
    }

    /**
     * Called by the aggregator callback (or the "mark paid" button in sandbox).
     *
     * @param  array<string, mixed>  $payload
     */
    public function confirm(Payment $payment, array $payload = []): void
    {
        DB::transaction(function () use ($payment, $payload) {
            $payment->update(['status' => 'confirmed', 'paid_at' => now(), 'raw_callback' => $payload ?: null]);

            $sale = $payment->sale()->with('payments')->first();
            $paid = $sale->amountPaid();
            $sale->update(['payment_status' => $paid + 0.01 >= (float) $sale->total_amount ? 'paid' : 'partial']);
        });
    }

    public function fail(Payment $payment, array $payload = []): void
    {
        $payment->update(['status' => 'failed', 'raw_callback' => $payload ?: null]);
    }
}
