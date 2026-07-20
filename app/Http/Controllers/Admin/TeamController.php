<?php

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Mail\TeamInvitationMail;
use App\Models\Account;
use App\Models\Role;
use App\Models\Tenant;
use App\Models\TenantMembership;
use App\Models\TeamInvitation;
use App\Notifications\TeamInvitationNotification;
use App\Services\ActivityLogger;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Notification;
use Inertia\Inertia;

class TeamController extends Controller
{
    public function index()
    {
        if (!auth()->user()->can('users.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        $members = TenantMembership::where('tenant_id', $tenant->id)
            ->with(['account', 'role'])
            ->orderBy('is_owner', 'desc')
            ->orderBy('joined_at')
            ->get()
            ->map(fn ($m) => [
                'id' => $m->id,
                'account_id' => $m->account_id,
                'name' => $m->account?->getDisplayName() ?? 'Unknown',
                'email' => $m->account?->email,
                'avatar' => $m->account?->profile_image_url,
                'role' => $m->role?->name,
                'role_label' => $m->is_owner ? 'Owner' : ($m->role?->name ? ucfirst($m->role->name) : 'Unknown'),
                'is_owner' => $m->is_owner,
                'status' => $m->status,
                'joined_at' => $m->joined_at?->toDateString(),
            ]);

        $invitations = TeamInvitation::where('tenant_id', $tenant->id)
            ->where('status', 'pending')
            ->with('role')
            ->orderBy('invited_at', 'desc')
            ->get()
            ->map(fn ($i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role?->name,
                'role_label' => ucfirst($i->role?->name ?? 'Unknown'),
                'invited_at' => $i->invited_at?->toDateString(),
                'expires_at' => $i->expires_at?->toDateString(),
                'is_expired' => $i->isExpired(),
            ]);

        $roles = Role::where('tenant_id', $tenant->id)
            ->where('name', '!=', 'customer')
            ->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'label' => ucfirst($r->name)]);

        return Inertia::render('Admin/Team/Index', [
            'members' => $members,
            'invitations' => $invitations,
            'roles' => $roles,
        ]);
    }

    public function members(Request $request)
    {
        if (!auth()->user()->can('users.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();
        $search = $request->input('search');
        $roleFilter = $request->input('role');
        $statusFilter = $request->input('status');

        $query = TenantMembership::where('tenant_id', $tenant->id)
            ->with(['account', 'role']);

        if ($search) {
            $query->whereHas('account', function ($q) use ($search) {
                $q->where('name', 'like', "%{$search}%")
                  ->orWhere('email', 'like', "%{$search}%");
            });
        }

        if ($roleFilter) {
            $query->whereHas('role', fn ($q) => $q->where('name', $roleFilter));
        }

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $members = $query
            ->orderBy('is_owner', 'desc')
            ->orderBy('joined_at')
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($m) => [
                'id' => $m->id,
                'account_id' => $m->account_id,
                'name' => $m->account?->getDisplayName() ?? 'Unknown',
                'email' => $m->account?->email,
                'avatar' => $m->account?->profile_image_url,
                'role' => $m->role?->name,
                'role_label' => $m->is_owner ? 'Owner' : ($m->role?->name ? ucfirst($m->role->name) : 'Unknown'),
                'is_owner' => $m->is_owner,
                'status' => $m->status,
                'joined_at' => $m->joined_at?->toDateString(),
                'last_login_at' => $m->account?->last_login_at?->diffForHumans(),
            ]);

        $roles = Role::where('tenant_id', $tenant->id)
            ->where('name', '!=', 'customer')
            ->get()
            ->map(fn ($r) => ['id' => $r->id, 'name' => $r->name, 'label' => ucfirst($r->name)]);

        return Inertia::render('Admin/Team/Members', [
            'members' => $members,
            'filters' => ['search' => $search, 'role' => $roleFilter, 'status' => $statusFilter],
            'roles' => $roles,
        ]);
    }

    public function showJson(TenantMembership $member)
    {
        if (!auth()->user()->can('users.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if ($member->tenant_id !== $tenant->id) {
            abort(404);
        }

        $member->load(['account', 'role.permissions', 'account.activityLogs']);

        return response()->json([
            'id' => $member->id,
            'account_id' => $member->account_id,
            'name' => $member->account?->getDisplayName() ?? 'Unknown',
            'email' => $member->account?->email,
            'phone' => $member->account?->phone ?? null,
            'avatar' => $member->account?->profile_image_url,
            'role' => $member->role?->name,
            'role_label' => $member->is_owner ? 'Owner' : ($member->role?->name ? ucfirst($member->role->name) : 'Unknown'),
            'is_owner' => $member->is_owner,
            'status' => $member->status,
            'joined_at' => $member->joined_at?->toDateString(),
            'last_login_at' => $member->account?->last_login_at?->diffForHumans(),
            'permissions' => $member->is_owner
                ? ['*']
                : ($member->role?->permissions?->pluck('name')->values()->toArray() ?? []),
            'activity_logs' => $member->account?->activityLogs?->take(20)->map(fn ($log) => [
                'id' => $log->id,
                'description' => $log->description,
                'created_at' => $log->created_at?->diffForHumans(),
            ]) ?? [],
        ]);
    }

    public function invitations(Request $request)
    {
        if (!auth()->user()->can('users.view')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();
        $search = $request->input('search');
        $statusFilter = $request->input('status');

        $query = TeamInvitation::where('tenant_id', $tenant->id)
            ->with(['role', 'inviter']);

        if ($search) {
            $query->where('email', 'like', "%{$search}%");
        }

        if ($statusFilter) {
            $query->where('status', $statusFilter);
        }

        $invitations = $query
            ->orderBy('invited_at', 'desc')
            ->paginate(15)
            ->withQueryString()
            ->through(fn ($i) => [
                'id' => $i->id,
                'email' => $i->email,
                'role' => $i->role?->name,
                'role_label' => ucfirst($i->role?->name ?? 'Unknown'),
                'invited_by' => $i->inviter?->getDisplayName() ?? 'Unknown',
                'invited_at' => $i->invited_at?->toDateString(),
                'expires_at' => $i->expires_at?->toDateString(),
                'is_expired' => $i->isExpired(),
                'status' => $i->status,
            ]);

        return Inertia::render('Admin/Team/Invitations', [
            'invitations' => $invitations,
            'filters' => ['search' => $search, 'status' => $statusFilter],
        ]);
    }

    public function invite(Request $request)
    {
        if (!auth()->user()->can('users.create')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();
        $inviter = auth()->user();

        $validated = $request->validate([
            'email' => 'required|email|max:255',
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($validated['role_id']);

        if ($role->tenant_id !== $tenant->id) {
            return back()->withErrors(['role_id' => 'Invalid role for this store.']);
        }

        if ($role->name === 'customer') {
            return back()->withErrors(['role_id' => 'Cannot invite as customer. Use storefront registration.']);
        }

        $existingMembership = TenantMembership::where('tenant_id', $tenant->id)
            ->whereHas('account', fn ($q) => $q->where('email', $validated['email']))
            ->first();

        if ($existingMembership) {
            return back()->withErrors(['email' => 'This user is already a member of this store.']);
        }

        $existingInvitation = TeamInvitation::where('tenant_id', $tenant->id)
            ->where('email', $validated['email'])
            ->where('status', 'pending')
            ->first();

        if ($existingInvitation && !$existingInvitation->isExpired()) {
            return back()->withErrors(['email' => 'An invitation has already been sent to this email.']);
        }

        if ($existingInvitation && $existingInvitation->isExpired()) {
            $existingInvitation->markRevoked();
        }

        $invitation = TeamInvitation::create([
            'tenant_id' => $tenant->id,
            'role_id' => $role->id,
            'invited_by' => $inviter->id,
            'email' => $validated['email'],
            'token' => TeamInvitation::generateToken(),
            'status' => 'pending',
            'invited_at' => now(),
            'expires_at' => now()->addDays(7),
        ]);

        ActivityLogger::log(
            "Invitation sent to {$validated['email']} as {$role->name}",
            'team.invitation_sent',
            $invitation,
            ['email' => $validated['email'], 'role' => $role->name],
            'team'
        );

        // Send email to invitee
        Mail::to($validated['email'])->queue(new TeamInvitationMail($invitation));

        // Send database notification to inviter (for tracking)
        $inviter->notify(new TeamInvitationNotification($invitation));

        return back()->with('success', "Invitation sent to {$validated['email']}.");
    }

    public function revokeInvitation(TeamInvitation $invitation)
    {
        if (!auth()->user()->can('users.update')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if ($invitation->tenant_id !== $tenant->id) {
            abort(404);
        }

        $invitation->markRevoked();

        ActivityLogger::log(
            "Invitation revoked for {$invitation->email}",
            'team.invitation_revoked',
            $invitation,
            ['email' => $invitation->email],
            'team'
        );

        return back()->with('success', 'Invitation revoked.');
    }

    public function updateRole(Request $request, TenantMembership $member)
    {
        if (!auth()->user()->can('users.update')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if ($member->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($member->is_owner) {
            return back()->withErrors(['member' => 'Cannot change the owner role.']);
        }

        $validated = $request->validate([
            'role_id' => 'required|exists:roles,id',
        ]);

        $role = Role::findOrFail($validated['role_id']);

        if ($role->tenant_id !== $tenant->id) {
            return back()->withErrors(['role_id' => 'Invalid role for this store.']);
        }

        $oldRole = $member->role?->name;

        $member->update(['role_id' => $role->id]);

        ActivityLogger::log(
            "Role changed from {$oldRole} to {$role->name} for {$member->account?->email}",
            'team.role_changed',
            $member,
            ['old_role' => $oldRole, 'new_role' => $role->name, 'email' => $member->account?->email],
            'team'
        );

        return back()->with('success', 'Role updated successfully.');
    }

    public function suspend(TenantMembership $member)
    {
        if (!auth()->user()->can('users.suspend')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if ($member->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($member->is_owner) {
            return back()->withErrors(['member' => 'Cannot suspend the owner.']);
        }

        $member->update(['status' => 'suspended']);

        ActivityLogger::log(
            "Member {$member->account?->email} suspended",
            'team.member_suspended',
            $member,
            ['email' => $member->account?->email],
            'team'
        );

        return back()->with('success', 'Member suspended.');
    }

    public function restore(TenantMembership $member)
    {
        if (!auth()->user()->can('users.activate')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if ($member->tenant_id !== $tenant->id) {
            abort(404);
        }

        $member->update(['status' => 'active']);

        ActivityLogger::log(
            "Member {$member->account?->email} restored",
            'team.member_restored',
            $member,
            ['email' => $member->account?->email],
            'team'
        );

        return back()->with('success', 'Member restored.');
    }

    public function remove(TenantMembership $member)
    {
        if (!auth()->user()->can('users.delete')) {
            abort(403, 'Unauthorized');
        }

        $tenant = Tenant::getCurrent();

        if ($member->tenant_id !== $tenant->id) {
            abort(404);
        }

        if ($member->is_owner) {
            return back()->withErrors(['member' => 'Cannot remove the owner.']);
        }

        $email = $member->account?->email;

        $member->update(['status' => 'removed']);
        $member->delete();

        ActivityLogger::log(
            "Member {$email} removed from staff",
            'team.member_removed',
            null,
            ['email' => $email],
            'team'
        );

        return back()->with('success', 'Member removed from staff.');
    }
}
