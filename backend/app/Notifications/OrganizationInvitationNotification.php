<?php

namespace App\Notifications;

use App\Models\OrganizationInvitation;
use Illuminate\Bus\Queueable;
use Illuminate\Notifications\Notification;

class OrganizationInvitationNotification extends Notification
{
    use Queueable;

    public function __construct(private OrganizationInvitation $invitation) {}

    public function via(): array
    {
        return ['mail'];
    }

    /** @return array<string, mixed> */
    public function toArray(): array
    {
        return ['invitation_id' => $this->invitation->id];
    }

    /** @return array<string, mixed> */
    public function toMail(): array
    {
        return [
            'subject' => 'Lời mời tham gia tổ chức',
            'mailer' => 'smtp',
            'data' => ['token' => $this->invitation->token],
        ];
    }
}
