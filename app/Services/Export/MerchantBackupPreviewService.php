<?php

namespace App\Services\Export;

use App\Models\Tenant;
use Illuminate\Support\Str;
use ZipArchive;

final class MerchantBackupPreviewService
{
    private const CONFLICT_LIMIT = 25;

    private const CONFLICT_SCAN_LIMIT = 100000;

    private const CONFLICT_RULES = [
        'products' => ['column' => 'sku', 'label' => 'Product SKU'],
        'coupons' => ['column' => 'code', 'label' => 'Coupon code'],
        'promotions' => ['column' => 'code', 'label' => 'Promotion code'],
        'categories' => ['column' => 'slug', 'label' => 'Category slug'],
        'brands' => ['column' => 'slug', 'label' => 'Brand slug'],
        'units' => ['column' => 'short_name', 'label' => 'Unit short name'],
        'warehouses' => ['column' => 'code', 'label' => 'Warehouse code'],
        'payment_methods' => ['column' => 'name', 'label' => 'Payment method name'],
        'cities' => ['column' => 'name', 'label' => 'City name'],
        'townships' => ['column' => 'name', 'label' => 'Township name'],
    ];

    private const KNOWN_DATA_LOSSES = [
        'Media binaries (images and files) are not in the archive and cannot be recovered.',
        'Order idempotency keys, transaction IDs, payment screenshots and payment proofs are not included.',
        'Orders are restored without their original customer link; a placeholder customer is required.',
        'Customer emails are rewritten with a recovery suffix because emails are unique across the platform.',
        'Order coupons lose their promotion link.',
        'order_items.updated_at is reconstructed from created_at.',
        'Stock movement adjustment numbers are renumbered to avoid platform-wide collisions.',
        'Promotion, flash sale and storefront revision creator links are cleared.',
        'Staff users, roles, memberships and sessions are not exported; staff must be re-invited.',
        'Email verification timestamps are not restored.',
        'The storefront theme identifier is not restored; the theme must be re-selected.',
    ];

    public function __construct(
        private readonly MerchantDataExportService $exporter
    ) {}

    /**
     * Build a read-only recovery preview. Only SELECT queries are issued.
     *
     * @param array{manifest: array<string, mixed>, asset_summary: array<string, mixed>, bytes: int, verified_at: string} $verified
     * @return array<string, mixed>
     */
    public function build(Tenant $tenant, string $archivePath, array $verified): array
    {
        $manifest = $verified['manifest'];
        $conflicts = $this->detectConflicts($archivePath);
        $warnings = [];

        if ((string) ($manifest['store']['slug'] ?? '') !== (string) $tenant->slug) {
            $warnings[] = 'The store slug recorded in the archive differs from the current store slug. The archive is still bound to this store by its tenant identifier.';
        }

        if ($conflicts['total'] > 0) {
            $warnings[] = 'The archive contains duplicate identifiers that recovery will have to resolve.';
        }

        if (($manifest['schema']['migrations_applied'] ?? null) === null) {
            $warnings[] = 'The source schema fingerprint is unavailable. A future recovery will validate compatibility before importing.';
        }

        return [
            'archive_id' => (string) $manifest['archive_id'],
            'archive_created_at' => (string) ($manifest['generated_at'] ?? ''),
            'verified_at' => $verified['verified_at'],
            'source_store' => [
                'id' => (int) $manifest['tenant']['id'],
                'name' => (string) ($manifest['tenant']['name'] ?? ''),
                'slug' => (string) ($manifest['tenant']['slug'] ?? ''),
            ],
            'archive' => [
                'bytes' => (int) $verified['bytes'],
                'files' => count($manifest['files']),
                'categories' => count($manifest['categories']),
            ],
            'summary' => [
                'total_records' => (int) $manifest['total_records'],
                'categories' => count($manifest['categories']),
                'conflicts' => (int) $conflicts['total'],
                'media_assets' => (int) ($verified['asset_summary']['total'] ?? 0),
            ],
            'categories' => $this->categoryRows($manifest),
            'conflicts' => $conflicts,
            'excluded' => $manifest['excluded'],
            'excluded_settings' => $manifest['excluded_settings'] ?? ['keys' => [], 'total' => 0, 'truncated' => false],
            'data_losses' => self::KNOWN_DATA_LOSSES,
            'media' => [
                'bundled' => false,
                'recoverable' => false,
                'note' => 'Images and files are not part of the archive. Only media metadata and checksums are present, so media must be uploaded again after recovery.',
                'summary' => $verified['asset_summary'],
            ],
            'warnings' => $warnings,
            'target' => [
                'mode' => 'new_inactive_store',
                'slug' => $this->suggestTargetSlug($tenant),
                'note' => 'Recovery creates a new inactive (pending) store with remapped identifiers. It never overwrites, merges into or deletes the live store.',
            ],
            'recovery' => [
                'enabled' => false,
                'mode' => 'new_inactive_store',
                'requires' => [
                    'Re-verification of the retained signed archive immediately before import.',
                    'Explicit confirmation of the current store slug and a new target store slug.',
                    'The source store subscription plan must allow the archived product count.',
                ],
                'guarantees' => [
                    'Every imported row receives the new store identifier.',
                    'The source store is never modified; failures roll back only the new store.',
                    'The recovered store is left inactive until it is reviewed and activated deliberately.',
                ],
            ],
            'recovery_enabled' => false,
        ];
    }

    /**
     * @param array<string, mixed> $manifest
     * @return list<array{key: string, label: string, group: string, records: int}>
     */
    private function categoryRows(array $manifest): array
    {
        $rows = [];

        foreach ($this->exporter->categories() as $key => $meta) {
            $rows[] = [
                'key' => $key,
                'label' => $meta['label'] ?? $key,
                'group' => $meta['group'] ?? 'Other',
                'records' => (int) ($manifest['record_counts'][$key] ?? 0),
            ];
        }

        return $rows;
    }

    /**
     * @return array{items: list<array{category: string, label: string, value: string, count: int}>, total: int, truncated: bool}
     */
    private function detectConflicts(string $archivePath): array
    {
        $zip = new ZipArchive();

        if ($zip->open($archivePath) !== true) {
            return ['items' => [], 'total' => 0, 'truncated' => false];
        }

        $items = [];
        $total = 0;
        $truncated = false;

        try {
            foreach (self::CONFLICT_RULES as $category => $rule) {
                $found = $this->duplicateValues($zip, $category, $rule['column']);

                $total += $found['duplicates'];
                $truncated = $truncated || $found['truncated'];

                foreach ($found['values'] as $value => $count) {
                    if (count($items) >= self::CONFLICT_LIMIT) {
                        $truncated = true;
                        break 2;
                    }

                    $items[] = [
                        'category' => $category,
                        'label' => $rule['label'],
                        'value' => (string) $value,
                        'count' => (int) $count,
                    ];
                }
            }
        } finally {
            $zip->close();
        }

        return ['items' => $items, 'total' => $total, 'truncated' => $truncated];
    }

    /**
     * @return array{values: array<string, int>, duplicates: int, truncated: bool}
     */
    private function duplicateValues(ZipArchive $zip, string $category, string $column): array
    {
        $stream = $zip->getStream($category . '.csv');

        if ($stream === false) {
            return ['values' => [], 'duplicates' => 0, 'truncated' => false];
        }

        $values = [];
        $truncated = false;

        try {
            $header = fgetcsv($stream, 0, ',', '"', '');

            if (!is_array($header)) {
                return ['values' => [], 'duplicates' => 0, 'truncated' => false];
            }

            $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
            $index = array_search($column, $header, true);

            if ($index === false) {
                return ['values' => [], 'duplicates' => 0, 'truncated' => false];
            }

            $seen = 0;

            while (($row = fgetcsv($stream, 0, ',', '"', '')) !== false) {
                if ($row === [null]) {
                    continue;
                }

                $seen++;

                if ($seen > self::CONFLICT_SCAN_LIMIT) {
                    $truncated = true;
                    break;
                }

                $value = trim((string) ($row[$index] ?? ''));

                if ($value === '') {
                    continue;
                }

                $values[$value] = ($values[$value] ?? 0) + 1;
            }
        } finally {
            fclose($stream);
        }

        $duplicates = array_filter($values, fn (int $count) => $count > 1);

        return [
            'values' => $duplicates,
            'duplicates' => array_sum($duplicates),
            'truncated' => $truncated,
        ];
    }

    private function suggestTargetSlug(Tenant $tenant): string
    {
        $base = Str::slug($tenant->slug ?: $tenant->name) ?: 'store';
        $candidate = $base . '-restored-' . now()->format('Ymd');
        $suffix = 1;

        while (Tenant::where('slug', $candidate)->exists()) {
            $suffix++;
            $candidate = $base . '-restored-' . now()->format('Ymd') . '-' . $suffix;
        }

        return $candidate;
    }
}
