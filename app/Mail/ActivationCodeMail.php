<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Http\Controllers\EmployeIntermediaireController;
use App\Models\EmployeIntermediaire;

use function PHPSTORM_META\type;

class ActivationCodeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $employeIntermediaire;
    /**
     * Create a new message instance.
     */
    public function __construct(EmployeIntermediaire $employeIntermediaire)
    {
        //
        $this->employeIntermediaire = $employeIntermediaire;
    }



    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Code d\'activation de votre compte',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.activation-code',
            with:[
                    'employeIntermediaire'=> $this->employeIntermediaire,
                    'code'=> $this->employeIntermediaire->code_activation,
                    'type'=> $this->employeIntermediaire->type
                ]
            );

    }

    /**
     * Get the attachments for the message.
     *
     * @return array<int, \Illuminate\Mail\Mailables\Attachment>
     */
    public function attachments(): array
    {
        return [];
    }
}
