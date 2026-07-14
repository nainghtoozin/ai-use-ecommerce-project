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
            return $this->acceptForExistingAccount($request, $invitation, $existingAccount);
        }

        return $this->acceptForNewAccount($request, $invitation);
    }

    protected function acceptForExistingAccount(Request $request, TeamInvitation $invitation, Account $account): RedirectResponse
    {
        $existingMembership = TenantMembership::where('tenant_id', $invitation->tenant_id)
            ->where('account_id', $account->id)
            ->first();

        if ($existingMembership && !$existingMembership->trashed()) {
            $invitation->markAccepted();

            ActivityLogger::log(
                "Invitation accepted by {$account->email} (already a member)",
                'team.invitation_accepted',
                $invitation,
                ['email' => $account->email, 'existing_member' => true],
                'team'
            );

            $this->loginAndRedirect($request, $account, $invitation);

            return redirect()->route('storefront.admin.dashboard', ['store_slug' => $invitation->tenant->slug])
                ->with('success', 'You are already a member of this store.');
        }

        if ($existingMembership && $existingMembership->trashed()) {
            $existingMembership->restore();
            $existingMembership->update([
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

        $invitation->markAccepted();

        ActivityLogger::log(
            "Invitation accepted by {$account->email}",
            'team.invitation_accepted',
            $invitation,
            ['email' => $account->email, 'role' => $invitation->role->name],
            'team'
        );

        $this->loginAndRedirect($request, $account, $invitation);

        return redirect()->route('storefront.admin.dashboard', ['store_slug' => $invitation->tenant->slug])
            ->with('success', "Welcome to {$invitation->tenant->name}!");
    }

    protected function acceptForNewAccount(Request $request, TeamInvitation $invitation): RedirectResponse
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

        $invitation->markAccepted();

        ActivityLogger::log(
            "Invitation accepted by {$account->email} (new account created)",
            'team.invitation_accepted',
            $invitation,
            ['email' => $account->email, 'role' => $invitation->role->name, 'new_account' => true],
            'team'
        );

        $this->loginAndRedirect($request, $account, $invitation);

        return redirect()->route('storefront.admin.dashboard', ['store_slug' => $invitation->tenant->slug])
            ->with('success', "Welcome to {$invitation->tenant->name}!");
    }

    protected function loginAndRedirect(Request $request, Account $account, TeamInvitation $invitation): void
    {
        Auth::guard('accounts')->login($account);
        $request->session()->regenerate();
        $request->session()->put('current_tenant_slug', $invitation->tenant->slug);
    }
}
