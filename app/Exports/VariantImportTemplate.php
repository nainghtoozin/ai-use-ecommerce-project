<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VariantImportTemplate implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function collection(): Collection
    {
        return collect([
            ['TS001', 'TS001-RED-S', 'Color', 'Red', 'Size', 'S', '', '29.99', '15.00', '10', 'BAR-TS001-RS', 'active', ''],
            ['TS001', 'TS001-RED-M', 'Color', 'Red', 'Size', 'M', '', '29.99', '15.00', '15', 'BAR-TS001-RM', 'active', ''],
            ['TS001', 'TS001-BLUE-S', 'Color', 'Blue', 'Size', 'S', '', '29.99', '15.00', '8', 'BAR-TS001-BS', 'active', ''],
            ['TS001', 'TS001-BLUE-M', 'Color', 'Blue', 'Size', 'M', '', '29.99', '15.00', '12', 'BAR-TS001-BM', 'active', ''],
            ['CAP001', 'CAP001-BLK', 'Color', 'Black', '', '', '', '24.99', '12.00', '20', 'BAR-CAP-BLK', 'active', ''],
            ['CAP001', 'CAP001-WHT', 'Color', 'White', '', '', '', '24.99', '12.00', '18', 'BAR-CAP-WHT', 'active', ''],
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
            'A' => 15,
            'B' => 20,
            'C' => 16,
            'D' => 16,
            'E' => 16,
            'F' => 16,
            'G' => 16,
            'H' => 16,
            'I' => 14,
            'J' => 12,
            'K' => 10,
            'L' => 18,
            'M' => 10,
        ];
    }
}
