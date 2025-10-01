<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\User;

class ActivationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $user;
    public $activationLink;

    /**
     * Create a new message instance.
     */
    public function __construct(User $user)
    {
        $this->user = $user;

        // Lien vers le frontend React (sans exposer le code)
        $this->activationLink = config('app.frontend_url') . '/code';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Activation de votre compte',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.activation-code',
            with: [
                'user' => $this->user,
                'employeIntermediaire' => $this->user,
                'type' => $this->user->type,
                'activationLink' => $this->activationLink
            ]
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
