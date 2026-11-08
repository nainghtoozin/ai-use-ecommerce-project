<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\MerchantEmailAttachment;
use App\Models\MerchantEmailCampaign;
use App\Models\MerchantEmailRecipient;
use App\Models\Tenant;
use App\Services\ActivityLogger;
use App\Services\MerchantEmailCommunicationService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Validator;
use Inertia\Inertia;

class MerchantEmailCommunicationController extends Controller
{
    public function __construct(private MerchantEmailCommunicationService $service) {}

    public function legacyRedirect($id, Request $request)
    {
        $campaign = MerchantEmailCampaign::findOrFail($id);
        $returnTo = $this->safeReturnUrl($request->get('return_to'));
        $url = route('superadmin.email-communications.show', ['campaign' => $campaign->public_id]);
        if ($returnTo) {
            $url .= '?return_to=' . urlencode($returnTo);
        }
        return redirect($url);
    }

    public function index(Request $request)
    {
        $search = $request->get('search');
        $emailType = $request->get('email_type');
        $status = $request->get('status');
        $archived = $request->get('archived');
        $from = $request->get('from');
        $to = $request->get('to');
        $perPage = (int) $request->get('per_page', 10);
        if (!in_array($perPage, [10, 25, 50], true)) {
            $perPage = 10;
        }

        $campaigns = MerchantEmailCampaign::query()
            ->withCount(['recipients as sent_count' => fn($q) => $q->where('status', 'sent')])
            ->withCount(['recipients as pending_count' => fn($q) => $q->whereIn('status', ['pending', 'processing'])])
            ->withCount(['recipients as failed_count' => fn($q) => $q->where('status', 'failed')])
            ->when($search, fn($q, $s) => $q->where('subject', 'like', "%{$s}%"))
            ->when($emailType, fn($q, $t) => $q->where('email_type', $t))
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->when($archived === '1', fn($q) => $q->whereNotNull('archived_at'))
            ->when($archived !== '1', fn($q) => $q->whereNull('archived_at'))
            ->when($from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($to, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->orderByDesc('created_at')
            ->orderByDesc('id')
            ->paginate($perPage)
            ->withQueryString();

        return Inertia::render('SuperAdmin/EmailCommunications/Index', [
            'stats' => $this->service->stats(),
            'email_types' => $this->service->emailTypes(),
            'campaigns' => $campaigns,
            'recent_campaigns' => $campaigns->items(),
            'filters' => [
                'search' => $search,
                'email_type' => $emailType,
                'status' => $status,
                'archived' => $archived,
                'from' => $from,
                'to' => $to,
                'per_page' => $perPage,
            ],
        ]);
    }

    public function compose(Request $request)
    {
        $search = $request->get('search');
        $status = $request->get('status');
        $page = (int) $request->get('page', 1);

        $tenants = Tenant::query()
            ->when($search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                    ->orWhere('slug', 'like', "%{$s}%")
                    ->orWhere('email', 'like', "%{$s}%");
            }))
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('name')
            ->paginate(15)
            ->withQueryString();

        $tenants->getCollection()->transform(function ($tenant) {
            $resolved = $this->service->resolveOwner($tenant);
            return [
                'id' => $tenant->id,
                'name' => $tenant->name,
                'slug' => $tenant->slug,
                'status' => $tenant->status,
                'owner_name' => $resolved['recipient_name'],
                'owner_email' => $resolved['recipient_email'],
                'valid' => $resolved['valid'],
            ];
        });

        return Inertia::render('SuperAdmin/EmailCommunications/Compose', [
            'tenants' => $tenants,
            'filters' => ['search' => $search, 'status' => $status],
            'email_types' => $this->service->emailTypes(),
            'tenant_statuses' => $this->service->tenantStatuses(),
            'default_statuses' => MerchantEmailCommunicationService::DEFAULT_STATUSES,
        ]);
    }

    public function preview(Request $request)
    {
        $validated = $this->validatePayload($request, true);
        $preview = $this->service->preview($validated);

        return response()->json($preview);
    }

    public function store(Request $request)
    {
        $validated = $this->validatePayload($request, false);

        $request->validate([
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:5120|mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg',
        ]);

        try {
            $campaign = $this->service->createCampaign($validated, $request->user());
        } catch (\RuntimeException $e) {
            return back()->withErrors(['recipients' => $e->getMessage()]);
        }

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('merchant-email-attachments/' . $campaign->id, 'local');
                \App\Models\MerchantEmailAttachment::create([
                    'campaign_id' => $campaign->id,
                    'original_filename' => $file->getClientOriginalName(),
                    'storage_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        ActivityLogger::log(
            "Sent merchant email campaign: {$campaign->subject}",
            'merchant_email_sent',
            $campaign,
            ['campaign_id' => $campaign->id, 'recipient_count' => $campaign->recipient_count],
            'merchant_email',
            null,
            null
        );

        return redirect()->route('superadmin.email-communications.show', $campaign)
            ->with('success', 'Your email is now in the delivery queue. You can check delivery progress in Email Details.');
    }

    public function history(Request $request)
    {
        $search = $request->get('search');
        $emailType = $request->get('email_type');
        $status = $request->get('status');
        $tenantId = $request->get('tenant_id');
        $from = $request->get('from');
        $to = $request->get('to');
        $archived = $request->get('archived');

        $campaigns = MerchantEmailCampaign::query()
            ->withCount(['recipients as sent_count' => fn($q) => $q->where('status', 'sent')])
            ->withCount(['recipients as pending_count' => fn($q) => $q->whereIn('status', ['pending', 'processing'])])
            ->withCount(['recipients as failed_count' => fn($q) => $q->where('status', 'failed')])
            ->when($search, fn($q, $s) => $q->where('subject', 'like', "%{$s}%"))
            ->when($emailType, fn($q, $t) => $q->where('email_type', $t))
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->when($tenantId, fn($q, $id) => $q->whereHas('recipients', fn($r) => $r->where('tenant_id', $id)))
            ->when($from, fn($q, $d) => $q->whereDate('created_at', '>=', $d))
            ->when($to, fn($q, $d) => $q->whereDate('created_at', '<=', $d))
            ->when($archived === '1', fn($q) => $q->whereNotNull('archived_at'))
            ->when($archived !== '1', fn($q) => $q->whereNull('archived_at'))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return Inertia::render('SuperAdmin/EmailCommunications/History', [
            'campaigns' => $campaigns,
            'filters' => [
                'search' => $search,
                'email_type' => $emailType,
                'status' => $status,
                'tenant_id' => $tenantId,
                'from' => $from,
                'to' => $to,
            ],
            'email_types' => $this->service->emailTypes(),
        ]);
    }

    public function show(MerchantEmailCampaign $campaign, Request $request)
    {
        $status = $request->get('status');
        $returnUrl = $this->safeReturnUrl($request->get('return_to')) ?: '/superadmin/email-communications/history';
        $campaign->load('attachments');

        $recipients = $campaign->recipients()
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->orderByDesc('id')
            ->paginate(25)
            ->withQueryString();

        $counts = [
            'total' => $campaign->recipients()->count(),
            'sent' => $campaign->recipients()->where('status', 'sent')->count(),
            'pending' => $campaign->recipients()->whereIn('status', ['pending', 'processing'])->count(),
            'failed' => $campaign->recipients()->where('status', 'failed')->count(),
        ];

        return Inertia::render('SuperAdmin/EmailCommunications/Show', [
            'campaign' => $campaign,
            'recipients' => $recipients,
            'counts' => $counts,
            'filters' => ['status' => $status],
            'return_url' => $returnUrl,
        ]);
    }

    public function retryFailed(MerchantEmailCampaign $campaign)
    {
        $count = $this->service->retryFailed($campaign);

        ActivityLogger::log(
            "Retried failed merchant emails for campaign: {$campaign->subject}",
            'merchant_email_retry',
            $campaign,
            ['campaign_id' => $campaign->id, 'retried' => $count],
            'merchant_email'
        );

        return back()->with('success', "Retried {$count} failed recipient(s).");
    }

    public function cancel(MerchantEmailCampaign $campaign)
    {
        if (!in_array($campaign->status, ['queued', 'sending', 'processing'], true)) {
            return back()->withErrors(['status' => 'Campaign cannot be cancelled.']);
        }

        $campaign->recipients()->where('status', 'pending')->update(['status' => 'cancelled']);
        $campaign->update(['status' => 'cancelled', 'completed_at' => now()]);

        ActivityLogger::log('Cancelled merchant email campaign: ' . $campaign->subject, 'merchant_email_cancelled', $campaign);

        return back()->with('success', 'Remaining email deliveries have been cancelled.');
    }

    public function archive(MerchantEmailCampaign $campaign)
    {
        if (!in_array($campaign->status, ['completed', 'failed', 'cancelled'], true)) {
            return back()->withErrors(['status' => 'Campaign cannot be archived.']);
        }

        $campaign->update(['archived_at' => now()]);

        ActivityLogger::log('Archived merchant email campaign: ' . $campaign->subject, 'merchant_email_archived', $campaign);

        return back()->with('success', 'Email moved to Archive. You can restore it later.');
    }

    public function restore(MerchantEmailCampaign $campaign)
    {
        if (!$campaign->archived_at) {
            return back()->withErrors(['status' => 'Email is not archived.']);
        }

        $campaign->update(['archived_at' => null]);

        ActivityLogger::log('Restored merchant email campaign: ' . $campaign->subject, 'merchant_email_restored', $campaign);

        return back()->with('success', 'Email restored successfully.');
    }

    public function destroy(MerchantEmailCampaign $campaign)
    {
        if (in_array($campaign->status, ['queued', 'sending'], true)) {
            return back()->withErrors(['status' => 'Cancel the delivery before deleting this email.']);
        }

        if (!$campaign->archived_at && $campaign->recipients()->where('status', 'sent')->exists()) {
            return back()->withErrors(['status' => 'Archive this email before permanently deleting delivered emails.']);
        }

        foreach ($campaign->attachments as $attachment) {
            \Illuminate\Support\Facades\Storage::disk('local')->delete($attachment->storage_path);
        }

        $campaign->delete();

        ActivityLogger::log('Deleted merchant email campaign: ' . $campaign->subject, 'merchant_email_deleted', $campaign);

        return redirect()->route('superadmin.email-communications.history')->with('success', 'Email record permanently deleted.');
    }

    public function downloadAttachment(\App\Models\MerchantEmailAttachment $attachment)
    {
        $path = $attachment->storage_path;
        if (!\Illuminate\Support\Facades\Storage::disk('local')->exists($path)) {
            abort(404);
        }

        return \Illuminate\Support\Facades\Storage::disk('local')->download($path, $attachment->original_filename);
    }

    public function storeDraft(Request $request)
    {
        $validated = $this->validatePayload($request, false);

        $request->validate([
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:5120|mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg',
        ]);

        $result = $this->service->preview($validated);
        $recipients = $result['recipients'];

        $campaign = \Illuminate\Support\Facades\DB::transaction(function () use ($validated, $request, $recipients) {
            $campaign = MerchantEmailCampaign::create([
                'created_by' => $request->user()?->id,
                'subject' => $validated['subject'],
                'email_type' => $validated['email_type'],
                'message' => $validated['message'],
                'recipient_mode' => $validated['recipient_mode'],
                'recipient_count' => count($recipients),
                'status' => 'draft',
                'reply_to' => $validated['reply_to'] ?? null,
                'filters' => $validated['recipient_mode'] === 'all' ? ['statuses' => $validated['statuses'] ?? []] : null,
            ]);

            foreach ($recipients as $recipient) {
                MerchantEmailRecipient::create([
                    'campaign_id' => $campaign->id,
                    'tenant_id' => $recipient['tenant_id'],
                    'recipient_email' => $recipient['recipient_email'],
                    'recipient_name' => $recipient['recipient_name'],
                    'status' => 'draft',
                ]);
            }

            if ($request->hasFile('attachments')) {
                foreach ($request->file('attachments') as $file) {
                    $path = $file->store('merchant-email-attachments/' . $campaign->id, 'local');
                    \App\Models\MerchantEmailAttachment::create([
                        'campaign_id' => $campaign->id,
                        'original_filename' => $file->getClientOriginalName(),
                        'storage_path' => $path,
                        'mime_type' => $file->getMimeType(),
                        'file_size' => $file->getSize(),
                    ]);
                }
            }

            return $campaign;
        });

        ActivityLogger::log('Saved draft merchant email campaign: ' . $campaign->subject, 'merchant_email_draft_saved', $campaign);

        return redirect()->route('superadmin.email-communications.show', $campaign)->with('success', 'Draft saved successfully.');
    }

    public function edit(MerchantEmailCampaign $campaign)
    {
        if ($campaign->status !== 'draft') {
            return redirect()->route('superadmin.email-communications.show', $campaign);
        }

        $campaign->load(['recipients', 'attachments']);

        $selectedTenantIds = $campaign->recipients->pluck('tenant_id')->filter()->values()->all();

        $existingRecipients = $campaign->recipients->map(function ($r) {
            return [
                'id' => $r->id,
                'name' => $r->recipient_name,
                'email' => $r->recipient_email,
                'status' => $r->status,
            ];
        })->values()->all();

        return Inertia::render('SuperAdmin/EmailCommunications/Compose', [
            'campaign' => $campaign,
            'selectedTenantIds' => $selectedTenantIds,
            'existingRecipients' => $existingRecipients,
            'email_types' => $this->service->emailTypes(),
            'tenant_statuses' => $this->service->tenantStatuses(),
            'default_statuses' => $campaign->filters['statuses'] ?? \App\Services\MerchantEmailCommunicationService::DEFAULT_STATUSES,
            'tenants' => \App\Models\Tenant::query()->orderBy('name')->paginate(15),
        ]);
    }

    public function update(MerchantEmailCampaign $campaign, Request $request)
    {
        if ($campaign->status !== 'draft') {
            return back()->withErrors(['status' => 'Only drafts can be edited.']);
        }

        $validated = $request->validate([
            'email_type' => 'required|string|in:' . implode(',', array_keys(\App\Services\MerchantEmailCommunicationService::EMAIL_TYPES)),
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:10000',
            'reply_to' => 'nullable|email|max:255',
        ]);

        $request->validate([
            'attachments' => 'nullable|array|max:10',
            'attachments.*' => 'file|max:5120|mimes:pdf,doc,docx,xls,xlsx,csv,png,jpg,jpeg',
            'remove_attachments' => 'nullable|array',
            'remove_attachments.*' => 'integer|exists:merchant_email_attachments,id',
        ]);

        if ($request->filled('remove_attachments')) {
            foreach (\App\Models\MerchantEmailAttachment::where('campaign_id', $campaign->id)->whereIn('id', $request->input('remove_attachments'))->get() as $attachment) {
                \Illuminate\Support\Facades\Storage::disk('local')->delete($attachment->storage_path);
                $attachment->delete();
            }
        }

        $campaign->update([
            'subject' => $validated['subject'],
            'email_type' => $validated['email_type'],
            'message' => $validated['message'],
            'reply_to' => $validated['reply_to'] ?? null,
        ]);

        if ($request->hasFile('attachments')) {
            foreach ($request->file('attachments') as $file) {
                $path = $file->store('merchant-email-attachments/' . $campaign->id, 'local');
                \App\Models\MerchantEmailAttachment::create([
                    'campaign_id' => $campaign->id,
                    'original_filename' => $file->getClientOriginalName(),
                    'storage_path' => $path,
                    'mime_type' => $file->getMimeType(),
                    'file_size' => $file->getSize(),
                ]);
            }
        }

        ActivityLogger::log('Updated draft merchant email campaign: ' . $campaign->subject, 'merchant_email_draft_updated', $campaign);

        return redirect()->route('superadmin.email-communications.show', $campaign)->with('success', 'Draft updated successfully.');
    }

    public function sendDraft(MerchantEmailCampaign $campaign)
    {
        $locked = null;

        try {
            \Illuminate\Support\Facades\DB::transaction(function () use ($campaign, &$locked) {
                $locked = MerchantEmailCampaign::lockForUpdate()->find($campaign->id);
                if (!$locked || $locked->status !== 'draft') {
                    throw new \RuntimeException('Only drafts can be sent.');
                }

                $locked->recipients()->where('status', 'draft')->update(['status' => 'pending']);
                $locked->update([
                    'status' => 'queued',
                    'recipient_count' => $locked->recipients()->count(),
                    'started_at' => now(),
                ]);
            });
        } catch (\RuntimeException $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        try {
            $this->service->dispatchCampaign($locked);
        } catch (\Throwable $e) {
            return back()->withErrors(['status' => $e->getMessage()]);
        }

        return redirect()->route('superadmin.email-communications.show', $locked)->with('success', 'Your email is now in the delivery queue. You can check delivery progress in Email Details.');
    }

    private function safeReturnUrl(?string $value): ?string
    {
        if (!$value) {
            return null;
        }
        $path = parse_url($value, PHP_URL_PATH);
        if (!$path || !str_starts_with($path, '/superadmin/email-communications')) {
            return null;
        }
        return $value;
    }

    private function validatePayload(Request $request, bool $preview): array
    {
        foreach (['statuses', 'tenant_ids'] as $field) {
            $value = $request->input($field);
            if (is_string($value)) {
                $decoded = json_decode($value, true);
                if (is_array($decoded)) {
                    $request->merge([$field => $decoded]);
                }
            }
        }
        $rules = [
            'email_type' => 'required|string|in:' . implode(',', array_keys(MerchantEmailCommunicationService::EMAIL_TYPES)),
            'subject' => 'required|string|max:255',
            'message' => 'required|string|max:10000',
            'reply_to' => 'nullable|email|max:255',
            'recipient_mode' => 'required|string|in:all,selected,single',
        ];

        if ($request->input('recipient_mode') === 'all') {
            $rules['statuses'] = 'nullable|array';
            $rules['statuses.*'] = 'string|in:' . implode(',', MerchantEmailCommunicationService::ALL_STATUSES);
        } elseif ($request->input('recipient_mode') === 'selected') {
            $rules['tenant_ids'] = 'nullable|array';
            $rules['tenant_ids.*'] = 'integer|exists:tenants,id';
            $rules['select_all'] = 'nullable|boolean';
            $rules['search'] = 'nullable|string|max:255';
            $rules['status'] = 'nullable|string|in:' . implode(',', MerchantEmailCommunicationService::ALL_STATUSES);
        } else {
            $rules['tenant_id'] = 'required|integer|exists:tenants,id';
        }

        $validated = $request->validate($rules);

        if (!isset($validated['statuses']) && $validated['recipient_mode'] === 'all') {
            $validated['statuses'] = MerchantEmailCommunicationService::DEFAULT_STATUSES;
        }

        return $validated;
    }
}
