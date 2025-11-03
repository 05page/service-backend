<x-mail::message>
# Bonjour {{ $commission->user->fullname ?? 'Cher collaborateur' }},

Votre commission d'un montant de **{{ number_format($commission->commission_due, 0, ',', ' ') }} FCFA**
vient d'être **réglée avec succès**.

<x-mail::panel>
**Détails du paiement :**

- Montant versé : {{ number_format($commission->commission_due, 0, ',', ' ') }} FCFA  
- Date : {{ now()->format('d/m/Y à H:i') }}
</x-mail::panel>

Merci pour votre travail et votre engagement.

Cordialement,  
**L'équipe {{ config('app.name') }}**
</x-mail::message>