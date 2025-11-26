<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <title>Bon de Commande #{{ $achat->numero_achat }}</title>
    <style>
        /* ... Vos styles existants ... */
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 11px;
            color: #333;
            line-height: 1.3;
            position: relative;
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 15px;
            position: relative;
        }

        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 70px;
            font-weight: bold;
            z-index: -1;
            white-space: nowrap;
            pointer-events: none;
            color: rgba(59, 130, 246, 0.05);
        }

        .header {
            margin-bottom: 25px;
            overflow: hidden;
        }

        .achat-title {
            float: left;
            width: 50%;
        }

        .achat-title h1 {
            font-size: 28px;
            color: #3b82f6;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .facture-numero {
            font-size: 13px;
            color: #666;
            margin-bottom: 4px;
        }

        .facture-date {
            font-size: 11px;
            color: #666;
        }

        .entreprise-info {
            float: right;
            width: 45%;
            text-align: right;
        }

        .entreprise-info h2 {
            font-size: 15px;
            color: #1f2937;
            font-weight: bold;
            margin-bottom: 8px;
        }

        .entreprise-info p {
            margin-bottom: 2px;
            font-size: 10px;
        }

        .entreprise-siret {
            font-weight: bold;
            margin-top: 4px;
        }

        .billing-section {
            margin: 30px 0;
            overflow: hidden;
        }

        .billing-info {
            width: 48%;
            display: inline-block;
            vertical-align: top;
            border: 1px solid #e5e7eb;
            padding: 12px;
            border-radius: 4px;
        }

        .billing-left {
            margin-right: 4%;
        }

        .billing-info h3 {
            font-size: 13px;
            font-weight: bold;
            margin-bottom: 8px;
            color: #3b82f6;
        }

        .billing-info p {
            margin-bottom: 2px;
            font-size: 10px;
        }

        .fournisseur-name {
            font-size: 12px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 6px;
        }

        .articles-section {
            margin: 20px 0;
        }

        .articles-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 15px;
        }

        .articles-table th {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 10px 6px;
            text-align: left;
            font-weight: bold;
            font-size: 10px;
            color: #495057;
        }

        .articles-table td {
            border: 1px solid #dee2e6;
            padding: 8px 6px;
            font-size: 10px;
        }

        .articles-table tbody tr:nth-child(even) {
            background-color: #f8f9fa;
        }

        .text-right {
            text-align: right;
        }

        .text-center {
            text-align: center;
        }

        .totaux-section {
            margin-top: 20px;
            overflow: hidden;
        }

        .totaux-table {
            float: right;
            width: 280px;
        }

        .totaux-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .totaux-table td {
            padding: 6px 10px;
            border: 1px solid #dee2e6;
            font-size: 11px;
        }

        .totaux-table .total-label {
            font-weight: bold;
            background-color: #f8f9fa;
        }

        .totaux-table .total-final-achat {
            background-color: #3b82f6;
            color: white;
            font-weight: bold;
            font-size: 13px;
        }

        .description-section {
            margin: 20px 0;
            padding: 12px;
            background-color: #f8f9fa;
            border-left: 4px solid #3b82f6;
            border-radius: 4px;
        }

        .description-section h3 {
            font-size: 12px;
            color: #3b82f6;
            margin-bottom: 6px;
        }

        .description-section p {
            font-size: 10px;
            line-height: 1.5;
        }

        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #666;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }

        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .prix-amount {
            font-weight: bold;
            color: #3b82f6;
        }

        .item-details {
            font-size: 9px;
            color: #666;
            margin-top: 2px;
        }

        .debug-section {
            background: #fff3cd;
            border: 1px solid #ffc107;
            padding: 10px;
            margin: 10px 0;
            font-size: 9px;
        }
    </style>
</head>

<body>
    <?php
    // ✅ DEBUG: Logger les données reçues dans le template
    \Log::info('📄 Données reçues dans bon_commande.blade.php:', [
        'achat_existe' => isset($achat) ? 'OUI' : 'NON',
        'achat_id' => $achat->id ?? 'NULL',
        'numero_achat' => $achat->numero_achat ?? 'NULL',
        'items_existe' => isset($achat->items) ? 'OUI' : 'NON',
        'items_count' => isset($achat->items) ? $achat->items->count() : 0,
        'fournisseur_existe' => isset($achat->fournisseur) ? 'OUI' : 'NON',
    ]);
    ?>

    <!-- Filigrane -->
    <div class="watermark">BON DE COMMANDE</div>

    <div class="container">
        <!-- En-tête -->
        <div class="header clearfix">
            <div class="achat-title">
                <h1>BON DE COMMANDE</h1>
                <p class="facture-numero">N° {{ $achat->numero_achat ?? 'N/A' }}</p>
                <p class="facture-date">Date: {{ $achat->created_at->format('d/m/Y') }}</p>
            </div>

            <div class="entreprise-info">
                <h2>{{ config('app.name') }}</h2>
                <p>Adresse de votre entreprise</p>
                <p>Ville, Code Postal</p>
                <p>Tél: +225 XX XX XX XX XX</p>
                <p>Email: contact@entreprise.com</p>
                <p class="entreprise-siret">SIRET: XXX XXX XXX XXXXX</p>
            </div>
        </div>

        <!-- Informations fournisseur -->
        <div class="billing-section clearfix">
            <div class="billing-info billing-left">
                <h3>FOURNISSEUR</h3>
                @if(isset($achat->fournisseur))
                    <p class="fournisseur-name">{{ $achat->fournisseur->nom_fournisseurs ?? 'Non défini' }}</p>
                    @if($achat->fournisseur->adresse)
                    <p>{{ $achat->fournisseur->adresse }}</p>
                    @endif
                    @if($achat->fournisseur->telephone)
                    <p>Tél: {{ $achat->fournisseur->telephone }}</p>
                    @endif
                    @if($achat->fournisseur->email)
                    <p>Email: {{ $achat->fournisseur->email }}</p>
                    @endif
                @else
                    <p class="fournisseur-name">Fournisseur non chargé</p>
                @endif
            </div>

            <div class="billing-info">
                <h3>STATUT DE LA COMMANDE</h3>
                <p><strong>État:</strong> {{ ucfirst(str_replace('_', ' ', $achat->statut ?? 'En cours')) }}</p>
                <p><strong>Créé par:</strong> {{ $achat->creePar->fullname ?? 'Système' }}</p>
                <p><strong>Date de création:</strong> {{ $achat->created_at->format('d/m/Y H:i') }}</p>
            </div>
        </div>

        <!-- Tableau des articles -->
        <div class="articles-section">
            <table class="articles-table">
                <thead>
                    <tr>
                        <th style="width: 45%;">Produit / Service</th>
                        <th class="text-center" style="width: 15%;">Quantité</th>
                        <th class="text-right" style="width: 20%;">Prix unitaire</th>
                        <th class="text-right" style="width: 20%;">Prix total</th>
                    </tr>
                </thead>
                <tbody>
                    @php
                    $totalGeneral = 0;
                    @endphp

                    @if(isset($achat->items) && $achat->items->count() > 0)
                        @foreach($achat->items as $item)
                        <tr>
                            <td>
                                <strong>{{ $item->nom_service ?? 'Non défini' }}</strong>
                                <div class="item-details">
                                    @if($item->date_commande)
                                    Commandé le: {{ \Carbon\Carbon::parse($item->date_commande)->format('d/m/Y') }}
                                    @endif
                                    @if($item->date_livraison)
                                    <br>Livraison prévue: {{ \Carbon\Carbon::parse($item->date_livraison)->format('d/m/Y') }}
                                    @endif
                                </div>
                            </td>
                            <td class="text-center">{{ $item->quantite ?? 0 }}</td>
                            <td class="text-right prix-amount">{{ number_format($item->prix_unitaire ?? 0, 0, ',', ' ') }} FCFA</td>
                            <td class="text-right prix-amount">{{ number_format($item->prix_total ?? 0, 0, ',', ' ') }} FCFA</td>
                        </tr>
                        @php
                        $totalGeneral += ($item->prix_total ?? 0);
                        @endphp
                        @endforeach
                    @else
                        <tr>
                            <td colspan="4" class="text-center" style="padding: 20px; color: #999;">
                                 Aucun article dans cette commande (Items non chargés)
                            </td>
                        </tr>
                    @endif
                </tbody>
            </table>
        </div>

        <!-- Description -->
        @if(isset($achat->description) && $achat->description)
        <div class="description-section">
            <h3>Description / Remarques</h3>
            <p>{{ $achat->description }}</p>
        </div>
        @endif

        <!-- Totaux -->
        <div class="totaux-section clearfix">
            <div class="totaux-table">
                <table>
                    <tr>
                        <td class="total-label">TOTAL HT</td>
                        <td class="text-right">{{ number_format($totalGeneral, 0, ',', ' ') }} FCFA</td>
                    </tr>
                    <tr>
                        <td class="total-label">TVA (0%)</td>
                        <td class="text-right">0 FCFA</td>
                    </tr>
                    <tr>
                        <td class="total-final-achat">TOTAL TTC</td>
                        <td class="text-right total-final-achat">{{ number_format($totalGeneral, 0, ',', ' ') }} FCFA</td>
                    </tr>
                </table>
            </div>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p><strong>Conditions générales:</strong> Paiement à réception de facture. Livraison selon les délais convenus.</p>
            <p>{{ config('app.name') }} - Tous droits réservés © {{ date('Y') }}</p>
            <p>Document généré automatiquement le {{ now()->format('d/m/Y à H:i') }}</p>
        </div>
    </div>
</body>
</html>