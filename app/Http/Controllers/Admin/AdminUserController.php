<?php

namespace App\Http\Controllers\Admin;

use App\Auth\IdentityResolver;
use App\Http\Controllers\Controller;
use App\Http\Requests\StoreUserRequest;
use App\Http\Requests\UpdateUserRequest;
use App\Models\Account;
use App\Models\ActivityLog;
use App\Models\Tenant;
use App\Models\User;
use App\Exceptions\SubscriptionLimitException;
use App\Services\PerPageTrait;
use App\Services\SubscriptionLimitService;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\Log;
use Inertia\Inertia;
use Spatie\Permission\Models\Role;

class AdminUserController extends Controller
{
    use PerPageTrait;

    public function __construct(
        private readonly IdentityResolver $identityResolver,
        private readonly \App\Services\ImageService $imageService,
    ) {}

    private function isSuperAdmin(): bool
    {
        return auth()->check() && auth()->user()->isSuperAdmin();
    }

    private function protectOwner(Model $user): void
    {
        if ($this->isSuperAdmin()) {
            return;
        }

        $isOwner = $user instanceof Account
            ? $user->isOwner($this->getTenantFilter())
            : $user->isOwner();

        if ($isOwner) {
            abort(403, 'The merchant owner account cannot be modified or removed. Contact SuperAdmin.');
        }
    }

    private function getTenantFilter(): mixed
    {
        if ($this->isSuperAdmin()) {
            return false;
        }
        if ($this->identityResolver->supportsAccount()) {
            return $this->identityResolver->getCurrentTenantId();
        }
        return auth()->user()->tenant_id;
    }

    private function countSuperAdmins(): int
    {
        if ($this->identityResolver->supportsAccount()) {
            return Account::query()
                ->whereHas('roles', fn ($q) => $q->where('name', 'superadmin'))
                ->count();
        }

        return User::role('superadmin')->count();
    }

    private function countTenantAdmins(mixed $tenantId): int
    {
        if ($this->identityResolver->supportsAccount()) {
            return \App\Models\TenantMembership::query()
                ->when($tenantId, fn ($q, $id) => $q->where('tenant_id', $id))
                ->where(fn ($q) => $q
                    ->where('is_owner', true)
                    ->orWhereHas('role', fn ($r) => $r->where('name', 'admin')))
                ->count();
        }

        return User::role('admin')
            ->when($tenantId, fn ($q, $id) => $q->where('users.tenant_id', $id))
            ->count();
    }

    public function index(Request $request)
    {
        if (!auth()->user()->can('users.view')) {
            abort(403, 'Unauthorized');
        }

        $search = $request->get('search');
        $role = $request->get('role');
        $status = $request->get('status');
        $tenantId = $this->getTenantFilter();

        $useAccounts = $this->identityResolver->supportsAccount();

        $users = $this->identityResolver->queryUsersForTenant($tenantId)
            ->when($useAccounts, function ($q) use ($tenantId) {
                $q->whereDoesntHave('memberships', function ($m) use ($tenantId) {
                    $m->when($tenantId, fn($m) => $m->where('tenant_id', $tenantId))
                        ->whereHas('role', fn($r) => $r->where('name', 'customer'));
                });
            }, fn($q) => $q->whereDoesntHave('roles', fn($r) => $r->where('name', 'customer')))
            ->when($search, fn($q, $s) => $q->where(function ($q) use ($s) {
                $q->where('name', 'like', "%{$s}%")
                  ->orWhere('email', 'like', "%{$s}%");
            }))
            ->when($role, fn($q, $r) => $useAccounts
                ? $q->whereHas('memberships', fn($m) => $m
                    ->when($tenantId, fn($m) => $m->where('tenant_id', $tenantId))
                    ->whereHas('role', fn($qr) => $qr->where('name', $r)))
                : $q->whereHas('roles', fn($qr) => $qr->where('name', $r))
            )
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
            ->when($tenantId, fn($q, $id) => $q->where('tenant_id', $id))
            ->where('name', '!=', 'customer')
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
            ->when($this->getTenantFilter(), fn($q, $tenantId) => $q->where('tenant_id', $tenantId))
            ->where('name', '!=', 'customer')
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

        if (($data['role'] ?? null) === 'admin') {
            try {
                SubscriptionLimitService::for()->assertCanCreateStaff();
            } catch (SubscriptionLimitException $e) {
                return back()->with('error', $e->getMessage())->withInput();
            }
        }

        $tenantId = $this->getTenantFilter();

        if ($this->identityResolver->supportsAccount()) {
            $user = Account::where('email', $data['email'])->first();

            if ($user) {
                $existingMembership = $user->memberships()
                    ->where('tenant_id', $tenantId)
                    ->exists();

                if ($existingMembership) {
                    return back()->withErrors([
                        'email' => 'This account is already a member of this store.',
                    ])->onlyInput('email');
                }
            } else {
                $user = Account::create([
                    'name' => $data['name'],
                    'email' => $data['email'],
                    'password' => Hash::make($data['password']),
                    'status' => $data['status'] ?? Account::STATUS_ACTIVE,
                    'notification_preferences' => [
                        'email' => true,
                        'browser' => true,
                        'telegram' => true,
                        'marketing' => false,
                        'order_updates' => true,
                        'system_alerts' => true,
                    ],
                ]);
            }

            $roleModel = Role::where('name', $data['role'])
                ->where(fn ($q) => $q->where('tenant_id', $tenantId)->orWhereNull('tenant_id'))
                ->first();

            if (! $roleModel) {
                return back()->withErrors([
                    'role' => 'The selected role does not exist for this store.',
                ])->onlyInput('email');
            }

            $user->memberships()->create([
                'tenant_id' => $tenantId,
                'role_id' => $roleModel->id,
                'is_owner' => false,
                'status' => 'active',
                'invited_at' => now(),
                'joined_at' => now(),
            ]);

            if ($request->hasFile('profile_image')) {
                $path = $this->imageService->upload($request->file('profile_image'), 'profile-images');
                $user->update(['profile_image' => $path]);
            }
        } else {
            $user = User::create([
                'name' => $data['name'],
                'email' => $data['email'],
                'password' => Hash::make($data['password']),
                'status' => $data['status'] ?? User::STATUS_ACTIVE,
                'notification_preferences' => [
                    'email' => true,
                    'browser' => true,
                    'telegram' => true,
                    'marketing' => false,
                    'order_updates' => true,
                    'system_alerts' => true,
                ],
            ]);

            if ($request->hasFile('profile_image')) {
                $path = $this->imageService->upload($request->file('profile_image'), 'profile-images');
                $user->update(['profile_image' => $path]);
            }
        }

        $user->syncRoles([$data['role']]);
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

        $user = $this->identityResolver->findUserForTenant($id, $this->getTenantFilter());
        if (!$user) {
            abort(404);
        }

        $activities = auth()->user()->can('users.view-activity')
            ? ActivityLog::query()
                ->where('subject_type', $user::class)
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

        $user = $this->identityResolver->findUserForTenant($id, $this->getTenantFilter());
        if (!$user) {
            abort(404);
        }

        $roles = Role::orderBy('name')
            ->when($this->getTenantFilter(), fn($q, $tenantId) => $q->where('tenant_id', $tenantId))
            ->where('name', '!=', 'customer')
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

        $user = $this->identityResolver->findUserForTenant($id, $this->getTenantFilter());
        if (!$user) {
            abort(404);
        }
        $data = $request->validated();
        $changes = [];

        if (isset($data['name']) && $data['name'] !== $user->name) {
            $changes[] = 'name';
        }
        if (isset($data['email']) && $data['email'] !== $user->email) {
            $changes[] = 'email';
        }

        $updateData = [];
        if ($user instanceof User) {
            foreach (['name', 'email'] as $field) {
                if (isset($data[$field])) {
                    $updateData[$field] = $data[$field];
                }
            }
        } else {
            if (isset($data['email'])) {
                $updateData['email'] = $data['email'];
            }
        }

        if (!empty($data['password'])) {
            $updateData['password'] = Hash::make($data['password']);
            $changes[] = 'password';
        }

        $statusConst = $user instanceof User ? User::class : Account::class;
        if (!empty($data['status'])) {
            if ($data['status'] !== $user->status) {
                if ($data['status'] !== ($statusConst)::STATUS_ACTIVE && auth()->id() === $user->id) {
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

            $currentRoleNames = $user->getRoleNames()->toArray();
            if ($data['role'] !== ($currentRoleNames[0] ?? null)) {
                if ($user->isOwner() && $data['role'] !== 'admin' && !$this->isSuperAdmin()) {
                    return redirect()->back()->with('error', 'The merchant owner role cannot be changed. Contact SuperAdmin.');
                }

                if ($user->hasRole('superadmin') && $data['role'] !== 'superadmin') {
                    $superadminCount = $this->countSuperAdmins();
                    if ($superadminCount <= 1) {
                        return redirect()->back()->with('error', 'Cannot remove the last remaining superadmin.');
                    }
                }
                if ($this->identityResolver->supportsAccount()) {
                    $tenantIdForRole = $this->getTenantFilter();
                    $roleExistsForTenant = $tenantIdForRole
                        ? Role::where('name', $data['role'])
                            ->where(fn ($q) => $q->where('tenant_id', $tenantIdForRole)->orWhereNull('tenant_id'))
                            ->exists()
                        : Role::where('name', $data['role'])->exists();
                    if (! $roleExistsForTenant) {
                        return redirect()->back()->withErrors([
                            'role' => 'The selected role does not exist for this store.',
                        ]);
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

        $user = $this->identityResolver->findUserForTenant($id, $this->getTenantFilter());
        if (!$user) {
            abort(404);
        }

        $this->protectOwner($user);

        if ($user->hasRole('superadmin')) {
            $superadminCount = $this->countSuperAdmins();
            if ($superadminCount <= 1) {
                return admin_redirect('admin.users.index')
                    ->with('error', 'Cannot delete the last remaining superadmin.');
            }
        }

        if ($user->hasRole('admin') && !$user->hasRole('superadmin')) {
            $adminCount = $this->countTenantAdmins($this->getTenantFilter());
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

        $user = $this->identityResolver->findUserForTenant($id, $this->getTenantFilter());
        if (!$user) {
            abort(404);
        }

        $this->protectOwner($user);

        if (auth()->id() === $user->id) {
            return redirect()->back()->with('error', 'You cannot suspend your own account.');
        }

        $user->update(['status' => $user instanceof User ? User::STATUS_SUSPENDED : Account::STATUS_SUSPENDED]);

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

        $user = $this->identityResolver->findUserForTenant($id, $this->getTenantFilter());
        if (!$user) {
            abort(404);
        }

        $this->protectOwner($user);

        if (auth()->id() === $user->id) {
            return redirect()->back()->with('error', 'You cannot ban your own account.');
        }

        $user->update(['status' => $user instanceof User ? User::STATUS_BANNED : Account::STATUS_BANNED]);

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

        $user = $this->identityResolver->findUserForTenant($id, $this->getTenantFilter());
        if (!$user) {
            abort(404);
        }

        $user->update(['status' => $user instanceof User ? User::STATUS_ACTIVE : Account::STATUS_ACTIVE]);

        $user->logActivity('activated', "User reactivated by admin", [
            'activated_by' => auth()->id(),
        ]);

        return admin_redirect('admin.users.index')
            ->with('success', 'User reactivated successfully.');
    }
}
