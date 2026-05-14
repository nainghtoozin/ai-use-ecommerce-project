<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use Illuminate\Http\Request;
use Inertia\Inertia;

class ActivityLogController extends Controller
{
    public function index(Request $request)
    {
        $logName = $request->get('log_name');
        $event = $request->get('event');

        $logs = ActivityLog::with('causer')
            ->when($logName, fn($q, $v) => $q->where('log_name', $v))
            ->when($event, fn($q, $v) => $q->where('event', $v))
            ->latest()
            ->paginate(30)
            ->withQueryString();

        return Inertia::render('Admin/ActivityLogs/Index', [
            'logs' => $logs,
            'filters' => [
                'log_name' => $logName,
                'event' => $event,
            ],
        ]);
    }

    public function show(int $id)
    {
        $log = ActivityLog::with('causer', 'subject')->findOrFail($id);

        return Inertia::render('Admin/ActivityLogs/Show', [
            'log' => $log,
        ]);
    }
}
