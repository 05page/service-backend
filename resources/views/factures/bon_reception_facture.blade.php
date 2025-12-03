{{-- resources/views/factures/bon_reception_facture.blade.php - Facture basée sur Bon de Réception --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture - Bon de Réception {{ $bon_reception['numero'] }}</title>
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
            color: rgba(59, 130, 246, 0.05); /* Bleu Achat */
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
            color: #3b82f6; /* Bleu Achat */
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
            color: #3b82f6; /* Bleu Achat */
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
            background-color: #3b82f6; /* Bleu Achat */
            color: white;
            font-weight: bold;
            font-size: 13px;
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
        
        /* Style spécifique pour le bon de réception */
        .bon-reception-info {
            margin-top: 5px;
            font-size: 11px;
            color: #3b82f6;
            font-weight: bold;
        }
    </style>
</head>

<body>
    {{-- FILIGRANE --}}
    <div class="watermark">
        Bon Réception
    </div>

    <div class="container">

        {{-- EN-TÊTE --}}
        <div class="header clearfix">
            <div class="facture-title">
                <h1>FACTURE</h1>
                <div class="facture-numero">
                    N° {{ $facture->numero_facture }}
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
            {{-- Facture d'ACHAT (Fournisseur) --}}
            <div class="billing-info billing-left">
                <h3>FACTURÉ PAR:</h3>
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

            {{-- Info Bon de Réception (à droite) --}}
            <div class="billing-info">
                <h3>BON DE RÉCEPTION:</h3>
                <div class="fournisseur-name">N° {{ $bon_reception['numero'] }}</div>
                <p>Date de réception: {{ $bon_reception['date'] }}</p>
            </div>
        </div>

        {{-- TABLEAU DES ARTICLES --}}
        <div class="articles-section">
            <table class="articles-table">
                <thead>
                    <tr>
                        <th style="width: 40%;">Description</th>
                        <th style="width: 20%;">N° Bon Réception</th>
                        <th style="width: 12%;" class="text-center">Qté</th>
                        <th style="width: 14%;" class="text-right">P.U HT</th>
                        <th style="width: 14%;" class="text-right">Total HT</th>
                    </tr>
                </thead>

                <tbody>
                    @foreach($articles as $article)
                    <tr>
                        <td>
                            <strong>{{ $article['description'] }}</strong>
                        </td>
                        <td>
                            <strong>{{ $article['numero_bon_reception'] }}</strong>
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

                    <tr class="total-final">
                        <td><strong>TOTAL:</strong></td>
                        <td class="text-right"><strong>{{ number_format($totaux['montant_total'], 2, ',', ' ') }} Fcfa</strong></td>
                    </tr>
                </table>
            </div>
        </div>

        {{-- FOOTER --}}
        <div class="footer">
            <p>Facture générée automatiquement le {{ $date_generation }}</p>
            <p>Basée sur le Bon de Réception N° {{ $bon_reception['numero'] }}</p>
            <p>{{ $entreprise['nom'] }} - Merci de votre confiance</p>
        </div>

    </div>
</body>

</html>
