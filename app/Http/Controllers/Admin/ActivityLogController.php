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

    private function getTenantFilter(): mixed
    {
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return false;
        }
        return \App\Models\Tenant::getCurrent();
    }

    public function index(Request $request)
    {
        if (!auth()->user()->can('activity-logs.view')) {
            abort(403, 'Unauthorized');
        }

        $logName = $request->get('log_name');
        $event = $request->get('event');

        $query = ActivityLog::with('causer', 'impersonator', 'impersonatedUser')
            ->when($this->getTenantFilter(), fn($q, $t) => $q->where('activity_logs.tenant_id', $t->id))
            ->when($logName, fn($q, $v) => $q->where('log_name', $v))
            ->when($event, fn($q, $v) => $q->where('event', $v))
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
                'log_name' => $logName,
                'event' => $event,
            ],
        ]);
    }

    public function show(int $id)
    {
        if (!auth()->user()->can('activity-logs.view')) {
            abort(403, 'Unauthorized');
        }

        $log = ActivityLog::with('causer', 'subject', 'impersonator', 'impersonatedUser')
            ->when($this->getTenantFilter(), fn($q, $t) => $q->where('activity_logs.tenant_id', $t->id))
            ->findOrFail($id);

        return Inertia::render('Admin/ActivityLogs/Show', [
            'log' => $log,
        ]);
    }
}
