<?php

namespace Tests\Support\MerchantRestoreDrill;

use App\Models\User;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Str;
use RuntimeException;
use ZipArchive;

class ArchiveImporter
{
    private string $workDir;
    private array $manifest = [];
    private array $rows = [];
    private array $idMaps = [];
    private int $targetTenantId;
    private int $placeholderUserId;
    private string $customerMorphType;

    public function __construct(
        private readonly string $archivePath,
        private readonly string $connection,
        private readonly array $categories
    ) {}

    public function import(int $targetTenantId): array
    {
        $this->targetTenantId = $targetTenantId;
        $this->extractArchive();
        $this->readAllRows();

        $this->insertCustomers();
        $this->insertCategories();
        $this->insertBrandsUnitsAndProducts();
        $this->insertDeliveryAndPaymentData();
        $this->insertInventoryMovements();
        $this->insertCouponsAndPromotions();
        $this->insertFlashSales();
        $this->insertOrders();
        $this->insertCustomerRelations();
        $this->insertStorefronts();
        $this->insertWebsiteAndSettings();
        $this->insertImportHistories();

        return [
            'manifest' => $this->manifest,
            'placeholder_user_id' => $this->placeholderUserId,
            'maps' => $this->idMaps,
        ];
    }

    public function rows(string $category): array
    {
        return $this->rows[$category] ?? [];
    }

    public function manifest(): array
    {
        return $this->manifest;
    }

    public function placeholderUserId(): int
    {
        return $this->placeholderUserId;
    }

    public function cleanup(): void
    {
        if (!isset($this->workDir) || !is_dir($this->workDir)) {
            return;
        }

        $items = new \RecursiveIteratorIterator(
            new \RecursiveDirectoryIterator($this->workDir, \FilesystemIterator::SKIP_DOTS),
            \RecursiveIteratorIterator::CHILD_FIRST
        );

        foreach ($items as $item) {
            if ($item->isDir()) {
                @rmdir($item->getPathname());
            } else {
                @unlink($item->getPathname());
            }
        }

        @rmdir($this->workDir);
    }

    public function __destruct()
    {
        $this->cleanup();
    }

    private function extractArchive(): void
    {
        $this->workDir = sys_get_temp_dir() . DIRECTORY_SEPARATOR . 'merchant-restore-drill-' . Str::lower(Str::random(12));

        if (!mkdir($this->workDir, 0775, true) && !is_dir($this->workDir)) {
            throw new RuntimeException('Unable to create the archive extraction directory.');
        }

        $zip = new ZipArchive();
        if ($zip->open($this->archivePath) !== true) {
            throw new RuntimeException('Unable to open the export archive.');
        }

        try {
            $entries = [];
            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entries[] = $zip->getNameIndex($i);
            }

            $expected = array_map(fn ($category) => $category . '.csv', array_keys($this->categories));
            $expected[] = 'manifest.json';
            $expected[] = 'assets.json';
            sort($expected);
            sort($entries);

            if ($entries !== $expected) {
                throw new RuntimeException('Archive entries do not match the configured export categories.');
            }

            if (!$zip->extractTo($this->workDir)) {
                throw new RuntimeException('Unable to extract the export archive.');
            }
        } finally {
            $zip->close();
        }

        $manifestPath = $this->workDir . DIRECTORY_SEPARATOR . 'manifest.json';
        $assetsPath = $this->workDir . DIRECTORY_SEPARATOR . 'assets.json';
        $manifest = json_decode((string) file_get_contents($manifestPath), true);
        $assets = file_get_contents($assetsPath);

        if (!is_array($manifest) || !is_string($assets)) {
            throw new RuntimeException('Unable to read the archive manifest or asset list.');
        }

        foreach ($manifest['files'] ?? [] as $name => $fileMeta) {
            $path = $this->workDir . DIRECTORY_SEPARATOR . $name;
            if (!is_file($path) || filesize($path) !== (int) ($fileMeta['bytes'] ?? -1) || hash_file('sha256', $path) !== ($fileMeta['sha256'] ?? null)) {
                throw new RuntimeException('Archive file integrity check failed for ' . $name);
            }
        }

        $this->manifest = $manifest;
    }

    private function readAllRows(): void
    {
        foreach (array_keys($this->categories) as $category) {
            $path = $this->workDir . DIRECTORY_SEPARATOR . $category . '.csv';
            $handle = fopen($path, 'r');
            if ($handle === false) {
                throw new RuntimeException('Unable to read ' . $category . '.csv');
            }

            $header = fgetcsv($handle, 0, ',', '"', '');
            if (!is_array($header)) {
                fclose($handle);
                throw new RuntimeException('Missing CSV header for ' . $category);
            }

            $header[0] = ltrim((string) $header[0], "\xEF\xBB\xBF");

            $parsed = [];
            while (($values = fgetcsv($handle, 0, ',', '"', '')) !== false) {
                if ($values === [null]) {
                    continue;
                }
                $parsed[] = array_combine($header, array_map([$this, 'unsanitize'], $values));
            }
            fclose($handle);
            $this->rows[$category] = $parsed;
        }
    }

    private function unsanitize(string $value): string
    {
        if (strlen($value) > 1 && $value[0] === "'" && preg_match('/^[=+\-@\t\r]/', substr($value, 1)) === 1) {
            return substr($value, 1);
        }

        return $value;
    }

    private function insertCustomers(): void
    {
        $conn = DB::connection($this->connection);
        $firstCustomer = $this->rows['customers'][0] ?? null;
        $customerType = $firstCustomer['customer_type'] ?? null;

        if ($customerType === 'account') {
            $this->insertAccountCustomers($conn);
            return;
        }

        if ($customerType !== 'user') {
            throw new RuntimeException('The merchant export does not declare a supported customer identity type.');
        }

        $this->customerMorphType = \App\Models\User::class;
        $this->placeholderUserId = 900000001;
        $this->assertIdFree('users', $this->placeholderUserId);

        $conn->table('users')->insert([
            'id' => $this->placeholderUserId,
            'tenant_id' => $this->targetTenantId,
            'name' => 'Restored Order Identity Placeholder',
            'email' => 'restore-order-placeholder-' . Str::lower(Str::random(10)) . '@example.test',
            'password' => Hash::make(Str::random(40)),
            'status' => 'active',
            'is_owner' => 0,
            'allow_cod' => 0,
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $this->idMaps['customers'] = [];
        $this->idMaps['memberships'] = [];

        foreach ($this->rows['customers'] as $row) {
            $userId = (int) $row['id'];
            $this->assertIdFree('users', $userId);

            $conn->table('users')->insert([
                'id' => $userId,
                'tenant_id' => $this->targetTenantId,
                'name' => $this->string($row, 'name') ?: 'Restored Customer ' . $userId,
                'email' => $this->string($row, 'email'),
                'password' => Hash::make(Str::random(40)),
                'status' => $this->string($row, 'status') ?: 'active',
                'is_owner' => 0,
                'allow_cod' => 0,
                'created_at' => $this->nullableTime($row, 'joined_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'last_order_at') ?? now(),
            ]);

            $this->idMaps['customers'][$userId] = $userId;
        }
    }

    private function insertAccountCustomers($conn): void
    {
        $this->customerMorphType = \App\Models\Account::class;
        $this->placeholderUserId = 900000001;
        $this->assertIdFree('accounts', $this->placeholderUserId);

        $conn->table('accounts')->insert([
            'id' => $this->placeholderUserId,
            'name' => 'Restored Order Identity Placeholder',
            'email' => 'restore-order-placeholder-' . Str::lower(Str::random(10)) . '@example.test',
            'password' => Hash::make(Str::random(40)),
            'status' => 'active',
            'created_at' => now(),
            'updated_at' => now(),
        ]);

        $roleId = $conn->table('roles')
            ->where('tenant_id', $this->targetTenantId)
            ->where('name', 'customer')
            ->where('guard_name', 'web')
            ->value('id');

        if ($roleId === null) {
            $roleId = $conn->table('roles')->insertGetId([
                'tenant_id' => $this->targetTenantId,
                'name' => 'customer',
                'guard_name' => 'web',
                'created_at' => now(),
                'updated_at' => now(),
            ]);
        }

        $this->idMaps['customers'] = [];
        $this->idMaps['memberships'] = [];

        foreach ($this->rows['customers'] as $row) {
            if (($row['customer_type'] ?? null) !== 'account') {
                throw new RuntimeException('Mixed customer identity types are not reconstructable in one drill.');
            }

            $accountId = (int) $row['id'];
            $this->assertIdFree('accounts', $accountId);

            $joinedAt = $this->nullableTime($row, 'joined_at') ?? now();
            $conn->table('accounts')->insert([
                'id' => $accountId,
                'name' => $this->string($row, 'name') ?: 'Restored Customer ' . $accountId,
                'email' => $this->string($row, 'email'),
                'password' => Hash::make(Str::random(40)),
                'status' => $this->string($row, 'status') ?: 'active',
                'created_at' => $joinedAt,
                'updated_at' => $this->nullableTime($row, 'last_order_at') ?? now(),
            ]);

            $membershipId = $conn->table('tenant_memberships')->insertGetId([
                'account_id' => $accountId,
                'tenant_id' => $this->targetTenantId,
                'role_id' => $roleId,
                'is_owner' => 0,
                'status' => $this->string($row, 'status') ?: 'active',
                'joined_at' => $joinedAt,
                'is_default' => 1,
                'created_at' => $joinedAt,
                'updated_at' => $joinedAt,
            ]);

            $conn->table('customer_profiles')->insert([
                'tenant_membership_id' => $membershipId,
                'name' => $this->string($row, 'name') ?: 'Restored Customer ' . $accountId,
                'phone' => $this->nullable($row, 'phone'),
                'created_at' => $joinedAt,
                'updated_at' => $joinedAt,
            ]);

            $this->idMaps['customers'][$accountId] = $accountId;
            $this->idMaps['memberships'][$accountId] = $membershipId;
        }
    }
    private function insertCategories(): void
    {
        $conn = DB::connection($this->connection);
        $this->idMaps['categories'] = [];

        foreach ($this->rows['categories'] as $row) {
            $id = (int) $row['id'];
            $conn->table('categories')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'name' => $this->string($row, 'name'),
                'slug' => $this->nullable($row, 'slug'),
                'description' => $this->nullable($row, 'description'),
                'image' => $this->nullable($row, 'image'),
                'is_active' => $this->bool($row, 'is_active'),
                'featured' => $this->bool($row, 'featured'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'parent_id' => null,
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['categories'][$id] = $id;
        }

        foreach ($this->rows['categories'] as $row) {
            $parentId = $this->nullable($row, 'parent_id');
            if ($parentId === null) {
                continue;
            }
            $conn->table('categories')->where('id', (int) $row['id'])->update(['parent_id' => (int) $parentId]);
        }
    }

    private function insertBrandsUnitsAndProducts(): void
    {
        $conn = DB::connection($this->connection);

        $this->idMaps['brands'] = [];
        foreach ($this->rows['brands'] as $row) {
            $id = (int) $row['id'];
            $conn->table('brands')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'name' => $this->string($row, 'name'),
                'slug' => $this->nullable($row, 'slug'),
                'description' => $this->nullable($row, 'description'),
                'logo' => $this->nullable($row, 'logo'),
                'banner' => $this->nullable($row, 'banner'),
                'featured' => $this->bool($row, 'featured'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['brands'][$id] = $id;
        }

        $this->idMaps['units'] = [];
        foreach ($this->rows['units'] as $row) {
            $id = (int) $row['id'];
            $conn->table('units')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'name' => $this->string($row, 'name'),
                'short_name' => $this->string($row, 'short_name'),
                'description' => $this->nullable($row, 'description'),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['units'][$id] = $id;
        }

        $categoryIdsByName = $conn->table('categories')
            ->where('tenant_id', $this->targetTenantId)
            ->get()
            ->groupBy('name');

        $brandIdsByName = $conn->table('brands')
            ->where('tenant_id', $this->targetTenantId)
            ->get()
            ->groupBy('name');

        $unitIdsByName = $conn->table('units')
            ->where('tenant_id', $this->targetTenantId)
            ->get()
            ->groupBy('name');

        $this->idMaps['products'] = [];
        foreach ($this->rows['products'] as $row) {
            $id = (int) $row['id'];
            $categoryId = $this->uniqueIdByName($categoryIdsByName, $this->string($row, 'category'), 'category', true);

            $conn->table('products')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'name' => $this->string($row, 'name'),
                'slug' => $this->nullable($row, 'slug'),
                'sku' => $this->nullable($row, 'sku'),
                'barcode' => $this->nullable($row, 'barcode'),
                'description' => $this->nullable($row, 'description'),
                'short_description' => $this->nullable($row, 'short_description'),
                'price' => (float) $row['price'],
                'sale_price' => $this->nullableNumber($row, 'sale_price'),
                'base_price' => (float) $row['base_price'],
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
                'category_id' => $categoryId,
                'brand_id' => $this->uniqueIdByName($brandIdsByName, $this->string($row, 'brand'), 'brand', false),
                'unit_id' => $this->uniqueIdByName($unitIdsByName, $this->string($row, 'unit'), 'unit', false),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['products'][$id] = $id;
        }

        $this->idMaps['variants'] = [];
        foreach ($this->rows['product_variants'] as $row) {
            $id = (int) $row['id'];
            $productId = $this->requiredMap('products', $row, 'product_id');
            $conn->table('product_variants')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'product_id' => $productId,
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
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['variants'][$id] = $id;
        }

        foreach ($this->rows['product_combos'] as $row) {
            $conn->table('product_combos')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'product_id' => $this->requiredMap('products', $row, 'product_id'),
                'combo_product_id' => $this->requiredMap('products', $row, 'combo_product_id'),
                'linked_variant_id' => $this->optionalMap('variants', $row, 'linked_variant_id'),
                'quantity' => (int) ($row['quantity'] ?? 1),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'created_at') ?? now(),
            ]);
        }
    }

    private function insertInventoryMovements(): void
    {
        $conn = DB::connection($this->connection);
        $warehouseIdsByName = $conn->table('warehouses')
            ->where('tenant_id', $this->targetTenantId)
            ->get()
            ->groupBy('name');

        foreach ($this->rows['inventory_movements'] as $row) {
            $warehouseId = $this->uniqueIdByName($warehouseIdsByName, $this->string($row, 'warehouse'), 'warehouse', false);

            $conn->table('stock_movements')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'product_id' => $this->requiredMap('products', $row, 'product_id'),
                'product_variant_id' => $this->optionalMap('variants', $row, 'variant_id'),
                'warehouse_id' => $warehouseId,
                'type' => $this->string($row, 'type'),
                'adjustment_number' => $this->nullable($row, 'adjustment_number'),
                'quantity' => (int) $row['quantity'],
                'unit_price' => $this->nullableNumber($row, 'unit_price'),
                'reference_type' => $this->nullable($row, 'reference_type'),
                'reference_id' => $this->nullable($row, 'reference_id') === null ? null : (int) $row['reference_id'],
                'description' => $this->nullable($row, 'description'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'created_at') ?? now(),
            ]);
        }
    }

    private function insertCouponsAndPromotions(): void
    {
        $conn = DB::connection($this->connection);

        $this->idMaps['coupons'] = [];
        foreach ($this->rows['coupons'] as $row) {
            $id = (int) $row['id'];
            $conn->table('coupons')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'name' => $this->string($row, 'name'),
                'description' => $this->nullable($row, 'description'),
                'code' => $this->nullable($row, 'code'),
                'type' => $this->string($row, 'type'),
                'discount_value' => (float) $row['discount_value'],
                'min_order_amount' => $this->nullableNumber($row, 'min_order_amount'),
                'discount_cap' => $this->nullableNumber($row, 'discount_cap'),
                'usage_limit' => $this->nullableInteger($row, 'usage_limit'),
                'per_customer_limit' => $this->nullableInteger($row, 'per_customer_limit'),
                'used_count' => (int) ($row['used_count'] ?? 0),
                'is_active' => $this->bool($row, 'is_active'),
                'starts_at' => $this->nullableTime($row, 'starts_at'),
                'expires_at' => $this->nullableTime($row, 'expires_at'),
                'priority' => (int) ($row['priority'] ?? 0),
                'is_stackable' => $this->bool($row, 'is_stackable'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['coupons'][$id] = $id;
        }

        foreach ($this->rows['coupon_products'] as $row) {
            $conn->table('coupon_product')->updateOrInsert(
                [
                    'coupon_id' => $this->requiredMap('coupons', $row, 'coupon_id'),
                    'product_id' => $this->requiredMap('products', $row, 'product_id'),
                ],
                ['tenant_id' => $this->targetTenantId]
            );
        }

        foreach ($this->rows['coupon_categories'] as $row) {
            $conn->table('coupon_category')->updateOrInsert(
                [
                    'coupon_id' => $this->requiredMap('coupons', $row, 'coupon_id'),
                    'category_id' => $this->requiredMap('categories', $row, 'category_id'),
                ],
                ['tenant_id' => $this->targetTenantId]
            );
        }

        $this->idMaps['promotions'] = [];
        foreach ($this->rows['promotions'] as $row) {
            $id = (int) $row['id'];
            $conn->table('promotions')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'name' => $this->string($row, 'name'),
                'code' => $this->nullable($row, 'code'),
                'description' => $this->nullable($row, 'description'),
                'type' => $this->string($row, 'type'),
                'value' => (float) $row['value'],
                'max_discount_amount' => $this->nullableNumber($row, 'max_discount_amount'),
                'minimum_order_amount' => $this->nullableNumber($row, 'minimum_order_amount'),
                'starts_at' => $this->nullableTime($row, 'starts_at'),
                'ends_at' => $this->nullableTime($row, 'ends_at'),
                'usage_limit' => $this->nullableInteger($row, 'usage_limit'),
                'usage_count' => (int) ($row['usage_count'] ?? 0),
                'per_customer_limit' => $this->nullableInteger($row, 'per_customer_limit'),
                'is_active' => $this->bool($row, 'is_active'),
                'is_automatic' => $this->bool($row, 'is_automatic'),
                'applies_to' => $this->string($row, 'applies_to') ?: 'all',
                'priority' => (int) ($row['priority'] ?? 0),
                'stackable' => $this->bool($row, 'stackable'),
                'created_by' => $this->nullable($row, 'created_by'),
                'created_by_type' => $this->nullable($row, 'created_by_type'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['promotions'][$id] = $id;
        }

        foreach ($this->rows['promotion_products'] as $row) {
            $conn->table('promotion_product')->updateOrInsert(
                [
                    'promotion_id' => $this->requiredMap('promotions', $row, 'promotion_id'),
                    'product_id' => $this->requiredMap('products', $row, 'product_id'),
                ],
                ['tenant_id' => $this->targetTenantId]
            );
        }

        foreach ($this->rows['promotion_categories'] as $row) {
            $conn->table('promotion_category')->updateOrInsert(
                [
                    'promotion_id' => $this->requiredMap('promotions', $row, 'promotion_id'),
                    'category_id' => $this->requiredMap('categories', $row, 'category_id'),
                ],
                ['tenant_id' => $this->targetTenantId]
            );
        }
    }

    private function insertFlashSales(): void
    {
        $conn = DB::connection($this->connection);

        $this->idMaps['flash_sales'] = [];
        foreach ($this->rows['flash_sales'] as $row) {
            $id = (int) $row['id'];
            $conn->table('flash_sales')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'name' => $this->string($row, 'name'),
                'description' => $this->nullable($row, 'description'),
                'discount_type' => $this->string($row, 'discount_type'),
                'discount_value' => (float) $row['discount_value'],
                'max_discount_amount' => $this->nullableNumber($row, 'max_discount_amount'),
                'starts_at' => $this->nullableTime($row, 'starts_at'),
                'ends_at' => $this->nullableTime($row, 'ends_at'),
                'is_active' => $this->bool($row, 'is_active'),
                'priority' => (int) ($row['priority'] ?? 0),
                'usage_limit' => $this->nullableInteger($row, 'usage_limit'),
                'usage_count' => (int) ($row['usage_count'] ?? 0),
                'created_by' => $this->nullable($row, 'created_by'),
                'created_by_type' => $this->nullable($row, 'created_by_type'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['flash_sales'][$id] = $id;
        }

        foreach ($this->rows['flash_sale_products'] as $row) {
            $conn->table('flash_sale_products')->insert([
                'id' => (int) $row['id'],
                'flash_sale_id' => $this->requiredMap('flash_sales', $row, 'flash_sale_id'),
                'product_id' => $this->requiredMap('products', $row, 'product_id'),
                'variant_id' => $this->optionalMap('variants', $row, 'variant_id'),
                'flash_price' => (float) $row['flash_price'],
                'quantity_limit' => $this->nullableInteger($row, 'quantity_limit'),
                'quantity_sold' => (int) ($row['quantity_sold'] ?? 0),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }
    }

    private function insertDeliveryAndPaymentData(): void
    {
        $conn = DB::connection($this->connection);

        $this->idMaps['cities'] = [];
        foreach ($this->rows['cities'] as $row) {
            $id = (int) $row['id'];
            $conn->table('cities')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'name' => $this->string($row, 'name'),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['cities'][$id] = $id;
        }

        $this->idMaps['townships'] = [];
        foreach ($this->rows['townships'] as $row) {
            $id = (int) $row['id'];
            $conn->table('townships')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'city_id' => $this->requiredMap('cities', $row, 'city_id'),
                'name' => $this->string($row, 'name'),
                'postal_code' => $this->nullable($row, 'postal_code'),
                'delivery_fee' => (float) ($row['delivery_fee'] ?? 0),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['townships'][$id] = $id;
        }

        $this->idMaps['delivery_services'] = [];
        foreach ($this->rows['delivery_services'] as $row) {
            $id = (int) $row['id'];
            $conn->table('delivery_services')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'name' => $this->string($row, 'name'),
                'code' => $this->string($row, 'code'),
                'description' => $this->nullable($row, 'description'),
                'base_fee' => (float) ($row['base_fee'] ?? 0),
                'fee_per_kg' => $this->nullableNumber($row, 'fee_per_kg'),
                'min_days' => (int) ($row['min_days'] ?? 1),
                'max_days' => (int) ($row['max_days'] ?? 1),
                'is_active' => $this->bool($row, 'is_active'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['delivery_services'][$id] = $id;
        }

        foreach ($this->rows['delivery_pricing'] as $row) {
            $conn->table('delivery_pricing')->insert([
                'id' => (int) $row['id'],
                'delivery_service_id' => $this->requiredMap('delivery_services', $row, 'delivery_service_id'),
                'township_id' => $this->optionalMap('townships', $row, 'township_id'),
                'min_days' => $this->nullableInteger($row, 'min_days'),
                'max_days' => $this->nullableInteger($row, 'max_days'),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }

        $this->idMaps['warehouses'] = [];
        foreach ($this->rows['warehouses'] as $row) {
            $id = (int) $row['id'];
            $conn->table('warehouses')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'name' => $this->string($row, 'name'),
                'code' => $this->nullable($row, 'code'),
                'description' => $this->nullable($row, 'description'),
                'address' => $this->nullable($row, 'address'),
                'is_default' => $this->bool($row, 'is_default'),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['warehouses'][$id] = $id;
        }

        $this->idMaps['packaging_options'] = [];
        foreach ($this->rows['packaging_options'] as $row) {
            $id = (int) $row['id'];
            $conn->table('packaging_options')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'name' => $this->string($row, 'name'),
                'code' => $this->string($row, 'code'),
                'description' => $this->nullable($row, 'description'),
                'fee' => (float) ($row['fee'] ?? 0),
                'is_active' => $this->bool($row, 'is_active'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['packaging_options'][$id] = $id;
        }

        $this->idMaps['payment_methods'] = [];
        foreach ($this->rows['payment_methods'] as $row) {
            $id = (int) $row['id'];
            $conn->table('payment_methods')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
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
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['payment_methods'][$id] = $id;
        }

        foreach ($this->rows['cod_rules'] as $row) {
            $conn->table('cod_rules')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'name' => $this->string($row, 'name'),
                'is_active' => $this->bool($row, 'is_active'),
                'min_order_amount' => $this->nullableNumber($row, 'min_order_amount'),
                'max_order_amount' => $this->nullableNumber($row, 'max_order_amount'),
                'allowed_city_ids' => $this->nullableJson($row, 'allowed_city_ids'),
                'excluded_city_ids' => $this->nullableJson($row, 'excluded_city_ids'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }
    }

    private function insertOrders(): void
    {
        $conn = DB::connection($this->connection);
        $cityIdsByName = $conn->table('cities')->where('tenant_id', $this->targetTenantId)->get()->groupBy('name');
        $townshipIdsByName = $conn->table('townships')->where('tenant_id', $this->targetTenantId)->get()->groupBy('name');
        $paymentIdsByName = $conn->table('payment_methods')->where('tenant_id', $this->targetTenantId)->get()->groupBy('name');
        $deliveryIdsByName = $conn->table('delivery_services')->where('tenant_id', $this->targetTenantId)->get()->groupBy('name');
        $packagingIdsByName = $conn->table('packaging_options')->where('tenant_id', $this->targetTenantId)->get()->groupBy('name');
        $promotionIdsByCode = $conn->table('promotions')
            ->where('tenant_id', $this->targetTenantId)
            ->whereNotNull('code')
            ->pluck('id', 'code');

        $this->idMaps['orders'] = [];
        foreach ($this->rows['orders'] as $row) {
            $id = (int) $row['id'];
            $promotionCode = $this->nullable($row, 'promotion_code');

            $conn->table('orders')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'user_id' => $this->placeholderUserId,
                'user_type' => $this->customerMorphType,
                'tenant_membership_id' => null,
                'invoice_number' => $this->nullable($row, 'invoice_number'),
                'customer_name' => $this->nullable($row, 'customer_name'),
                'first_name' => $this->string($row, 'first_name'),
                'last_name' => $this->string($row, 'last_name'),
                'phone' => $this->string($row, 'phone'),
                'email' => $this->nullable($row, 'email'),
                'address' => $this->string($row, 'address'),
                'postal_code' => $this->nullable($row, 'postal_code'),
                'city_id' => $this->uniqueIdByName($cityIdsByName, $this->string($row, 'city'), 'city', false),
                'township_id' => $this->uniqueIdByName($townshipIdsByName, $this->string($row, 'township'), 'township', false),
                'payment_method_id' => $this->uniqueIdByName($paymentIdsByName, $this->string($row, 'payment_method'), 'payment method', false),
                'delivery_service_id' => $this->uniqueIdByName($deliveryIdsByName, $this->string($row, 'delivery_service'), 'delivery service', false),
                'packaging_id' => $this->uniqueIdByName($packagingIdsByName, $this->string($row, 'packaging'), 'packaging', false),
                'packaging_fee' => $this->nullableNumber($row, 'packaging_fee'),
                'cod_fee' => $this->nullableNumber($row, 'cod_fee'),
                'delivery_days_min' => $this->nullableInteger($row, 'delivery_days_min'),
                'delivery_days_max' => $this->nullableInteger($row, 'delivery_days_max'),
                'subtotal' => $this->nullableNumber($row, 'subtotal'),
                'discount_amount' => (float) ($row['discount_amount'] ?? 0),
                'delivery_fee' => $this->nullableNumber($row, 'delivery_fee'),
                'total_amount' => $this->nullableNumber($row, 'total_amount'),
                'paid_amount' => $this->nullableNumber($row, 'paid_amount'),
                'payment_status' => $this->string($row, 'payment_status') ?: 'pending',
                'order_status' => $this->nullable($row, 'order_status'),
                'payment_verified_at' => $this->nullableTime($row, 'payment_verified_at'),
                'rejection_reason' => $this->nullable($row, 'rejection_reason'),
                'notes' => $this->nullable($row, 'notes'),
                'stock_reduced' => $this->bool($row, 'stock_reduced'),
                'promotion_id' => $promotionCode !== null ? ($promotionIdsByCode[$promotionCode] ?? null) : null,
                'promotion_code' => $promotionCode,
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['orders'][$id] = $id;
        }

        foreach ($this->rows['order_items'] as $row) {
            $conn->table('order_items')->insert([
                'tenant_id' => $this->targetTenantId,
                'order_id' => $this->requiredMap('orders', $row, 'order_id'),
                'product_id' => $this->requiredMap('products', $row, 'product_id'),
                'variant_id' => $this->optionalMap('variants', $row, 'variant_id'),
                'flash_sale_id' => $this->optionalMap('flash_sales', $row, 'flash_sale_id'),
                'original_price' => $this->nullableNumber($row, 'original_price'),
                'quantity' => (int) $row['quantity'],
                'price' => (float) $row['price'],
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'created_at') ?? now(),
            ]);
        }

        foreach ($this->rows['order_coupons'] as $row) {
            $conn->table('order_coupon')->insert([
                'tenant_id' => $this->targetTenantId,
                'order_id' => $this->requiredMap('orders', $row, 'order_id'),
                'coupon_id' => $this->requiredMap('coupons', $row, 'coupon_id'),
                'promotion_id' => null,
                'code' => $this->string($row, 'code'),
                'type' => $this->string($row, 'type'),
                'discount_amount' => (float) $row['discount_amount'],
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'created_at') ?? now(),
            ]);
        }

        foreach ($this->rows['order_override_logs'] as $row) {
            $conn->table('order_override_logs')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'order_id' => $this->requiredMap('orders', $row, 'order_id'),
                'user_id' => $this->optionalMap('customers', $row, 'user_id') ?? $this->placeholderUserId,
                'user_type' => $this->nullable($row, 'user_type') ?? $this->customerMorphType,
                'field' => $this->string($row, 'field'),
                'old_value' => $this->string($row, 'old_value'),
                'new_value' => $this->string($row, 'new_value'),
                'reason' => $this->string($row, 'reason'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'created_at') ?? now(),
            ]);
        }

        foreach ($this->rows['promotion_usages'] as $row) {
            $conn->table('promotion_usages')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'promotion_id' => $this->requiredMap('promotions', $row, 'promotion_id'),
                'user_id' => $this->optionalMap('customers', $row, 'user_id'),
                'user_type' => $this->nullable($row, 'user_type'),
                'order_id' => $this->requiredMap('orders', $row, 'order_id'),
                'discount_amount' => (float) $row['discount_amount'],
                'used_at' => $this->nullableTime($row, 'used_at') ?? now(),
            ]);
        }
    }

    private function insertCustomerRelations(): void
    {
        $conn = DB::connection($this->connection);
        $cityIdsByName = $conn->table('cities')->where('tenant_id', $this->targetTenantId)->get()->groupBy('name');
        $townshipIdsByName = $conn->table('townships')->where('tenant_id', $this->targetTenantId)->get()->groupBy('name');

        foreach ($this->rows['customer_addresses'] as $row) {
            $conn->table('customer_addresses')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'user_id' => $this->requiredMap('customers', $row, 'user_id'),
                'user_type' => $this->nullable($row, 'user_type') ?? $this->customerMorphType,
                'tenant_membership_id' => $this->optionalMap('memberships', $row, 'user_id'),
                'label' => $this->string($row, 'label'),
                'first_name' => $this->string($row, 'first_name'),
                'last_name' => $this->string($row, 'last_name'),
                'phone' => $this->string($row, 'phone'),
                'address_line' => $this->string($row, 'address_line'),
                'city_id' => $this->uniqueIdByName($cityIdsByName, $this->string($row, 'city'), 'city', false),
                'township_id' => $this->uniqueIdByName($townshipIdsByName, $this->string($row, 'township'), 'township', false),
                'postal_code' => $this->nullable($row, 'postal_code'),
                'is_default' => $this->bool($row, 'is_default'),
                'notes' => $this->nullable($row, 'notes'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }

        foreach ($this->rows['wishlists'] as $row) {
            $conn->table('wishlists')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'user_id' => $this->requiredMap('customers', $row, 'user_id'),
                'user_type' => $this->nullable($row, 'user_type') ?? $this->customerMorphType,
                'tenant_membership_id' => $this->optionalMap('memberships', $row, 'user_id'),
                'product_id' => $this->requiredMap('products', $row, 'product_id'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'created_at') ?? now(),
            ]);
        }
    }

    private function insertStorefronts(): void
    {
        $conn = DB::connection($this->connection);
        $this->idMaps['storefronts'] = [];

        foreach ($this->rows['storefronts'] as $row) {
            $id = (int) $row['id'];
            $conn->table('storefronts')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'theme_id' => null,
                'status' => $this->string($row, 'status') ?: 'active',
                'draft_revision_id' => null,
                'published_revision_id' => null,
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['storefronts'][$id] = $id;
        }

        foreach ($this->rows['storefront_media'] as $row) {
            $conn->table('storefront_media')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'storefront_id' => $this->optionalMap('storefronts', $row, 'storefront_id'),
                'key' => $this->string($row, 'key'),
                'path' => $this->string($row, 'path'),
                'original_name' => $this->nullable($row, 'original_name'),
                'mime_type' => $this->nullable($row, 'mime_type'),
                'size' => $this->nullableInteger($row, 'size'),
                'alt_text' => $this->nullable($row, 'alt_text'),
                'metadata' => $this->nullableJson($row, 'metadata'),
                'is_visible' => $this->bool($row, 'is_visible'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }

        foreach ($this->rows['storefront_contents'] as $row) {
            $conn->table('storefront_contents')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'labels' => $this->nullableJson($row, 'labels'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }

        foreach ($this->rows['storefront_design_tokens'] as $row) {
            $conn->table('storefront_design_tokens')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'tokens' => $this->nullableJson($row, 'tokens'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }

        foreach ($this->rows['storefront_homepage_sections'] as $row) {
            $conn->table('storefront_homepage_sections')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'type' => $this->string($row, 'type'),
                'variant' => $this->string($row, 'variant'),
                'enabled' => $this->bool($row, 'enabled'),
                'desktop_visible' => $this->bool($row, 'desktop_visible'),
                'mobile_visible' => $this->bool($row, 'mobile_visible'),
                'position' => (int) ($row['position'] ?? 0),
                'configuration' => $this->nullableJson($row, 'configuration'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }

        $this->idMaps['storefront_navigations'] = [];
        foreach ($this->rows['storefront_navigations'] as $row) {
            $id = (int) $row['id'];
            $conn->table('storefront_navigations')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'settings' => $this->nullableJson($row, 'settings'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['storefront_navigations'][$id] = $id;
        }

        foreach ($this->rows['storefront_navigation_items'] as $row) {
            $conn->table('storefront_navigation_items')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'navigation_id' => $this->requiredMap('storefront_navigations', $row, 'navigation_id'),
                'key' => $this->string($row, 'key'),
                'group' => $this->string($row, 'group'),
                'label' => $this->string($row, 'label'),
                'path' => $this->string($row, 'path'),
                'icon' => $this->nullable($row, 'icon'),
                'enabled' => $this->bool($row, 'enabled'),
                'position' => (int) ($row['position'] ?? 0),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }

        $this->idMaps['storefront_revisions'] = [];
        foreach ($this->rows['storefront_revisions'] as $row) {
            $id = (int) $row['id'];
            $conn->table('storefront_revisions')->insert([
                'id' => $id,
                'tenant_id' => $this->targetTenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'revision_number' => (int) $row['revision_number'],
                'status' => $this->string($row, 'status') ?: 'draft',
                'configuration' => $this->nullableJson($row, 'configuration'),
                'created_by_type' => $this->nullable($row, 'created_by_type'),
                'created_by_id' => $this->nullableInteger($row, 'created_by_id'),
                'published_at' => $this->nullableTime($row, 'published_at'),
                'published_by_type' => $this->nullable($row, 'published_by_type'),
                'published_by_id' => $this->nullableInteger($row, 'published_by_id'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
            $this->idMaps['storefront_revisions'][$id] = $id;
        }

        foreach ($this->rows['storefronts'] as $row) {
            $conn->table('storefronts')->where('id', (int) $row['id'])->update([
                'draft_revision_id' => $this->optionalMap('storefront_revisions', $row, 'draft_revision_id'),
                'published_revision_id' => $this->optionalMap('storefront_revisions', $row, 'published_revision_id'),
            ]);
        }

        foreach ($this->rows['storefront_theme_configs'] as $row) {
            $conn->table('storefront_theme_configs')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'theme_id' => null,
                'configuration' => $this->nullableJson($row, 'configuration'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }

        foreach ($this->rows['storefront_checkout_configs'] as $row) {
            $conn->table('storefront_checkout_configs')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'configuration' => $this->nullableJson($row, 'configuration'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }

        foreach ($this->rows['storefront_product_display_configs'] as $row) {
            $conn->table('storefront_product_display_configs')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'storefront_id' => $this->requiredMap('storefronts', $row, 'storefront_id'),
                'configuration' => $this->nullableJson($row, 'configuration'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }

        foreach ($this->rows['promotion_banners'] as $row) {
            $conn->table('promotion_banners')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'promotion_id' => $this->optionalMap('promotions', $row, 'promotion_id'),
                'title' => $this->string($row, 'title'),
                'description' => $this->nullable($row, 'description'),
                'image' => $this->string($row, 'image'),
                'storefront_media_id' => $this->nullableInteger($row, 'storefront_media_id'),
                'link' => $this->string($row, 'link'),
                'cta_label' => $this->nullable($row, 'cta_label'),
                'is_active' => $this->bool($row, 'is_active'),
                'starts_at' => $this->nullableTime($row, 'starts_at'),
                'ends_at' => $this->nullableTime($row, 'ends_at'),
                'position' => (int) ($row['position'] ?? 0),
                'desktop_visible' => $this->bool($row, 'desktop_visible'),
                'mobile_visible' => $this->bool($row, 'mobile_visible'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }
    }

    private function insertWebsiteAndSettings(): void
    {
        $conn = DB::connection($this->connection);

        foreach ($this->rows['website_faqs'] as $row) {
            $conn->table('website_faqs')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
                'category' => $this->string($row, 'category'),
                'question_en' => $this->string($row, 'question_en'),
                'question_my' => $this->nullable($row, 'question_my'),
                'answer_en' => $this->string($row, 'answer_en'),
                'answer_my' => $this->nullable($row, 'answer_my'),
                'sort_order' => (int) ($row['sort_order'] ?? 0),
                'is_active' => $this->bool($row, 'is_active'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'updated_at') ?? now(),
            ]);
        }

        $website = [];
        foreach ($this->rows['store_settings'] as $row) {
            $website[$row['key']] = $row['value'];
        }

        if ($website !== []) {
            $website['tenant_id'] = $this->targetTenantId;
            $website['created_at'] = now();
            $website['updated_at'] = now();
            $conn->table('website_infos')->insert($website);
        }

        foreach ($this->rows['settings'] as $row) {
            $updatedAt = $this->nullableTime($row, 'updated_at') ?? now();
            $conn->table('settings')->insert([
                'tenant_id' => $this->targetTenantId,
                'key' => $this->string($row, 'key'),
                'value' => $this->nullable($row, 'value'),
                'created_at' => $updatedAt,
                'updated_at' => $updatedAt,
            ]);
        }
    }

    private function insertImportHistories(): void
    {
        $conn = DB::connection($this->connection);

        foreach ($this->rows['import_histories'] as $row) {
            $conn->table('import_histories')->insert([
                'id' => (int) $row['id'],
                'tenant_id' => $this->targetTenantId,
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
                'duration_ms' => $this->nullableInteger($row, 'duration_ms'),
                'created_at' => $this->nullableTime($row, 'created_at') ?? now(),
                'updated_at' => $this->nullableTime($row, 'created_at') ?? now(),
            ]);
        }
    }

    private function requiredMap(string $map, array $row, string $field): int
    {
        $source = $this->nullable($row, $field);
        if ($source === null || !isset($this->idMaps[$map][(int) $source])) {
            throw new RuntimeException(sprintf('Missing %s mapping for %s=%s', $map, $field, (string) $source));
        }

        return $this->idMaps[$map][(int) $source];
    }

    private function optionalMap(string $map, array $row, string $field): ?int
    {
        $source = $this->nullable($row, $field);
        if ($source === null) {
            return null;
        }

        return $this->idMaps[$map][(int) $source] ?? null;
    }

    private function uniqueIdByName($groups, string $name, string $label, bool $required): ?int
    {
        if ($name === '') {
            if ($required) {
                throw new RuntimeException('Missing required ' . $label . ' name.');
            }

            return null;
        }

        $matches = $groups->get($name, collect());
        if ($matches->count() > 1) {
            throw new RuntimeException(sprintf('Ambiguous %s name: %s', $label, $name));
        }

        if ($matches->count() === 0) {
            if ($required) {
                throw new RuntimeException('Missing target ' . $label . ': ' . $name);
            }

            return null;
        }

        return (int) $matches->first()->id;
    }

    private function assertIdFree(string $table, int $id): void
    {
        if (DB::connection($this->connection)->table($table)->where('id', $id)->exists()) {
            throw new RuntimeException(sprintf('Target %s id %d already exists.', $table, $id));
        }
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

    private function nullableInteger(array $row, string $field): ?int
    {
        $value = $this->nullable($row, $field);

        return $value === null ? null : (int) $value;
    }

    private function bool(array $row, string $field): bool
    {
        return in_array($this->string($row, $field), ['1', 'true', 'yes'], true);
    }

    private function nullableTime(array $row, string $field): ?string
    {
        return $this->nullable($row, $field);
    }

    private function nullableJson(array $row, string $field): ?string
    {
        $value = $this->nullable($row, $field);

        if ($value === null) {
            return null;
        }

        json_decode($value, true);
        if (json_last_error() !== JSON_ERROR_NONE) {
            throw new RuntimeException('Invalid JSON in export field ' . $field);
        }

        return $value;
    }
}
