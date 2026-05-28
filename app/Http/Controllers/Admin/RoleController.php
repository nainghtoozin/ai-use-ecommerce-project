<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreRoleRequest;
use App\Http\Requests\UpdateRoleRequest;
use App\Models\Tenant;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Inertia\Inertia;
use App\Models\Role;
use Spatie\Permission\Models\Permission;

class RoleController extends Controller
{
    private function getTenantFilter(): mixed
    {
        if (auth()->check() && auth()->user()->isSuperAdmin()) {
            return false;
        }
        return Tenant::getCurrent();
    }

    public function index(Request $request)
    {
        if (!auth()->user()->can('roles.view')) {
            abort(403, 'Unauthorized');
        }

        $search = $request->input('search');

        $roles = Role::with('permissions')
            ->withCount(['users' => function ($q) {
                $q->when($this->getTenantFilter(), fn($q, $t) => $q->where('users.tenant_id', $t->id));
            }])
            ->when($this->getTenantFilter(), fn($q, $tenant) => $q->where('tenant_id', $tenant->id))
            ->when($search, fn($q) => $q->where('name', 'like', "%{$search}%"))
            ->orderBy('name')
            ->paginate(10)
            ->through(fn($role) => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions_count' => $role->permissions->count(),
                'users_count' => $role->users_count,
                'created_at' => $role->created_at->format('Y-m-d H:i:s'),
            ]);

        return Inertia::render('Admin/Roles/Index', [
            'roles' => $roles,
            'filters' => ['search' => $search],
        ]);
    }

    public function create()
    {
        if (!auth()->user()->can('roles.create')) {
            abort(403, 'Unauthorized');
        }

        $groupedPermissions = $this->getGroupedPermissions();

        return Inertia::render('Admin/Roles/Create', [
            'permission_groups' => $groupedPermissions,
        ]);
    }

    public function store(StoreRoleRequest $request)
    {
        $role = Role::create(['name' => $request->name, 'guard_name' => 'web']);

        if ($request->filled('permissions')) {
            $role->syncPermissions($request->permissions);
        }

        ActivityLogger::log(
            "Role '{$role->name}' created",
            'role_created',
            $role,
            ['permissions' => $request->permissions ?? []],
            'roles'
        );

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role created successfully.');
    }

    public function show($id)
    {
        if (!auth()->user()->can('roles.view')) {
            abort(403, 'Unauthorized');
        }

        $role = Role::with('permissions')
            ->withCount(['users' => function ($q) {
                $q->when($this->getTenantFilter(), fn($q, $t) => $q->where('users.tenant_id', $t->id));
            }])
            ->when($this->getTenantFilter(), fn($q, $tenant) => $q->where('tenant_id', $tenant->id))
            ->findOrFail($id);

        $groupedPermissions = $role->permissions
            ->groupBy(fn($p) => explode('.', $p->name)[0])
            ->map(fn($group, $key) => [
                'group' => $key,
                'label' => $this->getGroupLabel($key),
                'permissions' => $group->map(fn($p) => $p->name)->values(),
            ])
            ->values();

        return Inertia::render('Admin/Roles/Show', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions_count' => $role->permissions->count(),
                'users_count' => $role->users_count,
                'created_at' => $role->created_at->format('Y-m-d H:i:s'),
                'updated_at' => $role->updated_at->format('Y-m-d H:i:s'),
            ],
            'grouped_permissions' => $groupedPermissions,
        ]);
    }

    public function edit($id)
    {
        if (!auth()->user()->can('roles.update')) {
            abort(403, 'Unauthorized');
        }

        $role = Role::with('permissions')
            ->when($this->getTenantFilter(), fn($q, $tenant) => $q->where('tenant_id', $tenant->id))
            ->findOrFail($id);

        $groupedPermissions = $this->getGroupedPermissions();

        return Inertia::render('Admin/Roles/Edit', [
            'role' => [
                'id' => $role->id,
                'name' => $role->name,
                'guard_name' => $role->guard_name,
                'permissions' => $role->permissions->pluck('name')->toArray(),
            ],
            'permission_groups' => $groupedPermissions,
        ]);
    }

    public function update(UpdateRoleRequest $request, $id)
    {
        $role = Role::when($this->getTenantFilter(), fn($q, $tenant) => $q->where('tenant_id', $tenant->id))
            ->findOrFail($id);

        $role->update(['name' => $request->name]);

        $newPermissions = $request->filled('permissions') ? $request->permissions : [];

        if ($role->name === 'superadmin' && empty($newPermissions)) {
            return redirect()->back()
                ->with('error', 'Cannot remove all permissions from the superadmin role.');
        }

        $role->syncPermissions($newPermissions);

        ActivityLogger::log(
            "Role '{$role->name}' updated",
            'role_updated',
            $role,
            [
                'name' => $request->name,
                'permissions' => $newPermissions,
            ],
            'roles'
        );

        return redirect()->route('admin.roles.index')
            ->with('success', 'Role updated successfully.');
    }

    public function destroy($id)
    {
        if (!auth()->user()->can('roles.delete')) {
            abort(403, 'Unauthorized');
        }

        $role = Role::when($this->getTenantFilter(), fn($q, $tenant) => $q->where('tenant_id', $tenant->id))
            ->findOrFail($id);

        if (in_array($role->name, ['superadmin', 'admin', 'customer'])) {
            return redirect()->route('admin.roles.index')
                ->with('error', "The '{$role->name}' role cannot be deleted.");
        }

        $roleUserCount = $role->users()
            ->when($this->getTenantFilter(), fn($q, $t) => $q->where('users.tenant_id', $t->id))
            ->count();

        if ($roleUserCount > 0) {
            return redirect()->route('admin.roles.index')
                ->with('error', "Cannot delete role '{$role->name}' because it is assigned to {$roleUserCount} user(s). Please reassign them first.");
        }

        $roleName = $role->name;

        ActivityLogger::log(
            "Role '{$roleName}' deleted",
            'role_deleted',
            null,
            ['role_name' => $roleName],
            'roles'
        );

        $role->delete();

        return redirect()->route('admin.roles.index')
            ->with('success', "Role '{$roleName}' deleted successfully.");
    }

    private function getGroupedPermissions(): array
    {
        $permissions = Permission::orderBy('name')->get();

        return $permissions
            ->groupBy(fn($p) => explode('.', $p->name)[0])
            ->map(fn($group, $key) => [
                'group' => $key,
                'label' => $this->getGroupLabel($key),
                'items' => $group->map(fn($p) => [
                    'id' => $p->id,
                    'name' => $p->name,
                ])->values(),
            ])
            ->values()
            ->toArray();
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
