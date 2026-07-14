<?php

namespace App\Mail;

use App\Models\PlatformSetting;
use App\Models\TeamInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class TeamInvitationMail extends Mailable implements ShouldQueue
{
    use Queueable, SerializesModels;

    public function __construct(
        public TeamInvitation $invitation,
    ) {}

    public function envelope(): Envelope
    {
        $storeName = $this->invitation->tenant->name;

        return new Envelope(
            subject: __("You've been invited to join :store", ['store' => $storeName]),
        );
    }

    public function content(): Content
    {
        $invitation = $this->invitation;
        $tenant = $invitation->tenant;
        $inviter = $invitation->inviter;
        $platform = PlatformSetting::current();

        $logoUrl = $platform->site_logo ? $platform->site_logo_url : null;

        return new Content(
            view: 'mail.invitation',
            with: [
                'storeName' => $tenant->name,
                'storeEmail' => $tenant->email ?? $platform->support_email ?? config('mail.from.address'),
                'storeSlug' => $tenant->slug,
                'logoUrl' => $logoUrl,
                'email' => $invitation->email,
                'inviteeName' => null,
                'inviterName' => $inviter?->getDisplayName() ?? __('Store Owner'),
                'roleLabel' => ucfirst($invitation->role->name),
                'acceptUrl' => $invitation->getAcceptUrl(),
                'expiresAt' => $invitation->expires_at?->format('M j, Y g:i A') ?? __('7 days from now'),
            ],
        );
    }

    public function attachments(): array
    {
        return [];
    }
}
