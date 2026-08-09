<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use Maatwebsite\Excel\Facades\Excel;
use App\Exports\SimpleExcelExport;
use Symfony\Component\HttpFoundation\StreamedResponse;

class ExportService
{
    public function exportCategories(string $format, ?string $search = null): StreamedResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $query = Category::forCurrentTenant()->orderBy('name');

        if ($search) {
            $query->where('name', 'like', "%{$search}%");
        }

        $data = $query->get()->map(fn($item) => [
            'name' => $item->name,
            'description' => $item->description ?? '',
            'status' => 'active',
        ]);

        $headers = ['Name', 'Description', 'Status'];

        return $this->export($data, $headers, 'categories', $format);
    }

    public function exportBrands(string $format, ?string $search = null): StreamedResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $query = Brand::forCurrentTenant();

        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('slug', 'like', "%{$search}%");
        }

        $data = $query->orderBy('name')->get()->map(fn($item) => [
            'name' => $item->name,
            'description' => $item->description ?? '',
            'status' => $item->is_active ? 'active' : 'inactive',
        ]);

        $headers = ['Name', 'Description', 'Status'];

        return $this->export($data, $headers, 'brands', $format);
    }

    public function exportUnits(string $format, ?string $search = null): StreamedResponse|\Symfony\Component\HttpFoundation\BinaryFileResponse
    {
        $query = Unit::forCurrentTenant();

        if ($search) {
            $query->where('name', 'like', "%{$search}%")
                  ->orWhere('short_name', 'like', "%{$search}%");
        }

        $data = $query->orderBy('name')->get()->map(fn($item) => [
            'name' => $item->name,
            'short_name' => $item->short_name,
            'base_unit' => '',
            'operator' => '',
            'operation_value' => '',
            'status' => $item->is_active ? 'active' : 'inactive',
        ]);

        $headers = ['Name', 'Short Name', 'Base Unit', 'Operator', 'Operation Value', 'Status'];

        return $this->export($data, $headers, 'units', $format);
    }

    private function export($data, array $headers, string $name, string $format)
    {
        $filename = "{$name}_" . now()->format('Y-m-d_His');

        if ($format === 'xlsx') {
            return Excel::download(new SimpleExcelExport($data, $headers), "{$filename}.xlsx");
        }

        return $this->exportCsv($data, $headers, $filename);
    }

    private function exportCsv($data, array $headers, string $filename): StreamedResponse
    {
        $callback = function () use ($data, $headers) {
            $file = fopen('php://output', 'w');
            fputcsv($file, $headers);

            foreach ($data as $row) {
                fputcsv($file, array_values($row));
            }

            fclose($file);
        };

        return new StreamedResponse($callback, 200, [
            'Content-Type' => 'text/csv',
            'Content-Disposition' => "attachment; filename=\"{$filename}.csv\"",
        ]);
    }
}
