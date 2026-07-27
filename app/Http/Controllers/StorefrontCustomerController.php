<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOrderNotifications;
use App\Jobs\ProcessOrderStatusChange;
use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Tenant;
use App\Models\Township;
use App\Services\ImageService;
use App\Services\OrderService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Inertia\Inertia;

class StorefrontCustomerController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly OrderService $orderService,
    ) {}
    private function ensureTenantAccess(Request $request): Tenant
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }

        $user = $request->user();
        if (!$user) {
            abort(403, 'Unauthorized access to this store.');
        }

        if (config('identity.use_accounts')) {
            if (!$user->memberships()->where('tenant_id', $tenant->id)->exists() && !$user->isSuperAdmin()) {
                abort(403, 'Unauthorized access to this store.');
            }
        } else {
            if ($user->tenant_id !== null && $user->tenant_id !== $tenant->id) {
                abort(403, 'Unauthorized access to this store.');
            }

            // Auto-assign tenant_id for legacy users
            if ($user->tenant_id === null) {
                $user->update(['tenant_id' => $tenant->id]);
            }
        }

        return $tenant;
    }

    private function tenantData(Tenant $tenant): array
    {
        return [
            'id' => $tenant->id,
            'name' => $tenant->name,
            'slug' => $tenant->slug,
            'store_url' => $tenant->store_url,
            'logo' => $tenant->logo,
            'status' => $tenant->status,
        ];
    }

    public function account(Request $request, $storeSlug = null)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        $orderStats = [
            'total' => $user->orders()->count(),
            'pending' => $user->orders()->where('order_status', Order::ORDER_STATUS_PENDING)->count(),
            'processing' => $user->orders()->where('order_status', Order::ORDER_STATUS_PROCESSING)->count(),
            'shipped' => $user->orders()->where('order_status', Order::ORDER_STATUS_SHIPPED)->count(),
            'delivered' => $user->orders()->where('order_status', Order::ORDER_STATUS_DELIVERED)->count(),
            'cancelled' => $user->orders()->where('order_status', Order::ORDER_STATUS_CANCELLED)->count(),
        ];

        $addressesCount = $user->addresses()->count();

        return Inertia::render('Storefront/Account', [
            'tenant' => $this->tenantData($tenant),
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'member_since' => $user->created_at,
                'addresses_count' => $addressesCount,
            ],
            'orderStats' => $orderStats,
        ]);
    }

    public function orders(Request $request, $storeSlug = null)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        $query = $user->orders()
            ->with(['items.product', 'items.variant', 'paymentMethod'])
            ->withCount('items');

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('invoice_number', 'like', "%{$search}%")
                    ->orWhere('id', 'like', "%{$search}%")
                    ->orWhereHas('items.product', function ($pq) use ($search) {
                        $pq->where('name', 'like', "%{$search}%");
                    });
            });
        }

        if ($request->filled('order_status')) {
            $query->where('order_status', $request->order_status);
        }

        if ($request->filled('payment_status')) {
            $query->where('payment_status', $request->payment_status);
        }

        if ($request->filled('date_range')) {
            $now = now();
            switch ($request->date_range) {
                case 'today':
                    $query->whereDate('created_at', $now->toDateString());
                    break;
                case '7days':
                    $query->where('created_at', '>=', $now->copy()->subDays(7));
                    break;
                case '30days':
                    $query->where('created_at', '>=', $now->copy()->subDays(30));
                    break;
                case 'this_month':
                    $query->whereMonth('created_at', $now->month)->whereYear('created_at', $now->year);
                    break;
            }
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }
        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        $sort = $request->input('sort', 'newest');
        switch ($sort) {
            case 'oldest':
                $query->orderBy('created_at', 'asc');
                break;
            case 'amount_high':
                $query->orderBy('total_amount', 'desc');
                break;
            case 'amount_low':
                $query->orderBy('total_amount', 'asc');
                break;
            default:
                $query->orderBy('created_at', 'desc');
        }

        $orders = $query->simplePaginate(10)->withQueryString();

        return Inertia::render('Storefront/Orders', [
            'tenant' => $this->tenantData($tenant),
            'orders' => $orders,
            'filters' => $request->only(['search', 'order_status', 'payment_status', 'date_range', 'date_from', 'date_to', 'sort']),
        ]);
    }

    public function showOrder(Request $request, $storeSlug, Order $order)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ($order->user_id !== $user->id || $order->user_type !== $user->getMorphClass()) {
            abort(404);
        }

        $order->loadMissing(['items.product', 'items.variant', 'paymentMethod', 'city', 'township']);

        return Inertia::render('Storefront/OrderShow', [
            'tenant' => $this->tenantData($tenant),
            'order' => $order,
        ]);
    }

    public function addresses(Request $request, $storeSlug = null)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        $addresses = $user->addresses()
            ->with(['city', 'township'])
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $cities = City::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Storefront/Addresses', [
            'tenant' => $this->tenantData($tenant),
            'addresses' => $addresses,
            'cities' => $cities,
            'customer' => [
                'first_name' => $user->first_name ?? ($user->name ? explode(' ', $user->name)[0] : ''),
                'last_name' => $user->last_name ?? ($user->name ? implode(' ', array_slice(explode(' ', $user->name), 1)) : ''),
                'phone' => $user->phone ?? '',
                'email' => $user->email ?? '',
            ],
        ]);
    }

    public function storeAddress(Request $request, $storeSlug = null): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'address_line' => ['required', 'string'],
            'city_id' => ['required', 'exists:cities,id'],
            'township_id' => ['required', 'exists:townships,id'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_default' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $township = Township::find($validated['township_id']);
        if (!$township || (int) $township->city_id !== (int) $validated['city_id']) {
            return back()->withErrors(['township_id' => 'The selected township is not valid for the chosen city.'])->withInput();
        }
        $validated['postal_code'] = $township->postal_code ?? $validated['postal_code'];

        if (!empty($validated['is_default'])) {
            $user->addresses()->update(['is_default' => false]);
        }

        $user->addresses()->create($validated);

        return redirect()->route('storefront.customer.addresses', ['store_slug' => $tenant->slug])
            ->with('success', 'Address added successfully.');
    }

    public function updateAddress(Request $request, $storeSlug, CustomerAddress $address): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ($address->user_id !== $user->id || $address->user_type !== $user->getMorphClass()) {
            abort(404);
        }

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:255'],
            'first_name' => ['required', 'string', 'max:255'],
            'last_name' => ['required', 'string', 'max:255'],
            'phone' => ['required', 'string', 'max:20'],
            'address_line' => ['required', 'string'],
            'city_id' => ['required', 'exists:cities,id'],
            'township_id' => ['required', 'exists:townships,id'],
            'postal_code' => ['nullable', 'string', 'max:20'],
            'is_default' => ['boolean'],
            'notes' => ['nullable', 'string'],
        ]);

        $township = Township::find($validated['township_id']);
        if (!$township || (int) $township->city_id !== (int) $validated['city_id']) {
            return back()->withErrors(['township_id' => 'The selected township is not valid for the chosen city.'])->withInput();
        }
        $validated['postal_code'] = $township->postal_code ?? $validated['postal_code'];

        if (!empty($validated['is_default'])) {
            $user->addresses()->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return redirect()->route('storefront.customer.addresses', ['store_slug' => $tenant->slug])
            ->with('success', 'Address updated successfully.');
    }

    public function destroyAddress(Request $request, $storeSlug, CustomerAddress $address): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ($address->user_id !== $user->id || $address->user_type !== $user->getMorphClass()) {
            abort(404);
        }

        $address->delete();

        return redirect()->route('storefront.customer.addresses', ['store_slug' => $tenant->slug])
            ->with('success', 'Address deleted successfully.');
    }

    public function setDefaultAddress(Request $request, $storeSlug, CustomerAddress $address): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ($address->user_id !== $user->id || $address->user_type !== $user->getMorphClass()) {
            abort(404);
        }

        $user->addresses()->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return redirect()->route('storefront.customer.addresses', ['store_slug' => $tenant->slug])
            ->with('success', 'Default address updated.');
    }

    public function cancelOrder(Request $request, $storeSlug, Order $order): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ($order->user_id !== $user->id || $order->user_type !== $user->getMorphClass()) {
            abort(404);
        }

        if (!$order->canCancel()) {
            return redirect()->back()->with('error', 'You cannot cancel this order.');
        }

        $oldStatus = $order->order_status;
        $order->update(['order_status' => Order::ORDER_STATUS_CANCELLED]);

        $this->orderService->reverseStockReduction($order);

        ProcessOrderStatusChange::dispatch($order, 'cancelled_by_customer', oldStatus: $oldStatus);

        return redirect()->route('storefront.customer.orders.show', [
            'store_slug' => $tenant->slug,
            'order' => $order->id,
        ])->with('success', 'Order cancelled. Stock has been restored.');
    }

    public function uploadPayment(Request $request, $storeSlug, Order $order): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ($order->user_id !== $user->id || $order->user_type !== $user->getMorphClass()) {
            abort(404);
        }

        $request->validate([
            'payment_proof' => 'required|image|mimes:jpg,jpeg,png,webp|max:2048',
            'transaction_id' => 'nullable|string|max:100',
        ]);

        if ($order->payment_status !== Order::PAYMENT_STATUS_PENDING) {
            return redirect()->back()->with('error', 'You cannot upload payment proof for this order.');
        }

        if ($request->hasFile('payment_proof')) {
            $path = $this->imageService->upload($request->file('payment_proof'), 'payment-proofs');

            $order->update([
                'payment_proof' => $path,
                'payment_status' => Order::PAYMENT_STATUS_PAID,
                'transaction_id' => $request->transaction_id,
            ]);

            ProcessOrderStatusChange::dispatch($order, 'payment_proof_uploaded');
        }

        return redirect()->route('storefront.customer.orders.show', [
            'store_slug' => $tenant->slug,
            'order' => $order->id,
        ])->with('success', 'Payment proof uploaded successfully.');
    }

    public function downloadInvoice(Request $request, $storeSlug, string $invoice_number)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        $order = Order::where('invoice_number', $invoice_number)
            ->where('user_id', $user->id)
            ->where('user_type', $user->getMorphClass())
            ->firstOrFail();

        $order->loadMissing(['items.product', 'items.variant', 'paymentMethod', 'city', 'township']);

        $html = view('invoices.customer-order', [
            'order' => $order,
            'tenant' => $tenant,
        ])->render();

        return response($html)
            ->header('Content-Type', 'text/html')
            ->header('Content-Disposition', 'inline; filename="' . $order->invoice_number . '.html"');
    }
}
