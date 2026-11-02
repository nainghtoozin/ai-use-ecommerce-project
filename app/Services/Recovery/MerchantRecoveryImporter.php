<?php

namespace App\Services\Recovery;

use App\Models\Account;
use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use Throwable;
use ZipArchive;

final class MerchantRecoveryImporter
{
    private int $tenantId = 0;

    private bool $useAccounts = false;

    private string $identityMorphType = User::class;

    private int $placeholderIdentityId = 0;

    /** @var array<string, array<int, int>> */
    private array $maps = [];

    /** @var array<string, int> */
    private array $counts = [];

    /** @var array<int, int> */
    private array $pendingCategoryParents = [];

    /** @var array<int, int> */
    private array $pendingStorefrontDrafts = [];

    /** @var array<int, int> */
    private array $pendingStorefrontPublished = [];

    /** @var array<string, float> */
    private array $inventorySums = [];

    /** @var array<int, string> */
    private array $usedAdjustmentNumbers = [];

    private int $emailCounter = 0;

    private int $guestCustomers = 0;

    private bool $restoreExisting = false;

    /** @var array<string, array{customer: int, membership: int|null}> */
    private array $customerEmailMap = [];

    public function import(string $archivePath, int $tenantId, array $manifest, bool $restoreExisting = false): array
    {
        $this->resetImportState();

        $this->restoreExisting = $restoreExisting;
        $this->tenantId = $tenantId;
        $this->useAccounts = (bool) config('identity.use_accounts');
        $this->identityMorphType = $this->useAccounts ? Account::class : User::class;

        $zip = new ZipArchive();

        if ($zip->open($archivePath, ZipArchive::CHECKCONS) !== true) {
            throw new MerchantRecoveryException('corrupt_archive', 'The archive could not be opened for recovery.');
        }

        try {
            $this->assertIdentityMode($zip);

            $this->importIdentities($zip);
            $this->importCategories($zip);
            $this->importBrands($zip);
            $this->importUnits($zip);
            $this->importProducts($zip);
            $this->importProductVariants($zip);
            $this->importProductCombos($zip);
            $this->importCities($zip);
            $this->importTownships($zip);
            $this->importDeliveryServices($zip);
            $this->importDeliveryPricing($zip);
            $this->importWarehouses($zip);
            $this->importPackagingOptions($zip);
            $this->importPaymentMethods($zip);
            $this->importCodRules($zip);
            $this->importInventoryMovements($zip);
            $this->importCoupons($zip);
            $this->importPromotions($zip);
            $this->importFlashSales($zip);
            $this->importOrders($zip);
            $this->importCustomerRelations($zip);
            $this->importStorefronts($zip);
            $this->importWebsiteFaqs($zip);
            $this->importWebsiteInfo($zip);
            $this->importSettings($zip);
            $this->importImportHistories($zip);
            $this->verifyInventory($zip);
        } finally {
            $zip->close();
        }

        return [
            'counts' => $this->counts,
            'placeholder_identity_id' => $this->placeholderIdentityId,
            'identity_morph_type' => $this->identityMorphType,
        ];
    }

    public function setRestoreExisting(bool $value): void
    {
        $this->restoreExisting = $value;
    }

    /**
     * Clear every mutable field before an import run.
     *
     * Laravel's `DB::transaction($callback, $attempts)` re-invokes the whole
     * callback after a deadlock, and the recovery service reuses a single
     * importer instance. Without this reset a retry would inherit stale
     * counters, stale ID maps and a stale placeholder identity, producing
     * duplicate alias suffixes and false count mismatches.
     */
    private function resetImportState(): void
    {
        $this->tenantId = 0;
        $this->useAccounts = false;
        $this->identityMorphType = User::class;
        $this->placeholderIdentityId = 0;
        $this->maps = [];
        $this->counts = [];
        $this->pendingCategoryParents = [];
        $this->pendingStorefrontDrafts = [];
        $this->pendingStorefrontPublished = [];
        $this->inventorySums = [];
        $this->usedAdjustmentNumbers = [];
        $this->emailCounter = 0;
        $this->guestCustomers = 0;
        $this->restoreExisting = false;
        $this->customerEmailMap = [];
    }

    /**
     * Verify imported row counts, relationship integrity and tenant isolation.
     * Throws inside the recovery transaction when anything is inconsistent.
     *
     * @param array<string, mixed> $manifest
     * @return array<string, mixed>
     */
    public function verify(array $manifest): array
    {
        $expected = $manifest['record_counts'] ?? [];
        $checked = [];

        foreach (array_keys($expected) as $category) {
            $expectedCount = (int) $expected[$category];

            if ($category === 'customers') {
                $expectedCount = max(0, $expectedCount - $this->guestCustomers);
            }

            if (($this->counts[$category] ?? 0) !== $expectedCount) {
                throw new MerchantRecoveryException(
                    'count_mismatch',
                    sprintf('Recovery count mismatch for %s: expected %d, imported %d.', $category, $expectedCount, $this->counts[$category] ?? 0)
                );
            }

            $skipDatabaseCount = $this->restoreExisting && $category === 'settings';

            $actual = $skipDatabaseCount ? null : $this->databaseCount($category);

            if ($actual !== null && $actual !== $expectedCount) {
                throw new MerchantRecoveryException(
                    'count_mismatch',
                    sprintf('Recovery verification failed for %s: expected %d, found %d.', $category, $expectedCount, $actual)
                );
            }

            $checked[$category] = $actual ?? $expectedCount;
        }

        foreach (['categories', 'products', 'orders', 'customers', 'storefronts', 'website_faqs'] as $category) {
            if (!isset($checked[$category])) {
                continue;
            }

            $value = $checked[$category];
            if (!is_int($value) || $value < 0) {
                throw new MerchantRecoveryException('verification_failed', 'Recovery verification produced an invalid result.');
            }
        }

        $this->assertForeignReferences();
        $this->assertIdentityReferences();

        return [
            'verified_counts' => $checked,
            'inventory_levels_verified' => true,
            'guest_customers' => $this->guestCustomers,
        ];
    }

    private function assertIdentityMode(ZipArchive $zip): void
    {
        $sourceType = null;

        $this->eachRow($zip, 'customers', function (array $row) use (&$sourceType): void {
            if ($sourceType !== null) {
                return;
            }

            $type = $this->string($row, 'customer_type');

            if ($type !== '' && $type !== 'guest') {
                $sourceType = $type;
            }
        });

        if ($sourceType === null) {
            return;
        }

        $expected = $this->useAccounts ? 'account' : 'user';

        if ($sourceType !== $expected) {
            throw new MerchantRecoveryException(
                'identity_mode_mismatch',
                'This archive was exported with a different customer identity architecture and cannot be recovered by this installation.'
            );
        }
    }

    private function importIdentities(ZipArchive $zip): void
    {
        $this->maps['customers'] = [];
        $this->maps['memberships'] = [];
        $this->counts['customers'] = 0;

        $this->placeholderIdentityId = $this->useAccounts
            ? $this->createPlaceholderAccount()
            : $this->createPlaceholderUser();

        $customerRoleId = $this->customerRoleId();

        $this->eachRow($zip, 'customers', function (array $row) use ($customerRoleId): void {
            $customerType = $this->string($row, 'customer_type');
            $rawId = $this->nullable($row, 'id');

            if ($customerType === 'guest' || $rawId === null) {
                $this->guestCustomers++;

                return;
            }

            $sourceId = $this->sourceId($row);

            if ($this->restoreExisting) {
                $archiveEmail = $this->nullable($row, 'email');

                if ($this->useAccounts) {
                    $existingAccount = DB::table('accounts')->where('id', $sourceId)->first();

                    if ($existingAccount) {
                        if ($archiveEmail !== null && strtolower((string) $existingAccount->email) !== strtolower((string) $archiveEmail)) {
                            throw new MerchantRecoveryException('customer_identity_conflict', 'Customer identity could not be safely mapped for source id ' . $sourceId . '.');
                        }

                        $existingMembership = DB::table('tenant_memberships')
                            ->where('tenant_id', $this->tenantId)
                            ->where('account_id', $sourceId)
                            ->first();

                        if (!$existingMembership) {
                            $roleId = $this->customerRoleId();

                            $membershipId = DB::table('tenant_memberships')->insertGetId([
                                'account_id' => $sourceId,
                                'tenant_id' => $this->tenantId,
                                'role_id' => $roleId,
                                'is_owner' => false,
                                'status' => $this->string($row, 'status') ?: 'active',
                                'joined_at' => $this->nullable($row, 'joined_at') ?? now()->toDateTimeString(),
                                'is_default' => true,
                                'created_at' => now()->toDateTimeString(),
                                'updated_at' => now()->toDateTimeString(),
                            ]);
                        } else {
                            $membershipId = $existingMembership->id;
                        }

                        $this->maps['customers'][$sourceId] = $sourceId;
                        $this->maps['memberships'][$sourceId] = $membershipId;

                        if ($archiveEmail !== null && $archiveEmail !== '') {
                            $this->customerEmailMap[strtolower((string) $archiveEmail)] = [
                                'customer' => $sourceId,
                                'membership' => $membershipId,
                            ];
                        }

                        $this->counts['customers']++;

                        return;
                    }
                } else {
                    $existingUser = DB::table('users')
                        ->where('tenant_id', $this->tenantId)
                        ->where('id', $sourceId)
                        ->first();

                    if ($existingUser) {
                        if ($archiveEmail !== null && strtolower((string) $existingUser->email) !== strtolower((string) $archiveEmail)) {
                            throw new MerchantRecoveryException('customer_identity_conflict', 'Customer identity could not be safely mapped for source id ' . $sourceId . '.');
                        }

                        $this->maps['customers'][$sourceId] = $sourceId;
                        $this->maps['memberships'][$sourceId] = null;

                        if ($archiveEmail !== null && $archiveEmail !== '') {
                            $this->customerEmailMap[strtolower((string) $archiveEmail)] = [
                                'customer' => $sourceId,
                                'membership' => null,
                            ];
                        }

                        $this->counts['customers']++;

                        return;
                    }
                }
            }

            $joinedAt = $this->nullable($row, 'joined_at') ?? now()->toDateTimeString();
            $email = $this->aliasCustomerEmail($this->nullable($row, 'email'), $sourceId);
            $name = $this->string($row, 'name') ?: 'Restored Customer ' . $sourceId;
            $status = $this->string($row, 'status') ?: 'active';

            if ($this->useAccounts) {
                $accountId = DB::table('accounts')->insertGetId([
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(48)),
                    'status' => $status,
                    'created_at' => $joinedAt,
                    'updated_at' => $this->nullable($row, 'last_order_at') ?? $joinedAt,
                ]);

                $membershipId = DB::table('tenant_memberships')->insertGetId([
                    'account_id' => $accountId,
                    'tenant_id' => $this->tenantId,
                    'role_id' => $customerRoleId,
                    'is_owner' => false,
                    'status' => $status,
                    'joined_at' => $joinedAt,
                    'is_default' => true,
                    'created_at' => $joinedAt,
                    'updated_at' => $joinedAt,
                ]);

                DB::table('customer_profiles')->insert([
                    'tenant_membership_id' => $membershipId,
                    'name' => $name,
                    'phone' => $this->nullable($row, 'phone'),
                    'created_at' => $joinedAt,
                    'updated_at' => $joinedAt,
                ]);

                $this->maps['customers'][$sourceId] = $accountId;
                $this->maps['memberships'][$sourceId] = $membershipId;
            } else {
                $userId = DB::table('users')->insertGetId([
                    'tenant_id' => $this->tenantId,
                    'name' => $name,
                    'email' => $email,
                    'password' => Hash::make(Str::random(48)),
                    'status' => $status,
                    'is_owner' => false,
                    'allow_cod' => false,
                    'created_at' => $joinedAt,
                    'updated_at' => $this->nullable($row, 'last_order_at') ?? $joinedAt,
                ]);

                DB::table('model_has_roles')->insert([
                    'role_id' => $customerRoleId,
                    'model_type' => User::class,
                    'model_id' => $userId,
                ]);

                $this->maps['customers'][$sourceId] = $userId;
            }

            $this->counts['customers']++;
        });
    }

    private function createPlaceholderAccount(): int
    {
        if (DB::table('roles')->where('tenant_id', $this->tenantId)->where('name', 'customer')->where('guard_name', 'web')->doesntExist()) {
            throw new MerchantRecoveryException('missing_role', 'The recovered tenant is missing its customer role.');
        }

        $accountId = DB::table('accounts')->insertGetId([
            'name' => 'Recovered Order Identity',
            'email' => $this->aliasEmail(null, 'order-placeholder'),
            'password' => Hash::make(Str::random(48)),
            'status' => 'active',
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        $roleId = $this->customerRoleId();

        DB::table('tenant_memberships')->insert([
            'account_id' => $accountId,
            'tenant_id' => $this->tenantId,
            'role_id' => $roleId,
            'is_owner' => false,
            'status' => 'active',
            'joined_at' => now()->toDateTimeString(),
            'is_default' => false,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        return $accountId;
    }

    private function createPlaceholderUser(): int
    {
        $userId = DB::table('users')->insertGetId([
            'tenant_id' => $this->tenantId,
            'name' => 'Recovered Order Identity',
            'email' => $this->aliasEmail(null, 'order-placeholder'),
            'password' => Hash::make(Str::random(48)),
            'status' => 'active',
            'is_owner' => false,
            'allow_cod' => false,
            'created_at' => now()->toDateTimeString(),
            'updated_at' => now()->toDateTimeString(),
        ]);

        DB::table('model_has_roles')->insert([
            'role_id' => $this->customerRoleId(),
            'model_type' => User::class,
            'model_id' => $userId,
        ]);

        return $userId;
    }

    private function customerRoleId(): int
    {
        $roleId = DB::table('roles')
            ->where('tenant_id', $this->tenantId)
            ->where('name', 'customer')
            ->where('guard_name', 'web')
            ->value('id');

        if ($roleId === null) {
            throw new MerchantRecoveryException('missing_role', 'The recovered tenant is missing its customer role.');
        }

        return (int) $roleId;
    }

    private function importCategories(ZipArchive $zip): void
    {
        $this->maps['categories'] = [];
        $count = 0;

        $this->eachRow($zip, 'categories', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('categories')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $this->string($row, 'name'),
                'slug' => $this->nullable($row, 'slug'),
                'description' => $this->nullable($row, 'description'),
                'image' => $this->nullable($row, 'image'),
                'is_active' => $this->bool($row, 'is_active'),
                'featured' => $this->bool($row, 'featured'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'parent_id' => null,
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['categories'][$sourceId] = $newId;

            $parent = $this->nullable($row, 'parent_id');
            if ($parent !== null) {
                $this->pendingCategoryParents[$newId] = (int) $parent;
            }

            $count++;
        });

        foreach ($this->pendingCategoryParents as $newId => $sourceParent) {
            $mapped = $this->maps['categories'][$sourceParent] ?? null;

            if ($mapped === null) {
                throw new MerchantRecoveryException('missing_mapping', 'A recovered category references a parent that is not present in the archive.');
            }

            DB::table('categories')->where('id', $newId)->where('tenant_id', $this->tenantId)->update(['parent_id' => $mapped]);
        }

        $this->counts['categories'] = $count;
    }

    private function importBrands(ZipArchive $zip): void
    {
        $this->maps['brands'] = [];
        $count = 0;

        $this->eachRow($zip, 'brands', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('brands')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $this->string($row, 'name'),
                'slug' => $this->nullable($row, 'slug'),
                'description' => $this->nullable($row, 'description'),
                'logo' => $this->nullable($row, 'logo'),
                'banner' => $this->nullable($row, 'banner'),
                'featured' => $this->bool($row, 'featured'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['brands'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['brands'] = $count;
    }

    private function importUnits(ZipArchive $zip): void
    {
        $this->maps['units'] = [];
        $count = 0;

        $this->eachRow($zip, 'units', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('units')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $this->string($row, 'name'),
                'short_name' => $this->string($row, 'short_name'),
                'description' => $this->nullable($row, 'description'),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['units'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['units'] = $count;
    }

    private function importProducts(ZipArchive $zip): void
    {
        $this->maps['products'] = [];
        $count = 0;

        $categoryIds = $this->idsByName('categories');
        $brandIds = $this->idsByName('brands');
        $unitIds = $this->idsByName('units');

        $this->eachRow($zip, 'products', function (array $row) use (&$count, $categoryIds, $brandIds, $unitIds): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('products')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $this->string($row, 'name'),
                'slug' => $this->nullable($row, 'slug'),
                'sku' => $this->nullable($row, 'sku'),
                'barcode' => $this->nullable($row, 'barcode'),
                'description' => $this->nullable($row, 'description'),
                'short_description' => $this->nullable($row, 'short_description'),
                'price' => (float) ($row['price'] ?? 0),
                'sale_price' => $this->nullableNumber($row, 'sale_price'),
                'base_price' => (float) ($row['base_price'] ?? 0),
                'cost_price' => $this->nullableNumber($row, 'cost_price'),
                'stock' => (int) ($row['stock'] ?? 0),
                'low_stock_alert' => (int) ($row['low_stock_alert'] ?? 0),
                'status' => $this->string($row, 'status') ?: 'active',
                'featured' => $this->bool($row, 'featured'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'type' => $this->string($row, 'type') ?: 'single',
                'photo1' => $this->nullable($row, 'photo1'),
                'photo2' => $this->nullable($row, 'photo2'),
                'gallery_images' => $this->nullableJson($row, 'gallery_images'),
                'seo_title' => $this->nullable($row, 'seo_title'),
                'seo_description' => $this->nullable($row, 'seo_description'),
                'seo_keywords' => $this->nullable($row, 'seo_keywords'),
                'seo_image' => $this->nullable($row, 'seo_image'),
                'category_id' => $this->resolveByName($categoryIds, $this->string($row, 'category'), 'category', true),
                'brand_id' => $this->resolveByName($brandIds, $this->string($row, 'brand'), 'brand', false),
                'unit_id' => $this->resolveByName($unitIds, $this->string($row, 'unit'), 'unit', false),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['products'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['products'] = $count;
    }

    private function importProductVariants(ZipArchive $zip): void
    {
        $this->maps['variants'] = [];
        $count = 0;

        $this->eachRow($zip, 'product_variants', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('product_variants')->insertGetId([
                'tenant_id' => $this->tenantId,
                'product_id' => $this->requiredMap('products', $row, 'product_id'),
                'sku' => $this->nullable($row, 'sku'),
                'barcode' => $this->nullable($row, 'barcode'),
                'price' => $this->nullableNumber($row, 'price'),
                'compare_price' => $this->nullableNumber($row, 'compare_price'),
                'cost_price' => $this->nullableNumber($row, 'cost_price'),
                'stock' => (int) ($row['stock'] ?? 0),
                'low_stock_threshold' => (int) ($row['low_stock_threshold'] ?? 0),
                'image' => $this->nullable($row, 'image'),
                'attributes' => $this->nullableJson($row, 'attributes'),
                'status' => $this->string($row, 'status') ?: 'active',
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['variants'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['product_variants'] = $count;
    }

    private function importProductCombos(ZipArchive $zip): void
    {
        $count = 0;

        $this->eachRow($zip, 'product_combos', function (array $row) use (&$count): void {
            DB::table('product_combos')->insert([
                'tenant_id' => $this->tenantId,
                'product_id' => $this->requiredMap('products', $row, 'product_id'),
                'combo_product_id' => $this->requiredMap('products', $row, 'combo_product_id'),
                'linked_variant_id' => $this->optionalMap('variants', $row, 'linked_variant_id'),
                'quantity' => (int) ($row['quantity'] ?? 1),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
            ]);

            $count++;
        });

        $this->counts['product_combos'] = $count;
    }

    private function importCities(ZipArchive $zip): void
    {
        $this->maps['cities'] = [];
        $count = 0;

        $this->eachRow($zip, 'cities', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('cities')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $this->string($row, 'name'),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['cities'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['cities'] = $count;
    }

    private function importTownships(ZipArchive $zip): void
    {
        $this->maps['townships'] = [];
        $count = 0;

        $this->eachRow($zip, 'townships', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('townships')->insertGetId([
                'tenant_id' => $this->tenantId,
                'city_id' => $this->requiredMap('cities', $row, 'city_id'),
                'name' => $this->string($row, 'name'),
                'postal_code' => $this->nullable($row, 'postal_code'),
                'delivery_fee' => (float) ($row['delivery_fee'] ?? 0),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['townships'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['townships'] = $count;
    }

    private function importDeliveryServices(ZipArchive $zip): void
    {
        $this->maps['delivery_services'] = [];
        $count = 0;

        $this->eachRow($zip, 'delivery_services', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('delivery_services')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $this->string($row, 'name'),
                'code' => $this->string($row, 'code'),
                'description' => $this->nullable($row, 'description'),
                'base_fee' => (float) ($row['base_fee'] ?? 0),
                'fee_per_kg' => $this->nullableNumber($row, 'fee_per_kg'),
                'min_days' => (int) ($row['min_days'] ?? 1),
                'max_days' => (int) ($row['max_days'] ?? 1),
                'is_active' => $this->bool($row, 'is_active'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['delivery_services'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['delivery_services'] = $count;
    }

    private function importDeliveryPricing(ZipArchive $zip): void
    {
        $count = 0;

        $this->eachRow($zip, 'delivery_pricing', function (array $row) use (&$count): void {
            DB::table('delivery_pricing')->insert([
                'delivery_service_id' => $this->requiredMap('delivery_services', $row, 'delivery_service_id'),
                'township_id' => $this->optionalMap('townships', $row, 'township_id'),
                'min_days' => $this->nullableInt($row, 'min_days'),
                'max_days' => $this->nullableInt($row, 'max_days'),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $count++;
        });

        $this->counts['delivery_pricing'] = $count;
    }

    private function importWarehouses(ZipArchive $zip): void
    {
        $this->maps['warehouses'] = [];
        $count = 0;

        $this->eachRow($zip, 'warehouses', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('warehouses')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $this->string($row, 'name'),
                'code' => $this->nullable($row, 'code'),
                'description' => $this->nullable($row, 'description'),
                'address' => $this->nullable($row, 'address'),
                'is_default' => $this->bool($row, 'is_default'),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['warehouses'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['warehouses'] = $count;
    }

    private function importPackagingOptions(ZipArchive $zip): void
    {
        $this->maps['packaging_options'] = [];
        $count = 0;

        $this->eachRow($zip, 'packaging_options', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('packaging_options')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $this->string($row, 'name'),
                'code' => $this->string($row, 'code'),
                'description' => $this->nullable($row, 'description'),
                'fee' => (float) ($row['fee'] ?? 0),
                'is_active' => $this->bool($row, 'is_active'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['packaging_options'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['packaging_options'] = $count;
    }

    private function importPaymentMethods(ZipArchive $zip): void
    {
        $this->maps['payment_methods'] = [];
        $count = 0;

        $this->eachRow($zip, 'payment_methods', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('payment_methods')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $this->string($row, 'name'),
                'display_name' => $this->nullable($row, 'display_name'),
                'slug' => $this->nullable($row, 'slug'),
                'type' => $this->nullable($row, 'type'),
                'account_name' => $this->nullable($row, 'account_name'),
                'account_number' => $this->nullable($row, 'account_number'),
                'bank_name' => $this->nullable($row, 'bank_name'),
                'branch' => $this->nullable($row, 'branch'),
                'instructions' => $this->nullable($row, 'instructions'),
                'currency' => $this->nullable($row, 'currency'),
                'qr_image' => $this->nullable($row, 'qr_image'),
                'is_active' => $this->bool($row, 'is_active'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['payment_methods'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['payment_methods'] = $count;
    }

    private function importCodRules(ZipArchive $zip): void
    {
        $count = 0;

        $this->eachRow($zip, 'cod_rules', function (array $row) use (&$count): void {
            DB::table('cod_rules')->insert([
                'tenant_id' => $this->tenantId,
                'name' => $this->string($row, 'name'),
                'is_active' => $this->bool($row, 'is_active'),
                'min_order_amount' => $this->nullableNumber($row, 'min_order_amount'),
                'max_order_amount' => $this->nullableNumber($row, 'max_order_amount'),
                'allowed_city_ids' => $this->remapCityIds($this->nullableJson($row, 'allowed_city_ids')),
                'excluded_city_ids' => $this->remapCityIds($this->nullableJson($row, 'excluded_city_ids')),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $count++;
        });

        $this->counts['cod_rules'] = $count;
    }

    private function importInventoryMovements(ZipArchive $zip): void
    {
        $count = 0;
        $warehouseIds = $this->idsByName('warehouses');

        $this->eachRow($zip, 'inventory_movements', function (array $row) use (&$count, $warehouseIds): void {
            $productId = $this->requiredMap('products', $row, 'product_id');
            $variantId = $this->optionalMap('variants', $row, 'variant_id');
            $warehouseName = $this->string($row, 'warehouse');
            $warehouseId = $this->resolveByName($warehouseIds, $warehouseName, 'warehouse', false);
            $quantity = (float) ($row['quantity'] ?? 0);

            DB::table('stock_movements')->insert([
                'tenant_id' => $this->tenantId,
                'product_id' => $productId,
                'product_variant_id' => $variantId,
                'warehouse_id' => $warehouseId,
                'type' => $this->string($row, 'type'),
                'adjustment_number' => $this->adjustmentNumber($this->nullable($row, 'adjustment_number')),
                'quantity' => $quantity,
                'unit_price' => $this->nullableNumber($row, 'unit_price'),
                'reference_type' => null,
                'reference_id' => null,
                'description' => $this->nullable($row, 'description'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
            ]);

            $key = $this->inventoryKey($productId, $variantId, $warehouseName);
            $this->inventorySums[$key] = ($this->inventorySums[$key] ?? 0) + $quantity;

            $count++;
        });

        $this->counts['inventory_movements'] = $count;
    }

    private function importCoupons(ZipArchive $zip): void
    {
        $this->maps['coupons'] = [];
        $count = 0;

        $this->eachRow($zip, 'coupons', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('coupons')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $this->string($row, 'name'),
                'description' => $this->nullable($row, 'description'),
                'code' => $this->nullable($row, 'code'),
                'type' => $this->string($row, 'type'),
                'discount_value' => (float) ($row['discount_value'] ?? 0),
                'min_order_amount' => $this->nullableNumber($row, 'min_order_amount'),
                'discount_cap' => $this->nullableNumber($row, 'discount_cap'),
                'usage_limit' => $this->nullableInt($row, 'usage_limit'),
                'per_customer_limit' => $this->nullableInt($row, 'per_customer_limit'),
                'used_count' => (int) ($row['used_count'] ?? 0),
                'is_active' => $this->bool($row, 'is_active'),
                'starts_at' => $this->nullable($row, 'starts_at'),
                'expires_at' => $this->nullable($row, 'expires_at'),
                'priority' => (int) ($row['priority'] ?? 0),
                'is_stackable' => $this->bool($row, 'is_stackable'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['coupons'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['coupons'] = $count;

        $pivotCount = 0;
        $this->eachRow($zip, 'coupon_products', function (array $row) use (&$pivotCount): void {
            DB::table('coupon_product')->insert([
                'coupon_id' => $this->requiredMap('coupons', $row, 'coupon_id'),
                'product_id' => $this->requiredMap('products', $row, 'product_id'),
                'tenant_id' => $this->tenantId,
            ]);
            $pivotCount++;
        });
        $this->counts['coupon_products'] = $pivotCount;

        $categoryCount = 0;
        $this->eachRow($zip, 'coupon_categories', function (array $row) use (&$categoryCount): void {
            DB::table('coupon_category')->insert([
                'coupon_id' => $this->requiredMap('coupons', $row, 'coupon_id'),
                'category_id' => $this->requiredMap('categories', $row, 'category_id'),
                'tenant_id' => $this->tenantId,
            ]);
            $categoryCount++;
        });
        $this->counts['coupon_categories'] = $categoryCount;
    }

    private function importPromotions(ZipArchive $zip): void
    {
        $this->maps['promotions'] = [];
        $count = 0;

        $this->eachRow($zip, 'promotions', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('promotions')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $this->string($row, 'name'),
                'code' => $this->nullable($row, 'code'),
                'description' => $this->nullable($row, 'description'),
                'type' => $this->string($row, 'type'),
                'value' => (float) ($row['value'] ?? 0),
                'max_discount_amount' => $this->nullableNumber($row, 'max_discount_amount'),
                'minimum_order_amount' => $this->nullableNumber($row, 'minimum_order_amount'),
                'starts_at' => $this->nullable($row, 'starts_at'),
                'ends_at' => $this->nullable($row, 'ends_at'),
                'usage_limit' => $this->nullableInt($row, 'usage_limit'),
                'usage_count' => (int) ($row['usage_count'] ?? 0),
                'per_customer_limit' => $this->nullableInt($row, 'per_customer_limit'),
                'is_active' => $this->bool($row, 'is_active'),
                'is_automatic' => $this->bool($row, 'is_automatic'),
                'applies_to' => $this->string($row, 'applies_to') ?: 'all',
                'priority' => (int) ($row['priority'] ?? 0),
                'stackable' => $this->bool($row, 'stackable'),
                'created_by' => null,
                'created_by_type' => null,
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['promotions'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['promotions'] = $count;

        $productCount = 0;
        $this->eachRow($zip, 'promotion_products', function (array $row) use (&$productCount): void {
            DB::table('promotion_product')->insert([
                'promotion_id' => $this->requiredMap('promotions', $row, 'promotion_id'),
                'product_id' => $this->requiredMap('products', $row, 'product_id'),
                'tenant_id' => $this->tenantId,
            ]);
            $productCount++;
        });
        $this->counts['promotion_products'] = $productCount;

        $categoryCount = 0;
        $this->eachRow($zip, 'promotion_categories', function (array $row) use (&$categoryCount): void {
            DB::table('promotion_category')->insert([
                'promotion_id' => $this->requiredMap('promotions', $row, 'promotion_id'),
                'category_id' => $this->requiredMap('categories', $row, 'category_id'),
                'tenant_id' => $this->tenantId,
            ]);
            $categoryCount++;
        });
        $this->counts['promotion_categories'] = $categoryCount;
    }

    private function importFlashSales(ZipArchive $zip): void
    {
        $this->maps['flash_sales'] = [];
        $count = 0;

        $this->eachRow($zip, 'flash_sales', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('flash_sales')->insertGetId([
                'tenant_id' => $this->tenantId,
                'name' => $this->string($row, 'name'),
                'description' => $this->nullable($row, 'description'),
                'discount_type' => $this->string($row, 'discount_type'),
                'discount_value' => (float) ($row['discount_value'] ?? 0),
                'max_discount_amount' => $this->nullableNumber($row, 'max_discount_amount'),
                'starts_at' => $this->nullable($row, 'starts_at'),
                'ends_at' => $this->nullable($row, 'ends_at'),
                'is_active' => $this->bool($row, 'is_active'),
                'priority' => (int) ($row['priority'] ?? 0),
                'usage_limit' => $this->nullableInt($row, 'usage_limit'),
                'usage_count' => (int) ($row['usage_count'] ?? 0),
                'created_by' => null,
                'created_by_type' => null,
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['flash_sales'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['flash_sales'] = $count;

        $productCount = 0;
        $this->eachRow($zip, 'flash_sale_products', function (array $row) use (&$productCount): void {
            DB::table('flash_sale_products')->insert([
                'flash_sale_id' => $this->requiredMap('flash_sales', $row, 'flash_sale_id'),
                'product_id' => $this->requiredMap('products', $row, 'product_id'),
                'variant_id' => $this->optionalMap('variants', $row, 'variant_id'),
                'flash_price' => (float) ($row['flash_price'] ?? 0),
                'quantity_limit' => $this->nullableInt($row, 'quantity_limit'),
                'quantity_sold' => (int) ($row['quantity_sold'] ?? 0),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);
            $productCount++;
        });
        $this->counts['flash_sale_products'] = $productCount;
    }

    private function importOrders(ZipArchive $zip): void
    {
        $this->maps['orders'] = [];
        $count = 0;

        $cityIds = $this->idsByName('cities');
        $townshipIds = $this->idsByName('townships');
        $paymentIds = $this->idsByName('payment_methods');
        $deliveryIds = $this->idsByName('delivery_services');
        $packagingIds = $this->idsByName('packaging_options');
        $promotionIdsByCode = DB::table('promotions')
            ->where('tenant_id', $this->tenantId)
            ->whereNotNull('code')
            ->pluck('id', 'code');

        $this->eachRow($zip, 'orders', function (array $row) use (&$count, $cityIds, $townshipIds, $paymentIds, $deliveryIds, $packagingIds, $promotionIdsByCode): void {
            $sourceId = $this->sourceId($row);
            $promotionCode = $this->nullable($row, 'promotion_code');

            $mappedCustomerId = $this->placeholderIdentityId;
            $mappedMembershipId = null;

            if ($this->restoreExisting) {
                $orderEmail = strtolower(trim((string) ($this->nullable($row, 'email') ?? '')));

                if ($orderEmail !== '' && isset($this->customerEmailMap[$orderEmail])) {
                    $mappedCustomerId = $this->customerEmailMap[$orderEmail]['customer'];
                    $mappedMembershipId = $this->customerEmailMap[$orderEmail]['membership'];
                }
            }

            $newId = DB::table('orders')->insertGetId([
                'tenant_id' => $this->tenantId,
                'user_id' => $mappedCustomerId,
                'user_type' => $this->identityMorphType,
                'tenant_membership_id' => $mappedMembershipId,
                'invoice_number' => $this->nullable($row, 'invoice_number'),
                'customer_name' => $this->nullable($row, 'customer_name'),
                'first_name' => $this->string($row, 'first_name'),
                'last_name' => $this->string($row, 'last_name'),
                'phone' => $this->string($row, 'phone'),
                'email' => $this->nullable($row, 'email'),
                'address' => $this->string($row, 'address'),
                'postal_code' => $this->nullable($row, 'postal_code'),
                'city_id' => $this->resolveByName($cityIds, $this->string($row, 'city'), 'city', false),
                'township_id' => $this->resolveByName($townshipIds, $this->string($row, 'township'), 'township', false),
                'payment_method_id' => $this->resolveByName($paymentIds, $this->string($row, 'payment_method'), 'payment method', false),
                'delivery_service_id' => $this->resolveByName($deliveryIds, $this->string($row, 'delivery_service'), 'delivery service', false),
                'packaging_id' => $this->resolveByName($packagingIds, $this->string($row, 'packaging'), 'packaging', false),
                'packaging_fee' => $this->nullableNumber($row, 'packaging_fee'),
                'cod_fee' => $this->nullableNumber($row, 'cod_fee'),
                'delivery_days_min' => $this->nullableInt($row, 'delivery_days_min'),
                'delivery_days_max' => $this->nullableInt($row, 'delivery_days_max'),
                'subtotal' => $this->nullableNumber($row, 'subtotal'),
                'discount_amount' => (float) ($row['discount_amount'] ?? 0),
                'delivery_fee' => $this->nullableNumber($row, 'delivery_fee'),
                'total_amount' => $this->nullableNumber($row, 'total_amount'),
                'paid_amount' => $this->nullableNumber($row, 'paid_amount'),
                'payment_status' => $this->string($row, 'payment_status') ?: 'pending',
                'order_status' => $this->nullable($row, 'order_status'),
                'payment_verified_at' => $this->nullable($row, 'payment_verified_at'),
                'rejection_reason' => $this->nullable($row, 'rejection_reason'),
                'notes' => $this->nullable($row, 'notes'),
                'stock_reduced' => $this->bool($row, 'stock_reduced'),
                'promotion_id' => $promotionCode !== null ? ($promotionIdsByCode[$promotionCode] ?? null) : null,
                'promotion_code' => $promotionCode,
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['orders'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['orders'] = $count;

        $itemCount = 0;
        $this->eachRow($zip, 'order_items', function (array $row) use (&$itemCount): void {
            DB::table('order_items')->insert([
                'tenant_id' => $this->tenantId,
                'order_id' => $this->requiredMap('orders', $row, 'order_id'),
                'product_id' => $this->requiredMap('products', $row, 'product_id'),
                'variant_id' => $this->optionalMap('variants', $row, 'variant_id'),
                'flash_sale_id' => $this->optionalMap('flash_sales', $row, 'flash_sale_id'),
                'original_price' => $this->nullableNumber($row, 'original_price'),
                'quantity' => (int) ($row['quantity'] ?? 0),
                'price' => (float) ($row['price'] ?? 0),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
            ]);
            $itemCount++;
        });
        $this->counts['order_items'] = $itemCount;

        $couponCount = 0;
        $this->eachRow($zip, 'order_coupons', function (array $row) use (&$couponCount): void {
            DB::table('order_coupon')->insert([
                'tenant_id' => $this->tenantId,
                'order_id' => $this->requiredMap('orders', $row, 'order_id'),
                'coupon_id' => $this->requiredMap('coupons', $row, 'coupon_id'),
                'promotion_id' => null,
                'code' => $this->string($row, 'code'),
                'type' => $this->string($row, 'type'),
                'discount_amount' => (float) ($row['discount_amount'] ?? 0),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
            ]);
            $couponCount++;
        });
        $this->counts['order_coupons'] = $couponCount;

        $overrideCount = 0;
        $this->eachRow($zip, 'order_override_logs', function (array $row) use (&$overrideCount): void {
            $userId = $this->optionalMap('customers', $row, 'user_id') ?? $this->placeholderIdentityId;

            DB::table('order_override_logs')->insert([
                'tenant_id' => $this->tenantId,
                'order_id' => $this->requiredMap('orders', $row, 'order_id'),
                'user_id' => $userId,
                'user_type' => $this->identityMorphType,
                'field' => $this->string($row, 'field'),
                'old_value' => $this->string($row, 'old_value'),
                'new_value' => $this->string($row, 'new_value'),
                'reason' => $this->string($row, 'reason'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
            ]);
            $overrideCount++;
        });
        $this->counts['order_override_logs'] = $overrideCount;

        $usageCount = 0;
        $this->eachRow($zip, 'promotion_usages', function (array $row) use (&$usageCount): void {
            DB::table('promotion_usages')->insert([
                'tenant_id' => $this->tenantId,
                'promotion_id' => $this->requiredMap('promotions', $row, 'promotion_id'),
                'user_id' => $this->optionalMap('customers', $row, 'user_id'),
                'user_type' => $this->optionalMap('customers', $row, 'user_id') !== null ? $this->identityMorphType : null,
                'order_id' => $this->requiredMap('orders', $row, 'order_id'),
                'discount_amount' => (float) ($row['discount_amount'] ?? 0),
                'used_at' => $this->nullable($row, 'used_at') ?? now()->toDateTimeString(),
            ]);
            $usageCount++;
        });
        $this->counts['promotion_usages'] = $usageCount;
    }

    private function importCustomerRelations(ZipArchive $zip): void
    {
        $cityIds = $this->idsByName('cities');
        $townshipIds = $this->idsByName('townships');
        $addressCount = 0;

        $this->eachRow($zip, 'customer_addresses', function (array $row) use (&$addressCount, $cityIds, $townshipIds): void {
            DB::table('customer_addresses')->insert([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->optionalMap('customers', $row, 'user_id') ?? $this->placeholderIdentityId,
                'user_type' => $this->identityMorphType,
                'tenant_membership_id' => $this->optionalMap('memberships', $row, 'user_id'),
                'label' => $this->string($row, 'label'),
                'first_name' => $this->string($row, 'first_name'),
                'last_name' => $this->string($row, 'last_name'),
                'phone' => $this->string($row, 'phone'),
                'address_line' => $this->string($row, 'address_line'),
                'city_id' => $this->resolveByName($cityIds, $this->string($row, 'city'), 'city', false),
                'township_id' => $this->resolveByName($townshipIds, $this->string($row, 'township'), 'township', false),
                'postal_code' => $this->nullable($row, 'postal_code'),
                'is_default' => $this->bool($row, 'is_default'),
                'notes' => $this->nullable($row, 'notes'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);
            $addressCount++;
        });

        $this->counts['customer_addresses'] = $addressCount;

        $wishlistCount = 0;
        $this->eachRow($zip, 'wishlists', function (array $row) use (&$wishlistCount): void {
            DB::table('wishlists')->insert([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->optionalMap('customers', $row, 'user_id') ?? $this->placeholderIdentityId,
                'user_type' => $this->identityMorphType,
                'tenant_membership_id' => $this->optionalMap('memberships', $row, 'user_id'),
                'product_id' => $this->requiredMap('products', $row, 'product_id'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
            ]);
            $wishlistCount++;
        });

        $this->counts['wishlists'] = $wishlistCount;
    }

    private function importStorefronts(ZipArchive $zip): void
    {
        $this->maps['storefronts'] = [];
        $this->maps['storefront_media'] = [];
        $this->maps['storefront_navigations'] = [];
        $count = 0;

        $this->eachRow($zip, 'storefronts', function (array $row) use (&$count): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('storefronts')->insertGetId([
                'tenant_id' => $this->tenantId,
                'theme_id' => null,
                'status' => $this->string($row, 'status') ?: 'active',
                'draft_revision_id' => null,
                'published_revision_id' => null,
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['storefronts'][$sourceId] = $newId;
            $count++;
        });

        $this->counts['storefronts'] = $count;

        $mediaCount = 0;
        $this->eachRow($zip, 'storefront_media', function (array $row) use (&$mediaCount): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('storefront_media')->insertGetId([
                'tenant_id' => $this->tenantId,
                'storefront_id' => $this->optionalMap('storefronts', $row, 'storefront_id'),
                'key' => $this->string($row, 'key'),
                'path' => $this->string($row, 'path'),
                'original_name' => $this->nullable($row, 'original_name'),
                'mime_type' => $this->nullable($row, 'mime_type'),
                'size' => $this->nullableInt($row, 'size'),
                'alt_text' => $this->nullable($row, 'alt_text'),
                'metadata' => $this->nullableJson($row, 'metadata'),
                'is_visible' => $this->bool($row, 'is_visible'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['storefront_media'][$sourceId] = $newId;
            $mediaCount++;
        });
        $this->counts['storefront_media'] = $mediaCount;

        $contentCount = 0;
        $this->eachRow($zip, 'storefront_contents', function (array $row) use (&$contentCount): void {
            DB::table('storefront_contents')->insert([
                'tenant_id' => $this->tenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'labels' => $this->nullableJson($row, 'labels'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);
            $contentCount++;
        });
        $this->counts['storefront_contents'] = $contentCount;

        $tokenCount = 0;
        $this->eachRow($zip, 'storefront_design_tokens', function (array $row) use (&$tokenCount): void {
            DB::table('storefront_design_tokens')->insert([
                'tenant_id' => $this->tenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'tokens' => $this->nullableJson($row, 'tokens'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);
            $tokenCount++;
        });
        $this->counts['storefront_design_tokens'] = $tokenCount;

        $sectionCount = 0;
        $this->eachRow($zip, 'storefront_homepage_sections', function (array $row) use (&$sectionCount): void {
            DB::table('storefront_homepage_sections')->insert([
                'tenant_id' => $this->tenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'type' => $this->string($row, 'type'),
                'variant' => $this->string($row, 'variant'),
                'enabled' => $this->bool($row, 'enabled'),
                'desktop_visible' => $this->bool($row, 'desktop_visible'),
                'mobile_visible' => $this->bool($row, 'mobile_visible'),
                'position' => (int) ($row['position'] ?? 0),
                'configuration' => $this->nullableJson($row, 'configuration'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);
            $sectionCount++;
        });
        $this->counts['storefront_homepage_sections'] = $sectionCount;

        $navigationCount = 0;
        $this->eachRow($zip, 'storefront_navigations', function (array $row) use (&$navigationCount): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('storefront_navigations')->insertGetId([
                'tenant_id' => $this->tenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'settings' => $this->nullableJson($row, 'settings'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['storefront_navigations'][$sourceId] = $newId;
            $navigationCount++;
        });
        $this->counts['storefront_navigations'] = $navigationCount;

        $itemCount = 0;
        $this->eachRow($zip, 'storefront_navigation_items', function (array $row) use (&$itemCount): void {
            DB::table('storefront_navigation_items')->insert([
                'tenant_id' => $this->tenantId,
                'navigation_id' => $this->requiredMap('storefront_navigations', $row, 'navigation_id'),
                'key' => $this->string($row, 'key'),
                'group' => $this->string($row, 'group'),
                'label' => $this->string($row, 'label'),
                'path' => $this->string($row, 'path'),
                'icon' => $this->nullable($row, 'icon'),
                'enabled' => $this->bool($row, 'enabled'),
                'position' => (int) ($row['position'] ?? 0),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);
            $itemCount++;
        });
        $this->counts['storefront_navigation_items'] = $itemCount;

        $this->maps['storefront_revisions'] = [];
        $revisionCount = 0;
        $this->eachRow($zip, 'storefront_revisions', function (array $row) use (&$revisionCount): void {
            $sourceId = $this->sourceId($row);

            $newId = DB::table('storefront_revisions')->insertGetId([
                'tenant_id' => $this->tenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'revision_number' => (int) ($row['revision_number'] ?? 1),
                'status' => $this->string($row, 'status') ?: 'draft',
                'configuration' => $this->nullableJson($row, 'configuration'),
                'created_by_type' => null,
                'created_by_id' => null,
                'published_at' => $this->nullable($row, 'published_at'),
                'published_by_type' => null,
                'published_by_id' => null,
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);

            $this->maps['storefront_revisions'][$sourceId] = $newId;
            $revisionCount++;
        });
        $this->counts['storefront_revisions'] = $revisionCount;

        $this->eachRow($zip, 'storefronts', function (array $row): void {
            $sourceId = $this->sourceId($row);
            $newId = $this->maps['storefronts'][$sourceId];

            $draft = $this->optionalMap('storefront_revisions', $row, 'draft_revision_id');
            $published = $this->optionalMap('storefront_revisions', $row, 'published_revision_id');

            if ($draft !== null) {
                $this->pendingStorefrontDrafts[$newId] = $draft;
            }

            if ($published !== null) {
                $this->pendingStorefrontPublished[$newId] = $published;
            }
        });

        foreach ($this->pendingStorefrontDrafts as $newId => $revisionId) {
            DB::table('storefronts')->where('id', $newId)->where('tenant_id', $this->tenantId)->update(['draft_revision_id' => $revisionId]);
        }

        foreach ($this->pendingStorefrontPublished as $newId => $revisionId) {
            DB::table('storefronts')->where('id', $newId)->where('tenant_id', $this->tenantId)->update(['published_revision_id' => $revisionId]);
        }

        $themeCount = 0;
        $this->eachRow($zip, 'storefront_theme_configs', function (array $row) use (&$themeCount): void {
            DB::table('storefront_theme_configs')->insert([
                'tenant_id' => $this->tenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'theme_id' => null,
                'configuration' => $this->nullableJson($row, 'configuration'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);
            $themeCount++;
        });
        $this->counts['storefront_theme_configs'] = $themeCount;

        $checkoutCount = 0;
        $this->eachRow($zip, 'storefront_checkout_configs', function (array $row) use (&$checkoutCount): void {
            DB::table('storefront_checkout_configs')->insert([
                'tenant_id' => $this->tenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'configuration' => $this->nullableJson($row, 'configuration'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);
            $checkoutCount++;
        });
        $this->counts['storefront_checkout_configs'] = $checkoutCount;

        $displayCount = 0;
        $this->eachRow($zip, 'storefront_product_display_configs', function (array $row) use (&$displayCount): void {
            DB::table('storefront_product_display_configs')->insert([
                'tenant_id' => $this->tenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'configuration' => $this->nullableJson($row, 'configuration'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);
            $displayCount++;
        });
        $this->counts['storefront_product_display_configs'] = $displayCount;

        $bannerCount = 0;
        $this->eachRow($zip, 'promotion_banners', function (array $row) use (&$bannerCount): void {
            DB::table('promotion_banners')->insert([
                'tenant_id' => $this->tenantId,
                'promotion_id' => $this->optionalMap('promotions', $row, 'promotion_id'),
                'title' => $this->string($row, 'title'),
                'description' => $this->nullable($row, 'description'),
                'image' => $this->string($row, 'image'),
                'storefront_media_id' => $this->optionalMap('storefront_media', $row, 'storefront_media_id'),
                'link' => $this->string($row, 'link'),
                'cta_label' => $this->nullable($row, 'cta_label'),
                'is_active' => $this->bool($row, 'is_active'),
                'starts_at' => $this->nullable($row, 'starts_at'),
                'ends_at' => $this->nullable($row, 'ends_at'),
                'position' => (int) ($row['position'] ?? 0),
                'desktop_visible' => $this->bool($row, 'desktop_visible'),
                'mobile_visible' => $this->bool($row, 'mobile_visible'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);
            $bannerCount++;
        });
        $this->counts['promotion_banners'] = $bannerCount;
    }

    private function importWebsiteFaqs(ZipArchive $zip): void
    {
        $count = 0;

        $this->eachRow($zip, 'website_faqs', function (array $row) use (&$count): void {
            DB::table('website_faqs')->insert([
                'tenant_id' => $this->tenantId,
                'category' => $this->string($row, 'category'),
                'question_en' => $this->string($row, 'question_en'),
                'question_my' => $this->nullable($row, 'question_my'),
                'answer_en' => $this->string($row, 'answer_en'),
                'answer_my' => $this->nullable($row, 'answer_my'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'updated_at') ?? now()->toDateTimeString(),
            ]);
            $count++;
        });

        $this->counts['website_faqs'] = $count;
    }

    private function importWebsiteInfo(ZipArchive $zip): void
    {
        $values = [];
        $rows = 0;

        $this->eachRow($zip, 'store_settings', function (array $row) use (&$values, &$rows): void {
            $rows++;
            $key = $this->string($row, 'key');

            if ($key === '' || !preg_match('/^[a-z0-9_]+$/', $key)) {
                return;
            }

            $value = $this->nullable($row, 'value');

            if ($value !== null) {
                $values[$key] = $value;
            }
        });

        if ($values !== []) {
            $values['tenant_id'] = $this->tenantId;
            $values['created_at'] = now()->toDateTimeString();
            $values['updated_at'] = now()->toDateTimeString();

            DB::table('website_infos')->insert($values);
        }

        $this->counts['store_settings'] = $rows;
    }

    private function importSettings(ZipArchive $zip): void
    {
        $count = 0;

        $this->eachRow($zip, 'settings', function (array $row) use (&$count): void {
            $key = $this->string($row, 'key');

            if ($key === '') {
                return;
            }

            $updatedAt = $this->nullable($row, 'updated_at') ?? now()->toDateTimeString();

            DB::table('settings')->insert([
                'tenant_id' => $this->tenantId,
                'key' => $key,
                'value' => $this->nullable($row, 'value'),
                'created_at' => $updatedAt,
                'updated_at' => $updatedAt,
            ]);

            $count++;
        });

        $this->counts['settings'] = $count;
    }

    private function importImportHistories(ZipArchive $zip): void
    {
        $count = 0;

        $this->eachRow($zip, 'import_histories', function (array $row) use (&$count): void {
            DB::table('import_histories')->insert([
                'tenant_id' => $this->tenantId,
                'user_id' => $this->optionalMap('customers', $row, 'user_id'),
                'file_name' => $this->string($row, 'file_name'),
                'file_type' => $this->string($row, 'file_type'),
                'import_type' => $this->string($row, 'import_type'),
                'status' => $this->string($row, 'status'),
                'import_mode' => $this->nullable($row, 'import_mode'),
                'total_rows' => (int) ($row['total_rows'] ?? 0),
                'total_products' => (int) ($row['total_products'] ?? 0),
                'total_variants' => (int) ($row['total_variants'] ?? 0),
                'products_created' => (int) ($row['products_created'] ?? 0),
                'products_skipped' => (int) ($row['products_skipped'] ?? 0),
                'variants_created' => (int) ($row['variants_created'] ?? 0),
                'warning_count' => (int) ($row['warning_count'] ?? 0),
                'error_count' => (int) ($row['error_count'] ?? 0),
                'errors' => $this->nullableJson($row, 'errors'),
                'warnings' => $this->nullableJson($row, 'warnings'),
                'error_report_path' => $this->nullable($row, 'error_report_path'),
                'duration_ms' => $this->nullableInt($row, 'duration_ms'),
                'created_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
                'updated_at' => $this->nullable($row, 'created_at') ?? now()->toDateTimeString(),
            ]);
            $count++;
        });

        $this->counts['import_histories'] = $count;
    }

    private function verifyInventory(ZipArchive $zip): void
    {
        $seen = [];
        $count = 0;

        $this->eachRow($zip, 'inventory_levels', function (array $row) use (&$seen, &$count): void {
            $productId = $this->requiredMap('products', $row, 'product_id');
            $variantId = $this->optionalMap('variants', $row, 'variant_id');
            $warehouseName = $this->string($row, 'warehouse_name');
            $expected = (float) ($row['quantity'] ?? 0);

            $key = $this->inventoryKey($productId, $variantId, $warehouseName);

            if (!array_key_exists($key, $this->inventorySums)) {
                throw new MerchantRecoveryException('inventory_mismatch', 'A recovered inventory level has no matching stock movement.');
            }

            if (abs($this->inventorySums[$key] - $expected) > 0.0001) {
                throw new MerchantRecoveryException('inventory_mismatch', 'Recovered inventory levels do not match the reconstructed stock movements.');
            }

            $seen[$key] = true;
            $count++;
        });

        foreach (array_keys($this->inventorySums) as $key) {
            if (!isset($seen[$key])) {
                throw new MerchantRecoveryException('inventory_mismatch', 'A recovered stock movement is missing its inventory level.');
            }
        }

        $this->counts['inventory_levels'] = $count;
    }

    private function assertForeignReferences(): void
    {
        $pairs = [
            ['products', 'category_id', 'categories', true],
            ['products', 'brand_id', 'brands', true],
            ['products', 'unit_id', 'units', true],
            ['product_variants', 'product_id', 'products', false],
            ['product_combos', 'product_id', 'products', false],
            ['product_combos', 'combo_product_id', 'products', false],
            ['product_combos', 'linked_variant_id', 'product_variants', true],
            ['stock_movements', 'product_id', 'products', false],
            ['stock_movements', 'product_variant_id', 'product_variants', true],
            ['stock_movements', 'warehouse_id', 'warehouses', true],
            ['orders', 'city_id', 'cities', true],
            ['orders', 'township_id', 'townships', true],
            ['orders', 'payment_method_id', 'payment_methods', true],
            ['orders', 'delivery_service_id', 'delivery_services', true],
            ['orders', 'packaging_id', 'packaging_options', true],
            ['orders', 'promotion_id', 'promotions', true],
            ['order_items', 'order_id', 'orders', false],
            ['order_items', 'product_id', 'products', false],
            ['order_items', 'variant_id', 'product_variants', true],
            ['order_items', 'flash_sale_id', 'flash_sales', true],
            ['order_coupon', 'order_id', 'orders', false],
            ['order_coupon', 'coupon_id', 'coupons', false],
            ['order_override_logs', 'order_id', 'orders', false],
            ['coupon_product', 'coupon_id', 'coupons', false],
            ['coupon_product', 'product_id', 'products', false],
            ['coupon_category', 'coupon_id', 'coupons', false],
            ['coupon_category', 'category_id', 'categories', false],
            ['promotion_product', 'promotion_id', 'promotions', false],
            ['promotion_product', 'product_id', 'products', false],
            ['promotion_category', 'promotion_id', 'promotions', false],
            ['promotion_category', 'category_id', 'categories', false],
            ['promotion_usages', 'promotion_id', 'promotions', false],
            ['promotion_usages', 'order_id', 'orders', false],
            ['promotion_banners', 'promotion_id', 'promotions', true],
            ['promotion_banners', 'storefront_media_id', 'storefront_media', true],
            ['townships', 'city_id', 'cities', false],
            ['storefront_contents', 'storefront_id', 'storefronts', false],
            ['storefront_design_tokens', 'storefront_id', 'storefronts', false],
            ['storefront_homepage_sections', 'storefront_id', 'storefronts', false],
            ['storefront_media', 'storefront_id', 'storefronts', true],
            ['storefront_navigations', 'storefront_id', 'storefronts', false],
            ['storefront_navigation_items', 'navigation_id', 'storefront_navigations', false],
            ['storefront_revisions', 'storefront_id', 'storefronts', false],
            ['storefront_theme_configs', 'storefront_id', 'storefronts', false],
            ['storefront_checkout_configs', 'storefront_id', 'storefronts', false],
            ['storefront_product_display_configs', 'storefront_id', 'storefronts', false],
        ];

        foreach ($pairs as [$table, $column, $reference, $nullable]) {
            $this->assertReference($table, $column, $reference, $nullable);
        }

        $this->assertReference(
            'customer_addresses',
            'city_id',
            'cities',
            true
        );

        $this->assertReference(
            'customer_addresses',
            'township_id',
            'townships',
            true
        );

        $this->assertReference(
            'wishlists',
            'product_id',
            'products',
            false
        );

        $orphanPricing = DB::table('delivery_pricing')
            ->leftJoin('delivery_services', 'delivery_services.id', '=', 'delivery_pricing.delivery_service_id')
            ->whereNull('delivery_services.id')
            ->count();

        if ($orphanPricing > 0) {
            throw new MerchantRecoveryException('relationship_mismatch', 'Recovered delivery pricing references a service that was not imported.');
        }

        $orphanTownships = DB::table('delivery_pricing')
            ->join('delivery_services', 'delivery_services.id', '=', 'delivery_pricing.delivery_service_id')
            ->where('delivery_services.tenant_id', $this->tenantId)
            ->whereNotNull('delivery_pricing.township_id')
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('townships')
                    ->whereColumn('townships.id', 'delivery_pricing.township_id')
                    ->where('townships.tenant_id', $this->tenantId);
            })
            ->count();

        if ($orphanTownships > 0) {
            throw new MerchantRecoveryException('relationship_mismatch', 'Recovered delivery pricing references an unknown township.');
        }

        $orphanFlashSaleProducts = DB::table('flash_sale_products')
            ->join('flash_sales', 'flash_sales.id', '=', 'flash_sale_products.flash_sale_id')
            ->where('flash_sales.tenant_id', $this->tenantId)
            ->whereNotExists(function ($query): void {
                $query->selectRaw('1')
                    ->from('products')
                    ->whereColumn('products.id', 'flash_sale_products.product_id')
                    ->where('products.tenant_id', $this->tenantId);
            })
            ->count();

        if ($orphanFlashSaleProducts > 0) {
            throw new MerchantRecoveryException('relationship_mismatch', 'Recovered flash sale products reference an unknown product.');
        }
    }

    private function assertReference(string $table, string $column, string $reference, bool $nullable): void
    {
        $query = DB::table($table)->where($table . '.tenant_id', $this->tenantId);

        if ($nullable) {
            $query->whereNotNull($table . '.' . $column);
        }

        $missing = $query->whereNotExists(function ($subquery) use ($table, $column, $reference): void {
            $subquery->selectRaw('1')
                ->from($reference)
                ->whereColumn($reference . '.id', $table . '.' . $column)
                ->where($reference . '.tenant_id', $this->tenantId);
        })->count();

        if ($missing > 0) {
            throw new MerchantRecoveryException(
                'relationship_mismatch',
                sprintf('Recovered data in %s references a %s row from another tenant.', $table, $reference)
            );
        }
    }

    private function assertIdentityReferences(): void
    {
        $columns = [
            ['orders', 'user_id'],
            ['order_override_logs', 'user_id'],
            ['customer_addresses', 'user_id'],
            ['wishlists', 'user_id'],
            ['promotion_usages', 'user_id'],
            ['import_histories', 'user_id'],
        ];

        foreach ($columns as [$table, $column]) {
            $query = DB::table($table)
                ->where($table . '.tenant_id', $this->tenantId)
                ->whereNotNull($table . '.' . $column);

            if ($this->useAccounts) {
                $missing = $query->whereNotExists(function ($subquery) use ($table, $column): void {
                    $subquery->selectRaw('1')
                        ->from('tenant_memberships')
                        ->whereColumn('tenant_memberships.account_id', $table . '.' . $column)
                        ->where('tenant_memberships.tenant_id', $this->tenantId);
                })->count();
            } else {
                $missing = $query->whereNotExists(function ($subquery) use ($table, $column): void {
                    $subquery->selectRaw('1')
                        ->from('users')
                        ->whereColumn('users.id', $table . '.' . $column)
                        ->where('users.tenant_id', $this->tenantId);
                })->count();
            }

            if ($missing > 0) {
                throw new MerchantRecoveryException(
                    'relationship_mismatch',
                    sprintf('Recovered data in %s references a customer from another tenant.', $table)
                );
            }
        }

        if (!$this->useAccounts) {
            return;
        }

        $missingMemberships = DB::table('customer_addresses')
            ->where('tenant_id', $this->tenantId)
            ->whereNotNull('tenant_membership_id')
            ->whereNotExists(function ($subquery): void {
                $subquery->selectRaw('1')
                    ->from('tenant_memberships')
                    ->whereColumn('tenant_memberships.id', 'customer_addresses.tenant_membership_id')
                    ->where('tenant_memberships.tenant_id', $this->tenantId);
            })
            ->count();

        if ($missingMemberships > 0) {
            throw new MerchantRecoveryException('relationship_mismatch', 'Recovered customer addresses reference a membership from another tenant.');
        }
    }

    private function databaseCount(string $category): ?int
    {
        $tenantTables = [
            'categories' => 'categories',
            'brands' => 'brands',
            'units' => 'units',
            'products' => 'products',
            'product_variants' => 'product_variants',
            'product_combos' => 'product_combos',
            'inventory_movements' => 'stock_movements',
            'orders' => 'orders',
            'order_items' => 'order_items',
            'order_coupons' => 'order_coupon',
            'order_override_logs' => 'order_override_logs',
            'customer_addresses' => 'customer_addresses',
            'wishlists' => 'wishlists',
            'coupons' => 'coupons',
            'coupon_products' => 'coupon_product',
            'coupon_categories' => 'coupon_category',
            'promotions' => 'promotions',
            'promotion_products' => 'promotion_product',
            'promotion_categories' => 'promotion_category',
            'promotion_usages' => 'promotion_usages',
            'promotion_banners' => 'promotion_banners',
            'flash_sales' => 'flash_sales',
            'delivery_services' => 'delivery_services',
            'cod_rules' => 'cod_rules',
            'packaging_options' => 'packaging_options',
            'warehouses' => 'warehouses',
            'payment_methods' => 'payment_methods',
            'cities' => 'cities',
            'townships' => 'townships',
            'storefronts' => 'storefronts',
            'storefront_contents' => 'storefront_contents',
            'storefront_design_tokens' => 'storefront_design_tokens',
            'storefront_homepage_sections' => 'storefront_homepage_sections',
            'storefront_media' => 'storefront_media',
            'storefront_navigations' => 'storefront_navigations',
            'storefront_navigation_items' => 'storefront_navigation_items',
            'storefront_revisions' => 'storefront_revisions',
            'storefront_theme_configs' => 'storefront_theme_configs',
            'storefront_checkout_configs' => 'storefront_checkout_configs',
            'storefront_product_display_configs' => 'storefront_product_display_configs',
            'website_faqs' => 'website_faqs',
            'settings' => 'settings',
            'import_histories' => 'import_histories',
        ];

        if (isset($tenantTables[$category])) {
            return DB::table($tenantTables[$category])->where('tenant_id', $this->tenantId)->count();
        }

        return match ($category) {
            'customers' => $this->customerIdentityCount(),
            'delivery_pricing' => DB::table('delivery_pricing')
                ->join('delivery_services', 'delivery_services.id', '=', 'delivery_pricing.delivery_service_id')
                ->where('delivery_services.tenant_id', $this->tenantId)
                ->count(),
            'flash_sale_products' => DB::table('flash_sale_products')
                ->join('flash_sales', 'flash_sales.id', '=', 'flash_sale_products.flash_sale_id')
                ->where('flash_sales.tenant_id', $this->tenantId)
                ->count(),
            'store_settings' => $this->storeSettingsCount(),
            default => null,
        };
    }

    /**
     * `store_settings` is exported as one row per key but re-imported as a
     * single aggregated `website_infos` row. The previous check returned -1
     * whenever the recovered tenant had zero rows, which made a legitimately
     * empty source fail verification with a permanent count mismatch.
     */
    private function storeSettingsCount(): int
    {
        $expected = (int) ($this->counts['store_settings'] ?? 0);
        $rows = DB::table('website_infos')->where('tenant_id', $this->tenantId)->count();

        if ($expected === 0) {
            return $rows;
        }

        return $rows === 1 ? $expected : -1;
    }

    private function customerIdentityCount(): int
    {
        $roleId = $this->customerRoleId();

        if ($this->useAccounts) {
            return DB::table('tenant_memberships')
                ->where('tenant_id', $this->tenantId)
                ->where('role_id', $roleId)
                ->where('account_id', '!=', $this->placeholderIdentityId)
                ->count();
        }

        return DB::table('users')
            ->join('model_has_roles', 'model_has_roles.model_id', '=', 'users.id')
            ->where('users.tenant_id', $this->tenantId)
            ->where('model_has_roles.model_type', User::class)
            ->where('model_has_roles.role_id', $roleId)
            ->where('users.id', '!=', $this->placeholderIdentityId)
            ->count();
    }

    private function adjustmentNumber(?string $source): ?string
    {
        if ($source === null || $source === '') {
            return null;
        }

        $candidate = 'R' . $this->tenantId . '-' . $source;

        if (mb_strlen($candidate) > 30) {
            $candidate = 'R' . $this->tenantId . '-' . substr(sha1($source), 0, 20);
        }

        $base = $candidate;
        $suffix = 1;

        while (isset($this->usedAdjustmentNumbers[$candidate]) || DB::table('stock_movements')->where('adjustment_number', $candidate)->exists()) {
            $suffix++;
            $suffixText = '-' . $suffix;
            $candidate = mb_substr($base, 0, 30 - mb_strlen($suffixText)) . $suffixText;
        }

        $this->usedAdjustmentNumbers[$candidate] = $candidate;

        return $candidate;
    }

    private function remapCityIds(?string $json): ?string
    {
        if ($json === null || $json === '') {
            return null;
        }

        $decoded = json_decode($json, true);

        if (!is_array($decoded)) {
            return null;
        }

        $mapped = [];

        foreach ($decoded as $value) {
            $newId = $this->maps['cities'][(int) $value] ?? null;

            if ($newId !== null) {
                $mapped[] = $newId;
            }
        }

        return json_encode($mapped, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);
    }

    private function inventoryKey(int $productId, ?int $variantId, string $warehouseName): string
    {
        return $productId . '|' . ($variantId ?? 0) . '|' . $warehouseName;
    }

    /**
     * @return array<string, \Illuminate\Support\Collection>
     */
    private function idsByName(string $table): array
    {
        return DB::table($table)
            ->where('tenant_id', $this->tenantId)
            ->get(['id', 'name'])
            ->groupBy('name')
            ->all();
    }

    private function resolveByName(array $groups, string $name, string $label, bool $required): ?int
    {
        if ($name === '') {
            if ($required) {
                throw new MerchantRecoveryException('missing_reference', 'Missing required ' . $label . ' name in the archive.');
            }

            return null;
        }

        $matches = $groups[$name] ?? null;

        if ($matches === null || $matches->isEmpty()) {
            if ($required) {
                throw new MerchantRecoveryException('missing_reference', 'Missing target ' . $label . ': ' . $name);
            }

            return null;
        }

        if ($matches->count() > 1) {
            throw new MerchantRecoveryException('ambiguous_reference', 'Ambiguous ' . $label . ' name in the archive: ' . $name);
        }

        return (int) $matches->first()->id;
    }

    private function requiredMap(string $map, array $row, string $field): int
    {
        $source = $this->nullable($row, $field);

        if ($source === null || !isset($this->maps[$map][(int) $source])) {
            throw new MerchantRecoveryException('missing_mapping', sprintf('Missing %s mapping for %s=%s.', $map, $field, (string) $source));
        }

        return $this->maps[$map][(int) $source];
    }

    private function optionalMap(string $map, array $row, string $field): ?int
    {
        $source = $this->nullable($row, $field);

        if ($source === null) {
            return null;
        }

        return $this->maps[$map][(int) $source] ?? null;
    }

    private function sourceId(array $row): int
    {
        $id = (int) ($row['id'] ?? 0);

        if ($id <= 0) {
            throw new MerchantRecoveryException('invalid_row', 'The archive contains a row without a valid source identifier.');
        }

        return $id;
    }

    private function aliasCustomerEmail(?string $email, int $sourceId): string
    {
        return $this->aliasEmail($email, 'customer-' . $sourceId);
    }

    private function aliasEmail(?string $email, string $tag): string
    {
        $this->emailCounter++;
        $email = is_string($email) ? trim($email) : '';
        $domain = 'restored.invalid';
        $local = 'customer';

        if ($email !== '' && str_contains($email, '@')) {
            [$local, $domain] = explode('@', $email, 2);
            $local = preg_replace('/[^a-zA-Z0-9._-]/', '', $local) ?: 'customer';
            $domain = preg_replace('/[^a-zA-Z0-9.-]/', '', $domain) ?: 'restored.invalid';
        }

        $tag = substr(preg_replace('/[^a-zA-Z0-9._-]/', '-', $tag) ?: (string) $this->emailCounter, 0, 40);

        // The target tenant id is part of the alias so two recoveries of the
        // same archive into different stores can never generate the same
        // address. The per-import counter alone was not enough because it
        // restarts at 1 for every import.
        $scope = '-t' . $this->tenantId . '-' . $this->emailCounter;
        $localBudget = max(8, 64 - strlen($tag) - strlen($scope) - 1);
        $baseLocal = substr($local, 0, $localBudget) . '+' . $tag . $scope;
        $candidate = $baseLocal . '@' . $domain;
        $suffix = 1;

        while (DB::table('users')->where('email', $candidate)->exists() || DB::table('accounts')->where('email', $candidate)->exists()) {
            $suffix++;
            $candidate = $baseLocal . '-' . $suffix . '@' . $domain;
        }

        return $candidate;
    }

    /**
     * @param callable(array<string, string>): void $callback
     */
    private function eachRow(ZipArchive $zip, string $category, callable $callback): int
    {
        $stream = $zip->getStream($category . '.csv');

        if ($stream === false) {
            throw new MerchantRecoveryException('unreadable_entry', 'The archived dataset ' . $category . ' could not be read.');
        }

        $rows = 0;

        try {
            $header = fgetcsv($stream, 0, ',', '"', '\\');

            if (!is_array($header)) {
                throw new MerchantRecoveryException('malformed_csv', 'The archived dataset ' . $category . ' has no header.');
            }

            $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");

            while (($values = fgetcsv($stream, 0, ',', '"', '\\')) !== false) {
                if ($values === [null]) {
                    continue;
                }

                if (count($values) !== count($header)) {
                    throw new MerchantRecoveryException('malformed_csv', 'The archived dataset ' . $category . ' has a malformed row.');
                }

                $row = array_combine($header, array_map([$this, 'unsanitize'], $values));

                if (!is_array($row)) {
                    throw new MerchantRecoveryException('malformed_csv', 'The archived dataset ' . $category . ' has a malformed row.');
                }

                $callback($row);
                $rows++;
            }
        } finally {
            fclose($stream);
        }

        return $rows;
    }

    private function firstRow(ZipArchive $zip, string $category): ?array
    {
        $stream = $zip->getStream($category . '.csv');

        if ($stream === false) {
            return null;
        }

        try {
            $header = fgetcsv($stream, 0, ',', '"', '\\');

            if (!is_array($header)) {
                return null;
            }

            $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");
            $values = fgetcsv($stream, 0, ',', '"', '\\');

            if (!is_array($values) || $values === [null]) {
                return null;
            }

            return array_combine($header, array_map([$this, 'unsanitize'], $values)) ?: null;
        } finally {
            fclose($stream);
        }
    }

    private function unsanitize(string $value): string
    {
        if (strlen($value) > 1 && $value[0] === "'" && preg_match('/^[=+\-@\t\r]/', substr($value, 1)) === 1) {
            return substr($value, 1);
        }

        return $value;
    }

    private function string(array $row, string $field): string
    {
        return trim((string) ($row[$field] ?? ''));
    }

    private function nullable(array $row, string $field): ?string
    {
        $value = $this->string($row, $field);

        return $value === '' ? null : $value;
    }

    private function nullableNumber(array $row, string $field): ?float
    {
        $value = $this->nullable($row, $field);

        return $value === null ? null : (float) $value;
    }

    private function nullableInt(array $row, string $field): ?int
    {
        $value = $this->nullable($row, $field);

        return $value === null ? null : (int) $value;
    }

    private function bool(array $row, string $field): bool
    {
        return in_array($this->string($row, $field), ['1', 'true', 'yes'], true);
    }

    private function nullableJson(array $row, string $field): ?string
    {
        $value = $this->nullable($row, $field);

        if ($value === null) {
            return null;
        }

        json_decode($value, true);

        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new MerchantRecoveryException('invalid_json', 'The archive contains invalid JSON in a recoverable field.');
        }

        return $value;
    }
}
