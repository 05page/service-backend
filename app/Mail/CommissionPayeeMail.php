<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CommissionPayeeMail extends Mailable
{
    use Queueable, SerializesModels;

    public $commission;
    public $commissions; // ✅ Ajout pour paiement groupé
    public $totalMontant; // ✅ Ajout pour paiement groupé
    public $isGrouped; // ✅ Flag pour différencier les deux cas

    /**
     * Create a new message instance.
     * 
     * @param mixed $commissionOrCommissions Commission unique ou collection de commissions
     * @param float|null $totalMontant Montant total pour paiement groupé
     */
    public function __construct($commissionOrCommissions, $totalMontant = null)
    {
        if ($commissionOrCommissions instanceof \Illuminate\Database\Eloquent\Collection) {
            // Paiement groupé
            $this->isGrouped = true;
            $this->commissions = $commissionOrCommissions;
            $this->totalMontant = $totalMontant;
            $this->commission = $commissionOrCommissions->first(); // Pour accéder au user
        } else {
            // Paiement individuel
            $this->isGrouped = false;
            $this->commission = $commissionOrCommissions;
            $this->commissions = collect([$commissionOrCommissions]);
            $this->totalMontant = $commissionOrCommissions->commission_due;
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $subject = $this->isGrouped 
            ? "🎉 Paiement groupé de " . $this->commissions->count() . " commission(s) - " . number_format($this->totalMontant, 0, ',', ' ') . " Fcfa"
            : "Votre commission a été payée - " . number_format($this->commission->commission_due, 0, ',', ' ') . " Fcfa";

        return new Envelope(
            subject: $subject,
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.commissions.payee',
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