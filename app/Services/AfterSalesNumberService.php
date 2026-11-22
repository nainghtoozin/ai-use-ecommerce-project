<?php

namespace App\Services;

use App\Models\ReferenceNumber;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;

class AfterSalesNumberService
{
    private const CODES = [
        'cancellation' => 'CAN',
        'return' => 'RMA',
        'refund' => 'REF',
    ];

    public function next(int $tenantId, string $type): string
    {
        $code = self::CODES[$type] ?? null;

        if (!$code) {
            throw new \InvalidArgumentException("Unknown after-sales document type: {$type}");
        }

        $date = Carbon::today();

        return DB::transaction(function () use ($tenantId, $code, $date) {
            $prefix = substr($code, 0, 1) . $tenantId;

            $record = ReferenceNumber::where('prefix', $prefix)
                ->where('date', $date->toDateString())
                ->lockForUpdate()
                ->first();

            if (!$record) {
                $record = ReferenceNumber::create([
                    'prefix' => $prefix,
                    'date' => $date->toDateString(),
                    'last_sequence' => 0,
                ]);
            }

            $record->increment('last_sequence');
            $record->refresh();

            return sprintf('%s-%s-%05d', $code, $date->format('Ymd'), $record->last_sequence);
        });
    }

    public function nextForTenant(int $tenantId, string $type, callable $exists): string
    {
        for ($attempt = 0; $attempt < 5; $attempt++) {
            $number = $this->next($tenantId, $type);

            if (!$exists($number)) {
                return $number;
            }
        }

        throw new \RuntimeException("Unable to allocate a unique {$type} number.");
    }
}
