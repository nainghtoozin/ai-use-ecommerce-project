<?php

namespace App\Http\Controllers;

use App\Models\Tenant;
use App\Services\BuyNowService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class BuyNowController extends Controller
{
    public function __construct(
        private readonly BuyNowService $buyNowService,
    ) {}

    public function store(Request $request): RedirectResponse
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            abort(404);
        }

        $validated = $request->validate([
            'product_id' => ['required', 'integer', 'min:1'],
            'variant_id' => ['nullable', 'integer', 'min:1'],
            'quantity' => ['required', 'integer', 'min:1'],
        ]);

        $this->buyNowService->start(
            $tenant,
            (int) $validated['product_id'],
            isset($validated['variant_id']) ? (int) $validated['variant_id'] : null,
            (int) $validated['quantity'],
        );

        return redirect()->route('storefront.checkout', $tenant->slug);
    }
}
