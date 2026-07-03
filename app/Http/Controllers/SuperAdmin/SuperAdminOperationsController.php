<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Enums\Payment\GatewayType;
use App\Models\WebhookLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class SuperAdminOperationsController extends Controller
{
    public function index(Request $request)
    {
        $query = WebhookLog::with(['paymentIntent']);

        if ($request->filled('gateway')) {
            $query->where('gateway', $request->gateway);
        }

        if ($request->filled('status')) {
            $query->where('status', $request->status);
        }

        if ($request->filled('event_type')) {
            $query->where('event_type', 'like', "%{$request->event_type}%");
        }

        if ($request->filled('date_from')) {
            $query->whereDate('created_at', '>=', $request->date_from);
        }

        if ($request->filled('date_to')) {
            $query->whereDate('created_at', '<=', $request->date_to);
        }

        if ($request->filled('search')) {
            $search = $request->search;
            $query->where(function ($q) use ($search) {
                $q->where('gateway_event_id', 'like', "%{$search}%")
                  ->orWhere('gateway_reference', 'like', "%{$search}%")
                  ->orWhere('event_type', 'like', "%{$search}%")
                  ->orWhere('gateway', 'like', "%{$search}%");
            });
        }

        $query->orderBy('created_at', 'desc');

        $perPage = min((int) $request->get('per_page', 20), 100);
        $webhooks = $query->paginate($perPage);

        $webhooks->getCollection()->transform(function ($log) {
            $headers = $log->request_headers;
            $payload = $log->request_payload;
            return [
                'id' => $log->id,
                'gateway' => $log->gateway,
                'event_type' => $log->event_type,
                'gateway_event_id' => $log->gateway_event_id,
                'gateway_reference' => $log->gateway_reference,
                'status' => $log->status,
                'failure_reason' => $log->failure_reason,
                'verified_at' => $log->verified_at?->toDateTimeString(),
                'processed_at' => $log->processed_at?->toDateTimeString(),
                'created_at' => $log->created_at->toDateTimeString(),
                'request_headers_raw' => $headers ? json_encode($headers, JSON_PRETTY_PRINT) : null,
                'request_payload_raw' => $payload ? json_encode($payload, JSON_PRETTY_PRINT) : null,
                'payload_size' => $payload ? strlen(json_encode($payload)) : 0,
                'intent_id' => $log->payment_intent_id,
            ];
        });

        return Inertia::render('SuperAdmin/Operations/Index', [
            'webhooks' => $webhooks,
            'filters' => $request->only(['gateway', 'status', 'event_type', 'date_from', 'date_to', 'search', 'per_page']),
            'stats' => $this->getStats(),
            'gateways' => collect(GatewayType::cases())->map(fn($g) => [
                'value' => $g->value,
                'label' => $g->label(),
                'is_online' => $g->isOnline(),
                'is_offline' => $g->isOffline(),
                'integrated' => $g === GatewayType::MANUAL,
            ]),
        ]);
    }

    private function getStats(): array
    {
        $now = now();

        $total = WebhookLog::count();
        $success = WebhookLog::where('status', 'processed')->count();
        $failed = WebhookLog::where('status', 'failed')->count();
        $pending = WebhookLog::whereIn('status', ['received', 'processing'])->count();
        $lastSuccess = WebhookLog::where('status', 'processed')->latest()->first();
        $avgProcessing = WebhookLog::whereNotNull('processed_at')
            ->whereNotNull('verified_at')
            ->selectRaw('AVG(TIMESTAMPDIFF(SECOND, created_at, processed_at)) as avg_seconds')
            ->value('avg_seconds');

        return [
            'total_webhooks' => $total,
            'success_rate' => $total > 0 ? round(($success / $total) * 100, 1) : 0,
            'failure_rate' => $total > 0 ? round(($failed / $total) * 100, 1) : 0,
            'pending_queue' => $pending,
            'failed_count' => $failed,
            'success_count' => $success,
            'avg_processing_seconds' => round((float) ($avgProcessing ?? 0), 1),
            'last_successful_at' => $lastSuccess?->created_at?->toDateTimeString(),
            'processed_today' => WebhookLog::where('status', 'processed')
                ->whereDate('created_at', $now->toDateString())->count(),
            'failed_today' => WebhookLog::where('status', 'failed')
                ->whereDate('created_at', $now->toDateString())->count(),
        ];
    }
}
