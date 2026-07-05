<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\PaymentIntent;
use App\Models\PaymentTransaction;
use App\Models\Plan;
use App\Services\ImageService;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuperAdminFinancialController extends Controller
{
    public function index(Request $request)
    {
        $query = PaymentTransaction::with([
            'tenant', 'plan', 'paymentIntent' => fn($q) => $q->with([
                'evidences', 'timelineEvents', 'comments', 'reviews',
            ]),
            'ledgerEntries',
        ]);

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

        if ($request->filled('amount_min')) {
            $query->where('amount', '>=', (float) $request->amount_min);
        }

        if ($request->filled('amount_max')) {
            $query->where('amount', '<=', (float) $request->amount_max);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('transaction_number', 'like', "%{$search}%")
                  ->orWhereHas('paymentIntent', fn($pi) => $pi->where('reference_number', 'like', "%{$search}%"))
                  ->orWhereHas('tenant', fn($t) => $t->where('name', 'like', "%{$search}%"))
                  ->orWhereHas('plan', fn($p) => $p->where('name', 'like', "%{$search}%"));
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min((int) $request->get('per_page', 20), 100);
        $transactions = $query->paginate($perPage);

        $transactions->getCollection()->transform(function ($txn) {
            $intent = $txn->paymentIntent;
            return [
                'id' => $txn->id,
                'transaction_number' => $txn->transaction_number,
                'amount' => (float) $txn->amount,
                'currency' => $txn->currency,
                'gateway' => $txn->gateway,
                'status' => $txn->status,
                'gateway_reference' => $txn->gateway_reference,
                'created_at' => $txn->created_at->toDateTimeString(),
                'tenant' => $txn->tenant ? [
                    'id' => $txn->tenant->id,
                    'name' => $txn->tenant->name,
                    'slug' => $txn->tenant->slug,
                    'email' => $txn->tenant->email,
                ] : null,
                'plan' => $txn->plan ? [
                    'id' => $txn->plan->id,
                    'name' => $txn->plan->name,
                ] : null,
                'intent' => $intent ? [
                    'id' => $intent->id,
                    'reference_number' => $intent->reference_number,
                    'billing_cycle' => $intent->billing_cycle,
                    'evidences' => $intent->evidences->map(fn($ev) => [
                        'id' => $ev->id,
                        'type' => $ev->type,
                        'file_path' => ImageService::url($ev->file_path),
                        'note' => $ev->note,
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
                ] : null,
                'ledger' => $txn->ledgerEntries->map(fn($le) => [
                    'id' => $le->id,
                    'type' => $le->type,
                    'amount' => (float) $le->amount,
                    'currency' => $le->currency,
                    'description' => $le->description,
                    'recorded_at' => $le->recorded_at?->toDateTimeString(),
                ])->all(),
            ];
        });

        return Inertia::render('SuperAdmin/Financial/Index', [
            'transactions' => $transactions,
            'filters' => $request->only(['status', 'date_from', 'date_to', 'plan_id', 'amount_min', 'amount_max', 'search', 'per_page']),
            'plans' => Plan::active()->ordered()->get(['id', 'name', 'slug']),
            'stats' => $this->getStats(),
        ]);
    }

    private function getStats(): array
    {
        $now = now();
        $todayStart = $now->copy()->startOfDay();
        $monthStart = $now->copy()->startOfMonth();

        $successStatuses = ['completed', 'approved', 'paid'];

        $totalRevenue = PaymentTransaction::whereIn('status', $successStatuses)->sum('amount');
        $monthlyRevenue = PaymentTransaction::whereIn('status', $successStatuses)
            ->where('created_at', '>=', $monthStart)->sum('amount');
        $todayRevenue = PaymentTransaction::whereIn('status', $successStatuses)
            ->where('created_at', '>=', $todayStart)->sum('amount');
        $pendingRevenue = PaymentTransaction::whereIn('status', ['pending', 'waiting_payment', 'waiting_review'])->sum('amount');
        $completedCount = PaymentTransaction::whereIn('status', $successStatuses)->count();
        $pendingReviewCount = PaymentTransaction::where('status', 'waiting_review')->count();
        $rejectedCount = PaymentTransaction::where('status', 'rejected')->count();
        $avgTransaction = $completedCount > 0
            ? PaymentTransaction::whereIn('status', $successStatuses)->average('amount')
            : 0;

        return [
            'total_revenue' => (float) $totalRevenue,
            'monthly_revenue' => (float) $monthlyRevenue,
            'today_revenue' => (float) $todayRevenue,
            'pending_revenue' => (float) $pendingRevenue,
            'completed_transactions' => $completedCount,
            'pending_review' => $pendingReviewCount,
            'rejected_payments' => $rejectedCount,
            'avg_transaction' => (float) round($avgTransaction, 2),
        ];
    }
}
