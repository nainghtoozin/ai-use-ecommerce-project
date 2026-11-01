<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Spatie\Permission\Models\Permission;
use App\Models\Tenant;
use App\Services\MerchantStoreResetException;
use App\Services\MerchantStoreResetService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class MerchantStoreResetController extends Controller
{
    private const RESULT_SESSION_KEY = 'merchant_store_reset_result';

    public function __construct(
        private readonly MerchantStoreResetService $resetService
    ) {}

    public function index(Request $request)
    {
        $tenant = $this->authorizeReset();

        $result = $request->session()->get(self::RESULT_SESSION_KEY);

        return Inertia::render('Admin/StoreReset/Index', [
            'storeName' => $tenant->name,
            'storeSlug' => $tenant->slug,
            'result' => $result,
        ]);
    }

    public function reset(Request $request)
    {
        return $this->handleReset($request);
    }

    public function execute(Request $request)
    {
        return $this->handleReset($request);
    }

    public function perform(Request $request)
    {
        return $this->handleReset($request);
    }

    public function destroy(Request $request)
    {
        return $this->handleReset($request);
    }

    public function store(Request $request)
    {
        return $this->handleReset($request);
    }

    public function update(Request $request)
    {
        return $this->handleReset($request);
    }

    public function edit(Request $request)
    {
        return $this->index($request);
    }

    private function handleReset(Request $request)
    {
        $tenant = $this->authorizeReset();

        $user = auth()->user();

        $confirmation = $request->input('confirmation');
        if ($confirmation === null) {
            foreach (['store_name', 'store_slug', 'confirm', 'confirmation_text', 'name', 'slug'] as $key) {
                if ($request->filled($key)) {
                    $confirmation = $request->input($key);
                    break;
                }
            }
        }
        $confirmation = is_string($confirmation) ? $confirmation : (string) $confirmation;

        $finalConfirmation = false;
        foreach (['accept', 'final_confirmation', 'confirm_reset', 'confirm_final', 'agree', 'confirmed'] as $key) {
            $value = $request->input($key);
            if ($value === true || $value === 1 || $value === '1' || $value === 'true' || $value === 'on' || $value === 'yes') {
                $finalConfirmation = true;
                break;
            }
        }

        $password = $request->input('password');
        $password = is_string($password) ? $password : null;

        try {
            $result = $this->resetService->reset($tenant, $user, $confirmation, $finalConfirmation, $password);
        } catch (MerchantStoreResetException $exception) {
            Log::warning('Merchant store reset rejected', [
                'tenant_id' => $tenant->id,
                'user_id' => $user?->getAuthIdentifier(),
                'reason' => $exception->reason(),
            ]);

            return back()->withErrors(['reset' => $exception->getMessage()])
                ->with('error', $exception->getMessage());
        }

        $request->session()->put(self::RESULT_SESSION_KEY, $result);

        return admin_redirect('admin.store-reset.index')
            ->with('success', 'Store data reset completed successfully.');
    }

    private function authorizeReset(): Tenant
    {
        $user = auth()->user();

        if (!$user) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if (!$tenant) {
            abort(403, 'Store not found.');
        }

        // Only the actual owner may reset.
        if (!$tenant->isOwnedBy($user)) {
            // Allow non-owner only if a dedicated permission exists AND user holds it.
            // (Still blocked by ownership above, so non-owners always 403.)
            abort(403, 'Only the store owner can reset this store.');
        }

        return $tenant;
    }

    private function routeName(string $action): string
    {
        // Prefer the route name that exists
        $candidates = [
            'admin.store-reset.' . $action,
            'admin.store-reset.index',
            'admin.store-data-reset.' . $action,
            'admin.reset.' . $action,
        ];

        foreach ($candidates as $name) {
            if (\Illuminate\Support\Facades\Route::has($name)) {
                return $name;
            }
        }

        return 'admin.store-reset.index';
    }
}
