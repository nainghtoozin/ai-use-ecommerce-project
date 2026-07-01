<?php

namespace App\Services\Payment\Platform;

use App\Models\PaymentEvidence;
use App\Models\PaymentIntent;
use Illuminate\Support\Collection;

class PaymentEvidenceService
{
    public function __construct(
        private readonly PaymentTimelineService $timeline,
    ) {}

    public function store(
        PaymentIntent $intent,
        string $type,
        ?string $filePath = null,
        ?string $note = null,
        array $metadata = [],
    ): PaymentEvidence {
        $evidence = PaymentEvidence::create([
            'payment_intent_id' => $intent->id,
            'type' => $type,
            'file_path' => $filePath,
            'note' => $note,
            'metadata' => $metadata,
        ]);

        $this->timeline->record(
            intent: $intent,
            type: 'evidence_uploaded',
            description: "Evidence uploaded: {$type}",
            metadata: ['evidence_type' => $type, 'evidence_id' => $evidence->id],
        );

        return $evidence;
    }

    public function getForIntent(PaymentIntent $intent): Collection
    {
        return $intent->evidences()->orderBy('created_at', 'desc')->get();
    }

    public function remove(int $evidenceId): bool
    {
        $evidence = PaymentEvidence::find($evidenceId);

        if (!$evidence) {
            return false;
        }

        return (bool) $evidence->delete();
    }

    public function hasEvidence(PaymentIntent $intent): bool
    {
        return $intent->evidences()->exists();
    }

    public function count(PaymentIntent $intent): int
    {
        return $intent->evidences()->count();
    }
}
