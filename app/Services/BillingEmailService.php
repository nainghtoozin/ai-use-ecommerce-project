<?php

namespace App\Services;

use App\Mail\Billing\InvoiceIssuedMail;
use App\Mail\Billing\PaymentApprovedMail;
use App\Mail\Billing\PaymentRejectedMail;
use App\Mail\Billing\PaymentReviewMail;
use App\Mail\Billing\PaymentSubmittedMail;
use App\Mail\Billing\ReceiptIssuedMail;
use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Services\Payment\Platform\PaymentTimelineService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class BillingEmailService
{
    public function __construct(
        private readonly PaymentTimelineService $timeline,
    ) {}

    public function resolveMerchantEmail(PaymentIntent $intent): ?string
    {
        $tenant = $intent->tenant;

        if (!$tenant) {
            return null;
        }

        $ownerEmail = $tenant->ownerMembership?->account?->email;

        if ($ownerEmail) {
            return $ownerEmail;
        }

        $adminUser = $tenant->users()->whereHas('roles', function ($query) {
            $query->where('name', 'admin');
        })->first();

        return $adminUser?->email;
    }

    public function sendSubmittedEmail(PaymentIntent $intent): void
    {
        $email = $this->resolveMerchantEmail($intent);

        if (!$email) {
            return;
        }

        $this->sendOnce($intent, 'email.submitted', $email, function () use ($intent) {
            return new PaymentSubmittedMail($this->baseData($intent));
        });
    }

    public function sendApprovedEmail(PaymentIntent $intent): void
    {
        $email = $this->resolveMerchantEmail($intent);

        if (!$email) {
            return;
        }

        $this->sendOnce($intent, 'email.approved', $email, function () use ($intent) {
            $invoice = Invoice::where('payment_intent_id', $intent->id)->first();

            return new PaymentApprovedMail($this->approvedData($intent, $invoice));
        });
    }

    public function sendReceiptEmail(PaymentIntent $intent, $receipt = null): void
    {
        $email = $this->resolveMerchantEmail($intent);

        if (!$email) {
            return;
        }

        $receipt ??= \App\Models\Receipt::where('payment_intent_id', $intent->id)->first();

        if (!$receipt) {
            return;
        }

        $this->sendOnce($intent, 'email.receipt', $email, function () use ($intent, $receipt) {
            return new ReceiptIssuedMail($this->receiptData($intent, $receipt));
        });
    }

    public function sendRejectedEmail(PaymentIntent $intent): void
    {
        $email = $this->resolveMerchantEmail($intent);

        if (!$email) {
            return;
        }

        $this->sendOnce($intent, 'email.rejected', $email, function () use ($intent) {
            $reason = $intent->reviews()->latest()->first()?->reason
                ?? $intent->metadata['rejection_reason']
                ?? null;
            $evidence = $intent->evidences()->latest()->first();

            return new PaymentRejectedMail(array_merge(
                $this->baseData($intent),
                [
                    'rejection_reason' => $reason,
                    'payment_date' => $evidence?->transfer_date?->toDateString()
                        ?? $intent->created_at?->toDateString(),
                ]
            ));
        });
    }

    public function sendInvoiceEmail(PaymentIntent $intent, ?Invoice $invoice = null): void
    {
        $email = $this->resolveMerchantEmail($intent);

        if (!$email) {
            return;
        }

        $invoice ??= Invoice::where('payment_intent_id', $intent->id)->first();

        if (!$invoice) {
            return;
        }

        $this->sendOnce($intent, 'email.invoice', $email, function () use ($intent, $invoice) {
            return new InvoiceIssuedMail($this->invoiceData($intent, $invoice));
        });
    }

    public function sendReviewEmail(PaymentIntent $intent): void
    {
        $recipients = $this->resolveReviewerEmails();

        if (empty($recipients)) {
            return;
        }

        $round = (string) ($intent->metadata['submission_key'] ?? 'once');
        $lock = Cache::lock("billing-email:{$intent->id}:email.review:{$round}", 10);

        if (!$lock->get()) {
            return;
        }

        try {
            $alreadySent = $this->timeline->getByType($intent, 'email.review')
                ->contains(function ($event) use ($round) {
                    return ($event->metadata['submission_key'] ?? 'once') === $round;
                });

            if ($alreadySent) {
                return;
            }

            foreach ($recipients as $email) {
                Mail::to($email)->queue(new PaymentReviewMail($this->reviewData($intent)));
            }

            $this->timeline->record(
                intent: $intent,
                type: 'email.review',
                description: 'Admin review email sent.',
                metadata: ['submission_key' => $round],
            );
        } catch (\Throwable $e) {
            Log::warning('Billing review email failed.', [
                'intent_id' => $intent->id,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $lock->release();
        }
    }

    public function resolveReviewerEmails(): array
    {
        $ids = \App\Auth\IdentityResolver::resolveSuperAdmins();

        if ($ids->isEmpty()) {
            return [];
        }

        if (config('identity.use_accounts')) {
            $emails = \App\Models\Account::whereIn('id', $ids)->pluck('email');
        } else {
            $emails = \App\Models\User::whereIn('id', $ids)->pluck('email');
        }

        return $emails->filter()->unique()->values()->all();
    }

    private function sendOnce(PaymentIntent $intent, string $type, string $email, callable $factory): void
    {
        $round = (string) ($intent->metadata['submission_key'] ?? 'once');
        $lock = Cache::lock("billing-email:{$intent->id}:{$type}:{$round}", 10);

        if (!$lock->get()) {
            return;
        }

        try {
            $alreadySent = $this->timeline->getByType($intent, $type)
                ->contains(function ($event) use ($round) {
                    return ($event->metadata['submission_key'] ?? 'once') === $round;
                });

            if ($alreadySent) {
                return;
            }

            Mail::to($email)->queue($factory());

            $this->timeline->record(
                intent: $intent,
                type: $type,
                description: "Merchant email sent: {$type}.",
                metadata: ['submission_key' => $round, 'to' => $email],
            );
        } catch (\Throwable $e) {
            Log::warning('Billing merchant email failed.', [
                'intent_id' => $intent->id,
                'type' => $type,
                'error' => $e->getMessage(),
            ]);
        } finally {
            $lock->release();
        }
    }

    private function baseData(PaymentIntent $intent): array
    {
        $tenant = $intent->tenant;

        return [
            'app_name' => (string) config('app.name'),
            'store_name' => $tenant?->name ?? 'Your store',
            'plan_name' => $intent->plan?->name ?? 'your plan',
            'billing_cycle' => $intent->billing_cycle ?? 'monthly',
            'amount' => $this->formatAmount($intent->amount, $intent->currency),
            'currency' => $intent->currency ?? 'MMK',
            'reference_number' => $intent->reference_number,
            'billing_url' => $this->billingUrl($intent),
        ];
    }

    private function approvedData(PaymentIntent $intent, ?Invoice $invoice): array
    {
        $subscription = $intent->subscription ?? $intent->tenant?->subscription;

        $period = null;
        if ($invoice?->billing_period_start && $invoice?->billing_period_end) {
            $period = $invoice->billing_period_start->toDateString() . ' → ' . $invoice->billing_period_end->toDateString();
        } elseif ($subscription && $subscription->starts_at && $subscription->expires_at) {
            $period = $subscription->starts_at->toDateString() . ' → ' . $subscription->expires_at->toDateString();
        }

        $receipt = \App\Models\Receipt::where('payment_intent_id', $intent->id)->first();

        return array_merge($this->baseData($intent), [
            'approval_status' => 'Approved',
            'invoice_number' => $invoice?->invoice_number,
            'invoice_url' => $this->invoiceUrl($intent, $invoice),
            'receipt_number' => $receipt?->receipt_number,
            'receipt_url' => $this->receiptViewUrl($intent, $receipt),
            'subscription_period' => $period,
        ]);
    }

    private function receiptData(PaymentIntent $intent, $receipt): array
    {
        $invoice = $receipt->invoice ?? Invoice::where('payment_intent_id', $intent->id)->first();

        return array_merge($this->baseData($intent), [
            'receipt_number' => $receipt->receipt_number,
            'receipt_total' => $this->formatAmount($receipt->amount, $receipt->currency),
            'paid_at' => $receipt->paid_at?->toDateString(),
            'invoice_number' => $invoice?->invoice_number,
            'subscription_period' => $invoice && $invoice->billing_period_start && $invoice->billing_period_end
                ? $invoice->billing_period_start->toDateString() . ' → ' . $invoice->billing_period_end->toDateString()
                : null,
            'receipt_url' => $this->receiptViewUrl($intent, $receipt),
            'receipt_download_url' => $this->receiptDownloadUrl($intent, $receipt),
        ]);
    }

    private function invoiceData(PaymentIntent $intent, Invoice $invoice): array
    {
        return array_merge($this->baseData($intent), [
            'invoice_number' => $invoice->invoice_number,
            'invoice_total' => $this->formatAmount($invoice->total, $invoice->currency),
            'invoice_status' => $invoice->status,
            'invoice_url' => $this->invoiceUrl($intent, $invoice),
            'invoice_download_url' => $this->invoiceDownloadUrl($intent, $invoice),
            'billing_period' => $invoice->billing_period_start && $invoice->billing_period_end
                ? $invoice->billing_period_start->toDateString() . ' → ' . $invoice->billing_period_end->toDateString()
                : null,
            'issued_at' => $invoice->issued_at?->toDateString(),
        ]);
    }

    private function reviewData(PaymentIntent $intent): array
    {
        $evidence = $intent->evidences()->latest()->first();
        $methodName = 'Manual transfer';

        if ($evidence?->metadata['payment_method_id'] ?? null) {
            $methodName = \App\Models\BillingPaymentMethod::find(
                $evidence->metadata['payment_method_id']
            )?->display_name ?? $methodName;
        }

        return array_merge($this->baseData($intent), [
            'merchant_name' => $intent->tenant?->name ?? 'A merchant',
            'merchant_email' => $this->resolveMerchantEmail($intent),
            'payment_method' => $methodName,
            'transfer_date' => $evidence?->transfer_date?->toDateString(),
            'transfer_time' => $evidence?->metadata['transfer_time'] ?? null,
            'submitted_at' => $intent->created_at?->toDateTimeString(),
            'evidence_count' => $intent->evidences()->count(),
            'review_url' => route('superadmin.billing.index', ['status' => 'waiting_review']),
        ]);
    }

    private function formatAmount($amount, ?string $currency): string
    {
        return number_format((float) $amount, 2) . ' ' . ($currency ?? 'MMK');
    }

    private function billingUrl(PaymentIntent $intent): string
    {
        $slug = $intent->tenant?->slug;

        if ($slug) {
            return route('storefront.admin.billing', ['store_slug' => $slug]);
        }

        return route('admin.billing');
    }

    private function invoiceUrl(PaymentIntent $intent, ?Invoice $invoice): ?string
    {
        $slug = $intent->tenant?->slug;

        if (!$slug || !$invoice) {
            return null;
        }

        return route('storefront.admin.billing.invoices.show', [
            'store_slug' => $slug,
            'invoice' => $invoice->id,
        ]);
    }

    private function invoiceDownloadUrl(PaymentIntent $intent, ?Invoice $invoice): ?string
    {
        $slug = $intent->tenant?->slug;

        if (!$slug || !$invoice) {
            return null;
        }

        return route('storefront.admin.billing.invoices.download', [
            'store_slug' => $slug,
            'invoice' => $invoice->id,
        ]);
    }

    private function receiptViewUrl(PaymentIntent $intent, $receipt): ?string
    {
        $slug = $intent->tenant?->slug;

        if (!$slug || !$receipt) {
            return null;
        }

        return route('storefront.admin.billing.documents.receipt', [
            'store_slug' => $slug,
            'receipt' => $receipt->id,
        ]);
    }

    private function receiptDownloadUrl(PaymentIntent $intent, $receipt): ?string
    {
        $slug = $intent->tenant?->slug;

        if (!$slug || !$receipt) {
            return null;
        }

        return route('storefront.admin.billing.documents.receipt.pdf', [
            'store_slug' => $slug,
            'receipt' => $receipt->id,
        ]);
    }
}
