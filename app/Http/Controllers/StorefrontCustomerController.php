<?php

namespace App\Http\Controllers;

use App\Jobs\ProcessOrderStatusChange;
use App\Models\City;
use App\Models\CustomerAddress;
use App\Models\Order;
use App\Models\OrderCancellation;
use App\Models\OrderReturn;
use App\Models\Tenant;
use App\Services\CustomerOrderInvoicePdfService;
use App\Services\AfterSalesHistoryService;
use App\Services\AuthorizationService;
use App\Services\ImageService;
use App\Services\OrderCancellationService;
use App\Services\OrderReturnService;
use App\Services\OrderWorkflow;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Redirect;
use Illuminate\Validation\Rules;
use Inertia\Inertia;

class StorefrontCustomerController extends Controller
{
    public function __construct(
        private readonly ImageService $imageService,
        private readonly OrderCancellationService $cancellationService,
        private readonly OrderReturnService $returnService,
        private readonly AfterSalesHistoryService $historyService,
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

    public function emailVerified(Request $request, $store_slug = null)
    {
        $storeSlug = $store_slug ?: $request->route('store_slug');
        $tenant = Tenant::where('slug', $storeSlug)->firstOrFail();

        return Inertia::render('Storefront/EmailVerified', [
            'tenant' => $this->tenantData($tenant),
            'storeUrl' => url('/store/' . $tenant->slug),
        ]);
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

        $recentOrders = $user->orders()
            ->orderByDesc('created_at')
            ->limit(5)
            ->get(['id', 'invoice_number', 'order_status', 'total_amount', 'created_at']);

        return Inertia::render('Storefront/Account', [
            'tenant' => $this->tenantData($tenant),
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'profile_image_url' => $user->profile_image_url,
                'member_since' => $user->created_at,
                'addresses_count' => $addressesCount,
                'notification_preferences' => $user->notification_preferences ?? [],
                'email_verified' => $user->hasVerifiedEmail(),
                'last_login_at' => $user->last_login_at?->toISOString(),
                'has_orders' => $user->orders()->exists(),
                'locale' => $user->locale ?? 'en',
            ],
            'orderStats' => $orderStats,
            'recentOrders' => $recentOrders,
            'mustVerifyEmail' => $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail(),
            'status' => session('status'),
            'defaultTab' => $request->query('tab', 'dashboard'),
        ]);
    }

    public function profile(Request $request, $storeSlug = null)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        return Inertia::render('Storefront/Profile', [
            'tenant' => $this->tenantData($tenant),
            'customer' => [
                'id' => $user->id,
                'name' => $user->name,
                'email' => $user->email,
                'phone' => $user->phone ?? '',
                'profile_image_url' => $user->profile_image_url,
            ],
            'mustVerifyEmail' => $user instanceof \Illuminate\Contracts\Auth\MustVerifyEmail && !$user->hasVerifiedEmail(),
            'status' => session('status'),
        ]);
    }

    public function updateProfile(Request $request, $storeSlug = null)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'email' => ['required', 'email', 'max:255'],
            'phone' => ['nullable', 'string', 'max:50'],
            'profile_image' => ['nullable', 'image', 'mimes:jpeg,png,jpg,gif,webp', 'max:2048'],
        ]);

        $user->fill([
            'name' => $validated['name'],
            'email' => $validated['email'],
            'phone' => $validated['phone'] ?? $user->phone,
        ]);

        if ($request->hasFile('profile_image')) {
            $imageService = app(ImageService::class);
            $imagePath = $imageService->upload($request->file('profile_image'), 'profiles');
            if ($user->profile_image) {
                $imageService->delete($user->profile_image);
            }
            $user->profile_image = $imagePath;
        }

        if ($user->isDirty('email')) {
            $user->email_verified_at = null;
        }

        $user->save();

        return Redirect::route('storefront.customer.account', ['store_slug' => $tenant->slug, 'tab' => 'profile'])
            ->with('status', 'profile-updated');
    }

    public function updatePassword(Request $request, $storeSlug = null)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        $validated = $request->validate([
            'current_password' => ['required', 'current_password'],
            'password' => ['required', 'confirmed', Rules\Password::defaults()],
        ]);

        $user->update([
            'password' => Hash::make($validated['password']),
        ]);

        $guard = config('identity.use_accounts') ? 'accounts' : 'web';
        Auth::guard($guard)->logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        // The slug is resolved from the membership-checked tenant context,
        // never from the optional controller parameter or client input.
        // Customers land on their own store's login page (not the merchant login).
        return Redirect::route('storefront.login', ['store_slug' => $tenant->slug])
            ->with('status', 'Password changed. Please log in with your new password.');
    }

    public function deleteAccount(Request $request, $storeSlug = null)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        $validated = $request->validate([
            'password' => ['required', 'current_password'],
        ]);

        $hasOrders = $user->orders()->exists();

        \DB::transaction(function () use ($user, $hasOrders) {
            if ($hasOrders) {
                $anonymizedEmail = 'deleted_' . $user->id . '_' . time() . '@anonymized.local';
                $user->update([
                    'name' => 'Deleted User',
                    'email' => $anonymizedEmail,
                    'phone' => null,
                    'profile_image' => null,
                    'password' => Hash::make(bin2hex(random_bytes(16))),
                    'email_verified_at' => null,
                    'status' => 'deleted',
                ]);

                $user->addresses()->update([
                    'first_name' => 'Deleted',
                    'last_name' => 'User',
                    'phone' => null,
                    'address_line' => 'Anonymized',
                ]);
            } else {
                $user->addresses()->delete();
                if ($user->profile_image) {
                    app(ImageService::class)->delete($user->profile_image);
                }
                $user->delete();
            }
        });

        \Auth::logout();
        $request->session()->invalidate();
        $request->session()->regenerateToken();

        return redirect('/')->with('success', 'Your account has been deleted successfully.');
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

        if ($request->payment_status === 'due_on_delivery') {
            $query->where('payment_status', Order::PAYMENT_STATUS_PENDING)
                ->where(function ($q) {
                    $q->whereNull('payment_method_id')
                        ->orWhereHas('paymentMethod', fn ($m) => $m->where('type', 'cod'));
                });
        } elseif ($request->filled('payment_status')) {
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

        $filters = $request->only(['search', 'order_status', 'payment_status', 'date_range', 'date_from', 'date_to', 'sort']);
        if (empty($filters)) {
            $filters = new \stdClass();
        }

        return Inertia::render('Storefront/Orders', [
            'tenant' => $this->tenantData($tenant),
            'orders' => $orders,
            'filters' => $filters,
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

        $cancellation = $this->cancellationService->evaluate($order, OrderCancellation::ROLE_CUSTOMER, $user);
        $return = $this->returnService->eligibility($order);

        return Inertia::render('Storefront/OrderShow', [
            'tenant' => $this->tenantData($tenant),
            'order' => $order,
            'isCodOrder' => app(OrderWorkflow::class)->isCod($order),
            'afterSales' => [
                'cancellation' => $cancellation,
                'return' => $this->returnPayload($return),
                'cancellations' => $order->cancellations()
                    ->latest()
                    ->get()
                    ->map(fn (OrderCancellation $case) => [
                        'id' => $case->id,
                        'number' => $case->cancellation_number,
                        'status' => $case->status,
                        'mode' => $case->mode,
                        'reason' => $case->reason_label,
                        'note' => $case->customer_note,
                        'rejection_reason' => $case->rejection_reason,
                        'requested_at' => $case->requested_at?->toIso8601String(),
                        'completed_at' => $case->completed_at?->toIso8601String(),
                        'refund' => $case->refund ? [
                            'number' => $case->refund->refund_number,
                            'amount' => (float) $case->refund->amount,
                            'status' => $case->refund->status,
                        ] : null,
                    ]),
                'returns' => $order->returns()
                    ->with(['items.orderItem'])
                    ->latest()
                    ->get()
                    ->map(fn (OrderReturn $case) => $this->returnCasePayload($case)),
                'refunds' => $order->refunds()
                    ->latest()
                    ->get()
                    ->map(fn ($refund) => [
                        'number' => $refund->refund_number,
                        'amount' => (float) $refund->amount,
                        'status' => $refund->status,
                        'method' => $refund->method,
                        'reference' => $refund->reference,
                        'processed_at' => $refund->processed_at?->toIso8601String(),
                    ]),
            ],
        ]);
    }

    private function returnPayload(array $eligibility): array
    {
        $settings = $eligibility['settings'] ?? null;

        return [
            'allowed' => $eligibility['allowed'],
            'reason' => $eligibility['reason'],
            'window_days' => $eligibility['window_days'],
            'delivered_at' => $eligibility['delivered_at'],
            'days_remaining' => $eligibility['days_remaining'],
            'items' => $eligibility['items'],
            'settings' => $settings ? [
                'return_window_days' => $settings->return_window_days,
                'allow_full_return' => $settings->allow_full_return,
                'allow_partial_return' => $settings->allow_partial_return,
                'require_reason' => $settings->require_reason,
                'require_note' => $settings->require_note,
                'require_evidence' => $settings->require_evidence,
                'allow_refund' => $settings->allow_refund,
                'allow_exchange' => $settings->allow_exchange,
                'allow_replacement' => $settings->allow_replacement,
                'return_shipping_responsibility' => $settings->return_shipping_responsibility,
                'refund_shipping_fee' => $settings->refund_shipping_fee,
                'reasons' => $settings->reasons ?: \App\Models\ReturnSetting::DEFAULT_REASONS,
                'instructions' => $settings->instructions,
                'policy_text' => $settings->policy_text,
            ] : null,
        ];
    }

    private function returnCasePayload(OrderReturn $case): array
    {
        return [
            'id' => $case->id,
            'number' => $case->rma_number,
            'status' => $case->status,
            'resolution' => $case->resolution,
            'reason' => $case->reason_label,
            'note' => $case->customer_note,
            'rejection_reason' => $case->rejection_reason,
            'shipping_responsibility' => $case->shipping_responsibility,
            'requested_at' => $case->requested_at?->toIso8601String(),
            'received_at' => $case->received_at?->toIso8601String(),
            'inspected_at' => $case->inspected_at?->toIso8601String(),
            'completed_at' => $case->completed_at?->toIso8601String(),
            'expires_at' => $case->expires_at?->toIso8601String(),
            'order' => $case->relationLoaded('order') && $case->order ? [
                'id' => $case->order->id,
                'invoice_number' => $case->order->invoice_number,
            ] : null,
            'items' => $case->items->map(fn ($item) => [
                'order_item_id' => $item->order_item_id,
                'quantity' => $item->quantity,
                'received_quantity' => $item->received_quantity,
                'condition' => $item->condition,
                'product_name' => $item->relationLoaded('orderItem') ? $item->orderItem?->product?->name : null,
            ]),
            'evidence' => $case->evidence_urls,
            'tracking' => $case->trackingPayload(),
            'refund' => $case->refund ? [
                'number' => $case->refund->refund_number,
                'amount' => (float) $case->refund->amount,
                'status' => $case->refund->status,
            ] : null,
        ];
    }

    public function addresses(Request $request, $storeSlug = null)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        $addresses = $user->addresses()
            ->where('tenant_id', $tenant->id)
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

        $location = app(\App\Services\LocationService::class)->resolveTenantLocation(
            (int) $validated['city_id'],
            (int) $validated['township_id'],
            $tenant
        );
        $township = $location['township'];
        $validated['city_id'] = $location['city']?->id ?? $validated['city_id'];
        $validated['postal_code'] = $township->postal_code ?? ($validated['postal_code'] ?? null);

        if (!empty($validated['is_default'])) {
            $user->addresses()->where('tenant_id', $tenant->id)->update(['is_default' => false]);
        }

        $user->addresses()->create($validated);

        return redirect()->route('storefront.customer.addresses', ['store_slug' => $tenant->slug])
            ->with('success', 'Address added successfully.');
    }

    public function updateAddress(Request $request, $storeSlug, CustomerAddress $address): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ($address->user_id !== $user->id || $address->user_type !== $user->getMorphClass() || (int) $address->tenant_id !== (int) $tenant->id) {
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

        $location = app(\App\Services\LocationService::class)->resolveTenantLocation(
            (int) $validated['city_id'],
            (int) $validated['township_id'],
            $tenant
        );
        $township = $location['township'];
        $validated['city_id'] = $location['city']?->id ?? $validated['city_id'];
        $validated['postal_code'] = $township->postal_code ?? ($validated['postal_code'] ?? null);

        if (!empty($validated['is_default'])) {
            $user->addresses()->where('tenant_id', $tenant->id)->where('id', '!=', $address->id)->update(['is_default' => false]);
        }

        $address->update($validated);

        return redirect()->route('storefront.customer.addresses', ['store_slug' => $tenant->slug])
            ->with('success', 'Address updated successfully.');
    }

    public function destroyAddress(Request $request, $storeSlug, CustomerAddress $address): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ($address->user_id !== $user->id || $address->user_type !== $user->getMorphClass() || (int) $address->tenant_id !== (int) $tenant->id) {
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

        if ($address->user_id !== $user->id || $address->user_type !== $user->getMorphClass() || (int) $address->tenant_id !== (int) $tenant->id) {
            abort(404);
        }

        $user->addresses()->where('tenant_id', $tenant->id)->update(['is_default' => false]);
        $address->update(['is_default' => true]);

        return redirect()->route('storefront.customer.addresses', ['store_slug' => $tenant->slug])
            ->with('success', 'Default address updated.');
    }

    public function cancelOrder(Request $request, $storeSlug, Order $order): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        $this->ensureOrderOwnership($order, $user);

        $validated = $request->validate([
            'reason_code' => 'nullable|string|max:50',
            'reason_label' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:1000',
        ]);

        try {
            $this->cancellationService->request($order, $user, OrderCancellation::ROLE_CUSTOMER, [
                'reason_code' => $validated['reason_code'] ?? null,
                'reason_label' => $validated['reason_label'] ?? null,
                'note' => $validated['note'] ?? null,
            ]);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        $fresh = $order->fresh();

        $message = $fresh?->order_status === Order::ORDER_STATUS_CANCELLED
            ? 'Order cancelled. Stock has been restored.'
            : 'Cancellation request submitted. The store will review it shortly.';

        return redirect()->route('storefront.customer.orders.show', [
            'store_slug' => $tenant->slug,
            'order' => $order->id,
        ])->with('success', $message);
    }

    public function returns(Request $request, $storeSlug = null)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        $this->ensureReturnsAccess($user);

        $returns = OrderReturn::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where('requested_by_type', $user->getMorphClass())
            ->where('requested_by_id', $user->id)
            ->with(['order:id,invoice_number,total_amount,order_status,payment_status', 'items', 'refund:id,refund_number,amount,status'])
            ->latest()
            ->paginate(10)
            ->withQueryString()
            ->through(fn (OrderReturn $case) => $this->returnCasePayload($case));

        return Inertia::render('Storefront/Returns', [
            'tenant' => $this->tenantData($tenant),
            'returns' => $returns,
        ]);
    }

    public function requestReturn(Request $request, $storeSlug, Order $order): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        $this->ensureOrderOwnership($order, $user);

        $validated = $request->validate([
            'resolution' => 'required|in:refund,exchange,replacement',
            'reason_code' => 'nullable|string|max:50',
            'reason_label' => 'nullable|string|max:255',
            'note' => 'nullable|string|max:2000',
            'items' => 'required|array|min:1',
            'items.*.order_item_id' => 'required|integer',
            'items.*.quantity' => 'required|integer|min:1',
            'evidence' => 'nullable|array|max:6',
            'evidence.*' => 'image|mimes:jpg,jpeg,png,webp|max:4096',
        ]);

        $evidencePaths = [];

        foreach ($request->file('evidence', []) as $file) {
            $evidencePaths[] = $this->imageService->upload($file, 'return-evidence');
        }

        try {
            $this->returnService->request($order, $user, [
                'resolution' => $validated['resolution'],
                'reason_code' => $validated['reason_code'] ?? null,
                'reason_label' => $validated['reason_label'] ?? null,
                'note' => $validated['note'] ?? null,
                'items' => $validated['items'],
                'evidence' => $evidencePaths,
            ]);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->route('storefront.customer.orders.show', [
            'store_slug' => $tenant->slug,
            'order' => $order->id,
        ])->with('success', 'Return request submitted.');
    }

    public function showReturn(Request $request, $storeSlug, OrderReturn $return)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ((int) $return->tenant_id !== (int) $tenant->id
            || $return->requested_by_type !== $user->getMorphClass()
            || (int) $return->requested_by_id !== (int) $user->id) {
            abort(404);
        }

        $this->ensureReturnsAccess($user);

        $return->load(['order:id,invoice_number,total_amount,order_status,payment_status', 'items.orderItem.product', 'refund']);

        return Inertia::render('Storefront/ReturnShow', [
            'tenant' => $this->tenantData($tenant),
            'returnRequest' => $this->returnCasePayload($return),
            'timeline' => $this->historyService->timeline($return, true),
        ]);
    }

    public function cancelReturn(Request $request, $storeSlug, OrderReturn $return): RedirectResponse
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        if ((int) $return->tenant_id !== (int) $tenant->id
            || $return->requested_by_type !== $user->getMorphClass()
            || (int) $return->requested_by_id !== (int) $user->id) {
            abort(404);
        }

        $this->ensureReturnsAccess($user);

        try {
            $this->returnService->cancel($return, $user, OrderCancellation::ROLE_CUSTOMER);
        } catch (\InvalidArgumentException $e) {
            return redirect()->back()->with('error', $e->getMessage());
        }

        return redirect()->back()->with('success', 'Return request cancelled.');
    }

    private function ensureOrderOwnership(Order $order, $user): void
    {
        if ($order->user_id !== $user->id || $order->user_type !== $user->getMorphClass()) {
            abort(404);
        }
    }

    private function ensureReturnsAccess($user): void
    {
        if (AuthorizationService::can('returns.view-own', $user) || AuthorizationService::can('returns.create', $user)) {
            return;
        }

        if ($user->getRoleNames()->isEmpty()) {
            return;
        }

        abort(403, 'Unauthorized');
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

        if (app(OrderWorkflow::class)->isCod($order)) {
            return redirect()->back()->with('error', 'Cash on Delivery orders do not require payment proof.');
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

    public function downloadInvoice(Request $request, $storeSlug, string $invoice_number, CustomerOrderInvoicePdfService $invoicePdf)
    {
        $tenant = $this->ensureTenantAccess($request);
        $user = $request->user();

        $order = Order::where('invoice_number', $invoice_number)
            ->where('user_id', $user->id)
            ->where('user_type', $user->getMorphClass())
            ->where('tenant_id', $tenant->id)
            ->firstOrFail();

        $order->loadMissing(['items.product', 'items.variant', 'paymentMethod', 'city', 'township']);

        return $invoicePdf->generate($order, $tenant, false);
    }
}
