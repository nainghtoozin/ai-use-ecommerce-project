<?php

namespace App\Notifications;

use App\Models\PlatformSetting;
use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Notifications\Notification;

class TeamInvitationNotification extends Notification implements ShouldQueue
{
    use Queueable;

    public function __construct(
        public TeamInvitation $invitation,
    ) {}

    public function via(object $notifiable): array
    {
        return ['database'];
    }

    public function toArray(object $notifiable): array
    {
        $invitation = $this->invitation;
        $tenant = $invitation->tenant;
        $inviter = $invitation->inviter;

        return [
            'title' => __("Invitation sent to :email", ['email' => $invitation->email]),
            'message' => __('You invited :email to join :store as :role.', [
                'email' => $invitation->email,
                'store' => $tenant->name,
                'role' => ucfirst($invitation->role->name),
            ]),
            'invitation_id' => $invitation->id,
            'tenant_id' => $tenant->id,
            'tenant_name' => $tenant->name,
            'tenant_slug' => $tenant->slug,
            'invitee_email' => $invitation->email,
            'role' => $invitation->role->name,
            'role_label' => ucfirst($invitation->role->name),
            'accept_url' => $invitation->getAcceptUrl(),
            'expires_at' => $invitation->expires_at?->toIso8601String(),
            'action_url' => route('storefront.admin.team.index', ['store_slug' => $tenant->slug]),
            'action_label' => __('View Staff'),
            'status' => $invitation->status,
        ];
    }
}
