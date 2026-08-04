<?php

namespace App\Services;

use App\Models\PlatformFaq;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\DB;

class FaqService
{
    public function list(?string $search = null, ?string $category = null, ?bool $isActive = null, int $perPage = 20)
    {
        return PlatformFaq::query()
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

    public function create(array $data): PlatformFaq
    {
        $maxOrder = PlatformFaq::max('sort_order') ?? 0;
        $data['sort_order'] = $data['sort_order'] ?? ($maxOrder + 1);

        return PlatformFaq::create($data);
    }

    public function update(PlatformFaq $faq, array $data): PlatformFaq
    {
        $faq->update($data);
        return $faq;
    }

    public function delete(PlatformFaq $faq): void
    {
        $faq->delete();
        $this->reindexSortOrder();
    }

    public function toggleActive(PlatformFaq $faq): PlatformFaq
    {
        $faq->update(['is_active' => !$faq->is_active]);
        return $faq;
    }

    public function reorder(array $orderedIds): void
    {
        DB::transaction(function () use ($orderedIds) {
            foreach ($orderedIds as $index => $id) {
                PlatformFaq::where('id', $id)->update(['sort_order' => $index + 1]);
            }
        });
    }

    public function bulkDelete(array $ids): int
    {
        $count = PlatformFaq::whereIn('id', $ids)->delete();
        $this->reindexSortOrder();
        return $count;
    }

    public function bulkSetActive(array $ids, bool $active): int
    {
        return PlatformFaq::whereIn('id', $ids)->update(['is_active' => $active]);
    }

    public function getActivePublic(): \Illuminate\Database\Eloquent\Collection
    {
        return PlatformFaq::getActiveCached();
    }

    public function getCategories(): array
    {
        return PlatformFaq::CATEGORIES;
    }

    private function reindexSortOrder(): void
    {
        PlatformFaq::ordered()->get()->each(function ($faq, $index) {
            $faq->updateQuietly(['sort_order' => $index + 1]);
        });
    }
}
