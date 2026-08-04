<?php

namespace App\Services;

use App\Models\Tenant;
use App\Models\WebsiteFaq;
use Illuminate\Support\Facades\DB;

class WebsiteFaqService
{
    public function list(?string $search = null, ?string $category = null, ?bool $isActive = null, int $perPage = 20)
    {
        return WebsiteFaq::query()
            ->when($search, fn ($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('question_en', 'like', "%{$s}%")
                    ->orWhere('question_my', 'like', "%{$s}%")
                    ->orWhere('answer_en', 'like', "%{$s}%");
            }))
            ->when($category, fn ($q, $c) => $q->where('category', $c))
            ->when($isActive !== null, fn ($q) => $q->where('is_active', $isActive))
            ->ordered()
            ->paginate($perPage)
            ->withQueryString();
    }

    public function create(array $data): WebsiteFaq
    {
        $tenant = Tenant::getCurrent();
        $maxOrder = WebsiteFaq::max('sort_order') ?? 0;
        $data['sort_order'] = $data['sort_order'] ?? ($maxOrder + 1);

        if ($tenant && empty($data['tenant_id'])) {
            $data['tenant_id'] = $tenant->id;
        }

        return WebsiteFaq::create($data);
    }

    public function update(WebsiteFaq $faq, array $data): WebsiteFaq
    {
        $faq->update($data);
        return $faq;
    }

    public function delete(WebsiteFaq $faq): void
    {
        $tenantId = $faq->tenant_id;
        $faq->delete();
        $this->reindexSortOrder($tenantId);
    }

    public function toggleActive(WebsiteFaq $faq): WebsiteFaq
    {
        $faq->update(['is_active' => !$faq->is_active]);
        return $faq;
    }

    public function duplicate(WebsiteFaq $faq): WebsiteFaq
    {
        $data = $faq->toArray();
        unset($data['id'], $data['created_at'], $data['updated_at']);

        $data['question_en'] = $faq->question_en . ' (Copy)';
        $data['sort_order'] = (WebsiteFaq::max('sort_order') ?? 0) + 1;

        return WebsiteFaq::create($data);
    }

    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                WebsiteFaq::where('id', $id)->update(['sort_order' => $index + 1]);
            }
        });

        $tenant = Tenant::getCurrent();
        if ($tenant) {
            WebsiteFaq::clearCacheForTenant($tenant->id);
        }
    }

    public function bulkDelete(array $ids): int
    {
        $tenant = Tenant::getCurrent();
        $count = WebsiteFaq::whereIn('id', $ids)->delete();

        if ($tenant) {
            $this->reindexSortOrder($tenant->id);
        }

        return $count;
    }

    public function bulkSetActive(array $ids, bool $active): int
    {
        return WebsiteFaq::whereIn('id', $ids)->update(['is_active' => $active]);
    }

    public function getActiveForCurrentTenant(): \Illuminate\Database\Eloquent\Collection
    {
        $tenant = Tenant::getCurrent();
        if (!$tenant) {
            return collect();
        }

        return WebsiteFaq::getActiveCachedForTenant($tenant->id);
    }

    public function getActiveForTenant(int $tenantId): \Illuminate\Database\Eloquent\Collection
    {
        return WebsiteFaq::getActiveCachedForTenant($tenantId);
    }

    public function getCategories(): array
    {
        return WebsiteFaq::CATEGORIES;
    }

    private function reindexSortOrder(int $tenantId): void
    {
        WebsiteFaq::where('tenant_id', $tenantId)
            ->ordered()
            ->get()
            ->each(function ($faq, $index) {
                $faq->updateQuietly(['sort_order' => $index + 1]);
            });
    }
}
