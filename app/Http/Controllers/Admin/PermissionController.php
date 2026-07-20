<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Services\ActivityLogger;
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

    public function create()
    {
        if (!auth()->user()->can('permissions.create')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Permissions/Create');
    }

    public function store(Request $request)
    {
        if (!auth()->user()->can('permissions.create')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name'],
            'guard_name' => ['nullable', 'string', 'max:255'],
        ]);

        Permission::create([
            'name' => $validated['name'],
            'guard_name' => $validated['guard_name'] ?? 'web',
        ]);

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Permission created successfully.');
    }

    public function edit(Permission $permission)
    {
        if (!auth()->user()->can('permissions.update')) {
            abort(403, 'Unauthorized');
        }

        return Inertia::render('Admin/Permissions/Edit', [
            'permission' => [
                'id' => $permission->id,
                'name' => $permission->name,
                'guard_name' => $permission->guard_name,
            ],
        ]);
    }

    public function update(Request $request, Permission $permission)
    {
        if (!auth()->user()->can('permissions.update')) {
            abort(403, 'Unauthorized');
        }

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255', 'unique:permissions,name,' . $permission->id],
        ]);

        $permission->update([
            'name' => $validated['name'],
        ]);

        ActivityLogger::log("Permission '{$permission->name}' updated", 'permission_updated', $permission);

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Permission updated successfully.');
    }

    public function destroy(Permission $permission)
    {
        if (!auth()->user()->can('permissions.delete')) {
            abort(403, 'Unauthorized');
        }

        $roleCount = $permission->roles()->count();
        if ($roleCount > 0) {
            return redirect()->route('admin.permissions.index')
                ->with('error', "Cannot delete permission '{$permission->name}' because it is assigned to {$roleCount} role(s). Please remove it from all roles first.");
        }

        $permission->delete();

        return redirect()->route('admin.permissions.index')
            ->with('success', 'Permission deleted successfully.');
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
            'reports' => 'Reports',
            'settings' => 'Settings',
        ];

        return $labels[$group] ?? ucfirst($group);
    }
}
