<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\PaymentMethodStoreRequest;
use App\Http\Requests\PaymentMethodUpdateRequest;
use App\Models\PaymentMethod;
use App\Services\PaymentMethodService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Inertia\Inertia;

class AdminPaymentMethodController extends Controller
{
    public function __construct(
        private PaymentMethodService $paymentMethodService
    ) {}

    public function index(): \Inertia\Response
    {
        if (!auth()->user()->can('payments.view')) {
            abort(403, 'Unauthorized');
        }

        $paymentMethods = PaymentMethod::latest()->paginate(10);
        return Inertia::render('Admin/PaymentMethods/Index', [
            'paymentMethods' => $paymentMethods,
        ]);
    }

    public function create(): \Inertia\Response
    {
        if (!auth()->user()->can('payments.view')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/PaymentMethods/Create');
    }

    public function store(PaymentMethodStoreRequest $request): RedirectResponse
    {
        if (!auth()->user()->can('payments.view')) {
            abort(403, 'Unauthorized');
        }

        $this->paymentMethodService->createPaymentMethod($request->validated());

        return admin_redirect('admin.payment-methods.index')
            ->with('success', 'Payment method created successfully.');
    }

    public function edit(PaymentMethod $paymentMethod): \Inertia\Response
    {
        if (!auth()->user()->can('payments.view')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/PaymentMethods/Edit', [
            'paymentMethod' => $paymentMethod,
        ]);
    }

    public function update(PaymentMethodUpdateRequest $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        if (!auth()->user()->can('payments.view')) {
            abort(403, 'Unauthorized');
        }

        $this->paymentMethodService->updatePaymentMethod($paymentMethod, $request->validated());

        return admin_redirect('admin.payment-methods.index')
            ->with('success', 'Payment method updated successfully.');
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        if (!auth()->user()->can('payments.view')) {
            abort(403, 'Unauthorized');
        }

        $this->paymentMethodService->deletePaymentMethod($paymentMethod);

        return admin_redirect('admin.payment-methods.index')
            ->with('success', 'Payment method deleted successfully.');
    }

    public function toggle(PaymentMethod $paymentMethod): JsonResponse
    {
        if (!auth()->user()->can('payments.view')) {
            abort(403, 'Unauthorized');
        }

        $paymentMethod = $this->paymentMethodService->toggleActive($paymentMethod);

        return response()->json([
            'success' => true,
            'is_active' => $paymentMethod->is_active,
            'message' => $paymentMethod->is_active ? 'Payment method activated.' : 'Payment method deactivated.',
        ]);
    }
}
