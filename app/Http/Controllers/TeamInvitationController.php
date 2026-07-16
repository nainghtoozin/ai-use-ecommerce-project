<?php

namespace App\Http\Controllers;

use App\Http\Controllers\Controller;
use App\Models\Account;
use App\Models\TenantMembership;
use App\Models\TeamInvitation;
use App\Services\ActivityLogger;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Inertia\Inertia;
use Inertia\Response as InertiaResponse;

class TeamInvitationController extends Controller
{
    public function show(string $token): InertiaResponse
    {
        $invitation = TeamInvitation::where('token', $token)
            ->with(['tenant', 'role'])
            ->firstOrFail();

        if (!$invitation->isPending()) {
            return Inertia::render('Public/InvitationExpired', [
                'message' => $invitation->isExpired()
                    ? 'This invitation has expired.'
                    : 'This invitation is no longer valid.',
            ]);
        }

        $existingAccount = Account::where('email', $invitation->email)->first();

        // Check if already a member
        if ($existingAccount) {
            $existingMembership = TenantMembership::where('tenant_id', $invitation->tenant_id)
                ->where('account_id', $existingAccount->id)
                ->first();

            if ($existingMembership && !$existingMembership->trashed()) {
                return Inertia::render('Public/InvitationExpired', [
                    'message' => 'You already belong to this store.',
                ]);
            }
        }

        return Inertia::render('Public/AcceptInvitation', [
            'invitation' => [
                'token' => $invitation->token,
                'email' => $invitation->email,
                'store_name' => $invitation->tenant->name,
                'store_slug' => $invitation->tenant->slug,
                'role' => $invitation->role->name,
                'role_label' => ucfirst($invitation->role->name),
                'inviter' => $invitation->inviter?->getDisplayName() ?? 'Store Owner',
            ],
            'existing_account' => $existingAccount ? true : false,
        ]);
    }

    public function accept(Request $request, string $token): RedirectResponse
    {
        $invitation = TeamInvitation::where('token', $token)
            ->with(['tenant', 'role'])
            ->firstOrFail();

        if (!$invitation->isPending()) {
            return redirect()->route('storefront.team.invite.show', [
                'store_slug' => $invitation->tenant->slug,
                'token' => $token,
            ])->withErrors(['token' => 'This invitation is no longer valid.']);
        }

        $existingAccount = Account::where('email', $invitation->email)->first();

        if ($existingAccount) {
            return $this->acceptExistingAccount($request, $invitation, $existingAccount);
        }

        return $this->acceptNewAccount($request, $invitation);
    }

    /**
     * CASE 2: Account already exists.
     * Verify password, then create membership only.
     */
    protected function acceptExistingAccount(Request $request, TeamInvitation $invitation, Account $account): RedirectResponse
    {
        // Check for duplicate membership
        $existingMembership = TenantMembership::where('tenant_id', $invitation->tenant_id)
            ->where('account_id', $account->id)
            ->first();

        if ($existingMembership && !$existingMembership->trashed()) {
            return back()->withErrors([
                'password' => 'You already belong to this store.',
            ]);
        }

        // Validate password only
        $request->validate([
            'password' => 'required|string',
        ]);

        // Verify password BEFORE consuming invitation
        if (!Hash::check($request->password, $account->password)) {
            return back()->withErrors([
                'password' => 'The provided password is incorrect.',
            ])->withInput();
        }

        // Password correct — complete invitation
        return $this->completeInvitation($request, $invitation, $account, [
            'restore_membership' => $existingMembership?->trashed() ? $existingMembership : null,
        ]);
    }

    /**
     * CASE 1: Account does not exist.
     * Create account, then create membership.
     */
    protected function acceptNewAccount(Request $request, TeamInvitation $invitation): RedirectResponse
    {
        $request->validate([
            'name' => 'required|string|max:255',
            'password' => 'required|confirmed|min:8',
        ]);

        $account = Account::create([
            'name' => $request->name,
            'email' => $invitation->email,
            'password' => Hash::make($request->password),
            'status' => Account::STATUS_ACTIVE,
            'email_verified_at' => now(),
        ]);

        return $this->completeInvitation($request, $invitation, $account, [
            'new_account' => true,
        ]);
    }

    /**
     * Shared logic: create membership, mark accepted, login, redirect.
     */
    protected function completeInvitation(
        Request $request,
        TeamInvitation $invitation,
        Account $account,
        array $options = []
    ): RedirectResponse {
        // Restore soft-deleted membership or create new
        if (!empty($options['restore_membership'])) {
            $options['restore_membership']->restore();
            $options['restore_membership']->update([
                'status' => 'active',
                'role_id' => $invitation->role_id,
            ]);
        } else {
            TenantMembership::create([
                'account_id' => $account->id,
                'tenant_id' => $invitation->tenant_id,
                'role_id' => $invitation->role_id,
                'is_owner' => false,
                'status' => 'active',
                'invited_by' => $invitation->invited_by,
                'invited_at' => $invitation->invited_at,
                'joined_at' => now(),
            ]);
        }

        // Mark invitation accepted ONLY after success
        $invitation->markAccepted();

        // Log activity
        ActivityLogger::log(
            'Invitation accepted by ' . $account->email
                . (!empty($options['new_account']) ? ' (new account)' : ''),
            'team.invitation_accepted',
            $invitation,
            [
                'email' => $account->email,
                'role' => $invitation->role->name,
                'new_account' => !empty($options['new_account']),
            ],
            'team'
        );

        // Login and redirect to invited tenant
        Auth::guard('accounts')->login($account);
        $request->session()->regenerate();
        $request->session()->put('current_tenant_slug', $invitation->tenant->slug);

        return redirect()
            ->route('storefront.admin.dashboard', ['store_slug' => $invitation->tenant->slug])
            ->with('success', 'Welcome to ' . $invitation->tenant->name . '!');
    }
}
