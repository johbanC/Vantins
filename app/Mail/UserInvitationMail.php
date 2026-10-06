<?php

namespace App\Mail;

use App\Models\User;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class UserInvitationMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public User $user, public string $url, public int $validDays) {}

    public function envelope(): Envelope
    {
        return new Envelope(subject: __('mail.invitation_subject', [], 'es').' / '.__('mail.invitation_subject', [], 'en'));
    }

    public function content(): Content
    {
        return new Content(view: 'mail.user-invitation');
    }
}
