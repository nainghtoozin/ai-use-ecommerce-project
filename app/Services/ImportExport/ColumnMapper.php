<?php

namespace App\Services\ImportExport;

class ColumnMapper
{
    /**
     * Default column mappings for product import.
     * Maps common file column names to system field names.
     */
    public static function productMappings(): array
    {
        return [
            'sku' => 'sku',
            'product sku' => 'sku',
            'product_name' => 'name',
            'product name' => 'name',
            'name' => 'name',
            'product_type' => 'type',
            'product type' => 'type',
            'type' => 'type',
            'description' => 'short_description',
            'full_description' => 'description',
            'full description' => 'description',
            'short_description' => 'short_description',
            'short description' => 'short_description',
            'category' => 'category',
            'category_name' => 'category',
            'category name' => 'category',
            'brand' => 'brand',
            'brand_name' => 'brand',
            'brand name' => 'brand',
            'unit' => 'unit',
            'unit_name' => 'unit',
            'unit name' => 'unit',
            'selling_price' => 'price',
            'selling price' => 'price',
            'price' => 'price',
            'sale_price' => 'price',
            'cost_price' => 'cost_price',
            'cost price' => 'cost_price',
            'cost' => 'cost_price',
            'barcode' => 'barcode',
            'status' => 'status',
            'stock' => 'stock',
            'quantity' => 'stock',
            'weight' => 'weight',
            'image' => 'photo1',
            'image_url' => 'photo1',
            'photo' => 'photo1',
            'seo_title' => 'seo_title',
            'seo title' => 'seo_title',
            'seo_description' => 'seo_description',
            'seo description' => 'seo_description',
            'parent_sku' => 'parent_sku',
            'parent sku' => 'parent_sku',
            'parent sku/product sku' => 'parent_sku',
            'variant_sku' => 'variant_sku',
            'variant sku' => 'variant_sku',
            'attribute_name' => 'attribute_name',
            'attribute name' => 'attribute_name',
            'attribute' => 'attribute_name',
            'attribute_value' => 'attribute_value',
            'attribute value' => 'attribute_value',
            'value' => 'attribute_value',
            'variant_price' => 'variant_price',
            'variant price' => 'variant_price',
            'variant_cost' => 'variant_cost',
            'variant cost' => 'variant_cost',
            'variant_stock' => 'variant_stock',
            'variant stock' => 'variant_stock',
            'variant_barcode' => 'variant_barcode',
            'variant barcode' => 'variant_barcode',
            'variant_image' => 'variant_image',
            'variant image' => 'variant_image',
            'base_unit' => 'base_unit',
            'base unit' => 'base_unit',
            'selling_unit' => 'selling_unit',
            'selling unit' => 'selling_unit',
            'conversion' => 'conversion',
            'operator' => 'operator',
            'operation_value' => 'operation_value',
            'operation value' => 'operation_value',
        ];
    }

    /**
     * Auto-map file headers to system fields.
     * Returns an array of [file_column => system_field] mappings.
     */
    public static function autoMap(array $fileHeaders): array
    {
        $mappings = self::productMappings();
        $result = [];

        foreach ($fileHeaders as $header) {
            $normalized = strtolower(trim($header));
            if (isset($mappings[$normalized])) {
                $result[$header] = $mappings[$normalized];
            }
        }

        return $result;
    }

    /**
     * Apply column mapping to a row.
     */
    public static function mapRow(array $row, array $mapping): array
    {
        $mapped = [];
        foreach ($mapping as $fileColumn => $systemField) {
            if (isset($row[$fileColumn])) {
                $mapped[$systemField] = $row[$fileColumn];
            }
        }
        return $mapped;
    }

    /**
     * Get available system fields for product import.
     */
    public static function productFields(): array
    {
        return [
            ['key' => 'sku', 'label' => 'SKU', 'required' => false],
            ['key' => 'name', 'label' => 'Product Name', 'required' => true],
            ['key' => 'type', 'label' => 'Product Type', 'required' => false],
            ['key' => 'short_description', 'label' => 'Short Description (Excel "Description" column)', 'required' => false],
            ['key' => 'description', 'label' => 'Full Description (Excel "Full Description" column)', 'required' => false],
            ['key' => 'category', 'label' => 'Category', 'required' => false],
            ['key' => 'brand', 'label' => 'Brand', 'required' => false],
            ['key' => 'unit', 'label' => 'Unit', 'required' => false],
            ['key' => 'price', 'label' => 'Selling Price', 'required' => true],
            ['key' => 'cost_price', 'label' => 'Cost Price', 'required' => false],
            ['key' => 'barcode', 'label' => 'Barcode', 'required' => false],
            ['key' => 'status', 'label' => 'Status', 'required' => false],
            ['key' => 'stock', 'label' => 'Stock', 'required' => false],
            ['key' => 'weight', 'label' => 'Weight', 'required' => false],
            ['key' => 'photo1', 'label' => 'Image URL', 'required' => false],
            ['key' => 'seo_title', 'label' => 'SEO Title', 'required' => false],
            ['key' => 'seo_description', 'label' => 'SEO Description', 'required' => false],
            ['key' => 'parent_sku', 'label' => 'Parent SKU (variants)', 'required' => false],
            ['key' => 'variant_sku', 'label' => 'Variant SKU', 'required' => false],
            ['key' => 'attribute_name', 'label' => 'Attribute Name', 'required' => false],
            ['key' => 'attribute_value', 'label' => 'Attribute Value', 'required' => false],
            ['key' => 'variant_price', 'label' => 'Variant Price', 'required' => false],
            ['key' => 'variant_cost', 'label' => 'Variant Cost', 'required' => false],
            ['key' => 'variant_stock', 'label' => 'Variant Stock', 'required' => false],
            ['key' => 'variant_barcode', 'label' => 'Variant Barcode', 'required' => false],
            ['key' => 'variant_image', 'label' => 'Variant Image', 'required' => false],
        ];
    }
}
