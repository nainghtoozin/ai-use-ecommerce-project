<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentMethodStoreRequest;
use App\Http\Requests\PaymentMethodUpdateRequest;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class AdminPaymentMethodController extends Controller
{
    public function __construct(
        private PaymentMethodService $paymentMethodService
    ) {}

    public function index(): View
    {
        $paymentMethods = PaymentMethod::latest()->paginate(10);
        return view('Admin.payment_methods.index', compact('paymentMethods'));
    }

    public function create(): View
    {
        return view('Admin.payment_methods.create');
    }

    public function store(PaymentMethodStoreRequest $request): RedirectResponse
    {
        $this->paymentMethodService->createPaymentMethod($request->validated());

        return redirect()->route('admin.payment-methods.index')
            ->with('success', 'Payment method created successfully.');
    }

    public function edit(PaymentMethod $paymentMethod): View
    {
        return view('Admin.payment_methods.edit', compact('paymentMethod'));
    }

    public function update(PaymentMethodUpdateRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->paymentMethodService->updatePaymentMethod($paymentMethod, $request->validated());

        return redirect()->route('admin.payment-methods.index')
            ->with('success', 'Payment method updated successfully.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        $this->paymentMethodService->deletePaymentMethod($paymentMethod);

        return redirect()->route('admin.payment-methods.index')
            ->with('success', 'Payment method deleted successfully.');
    }

    public function toggle(PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod = $this->paymentMethodService->toggleActive($paymentMethod);

        return response()->json([
            'success' => true,
            'is_active' => $paymentMethod->is_active,
            'message' => $paymentMethod->is_active ? 'Payment method activated.' : 'Payment method deactivated.',
        ]);
    }
}
