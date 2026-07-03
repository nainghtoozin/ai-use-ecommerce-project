<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PaymentMethod;
use App\Services\ImageService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Inertia\Inertia;

class SuperAdminPaymentMethodController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService
    ) {}

    public function index(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');

        $paymentMethods = PaymentMethod::withoutTenantScope()
            ->when($search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('display_name', 'like', "%{$s}%")
                  ->orWhere('bank_name', 'like', "%{$s}%");
            }))
            ->when($status === 'active', fn($q) => $q->where('is_active', true)->whereNull('deleted_at'))
            ->when($status === 'inactive', fn($q) => $q->where('is_active', false)->whereNull('deleted_at'))
            ->when($status === 'archived', fn($q) => $q->whereNotNull('deleted_at'))
            ->when(!$status, fn($q) => $q->whereNull('deleted_at'))
            ->orderBy('sort_order')
            ->orderBy('name')
            ->paginate(25)
            ->withQueryString();

        return Inertia::render('SuperAdmin/PaymentMethods/Index', [
            'paymentMethods' => $paymentMethods,
            'filters' => ['search' => $search, 'status' => $status],
        ]);
    }

    public function create()
    {
        return Inertia::render('SuperAdmin/PaymentMethods/Create');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'slug' => 'nullable|string|max:255|unique:payment_methods,slug|regex:/^[a-z0-9\-]+$/',
            'type' => 'required|string|in:bank_transfer,cod',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'instructions' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'qr_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($request->hasFile('qr_image')) {
            $validated['qr_image'] = $this->imageService->upload($request->file('qr_image'), 'payment-methods');
        }

        if (empty($validated['slug']) && !empty($validated['name'])) {
            $validated['slug'] = str($validated['name'])->slug()->toString();
        }

        $validated['is_active'] = $request->boolean('is_active', true);
        $validated['sort_order'] = $validated['sort_order'] ?? PaymentMethod::withoutTenantScope()->max('sort_order') + 1;

        PaymentMethod::withoutTenantScope()->create($validated);

        return redirect()->route('superadmin.payment-methods.index')
            ->with('success', 'Payment method created successfully.');
    }

    public function edit(PaymentMethod $paymentMethod)
    {
        $paymentMethod->loadMissing('tenant');
        return Inertia::render('SuperAdmin/PaymentMethods/Edit', [
            'paymentMethod' => $paymentMethod,
        ]);
    }

    public function update(Request $request, PaymentMethod $paymentMethod): RedirectResponse
    {
        $validated = $request->validate([
            'name' => 'required|string|max:255',
            'display_name' => 'nullable|string|max:255',
            'slug' => [
                'nullable',
                'string',
                'max:255',
                'regex:/^[a-z0-9\-]+$/',
                Rule::unique('payment_methods', 'slug')->ignore($paymentMethod->id),
            ],
            'type' => 'required|string|in:bank_transfer,cod',
            'account_name' => 'nullable|string|max:255',
            'account_number' => 'nullable|string|max:255',
            'bank_name' => 'nullable|string|max:255',
            'branch' => 'nullable|string|max:255',
            'instructions' => 'nullable|string',
            'currency' => 'nullable|string|max:3',
            'qr_image' => 'nullable|image|mimes:jpg,jpeg,png,webp|max:2048',
            'is_active' => 'boolean',
            'sort_order' => 'nullable|integer|min:0',
        ]);

        if ($request->hasFile('qr_image')) {
            if ($paymentMethod->qr_image) {
                $this->imageService->delete($paymentMethod->qr_image);
            }
            $validated['qr_image'] = $this->imageService->upload($request->file('qr_image'), 'payment-methods');
        } else {
            unset($validated['qr_image']);
        }

        if (empty($validated['slug']) && !empty($validated['name'])) {
            $validated['slug'] = str($validated['name'])->slug()->toString();
        }

        $validated['is_active'] = $request->boolean('is_active', true);

        $paymentMethod->update($validated);

        return redirect()->route('superadmin.payment-methods.index')
            ->with('success', 'Payment method updated successfully.');
    }

    public function toggleActive(PaymentMethod $paymentMethod): JsonResponse
    {
        $paymentMethod->update([
            'is_active' => !$paymentMethod->is_active,
        ]);

        return response()->json([
            'success' => true,
            'is_active' => $paymentMethod->fresh()->is_active,
            'message' => $paymentMethod->is_active ? 'Payment method activated.' : 'Payment method deactivated.',
        ]);
    }

    public function reorder(Request $request): JsonResponse
    {
        $request->validate([
            'items' => 'required|array',
            'items.*.id' => 'required|integer|exists:payment_methods,id',
            'items.*.sort_order' => 'required|integer|min:0',
        ]);

        foreach ($request->items as $item) {
            PaymentMethod::withoutTenantScope()->where('id', $item['id'])->update(['sort_order' => $item['sort_order']]);
        }

        return response()->json(['success' => true, 'message' => 'Order updated.']);
    }

    public function destroy(PaymentMethod $paymentMethod): RedirectResponse
    {
        if ($paymentMethod->orders()->exists()) {
            return redirect()->route('superadmin.payment-methods.index')
                ->with('error', 'Cannot delete payment method with associated orders. Archive it instead.');
        }

        $paymentMethod->update(['is_active' => false, 'deleted_at' => now()]);

        return redirect()->route('superadmin.payment-methods.index')
            ->with('success', 'Payment method archived successfully.');
    }

    public function restore($id): RedirectResponse
    {
        $paymentMethod = PaymentMethod::withoutTenantScope()->findOrFail($id);
        $paymentMethod->update(['is_active' => true, 'deleted_at' => null]);

        return redirect()->route('superadmin.payment-methods.index')
            ->with('success', 'Payment method restored successfully.');
    }
}
