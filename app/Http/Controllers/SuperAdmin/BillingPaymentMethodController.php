<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\BillingPaymentMethod;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class BillingPaymentMethodController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService
    ) {}

    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');

        $paymentMethods = BillingPaymentMethod::query()
            ->when($search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('display_name', 'like', "%{$s}%")
                  ->orWhere('bank_name', 'like', "%{$s}%")
                  ->orWhere('account_name', 'like', "%{$s}%");
            }))
            ->when($status === 'active', fn($q) => $q->where('is_active', true)->whereNull('deleted_at'))
            ->when($status === 'inactive', fn($q) => $q->where('is_active', false)->whereNull('deleted_at'))
            ->when($status === 'archived', fn($q) => $q->whereNotNull('deleted_at'))
            ->when(!$status, fn($q) => $q->whereNull('deleted_at'))
            ->orderBy('sort_order')
            ->orderBy('display_name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('SuperAdmin/BillingPaymentMethods/Index', [
            'paymentMethods' => $paymentMethods,
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create()
    {
        return Inertia::render('SuperAdmin/BillingPaymentMethods/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'type' => 'required|string|in:bank_transfer,cod,gateway',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'instructions' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'qr_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'supports_manual_payment' => 'boolean',
            'supports_gateway' => 'boolean',
            'gateway_code' => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('qr_image')) {
            $validated['qr_image'] = $this->imageService->upload($request->file('qr_image'), 'billing-payment-methods');
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_default'] = $request->boolean('is_default', false);
        $validated['supports_manual_payment'] = $request->boolean('supports_manual_payment', true);
        $validated['supports_gateway'] = $request->boolean('supports_gateway', false);
        $validated['sort_order'] = $validated['sort_order'] ?? BillingPaymentMethod::max('sort_order') + 1;
        $validated['created_by'] = auth()->id();

        if ($validated['is_default']) {
            BillingPaymentMethod::where('is_default', true)->update(['is_default' => false]);
        }

        BillingPaymentMethod::create($validated);

        return redirect()->route('superadmin.billing-payment-methods.index')
            ->with('success', 'Billing payment method created successfully.');
    }

    public function edit(BillingPaymentMethod $billingPaymentMethod)
    {
        return Inertia::render('SuperAdmin/BillingPaymentMethods/Edit', [
            'paymentMethod' => $billingPaymentMethod,
        ]);
    }

    public function update(Request $request, BillingPaymentMethod $billingPaymentMethod): RedirectResponse
    {
        $validated = $request->validate([
            'display_name' => 'required|string|max:255',
            'type' => 'required|string|in:bank_transfer,cod,gateway',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'instructions' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'qr_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active' => 'boolean',
            'is_default' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
            'supports_manual_payment' => 'boolean',
            'supports_gateway' => 'boolean',
            'gateway_code' => 'nullable|string|max:255',
        ]);

        if ($request->hasFile('qr_image')) {
            if ($billingPaymentMethod->qr_image) {
                $this->imageService->delete($billingPaymentMethod->qr_image);
            }
            $validated['qr_image'] = $this->imageService->upload($request->file('qr_image'), 'billing-payment-methods');
        } else {
            unset($validated['qr_image']);
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['is_default'] = $request->boolean('is_default', false);
        $validated['supports_manual_payment'] = $request->boolean('supports_manual_payment', true);
        $validated['supports_gateway'] = $request->boolean('supports_gateway', false);
        $validated['updated_by'] = auth()->id();

        if ($validated['is_default']) {
            BillingPaymentMethod::where('is_default', true)->where('id', '!=', $billingPaymentMethod->id)->update(['is_default' => false]);
        }

        $billingPaymentMethod->update($validated);

        return redirect()->route('superadmin.billing-payment-methods.index')
            ->with('success', 'Billing payment method updated successfully.');
    }

    public function toggleActive(BillingPaymentMethod $billingPaymentMethod): JsonResponse
    {
        $billingPaymentMethod->update([
            'is_active' => !$billingPaymentMethod->is_active,
        ]);

        return response()->json([
            'success' => true,
            'is_active' => $billingPaymentMethod->fresh()->is_active,
            'message' => $billingPaymentMethod->is_active ? 'Payment method activated.' : 'Payment method deactivated.',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:billing_payment_methods,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->items as $item) {
            BillingPaymentMethod::where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['success' => true, 'message' => 'Order updated.']);
    }

    public function destroy(BillingPaymentMethod $billingPaymentMethod): RedirectResponse
    {
        if ($billingPaymentMethod->qr_image) {
            $this->imageService->delete($billingPaymentMethod->qr_image);
        }

        $billingPaymentMethod->update(['is_active' => false, 'deleted_at' => now()]);

        return redirect()->route('superadmin.billing-payment-methods.index')
            ->with('success', 'Billing payment method archived successfully.');
    }

    public function restore($id): RedirectResponse
    {
        $method = BillingPaymentMethod::withTrashed()->findOrFail($id);
        $method->update(['is_active' => true, 'deleted_at' => null]);

        return redirect()->route('superadmin.billing-payment-methods.index')
            ->with('success', 'Billing payment method restored successfully.');
    }
}
