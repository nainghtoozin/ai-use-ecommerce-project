<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\UpdateStorefrontConfigurationRequest;
use App\Models\Storefront;
use App\Models\StorefrontContent;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use App\Models\StorefrontDesignToken;
use App\Models\StorefrontHomepageSection;
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

        DB::transaction(function () use ($request, $storefront, $validated, $theme, $previousThemeId) {
            $this->updateIdentity($validated);

            $storefront->update(['theme_id' => $theme->id, 'status' => 'active']);
            $heroVariant = StorefrontConfigurationResolver::normalizeHeroVariant($validated['hero']['variant'] ?? null);
            StorefrontThemeConfig::withoutTenantScope()->updateOrCreate(
                ['storefront_id' => $storefront->id],
                [
                    'tenant_id' => tenant()->id,
                    'theme_id' => $theme->id,
                    'configuration' => ['hero_variant' => $heroVariant],
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

            $this->updateSections($storefront, $validated, $request);
            $this->updateLabels($storefront, $validated['labels'] ?? []);
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

    private function updateSections(Storefront $storefront, array $validated, UpdateStorefrontConfigurationRequest $request): void
    {
        $sections = $validated['homepage_sections'];
        $heroSection = null;

        foreach ($sections as $position => $sectionData) {
            $section = StorefrontHomepageSection::where('storefront_id', $storefront->id)
                ->find($sectionData['id']);

            // Tolerate section IDs captured in older revision snapshots whose
            // rows were since removed (e.g. normalized duplicates), but never
            // accept an ID that belongs to another storefront.
            if (!$section) {
                if (StorefrontHomepageSection::withoutTenantScope()->whereKey($sectionData['id'])->exists()) {
                    throw (new ModelNotFoundException)
                        ->setModel(StorefrontHomepageSection::class, [$sectionData['id']]);
                }
                continue;
            }

            $variant = $sectionData['variant'] ?? $section->variant;
            if ($section->type === 'hero') {
                $variant = StorefrontConfigurationResolver::normalizeHeroVariant($variant);
            }
            $section->update([
                'enabled' => (bool) $sectionData['enabled'],
                'desktop_visible' => (bool) $sectionData['desktop_visible'],
                'mobile_visible' => (bool) $sectionData['mobile_visible'],
                'position' => $position,
                'variant' => $variant,
            ]);

            if ($section->type === 'hero') {
                $heroSection = $section;
            }
        }

        if (!$heroSection) {
            return;
        }

        $hero = $validated['hero'] ?? [];
        $existingMediaIds = $heroSection->configuration['media_ids'] ?? [];

        if ($request->hasFile('hero_image')) {
            $files = $request->file('hero_image');
            if (!is_array($files)) {
                $files = [$files];
            }
            $files = array_slice($files, 0, 5);
            $newMediaIds = [];
            foreach ($files as $file) {
                $path = $this->imageService->upload($file, 'storefront-media');
                $media = StorefrontMedia::withoutTenantScope()->create([
                    'tenant_id' => tenant()->id,
                    'storefront_id' => $storefront->id,
                    'key' => 'hero',
                    'path' => $path,
                    'original_name' => $file->getClientOriginalName(),
                    'mime_type' => $file->getMimeType(),
                    'size' => $file->getSize(),
                    'alt_text' => $validated['hero_alt_text'] ?? ($hero['title'] ?? tenant()->name),
                ]);
                $newMediaIds[] = $media->id;
            }
            $hero['media_ids'] = array_slice(array_merge($existingMediaIds, $newMediaIds), 0, 5);
        } elseif (($validated['hero_remove_image'] ?? false) === true) {
            $hero['media_ids'] = [];
        } elseif (array_key_exists('media_ids', $hero)) {
            $hero['media_ids'] = StorefrontMedia::where('storefront_id', $storefront->id)
                ->whereIn('id', $hero['media_ids'])
                ->pluck('id')->values()->all();
        }

        unset($hero['variant']);
        $heroSection->update([
            'variant' => StorefrontConfigurationResolver::normalizeHeroVariant($validated['hero']['variant'] ?? $heroSection->variant),
            'configuration' => array_filter([
                'title' => $hero['title'] ?? null,
                'subtitle' => $hero['subtitle'] ?? null,
                'button_text' => $hero['button_text'] ?? null,
                'button_link' => $hero['button_link'] ?? null,
                'media_ids' => $hero['media_ids'] ?? null,
            ], static fn ($value) => $value !== null),
        ]);
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
