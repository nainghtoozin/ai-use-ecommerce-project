<?php

namespace App\Services\Payment\Platform;

use App\Models\PaymentComment;
use App\Models\PaymentIntent;
use Illuminate\Support\Collection;

class PaymentCommentService
{
    public function __construct(
        private readonly PaymentTimelineService $timeline,
    ) {}

    public function addComment(
        PaymentIntent $intent,
        string $authorType,
        ?int $authorId,
        string $authorName,
        string $body,
        array $metadata = [],
    ): PaymentComment {
        $comment = PaymentComment::create([
            'payment_intent_id' => $intent->id,
            'author_type' => $authorType,
            'author_id' => $authorId,
            'author_name' => $authorName,
            'body' => $body,
            'metadata' => $metadata,
        ]);

        $this->timeline->record(
            intent: $intent,
            type: 'comment_added',
            description: "Comment added by {$authorName}",
            metadata: ['author_type' => $authorType, 'comment_id' => $comment->id],
        );

        return $comment;
    }

    public function getForIntent(PaymentIntent $intent): Collection
    {
        return PaymentComment::where('payment_intent_id', $intent->id)
            ->orderBy('created_at', 'asc')
            ->get();
    }
}
