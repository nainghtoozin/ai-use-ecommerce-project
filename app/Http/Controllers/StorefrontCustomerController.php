<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOrderNotifications;
use App\Jobs\ProcessOrderStatusChange;
use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\Tenant;
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

        if ($user->tenant_id !== null && $user->tenant_id !== $tenant->id) {
            abort(403, 'Unauthorized access to this store.');
        }

        // Auto-assign tenant_id for legacy users
        if ($user->tenant_id === null) {
            $user->update(['tenant_id' => $tenant->id]);
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
            'total' => Order::where('user_id', $user->id)->count(),
            'pending' => Order::where('user_id', $user->id)->where('order_status', Order::ORDER_STATUS_PENDING)->count(),
            'processing' => Order::where('user_id', $user->id)->where('order_status', Order::ORDER_STATUS_PROCESSING)->count(),
            'shipped' => Order::where('user_id', $user->id)->where('order_status', Order::ORDER_STATUS_SHIPPED)->count(),
            'delivered' => Order::where('user_id', $user->id)->where('order_status', Order::ORDER_STATUS_DELIVERED)->count(),
            'cancelled' => Order::where('user_id', $user->id)->where('order_status', Order::ORDER_STATUS_CANCELLED)->count(),
        ];

        $addressesCount = CustomerAddress::forUser($user->id)->count();

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

        $orders = Order::with(['items.product', 'items.variant', 'paymentMethod'])
            ->where('user_id', $user->id)
            ->orderBy('created_at', 'desc')
            ->simplePaginate(10);

        return Inertia::render('Storefront/Orders', [
            'tenant' => $this->tenantData($tenant),
            'orders' => $orders,
        ]);
    }

    public function showOrder(Request $request, $storeSlug, Order $order)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ($order->user_id !== $user->id) {
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

        $addresses = CustomerAddress::with(['city', 'township'])
            ->forUser($user->id)
            ->orderBy('is_default', 'desc')
            ->orderBy('created_at', 'desc')
            ->get();

        $cities = City::orderBy('name')->get(['id', 'name']);

        return Inertia::render('Storefront/Addresses', [
            'tenant' => $this->tenantData($tenant),
            'addresses' => $addresses,
            'cities' => $cities,
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

        if (!empty($validated['is_default'])) {
            CustomerAddress::forUser($user->id)->update(['is_default' => false]);
        }

        $validated['user_id'] = $user->id;

        CustomerAddress::create($validated);

        return redirect()->route('storefront.customer.addresses', ['store_slug' => $tenant->slug])
            ->with('success', 'Address added successfully.');
    }

    public function updateAddress(Request $request, $storeSlug, CustomerAddress $address): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ($address->user_id !== $user->id) {
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

        if (!empty($validated['is_default'])) {
            CustomerAddress::forUser($user->id)->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return redirect()->route('storefront.customer.addresses', ['store_slug' => $tenant->slug])
            ->with('success', 'Address updated successfully.');
    }

    public function destroyAddress(Request $request, $storeSlug, CustomerAddress $address): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ($address->user_id !== $user->id) {
            abort(404);
        }

        $address->delete();

        return redirect()->route('storefront.customer.addresses', ['store_slug' => $tenant->slug])
            ->with('success', 'Address deleted successfully.');
    }

    public function cancelOrder(Request $request, $storeSlug, Order $order): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ($order->user_id !== $user->id) {
            abort(404);
        }

        if (!$order->canCancel()) {
            return redirect()->back()->with('error', 'You cannot cancel this order.');
        }

        $oldStatus = $order->order_status;
        $order->update(['order_status' => Order::ORDER_STATUS_CANCELLED]);
        $this->orderService->restoreStock($order);

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

        if ($order->user_id !== $user->id) {
            abort(404);
        }

        $request->validate([
            'payment_proof' => 'required|image|max:2048',
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
}
