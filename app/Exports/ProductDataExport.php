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

class ProductDataExport implements WithMultipleSheets
{
    private array $products;
    private array $variants;
    private array $categories;
    private array $brands;
    private array $units;

    public function __construct(array $products, array $variants, array $categories, array $brands, array $units)
    {
        $this->products = $products;
        $this->variants = $variants;
        $this->categories = $categories;
        $this->brands = $brands;
        $this->units = $units;
    }

    public function sheets(): array
    {
        return [
            'Categories' => new ExportCategoriesSheet($this->categories),
            'Brands' => new ExportBrandsSheet($this->brands),
            'Units' => new ExportUnitsSheet($this->units),
            'Products' => new ExportProductsSheet($this->products),
            'Variants' => new ExportVariantsSheet($this->variants),
        ];
    }
}

class ExportCategoriesSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function title(): string { return 'Categories'; }

    public function headings(): array { return ['Name', 'Description', 'Status']; }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnWidths(): array
    {
        return ['A' => 25, 'B' => 40, 'C' => 12];
    }
}

class ExportBrandsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function title(): string { return 'Brands'; }

    public function headings(): array { return ['Name', 'Description', 'Status']; }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnWidths(): array
    {
        return ['A' => 25, 'B' => 40, 'C' => 12];
    }
}

class ExportUnitsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function title(): string { return 'Units'; }

    public function headings(): array { return ['Name', 'Short Name', 'Description', 'Base Unit', 'Operator / Value', 'Status']; }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnWidths(): array
    {
        return ['A' => 20, 'B' => 12, 'C' => 30, 'D' => 15, 'E' => 20, 'F' => 12];
    }
}

class ExportProductsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function title(): string { return 'Products'; }

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
            'Variant Option Names',
            'Variant Option Values',
            'Notes',
        ];
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
            'J' => 12, 'K' => 10, 'L' => 18, 'M' => 10,
            'N' => 22, 'O' => 30, 'P' => 20,
        ];
    }
}

class ExportVariantsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
    }

    public function title(): string { return 'Variants'; }

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
        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 15, 'B' => 20, 'C' => 16, 'D' => 16,
            'E' => 16, 'F' => 16, 'G' => 14, 'H' => 12,
            'I' => 10, 'J' => 18, 'K' => 10,
        ];
    }
}
