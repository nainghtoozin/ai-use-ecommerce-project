<?php

namespace App\Services\ImportExport\FormatHandlers;

use Illuminate\Http\UploadedFile;
use Symfony\Component\HttpFoundation\StreamedResponse;

class CsvHandler
{
    public function read(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);

        if (!$header) {
            fclose($handle);
            return ['headers' => [], 'rows' => []];
        }

        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) >= 1 && !empty(trim($data[0]))) {
                $rows[] = array_combine($header, array_slice($data, 0, count($header)));
            }
        }

        fclose($handle);
        return ['headers' => $header, 'rows' => $rows];
    }

    public function write(array $headers, array $rows, string $filename): StreamedResponse
    {
        $callback = function () use ($headers, $rows) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);
            foreach ($rows as $row) {
                fputcsv($file, array_values($row));
            }
            fclose($file);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv; charset=UTF-8',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }
}
