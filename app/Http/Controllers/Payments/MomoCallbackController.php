<?php

namespace App\Http\Controllers\Payments;

use App\Enums\PaymentEventType;
use App\Http\Controllers\Controller;
use App\Jobs\VerifyMomoPayment;
use App\Models\Payment;
use App\Services\Payments\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Support\Facades\Log;

/**
 * MTN calls this when a RequestToPay finishes. MTN does not sign callbacks,
 * so the body is only logged: the payment is then verified by asking MTN's
 * status endpoint, which is the only thing that can change its status.
 */
class MomoCallbackController extends Controller
{
    public function __invoke(Request $request, string $token, string $reference, PaymentService $payments): Response
    {
        $expected = (string) config('mtn_momo.callback_token');

        if ($expected === '' || ! hash_equals($expected, $token)) {
            abort(404);
        }

        $payment = Payment::where('transaction_reference', $reference)->first();

        if (! $payment) {
            Log::warning('MoMo callback for unknown reference', ['reference' => $reference, 'ip' => $request->ip()]);

            return response()->noContent(200);
        }

        $payments->log($payment, PaymentEventType::CallbackReceived, null, (array) $request->json()->all());
        VerifyMomoPayment::dispatch($payment->id);

        return response()->noContent(200);
    }
}
