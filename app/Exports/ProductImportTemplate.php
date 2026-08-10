<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithTitle;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductImportTemplate implements FromCollection, WithHeadings, WithTitle, WithStyles, WithColumnWidths
{
    public function collection(): Collection
    {
        return collect([
            ['WM001', 'Wireless Mouse', 'single', 'Ergonomic wireless mouse with USB receiver', 'Electronics', 'Generic', 'Piece', '19.99', '10.00', '50', '123456789012', 'active', ''],
            ['USB-CABLE-001', 'USB-C Charging Cable', 'single', 'Fast charging USB-C cable 1m', 'Electronics', 'Generic', 'Piece', '9.99', '4.00', '100', '123456789013', 'active', ''],
        ]);
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
            'A' => 18,
            'B' => 30,
            'C' => 12,
            'D' => 40,
            'E' => 18,
            'F' => 15,
            'G' => 12,
            'H' => 14,
            'I' => 12,
            'J' => 10,
            'K' => 18,
            'L' => 10,
            'M' => 20,
        ];
    }
}
