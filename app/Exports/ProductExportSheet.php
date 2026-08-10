<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ProductExportSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
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

    public function styles(Worksheet $sheet): array
    {
        return [1 => ['font' => ['bold' => true]]];
    }

    public function columnWidths(): array
    {
        return [
            'A' => 18, 'B' => 30, 'C' => 12, 'D' => 40,
            'E' => 18, 'F' => 15, 'G' => 12, 'H' => 14,
            'I' => 12, 'J' => 10, 'K' => 18, 'L' => 10, 'M' => 20,
        ];
    }
}
