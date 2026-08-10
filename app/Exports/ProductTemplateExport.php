<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;
use PhpOffice\PhpSpreadsheet\Style\Alignment;
use PhpOffice\PhpSpreadsheet\Style\Fill;
use PhpOffice\PhpSpreadsheet\Style\Font;

class ProductTemplateExport implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'README' => new ReadmeSheet(),
            'Categories' => new CategoriesSheet(),
            'Brands' => new BrandsSheet(),
            'Units' => new UnitsSheet(),
            'Products' => new ProductsSheet(),
            'Variants' => new VariantsSheet(),
        ];
    }
}

class ReadmeSheet implements FromCollection, WithTitle, WithStyles, WithColumnWidths
{
    public function collection(): Collection
    {
        $instructions = [
            ['PRODUCT IMPORT TEMPLATE — INSTRUCTIONS'],
            [''],
            ['This template allows you to import products (single and variable) into your store.'],
            ['Fill in each sheet carefully, then upload the completed file.'],
            [''],
            ['─────────────────────────────────────────────'],
            ['HOW TO USE'],
            ['─────────────────────────────────────────────'],
            [''],
            ['1. Fill in the Categories, Brands, and Units sheets first (if adding new ones).'],
            ['2. Add your products in the Products sheet.'],
            ['3. For variable products, add variants in the Variants sheet.'],
            ['4. Upload the completed file using the Import button in your dashboard.'],
            [''],
            ['─────────────────────────────────────────────'],
            ['REQUIRED FIELDS'],
            ['─────────────────────────────────────────────'],
            [''],
            ['Products Sheet:'],
            ['  • Product Name — required for all products'],
            ['  • Product Type — required: "single" or "variable"'],
            ['  • Selling Price — required for single products'],
            [''],
            ['Variants Sheet:'],
            ['  • Parent SKU — required (must match a product SKU)'],
            ['  • Variant SKU — required (must be unique)'],
            ['  • Option 1 Name — required (e.g., "Color")'],
            ['  • Option 1 Value — required (e.g., "Red")'],
            ['  • Price — required for each variant'],
            [''],
            ['─────────────────────────────────────────────'],
            ['PRODUCT TYPES'],
            ['─────────────────────────────────────────────'],
            [''],
            ['single — A simple product with no variations.'],
            ['         Example: A book, a charger, a fixed-price item.'],
            [''],
            ['variable — A product with multiple options (variants).'],
            ['           Example: A t-shirt available in different colors and sizes.'],
            ['           The parent product defines shared info (name, description).'],
            ['           Each variant defines its own price, stock, and attributes.'],
            [''],
            ['─────────────────────────────────────────────'],
            ['HOW VARIABLE PRODUCTS WORK'],
            ['─────────────────────────────────────────────'],
            [''],
            ['Step 1: Add a row in the Products sheet with:'],
            ['  • Product Type = "variable"'],
            ['  • SKU = a unique identifier (e.g., TS001)'],
            ['  • Fill in name, description, category, brand, unit'],
            ['  • Selling Price = default price (can be overridden per variant)'],
            [''],
            ['Step 2: Add one row per variant in the Variants sheet:'],
            ['  • Parent SKU = TS001 (links to the parent product)'],
            ['  • Variant SKU = TS001-RED-S (unique)'],
            ['  • Option 1 Name = Color'],
            ['  • Option 1 Value = Red'],
            ['  • Option 2 Name = Size'],
            ['  • Option 2 Value = S'],
            ['  • Price, Cost Price, Stock, Barcode per variant'],
            [''],
            ['─────────────────────────────────────────────'],
            ['MASTER DATA REFERENCES'],
            ['─────────────────────────────────────────────'],
            [''],
            ['Category, Brand, and Unit are referenced by NAME (not ID).'],
            [''],
            ['• If a category/brand/unit name matches an existing one in your store, it will be used.'],
            ['• If it does not match, it will be skipped (or you can create it first).'],
            ['• Pre-fill the Categories, Brands, and Units sheets with any new data you need.'],
            [''],
            ['─────────────────────────────────────────────'],
            ['IMPORT MODES'],
            ['─────────────────────────────────────────────'],
            [''],
            ['Create New Only — Only creates new products. Skips existing SKUs.'],
            ['Create + Update — Creates new products and updates existing ones by SKU.'],
            ['Update Existing — Only updates products that match existing SKUs.'],
            [''],
            ['─────────────────────────────────────────────'],
            ['EXAMPLE: SINGLE PRODUCT'],
            ['─────────────────────────────────────────────'],
            [''],
            ['Products Sheet:'],
            ['SKU: WM001 | Name: Wireless Mouse | Type: single | Price: 19.99 | Stock: 50'],
            [''],
            ['─────────────────────────────────────────────'],
            ['EXAMPLE: VARIABLE PRODUCT'],
            ['─────────────────────────────────────────────'],
            [''],
            ['Products Sheet:'],
            ['SKU: TS001 | Name: Basic T-Shirt | Type: variable | Price: 29.99'],
            [''],
            ['Variants Sheet:'],
            ['Parent: TS001 | Variant: TS001-RED-S | Color: Red | Size: S | Price: 29.99 | Stock: 10'],
            ['Parent: TS001 | Variant: TS001-RED-M | Color: Red | Size: M | Price: 29.99 | Stock: 15'],
            ['Parent: TS001 | Variant: TS001-BLUE-S | Color: Blue | Size: S | Price: 29.99 | Stock: 8'],
            [''],
            ['─────────────────────────────────────────────'],
            ['IMPORTANT RULES'],
            ['─────────────────────────────────────────────'],
            [''],
            ['• Do NOT modify column headers.'],
            ['• Do NOT add or remove columns.'],
            ['• Do NOT use database IDs. Use names for categories, brands, units.'],
            ['• SKU must be unique per tenant for products.'],
            ['• Variant SKU must be unique per product.'],
            ['• Status values: active, inactive, draft'],
            ['• This file can be re-imported after export (Export → Edit → Import).'],
            [''],
        ];

        return collect($instructions)->map(fn($row) => [$row[0]]);
    }

    public function title(): string
    {
        return 'README';
    }

    public function styles(Worksheet $sheet): array
    {
        $sheet->mergeCells('A1:A1');
        $sheet->getStyle('A1')->getFont()->setBold(true)->setSize(16);
        $sheet->getColumnDimension('A')->setWidth(80);
        return [];
    }

    public function columnWidths(): array
    {
        return ['A' => 80];
    }
}

class CategoriesSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function collection(): Collection
    {
        return collect([
            ['Electronics', 'Electronic devices and accessories', 'active'],
            ['Fashion', 'Clothing, shoes, and accessories', 'active'],
            ['Beauty', 'Beauty and personal care products', 'active'],
        ]);
    }

    public function title(): string
    {
        return 'Categories';
    }

    public function headings(): array
    {
        return ['Name', 'Description', 'Status'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 40,
            'C' => 12,
        ];
    }
}

class BrandsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function collection(): Collection
    {
        return collect([
            ['Samsung', 'Samsung electronics', 'active'],
            ['Nike', 'Nike sportswear', 'active'],
            ['Generic', 'Generic or unbranded', 'active'],
        ]);
    }

    public function title(): string
    {
        return 'Brands';
    }

    public function headings(): array
    {
        return ['Name', 'Description', 'Status'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 25,
            'B' => 40,
            'C' => 12,
        ];
    }
}

class UnitsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function collection(): Collection
    {
        return collect([
            ['Piece', 'pcs', 'Single item', '', '', 'active'],
            ['Kilogram', 'kg', 'Weight in kilograms', '', '', 'active'],
            ['Box', 'box', 'Items packed in a box', '', '', 'active'],
        ]);
    }

    public function title(): string
    {
        return 'Units';
    }

    public function headings(): array
    {
        return ['Name', 'Short Name', 'Description', 'Base Unit', 'Operator / Value', 'Status'];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 20,
            'B' => 12,
            'C' => 30,
            'D' => 15,
            'E' => 20,
            'F' => 12,
        ];
    }
}

class ProductsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function collection(): Collection
    {
        return collect([
            // Single product example
            ['WM001', 'Wireless Mouse', 'single', 'Ergonomic wireless mouse with USB receiver', 'Electronics', 'Generic', 'Piece', '19.99', '10.00', '50', '123456789012', 'active', '', '', ''],
            ['USB-CABLE-001', 'USB-C Charging Cable', 'single', 'Fast charging USB-C cable 1m', 'Electronics', 'Generic', 'Piece', '9.99', '4.00', '100', '123456789013', 'active', '', '', ''],
            // Variable product example (parent only — variants go in Variants sheet)
            ['TS001', 'Basic T-Shirt', 'variable', 'Premium cotton t-shirt available in multiple colors and sizes', 'Fashion', 'Nike', 'Piece', '29.99', '15.00', '', '', 'active', 'Color, Size', 'Red, Blue / S, M', ''],
            ['CAP001', 'Baseball Cap', 'variable', 'Adjustable baseball cap with logo', 'Fashion', 'Nike', 'Piece', '24.99', '12.00', '', '', 'active', 'Color', 'Black, White, Red', ''],
        ]);
    }

    public function title(): string
    {
        return 'Products';
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Product Name',
            'Product Type',
            'Description',
            'Category',
            'Brand',
            'Unit',
            'Selling Price',
            'Cost Price',
            'Stock',
            'Barcode',
            'Status',
            'Variant Option Names',
            'Variant Option Values',
            'Notes',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18,  // SKU
            'B' => 30,  // Product Name
            'C' => 12,  // Product Type
            'D' => 40,  // Description
            'E' => 18,  // Category
            'F' => 15,  // Brand
            'G' => 12,  // Unit
            'H' => 14,  // Selling Price
            'I' => 12,  // Cost Price
            'J' => 10,  // Stock
            'K' => 18,  // Barcode
            'L' => 10,  // Status
            'M' => 22,  // Variant Option Names
            'N' => 30,  // Variant Option Values
            'O' => 20,  // Notes
        ];
    }
}

class VariantsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function collection(): Collection
    {
        return collect([
            // Variant for TS001 (Basic T-Shirt)
            ['TS001', 'TS001-RED-S', 'Color', 'Red', 'Size', 'S', '29.99', '15.00', '10', 'BAR-TS001-RS', 'active'],
            ['TS001', 'TS001-RED-M', 'Color', 'Red', 'Size', 'M', '29.99', '15.00', '15', 'BAR-TS001-RM', 'active'],
            ['TS001', 'TS001-BLUE-S', 'Color', 'Blue', 'Size', 'S', '29.99', '15.00', '8', 'BAR-TS001-BS', 'active'],
            ['TS001', 'TS001-BLUE-M', 'Color', 'Blue', 'Size', 'M', '29.99', '15.00', '12', 'BAR-TS001-BM', 'active'],
            // Variant for CAP001 (Baseball Cap)
            ['CAP001', 'CAP001-BLK', 'Color', 'Black', '', '', '24.99', '12.00', '20', 'BAR-CAP-BLK', 'active'],
            ['CAP001', 'CAP001-WHT', 'Color', 'White', '', '', '24.99', '12.00', '18', 'BAR-CAP-WHT', 'active'],
            ['CAP001', 'CAP001-RED', 'Color', 'Red', '', '', '27.99', '14.00', '5', 'BAR-CAP-RED', 'active'],
        ]);
    }

    public function title(): string
    {
        return 'Variants';
    }

    public function headings(): array
    {
        return [
            'Parent SKU',
            'Variant SKU',
            'Option 1 Name',
            'Option 1 Value',
            'Option 2 Name',
            'Option 2 Value',
            'Price',
            'Cost Price',
            'Stock',
            'Barcode',
            'Status',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [
            1 => ['font' => ['bold' => true]],
        ];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15,  // Parent SKU
            'B' => 20,  // Variant SKU
            'C' => 16,  // Option 1 Name
            'D' => 16,  // Option 1 Value
            'E' => 16,  // Option 2 Name
            'F' => 16,  // Option 2 Value
            'G' => 14,  // Price
            'H' => 12,  // Cost Price
            'I' => 10,  // Stock
            'J' => 18,  // Barcode
            'K' => 10,  // Status
        ];
    }
}
