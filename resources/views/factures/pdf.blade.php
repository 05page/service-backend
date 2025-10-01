{{-- resources/views/factures/pdf.blade.php --}}
<!DOCTYPE html>
<html lang="fr">

<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Facture {{ $facture->numero_facture }}</title>
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

        /* En-tête */
        .header {
            margin-bottom: 30px;
            overflow: hidden;
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
    <div class="container">

        {{-- EN-TÊTE --}}
        <div class="header clearfix">
            <div class="facture-title">
                <h1>FACTURE</h1>
                <div class="facture-numero">N° {{ $facture->numero_facture }}</div>
                <div class="facture-date">Date: {{ $date_generation }}</div>
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
            @if($type_facture === 'vente')
            {{-- Facture de VENTE --}}
            <div class="billing-info billing-left">
                <h3>FACTURÉ À:</h3>
                <div class="client-name">{{ $client['nom'] }}</div>
                @if(!empty($client['telephone']))
                <p>Tél: {{ $client['telephone'] }}</p>
                @endif
                <p>Adresse: {{ $client['adresse'] }}</p>
            </div>

            @if(isset($intermediaire))
            <div class="billing-info">
                <h3 class="intermediaire">INTERMÉDIAIRE:</h3>
                <div class="client-name">{{ $intermediaire['nom'] ?? 'N/A' }}</div>
                @if(isset($intermediaire['commission']))
                <p class="commission">Commission: {{ $intermediaire['commission'] }}</p>
                @endif
            </div>
            @endif

            @else
            {{-- Facture d'ACHAT --}}
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
            @endif
        </div>

        {{-- TABLEAU DES ARTICLES --}}
        <div class="articles-section">
            <table class="articles-table">
                <thead>
                    <tr>
                        <th style="width: 30%;">Description</th>
                        @if($type_facture === 'vente')
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
                            @if($type_facture === 'vente')
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
                    {{-- Si vous avez de la TVA à ajouter plus tard --}}
                    {{-- <tr>
                        <td class="total-label">TVA (20%):</td>
                        <td class="text-right">{{ number_format($totaux['tva'] ?? 0, 2, ',', ' ') }} Fcfa</td>
                    </tr> --}}
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
            @if($type_facture === 'vente')
            <p>{{ $entreprise['nom'] }} - Merci de votre confiance</p>
            @else
            <p>Facture d'achat - {{ $entreprise['nom'] }}</p>
            @endif
        </div>

    </div>
</body>

</html>