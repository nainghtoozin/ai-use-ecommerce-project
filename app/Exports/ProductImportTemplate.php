<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithMultipleSheets;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductImportTemplate implements WithMultipleSheets
{
    public function sheets(): array
    {
        return [
            'Products' => new ProductImportTemplateProductsSheet(),
            'Variants' => new ProductImportTemplateVariantsSheet(),
        ];
    }
}

class ProductImportTemplateProductsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function collection(): Collection
    {
        return collect([
            ['WM001', 'Wireless Mouse', 'single', 'Ergonomic wireless mouse with USB receiver', '', 'Electronics', 'Generic', 'Piece', '19.99', '10.00', '50', '123456789012', 'active', ''],
            ['TS001', 'Basic T-Shirt', 'variable', 'Cotton t-shirt available in multiple sizes', '', 'Clothing', 'Apparel Co', 'Piece', '14.99', '6.00', '0', '123456789020', 'active', ''],
        ]);
    }

    public function headings(): array
    {
        return [
            'SKU',
            'Product Name',
            'Product Type',
            'Description',
            'Full Description',
            'Category',
            'Brand',
            'Unit',
            'Selling Price',
            'Cost Price',
            'Stock',
            'Barcode',
            'Status',
            'Notes',
        ];
    }

    public function title(): string
    {
        return 'Products';
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, 'B' => 30, 'C' => 12, 'D' => 40, 'E' => 40,
            'F' => 18, 'G' => 15, 'H' => 12, 'I' => 14,
            'J' => 12, 'K' => 10, 'L' => 18, 'M' => 10, 'N' => 20,
        ];
    }
}

class ProductImportTemplateVariantsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function collection(): Collection
    {
        return collect([
            ['TS001', 'TS001-RED-S', 'Color', 'Red', 'Size', 'S', '', '', '14.99', '6.00', '20', '123456789021', 'active'],
            ['TS001', 'TS001-RED-M', 'Color', 'Red', 'Size', 'M', '', '', '14.99', '6.00', '25', '123456789022', 'active'],
            ['TS001', 'TS001-BLUE-S', 'Color', 'Blue', 'Size', 'S', '', '', '14.99', '6.00', '15', '123456789023', 'active'],
        ]);
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
            'Option 3 Name',
            'Option 3 Value',
            'Selling Price',
            'Cost Price',
            'Stock',
            'Barcode',
            'Status',
        ];
    }

    public function title(): string
    {
        return 'Variants';
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, 'B' => 20, 'C' => 16, 'D' => 16,
            'E' => 16, 'F' => 16, 'G' => 16, 'H' => 16,
            'I' => 14, 'J' => 12, 'K' => 10, 'L' => 18, 'M' => 10,
        ];
    }
}
