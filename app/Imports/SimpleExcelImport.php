<?php

namespace App\Imports;

use Maatwebsite\Excel\Concerns\ToCollection;
use Maatwebsite\Excel\Concerns\WithHeadingRow;
use Illuminate\Support\Collection;

class SimpleExcelImport implements ToCollection, WithHeadingRow
{
    protected $data;

    public function collection(Collection $rows): void
    {
        $this->data = $rows;
    }

    public function getData(): Collection
    {
        return $this->data ?? collect();
    }
}
