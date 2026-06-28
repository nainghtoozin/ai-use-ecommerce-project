<?php

namespace App\Http\Controllers\Payment;

use App\Http\Controllers\Controller;
use App\Services\Payment\PaymentService;
use Illuminate\Http\Request;

class PaymentController extends Controller
{
    public function __construct(
        private PaymentService $paymentService,
    ) {}

    public function gateways(Request $request)
    {
        $available = $this->paymentService->getAvailableGateways();

        $gateways = array_map(fn($p) => [
            'name' => $p->getName(),
            'display_name' => $p->getDisplayName(),
            'supported_currencies' => $p->supportedCurrencies(),
            'is_configured' => $p->isConfigured(),
        ], $available);

        return response()->json(['gateways' => array_values($gateways)]);
    }

    public function checkout(string $gateway, Request $request)
    {
        $validated = $request->validate([
            'amount' => 'required|numeric|min:0.01',
            'currency' => 'required|string|size:3',
            'description' => 'required|string|max:500',
            'return_url' => 'nullable|url',
            'cancel_url' => 'nullable|url',
        ]);

        $result = $this->paymentService->charge(
            gateway: $gateway,
            amount: $validated['amount'],
            currency: $validated['currency'],
            description: $validated['description'],
            metadata: $request->input('metadata', []),
            returnUrl: $validated['return_url'] ?? null,
            cancelUrl: $validated['cancel_url'] ?? null,
        );

        if (!$result->success) {
            return redirect()->back()->with('error', $result->errorMessage ?? 'Payment failed.');
        }

        if ($result->requiresRedirect()) {
            return redirect()->away($result->redirectUrl);
        }

        return response()->json($result);
    }

    public function success(Request $request)
    {
        return inertia('Payment/Success', [
            'transaction_id' => $request->get('transaction_id'),
        ]);
    }

    public function cancel(Request $request)
    {
        return inertia('Payment/Cancelled', [
            'gateway' => $request->get('gateway'),
        ]);
    }
}
