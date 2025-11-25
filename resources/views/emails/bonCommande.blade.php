<!DOCTYPE html>
<html>
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Bon de Commande</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }

        body {
            font-family: Arial, sans-serif;
            line-height: 1.6;
            color: #333;
            background-color: #f4f4f4;
            padding: 20px;
        }

        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background-color: #ffffff;
            border-radius: 8px;
            overflow: hidden;
            box-shadow: 0 2px 4px rgba(0, 0, 0, 0.1);
        }

        .header {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 30px 20px;
            text-align: center;
        }

        .header h1 {
            font-size: 24px;
            margin-bottom: 5px;
        }

        .header p {
            font-size: 14px;
            opacity: 0.9;
        }

        .content {
            padding: 30px 25px;
        }

        .greeting {
            font-size: 16px;
            margin-bottom: 20px;
            color: #1f2937;
        }

        .intro-text {
            font-size: 14px;
            color: #4b5563;
            margin-bottom: 25px;
            line-height: 1.6;
        }

        .commande-box {
            background-color: #eff6ff;
            border-left: 4px solid #3b82f6;
            padding: 20px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .commande-numero {
            font-size: 18px;
            font-weight: bold;
            color: #3b82f6;
            margin-bottom: 15px;
        }

        .details-table {
            width: 100%;
            margin-top: 15px;
        }

        .details-table tr {
            border-bottom: 1px solid #e5e7eb;
        }

        .details-table tr:last-child {
            border-bottom: none;
        }

        .details-table td {
            padding: 10px 0;
            font-size: 14px;
        }

        .details-table .label {
            color: #6b7280;
            width: 45%;
        }

        .details-table .value {
            color: #1f2937;
            font-weight: 500;
        }

        .items-list {
            margin-top: 15px;
            padding: 15px;
            background: white;
            border-radius: 4px;
        }

        .item-row {
            padding: 10px 0;
            border-bottom: 1px solid #e5e7eb;
        }

        .item-row:last-child {
            border-bottom: none;
        }

        .item-name {
            font-weight: 600;
            color: #1f2937;
            margin-bottom: 5px;
        }

        .item-details {
            font-size: 13px;
            color: #6b7280;
        }

        .highlight-box {
            background: linear-gradient(135deg, #3b82f6 0%, #2563eb 100%);
            color: white;
            padding: 15px;
            border-radius: 6px;
            text-align: center;
            margin: 25px 0;
        }

        .highlight-box .label {
            font-size: 12px;
            opacity: 0.9;
            margin-bottom: 5px;
        }

        .highlight-box .amount {
            font-size: 28px;
            font-weight: bold;
        }

        .info-box {
            background-color: #fef3c7;
            border-left: 4px solid #f59e0b;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
        }

        .info-box p {
            font-size: 13px;
            color: #92400e;
            margin: 0;
        }

        .info-icon {
            display: inline-block;
            width: 18px;
            height: 18px;
            background-color: #f59e0b;
            color: white;
            border-radius: 50%;
            text-align: center;
            line-height: 18px;
            font-size: 12px;
            font-weight: bold;
            margin-right: 8px;
        }

        .footer {
            background-color: #f9fafb;
            padding: 25px;
            text-align: center;
            border-top: 1px solid #e5e7eb;
        }

        .footer p {
            font-size: 13px;
            color: #6b7280;
            margin-bottom: 10px;
        }

        .footer .signature {
            font-size: 14px;
            color: #1f2937;
            font-weight: 600;
            margin-top: 15px;
        }

        .footer .auto-message {
            font-size: 11px;
            color: #9ca3af;
            margin-top: 15px;
            font-style: italic;
        }

        @media only screen and (max-width: 600px) {
            .email-container {
                border-radius: 0;
            }
            
            .content {
                padding: 20px 15px;
            }
            
            .details-table .label,
            .details-table .value {
                display: block;
                width: 100%;
            }
            
            .details-table .label {
                font-weight: 600;
                margin-bottom: 3px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>📋 Bon de Commande</h1>
            <p>Nouvelle commande enregistrée</p>
        </div>

        <div class="content">
            <p class="greeting">Bonjour <strong>{{ $achat->fournisseur->nom_fournisseurs }}</strong>,</p>
            
            <p class="intro-text">
                Nous avons le plaisir de vous confirmer notre commande. 
                Vous trouverez ci-joint le bon de commande détaillé au format PDF.
            </p>

            <div class="commande-box">
                <div class="commande-numero">
                    Commande N° {{ $achat->numero_achat }}
                </div>

                @php
                    $totalCommande = 0;
                @endphp

                <!-- Liste des articles -->
                <div class="items-list">
                    <strong style="color: #3b82f6; display: block; margin-bottom: 10px;">Articles commandés:</strong>
                    @foreach($achat->items as $item)
                    <div class="item-row">
                        <div class="item-name">{{ $item->nom_service }}</div>
                        <div class="item-details">
                            Quantité: <strong>{{ $item->quantite }}</strong> | 
                            Prix unitaire: <strong>{{ number_format($item->prix_unitaire, 0, ',', ' ') }} FCFA</strong> | 
                            Total: <strong>{{ number_format($item->prix_total, 0, ',', ' ') }} FCFA</strong>
                        </div>
                        @if($item->date_commande)
                        <div class="item-details" style="margin-top: 3px;">
                            📅 Date commande: {{ \Carbon\Carbon::parse($item->date_commande)->format('d/m/Y') }}
                            @if($item->date_livraison)
                            | 🚚 Livraison prévue: {{ \Carbon\Carbon::parse($item->date_livraison)->format('d/m/Y') }}
                            @endif
                        </div>
                        @endif
                    </div>
                    @php
                        $totalCommande += $item->prix_total;
                    @endphp
                    @endforeach
                </div>

                <!-- Informations générales -->
                <table class="details-table">
                    <tr>
                        <td class="label">📅 Date de commande</td>
                        <td class="value">{{ $achat->created_at->format('d/m/Y') }}</td>
                    </tr>
                    <tr>
                        <td class="label">📊 Statut</td>
                        <td class="value">{{ ucfirst(str_replace('_', ' ', $achat->statut)) }}</td>
                    </tr>
                    <tr>
                        <td class="label">👤 Créé par</td>
                        <td class="value">{{ $achat->creePar->fullname ?? 'Système' }}</td>
                    </tr>
                </table>
            </div>

            <!-- Montant total mis en évidence -->
            <div class="highlight-box">
                <div class="label">MONTANT TOTAL</div>
                <div class="amount">{{ number_format($totalCommande, 0, ',', ' ') }} FCFA</div>
            </div>

            <div class="info-box">
                <p>
                    <span class="info-icon">📄</span>
                    <strong>Document joint :</strong> Le bon de commande complet est disponible en pièce jointe au format PDF.
                </p>
            </div>

            @if($achat->description)
            <div style="margin: 20px 0; padding: 15px; background-color: #f9fafb; border-radius: 4px;">
                <p style="font-size: 12px; color: #6b7280; margin-bottom: 5px;"><strong>Remarques :</strong></p>
                <p style="font-size: 13px; color: #374151;">{{ $achat->description }}</p>
            </div>
            @endif

            <p class="intro-text" style="margin-top: 25px;">
                Nous comptons sur votre professionnalisme pour honorer cette commande dans les délais convenus.
            </p>
        </div>

        <div class="footer">
            <p class="signature">Cordialement,<br><strong>{{ config('app.name') }}</strong></p>
            
            <p style="margin-top: 20px; font-size: 12px;">
                📧 contact@entreprise.com | 📞 +225 XX XX XX XX XX
            </p>
            
            <p class="auto-message">
                Ceci est un email automatique, merci de ne pas y répondre directement.
                <br>Pour toute question, veuillez contacter notre service commercial.
            </p>
            
            <p style="font-size: 10px; color: #d1d5db; margin-top: 15px;">
                © {{ date('Y') }} {{ config('app.name') }} - Tous droits réservés
            </p>
        </div>
    </div>
</body>
</html>