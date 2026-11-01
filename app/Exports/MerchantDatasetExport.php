<?php

namespace App\Exports;

use Iterator;
use Maatwebsite\Excel\Concerns\FromIterator;
use Maatwebsite\Excel\Concerns\ShouldAutoSize;
use Maatwebsite\Excel\Concerns\WithHeadings;

class MerchantDatasetExport implements FromIterator, WithHeadings, ShouldAutoSize
{
    public function __construct(
        private readonly array $headings,
        private readonly Iterator $rows
    ) {}

    public function headings(): array
    {
        return $this->headings;
    }

    public function iterator(): Iterator
    {
        return $this->rows;
    }
}
