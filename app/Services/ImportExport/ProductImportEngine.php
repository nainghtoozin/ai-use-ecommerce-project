<?php

namespace App\Services\ImportExport;

use App\Enums\ProductType;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Services\InventoryService;
use App\Services\SkuService;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class ProductImportEngine
{
    private MasterDataResolver $resolver;
    private SkuService $skuService;
    private InventoryService $inventoryService;
    private array $errors = [];
    private array $warnings = [];
    private array $createdProducts = [];
    private array $createdVariants = [];
    private array $seenSkus = [];
    private array $seenVariantSkus = [];

    public function __construct(
        MasterDataResolver $resolver,
        SkuService $skuService,
        InventoryService $inventoryService
    ) {
        $this->resolver = $resolver;
        $this->skuService = $skuService;
        $this->inventoryService = $inventoryService;
    }

    public function validate(array $products, array $variants): array
    {
        $this->errors = [];
        $this->warnings = [];
        $this->seenSkus = [];
        $this->seenVariantSkus = [];

        $variableSkus = $this->buildVariableSkuMap($products);

        $this->validateProducts($products);

        if (empty($variableSkus) && !empty($variants)) {
            $this->addWarning('Variants', 0, 'variants', '',
                'Variants sheet contains ' . count($variants) . ' row(s), but no variable products were found in the Products sheet. These variant rows will be ignored.');
            $applicableVariants = 0;
        } elseif (!empty($variableSkus) && empty($variants)) {
            $this->addError('Variants', 0, 'variants', '',
                'Variable products were found in the Products sheet, but no variant rows were provided. Variants are required for variable products.');
            $applicableVariants = 0;
        } elseif (!empty($variableSkus)) {
            $this->validateVariants($variants, $variableSkus);
            $applicableVariants = $this->countApplicableVariants($variants, $variableSkus);
        } else {
            $applicableVariants = 0;
        }

        $productErrors = collect($this->errors)->where('sheet', 'Products')->count();
        $variantErrors = collect($this->errors)->where('sheet', 'Variants')->count();

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'summary' => [
                'total_products' => count($products),
                'total_variants' => $applicableVariants,
                'valid_products' => count($products) - $productErrors,
                'valid_variants' => $applicableVariants - $variantErrors,
                'error_products' => $productErrors,
                'error_variants' => $variantErrors,
                'warning_count' => count($this->warnings),
            ],
        ];
    }

    public function validateProductsOnly(array $products): array
    {
        $this->errors = [];
        $this->warnings = [];
        $this->seenSkus = [];

        $this->validateProducts($products);

        $productErrors = collect($this->errors)->where('sheet', 'Products')->count();

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'summary' => [
                'total_products' => count($products),
                'total_variants' => 0,
                'valid_products' => count($products) - $productErrors,
                'valid_variants' => 0,
                'error_products' => $productErrors,
                'error_variants' => 0,
                'warning_count' => count($this->warnings),
            ],
        ];
    }

    public function validateVariantsOnly(array $variants): array
    {
        $this->errors = [];
        $this->warnings = [];
        $this->seenVariantSkus = [];

        foreach ($variants as $index => $row) {
            $rowNum = $index + 2;
            $parentSku = trim((string)($row['parent_sku'] ?? ''));
            $variantSku = trim((string)($row['variant_sku'] ?? ''));

            if (empty($parentSku)) {
                $this->addError('Variants', $rowNum, 'parent_sku', '', 'Parent SKU is required.');
            } else {
                $existingParent = Product::withoutTenantScope()
                    ->where('tenant_id', $this->resolver->getTenantId())
                    ->where('sku', $parentSku)
                    ->first();
                if (!$existingParent) {
                    $this->addError('Variants', $rowNum, 'parent_sku', $parentSku, "Parent product with SKU \"{$parentSku}\" not found. Import the parent product first.");
                }
            }

            if (empty($variantSku)) {
                $this->addError('Variants', $rowNum, 'variant_sku', '', 'Variant SKU is required.');
            } elseif (isset($this->seenVariantSkus[$variantSku])) {
                $this->addError('Variants', $rowNum, 'variant_sku', $variantSku, "Duplicate variant SKU in file (first seen at row {$this->seenVariantSkus[$variantSku]}).");
            } else {
                $this->seenVariantSkus[$variantSku] = $rowNum;
            }

            if (empty(trim((string)($row['option_1_name'] ?? '')))) {
                $this->addError('Variants', $rowNum, 'option_1_name', '', 'Option 1 Name is required.');
            }
            if (empty(trim((string)($row['option_1_value'] ?? '')))) {
                $this->addError('Variants', $rowNum, 'option_1_value', '', 'Option 1 Value is required.');
            }

            $price = $row['selling_price'] ?? $row['price'] ?? '';
            if (!empty($price) && !is_numeric($price)) {
                $this->addError('Variants', $rowNum, 'selling_price', $price, 'Selling price must be a number.');
            }

            $costPrice = $row['cost_price'] ?? '';
            if (!empty($costPrice) && !is_numeric($costPrice)) {
                $this->addError('Variants', $rowNum, 'cost_price', $costPrice, 'Cost price must be a number.');
            }

            $stock = $row['stock'] ?? '';
            if (!empty($stock) && !is_numeric($stock)) {
                $this->addError('Variants', $rowNum, 'stock', $stock, 'Stock must be a number.');
            }
        }

        $variantErrors = collect($this->errors)->where('sheet', 'Variants')->count();

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'summary' => [
                'total_products' => 0,
                'total_variants' => count($variants),
                'valid_products' => 0,
                'valid_variants' => count($variants) - $variantErrors,
                'error_products' => 0,
                'error_variants' => $variantErrors,
                'warning_count' => count($this->warnings),
            ],
        ];
    }

    private function validateProducts(array $products): void
    {
        foreach ($products as $index => $row) {
            $rowNum = $index + 2;
            $sku = trim((string)($row['sku'] ?? ''));

            if (empty(trim((string)($row['product_name'] ?? '')))) {
                $this->addError('Products', $rowNum, 'product_name', $row['product_name'] ?? '', 'Product name is required.');
            }

            $type = strtolower(trim((string)($row['product_type'] ?? 'single')));
            if (!in_array($type, ['single', 'variable'])) {
                $this->addError('Products', $rowNum, 'product_type', $row['product_type'] ?? '', 'Product type must be "single" or "variable".');
            }

            if (empty($sku)) {
                $this->addError('Products', $rowNum, 'sku', '', 'SKU is required.');
            } elseif (isset($this->seenSkus[$sku])) {
                $this->addError('Products', $rowNum, 'sku', $sku, "Duplicate SKU in file (first seen at row {$this->seenSkus[$sku]}).");
            } else {
                $this->seenSkus[$sku] = $rowNum;
                $existing = Product::withoutTenantScope()
                    ->where('tenant_id', $this->resolver->getTenantId())
                    ->where('sku', $sku)
                    ->first();
                if ($existing) {
                    $this->addWarning('Products', $rowNum, 'sku', $sku, 'SKU already exists and will be skipped.');
                }
            }

            $price = $row['selling_price'] ?? $row['price'] ?? '';
            if (!empty($price) && !is_numeric($price)) {
                $this->addError('Products', $rowNum, 'selling_price', $price, 'Selling price must be a number.');
            }

            $costPrice = $row['cost_price'] ?? '';
            if (!empty($costPrice) && !is_numeric($costPrice)) {
                $this->addError('Products', $rowNum, 'cost_price', $costPrice, 'Cost price must be a number.');
            }

            $stock = $row['stock'] ?? '';
            if (!empty($stock) && !is_numeric($stock)) {
                $this->addError('Products', $rowNum, 'stock', $stock, 'Stock must be a number.');
            }

            $category = trim((string)($row['category'] ?? ''));
            if (!empty($category) && !$this->resolver->resolveCategory($category)) {
                $this->addWarning('Products', $rowNum, 'category', $category, "Category \"{$category}\" not found. It will be created automatically.");
            }

            $brand = trim((string)($row['brand'] ?? ''));
            if (!empty($brand) && !$this->resolver->resolveBrand($brand)) {
                $this->addWarning('Products', $rowNum, 'brand', $brand, "Brand \"{$brand}\" not found. It will be created automatically.");
            }

            $unit = trim((string)($row['unit'] ?? ''));
            if (!empty($unit) && !$this->resolver->resolveUnit($unit)) {
                $this->addError('Products', $rowNum, 'unit', $unit, "Unit \"{$unit}\" not found. Please create this unit first.");
            }

            $status = strtolower(trim((string)($row['status'] ?? 'active')));
            if (!in_array($status, ['active', 'inactive', 'draft'])) {
                $this->addError('Products', $rowNum, 'status', $row['status'] ?? '', 'Status must be active, inactive, or draft.');
            }
        }
    }

    private function buildVariableSkuMap(array $products): array
    {
        $map = [];
        foreach ($products as $p) {
            $sku = trim((string)($p['sku'] ?? ''));
            $type = strtolower(trim((string)($p['product_type'] ?? 'single')));
            if (!empty($sku) && $type === 'variable') {
                $map[$sku] = true;
            }
        }
        return $map;
    }

    private function countApplicableVariants(array $variants, array $variableSkus): int
    {
        $count = 0;
        foreach ($variants as $row) {
            $parentSku = trim((string)($row['parent_sku'] ?? ''));
            if (isset($variableSkus[$parentSku])) {
                $count++;
            }
        }
        return $count;
    }

    private function validateVariants(array $variants, array $variableSkus): void
    {
        $allWorkbookSkus = array_fill_keys(array_keys($variableSkus), true);

        foreach ($variants as $index => $row) {
            $rowNum = $index + 2;
            $parentSku = trim((string)($row['parent_sku'] ?? ''));
            $variantSku = trim((string)($row['variant_sku'] ?? ''));

            if (empty($parentSku)) {
                $this->addError('Variants', $rowNum, 'parent_sku', '', 'Parent SKU is required.');
                continue;
            }

            if (!isset($variableSkus[$parentSku])) {
                $isInWorkbook = isset($allWorkbookSkus[$parentSku]);
                $existingParent = null;
                if (!$isInWorkbook) {
                    $existingParent = Product::withoutTenantScope()
                        ->where('tenant_id', $this->resolver->getTenantId())
                        ->where('sku', $parentSku)
                        ->first();
                }

                if ($existingParent) {
                    $existingType = strtolower($existingParent->type->value ?? '');
                    if ($existingType !== 'variable') {
                        $this->addError('Variants', $rowNum, 'parent_sku', $parentSku,
                            "Product SKU \"{$parentSku}\" is a Single Product and cannot have variants. Change the Product Type to Variable or remove this Variant row.");
                    }
                } elseif (!$isInWorkbook) {
                    $this->addError('Variants', $rowNum, 'parent_sku', $parentSku,
                        "Parent SKU \"{$parentSku}\" does not match any Variable Product in the Products sheet. Add the parent product to the Products sheet with Product Type set to Variable, or correct the Parent SKU.");
                } else {
                    $this->addError('Variants', $rowNum, 'parent_sku', $parentSku,
                        "Product SKU \"{$parentSku}\" is a Single Product and cannot have variants. Change the Product Type to Variable or remove this Variant row.");
                }
                continue;
            }

            if (empty($variantSku)) {
                $this->addError('Variants', $rowNum, 'variant_sku', '', 'Variant SKU is required.');
            } elseif (isset($this->seenVariantSkus[$variantSku])) {
                $this->addError('Variants', $rowNum, 'variant_sku', $variantSku, "Duplicate variant SKU in file (first seen at row {$this->seenVariantSkus[$variantSku]}).");
            } else {
                $this->seenVariantSkus[$variantSku] = $rowNum;
            }

            if (empty(trim((string)($row['option_1_name'] ?? '')))) {
                $this->addError('Variants', $rowNum, 'option_1_name', '', 'Option 1 Name is required.');
            }
            if (empty(trim((string)($row['option_1_value'] ?? '')))) {
                $this->addError('Variants', $rowNum, 'option_1_value', '', 'Option 1 Value is required.');
            }

            $price = $row['selling_price'] ?? $row['price'] ?? '';
            if (!empty($price) && !is_numeric($price)) {
                $this->addError('Variants', $rowNum, 'selling_price', $price, 'Selling price must be a number.');
            }

            $costPrice = $row['cost_price'] ?? '';
            if (!empty($costPrice) && !is_numeric($costPrice)) {
                $this->addError('Variants', $rowNum, 'cost_price', $costPrice, 'Cost price must be a number.');
            }

            $stock = $row['stock'] ?? '';
            if (!empty($stock) && !is_numeric($stock)) {
                $this->addError('Variants', $rowNum, 'stock', $stock, 'Stock must be a number.');
            }
        }
    }

    public function import(array $products, array $variants, string $mode): array
    {
        $tenantId = $this->resolver->getTenantId();
        $result = [
            'products_created' => 0,
            'products_skipped' => 0,
            'variants_created' => 0,
            'categories_matched' => 0,
            'brands_matched' => 0,
            'units_matched' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            $productMap = [];

            foreach ($products as $index => $row) {
                $sku = trim((string)($row['sku'] ?? ''));
                $type = strtolower(trim((string)($row['product_type'] ?? 'single')));

                $existing = Product::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('sku', $sku)
                    ->first();

                if ($existing && $mode === 'create_new') {
                    $result['products_skipped']++;
                    $productMap[$sku] = $existing;
                    continue;
                }

                $productData = $this->mapProductData($row, $tenantId);

                if ($existing && $mode === 'update_only') {
                    $existing->update($productData);
                    $productMap[$sku] = $existing;
                    $result['products_created']++;
                } elseif ($existing) {
                    $existing->update($productData);
                    $productMap[$sku] = $existing;
                    $result['products_created']++;
                } else {
                    $product = Product::create($productData);
                    $productMap[$sku] = $product;
                    $result['products_created']++;

                    if ($type === 'single' && ($product->stock ?? 0) > 0) {
                        $this->inventoryService->handleProductCreated($product, []);
                    }
                }
            }

            foreach ($variants as $row) {
                $parentSku = trim((string)($row['parent_sku'] ?? ''));
                $variantSku = trim((string)($row['variant_sku'] ?? ''));

                $parent = $productMap[$parentSku] ?? null;
                if (!$parent) {
                    $parent = Product::withoutTenantScope()
                        ->where('tenant_id', $tenantId)
                        ->where('sku', $parentSku)
                        ->first();
                }

                if (!$parent) {
                    $result['errors'][] = [
                        'sheet' => 'Variants',
                        'row' => 'N/A',
                        'column' => 'parent_sku',
                        'value' => $parentSku,
                        'error' => "Parent product not found.",
                    ];
                    continue;
                }

                $existingVariant = ProductVariant::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('sku', $variantSku)
                    ->first();

                if ($existingVariant && $mode === 'create_new') {
                    continue;
                }

                $attributes = $this->buildAttributes($row);

                $variantData = [
                    'product_id' => $parent->id,
                    'sku' => $variantSku,
                    'barcode' => $row['barcode'] ?? null,
                    'price' => !empty($row['selling_price'] ?? $row['price']) ? (float)($row['selling_price'] ?? $row['price']) : $parent->price,
                    'cost_price' => !empty($row['cost_price']) ? (float)$row['cost_price'] : $parent->cost_price,
                    'stock' => (int)($row['stock'] ?? 0),
                    'low_stock_threshold' => 5,
                    'attributes' => $attributes,
                    'status' => 'active',
                ];

                if ($existingVariant) {
                    $existingVariant->update($variantData);
                } else {
                    ProductVariant::create($variantData);
                    $result['variants_created']++;
                }
            }

            DB::commit();

            $result['categories_matched'] = count($this->resolver->getMatchedCategories());
            $result['brands_matched'] = count($this->resolver->getMatchedBrands());
            $result['units_matched'] = count($this->resolver->getMatchedUnits());

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Product import failed', ['error' => $e->getMessage(), 'trace' => $e->getTraceAsString()]);
            throw $e;
        }

        return $result;
    }

    public function importProducts(array $products, string $mode): array
    {
        $tenantId = $this->resolver->getTenantId();
        $result = [
            'products_created' => 0,
            'products_skipped' => 0,
            'variants_created' => 0,
            'categories_matched' => 0,
            'brands_matched' => 0,
            'units_matched' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($products as $row) {
                $sku = trim((string)($row['sku'] ?? ''));
                $type = strtolower(trim((string)($row['product_type'] ?? 'single')));

                $existing = Product::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('sku', $sku)
                    ->first();

                if ($existing && $mode === 'create_new') {
                    $result['products_skipped']++;
                    continue;
                }

                $productData = $this->mapProductData($row, $tenantId);

                if ($existing) {
                    $existing->update($productData);
                    $result['products_created']++;
                } else {
                    $product = Product::create($productData);
                    $result['products_created']++;

                    if ($type === 'single' && ($product->stock ?? 0) > 0) {
                        $this->inventoryService->handleProductCreated($product, []);
                    }
                }
            }

            DB::commit();

            $result['categories_matched'] = count($this->resolver->getMatchedCategories());
            $result['brands_matched'] = count($this->resolver->getMatchedBrands());
            $result['units_matched'] = count($this->resolver->getMatchedUnits());

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Product import failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $result;
    }

    public function importVariants(array $variants, string $mode): array
    {
        $tenantId = $this->resolver->getTenantId();
        $result = [
            'products_created' => 0,
            'products_skipped' => 0,
            'variants_created' => 0,
            'variants_skipped' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            foreach ($variants as $row) {
                $parentSku = trim((string)($row['parent_sku'] ?? ''));
                $variantSku = trim((string)($row['variant_sku'] ?? ''));

                $parent = Product::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('sku', $parentSku)
                    ->first();

                if (!$parent) {
                    $result['errors'][] = [
                        'sheet' => 'Variants',
                        'row' => 'N/A',
                        'column' => 'parent_sku',
                        'value' => $parentSku,
                        'error' => 'Parent product not found.',
                    ];
                    continue;
                }

                $existingVariant = ProductVariant::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('sku', $variantSku)
                    ->first();

                if ($existingVariant && $mode === 'create_new') {
                    $result['variants_skipped']++;
                    continue;
                }

                $attributes = $this->buildAttributes($row);

                $variantData = [
                    'product_id' => $parent->id,
                    'sku' => $variantSku,
                    'barcode' => $row['barcode'] ?? null,
                    'price' => !empty($row['selling_price'] ?? $row['price']) ? (float)($row['selling_price'] ?? $row['price']) : $parent->price,
                    'cost_price' => !empty($row['cost_price']) ? (float)$row['cost_price'] : $parent->cost_price,
                    'stock' => (int)($row['stock'] ?? 0),
                    'low_stock_threshold' => 5,
                    'attributes' => $attributes,
                    'status' => strtolower(trim((string)($row['status'] ?? 'active'))) === 'inactive' ? 'inactive' : 'active',
                ];

                if ($existingVariant) {
                    $existingVariant->update($variantData);
                    $result['variants_created']++;
                } else {
                    ProductVariant::create($variantData);
                    $result['variants_created']++;
                }
            }

            DB::commit();

        } catch (\Throwable $e) {
            DB::rollBack();
            Log::error('Variant import failed', ['error' => $e->getMessage()]);
            throw $e;
        }

        return $result;
    }

    private function mapProductData(array $row, int $tenantId): array
    {
        $categoryName = trim((string)($row['category'] ?? ''));
        $brandName = trim((string)($row['brand'] ?? ''));
        $unit = $this->resolver->resolveUnit(trim((string)($row['unit'] ?? '')));
        $type = strtolower(trim((string)($row['product_type'] ?? 'single')));

        $category = !empty($categoryName) ? $this->resolver->resolveOrCreateCategory($categoryName) : null;
        $brand = !empty($brandName) ? $this->resolver->resolveOrCreateBrand($brandName) : null;

        $name = trim((string)($row['product_name'] ?? ''));

        $data = [
            'tenant_id' => $tenantId,
            'name' => $name,
            'slug' => Str::slug($name) . '-' . Str::random(6),
            'sku' => trim((string)($row['sku'] ?? '')),
            'barcode' => $row['barcode'] ?? null,
            'description' => $row['description'] ?? null,
            'short_description' => $row['short_description'] ?? null,
            'price' => (float)($row['selling_price'] ?? $row['price'] ?? 0),
            'base_price' => (float)($row['selling_price'] ?? $row['price'] ?? 0),
            'cost_price' => !empty($row['cost_price']) ? (float)$row['cost_price'] : null,
            'stock' => $type === 'variable' ? 0 : (int)($row['stock'] ?? 0),
            'low_stock_alert' => 5,
            'category_id' => $category?->id,
            'brand_id' => $brand?->id,
            'unit_id' => $unit?->id,
            'status' => strtolower(trim((string)($row['status'] ?? 'active'))) === 'inactive' ? 'inactive' : 'active',
            'type' => $type === 'variable' ? ProductType::VARIABLE : ProductType::SINGLE,
        ];

        return $data;
    }

    private function buildAttributes(array $row): array
    {
        $attributes = [];

        for ($i = 1; $i <= 5; $i++) {
            $name = trim((string)($row["option_{$i}_name"] ?? ''));
            $value = trim((string)($row["option_{$i}_value"] ?? ''));
            if (!empty($name) && !empty($value)) {
                $attributes[$name] = $value;
            }
        }

        return $attributes;
    }

    private function addError(string $sheet, int $row, string $column, string $value, string $message): void
    {
        $this->errors[] = [
            'sheet' => $sheet,
            'row' => $row,
            'column' => $column,
            'value' => $value,
            'error' => $message,
        ];
    }

    private function addWarning(string $sheet, int $row, string $column, string $value, string $message): void
    {
        $this->warnings[] = [
            'sheet' => $sheet,
            'row' => $row,
            'column' => $column,
            'value' => $value,
            'warning' => $message,
        ];
    }
}
