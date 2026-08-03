<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Services\PerPageTrait;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    use PerPageTrait;

    // Security/auth events that belong to Audit Log (exclude from Activity)
    const AUDIT_EVENTS = [
        'login', 'logout', 'password_reset', 'password_changed', 'failed_login',
        'email_verified', 'registered',
        'suspended', 'banned', 'activated', 'locked', 'unlocked',
        'impersonation_started', 'impersonation_ended',
        'role_assigned', 'role_removed', 'permission_changed',
        'subscription_changed', 'tenant_status_changed', 'owner_changed',
        'security_settings_updated', 'two_factor_enabled', 'two_factor_disabled',
    ];

    private function getTenantFilter(): mixed
    {
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return false;
        }
        return \App\Models\Tenant::getCurrent();
    }

    public function index(Request $request)
    {
        if (!auth()->user()->can('activity.view')) {
            abort(403, 'Unauthorized');
        }

        $category = $request->get('category');
        $severity = $request->get('severity');
        $event = $request->get('event');
        $causerId = $request->get('causer_id');
        $search = $request->get('search');
        $dateFrom = $request->get('date_from');
        $dateTo = $request->get('date_to');

        $hasFilters = $category || $severity || $event || $causerId || $search || $dateFrom || $dateTo;

        $query = ActivityLog::with('causer', 'impersonator', 'impersonatedUser')
            ->when($this->getTenantFilter(), fn($q, $t) => $q->where('activity_logs.tenant_id', $t->id))
            // Exclude audit/security events from Activity view
            ->whereNotIn('event', self::AUDIT_EVENTS)
            // Only apply category filter when user selects one
            ->when($category, fn($q, $v) => $q->where('category', $v))
            ->when($severity, fn($q, $v) => $q->where('severity', $v))
            ->when($event, fn($q, $v) => $q->where('event', $v))
            ->when($causerId, fn($q, $v) => $q->where('causer_id', $v))
            ->when($search, fn($q, $v) => $q->where('description', 'like', "%{$v}%"))
            ->when($dateFrom, fn($q, $v) => $q->where('created_at', '>=', $v))
            ->when($dateTo, fn($q, $v) => $q->where('created_at', '<=', $v . ' 23:59:59'))
            ->latest();

        $resolved = $this->resolvePerPage($request);
        $perPage = $resolved['per_page'];

        if ($resolved['should_paginate']) {
            $logs = $query->paginate($perPage)->withQueryString();
            $showPagination = true;
        } else {
            $total = $query->count();
            $items = $query->get();

            $logs = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $total,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            $showPagination = false;
        }

        return Inertia::render('Admin/ActivityLogs/Index', [
            'logs' => $logs,
            'showPagination' => $showPagination,
            'filters' => [
                'category' => $category,
                'severity' => $severity,
                'event' => $event,
                'causer_id' => $causerId,
                'search' => $search,
                'date_from' => $dateFrom,
                'date_to' => $dateTo,
            ],
            'categories' => [
                ActivityLog::CATEGORY_ORDERS => 'Orders',
                ActivityLog::CATEGORY_INVENTORY => 'Inventory',
                ActivityLog::CATEGORY_PRODUCTS => 'Products',
                ActivityLog::CATEGORY_USERS => 'Users',
                ActivityLog::CATEGORY_WEBSITE => 'Website',
                ActivityLog::CATEGORY_SETTINGS => 'Settings',
                ActivityLog::CATEGORY_BILLING => 'Billing',
                ActivityLog::CATEGORY_NOTIFICATIONS => 'Notifications',
                ActivityLog::CATEGORY_SYSTEM => 'System',
            ],
            'severities' => ActivityLog::getSeverities(),
        ]);
    }

    public function show(int $id)
    {
        if (!auth()->user()->can('activity.view')) {
            abort(403, 'Unauthorized');
        }

        $log = ActivityLog::with('causer', 'subject', 'impersonator', 'impersonatedUser')
            ->when($this->getTenantFilter(), fn($q, $t) => $q->where('activity_logs.tenant_id', $t->id))
            ->whereNotIn('event', self::AUDIT_EVENTS)
            ->findOrFail($id);

        return Inertia::render('Admin/ActivityLogs/Show', [
            'log' => $log,
        ]);
    }
}
