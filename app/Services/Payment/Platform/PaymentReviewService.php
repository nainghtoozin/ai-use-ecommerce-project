<?php

namespace App\Services\Payment\Platform;

use App\Models\PaymentIntent;
use App\Models\PaymentReview;
use Illuminate\Support\Collection;
use InvalidArgumentException;

class PaymentReviewService
{
    public function __construct(
        private readonly ManualPaymentService $manualPayment,
    ) {}

    public function approve(
        PaymentIntent $intent,
        ?int $reviewerId = null,
        ?string $reviewerName = null,
        array $metadata = [],
    ): PaymentReview {
        if ($intent->status !== 'waiting_review') {
            throw new InvalidArgumentException(sprintf(
                'Cannot approve PaymentIntent #%d: must be in waiting_review status, currently %s.',
                $intent->id,
                $intent->status,
            ));
        }

        $this->manualPayment->approvePayment($intent);

        return PaymentReview::create([
            'payment_intent_id' => $intent->id,
            'action' => 'approved',
            'reviewer_id' => $reviewerId,
            'reviewer_name' => $reviewerName,
            'metadata' => $metadata,
        ]);
    }

    public function reject(
        PaymentIntent $intent,
        string $reason,
        ?int $reviewerId = null,
        ?string $reviewerName = null,
        array $metadata = [],
    ): PaymentReview {
        if ($intent->status !== 'waiting_review') {
            throw new InvalidArgumentException(sprintf(
                'Cannot reject PaymentIntent #%d: must be in waiting_review status, currently %s.',
                $intent->id,
                $intent->status,
            ));
        }

        $this->manualPayment->rejectPayment($intent, $reason);

        return PaymentReview::create([
            'payment_intent_id' => $intent->id,
            'action' => 'rejected',
            'reviewer_id' => $reviewerId,
            'reviewer_name' => $reviewerName,
            'reason' => $reason,
            'metadata' => $metadata,
        ]);
    }

    public function getPendingReviews(): Collection
    {
        return PaymentIntent::wherePendingReview()
            ->with(['tenant', 'plan', 'evidences', 'latestReview'])
            ->orderBy('created_at', 'asc')
            ->get();
    }

    public function getReviewHistory(PaymentIntent $intent): Collection
    {
        return $intent->reviews()->orderBy('created_at', 'desc')->get();
    }

    public function getRecentReviews(int $limit = 20): Collection
    {
        return PaymentReview::with(['paymentIntent.tenant', 'paymentIntent.plan'])
            ->orderBy('created_at', 'desc')
            ->limit($limit)
            ->get();
    }

    public function getRejectedIntents(): Collection
    {
        return PaymentIntent::whereRejected()
            ->with(['tenant', 'plan', 'evidences', 'reviews'])
            ->orderBy('rejected_at', 'desc')
            ->get();
    }
}
