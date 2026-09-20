<?php

namespace App\Services;

use App\Mail\Billing\InvoiceIssuedMail;
use App\Mail\Billing\PaymentApprovedMail;
use App\Mail\Billing\PaymentRejectedMail;
use App\Mail\Billing\PaymentReviewMail;
use App\Mail\Billing\PaymentSubmittedMail;
use App\Mail\Billing\ReceiptIssuedMail;
use App\Mail\Billing\SubscriptionRenewalReminderMail;
use App\Models\Invoice;
use App\Models\PaymentIntent;
use App\Models\PlatformSetting;
use App\Models\Subscription;
use App\Models\SubscriptionAuditLog;
use App\Models\Tenant;
use App\Services\Payment\Platform\PaymentTimelineService;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\URL;

class BillingEmailService
{
    public function __construct(
        private readonly PaymentTimelineService $timeline,
    ) {}

    public function resolveMerchantEmail(PaymentIntent $intent): ?string
    {
        return $this->resolveTenantMerchantEmail($intent->tenant);
    }

    private function resolveTenantMerchantEmail(?Tenant $tenant): ?string
    {
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
        try {
            $recipients = $this->resolveReviewerEmails();

            if (empty($recipients)) {
                Log::warning('Billing review email skipped: no SuperAdmin recipients resolved.', [
                    'intent_id' => $intent->id,
                ]);

                return;
            }

            $round = (string) ($intent->metadata['submission_key'] ?? 'once');

            foreach ($recipients as $email) {
                $this->sendOnce($intent, 'email.review', $email, function () use ($intent) {
                    return new PaymentReviewMail($this->reviewData($intent));
                }, $round);
            }
        } catch (\Throwable $e) {
            Log::warning('Billing review email failed.', [
                'intent_id' => $intent->id,
                'error' => $e->getMessage(),
            ]);
        }
    }

    public const DOCUMENT_LINK_DAYS = 30;

    public const RENEWAL_REMINDER_EVENT = 'renewal_reminder_email_sent';

    public function renewalReminderDays(): int
    {
        try {
            $days = (int) (PlatformSetting::current()->billing_renewal_reminder_days ?? 7);
        } catch (\Throwable $e) {
            return 7;
        }

        return max(1, min(30, $days));
    }

    public function sendRenewalReminder(Subscription $subscription): bool
    {
        try {
            if ($subscription->status !== 'active' || !$subscription->expires_at) {
                return false;
            }

            $daysRemaining = (int) now()->startOfDay()
                ->diffInDays($subscription->expires_at->copy()->startOfDay(), false);

            if ($daysRemaining < 0 || $daysRemaining > $this->renewalReminderDays()) {
                return false;
            }

            $email = $this->resolveTenantMerchantEmail($subscription->tenant);

            if (!$email) {
                return false;
            }

            $anchor = $subscription->expires_at->toDateTimeString();

            $alreadySent = SubscriptionAuditLog::where('subscription_id', $subscription->id)
                ->where('event', self::RENEWAL_REMINDER_EVENT)
                ->get()
                ->contains(fn ($log) => ($log->metadata['expires_at'] ?? null) === $anchor);

            if ($alreadySent) {
                return false;
            }

            Mail::to($email)->queue(
                new SubscriptionRenewalReminderMail($this->renewalReminderData($subscription, $daysRemaining))
            );

            \App\Services\SubscriptionAuditService::log($subscription, self::RENEWAL_REMINDER_EVENT, [
                'reason' => "Renewal reminder email sent ({$daysRemaining} day(s) before expiry).",
                'metadata' => ['expires_at' => $anchor, 'to' => $email, 'days_remaining' => $daysRemaining],
            ]);

            return true;
        } catch (\Throwable $e) {
            Log::warning('Subscription renewal reminder email failed.', [
                'subscription_id' => $subscription->id,
                'error' => $e->getMessage(),
            ]);

            return false;
        }
    }

    public function resolveReviewerEmails(): array
    {
        $emails = collect();

        try {
            $emails = $emails->merge(
                \App\Models\Account::whereHas('roles', function ($query) {
                    $query->withoutTenantScope()->where('name', 'superadmin');
                })->pluck('email')
            );
        } catch (\Throwable $e) {
            Log::warning('Billing reviewer account lookup failed.', ['error' => $e->getMessage()]);
        }

        try {
            $emails = $emails->merge(
                \App\Models\User::whereHas('roles', function ($query) {
                    $query->withoutTenantScope()->where('name', 'superadmin');
                })->pluck('email')
            );
        } catch (\Throwable $e) {
            Log::warning('Billing reviewer user lookup failed.', ['error' => $e->getMessage()]);
        }

        return $emails->filter()->unique()->values()->all();
    }

    private function sendOnce(PaymentIntent $intent, string $type, string $email, callable $factory, ?string $round = null): void
    {
        $round ??= (string) ($intent->metadata['submission_key'] ?? 'once');

        try {
            $lock = Cache::lock("billing-email:{$intent->id}:{$type}:{$round}", 10);
        } catch (\Throwable $e) {
            $lock = null;
        }

        if ($lock && !$lock->get()) {
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
            if (isset($lock)) {
                try {
                    $lock->release();
                } catch (\Throwable $e) {
                    // Best effort only; the lock expires on its own.
                }
            }
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
            'review_url' => $this->reviewUrl($intent),
        ]);
    }

    private function renewalReminderData(Subscription $subscription, int $daysRemaining): array
    {
        $tenant = $subscription->tenant;
        $plan = $subscription->plan;
        $cycle = $subscription->billing_interval ?? 'monthly';
        $amount = $cycle === 'yearly' ? $plan?->yearly_price : $plan?->monthly_price;

        return [
            'app_name' => (string) config('app.name'),
            'store_name' => $tenant?->name ?? 'Your store',
            'plan_name' => $plan?->name ?? 'your plan',
            'billing_cycle' => $cycle,
            'expires_at' => $subscription->expires_at?->toDateString(),
            'days_remaining' => $daysRemaining,
            'renewal_amount' => $this->formatAmount($amount, $plan?->currency ?? 'MMK'),
            'subscription_reference' => 'SUB-' . str_pad((string) $subscription->id, 6, '0', STR_PAD_LEFT),
            'billing_url' => $this->subscriptionBillingUrl($subscription),
        ];
    }

    private function subscriptionBillingUrl(Subscription $subscription): string
    {
        $slug = $subscription->tenant?->slug;

        if ($slug) {
            return route('storefront.admin.billing', ['store_slug' => $slug]);
        }

        return route('admin.billing');
    }

    private function formatAmount($amount, ?string $currency): string
    {
        return number_format((float) $amount, 2) . ' ' . ($currency ?? 'MMK');
    }

    /**
     * Link the SuperAdmin to the Billing Console focused on this payment.
     *
     * The console renders live database state, so the URL carries the
     * payment reference as a search term and never a status filter.
     * A stale status (e.g. waiting_review) would hide the payment after
     * it is approved or rejected.
     */
    private function reviewUrl(PaymentIntent $intent): string
    {
        if ($intent->reference_number) {
            return route('superadmin.billing.index', ['search' => $intent->reference_number]);
        }

        return route('superadmin.billing.index');
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
        if (!$invoice) {
            return null;
        }

        return URL::temporarySignedRoute(
            'billing.documents.invoice',
            now()->addDays(self::DOCUMENT_LINK_DAYS),
            ['invoice' => $invoice->id]
        );
    }

    private function invoiceDownloadUrl(PaymentIntent $intent, ?Invoice $invoice): ?string
    {
        if (!$invoice) {
            return null;
        }

        return URL::temporarySignedRoute(
            'billing.documents.invoice.pdf',
            now()->addDays(self::DOCUMENT_LINK_DAYS),
            ['invoice' => $invoice->id]
        );
    }

    private function receiptViewUrl(PaymentIntent $intent, $receipt): ?string
    {
        if (!$receipt) {
            return null;
        }

        return URL::temporarySignedRoute(
            'billing.documents.receipt',
            now()->addDays(self::DOCUMENT_LINK_DAYS),
            ['receipt' => $receipt->id]
        );
    }

    private function receiptDownloadUrl(PaymentIntent $intent, $receipt): ?string
    {
        if (!$receipt) {
            return null;
        }

        return URL::temporarySignedRoute(
            'billing.documents.receipt.pdf',
            now()->addDays(self::DOCUMENT_LINK_DAYS),
            ['receipt' => $receipt->id]
        );
    }
}
