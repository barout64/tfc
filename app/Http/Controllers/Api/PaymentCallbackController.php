<?php

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Services\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

/**
 * Receives confirmations from the mobile-money aggregator / GePG.
 * The aggregator signs the body with HMAC-SHA256 using the shared callback secret.
 */
class PaymentCallbackController extends Controller
{
    public function __invoke(Request $request, PaymentService $payments): JsonResponse
    {
        $expected = hash_hmac('sha256', $request->getContent(), (string) config('kilimo.payments.callback_secret'));
        abort_unless(hash_equals($expected, (string) $request->header('X-Signature')), 401, 'Invalid signature');

        $data = $request->validate([
            'reference' => ['required', 'string'],
            'status' => ['required', 'in:success,failed'],
            'amount' => ['required', 'numeric'],
        ]);

        $payment = Payment::where('reference', $data['reference'])->where('status', 'pending')->firstOrFail();

        if ($data['status'] === 'success' && (float) $data['amount'] + 0.01 >= (float) $payment->amount) {
            $payments->confirm($payment, $request->all());
        } else {
            $payments->fail($payment, $request->all());
        }

        return response()->json(['received' => true]);
    }
}
