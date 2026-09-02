<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StorefrontMediaUploadRequest;
use App\Models\Storefront;
use App\Models\StorefrontHomepageSection;
use App\Models\StorefrontMedia;
use App\Models\PromotionBanner;
use App\Models\WebsiteInfo;
use App\Services\ImageService;
use App\Services\StorefrontConfigurationResolver;
use App\Services\StorefrontRevisionService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Inertia\Inertia;

class StorefrontMediaController extends Controller
{
    public function __construct(
        private readonly StorefrontConfigurationResolver $resolver,
        private readonly ImageService $imageService,
        private readonly StorefrontRevisionService $revisionService,
    ) {}

    public function index()
    {
        $storefront = $this->storefront();
        $search = request('search');
        $media = StorefrontMedia::where('storefront_id', $storefront->id)
            ->when($search, fn ($query) => $query->where(function ($q) use ($search) {
                $q->where('original_name', 'like', "%{$search}%")
                    ->orWhere('alt_text', 'like', "%{$search}%");
            }))
            ->latest()
            ->paginate(24)
            ->withQueryString();

        $hero = collect($this->resolver->resolve(null, 'draft')['homepage']['sections'])
            ->firstWhere('type', 'hero');

        return Inertia::render('Admin/Storefront/Media', [
            'media' => $media,
            'currentHeroMediaIds' => $hero['configuration']['media_ids'] ?? [],
            'search' => $search ?? '',
        ]);
    }

    public function store(StorefrontMediaUploadRequest $request): RedirectResponse
    {
        $storefront = $this->storefront();
        $file = $request->file('file');
        $path = $this->imageService->upload($file, 'storefront-media');

        $media = StorefrontMedia::create([
            'tenant_id' => tenant()->id,
            'storefront_id' => $storefront->id,
            'key' => 'library',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'alt_text' => $request->validated('alt_text') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'metadata' => ['width' => null, 'height' => null],
            'is_visible' => true,
        ]);
        $this->revisionService->syncDraft($storefront);

        return back()->with('success', 'Media uploaded successfully.');
    }

    public function uploadHeroImage(StorefrontMediaUploadRequest $request): JsonResponse
    {
        $storefront = $this->storefront();
        $file = $request->file('file');
        $path = $this->imageService->upload($file, 'storefront-media');

        $media = StorefrontMedia::create([
            'tenant_id' => tenant()->id,
            'storefront_id' => $storefront->id,
            'key' => 'hero',
            'path' => $path,
            'original_name' => $file->getClientOriginalName(),
            'mime_type' => $file->getMimeType(),
            'size' => $file->getSize(),
            'alt_text' => $request->input('alt_text') ?: pathinfo($file->getClientOriginalName(), PATHINFO_FILENAME),
            'metadata' => ['width' => null, 'height' => null],
            'is_visible' => true,
        ]);
        $this->revisionService->syncDraft($storefront);

        $media->append('url');

        return response()->json([
            'id' => $media->id,
            'url' => $media->url,
            'alt_text' => $media->alt_text,
        ]);
    }

    public function update(StorefrontMedia $media): RedirectResponse
    {
        $this->assertMediaBelongsToCurrentStorefront($media);
        $validated = request()->validate(['alt_text' => ['nullable', 'string', 'max:255']]);
        $media->update(['alt_text' => $validated['alt_text'] ?? null]);
        $this->revisionService->syncDraft($this->storefront());

        return back()->with('success', 'Media details updated.');
    }

    public function destroy(StorefrontMedia $media): RedirectResponse
    {
        $this->assertMediaBelongsToCurrentStorefront($media);
        $usage = $this->mediaUsage($media);

        if ($usage) {
            return back()->with('error', "This media is still used by {$usage}. Detach it before deleting.");
        }

        $deleted = $this->imageService->delete($media->path);
        if (!$deleted && ImageService::exists($media->path)) {
            return back()->with('error', 'The media file could not be removed. No database record was changed.');
        }

        $media->delete();
        $this->revisionService->syncDraft($this->storefront());

        return back()->with('success', 'Media removed successfully.');
    }

    public function assignHero(StorefrontMedia $media): RedirectResponse
    {
        $this->assertMediaBelongsToCurrentStorefront($media);
        $this->updateHeroMedia([$media->id]);

        return back()->with('success', 'Media assigned to the hero.');
    }

    public function detachHero(StorefrontMedia $media): RedirectResponse
    {
        $this->assertMediaBelongsToCurrentStorefront($media);
        $this->updateHeroMedia([], $media->id);

        return back()->with('success', 'Media detached from the hero.');
    }

    public function assignLogo(StorefrontMedia $media): RedirectResponse
    {
        $this->assertMediaBelongsToCurrentStorefront($media);
        $info = WebsiteInfo::firstWhere('tenant_id', tenant()->id);
        if (!$info) {
            $info = new WebsiteInfo(['tenant_id' => tenant()->id]);
        }
        $info->logo = $media->path;
        $info->save();
        WebsiteInfo::clearCache();
        $this->revisionService->syncDraft($this->storefront());

        return back()->with('success', 'Media assigned as the store logo.');
    }

    private function updateHeroMedia(array $mediaIds, ?int $detachId = null): void
    {
        $storefront = $this->storefront();
        $sections = StorefrontHomepageSection::where('storefront_id', $storefront->id)
            ->where('type', 'hero')->get();

        if ($sections->isEmpty()) {
            $sections = collect([StorefrontHomepageSection::withoutTenantScope()->create([
                'tenant_id' => tenant()->id,
                'storefront_id' => $storefront->id,
                'type' => 'hero',
                'variant' => 'auto',
                'enabled' => true,
                'desktop_visible' => true,
                'mobile_visible' => true,
                'position' => 0,
                'configuration' => [],
            ])]);
        }

        foreach ($sections as $section) {
            $configuration = $section->configuration ?? [];
            $existingIds = $configuration['media_ids'] ?? [];
            if ($detachId !== null) {
                $mediaIds = array_values(array_filter($existingIds, fn ($id) => (int) $id !== $detachId));
            }
            $configuration['media_ids'] = array_values(array_unique(array_map('intval', $mediaIds)));
            $section->update(['configuration' => $configuration]);
        }
        $this->revisionService->syncDraft($storefront);
    }

    private function mediaUsage(StorefrontMedia $media): ?string
    {
        if (in_array($media->key, ['logo', 'favicon'], true)) {
            return $media->key;
        }

        $heroUses = StorefrontHomepageSection::where('type', 'hero')->get()
            ->contains(fn ($section) => in_array($media->id, array_map('intval', $section->configuration['media_ids'] ?? []), true));
        if ($heroUses) {
            return 'the homepage hero';
        }

        if (PromotionBanner::where('storefront_media_id', $media->id)->exists()) {
            return 'a promotion';
        }

        $info = WebsiteInfo::first();
        if ($info && in_array($media->path, [$info->logo, $info->favicon, $info->og_image], true)) {
            return 'store identity or SEO';
        }

        return null;
    }

    private function assertMediaBelongsToCurrentStorefront(StorefrontMedia $media): void
    {
        $storefront = $this->storefront();
        abort_unless((int) $media->tenant_id === (int) tenant()->id && (int) $media->storefront_id === (int) $storefront->id, 404);
    }

    private function storefront(): Storefront
    {
        abort_unless(tenant(), 404);
        abort_unless(Schema::hasTable('storefront_media'), 404);

        $storefront = Storefront::first() ?: $this->resolver->provision(tenant());
        abort_unless($storefront && (int) $storefront->tenant_id === (int) tenant()->id, 404);

        return $storefront;
    }
}
