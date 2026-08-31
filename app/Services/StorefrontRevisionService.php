<?php

namespace App\Services;

use App\Models\Storefront;
use App\Models\StorefrontRevision;
use App\Models\Theme;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Validation\ValidationException;

class StorefrontRevisionService
{
    public function __construct(private readonly StorefrontConfigurationResolver $resolver) {}

    public function prepareDraft(Storefront $storefront): ?StorefrontRevision
    {
        $this->assertCurrentTenant($storefront);
        if (!Schema::hasTable('storefront_revisions')) return null;

        return DB::transaction(function () use ($storefront) {
            $this->ensurePublishedBaseline($storefront);
            return $storefront->refresh()->draftRevision()->first();
        });
    }

    public function syncDraft(Storefront $storefront): ?StorefrontRevision
    {
        if (!Schema::hasTable('storefront_revisions')) return null;
        return DB::transaction(function () use ($storefront) {
            $this->ensurePublishedBaseline($storefront);
            $storefront->refresh();
            $draft = $storefront->draftRevision()->first();
            if (!$draft) {
                $draft = $this->createRevision($storefront, 'draft', $this->resolver->resolveBase(null, true), false);
                $storefront->update(['draft_revision_id' => $draft->id]);
            }
            $draft->update(['configuration' => $this->resolver->resolveBase(null, true)]);
            return $draft->fresh();
        });
    }

    public function publish(Storefront $storefront): StorefrontRevision
    {
        $this->assertCurrentTenant($storefront);
        abort_unless(Schema::hasTable('storefront_revisions'), 404);
        $draft = $storefront->draftRevision()->first();
        if (!$draft) {
            throw ValidationException::withMessages(['storefront' => 'There are no unpublished changes to publish.']);
        }

        $this->validateConfiguration($draft->configuration ?? []);

        return DB::transaction(function () use ($storefront, $draft) {
            $oldPublished = $storefront->publishedRevision()->first();
            if ($oldPublished) {
                $oldPublished->update(['status' => 'archived']);
            }

            $draft->update([
                'status' => 'published',
                'published_at' => now(),
                'published_by_type' => auth()->user()?->getMorphClass(),
                'published_by_id' => auth()->id(),
            ]);
            $storefront->update([
                'published_revision_id' => $draft->id,
                'draft_revision_id' => null,
            ]);

            return $draft->fresh();
        });
    }

    public function restoreAsDraft(Storefront $storefront, StorefrontRevision $revision): StorefrontRevision
    {
        $this->assertCurrentTenant($storefront);
        abort_unless(Schema::hasTable('storefront_revisions'), 404);
        abort_unless((int) $revision->storefront_id === (int) $storefront->id, 404);

        return DB::transaction(function () use ($storefront, $revision) {
            $this->ensurePublishedBaseline($storefront);
            $storefront->refresh();
            $draft = $storefront->draftRevision()->first();
            if (!$draft) {
                $draft = $this->createRevision($storefront, 'draft', $revision->configuration ?? [], false);
                $storefront->update(['draft_revision_id' => $draft->id]);
            } else {
                $draft->update(['configuration' => $revision->configuration]);
            }
            return $draft->fresh();
        });
    }

    public function status(Storefront $storefront): array
    {
        $this->assertCurrentTenant($storefront);
        if (!Schema::hasTable('storefront_revisions')) {
            return ['published' => null, 'draft' => null, 'has_unpublished_changes' => false];
        }
        $published = $storefront->publishedRevision;
        $draft = $storefront->draftRevision;

        return [
            'published' => $published ? [
                'id' => $published->id,
                'revision_number' => $published->revision_number,
                'published_at' => $published->published_at?->toISOString(),
            ] : null,
            'draft' => $draft ? [
                'id' => $draft->id,
                'revision_number' => $draft->revision_number,
                'created_at' => $draft->created_at?->toISOString(),
            ] : null,
            'has_unpublished_changes' => (bool) $draft,
        ];
    }

    private function createRevision(Storefront $storefront, string $status, array $configuration, bool $published): StorefrontRevision
    {
        $number = ((int) StorefrontRevision::withoutTenantScope()->where('storefront_id', $storefront->id)->max('revision_number')) + 1;
        return StorefrontRevision::withoutTenantScope()->create([
            'tenant_id' => tenant()->id,
            'storefront_id' => $storefront->id,
            'revision_number' => $number,
            'status' => $status,
            'configuration' => $configuration,
            'created_by_type' => auth()->user()?->getMorphClass(),
            'created_by_id' => auth()->id(),
            'published_at' => $published ? now() : null,
            'published_by_type' => $published ? auth()->user()?->getMorphClass() : null,
            'published_by_id' => $published ? auth()->id() : null,
        ]);
    }

    private function ensurePublishedBaseline(Storefront $storefront): StorefrontRevision
    {
        $storefront->refresh();
        if ($storefront->published_revision_id) {
            return $storefront->publishedRevision()->firstOrFail();
        }

        $published = $this->createRevision($storefront, 'published', $this->resolver->resolveBase(null, true), true);
        $storefront->update(['published_revision_id' => $published->id]);
        return $published;
    }

    private function validateConfiguration(array $configuration): void
    {
        $theme = $configuration['theme']['slug'] ?? null;
        if (!$theme || !Theme::where('slug', $theme)->where('is_active', true)->exists()) {
            throw ValidationException::withMessages(['storefront' => 'The draft does not have a valid theme.']);
        }

        $allowedPaths = \App\Http\Requests\UpdateStorefrontNavigationRequest::allowedPaths();
        foreach ($configuration['navigation']['items'] ?? [] as $item) {
            if (!in_array($item['path'] ?? null, $allowedPaths, true)) {
                throw ValidationException::withMessages(['storefront' => 'The draft contains an unsupported navigation destination.']);
            }
        }

        foreach ($configuration['homepage']['sections'] ?? [] as $section) {
            foreach ($section['configuration']['media_ids'] ?? [] as $mediaId) {
                if (!\App\Models\StorefrontMedia::whereKey($mediaId)->exists()) {
                    throw ValidationException::withMessages(['storefront' => 'The draft references unavailable media.']);
                }
            }
            foreach ($section['data']['promotions'] ?? [] as $promotion) {
                if (!empty($promotion['media_id']) && !\App\Models\StorefrontMedia::whereKey($promotion['media_id'])->exists()) {
                    throw ValidationException::withMessages(['storefront' => 'The draft references unavailable promotion media.']);
                }
            }
        }
    }

    private function assertCurrentTenant(Storefront $storefront): void
    {
        abort_unless(tenant() && (int) $storefront->tenant_id === (int) tenant()->id, 404);
    }
}
