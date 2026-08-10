<?php

namespace App\Services\ImportExport\FormatHandlers;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class VariantImportReader
{
    public function read(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $sheet = $spreadsheet->getSheetByName('Variants');

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
        $sheet = $spreadsheet->getSheetByName('Variants');
        $spreadsheet->disconnectWorksheets();

        return [
            'valid' => $sheet !== null,
            'has_variants_sheet' => $sheet !== null,
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
