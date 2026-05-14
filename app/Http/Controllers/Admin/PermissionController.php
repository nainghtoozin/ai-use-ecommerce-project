<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\Request;
use Inertia\Inertia;
use Spatie\Permission\Models\Permission;

class PermissionController extends Controller
{
    public function index(Request $request)
    {
        if (!auth()->user()->can('permissions.view')) {
            abort(403, 'Unauthorized');
        }

        $search = $request->input('search');

        $permissions = Permission::orderBy('name')
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->paginate(20)
            ->through(fn($permission) => [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
                'group' => explode('.', $permission->name)[0],
                'created_at' => $permission->created_at->format('Y-m-d H:i:s'),
            ]);

        $grouped = Permission::orderBy('name')->get()
            ->groupBy(fn($p) => explode('.', $p->name)[0])
            ->map(fn($group, $key) => [
                'group' => $key,
                'label' => $this->getGroupLabel($key),
                'count' => $group->count(),
            ])
            ->values();

        return Inertia::render('Admin/Permissions/Index', [
            'permissions' => $permissions,
            'grouped' => $grouped,
            'filters' => ['search' => $search],
        ]);
    }

    private function getGroupLabel(string $group): string
    {
        $labels = [
            'users' => 'Users',
            'roles' => 'Roles',
            'permissions' => 'Permissions',
            'products' => 'Products',
            'categories' => 'Categories',
            'orders' => 'Orders',
            'payments' => 'Payments',
            'dashboard' => 'Dashboard',
            'activity-logs' => 'Activity Logs',
        ];

        return $labels[$group] ?? ucfirst($group);
    }
}
