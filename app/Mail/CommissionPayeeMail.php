<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;
use App\Models\Commission;

// ✅ ENLEVEZ "implements ShouldQueue"
class CommissionPayeeMail extends Mailable
{
    use Queueable, SerializesModels;
    
    public $commission;

    public function __construct(Commission $commission)
    {
        $this->commission = $commission;
    }

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'Commission Payée avec succès',
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'emails.commissions.payee',
        );
    }

    public function attachments(): array
    {
        return [];
    }
}