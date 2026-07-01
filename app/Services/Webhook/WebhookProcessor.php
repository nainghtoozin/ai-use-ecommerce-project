<?php

namespace App\Services\Webhook;

use App\Contracts\Webhook\GatewaySignatureVerifier;
use App\Contracts\Webhook\PaymentGatewayAdapter;
use App\Data\Webhook\WebhookEvent;
use App\Data\Webhook\WebhookResult;
use App\Events\Webhooks\GatewayNotificationReceived;
use App\Events\Webhooks\PaymentConfirmed;
use App\Events\Webhooks\PaymentFailed;
use App\Events\Webhooks\RefundReceived;
use App\Models\PaymentIntent;
use App\Models\WebhookLog;
use App\Services\Payment\Platform\PaymentIntentService;
use App\Services\Payment\Platform\PaymentTimelineService;
use App\Services\Payment\Platform\PaymentTransactionService;
use App\Services\Payment\Platform\LedgerService;
use Illuminate\Support\Facades\DB;
use InvalidArgumentException;

class WebhookProcessor
{
    public function __construct(
        private readonly WebhookRouter $router,
        private readonly PaymentIntentService $intents,
        private readonly PaymentTransactionService $transactions,
        private readonly PaymentTimelineService $timeline,
        private readonly LedgerService $ledger,
    ) {}

    public function process(string $gateway, array $payload, array $headers): WebhookResult
    {
        try {
            $adapter = $this->router->resolve($gateway);
        } catch (InvalidArgumentException $e) {
            $this->logWebhook($gateway, null, 'failed', $headers, $payload, $e->getMessage());
            return WebhookResult::failed($e->getMessage());
        }

        if (!$adapter->getSignatureVerifier()->verify(json_encode($payload), $headers)) {
            $this->logWebhook($gateway, null, 'failed', $headers, $payload, 'Invalid signature');
            return WebhookResult::failed('Invalid signature');
        }

        $event = $adapter->getPayloadParser()->parse($payload, $headers);

        if ($this->isDuplicate($gateway, $event->gatewayEventId)) {
            $this->logWebhook($gateway, $event, 'duplicate', $headers, $payload);
            return WebhookResult::duplicate();
        }

        $log = $this->logWebhook(
            gateway: $gateway,
            event: $event,
            status: 'received',
            headers: $headers,
            payload: $payload,
        );

        $log->markAsVerified();

        $log->update(['status' => 'processing']);

        GatewayNotificationReceived::dispatch($gateway, $event);

        $intent = $this->findPaymentIntent($event);

        if ($intent) {
            $log->update(['payment_intent_id' => $intent->id]);
        }

        $result = match (true) {
            in_array($event->eventType, [
                'payment_intent.succeeded', 'payment_intent.confirmed',
                'checkout.session.completed', 'payment.succeeded',
            ]) => $this->processPaymentConfirmed($intent, $event, $log),
            in_array($event->eventType, [
                'payment_intent.payment_failed', 'payment_intent.canceled',
                'checkout.session.expired', 'payment.failed',
            ]) => $this->processPaymentFailed($intent, $event, $log),
            in_array($event->eventType, [
                'charge.refunded', 'payment_intent.refunded',
            ]) => $this->processRefundReceived($intent, $event, $log),
            default => WebhookResult::unhandled("Unhandled event type: {$event->eventType}"),
        };

        $log->update([
            'status' => $result->status,
            'processed_at' => now(),
        ]);

        return $result;
    }

    private function processPaymentConfirmed(?PaymentIntent $intent, WebhookEvent $event, WebhookLog $log): WebhookResult
    {
        if (!$intent) {
            return WebhookResult::failed('Payment intent not found');
        }

        $this->timeline->record(
            intent: $intent,
            type: 'payment_confirmed',
            description: "Payment confirmed via {$event->gateway} webhook. Gateway ref: {$event->gatewayReference}",
            metadata: [
                'gateway' => $event->gateway,
                'gateway_reference' => $event->gatewayReference,
                'gateway_event_id' => $event->gatewayEventId,
            ],
        );

        PaymentConfirmed::dispatch($intent, $event);

        return WebhookResult::processed(
            message: "Payment confirmed for intent #{$intent->id}",
            intent: $intent,
        );
    }

    private function processPaymentFailed(?PaymentIntent $intent, WebhookEvent $event, WebhookLog $log): WebhookResult
    {
        if (!$intent) {
            return WebhookResult::failed('Payment intent not found');
        }

        $this->timeline->record(
            intent: $intent,
            type: 'payment_failed',
            description: "Payment failed via {$event->gateway} webhook. Gateway ref: {$event->gatewayReference}",
            metadata: [
                'gateway' => $event->gateway,
                'gateway_reference' => $event->gatewayReference,
            ],
        );

        PaymentFailed::dispatch($intent, $event);

        return WebhookResult::processed(
            message: "Payment failed for intent #{$intent->id}",
            intent: $intent,
        );
    }

    private function processRefundReceived(?PaymentIntent $intent, WebhookEvent $event, WebhookLog $log): WebhookResult
    {
        if (!$intent) {
            return WebhookResult::failed('Payment intent not found');
        }

        $this->timeline->record(
            intent: $intent,
            type: 'refund_received',
            description: "Refund received via {$event->gateway} webhook. Gateway ref: {$event->gatewayReference}",
            metadata: [
                'gateway' => $event->gateway,
                'gateway_reference' => $event->gatewayReference,
            ],
        );

        RefundReceived::dispatch($intent, $event);

        return WebhookResult::processed(
            message: "Refund received for intent #{$intent->id}",
            intent: $intent,
        );
    }

    private function findPaymentIntent(WebhookEvent $event): ?PaymentIntent
    {
        if ($event->referenceNumber) {
            $intent = $this->intents->findByReference($event->referenceNumber);
            if ($intent) {
                return $intent;
            }
        }

        if ($event->gatewayReference) {
            $transaction = $this->transactions->search(gateway: $event->gateway)->first();
            if ($transaction && $transaction->gateway_reference === $event->gatewayReference) {
                return $transaction->paymentIntent;
            }

            $intent = PaymentIntent::where('gateway', $event->gateway)
                ->where('metadata->gateway_reference', $event->gatewayReference)
                ->first();

            if ($intent) {
                return $intent;
            }
        }

        return null;
    }

    private function isDuplicate(string $gateway, string $gatewayEventId): bool
    {
        return WebhookLog::where('gateway', $gateway)
            ->where('gateway_event_id', $gatewayEventId)
            ->whereIn('status', ['processed', 'duplicate'])
            ->exists();
    }

    private function logWebhook(
        string $gateway,
        ?WebhookEvent $event,
        string $status,
        array $headers,
        array $payload,
        ?string $failureReason = null,
    ): WebhookLog {
        return WebhookLog::create([
            'gateway' => $gateway,
            'event_type' => $event?->eventType,
            'gateway_event_id' => $event?->gatewayEventId,
            'gateway_reference' => $event?->gatewayReference,
            'status' => $status,
            'request_headers' => $this->sanitizeHeaders($headers),
            'request_payload' => $payload,
            'failure_reason' => $failureReason,
        ]);
    }

    private function sanitizeHeaders(array $headers): array
    {
        $sensitive = ['authorization', 'x-api-key', 'api-key', 'x-signature'];
        $sanitized = [];

        foreach ($headers as $key => $value) {
            if (in_array(strtolower($key), $sensitive)) {
                $sanitized[$key] = '[REDACTED]';
            } else {
                $sanitized[$key] = is_array($value) ? $value[0] : $value;
            }
        }

        return $sanitized;
    }
}
