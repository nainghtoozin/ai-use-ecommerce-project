<?php

namespace App\Services\ImportExport;

use App\Enums\ProductType;
use App\Models\Brand;
use App\Models\Category;
use App\Models\Product;
use App\Models\ProductVariant;
use App\Models\Unit;

class ImportValidator
{
    /**
     * Validate a single product row.
     * Returns array of error messages (empty = valid).
     */
    public function validateProductRow(array $row, int $lineNumber, int $tenantId): array
    {
        $errors = [];

        // Name is always required
        if (empty($row['name'])) {
            $errors[] = "Line {$lineNumber}: Product name is required.";
        } elseif (mb_strlen($row['name']) > 255) {
            $errors[] = "Line {$lineNumber}: Product name must be 255 characters or less.";
        }

        // Price validation
        if (empty($row['price']) && empty($row['variant_price'])) {
            $errors[] = "Line {$lineNumber}: Selling price is required.";
        } elseif (!empty($row['price']) && !is_numeric($row['price'])) {
            $errors[] = "Line {$lineNumber}: Selling price must be a number.";
        }

        // Type validation
        if (!empty($row['type'])) {
            $validTypes = [ProductType::SINGLE, ProductType::VARIABLE, ProductType::COMBO];
            if (!in_array(strtolower($row['type']), $validTypes)) {
                $errors[] = "Line {$lineNumber}: Product type must be 'single', 'variable', or 'combo'.";
            }
        }

        // Category validation
        if (!empty($row['category'])) {
            $exists = Category::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('name', $row['category'])
                ->exists();
            if (!$exists) {
                $errors[] = "Line {$lineNumber}: Category '{$row['category']}' not found.";
            }
        }

        // Brand validation
        if (!empty($row['brand'])) {
            $exists = Brand::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('name', $row['brand'])
                ->exists();
            if (!$exists) {
                $errors[] = "Line {$lineNumber}: Brand '{$row['brand']}' not found.";
            }
        }

        // Unit validation
        if (!empty($row['unit'])) {
            $exists = Unit::withoutTenantScope()
                ->where('tenant_id', $tenantId)
                ->where('name', $row['unit'])
                ->exists();
            if (!$exists) {
                $errors[] = "Line {$lineNumber}: Unit '{$row['unit']}' not found.";
            }
        }

        // Status validation
        if (!empty($row['status'])) {
            $validStatuses = ['active', 'inactive', 'draft'];
            if (!in_array(strtolower($row['status']), $validStatuses)) {
                $errors[] = "Line {$lineNumber}: Status must be 'active', 'inactive', or 'draft'.";
            }
        }

        // Stock validation
        if (!empty($row['stock']) && !is_numeric($row['stock'])) {
            $errors[] = "Line {$lineNumber}: Stock must be a number.";
        }

        // Variant-specific validation
        if (!empty($row['parent_sku'])) {
            if (empty($row['variant_sku'])) {
                $errors[] = "Line {$lineNumber}: Variant SKU is required when parent SKU is specified.";
            }
            if (!empty($row['variant_price']) && !is_numeric($row['variant_price'])) {
                $errors[] = "Line {$lineNumber}: Variant price must be a number.";
            }
            if (!empty($row['variant_stock']) && !is_numeric($row['variant_stock'])) {
                $errors[] = "Line {$lineNumber}: Variant stock must be a number.";
            }
        }

        return $errors;
    }

    /**
     * Validate all rows and return a summary.
     */
    public function validateAll(array $rows, int $tenantId): array
    {
        $total = count($rows);
        $valid = 0;
        $invalid = 0;
        $warnings = [];
        $errors = [];
        $skus = [];

        foreach ($rows as $index => $row) {
            $lineNumber = $index + 2; // Account for header
            $rowErrors = $this->validateProductRow($row, $lineNumber, $tenantId);

            // Duplicate SKU check within file
            $sku = $row['sku'] ?? $row['variant_sku'] ?? null;
            if ($sku) {
                if (isset($skus[$sku])) {
                    $warnings[] = "Line {$lineNumber}: Duplicate SKU '{$sku}' (first seen on line {$skus[$sku]}).";
                } else {
                    $skus[$sku] = $lineNumber;
                }
            }

            // Existing product check
            if (!empty($row['sku'])) {
                $exists = Product::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('sku', $row['sku'])
                    ->exists();
                if ($exists) {
                    $warnings[] = "Line {$lineNumber}: Product with SKU '{$row['sku']}' already exists (will be updated).";
                }
            }

            if (empty($rowErrors)) {
                $valid++;
            } else {
                $invalid++;
                $errors = array_merge($errors, $rowErrors);
            }
        }

        return [
            'total' => $total,
            'valid' => $valid,
            'invalid' => $invalid,
            'warnings' => $warnings,
            'errors' => $errors,
        ];
    }
}
