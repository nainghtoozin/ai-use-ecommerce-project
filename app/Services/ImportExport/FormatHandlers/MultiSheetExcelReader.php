<?php

namespace App\Services\ImportExport\FormatHandlers;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class MultiSheetExcelReader
{
    private const REQUIRED_SHEETS = ['Products', 'Variants'];
    private const OPTIONAL_SHEETS = ['Categories', 'Brands', 'Units'];
    private const ALL_SHEETS = ['README', 'Categories', 'Brands', 'Units', 'Products', 'Variants'];

    public function read(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $result = [];

        foreach (self::ALL_SHEETS as $sheetName) {
            $sheet = $spreadsheet->getSheetByName($sheetName);
            if ($sheet === null) {
                if (in_array($sheetName, self::REQUIRED_SHEETS)) {
                    throw new \RuntimeException("Required sheet '{$sheetName}' not found in the workbook.");
                }
                $result[$sheetName] = ['headers' => [], 'rows' => []];
                continue;
            }

            $rows = $this->parseSheet($sheet);
            if (empty($rows)) {
                $result[$sheetName] = ['headers' => [], 'rows' => []];
                continue;
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

            $result[$sheetName] = ['headers' => $headers, 'rows' => $dataRows];
        }

        $spreadsheet->disconnectWorksheets();
        return $result;
    }

    public function validateStructure(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheetNames = [];

        foreach ($spreadsheet->getWorksheetIterator() as $sheet) {
            $sheetNames[] = $sheet->getTitle();
        }

        $spreadsheet->disconnectWorksheets();

        $missing = [];
        foreach (self::REQUIRED_SHEETS as $required) {
            if (!in_array($required, $sheetNames)) {
                $missing[] = $required;
            }
        }

        return [
            'valid' => empty($missing),
            'sheets' => $sheetNames,
            'missing' => $missing,
        ];
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
                $rowData[] = is_null($value) ? '' : (string) $value;
            }
            $rows[] = $rowData;
        }
        return $rows;
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
