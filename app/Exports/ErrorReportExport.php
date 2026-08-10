<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;
use Maatwebsite\Excel\Concerns\WithStyles;
use Maatwebsite\Excel\Concerns\WithColumnWidths;
use PhpOffice\PhpSpreadsheet\Worksheet\Worksheet;

class ErrorReportExport implements FromCollection, WithHeadings, WithStyles, WithColumnWidths
{
    private array $errors;

    public function __construct(array $errors)
    {
        $this->errors = $errors;
    }

    public function collection(): Collection
    {
        return collect($this->errors)->map(fn($e) => [
            $e['sheet'] ?? '',
            $e['row'] ?? '',
            $e['column'] ?? '',
            $this->humanizeColumn($e['column'] ?? ''),
            $e['value'] ?? '',
            $e['error'] ?? $e['warning'] ?? '',
            $this->suggestFix($e),
        ]);
    }

    public function headings(): array
    {
        return ['Sheet', 'Row', 'Column', 'Field', 'Value', 'Error', 'Suggested Fix'];
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
            'A' => 12,
            'B' => 8,
            'C' => 20,
            'D' => 20,
            'E' => 25,
            'F' => 40,
            'G' => 45,
        ];
    }

    private function humanizeColumn(string $column): string
    {
        return match ($column) {
            'product_name' => 'Product Name',
            'product_type' => 'Product Type',
            'selling_price' => 'Selling Price',
            'cost_price' => 'Cost Price',
            'parent_sku' => 'Parent SKU',
            'variant_sku' => 'Variant SKU',
            'option_1_name' => 'Option 1 Name',
            'option_1_value' => 'Option 1 Value',
            'option_2_name' => 'Option 2 Name',
            'option_2_value' => 'Option 2 Value',
            default => ucfirst(str_replace('_', ' ', $column)),
        };
    }

    private function suggestFix(array $e): string
    {
        $column = $e['column'] ?? '';
        $error = $e['error'] ?? $e['warning'] ?? '';

        if (str_contains($error, 'required')) {
            return 'Fill in this required field.';
        }

        if (str_contains($error, 'not found')) {
            $field = $this->humanizeColumn($column);
            return "Create the {$field} first, or use an existing one from the template.";
        }

        if (str_contains($error, 'Duplicate SKU')) {
            return 'Use a unique SKU. Remove the duplicate row or change the SKU.';
        }

        if (str_contains($error, 'must be a number')) {
            return 'Enter a valid number (e.g. 19.99).';
        }

        if (str_contains($error, 'must be')) {
            return 'Check the allowed values in the template instructions.';
        }

        if (str_contains($error, 'already exists')) {
            return 'This SKU exists in your store. Use "Create + Update" mode to update it.';
        }

        return 'Check the template instructions and correct this value.';
    }
}
