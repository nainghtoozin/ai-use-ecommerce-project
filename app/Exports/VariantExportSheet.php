<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class VariantExportSheet implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    public function __construct(private array $rows) {}

    public function collection(): Collection
    {
        return collect($this->rows);
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
