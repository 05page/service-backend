{{-- resources/views/factures/pdf.blade.php - Fichier unique pour Factures, Reçus et Achats --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>
        @if($type_document === 'recu')
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

        /* Filigrane */
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
        }

        .recu-watermark {
            color: rgba(249, 115, 22, 0.05);
        }

        .facture-watermark {
            color: rgba(34, 197, 94, 0.05);
        }

        .achat-watermark {
            color: rgba(59, 130, 246, 0.05);
        }

        /* En-tête */
        .header {
            margin-bottom: 25px;
            overflow: hidden;
        }

        .facture-title {
            float: left;
            width: 50%;
        }

        .facture-title h1 {
            font-size: 28px;
            color: #22c55e;
            font-weight: bold;
            margin-bottom: 8px;
        }

        /* Style pour reçu - ORANGE */
        .recu-title h1 {
            color: #f97316;
        }

        /* Style pour achat - BLEU */
        .achat-title h1 {
            color: #3b82f6;
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

        /* Section client/fournisseur */
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
            color: #22c55e;
        }

        .billing-info h3.client-recu {
            color: #f97316;
        }

        .billing-info h3.fournisseur-achat {
            color: #3b82f6;
        }

        .billing-info h3.intermediaire {
            color: #f59e0b;
        }

        .billing-info p {
            margin-bottom: 2px;
            font-size: 10px;
        }

        .client-name,
        .fournisseur-name {
            font-size: 12px;
            font-weight: bold;
            color: #1f2937;
            margin-bottom: 6px;
        }

        .commission {
            color: #f59e0b;
            font-weight: bold;
        }

        /* Tableau des articles */
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

        /* Totaux */
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

        .totaux-table .total-final {
            background-color: #22c55e;
            color: white;
            font-weight: bold;
            font-size: 13px;
        }

        .totaux-table .total-final-recu {
            background-color: #f97316;
            color: white;
            font-weight: bold;
            font-size: 13px;
        }

        .totaux-table .total-final-achat {
            background-color: #3b82f6;
            color: white;
            font-weight: bold;
            font-size: 13px;
        }

        .totaux-table .paiement-info {
            background-color: #fed7aa;
            color: #9a3412;
            font-weight: bold;
        }

        .totaux-table .reste-payer {
            background-color: #fef3c7;
            color: #92400e;
            font-weight: bold;
        }

        .totaux-table .montant-verse-info {
            background-color: #dcfce7;
            color: #166534;
            font-weight: bold;
        }

        /* Footer */
        .footer {
            margin-top: 30px;
            text-align: center;
            font-size: 9px;
            color: #666;
            border-top: 1px solid #e5e7eb;
            padding-top: 10px;
        }

        /* Responsive pour DomPDF */
        .clearfix::after {
            content: "";
            display: table;
            clear: both;
        }

        .prix-amount {
            font-weight: bold;
            color: #059669;
        }
    </style>
</head>

<body>
    {{-- FILIGRANE --}}
    <div class="watermark 
        @if($type_document === 'recu') recu-watermark
        @elseif($type_document === 'achat') achat-watermark
        @else facture-watermark
        @endif">
        @if($type_document === 'recu')
            {{ $recu->numero_recu }}
        @else
            {{ $facture->numero_facture }}
        @endif
    </div>

    <div class="container">

        {{-- EN-TÊTE --}}
        <div class="header clearfix">
            <div class="facture-title 
                @if($type_document === 'recu') recu-title
                @elseif($type_document === 'achat') achat-title
                @endif">
                <h1>
                    @if($type_document === 'recu')
                        REÇU
                    @else
                        FACTURE
                    @endif
                </h1>
                <div class="facture-numero">
                    N°
                    @if($type_document === 'recu')
                        {{ $recu->numero_recu }}
                    @else
                        {{ $facture->numero_facture }}
                    @endif
                </div>
                <div class="facture-date">Date: {{ $date_generation }}</div>
                @if(isset($statut_copie))
                <div class="statut-copie" style="font-size: 12px; color: {{ $statut_copie === 'ORIGINAL' ? '#059669' : '#dc2626' }}; font-weight: bold; margin-top: 5px;">
                    {{ $statut_copie }}
                </div>
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

        {{-- SECTION CLIENT/FOURNISSEUR --}}
        <div class="billing-section clearfix">
            @if($type_document === 'achat')
                {{-- Facture d'ACHAT --}}
                <div class="billing-info billing-left">
                    <h3 class="fournisseur-achat">FACTURÉ PAR:</h3>
                    <div class="fournisseur-name">{{ $fournisseur['nom'] }}</div>
                    @if(!empty($fournisseur['email']))
                    <p>Email: {{ $fournisseur['email'] }}</p>
                    @endif
                    @if(!empty($fournisseur['telephone']))
                    <p>Tél: {{ $fournisseur['telephone'] }}</p>
                    @endif
                    @if(!empty($fournisseur['adresse']))
                    <p>Adresse: {{ $fournisseur['adresse'] }}</p>
                    @endif
                </div>
            @else
                {{-- Facture de VENTE ou REÇU --}}
                <div class="billing-info billing-left">
                    <h3 @if($type_document === 'recu') class="client-recu" @endif>
                        {{ $type_document === 'recu' ? 'PAIEMENT REÇU DE:' : 'FACTURÉ À:' }}
                    </h3>
                    <div class="client-name">{{ $client['nom'] }}</div>
                    @if(!empty($client['telephone']))
                    <p>Tél: {{ $client['telephone'] }}</p>
                    @endif
                    @if(!empty($client['adresse']))
                    <p>Adresse: {{ $client['adresse'] }}</p>
                    @endif
                </div>

                @if(isset($intermediaire) && $intermediaire)
                <div class="billing-info">
                    <h3 class="intermediaire">INTERMÉDIAIRE:</h3>
                    <div class="client-name">{{ $intermediaire['nom'] ?? 'N/A' }}</div>
                    @if(isset($intermediaire['commission']))
                    <p class="commission">Commission: {{ number_format($intermediaire['commission'], 2, ',', ' ') }} Fcfa</p>
                    @endif
                </div>
                @endif
            @endif
        </div>

        {{-- TABLEAU DES ARTICLES --}}
        <div class="articles-section">
            <table class="articles-table">
                <thead>
                    <tr>
                        <th style="width: 35%;">Description</th>
                        @if($type_document === 'achat')
                        <th style="width: 20%;">N° Achat</th>
                        @else
                        <th style="width: 20%;">Code produit</th>
                        @endif
                        <th style="width: 12%;" class="text-center">Qté</th>
                        <th style="width: 18%;" class="text-right">P.U HT</th>
                        <th style="width: 20%;" class="text-right">Total HT</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($articles as $article)
                    <tr>
                        <td>
                            <strong>{{ $article['description'] }}</strong>
                        </td>
                        <td>
                            @if($type_document === 'achat')
                                @if(isset($article['numero_achat']) && !empty($article['numero_achat']))
                                <strong>{{ $article['numero_achat'] }}</strong>
                                @endif
                            @else
                                @if(isset($article['code']) && !empty($article['code']))
                                <strong>{{ $article['code'] }}</strong>
                                @endif
                            @endif
                        </td>

                        <td class="text-center">{{ $article['quantite'] }}</td>
                        <td class="text-right">{{ number_format($article['prix_unitaire'], 2, ',', ' ') }}</td>
                        <td class="text-right prix-amount">{{ number_format($article['total'], 2, ',', ' ') }}</td>
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

                    @if($type_document === 'recu')
                        {{-- ========== INFORMATIONS SPÉCIFIQUES AU REÇU ========== --}}
                        <tr class="paiement-info">
                            <td class="total-label">Montant payé:</td>
                            <td class="text-right"><strong>{{ number_format($paiement['montant_paye'], 2, ',', ' ') }} Fcfa</strong></td>
                        </tr>
                        <tr class="paiement-info">
                            <td class="total-label">Montant cumulé:</td>
                            <td class="text-right"><strong>{{ number_format($paiement['montant_cumule'], 2, ',', ' ') }} Fcfa</strong></td>
                        </tr>
                        <tr class="reste-payer">
                            <td class="total-label">Reste à payer:</td>
                            <td class="text-right"><strong>{{ number_format($paiement['reste_a_payer'], 2, ',', ' ') }} Fcfa</strong></td>
                        </tr>
                        <tr>
                            <td class="total-label">Pourcentage payé:</td>
                            <td class="text-right">{{ number_format($paiement['pourcentage_paye'], 2, ',', ' ') }}%</td>
                        </tr>
                    @elseif($type_document === 'facture')
                        {{-- ========== INFORMATIONS POUR FACTURE DE VENTE ========== --}}
                        <tr class="montant-verse-info">
                            <td class="total-label">Montant versé:</td>
                            <td class="text-right"><strong>{{ number_format($totaux['montant_verse'], 2, ',', ' ') }} Fcfa</strong></td>
                        </tr>
                        <tr class="reste-payer">
                            <td class="total-label">Reste à payer:</td>
                            <td class="text-right"><strong>{{ number_format($totaux['reste_a_payer'], 2, ',', ' ') }} Fcfa</strong></td>
                        </tr>
                    @endif
                    {{-- Pour les factures d'achat, seulement le sous-total et total s'affichent --}}

                    <tr class="
                        @if($type_document === 'recu') total-final-recu
                        @elseif($type_document === 'achat') total-final-achat
                        @else total-final
                        @endif">
                        <td><strong>{{ $type_document === 'recu' ? 'MONTANT TOTAL:' : 'TOTAL:' }}</strong></td>
                        <td class="text-right"><strong>{{ number_format($totaux['montant_total'], 2, ',', ' ') }} Fcfa</strong></td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- FOOTER --}}
        <div class="footer">
            <p>{{ $type_document === 'recu' ? 'Reçu' : 'Facture' }} généré{{ $type_document === 'recu' ? '' : 'e' }} automatiquement le {{ $date_generation }}</p>
            @if($type_document === 'achat')
                <p>Facture d'achat - {{ $entreprise['nom'] }}</p>
            @elseif($type_document === 'recu')
                <p>{{ $entreprise['nom'] }} - Merci de votre paiement</p>
            @else
                <p>{{ $entreprise['nom'] }} - Merci de votre confiance</p>
            @endif
        </div>

    </div>
</body>

</html>