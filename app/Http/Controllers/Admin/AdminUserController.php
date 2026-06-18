<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use App\Services\PerPageTrait;
use App\Services\SubscriptionLimitService;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    use PerPageTrait;

    public function __construct(
        private readonly \App\Services\ImageService $imageService
    ) {}

    private function isSuperAdmin(): bool
    {
        return auth()->check() && auth()->user()->isSuperAdmin();
    }

    private function protectOwner(User $user): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        if ($user->isOwner()) {
            abort(403, 'The merchant owner account cannot be modified or removed. Contact SuperAdmin.');
        }
    }

    private function getTenantFilter(): mixed
    {
        if ($this->isSuperAdmin()) {
            return false;
        }
        return Tenant::getCurrent();
    }

    public function index(Request $request)
    {
        if (!auth()->user()->can('users.view')) {
            abort(403, 'Unauthorized');
        }

        $search = $request->get('search');
        $role = $request->get('role');
        $status = $request->get('status');

        $users = User::with('roles')
            ->when($this->getTenantFilter(), fn($q, $t) => $q->where('users.tenant_id', $t->id))
            ->when($search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            }))
            ->when($role, fn($q, $r) => $q->whereHas('roles', fn($q) => $q->where('name', $r)))
            ->when($status, fn($q, $s) => $q->where('status', $s))
            ->orderBy('created_at', 'desc');

        $resolved = $this->resolvePerPage($request);
        $perPage = $resolved['per_page'];
        $warning = $resolved['warning'];
        
        if ($resolved['should_paginate']) {
            $users = $users->paginate($perPage)->withQueryString();
            $showPagination = true;
        } else {
            $total = $users->count();
            $items = $users->get();
            
            $users = new \Illuminate\Pagination\LengthAwarePaginator(
                $items,
                $total,
                $total,
                1,
                ['path' => $request->url(), 'query' => $request->query()]
            );
            $showPagination = false;
        }

        $roles = Role::orderBy('name')
            ->when($this->getTenantFilter(), fn($q, $t) => $q->where('tenant_id', $t->id))
            ->pluck('name');

        return Inertia::render('Admin/Users/Index', [
            'users' => $users,
            'showPagination' => $showPagination,
            'warning' => $warning,
            'filters' => ['search' => $search, 'role' => $role, 'status' => $status],
            'roles' => $roles,
        ]);
    }

    public function create()
    {
        if (!auth()->user()->can('users.create')) {
            abort(403, 'Unauthorized');
        }

        $roles = Role::orderBy('name')
            ->when($this->getTenantFilter(), fn($q, $t) => $q->where('tenant_id', $t->id))
            ->pluck('name');

        return Inertia::render('Admin/Users/Create', [
            'roles' => $roles,
        ]);
    }

    public function store(StoreUserRequest $request)
    {
        if (!auth()->user()->can('users.create')) {
            abort(403, 'Unauthorized');
        }

        $data = $request->validated();

        // Enforce plan staff limit for admin role users
        if (($data['role'] ?? null) === 'admin') {
            SubscriptionLimitService::for()->assertCanCreateStaff();
        }

        $user = User::create([
            'name' => $data['name'],
            'email' => $data['email'],
            'password' => Hash::make($data['password']),
            'status' => $data['status'] ?? User::STATUS_ACTIVE,
            'allow_cod' => $data['allow_cod'] ?? false,
        ]);

        $user->syncRoles([$data['role']]);

        if ($request->hasFile('profile_image')) {
            $path = $this->imageService->upload($request->file('profile_image'), 'profile-images');
            $user->update(['profile_image' => $path]);
        }

        $user->logActivity('created', "User created by admin", [
            'created_by' => auth()->id(),
            'assigned_role' => $data['role'],
        ]);

        return admin_redirect('admin.users.index')
            ->with('success', 'User created successfully.');
    }

    public function show(int $id)
    {
        if (!auth()->user()->can('users.view')) {
            abort(403, 'Unauthorized');
        }

        $user = User::with('roles')
            ->when($this->getTenantFilter(), fn($q, $t) => $q->where('users.tenant_id', $t->id))
            ->findOrFail($id);

        $activities = auth()->user()->can('users.view-activity')
            ? ActivityLog::query()
                ->where('subject_type', User::class)
                ->where('subject_id', $user->id)
                ->latest()
                ->limit(20)
                ->get()
            : [];

        return Inertia::render('Admin/Users/Show', [
            'user' => $user,
            'activities' => $activities,
        ]);
    }

    public function edit(int $id)
    {
        if (!auth()->user()->can('users.update')) {
            abort(403, 'Unauthorized');
        }

        $user = User::with('roles')
            ->when($this->getTenantFilter(), fn($q, $t) => $q->where('users.tenant_id', $t->id))
            ->findOrFail($id);
        $roles = Role::orderBy('name')
            ->when($this->getTenantFilter(), fn($q, $t) => $q->where('tenant_id', $t->id))
            ->pluck('name');

        return Inertia::render('Admin/Users/Edit', [
            'user' => $user,
            'roles' => $roles,
        ]);
    }

    public function update(UpdateUserRequest $request, int $id)
    {
        if (!auth()->user()->can('users.update')) {
            abort(403, 'Unauthorized');
        }

        $user = User::with('roles')
            ->when($this->getTenantFilter(), fn($q, $t) => $q->where('users.tenant_id', $t->id))
            ->findOrFail($id);
        $data = $request->validated();
        $changes = [];

        if (isset($data['name']) && $data['name'] !== $user->name) {
            $changes[] = 'name';
        }
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $changes[] = 'email';
        }

        $updateData = [];
        foreach (['name', 'email'] as $field) {
            if (isset($data[$field])) {
                $updateData[$field] = $data[$field];
            }
        }

        if (array_key_exists('allow_cod', $data)) {
            $updateData['allow_cod'] = $data['allow_cod'];
            $changes[] = 'allow_cod';
        }

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
            $changes[] = 'password';
        }

        if (!empty($data['status'])) {
            if ($data['status'] !== $user->status) {
                if ($data['status'] !== User::STATUS_ACTIVE && auth()->id() === $user->id) {
                    return redirect()->back()->with('error', 'You cannot change your own status.');
                }
                $updateData['status'] = $data['status'];
                $changes[] = "status:{$user->status}->{$data['status']}";
            }
        }

        if (!empty($updateData)) {
            $user->update($updateData);
        }

        if (isset($data['role'])) {
            if (!auth()->user()->can('users.assign-roles')) {
                abort(403, 'Unauthorized');
            }

            $currentRoles = $user->roles->pluck('name')->toArray();
            if ($data['role'] !== ($currentRoles[0] ?? null)) {
                if ($user->isOwner() && $data['role'] !== 'admin' && !$this->isSuperAdmin()) {
                    return redirect()->back()->with('error', 'The merchant owner role cannot be changed. Contact SuperAdmin.');
                }

                if ($user->hasRole('superadmin') && $data['role'] !== 'superadmin') {
                    $superadminCount = User::role('superadmin')->count();
                    if ($superadminCount <= 1) {
                        return redirect()->back()->with('error', 'Cannot remove the last remaining superadmin.');
                    }
                }
                $user->syncRoles([$data['role']]);
                $changes[] = "role:->{$data['role']}";
            }
        }

        if ($request->hasFile('profile_image')) {
            $path = $this->imageService->upload($request->file('profile_image'), 'profile-images');
            $user->update(['profile_image' => $path]);
            $changes[] = 'profile_image';
        }

        if (!empty($changes)) {
            $user->logActivity('updated', "User updated by admin", [
                'updated_by' => auth()->id(),
                'changes' => $changes,
            ]);
        }

        return admin_redirect('admin.users.index')
            ->with('success', 'User updated successfully.');
    }

    public function destroy(int $id)
    {
        if (!auth()->user()->can('users.delete')) {
            abort(403, 'Unauthorized');
        }

        $user = User::with('roles')
            ->when($this->getTenantFilter(), fn($q, $t) => $q->where('users.tenant_id', $t->id))
            ->findOrFail($id);

        $this->protectOwner($user);

        if ($user->hasRole('superadmin')) {
            $superadminCount = User::role('superadmin')->count();
            if ($superadminCount <= 1) {
                return admin_redirect('admin.users.index')
                    ->with('error', 'Cannot delete the last remaining superadmin.');
            }
        }

        if ($user->hasRole('admin') && !$user->hasRole('superadmin')) {
            $adminCount = User::role('admin')
                ->when($this->getTenantFilter(), fn($q, $t) => $q->where('users.tenant_id', $t->id))
                ->count();
            if ($adminCount <= 1) {
                return admin_redirect('admin.users.index')
                    ->with('error', 'Cannot delete the last remaining admin.');
            }
        }

        if (auth()->id() === $user->id) {
            return admin_redirect('admin.users.index')
                ->with('error', 'You cannot delete your own account.');
        }

        $user->logActivity('deleted', "User deleted by admin", [
            'deleted_by' => auth()->id(),
        ]);

        $user->delete();

        return admin_redirect('admin.users.index')
            ->with('success', 'User deleted successfully.');
    }

    public function suspend(int $id)
    {
        if (!auth()->user()->can('users.suspend')) {
            abort(403, 'Unauthorized');
        }

        $user = User::when(Tenant::getCurrent(), fn($q, $t) => $q->where('users.tenant_id', $t->id))
            ->findOrFail($id);

        $this->protectOwner($user);

        if (auth()->id() === $user->id) {
            return redirect()->back()->with('error', 'You cannot suspend your own account.');
        }

        $user->update(['status' => User::STATUS_SUSPENDED]);

        $user->logActivity('suspended', "User suspended by admin", [
            'suspended_by' => auth()->id(),
        ]);

        return admin_redirect('admin.users.index')
            ->with('success', 'User suspended successfully.');
    }

    public function ban(int $id)
    {
        if (!auth()->user()->can('users.ban')) {
            abort(403, 'Unauthorized');
        }

        $user = User::when(Tenant::getCurrent(), fn($q, $t) => $q->where('users.tenant_id', $t->id))
            ->findOrFail($id);

        $this->protectOwner($user);

        if (auth()->id() === $user->id) {
            return redirect()->back()->with('error', 'You cannot ban your own account.');
        }

        $user->update(['status' => User::STATUS_BANNED]);

        $user->logActivity('banned', "User banned by admin", [
            'banned_by' => auth()->id(),
        ]);

        return admin_redirect('admin.users.index')
            ->with('success', 'User banned successfully.');
    }

    public function activate(int $id)
    {
        if (!auth()->user()->can('users.activate')) {
            abort(403, 'Unauthorized');
        }

        $user = User::when(Tenant::getCurrent(), fn($q, $t) => $q->where('users.tenant_id', $t->id))
            ->findOrFail($id);

        $user->update(['status' => User::STATUS_ACTIVE]);

        $user->logActivity('activated', "User reactivated by admin", [
            'activated_by' => auth()->id(),
        ]);

        return admin_redirect('admin.users.index')
            ->with('success', 'User reactivated successfully.');
    }
}
