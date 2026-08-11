<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VariableProductTemplateExport implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function collection(): Collection
    {
        return collect([
            ['SHIRT-001', 'Basic T-Shirt', 'Clothing', 'Nike', 'pcs', 'SHIRT-001-RED-S', 'Color', 'Red', 'Size', 'S', '', '', '29.99', '15.00', '10', 'BAR-TS001-RS', 'active'],
            ['SHIRT-001', 'Basic T-Shirt', 'Clothing', 'Nike', 'pcs', 'SHIRT-001-RED-M', 'Color', 'Red', 'Size', 'M', '', '', '29.99', '15.00', '15', 'BAR-TS001-RM', 'active'],
            ['SHIRT-001', 'Basic T-Shirt', 'Clothing', 'Nike', 'pcs', 'SHIRT-001-BLU-S', 'Color', 'Blue', 'Size', 'S', '', '', '29.99', '15.00', '8', 'BAR-TS001-BS', 'active'],
            ['SHIRT-001', 'Basic T-Shirt', 'Clothing', 'Nike', 'pcs', 'SHIRT-001-BLU-M', 'Color', 'Blue', 'Size', 'M', '', '', '29.99', '15.00', '12', 'BAR-TS001-BM', 'active'],
            ['CAP-001', 'Baseball Cap', 'Accessories', 'Nike', 'pcs', 'CAP-001-BLK', 'Color', 'Black', '', '', '', '', '24.99', '12.00', '20', 'BAR-CAP-BLK', 'active'],
            ['CAP-001', 'Baseball Cap', 'Accessories', 'Nike', 'pcs', 'CAP-001-WHT', 'Color', 'White', '', '', '', '', '24.99', '12.00', '18', 'BAR-CAP-WHT', 'active'],
        ]);
    }

    public function headings(): array
    {
        return [
            'Parent SKU',
            'Parent Name',
            'Category',
            'Brand',
            'Unit',
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
            'A' => 15,
            'B' => 22,
            'C' => 16,
            'D' => 15,
            'E' => 10,
            'F' => 20,
            'G' => 16,
            'H' => 16,
            'I' => 16,
            'J' => 16,
            'K' => 16,
            'L' => 16,
            'M' => 14,
            'N' => 12,
            'O' => 10,
            'P' => 18,
            'Q' => 10,
        ];
    }
}
