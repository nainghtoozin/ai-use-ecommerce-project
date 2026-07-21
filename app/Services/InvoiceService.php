<?php

namespace App\Services;

use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Models\Subscription;
use App\Models\Tenant;
use Illuminate\Support\Facades\DB;

class InvoiceService
{
    public function generateFromPaymentIntent(PaymentIntent $intent): Invoice
    {
        $subscription = $intent->subscription ?? $intent->tenant?->subscription;
        $tenant = $intent->tenant;

        if (!$subscription) {
            throw new \RuntimeException(sprintf(
                'Cannot generate invoice: no subscription found for PaymentIntent #%d.', $intent->id
            ));
        }

        return DB::transaction(function () use ($intent, $subscription, $tenant) {
            $amount = (float) $intent->amount;
            $tax = round($amount * 0.05, 2);
            $total = $amount + $tax;

            $invoice = Invoice::create([
                'tenant_id' => $tenant->id,
                'invoice_number' => Invoice::generateNumber(),
                'subscription_id' => $subscription->id,
                'plan_id' => $intent->plan_id,
                'billing_interval' => $intent->billing_cycle ?? 'monthly',
                'billing_period_start' => $subscription->starts_at?->toDateString() ?? now()->startOfMonth()->toDateString(),
                'billing_period_end' => $subscription->expires_at?->toDateString() ?? now()->endOfMonth()->toDateString(),
                'amount' => $amount,
                'subtotal' => $amount,
                'tax' => $tax,
                'total' => $total,
                'currency' => $intent->currency ?? 'MMK',
                'status' => in_array($intent->status, ['paid', 'completed', 'approved'])
                    ? Invoice::STATUS_PAID
                    : Invoice::STATUS_UNPAID,
                'payment_intent_id' => $intent->id,
                'paid_at' => in_array($intent->status, ['paid', 'completed', 'approved']) ? now() : null,
                'issued_at' => now(),
                'line_items' => $this->buildLineItems($intent),
            ]);

            return $invoice;
        });
    }

    public function generateForSubscription(Subscription $subscription, ?string $billingInterval = null): Invoice
    {
        $tenant = $subscription->tenant;
        $plan = $subscription->plan;
        $interval = $billingInterval ?? $subscription->billing_interval ?? 'monthly';
        $amount = $plan?->getPriceForInterval($interval) ?? 0;
        $tax = round($amount * 0.05, 2);
        $total = $amount + $tax;

        return DB::transaction(function () use ($subscription, $tenant, $plan, $interval, $amount, $tax, $total) {
            return Invoice::create([
                'tenant_id' => $tenant->id,
                'invoice_number' => Invoice::generateNumber(),
                'subscription_id' => $subscription->id,
                'plan_id' => $plan?->id,
                'billing_interval' => $interval,
                'billing_period_start' => $subscription->starts_at?->toDateString() ?? now()->startOfMonth()->toDateString(),
                'billing_period_end' => $subscription->expires_at?->toDateString() ?? now()->endOfMonth()->toDateString(),
                'amount' => $amount,
                'subtotal' => $amount,
                'tax' => $tax,
                'total' => $total,
                'currency' => 'MMK',
                'status' => Invoice::STATUS_UNPAID,
                'issued_at' => now(),
                'line_items' => [
                    [
                        'description' => $plan?->name . ' (' . ucfirst($interval) . ')',
                        'quantity' => 1,
                        'unit_price' => $amount,
                        'amount' => $amount,
                    ],
                    [
                        'description' => 'Tax (5%)',
                        'quantity' => 1,
                        'unit_price' => $tax,
                        'amount' => $tax,
                    ],
                ],
            ]);
        });
    }

    public function buildLineItems(PaymentIntent $intent): array
    {
        $amount = (float) $intent->amount;
        $tax = round($amount * 0.05, 2);
        $planName = $intent->plan?->name ?? 'Subscription';
        $cycle = ucfirst($intent->billing_cycle ?? 'Monthly');

        return [
            [
                'description' => "{$planName} ({$cycle})",
                'quantity' => 1,
                'unit_price' => $amount,
                'amount' => $amount,
            ],
            [
                'description' => 'Tax (5%)',
                'quantity' => 1,
                'unit_price' => $tax,
                'amount' => $tax,
            ],
        ];
    }

    public function getTenantStats(Tenant $tenant): array
    {
        $query = Invoice::forTenant($tenant->id);

        return [
            'total' => (clone $query)->count(),
            'paid' => (clone $query)->where('status', Invoice::STATUS_PAID)->count(),
            'unpaid' => (clone $query)->where('status', Invoice::STATUS_UNPAID)->count(),
            'cancelled' => (clone $query)->where('status', Invoice::STATUS_CANCELLED)->count(),
            'total_amount' => (float) (clone $query)->sum('total'),
            'paid_amount' => (float) (clone $query)->where('status', Invoice::STATUS_PAID)->sum('total'),
        ];
    }

    public static function url(): string
    {
        return '/admin/billing/invoices';
    }
}
