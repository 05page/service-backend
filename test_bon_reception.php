<?php

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$app->make('Illuminate\Contracts\Console\Kernel')->bootstrap();

use App\Models\AchatItems;

echo "Recherche d'AchatItems avec bon de réception...\n\n";

$items = AchatItems::whereNotNull('numero_bon_reception')
    ->whereNotNull('date_reception')
    ->whereIn('statut_item', ['recu', 'partiellement_recu'])
    ->with('achat.fournisseur')
    ->take(3)
    ->get();

if ($items->count() > 0) {
    echo "Trouvé {$items->count()} item(s) avec bon de réception:\n\n";
    
    foreach ($items as $item) {
        echo "- ID: {$item->id}\n";
        echo "  Service: {$item->nom_service}\n";
        echo "  Bon N°: {$item->numero_bon_reception}\n";
        echo "  Date: " . ($item->date_reception ? $item->date_reception->format('d/m/Y') : 'N/A') . "\n";
        echo "  Fournisseur: " . ($item->achat->fournisseur->nom_fournisseurs ?? 'N/A') . "\n";
        echo "  Statut: {$item->statut_item}\n";
        echo "  Quantité reçue: {$item->quantite_recu}\n";
        echo "  Prix unitaire: {$item->prix_unitaire} FCFA\n\n";
    }
    
    echo "\n✅ Vous pouvez tester l'API avec l'un de ces IDs.\n";
    echo "Exemple: GET /api/factures/bon-reception/{$items->first()->id}/facture\n";
} else {
    echo "❌ Aucun AchatItem avec bon de réception trouvé.\n";
    echo "Vous devez d'abord créer un achat et enregistrer sa réception.\n";
}
