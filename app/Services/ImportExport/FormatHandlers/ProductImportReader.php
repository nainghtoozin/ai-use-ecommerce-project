<?php

namespace App\Services\ImportExport\FormatHandlers;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductImportReader
{
    private const EXPECTED_HEADERS = [
        'sku', 'product_name', 'product_type', 'description', 'category',
        'brand', 'unit', 'selling_price', 'cost_price', 'stock',
        'barcode', 'status', 'notes',
    ];

    public function read(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName('Products');

        if (!$sheet) {
            $sheet = $spreadsheet->getActiveSheet();
        }

        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $rowData = [];
            foreach ($cellIterator as $cell) {
                $value = $cell->getValue();
                $rowData[] = is_null($value) ? '' : (string) $value;
            }
            $rows[] = $rowData;
        }

        $spreadsheet->disconnectWorksheets();

        if (empty($rows)) {
            return ['headers' => [], 'rows' => []];
        }

        $headers = array_map(fn($h) => $this->normalizeHeader($h), array_shift($rows));
        $dataRows = [];

        foreach ($rows as $row) {
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            }
            $row = array_slice($row, 0, count($headers));
            $mapped = array_combine($headers, $row);
            if ($this->isEmptyRow($mapped)) {
                continue;
            }
            $dataRows[] = $mapped;
        }

        return ['headers' => $headers, 'rows' => $dataRows];
    }

    public function validateStructure(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName('Products');

        if (!$sheet) {
            $sheet = $spreadsheet->getActiveSheet();
            $title = $sheet->getTitle();
            $spreadsheet->disconnectWorksheets();

            return [
                'valid' => $title !== 'Worksheet' && $title !== 'Sheet1',
                'has_products_sheet' => false,
                'actual_sheet' => $title,
            ];
        }

        $spreadsheet->disconnectWorksheets();

        return [
            'valid' => true,
            'has_products_sheet' => true,
        ];
    }

    private function normalizeHeader(string $header): string
    {
        $header = strtolower(trim($header));
        $header = preg_replace('/[^a-z0-9_]/', '_', $header);
        $header = preg_replace('/_+/', '_', $header);
        return trim($header, '_');
    }

    private function isEmptyRow(array $row): bool
    {
        foreach ($row as $value) {
            if (!empty(trim((string) $value))) {
                return false;
            }
        }
        return true;
    }
}
