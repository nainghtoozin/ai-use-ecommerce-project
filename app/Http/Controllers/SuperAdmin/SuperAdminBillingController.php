<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PaymentIntent;
use App\Models\Plan;
use App\Services\ImageService;
use App\Services\Payment\Platform\PaymentReviewService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuperAdminBillingController extends Controller
{
    public function __construct(
        private readonly PaymentReviewService $paymentReview,
    ) {}

    public function index(Request $request)
    {
        $query = PaymentIntent::withoutTenantScope()
            ->with(['tenant', 'plan', 'evidences', 'timelineEvents', 'comments', 'reviews']);

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('plan_id')) {
            $query->where('plan_id', $request->plan_id);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('reference_number', 'like', "%{$search}%")
                  ->orWhereHas('tenant', fn($t) => $t->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('plan', fn($p) => $p->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('evidences', fn($e) => $e->where('transaction_reference', 'like', "%{$search}%"))
                  ->orWhereHas('evidences', fn($e) => $e->where('sender_name', 'like', "%{$search}%"))
                  ->orWhereHas('evidences', fn($e) => $e->where('sender_account', 'like', "%{$search}%"));
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min((int) $request->get('per_page', 20), 100);
        $intents = $query->paginate($perPage);

        $intents->getCollection()->transform(function ($intent) {
            return [
                'id' => $intent->id,
                'reference_number' => $intent->reference_number,
                'status' => $intent->status,
                'amount' => (float) $intent->amount,
                'currency' => $intent->currency,
                'billing_cycle' => $intent->billing_cycle,
                'gateway' => $intent->gateway,
                'created_at' => $intent->created_at->toDateTimeString(),
                'expires_at' => $intent->expires_at?->toDateTimeString(),
                'tenant' => $intent->tenant ? [
                    'id' => $intent->tenant->id,
                    'name' => $intent->tenant->name,
                    'slug' => $intent->tenant->slug,
                    'email' => $intent->tenant->email,
                ] : null,
                'plan' => $intent->plan ? [
                    'id' => $intent->plan->id,
                    'name' => $intent->plan->name,
                    'slug' => $intent->plan->slug,
                ] : null,
                'evidences' => $intent->evidences->map(fn($ev) => [
                    'id' => $ev->id,
                    'type' => $ev->type,
                    'file_path' => ImageService::url($ev->file_path),
                    'note' => $ev->note,
                    'sender_name' => $ev->sender_name,
                    'sender_account' => $ev->sender_account,
                    'transaction_reference' => $ev->transaction_reference,
                    'transferred_amount' => $ev->transferred_amount ? (float) $ev->transferred_amount : null,
                    'transfer_date' => $ev->transfer_date?->toDateString(),
                ])->values()->all(),
                'timeline' => $intent->timelineEvents->sortBy('occurred_at')->values()->map(fn($tl) => [
                    'id' => $tl->id,
                    'type' => $tl->type,
                    'description' => $tl->description,
                    'occurred_at' => $tl->occurred_at?->toDateTimeString(),
                ])->all(),
                'comments' => $intent->comments->sortByDesc('created_at')->values()->map(fn($c) => [
                    'id' => $c->id,
                    'author_name' => $c->author_name,
                    'author_type' => $c->author_type,
                    'body' => $c->body,
                    'created_at' => $c->created_at->toDateTimeString(),
                ])->all(),
                'reviews' => $intent->reviews->map(fn($r) => [
                    'id' => $r->id,
                    'action' => $r->action,
                    'reviewer_name' => $r->reviewer_name,
                    'reason' => $r->reason,
                    'created_at' => $r->created_at?->toDateTimeString(),
                ])->all(),
                'subscription_event' => $intent->timelineEvents
                    ->sortBy('occurred_at')
                    ->first(fn($tl) => in_array($tl->type, ['subscription_activated', 'subscription_renewed']))
                    ?->type,
            ];
        });

        return Inertia::render('SuperAdmin/Billing/Index', [
            'intents' => $intents,
            'filters' => $request->only(['status', 'date_from', 'date_to', 'plan_id', 'search', 'per_page']),
            'plans' => Plan::active()->ordered()->get(['id', 'name', 'slug']),
            'stats' => $this->getStats(),
        ]);
    }

    public function approve(Request $request, int $id)
    {
        $intent = PaymentIntent::withoutTenantScope()->findOrFail($id);
        $user = $request->user();

        try {
            $this->paymentReview->approve(
                intent: $intent,
                reviewerId: $user->id,
                reviewerName: $user->name,
            );

            return redirect()->route('superadmin.billing.index')
                ->with('success', "Payment {$intent->reference_number} approved successfully.");
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    public function reject(Request $request, int $id)
    {
        $validated = $request->validate([
            'reason' => ['required', 'string', 'max:2000'],
        ]);

        $intent = PaymentIntent::withoutTenantScope()->findOrFail($id);
        $user = $request->user();

        try {
            $this->paymentReview->reject(
                intent: $intent,
                reason: $validated['reason'],
                reviewerId: $user->id,
                reviewerName: $user->name,
            );

            return redirect()->route('superadmin.billing.index')
                ->with('success', "Payment {$intent->reference_number} rejected.");
        } catch (\Exception $e) {
            return redirect()->back()
                ->with('error', $e->getMessage());
        }
    }

    private function getStats(): array
    {
        $today = now()->startOfDay();
        $tomorrow = now()->addDay()->startOfDay();

        return [
            'pending_review' => PaymentIntent::withoutTenantScope()
                ->where('status', 'waiting_review')->count(),
            'approved_today' => PaymentIntent::withoutTenantScope()
                ->where('status', 'approved')
                ->whereBetween('created_at', [$today, $tomorrow])->count(),
            'rejected_today' => PaymentIntent::withoutTenantScope()
                ->where('status', 'rejected')
                ->whereBetween('created_at', [$today, $tomorrow])->count(),
            'completed_total' => PaymentIntent::withoutTenantScope()
                ->whereIn('status', ['completed', 'approved', 'paid'])->count(),
        ];
    }
}
