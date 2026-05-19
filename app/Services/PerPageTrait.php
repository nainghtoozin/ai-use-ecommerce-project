<?php

namespace App\Services;

use Illuminate\Http\Request;
use Illuminate\Pagination\LengthAwarePaginator;
use Illuminate\Support\Collection;

trait PerPageTrait
{
    private array $perPageOptions = [10, 25, 50, 100, 500];
    private int $defaultPerPage = 10;
    private int $maxSafeLimit = 5000;

    protected function resolvePerPage(Request $request): array
    {
        $perPage = $request->input('per_page', $this->defaultPerPage);
        $warning = null;

        if ($perPage === 'all') {
            $perPage = -1;
        } else {
            $perPage = (int) $perPage;
            
            if (!in_array($perPage, $this->perPageOptions)) {
                $perPage = $this->defaultPerPage;
            }
            
            if ($perPage > $this->maxSafeLimit) {
                $perPage = 500;
            }
        }

        return [
            'per_page' => $perPage,
            'warning' => $warning,
            'should_paginate' => $perPage !== -1,
        ];
    }

    protected function createLengthAwarePaginator(Collection $items, int $total, Request $request): LengthAwarePaginator
    {
        return new LengthAwarePaginator(
            $items,
            $total,
            $total,
            1,
            ['path' => $request->url(), 'query' => $request->query()]
        );
    }

    protected function getPerPageOptions(): array
    {
        return $this->perPageOptions;
    }

    protected function getMaxSafeLimit(): int
    {
        return $this->maxSafeLimit;
    }
}