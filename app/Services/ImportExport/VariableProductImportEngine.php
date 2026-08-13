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

class VariableProductImportEngine
{
    private MasterDataResolver $resolver;
    private SkuService $skuService;
    private InventoryService $inventoryService;
    private array $errors = [];
    private array $warnings = [];
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

    public function validate(array $variants): array
    {
        $this->errors = [];
        $this->warnings = [];
        $this->seenVariantSkus = [];

        $this->validateVariants($variants);

        $variantErrors = collect($this->errors)->where('sheet', 'Variants')->count();

        return [
            'valid' => empty($this->errors),
            'errors' => $this->errors,
            'warnings' => $this->warnings,
            'summary' => [
                'total_variants' => count($variants),
                'valid_variants' => count($variants) - $variantErrors,
                'error_variants' => $variantErrors,
                'warning_count' => count($this->warnings),
            ],
        ];
    }

    public function import(array $variants, string $mode): array
    {
        $tenantId = $this->resolver->getTenantId();
        $result = [
            'products_created' => 0,
            'products_skipped' => 0,
            'variants_created' => 0,
            'variants_skipped' => 0,
            'categories_matched' => 0,
            'brands_matched' => 0,
            'units_matched' => 0,
            'errors' => [],
        ];

        DB::beginTransaction();

        try {
            $parentGroups = $this->groupByParentSku($variants);
            $productMap = [];

            foreach ($parentGroups as $parentSku => $rows) {
                $firstRow = $rows[0];
                $parentName = trim((string)($firstRow['parent_name'] ?? ''));
                $categoryName = trim((string)($firstRow['category'] ?? ''));
                $brandName = trim((string)($firstRow['brand'] ?? ''));
                $unitName = trim((string)($firstRow['unit'] ?? ''));

                $existing = Product::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('sku', $parentSku)
                    ->first();

                if ($existing && $mode === 'create_new') {
                    $result['products_skipped']++;
                    $productMap[$parentSku] = $existing;
                    continue;
                }

                $category = !empty($categoryName) ? $this->resolver->resolveOrCreateCategory($categoryName) : null;
                $brand = !empty($brandName) ? $this->resolver->resolveOrCreateBrand($brandName) : null;
                $unit = !empty($unitName) ? $this->resolver->resolveUnit($unitName) : null;

                $productData = [
                    'tenant_id' => $tenantId,
                    'name' => $parentName,
                    'slug' => Str::slug($parentName) . '-' . Str::random(6),
                    'sku' => $parentSku,
                    'short_description' => $firstRow['short_description'] ?? $firstRow['description'] ?? null,
                    'description' => $firstRow['full_description'] ?? null,
                    'price' => !empty($firstRow['selling_price']) ? (float)$firstRow['selling_price'] : 0,
                    'base_price' => !empty($firstRow['selling_price']) ? (float)$firstRow['selling_price'] : 0,
                    'cost_price' => !empty($firstRow['cost_price']) ? (float)$firstRow['cost_price'] : null,
                    'stock' => 0,
                    'low_stock_alert' => 5,
                    'category_id' => $category?->id,
                    'brand_id' => $brand?->id,
                    'unit_id' => $unit?->id,
                    'status' => 'active',
                    'type' => ProductType::VARIABLE,
                ];

                if ($existing) {
                    $existing->update($productData);
                    $productMap[$parentSku] = $existing;
                    $result['products_created']++;
                } else {
                    $product = Product::create($productData);
                    $productMap[$parentSku] = $product;
                    $result['products_created']++;
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
                        'error' => "Parent product with SKU \"{$parentSku}\" not found.",
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
                    'price' => !empty($row['selling_price'])
                        ? (float)$row['selling_price']
                        : $parent->price,
                    'cost_price' => !empty($row['cost_price'])
                        ? (float)$row['cost_price']
                        : $parent->cost_price,
                    'stock' => (int)($row['stock'] ?? 0),
                    'low_stock_threshold' => 5,
                    'attributes' => $attributes,
                    'status' => strtolower(trim((string)($row['status'] ?? 'active'))) === 'inactive'
                        ? 'inactive'
                        : 'active',
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
            Log::error('Variable product import failed', [
                'error' => $e->getMessage(),
                'trace' => $e->getTraceAsString(),
            ]);
            throw $e;
        }

        return $result;
    }

    private function validateVariants(array $variants): void
    {
        $parentData = [];

        foreach ($variants as $index => $row) {
            $rowNum = $index + 2;
            $parentSku = trim((string)($row['parent_sku'] ?? ''));
            $variantSku = trim((string)($row['variant_sku'] ?? ''));
            $parentName = trim((string)($row['parent_name'] ?? ''));

            if (empty($parentSku)) {
                $this->addError('Variants', $rowNum, 'parent_sku', '', 'Parent SKU is required.');
            } else {
                if (isset($parentData[$parentSku])) {
                    $existing = $parentData[$parentSku];
                    if ($existing['parent_name'] !== $parentName) {
                        $this->addError('Variants', $rowNum, 'parent_name', $parentName,
                            "Parent Name for SKU \"{$parentSku}\" conflicts with row {$existing['row']}. Expected \"{$existing['parent_name']}\".");
                    }
                    $cat = trim((string)($row['category'] ?? ''));
                    if ($cat !== $existing['category']) {
                        $this->addError('Variants', $rowNum, 'category', $cat,
                            "Category for SKU \"{$parentSku}\" conflicts with row {$existing['row']}. Expected \"{$existing['category']}\".");
                    }
                    $brand = trim((string)($row['brand'] ?? ''));
                    if ($brand !== $existing['brand']) {
                        $this->addError('Variants', $rowNum, 'brand', $brand,
                            "Brand for SKU \"{$parentSku}\" conflicts with row {$existing['row']}. Expected \"{$existing['brand']}\".");
                    }
                    $unit = trim((string)($row['unit'] ?? ''));
                    if ($unit !== $existing['unit']) {
                        $this->addError('Variants', $rowNum, 'unit', $unit,
                            "Unit for SKU \"{$parentSku}\" conflicts with row {$existing['row']}. Expected \"{$existing['unit']}\".");
                    }
                } else {
                    $parentData[$parentSku] = [
                        'row' => $rowNum,
                        'parent_name' => $parentName,
                        'category' => trim((string)($row['category'] ?? '')),
                        'brand' => trim((string)($row['brand'] ?? '')),
                        'unit' => trim((string)($row['unit'] ?? '')),
                    ];

                    $existingParent = Product::withoutTenantScope()
                        ->where('tenant_id', $this->resolver->getTenantId())
                        ->where('sku', $parentSku)
                        ->first();
                    if ($existingParent) {
                        $this->addWarning('Variants', $rowNum, 'parent_sku', $parentSku,
                            "Parent product \"{$existingParent->name}\" (SKU: {$parentSku}) already exists and will be updated.");
                    }
                }
            }

            if (empty($variantSku)) {
                $this->addError('Variants', $rowNum, 'variant_sku', '', 'Variant SKU is required.');
            } elseif (isset($this->seenVariantSkus[$variantSku])) {
                $this->addError('Variants', $rowNum, 'variant_sku', $variantSku,
                    "Duplicate variant SKU in file (first seen at row {$this->seenVariantSkus[$variantSku]}).");
            } else {
                $this->seenVariantSkus[$variantSku] = $rowNum;
            }

            if (empty(trim((string)($row['option_1_name'] ?? '')))) {
                $this->addError('Variants', $rowNum, 'option_1_name', '', 'Option 1 Name is required.');
            }
            if (empty(trim((string)($row['option_1_value'] ?? '')))) {
                $this->addError('Variants', $rowNum, 'option_1_value', '', 'Option 1 Value is required.');
            }

            $price = $row['selling_price'] ?? '';
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

            $unit = trim((string)($row['unit'] ?? ''));
            if (!empty($unit) && !$this->resolver->resolveUnit($unit)) {
                $this->addError('Variants', $rowNum, 'unit', $unit, "Unit \"{$unit}\" not found. Please create this unit first.");
            }

            $category = trim((string)($row['category'] ?? ''));
            if (!empty($category) && !$this->resolver->resolveCategory($category)) {
                $this->addWarning('Variants', $rowNum, 'category', $category,
                    "Category \"{$category}\" not found. It will be created automatically.");
            }

            $brand = trim((string)($row['brand'] ?? ''));
            if (!empty($brand) && !$this->resolver->resolveBrand($brand)) {
                $this->addWarning('Variants', $rowNum, 'brand', $brand,
                    "Brand \"{$brand}\" not found. It will be created automatically.");
            }
        }
    }

    private function groupByParentSku(array $variants): array
    {
        $groups = [];
        foreach ($variants as $row) {
            $sku = trim((string)($row['parent_sku'] ?? ''));
            if (!empty($sku)) {
                $groups[$sku][] = $row;
            }
        }
        return $groups;
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
