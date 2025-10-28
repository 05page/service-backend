{{-- resources/views/factures/document-pdf.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        @if(isset($type_document) && $type_document === 'recu')
            Reçu {{ $recu->numero_recu }}
        @else
            Facture {{ $facture->numero_facture }}
        @endif
    </title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            font-size: 12px;
            color: #333;
            line-height: 1.4;
        }

        .container {
            width: 100%;
            max-width: 800px;
            margin: 0 auto;
            padding: 20px;
        }

        /* Badge REÇU (seulement pour les reçus) */
        .document-badge {
            position: absolute;
            top: 20px;
            right: 30px;
            padding: 10px 25px;
            font-size: 20px;
            font-weight: bold;
            border-radius: 5px;
            background-color: #f59e0b;
            color: white;
            text-transform: uppercase;
        }

        /* Watermark pour REÇU */
        .watermark {
            position: fixed;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%) rotate(-45deg);
            font-size: 120px;
            color: rgba(245, 158, 11, 0.1);
            font-weight: bold;
            z-index: -1;
            pointer-events: none;
        }

        /* En-tête */
        .header {
            margin-bottom: 30px;
            overflow: hidden;
            position: relative;
        }

        .facture-title {
            float: left;
            width: 50%;
        }

        .facture-title h1 {
            font-size: 32px;
            color: #2563eb;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .facture-numero {
            font-size: 14px;
            color: #666;
            margin-bottom: 5px;
        }

        .facture-date {
            font-size: 12px;
            color: #666;
        }

        .entreprise-info {
            float: right;
            width: 45%;
            text-align: right;
        }

        .entreprise-info h2 {
            font-size: 16px;
            color: #2563eb;
            font-weight: bold;
            margin-bottom: 10px;
        }

        .entreprise-info p {
            margin-bottom: 3px;
            font-size: 11px;
        }

        .entreprise-siret {
            font-weight: bold;
            margin-top: 5px;
        }

        /* Section client/fournisseur */
        .billing-section {
            margin: 40px 0;
            overflow: hidden;
        }

        .billing-info {
            width: 48%;
            display: inline-block;
            vertical-align: top;
            border: 1px solid #e5e7eb;
            padding: 15px;
            border-radius: 4px;
        }

        .billing-left {
            margin-right: 4%;
        }

        .billing-info h3 {
            font-size: 14px;
            font-weight: bold;
            margin-bottom: 10px;
            color: #2563eb;
        }

        .billing-info h3.intermediaire {
            color: #f59e0b;
        }

        .billing-info p {
            margin-bottom: 3px;
            font-size: 11px;
        }

        .client-name,
        .fournisseur-name {
            font-size: 13px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 8px;
        }

        .commission {
            color: #f59e0b;
            font-weight: bold;
        }

        /* Encadré paiement (pour REÇU uniquement) */
        .payment-box {
            background-color: #fef3c7;
            border: 2px solid #f59e0b;
            padding: 20px;
            margin: 20px 0;
            border-radius: 8px;
        }

        .payment-box h3 {
            color: #f59e0b;
            font-size: 16px;
            margin-bottom: 15px;
            text-align: center;
            font-weight: bold;
        }

        .payment-details {
            width: 100%;
        }

        .payment-row {
            overflow: hidden;
            padding: 8px 0;
            border-bottom: 1px solid #fde68a;
        }

        .payment-row:last-child {
            border-bottom: none;
        }

        .payment-label {
            float: left;
            width: 60%;
            font-weight: bold;
            color: #78350f;
        }

        .payment-value {
            float: right;
            width: 40%;
            text-align: right;
            font-size: 14px;
            font-weight: bold;
            color: #92400e;
        }

        .payment-highlight {
            background-color: #fbbf24;
            padding: 10px;
            margin-top: 10px;
            border-radius: 5px;
        }

        .payment-highlight .payment-label {
            color: #78350f;
        }

        .payment-highlight .payment-value {
            font-size: 18px;
            color: #78350f;
        }

        .progress-bar-container {
            margin-top: 15px;
            background-color: #fde68a;
            height: 25px;
            border-radius: 12px;
            overflow: hidden;
            position: relative;
        }

        .progress-bar {
            background-color: #059669;
            height: 100%;
            transition: width 0.3s ease;
            float: left;
        }

        .progress-text {
            position: absolute;
            top: 50%;
            left: 50%;
            transform: translate(-50%, -50%);
            font-weight: bold;
            color: #1f2937;
            font-size: 12px;
        }

        /* Tableau des articles */
        .articles-section {
            margin: 30px 0;
        }

        .articles-table {
            width: 100%;
            border-collapse: collapse;
            margin-bottom: 20px;
        }

        .articles-table th {
            background-color: #f8f9fa;
            border: 1px solid #dee2e6;
            padding: 12px 8px;
            text-align: left;
            font-weight: bold;
            font-size: 11px;
            color: #495057;
        }

        .articles-table td {
            border: 1px solid #dee2e6;
            padding: 10px 8px;
            font-size: 11px;
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

        /* Totaux */
        .totaux-section {
            margin-top: 30px;
            overflow: hidden;
        }

        .totaux-table {
            float: right;
            width: 300px;
        }

        .totaux-table table {
            width: 100%;
            border-collapse: collapse;
        }

        .totaux-table td {
            padding: 8px 12px;
            border: 1px solid #dee2e6;
            font-size: 12px;
        }

        .totaux-table .total-label {
            font-weight: bold;
            background-color: #f8f9fa;
        }

        .totaux-table .total-final {
            background-color: #2563eb;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        .totaux-table .total-verse {
            background-color: #f59e0b;
            color: white;
            font-weight: bold;
        }

        .totaux-table .total-reste {
            background-color: #dc2626;
            color: white;
            font-weight: bold;
            font-size: 14px;
        }

        /* Footer */
        .footer {
            margin-top: 50px;
            text-align: center;
            font-size: 10px;
            color: #666;
            border-top: 1px solid #e5e7eb;
            padding-top: 15px;
        }

        /* Responsive pour DomPDF */
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        /* Styles spécifiques selon le type */
        .facture-vente .billing-info h3 {
            color: #059669;
        }

        .facture-achat .billing-info h3 {
            color: #dc2626;
        }

        .prix-amount {
            font-weight: bold;
            color: #059669;
        }
    </style>
</head>

<body>
    {{-- Watermark pour REÇU --}}
    @if(isset($type_document) && $type_document === 'recu')
    <div class="watermark">REÇU</div>
    <div class="document-badge">REÇU</div>
    @endif

    <div class="container">

        {{-- EN-TÊTE --}}
        <div class="header clearfix">
            <div class="facture-title">
                @if(isset($type_document) && $type_document === 'recu')
                    <h1>REÇU</h1>
                    <div class="facture-numero">N° {{ $recu->numero_recu }}</div>
                    <div class="facture-date">Date: {{ $date_generation }}</div>
                    <div class="facture-date">Référence vente: {{ $vente->reference }}</div>
                @else
                    <h1>FACTURE</h1>
                    <div class="facture-numero">N° {{ $facture->numero_facture }}</div>
                    <div class="facture-date">Date: {{ $date_generation }}</div>
                @endif
            </div>

            <div class="entreprise-info">
                <h2>{{ $entreprise['nom'] }}</h2>
                <p>{{ $entreprise['adresse'] }}</p>
                <p>{{ $entreprise['ville'] }}</p>
                <p>Tél: {{ $entreprise['telephone'] }}</p>
                <p>Email: {{ $entreprise['email'] }}</p>
                <div class="entreprise-siret">SIRET: {{ $entreprise['siret'] }}</div>
            </div>
        </div>

        {{-- ENCADRÉ PAIEMENT (uniquement pour REÇU) --}}
        @if(isset($type_document) && $type_document === 'recu' && isset($paiement))
        <div class="payment-box">
            <h3>📋 DÉTAILS DU PAIEMENT</h3>
            
            <div class="payment-details clearfix">
                <div class="payment-row clearfix">
                    <div class="payment-label">Montant total de la vente:</div>
                    <div class="payment-value">{{ number_format($totaux['montant_total'], 0, ',', ' ') }} Fcfa</div>
                </div>
                <div class="payment-row clearfix">
                    <div class="payment-label">Montant de ce paiement:</div>
                    <div class="payment-value" style="color: #059669;">
                        + {{ number_format($paiement['montant_paye'], 0, ',', ' ') }} Fcfa
                    </div>
                </div>
                <div class="payment-row clearfix">
                    <div class="payment-label">Total déjà versé:</div>
                    <div class="payment-value">{{ number_format($paiement['montant_cumule'], 0, ',', ' ') }} Fcfa</div>
                </div>
                <div class="payment-highlight clearfix">
                    <div class="payment-label">RESTE À PAYER:</div>
                    <div class="payment-value">{{ number_format($paiement['reste_a_payer'], 0, ',', ' ') }} Fcfa</div>
                </div>
            </div>

            {{-- Barre de progression --}}
            <div class="progress-bar-container clearfix">
                <div class="progress-bar" style="width: {{ $paiement['pourcentage_paye'] }}%;"></div>
                <div class="progress-text">{{ number_format($paiement['pourcentage_paye'], 1) }}% payé</div>
            </div>
        </div>
        @endif

        {{-- TABLEAU DES ARTICLES --}}
        <div class="articles-section">
            <table class="articles-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Description</th>
                        @if(isset($type_facture) && $type_facture === 'vente')
                        <th style="width: 20%;">Code produit</th>
                        @elseif(isset($type_document) && $type_document === 'recu')
                        <th style="width: 20%;">Code produit</th>
                        @else
                        <th style="width: 20%;">N° Achat</th>
                        @endif
                        <th style="width: 15%;" class="text-center">Quantité</th>
                        <th style="width: 20%;" class="text-right">Prix unitaire HT</th>
                        <th style="width: 25%;" class="text-right">Total HT</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($articles as $article)
                    <tr>
                        <td>
                            <strong>{{ $article['description'] }}</strong>
                        </td>
                        <td>
                            @if((isset($type_facture) && $type_facture === 'vente') || (isset($type_document) && $type_document === 'recu'))
                                @if(isset($article['code']) && !empty($article['code']))
                                <strong>{{ $article['code'] }}</strong>
                                @endif
                            @else
                                @if(isset($article['numero_achat']) && !empty($article['numero_achat']))
                                <strong>{{ $article['numero_achat'] }}</strong>
                                @endif
                            @endif
                        </td>

                        <td class="text-center">{{ $article['quantite'] }}</td>
                        <td class="text-right">{{ number_format($article['prix_unitaire'], 2, ',', ' ') }} Fcfa</td>
                        <td class="text-right prix-amount">{{ number_format($article['total'], 2, ',', ' ') }} Fcfa</td>
                    </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        {{-- SECTION TOTAUX --}}
        <div class="totaux-section clearfix">
            <div class="totaux-table">
                <table>
                    <tr>
                        <td class="total-label">Sous-total HT:</td>
                        <td class="text-right">{{ number_format($totaux['sous_total'], 2, ',', ' ') }} Fcfa</td>
                    </tr>
                    
                    @if(isset($type_document) && $type_document === 'recu')
                    {{-- REÇU : Paiement partiel --}}
                    <tr class="total-verse">
                        <td><strong>MONTANT VERSÉ:</strong></td>
                        <td class="text-right"><strong>{{ number_format($totaux['montant_verse'], 2, ',', ' ') }} Fcfa</strong></td>
                    </tr>
                    <tr class="total-reste">
                        <td><strong>RESTE À PAYER:</strong></td>
                        <td class="text-right"><strong>{{ number_format($totaux['reste_a_payer'], 2, ',', ' ') }} Fcfa</strong></td>
                    </tr>
                    @else
                    {{-- FACTURE : Vente soldée --}}
                    <tr class="total-final">
                        <td><strong>TOTAL:</strong></td>
                        <td class="text-right"><strong>{{ number_format($totaux['montant_total'], 2, ',', ' ') }} Fcfa</strong></td>
                    </tr>
                    @endif
                </table>
            </div>
        </div>

        {{-- FOOTER --}}
        <div class="footer">
            @if(isset($type_document) && $type_document === 'recu')
                <p style="font-size: 12px; color: #f59e0b; font-weight: bold;">
                    ⚠ PAIEMENT PARTIEL - Ce document ne constitue pas une facture définitive
                </p>
                <p>Reçu généré automatiquement le {{ $date_generation }}</p>
                <p>Une facture sera émise lors du paiement complet de la vente</p>
                <p>{{ $entreprise['nom'] }} - Merci de votre confiance</p>
            @elseif(isset($type_facture) && $type_facture === 'vente')
                <p style="font-size: 12px; color: #059669; font-weight: bold;">
                    ✓ VENTE SOLDÉE - PAIEMENT INTÉGRAL REÇU
                </p>
                <p>Facture générée automatiquement le {{ $date_generation }}</p>
                <p>{{ $entreprise['nom'] }} - Merci de votre confiance</p>
            @else
                <p>Facture générée automatiquement le {{ $date_generation }}</p>
                <p>Facture d'achat - {{ $entreprise['nom'] }}</p>
            @endif
        </div>

    </div>
</body>

</html>