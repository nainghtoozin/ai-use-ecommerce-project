<?php

namespace App\Services\ImportExport\FormatHandlers;

use App\Exports\SimpleExcelExport;
use App\Imports\SimpleExcelImport;
use Illuminate\Http\UploadedFile;
use Maatwebsite\Excel\Facades\Excel;
use Symfony\Component\HttpFoundation\BinaryFileResponse;

class ExcelHandler
{
    public function read(UploadedFile $file): array
    {
        $import = new SimpleExcelImport();
        Excel::import($import, $file);

        $rows = [];
        $header = null;

        foreach ($import->getData() as $index => $row) {
            $row = array_map(fn($v) => is_null($v) ? '' : trim((string) $v), $row->toArray());

            if ($index === 0) {
                $header = array_map(fn($h) => strtolower(trim($h)), $row);
                continue;
            }

            if (!empty($header) && count($row) >= count($header)) {
                $rows[] = array_combine($header, array_slice($row, 0, count($header)));
            }
        }

        return ['headers' => $header ?? [], 'rows' => $rows];
    }

    public function write(array $headers, array $rows, string $filename): BinaryFileResponse
    {
        $collection = collect($rows);
        return Excel::download(new SimpleExcelExport($collection, $headers), "{$filename}.xlsx");
    }
}
