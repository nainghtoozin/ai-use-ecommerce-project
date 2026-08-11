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

class VariableProductExport implements WithMultipleSheets
{
    private array $productRows;
    private array $variantRows;

    public function __construct(array $productRows, array $variantRows)
    {
        $this->productRows = $productRows;
        $this->variantRows = $variantRows;
    }

    public function sheets(): array
    {
        return [
            'Products' => new VariableProductExportProductsSheet($this->productRows),
            'Variants' => new VariableProductExportVariantsSheet($this->variantRows),
        ];
    }
}

class VariableProductExportProductsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
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
            'SKU', 'Product Name', 'Product Type', 'Description',
            'Category', 'Brand', 'Unit', 'Selling Price', 'Cost Price',
            'Stock', 'Barcode', 'Status',
        ];
    }

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, 'B' => 30, 'C' => 12, 'D' => 40,
            'E' => 18, 'F' => 15, 'G' => 12, 'H' => 14,
            'I' => 12, 'J' => 10, 'K' => 18, 'L' => 10,
        ];
    }
}

class VariableProductExportVariantsSheet implements FromCollection, WithTitle, WithHeadings, WithStyles, WithColumnWidths
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
            'Parent SKU', 'Variant SKU',
            'Option 1 Name', 'Option 1 Value',
            'Option 2 Name', 'Option 2 Value',
            'Option 3 Name', 'Option 3 Value',
            'Selling Price', 'Cost Price', 'Stock', 'Barcode', 'Status',
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
            'E' => 16, 'F' => 16, 'G' => 16, 'H' => 16,
            'I' => 14, 'J' => 12, 'K' => 10, 'L' => 18, 'M' => 10,
        ];
    }
}
