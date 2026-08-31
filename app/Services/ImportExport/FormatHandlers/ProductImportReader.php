<?php

namespace App\Services\ImportExport\FormatHandlers;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class ProductImportReader
{
    public function read(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $result = [
            'products' => [],
            'variants' => [],
            'sheet_names' => [],
            'has_products_sheet' => false,
            'has_variants_sheet' => false,
        ];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $result['sheet_names'][] = $sheet->getTitle();
        }

        $productsSheet = $this->findSheet($spreadsheet, 'Products');
        if ($productsSheet) {
            $result['has_products_sheet'] = true;
            $result['products'] = $this->parseSheet($productsSheet);
        }

        $variantsSheet = $this->findSheet($spreadsheet, 'Variants');
        if ($variantsSheet) {
            $result['has_variants_sheet'] = true;
            $result['variants'] = $this->parseSheet($variantsSheet);
        }

        $spreadsheet->disconnectWorksheets();

        return $result;
    }

    private function findSheet($spreadsheet, string $name)
    {
        $sheet = $spreadsheet->getSheetByName($name);
        if ($sheet) {
            return $sheet;
        }

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            if (strtolower(trim($sheet->getTitle())) === strtolower($name)) {
                return $sheet;
            }
        }

        return null;
    }

    private function parseSheet($sheet): array
    {
        $rows = [];
        foreach ($sheet->getRowIterator() as $row) {
            $cellIterator = $row->getCellIterator();
            $cellIterator->setIterateOnlyExistingCells(false);
            $rowData = [];
            foreach ($cellIterator as $cell) {
                $value = $cell->getValue();
                if (is_null($value)) {
                    $rowData[] = '';
                } elseif (is_scalar($value)) {
                    $rowData[] = (string) $value;
                } else {
                    $rowData[] = '';
                }
            }
            $rows[] = $rowData;
        }

        if (empty($rows)) {
            return [];
        }

        $rawHeaders = array_shift($rows);
        $headers = [];
        foreach ($rawHeaders as $h) {
            $headers[] = $this->normalizeHeader((string) $h);
        }

        if (empty($headers) || $this->isEmptyRow(array_combine($headers, $rawHeaders) ?: [])) {
            return [];
        }

        $dataRows = [];
        foreach ($rows as $rowIndex => $row) {
            if (count($row) < count($headers)) {
                $row = array_pad($row, count($headers), '');
            }
            $row = array_slice($row, 0, count($headers));
            $mapped = array_combine($headers, $row);
            if ($mapped === false || $this->isEmptyRow($mapped)) {
                continue;
            }
            $dataRows[] = $mapped;
        }

        return $dataRows;
    }

    private function normalizeHeader(string $header): string
    {
        $header = trim($header);
        if (strlen($header) >= 3 && ord($header[0]) === 0xEF && ord($header[1]) === 0xBB && ord($header[2]) === 0xBF) {
            $header = substr($header, 3);
        }
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
