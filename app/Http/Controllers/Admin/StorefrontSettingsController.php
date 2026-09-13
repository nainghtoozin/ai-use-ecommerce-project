<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStorefrontConfigurationRequest;
use App\Models\City;
use App\Models\CodRule;
use App\Models\DeliveryService;
use App\Models\PackagingOption;
use App\Models\Storefront;
use App\Models\StorefrontCheckoutConfig;
use App\Models\StorefrontContent;
use App\Models\StorefrontDesignToken;
use App\Models\StorefrontMedia;
use App\Models\StorefrontThemeConfig;
use App\Models\Theme;
use App\Models\WebsiteInfo;
use App\Services\ImageService;
use App\Services\StorefrontConfigurationResolver;
use App\Services\StorefrontRevisionService;
use Illuminate\Support\Facades\DB;
use Inertia\Inertia;

class StorefrontSettingsController extends Controller
{
    public function __construct(
        private readonly StorefrontConfigurationResolver $resolver,
        private readonly ImageService $imageService,
        private readonly StorefrontRevisionService $revisionService,
    ) {}

    public function index()
    {
        $storefront = $this->storefront();
        $this->ensureSections($storefront);
        $contract = $this->resolver->resolve(null, 'draft');

        return Inertia::render('Admin/Storefront/Index', [
            'storefront' => $contract,
            'themes' => Theme::where('is_active', true)->orderBy('name')->get(['id', 'slug', 'name', 'version', 'default_tokens']),
            'media' => StorefrontMedia::where('storefront_id', $storefront->id)
                ->orderByDesc('created_at')->get(['id', 'key', 'path', 'original_name', 'mime_type', 'size', 'alt_text', 'is_visible'])->append('url'),
            'revision' => $this->revisionService->status($storefront),
            'heroVariants' => \App\Services\StorefrontConfigurationResolver::heroVariants(),
        ]);
    }

    public function update(UpdateStorefrontConfigurationRequest $request)
    {
        $storefront = $this->storefront();
        $validated = $request->validated();
        $this->revisionService->prepareDraft($storefront);

        $theme = Theme::where('is_active', true)->findOrFail($validated['theme_id']);
        $previousThemeId = $storefront->theme_id;

        DB::transaction(function () use ($storefront, $validated, $theme, $previousThemeId) {
            $this->updateIdentity($validated);

            $storefront->update(['theme_id' => $theme->id, 'status' => 'active']);
            StorefrontThemeConfig::withoutTenantScope()->updateOrCreate(
                ['storefront_id' => $storefront->id],
                [
                    'tenant_id' => tenant()->id,
                    'theme_id' => $theme->id,
                ],
            );

            $themeChanged = (int) $previousThemeId !== (int) $theme->id;
            $currentDesign = $this->resolver->resolve(null, 'draft')['design'];
            $design = ($validated['reset_tokens'] ?? false) || $themeChanged
                ? ($theme->default_tokens ?: $currentDesign)
                : array_replace_recursive($currentDesign, $validated['tokens'] ?? []);
            StorefrontDesignToken::withoutTenantScope()->updateOrCreate(
                ['storefront_id' => $storefront->id],
                [
                    'tenant_id' => tenant()->id,
                    'tokens' => $design,
                ],
            );

            $this->updateLabels($storefront, $validated['labels'] ?? []);
            $this->updateCheckoutConfig($storefront, $validated['checkout'] ?? []);
        });
        $this->revisionService->syncDraft($storefront);

        return back()->with('success', 'Draft saved. Publish to make changes visible to customers.');
    }

    private function updateIdentity(array $validated): void
    {
        $info = WebsiteInfo::firstWhere('tenant_id', tenant()->id);
        if (!$info) {
            $info = new WebsiteInfo(['tenant_id' => tenant()->id]);
        }

        $identityFields = array_intersect_key($validated, array_flip(['site_name']));
        if ($identityFields) {
            $info->fill($identityFields);
        }

        $info->save();
        WebsiteInfo::clearCache();
    }

    private function updateLabels(Storefront $storefront, array $labels): void
    {
        if (!$labels) {
            return;
        }

        $content = StorefrontContent::withoutTenantScope()->firstOrNew(['storefront_id' => $storefront->id]);
        $content->tenant_id = tenant()->id;
        $content->labels = array_replace($content->labels ?? [], $labels);
        $content->save();
    }

    public function checkout()
    {
        $storefront = $this->storefront();
        $this->ensureSections($storefront);
        $contract = $this->resolver->resolve(null, 'draft');

        return Inertia::render('Admin/Storefront/Checkout', [
            'storefront' => $contract,
            'deliveryServices' => DeliveryService::orderBy('sort_order')->orderBy('name')->get(),
            'packagingOptions' => PackagingOption::orderBy('sort_order')->orderBy('name')->get(),
            'codRules' => CodRule::all(),
            'cities' => City::where('is_active', true)->orderBy('name')->get(),
            'revision' => $this->revisionService->status($storefront),
        ]);
    }

    public function updateCheckout(\Illuminate\Http\Request $request)
    {
        $storefront = $this->storefront();
        $validated = $request->validate([
            'checkout' => 'required|array',
            'checkout.title' => 'nullable|string|max:255',
            'checkout.subtitle' => 'nullable|string|max:255',
            'checkout.show_branding' => 'nullable|boolean',
            'checkout.sections' => 'nullable|array',
            'checkout.sections.*.visible' => 'nullable|boolean',
            'checkout.sections.*.title' => 'nullable|string|max:255',
            'checkout.sections.*.order' => 'nullable|integer|min:1',
            'checkout.sections.*.desktop_visible' => 'nullable|boolean',
            'checkout.sections.*.mobile_visible' => 'nullable|boolean',
            'checkout.button_labels' => 'nullable|array',
            'checkout.button_labels.*' => 'nullable|string|max:255',
            'checkout.messages' => 'nullable|array',
            'checkout.messages.*' => 'nullable|string|max:1000',
            'checkout.appearance' => 'nullable|array',
            'checkout.appearance.card_style' => 'nullable|string',
            'checkout.appearance.section_spacing' => 'nullable|string',
            'checkout.appearance.compact_mode' => 'nullable|boolean',
            'checkout.appearance.border_radius' => 'nullable|string',
            'checkout.appearance.button_style' => 'nullable|string',
            'checkout.layout' => 'nullable|array',
            'checkout.layout.order_summary_position' => 'nullable|string',
            'checkout.layout.show_order_summary_on_mobile' => 'nullable|boolean',
            'checkout.visual' => 'nullable|array',
            'checkout.visual.preset' => 'nullable|string',
            'checkout.visual.show_store_logo' => 'nullable|boolean',
            'checkout.visual.order_summary_style' => 'nullable|string',
        ]);

        $this->revisionService->prepareDraft($storefront);
        $this->updateCheckoutConfig($storefront, $validated['checkout'] ?? []);
        $this->revisionService->syncDraft($storefront);

        return back()->with('success', 'Checkout settings saved to draft.');
    }

    private function updateCheckoutConfig(Storefront $storefront, array $config): void
    {
        if (!$config) {
            return;
        }

        $defaults = StorefrontCheckoutConfig::getDefaults();
        $merged = array_replace_recursive($defaults, $config);

        StorefrontCheckoutConfig::withoutTenantScope()->updateOrCreate(
            ['storefront_id' => $storefront->id],
            [
                'tenant_id' => tenant()->id,
                'configuration' => $merged,
            ],
        );
    }

    private function ensureSections(Storefront $storefront): void
    {
        $this->resolver->ensureHomepageSections($storefront);
    }

    private function storefront(): Storefront
    {
        $tenant = tenant();
        abort_unless($tenant, 404);

        $storefront = Storefront::first();
        if (!$storefront) {
            $storefront = $this->resolver->provision($tenant);
        }

        abort_unless($storefront, 404);

        if ((int) $storefront->tenant_id !== (int) $tenant->id) {
            abort(403);
        }

        return $storefront;
    }
}
