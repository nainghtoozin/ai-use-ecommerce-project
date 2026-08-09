<?php

namespace App\Exports;

use Illuminate\Support\Collection;
use Maatwebsite\Excel\Concerns\FromCollection;
use Maatwebsite\Excel\Concerns\WithHeadings;

class SimpleExcelExport implements FromCollection, WithHeadings
{
    protected Collection $data;
    protected array $headers;

    public function __construct(Collection $data, array $headers)
    {
        $this->data = $data;
        $this->headers = $headers;
    }

    public function collection(): Collection
    {
        return $this->data;
    }

    public function headings(): array
    {
        return $this->headers;
    }
}
