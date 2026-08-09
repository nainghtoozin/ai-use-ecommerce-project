<?php

namespace App\Services;

use App\Models\Brand;
use App\Models\Category;
use App\Models\Unit;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Maatwebsite\Excel\Facades\Excel;
use App\Imports\SimpleExcelImport;

class ImportService
{
    public function importCategories(UploadedFile $file, int $tenantId): array
    {
        return $this->import($file, $tenantId, 'categories');
    }

    public function importBrands(UploadedFile $file, int $tenantId): array
    {
        return $this->import($file, $tenantId, 'brands');
    }

    public function importUnits(UploadedFile $file, int $tenantId): array
    {
        return $this->import($file, $tenantId, 'units');
    }

    private function import(UploadedFile $file, int $tenantId, string $type): array
    {
        $rows = $this->parseFile($file);
        $results = ['created' => 0, 'updated' => 0, 'skipped' => 0, 'errors' => []];

        DB::beginTransaction();

        try {
            foreach ($rows as $lineNumber => $row) {
                $lineNumber += 2; // Account for header row + 1-based index

                try {
                    $this->validateRow($row, $type, $lineNumber);
                    $result = $this->createOrUpdateRecord($row, $type, $tenantId);

                    if ($result === 'created') {
                        $results['created']++;
                    } elseif ($result === 'updated') {
                        $results['updated']++;
                    } else {
                        $results['skipped']++;
                    }
                } catch (\Throwable $e) {
                    $results['errors'][] = "Line {$lineNumber}: " . $e->getMessage();
                }
            }

            if (empty($results['errors'])) {
                DB::commit();
            } else {
                DB::rollBack();
                $results['created'] = 0;
                $results['updated'] = 0;
                $results['skipped'] = 0;
            }
        } catch (\Throwable $e) {
            DB::rollBack();
            $results['errors'][] = 'Import failed: ' . $e->getMessage();
        }

        return $results;
    }

    private function parseFile(UploadedFile $file): array
    {
        $extension = strtolower($file->getClientOriginalExtension());

        if (in_array($extension, ['xlsx', 'xls'])) {
            return $this->parseExcel($file);
        }

        return $this->parseCsv($file);
    }

    private function parseCsv(UploadedFile $file): array
    {
        $rows = [];
        $handle = fopen($file->getRealPath(), 'r');
        $header = fgetcsv($handle);

        if (!$header) {
            fclose($handle);
            return [];
        }

        $header = array_map(fn($h) => strtolower(trim($h)), $header);

        while (($data = fgetcsv($handle)) !== false) {
            if (count($data) >= 1 && !empty(trim($data[0]))) {
                $rows[] = array_combine($header, $data);
            }
        }

        fclose($handle);
        return $rows;
    }

    private function parseExcel(UploadedFile $file): array
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

        return $rows;
    }

    private function validateRow(array $row, string $type, int $lineNumber): void
    {
        if (empty($row['name'])) {
            throw new \InvalidArgumentException('Name is required.');
        }

        if (mb_strlen($row['name']) > 255) {
            throw new \InvalidArgumentException('Name must be 255 characters or less.');
        }

        if ($type === 'units' && empty($row['short_name'])) {
            throw new \InvalidArgumentException('Short name is required for units.');
        }

        if ($type === 'units' && mb_strlen($row['short_name'] ?? '') > 50) {
            throw new \InvalidArgumentException('Short name must be 50 characters or less.');
        }

        if (!empty($row['status']) && !in_array(strtolower($row['status']), ['active', 'inactive'])) {
            throw new \InvalidArgumentException('Status must be "active" or "inactive".');
        }
    }

    private function createOrUpdateRecord(array $row, string $type, int $tenantId): string
    {
        $name = trim($row['name']);
        $status = !empty($row['status']) ? strtolower(trim($row['status'])) : 'active';
        $isActive = $status === 'active';

        switch ($type) {
            case 'categories':
                $existing = Category::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('name', $name)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'description' => trim($row['description'] ?? ''),
                    ]);
                    return 'updated';
                }

                Category::withoutTenantScope()->create([
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'description' => trim($row['description'] ?? ''),
                ]);
                return 'created';

            case 'brands':
                $existing = Brand::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('name', $name)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'description' => trim($row['description'] ?? ''),
                        'is_active' => $isActive,
                    ]);
                    return 'updated';
                }

                Brand::withoutTenantScope()->create([
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'slug' => \Illuminate\Support\Str::slug($name),
                    'description' => trim($row['description'] ?? ''),
                    'is_active' => $isActive,
                ]);
                return 'created';

            case 'units':
                $shortName = trim($row['short_name'] ?? '');

                $existing = Unit::withoutTenantScope()
                    ->where('tenant_id', $tenantId)
                    ->where('name', $name)
                    ->first();

                if ($existing) {
                    $existing->update([
                        'short_name' => $shortName,
                        'description' => trim($row['description'] ?? ''),
                        'is_active' => $isActive,
                    ]);
                    return 'updated';
                }

                Unit::withoutTenantScope()->create([
                    'tenant_id' => $tenantId,
                    'name' => $name,
                    'short_name' => $shortName,
                    'description' => trim($row['description'] ?? ''),
                    'is_active' => $isActive,
                ]);
                return 'created';

            default:
                throw new \InvalidArgumentException("Unknown import type: {$type}");
        }
    }
}
