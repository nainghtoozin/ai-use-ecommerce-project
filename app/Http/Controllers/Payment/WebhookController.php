<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

class WebhookController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
    ) {}

    public function handle(string $gateway, Request $request)
    {
        $payload = $request->all();

        $signature = $request->header('X-Webhook-Signature')
            ?? $request->header('Stripe-Signature')
            ?? $request->header('Paypal-Auth-Algo')
            ?? $request->input('p_signature');

        $result = $this->paymentService->handleWebhook($gateway, $payload, $signature);

        if (!$result->handled) {
            return response()->json([
                'error' => $result->errorMessage ?? 'Webhook not handled.',
            ], Response::HTTP_NOT_IMPLEMENTED);
        }

        return response()->json([
            'event' => $result->eventType,
            'status' => $result->status,
        ]);
    }
}
