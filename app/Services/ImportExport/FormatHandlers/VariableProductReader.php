<?php

namespace App\Services\ImportExport\FormatHandlers;

use Illuminate\Http\UploadedFile;
use PhpOffice\PhpSpreadsheet\IOFactory;

class VariableProductReader
{
    public function read(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $variantsSheet = $spreadsheet->getSheetByName('Variants');

        if (!$variantsSheet) {
            $variantsSheet = $spreadsheet->getActiveSheet();
        }

        $rows = $this->readSheet($variantsSheet);
        $spreadsheet->disconnectWorksheets();

        return ['variants' => $rows];
    }

    public function validateStructure(UploadedFile $file): array
    {
        $spreadsheet = IOFactory::load($file->getRealPath());
        $variantsSheet = $spreadsheet->getSheetByName('Variants');
        $spreadsheet->disconnectWorksheets();

        return [
            'valid' => $variantsSheet !== null,
            'has_variants_sheet' => $variantsSheet !== null,
        ];
    }

    private function readSheet($sheet): array
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

        if (empty($rows)) {
            return [];
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

        return $dataRows;
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
