<?php

namespace App\Services;

use App\Models\CodRule;
use App\Models\DeliveryService;
use App\Models\PackagingOption;
use App\Models\PaymentMethod;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;

class TenantDemoConfigurationImportService
{
    public const CATEGORY_PAYMENT_METHODS = 'payment_methods';
    public const CATEGORY_DELIVERY_SERVICES = 'delivery_services';
    public const CATEGORY_PACKAGING_OPTIONS = 'packaging_options';
    public const CATEGORY_COD_RULES = 'cod_rules';

    public const ALL_CATEGORIES = [
        self::CATEGORY_PAYMENT_METHODS,
        self::CATEGORY_DELIVERY_SERVICES,
        self::CATEGORY_PACKAGING_OPTIONS,
        self::CATEGORY_COD_RULES,
    ];

    public const CATEGORY_LABELS = [
        self::CATEGORY_PAYMENT_METHODS => 'Payment Methods',
        self::CATEGORY_DELIVERY_SERVICES => 'Delivery Services',
        self::CATEGORY_PACKAGING_OPTIONS => 'Packaging Options',
        self::CATEGORY_COD_RULES => 'COD Rules',
    ];

    public function definitions(): array
    {
        return [
            self::CATEGORY_PAYMENT_METHODS => [
                [
                    'name' => 'KBZPay',
                    'display_name' => 'KBZPay (Demo)',
                    'slug' => 'demo-kbzpay',
                    'type' => 'manual',
                    'account_name' => 'Demo Store Account',
                    'account_number' => '09-000-000-000',
                    'bank_name' => null,
                    'instructions' => 'Demo configuration only. No gateway is connected. Replace this demo account with your real KBZPay details before accepting payments.',
                    'is_active' => true,
                    'sort_order' => 10,
                    'logo_path' => '/images/payment-methods/wallet.svg',
                ],
                [
                    'name' => 'Wave Pay',
                    'display_name' => 'Wave Pay (Demo)',
                    'slug' => 'demo-wave-pay',
                    'type' => 'manual',
                    'account_name' => 'Demo Store Account',
                    'account_number' => '09-000-000-001',
                    'bank_name' => null,
                    'instructions' => 'Demo configuration only. No gateway is connected. Replace this demo account with your real Wave Pay details before accepting payments.',
                    'is_active' => true,
                    'sort_order' => 20,
                    'logo_path' => '/images/payment-methods/wallet.svg',
                ],
                [
                    'name' => 'CB Pay',
                    'display_name' => 'CB Pay (Demo)',
                    'slug' => 'demo-cb-pay',
                    'type' => 'manual',
                    'account_name' => 'Demo Store Account',
                    'account_number' => '09-000-000-002',
                    'bank_name' => 'CB Bank',
                    'instructions' => 'Demo configuration only. No gateway is connected. Replace this demo account with your real CB Pay details before accepting payments.',
                    'is_active' => true,
                    'sort_order' => 30,
                    'logo_path' => '/images/payment-methods/bank.svg',
                ],
                [
                    'name' => 'Visa Card',
                    'display_name' => 'Visa Card (Demo)',
                    'slug' => 'demo-visa-card',
                    'type' => 'manual',
                    'account_name' => null,
                    'account_number' => null,
                    'bank_name' => null,
                    'instructions' => 'Demo placeholder only. This store does not process card payments online and no card gateway is connected. Never collect card numbers or CVV through this method. Leave it inactive unless a verified gateway is integrated.',
                    'is_active' => false,
                    'sort_order' => 40,
                    'logo_path' => '/images/payment-methods/card.svg',
                ],
            ],
            self::CATEGORY_DELIVERY_SERVICES => [
                [
                    'name' => 'Standard Delivery',
                    'code' => 'standard-delivery',
                    'description' => 'Demo default: economy delivery within 3-5 business days. Adjust the fee and estimates to match your operations.',
                    'base_fee' => 2000,
                    'fee_per_kg' => null,
                    'min_days' => 3,
                    'max_days' => 5,
                    'is_active' => true,
                    'sort_order' => 10,
                ],
                [
                    'name' => 'Express Delivery',
                    'code' => 'express-delivery',
                    'description' => 'Demo default: faster delivery within 1-2 business days. Adjust the fee and estimates to match your operations.',
                    'base_fee' => 4000,
                    'fee_per_kg' => null,
                    'min_days' => 1,
                    'max_days' => 2,
                    'is_active' => true,
                    'sort_order' => 20,
                ],
                [
                    'name' => 'Same Day Delivery',
                    'code' => 'same-day-delivery',
                    'description' => 'Demo default: same day delivery for nearby areas. Adjust the fee and estimates to match your operations.',
                    'base_fee' => 6000,
                    'fee_per_kg' => null,
                    'min_days' => 0,
                    'max_days' => 1,
                    'is_active' => true,
                    'sort_order' => 30,
                ],
            ],
            self::CATEGORY_PACKAGING_OPTIONS => [
                [
                    'name' => 'Standard Packaging',
                    'code' => 'standard-packaging',
                    'description' => 'Demo default: basic protective packaging at no extra cost.',
                    'fee' => 0,
                    'is_active' => true,
                    'sort_order' => 10,
                ],
                [
                    'name' => 'Gift Packaging',
                    'code' => 'gift-packaging',
                    'description' => 'Demo default: gift wrap with a handwritten note.',
                    'fee' => 1500,
                    'is_active' => true,
                    'sort_order' => 20,
                ],
                [
                    'name' => 'Premium Packaging',
                    'code' => 'premium-packaging',
                    'description' => 'Demo default: premium box with protective inserts.',
                    'fee' => 3000,
                    'is_active' => true,
                    'sort_order' => 30,
                ],
            ],
            self::CATEGORY_COD_RULES => [
                [
                    'name' => 'Cash on Delivery (Demo)',
                    'is_active' => true,
                    'min_order_amount' => 0,
                    'max_order_amount' => null,
                    'allowed_city_ids' => null,
                    'excluded_city_ids' => null,
                ],
            ],
        ];
    }

    public function importAll(Tenant $tenant): array
    {
        return $this->import($tenant, self::ALL_CATEGORIES);
    }

    public function import(Tenant $tenant, array $categories = self::ALL_CATEGORIES): array
    {
        $categories = $this->normalizeCategories($categories);
        $summary = [
            'tenant_id' => (int) $tenant->id,
            'tenant_slug' => $tenant->slug,
            'tenant_name' => $tenant->name,
            'categories' => [],
            'totals' => ['created' => 0, 'skipped' => 0, 'failed' => 0],
        ];

        foreach ($categories as $category) {
            $result = match ($category) {
                self::CATEGORY_PAYMENT_METHODS => $this->importPaymentMethods($tenant),
                self::CATEGORY_DELIVERY_SERVICES => $this->importDeliveryServices($tenant),
                self::CATEGORY_PACKAGING_OPTIONS => $this->importPackagingOptions($tenant),
                self::CATEGORY_COD_RULES => $this->importCodRules($tenant),
            };

            $summary['categories'][$category] = $result;
            $summary['totals']['created'] += $result['created'];
            $summary['totals']['skipped'] += $result['skipped'];
            $summary['totals']['failed'] += $result['failed'];
        }

        return $summary;
    }

    public function importForTenants(array $tenants, array $categories = self::ALL_CATEGORIES): array
    {
        $results = [];

        foreach ($tenants as $tenant) {
            try {
                $results[] = $this->import($tenant, $categories);
            } catch (\Throwable $e) {
                Log::error('Demo configuration import failed for tenant', [
                    'tenant_id' => $tenant->id,
                    'tenant_slug' => $tenant->slug,
                    'exception' => $e->getMessage(),
                ]);

                $results[] = [
                    'tenant_id' => (int) $tenant->id,
                    'tenant_slug' => $tenant->slug,
                    'tenant_name' => $tenant->name,
                    'categories' => [],
                    'totals' => ['created' => 0, 'skipped' => 0, 'failed' => 0],
                    'error' => $e->getMessage(),
                ];
            }
        }

        return $results;
    }

    public function status(Tenant $tenant): array
    {
        $definitions = $this->definitions();
        $status = [
            'tenant_id' => (int) $tenant->id,
            'categories' => [],
        ];

        $paymentExisting = PaymentMethod::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();

        $paymentRecords = array_map(function (array $definition) use ($tenant) {
            return [
                'name' => $definition['name'],
                'identifier' => $definition['slug'],
                'present' => $this->paymentMethodExists($tenant, $definition),
            ];
        }, $definitions[self::CATEGORY_PAYMENT_METHODS]);

        $status['categories'][self::CATEGORY_PAYMENT_METHODS] = $this->categoryStatus(
            self::CATEGORY_PAYMENT_METHODS,
            $definitions[self::CATEGORY_PAYMENT_METHODS],
            $paymentRecords,
            $paymentExisting
        );

        $deliveryExisting = DeliveryService::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();

        $deliveryRecords = array_map(function (array $definition) use ($tenant) {
            return [
                'name' => $definition['name'],
                'identifier' => $definition['code'],
                'present' => $this->deliveryServiceExists($tenant, $definition),
            ];
        }, $definitions[self::CATEGORY_DELIVERY_SERVICES]);

        $status['categories'][self::CATEGORY_DELIVERY_SERVICES] = $this->categoryStatus(
            self::CATEGORY_DELIVERY_SERVICES,
            $definitions[self::CATEGORY_DELIVERY_SERVICES],
            $deliveryRecords,
            $deliveryExisting
        );

        $packagingExisting = PackagingOption::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();

        $packagingRecords = array_map(function (array $definition) use ($tenant) {
            return [
                'name' => $definition['name'],
                'identifier' => $definition['code'],
                'present' => $this->packagingOptionExists($tenant, $definition),
            ];
        }, $definitions[self::CATEGORY_PACKAGING_OPTIONS]);

        $status['categories'][self::CATEGORY_PACKAGING_OPTIONS] = $this->categoryStatus(
            self::CATEGORY_PACKAGING_OPTIONS,
            $definitions[self::CATEGORY_PACKAGING_OPTIONS],
            $packagingRecords,
            $packagingExisting
        );

        $codExisting = CodRule::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->count();

        $codRecords = array_map(function (array $definition) use ($tenant) {
            return [
                'name' => $definition['name'],
                'identifier' => 'cod-rule',
                'present' => $this->codRuleExists($tenant),
            ];
        }, $definitions[self::CATEGORY_COD_RULES]);

        $status['categories'][self::CATEGORY_COD_RULES] = $this->categoryStatus(
            self::CATEGORY_COD_RULES,
            $definitions[self::CATEGORY_COD_RULES],
            $codRecords,
            $codExisting
        );

        return $status;
    }

    public function statusForTenants(array $tenantIds): array
    {
        $tenantIds = array_values(array_unique(array_map('intval', $tenantIds)));

        if (empty($tenantIds)) {
            return [];
        }

        $definitions = $this->definitions();

        $paymentCounts = PaymentMethod::withoutTenantScope()
            ->whereIn('tenant_id', $tenantIds)
            ->where(function ($query) use ($definitions) {
                $query->whereIn('slug', array_column($definitions[self::CATEGORY_PAYMENT_METHODS], 'slug'))
                    ->orWhereIn('name', array_column($definitions[self::CATEGORY_PAYMENT_METHODS], 'name'));
            })
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $deliveryCounts = DeliveryService::withoutTenantScope()
            ->whereIn('tenant_id', $tenantIds)
            ->where(function ($query) use ($definitions) {
                $query->whereIn('code', array_column($definitions[self::CATEGORY_DELIVERY_SERVICES], 'code'))
                    ->orWhereIn('name', array_column($definitions[self::CATEGORY_DELIVERY_SERVICES], 'name'));
            })
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $packagingCounts = PackagingOption::withoutTenantScope()
            ->whereIn('tenant_id', $tenantIds)
            ->where(function ($query) use ($definitions) {
                $query->whereIn('code', array_column($definitions[self::CATEGORY_PACKAGING_OPTIONS], 'code'))
                    ->orWhereIn('name', array_column($definitions[self::CATEGORY_PACKAGING_OPTIONS], 'name'));
            })
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $codCounts = CodRule::withoutTenantScope()
            ->whereIn('tenant_id', $tenantIds)
            ->selectRaw('tenant_id, count(*) as total')
            ->groupBy('tenant_id')
            ->pluck('total', 'tenant_id');

        $statuses = [];

        foreach ($tenantIds as $tenantId) {
            $statuses[$tenantId] = [
                self::CATEGORY_PAYMENT_METHODS => [
                    'demo_present' => (int) ($paymentCounts[$tenantId] ?? 0),
                    'demo_total' => count($definitions[self::CATEGORY_PAYMENT_METHODS]),
                ],
                self::CATEGORY_DELIVERY_SERVICES => [
                    'demo_present' => (int) ($deliveryCounts[$tenantId] ?? 0),
                    'demo_total' => count($definitions[self::CATEGORY_DELIVERY_SERVICES]),
                ],
                self::CATEGORY_PACKAGING_OPTIONS => [
                    'demo_present' => (int) ($packagingCounts[$tenantId] ?? 0),
                    'demo_total' => count($definitions[self::CATEGORY_PACKAGING_OPTIONS]),
                ],
                self::CATEGORY_COD_RULES => [
                    'demo_present' => (int) ($codCounts[$tenantId] ?? 0),
                    'demo_total' => count($definitions[self::CATEGORY_COD_RULES]),
                ],
            ];
        }

        return $statuses;
    }

    private function categoryStatus(string $category, array $definitions, array $records, int $existingCount): array
    {
        $present = count(array_filter($records, fn (array $record) => $record['present'] === true));
        $total = count($definitions);

        return [
            'label' => self::CATEGORY_LABELS[$category],
            'demo_total' => $total,
            'demo_present' => $present,
            'demo_missing' => max(0, $total - $present),
            'existing_count' => $existingCount,
            'records' => $records,
        ];
    }

    private function importPaymentMethods(Tenant $tenant): array
    {
        $result = $this->emptyResult(self::CATEGORY_PAYMENT_METHODS);

        foreach ($this->definitions()[self::CATEGORY_PAYMENT_METHODS] as $definition) {
            try {
                $created = DB::transaction(function () use ($tenant, $definition) {
                    if ($this->paymentMethodExists($tenant, $definition)) {
                        return false;
                    }

                    PaymentMethod::withoutTenantScope()->create([
                        'tenant_id' => $tenant->id,
                        'name' => $definition['name'],
                        'display_name' => $definition['display_name'],
                        'slug' => $definition['slug'],
                        'type' => $definition['type'],
                        'account_name' => $definition['account_name'],
                        'account_number' => $definition['account_number'],
                        'bank_name' => $definition['bank_name'],
                        'instructions' => $definition['instructions'],
                        'is_active' => $definition['is_active'],
                        'is_demo' => true,
                        'logo_path' => $definition['logo_path'],
                        'sort_order' => $definition['sort_order'],
                    ]);

                    return true;
                });

                $this->recordResult($result, $definition['name'], $definition['slug'], $created);
            } catch (\Throwable $e) {
                $this->recordFailure($result, $definition['name'], $definition['slug'], $e, $tenant);
            }
        }

        return $result;
    }

    private function importDeliveryServices(Tenant $tenant): array
    {
        $result = $this->emptyResult(self::CATEGORY_DELIVERY_SERVICES);

        foreach ($this->definitions()[self::CATEGORY_DELIVERY_SERVICES] as $definition) {
            try {
                $created = DB::transaction(function () use ($tenant, $definition) {
                    if ($this->deliveryServiceExists($tenant, $definition)) {
                        return false;
                    }

                    DeliveryService::withoutTenantScope()->create([
                        'tenant_id' => $tenant->id,
                        'name' => $definition['name'],
                        'code' => $definition['code'],
                        'description' => $definition['description'],
                        'base_fee' => $definition['base_fee'],
                        'fee_per_kg' => $definition['fee_per_kg'],
                        'min_days' => $definition['min_days'],
                        'max_days' => $definition['max_days'],
                        'is_active' => $definition['is_active'],
                        'sort_order' => $definition['sort_order'],
                    ]);

                    return true;
                });

                $this->recordResult($result, $definition['name'], $definition['code'], $created);
            } catch (\Throwable $e) {
                $this->recordFailure($result, $definition['name'], $definition['code'], $e, $tenant);
            }
        }

        return $result;
    }

    private function importPackagingOptions(Tenant $tenant): array
    {
        $result = $this->emptyResult(self::CATEGORY_PACKAGING_OPTIONS);

        foreach ($this->definitions()[self::CATEGORY_PACKAGING_OPTIONS] as $definition) {
            try {
                $created = DB::transaction(function () use ($tenant, $definition) {
                    if ($this->packagingOptionExists($tenant, $definition)) {
                        return false;
                    }

                    PackagingOption::withoutTenantScope()->create([
                        'tenant_id' => $tenant->id,
                        'name' => $definition['name'],
                        'code' => $definition['code'],
                        'description' => $definition['description'],
                        'fee' => $definition['fee'],
                        'is_active' => $definition['is_active'],
                        'sort_order' => $definition['sort_order'],
                    ]);

                    return true;
                });

                $this->recordResult($result, $definition['name'], $definition['code'], $created);
            } catch (\Throwable $e) {
                $this->recordFailure($result, $definition['name'], $definition['code'], $e, $tenant);
            }
        }

        return $result;
    }

    private function importCodRules(Tenant $tenant): array
    {
        $result = $this->emptyResult(self::CATEGORY_COD_RULES);

        foreach ($this->definitions()[self::CATEGORY_COD_RULES] as $definition) {
            try {
                $created = DB::transaction(function () use ($tenant, $definition) {
                    if ($this->codRuleExists($tenant)) {
                        return false;
                    }

                    CodRule::withoutTenantScope()->create([
                        'tenant_id' => $tenant->id,
                        'name' => $definition['name'],
                        'is_active' => $definition['is_active'],
                        'min_order_amount' => $definition['min_order_amount'],
                        'max_order_amount' => $definition['max_order_amount'],
                        'allowed_city_ids' => $definition['allowed_city_ids'],
                        'excluded_city_ids' => $definition['excluded_city_ids'],
                    ]);

                    return true;
                });

                $this->recordResult($result, $definition['name'], 'cod-rule', $created);
            } catch (\Throwable $e) {
                $this->recordFailure($result, $definition['name'], 'cod-rule', $e, $tenant);
            }
        }

        return $result;
    }

    private function paymentMethodExists(Tenant $tenant, array $definition): bool
    {
        return PaymentMethod::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($definition) {
                $query->where('name', $definition['name'])
                    ->orWhere('slug', $definition['slug']);
            })
            ->exists();
    }

    private function deliveryServiceExists(Tenant $tenant, array $definition): bool
    {
        return DeliveryService::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($definition) {
                $query->where('code', $definition['code'])
                    ->orWhere('name', $definition['name']);
            })
            ->exists();
    }

    private function packagingOptionExists(Tenant $tenant, array $definition): bool
    {
        return PackagingOption::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->where(function ($query) use ($definition) {
                $query->where('code', $definition['code'])
                    ->orWhere('name', $definition['name']);
            })
            ->exists();
    }

    private function codRuleExists(Tenant $tenant): bool
    {
        return CodRule::withoutTenantScope()
            ->where('tenant_id', $tenant->id)
            ->exists();
    }

    private function normalizeCategories(array $categories): array
    {
        if (empty($categories) || in_array('all', $categories, true)) {
            return self::ALL_CATEGORIES;
        }

        $normalized = array_values(array_intersect(self::ALL_CATEGORIES, $categories));

        return empty($normalized) ? self::ALL_CATEGORIES : $normalized;
    }

    private function emptyResult(string $category): array
    {
        return [
            'label' => self::CATEGORY_LABELS[$category],
            'created' => 0,
            'skipped' => 0,
            'failed' => 0,
            'records' => [],
        ];
    }

    private function recordResult(array &$result, string $name, string $identifier, bool $created): void
    {
        $status = $created ? 'created' : 'skipped';
        $result[$status]++;
        $result['records'][] = [
            'name' => $name,
            'identifier' => $identifier,
            'status' => $status,
        ];
    }

    private function recordFailure(array &$result, string $name, string $identifier, \Throwable $e, Tenant $tenant): void
    {
        $result['failed']++;
        $result['records'][] = [
            'name' => $name,
            'identifier' => $identifier,
            'status' => 'failed',
            'error' => $e->getMessage(),
        ];

        Log::warning('Demo configuration record import failed', [
            'tenant_id' => $tenant->id,
            'tenant_slug' => $tenant->slug,
            'record' => $name,
            'identifier' => $identifier,
            'exception' => $e->getMessage(),
        ]);
    }
}
