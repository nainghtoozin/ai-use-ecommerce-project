<?php

namespace App\Services\Payment\Platform;

use App\Models\ReferenceNumber;
use Illuminate\Support\Facades\DB;

class ReferenceNumberService
{
    const PREFIX_PAYMENT_INTENT = 'PAY';
    const PREFIX_SUBSCRIPTION = 'SUB';
    const PREFIX_INVOICE = 'INV';
    const PREFIX_REFUND = 'REF';
    const PREFIX_WEBHOOK = 'WEB';

    const SEQUENCE_PAD = 6;

    public function generate(string $prefix, ?string $date = null): string
    {
        $date = $date ?? now()->format('Ymd');
        $dateObj = now()->format('Y-m-d');

        $sequence = DB::transaction(function () use ($prefix, $dateObj) {
            $record = ReferenceNumber::lockForUpdate()
                ->firstOrCreate(
                    ['prefix' => $prefix, 'date' => $dateObj],
                    ['last_sequence' => 0]
                );

            $record->increment('last_sequence');

            return $record->fresh()->last_sequence;
        });

        return sprintf(
            '%s-%s-%s',
            $prefix,
            $date,
            str_pad((string) $sequence, self::SEQUENCE_PAD, '0', STR_PAD_LEFT)
        );
    }

    public function generatePaymentIntentRef(?string $date = null): string
    {
        return $this->generate(self::PREFIX_PAYMENT_INTENT, $date);
    }

    public function generateSubscriptionRef(?string $date = null): string
    {
        return $this->generate(self::PREFIX_SUBSCRIPTION, $date);
    }

    public function generateInvoiceRef(?string $date = null): string
    {
        return $this->generate(self::PREFIX_INVOICE, $date);
    }

    public function generateRefundRef(?string $date = null): string
    {
        return $this->generate(self::PREFIX_REFUND, $date);
    }

    public function generateWebhookRef(?string $date = null): string
    {
        return $this->generate(self::PREFIX_WEBHOOK, $date);
    }

    public function parsePrefix(string $reference): ?string
    {
        $parts = explode('-', $reference);
        return $parts[0] ?? null;
    }

    public function parseDate(string $reference): ?string
    {
        $parts = explode('-', $reference);
        return $parts[1] ?? null;
    }

    public function parseSequence(string $reference): ?string
    {
        $parts = explode('-', $reference);
        return $parts[2] ?? null;
    }
}
