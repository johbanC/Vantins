<?php

namespace App\Mail;

use App\Models\Application;
use Barryvdh\DomPDF\Facade\Pdf;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Attachment;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class WelcomeLetterMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public Application $application)
    {
        // Renders in the client's own language: content(), envelope() and
        // attachments() all run after this locale is applied.
        $this->locale($application->locale ?: 'en');
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: __('mail.welcome_letter_subject'),
        );
    }

    public function content(): Content
    {
        return new Content(
            view: 'mail.welcome-letter',
            with: [
                'recipient' => $this->application->recipientName(),
                'logo' => $this->logoDataUri(),
            ],
        );
    }

    /**
     * Embed the logo as a data URI instead of linking public_path(): most inbox
     * clients render it fine and, unlike an external URL, it never depends on
     * the app being reachable when the message is opened.
     */
    private function logoDataUri(): string
    {
        $path = public_path('images/brand/logo-white.png');

        return 'data:image/png;base64,'.base64_encode(file_get_contents($path));
    }

    public function attachments(): array
    {
        $pdf = Pdf::loadView('pdf.welcome-letter', [
            'application' => $this->application,
            'recipient' => $this->application->recipientName(),
            'sentAt' => $this->application->welcome_letter_sent_at ?: now(),
        ])->output();

        return [
            Attachment::fromData(fn () => $pdf, 'Vantins-welcome-letter.pdf')
                ->withMime('application/pdf'),
        ];
    }
}
