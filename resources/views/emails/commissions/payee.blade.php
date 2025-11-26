<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>{{ $isGrouped ? 'Paiement Groupé de Commissions' : 'Paiement de Commission' }}</title>
    <style>
        body {
            font-family: 'Arial', sans-serif;
            background-color: #f4f4f4;
            margin: 0;
            padding: 0;
        }
        .container {
            max-width: 650px;
            margin: 40px auto;
            background: #ffffff;
            border-radius: 12px;
            overflow: hidden;
            box-shadow: 0 4px 20px rgba(0, 0, 0, 0.1);
        }
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            color: #ffffff;
            padding: 30px;
            text-align: center;
        }
        .header h1 {
            margin: 0;
            font-size: 28px;
            font-weight: 600;
        }
        .header p {
            margin: 10px 0 0;
            font-size: 16px;
            opacity: 0.95;
        }
        .content {
            padding: 40px 30px;
        }
        .greeting {
            font-size: 18px;
            color: #333333;
            margin-bottom: 20px;
        }
        .amount-card {
            background: linear-gradient(135deg, #667eea15 0%, #764ba215 100%);
            border-left: 4px solid #667eea;
            padding: 25px;
            margin: 25px 0;
            border-radius: 8px;
        }
        .amount-label {
            font-size: 14px;
            color: #666666;
            text-transform: uppercase;
            letter-spacing: 1px;
            margin-bottom: 8px;
        }
        .amount-value {
            font-size: 36px;
            font-weight: 700;
            color: #667eea;
            margin: 0;
        }
        .info-card {
            background-color: #f8f9fa;
            padding: 20px;
            border-radius: 8px;
            margin: 20px 0;
        }
        .info-row {
            display: flex;
            justify-content: space-between;
            padding: 10px 0;
            border-bottom: 1px solid #e0e0e0;
        }
        .info-row:last-child {
            border-bottom: none;
        }
        .commissions-table {
            width: 100%;
            border-collapse: collapse;
            margin: 25px 0;
        }
        .commissions-table th {
            background-color: #f8f9fa;
            padding: 12px;
            text-align: left;
            font-size: 12px;
            text-transform: uppercase;
            color: #666666;
            border-bottom: 2px solid #e0e0e0;
        }
        .commissions-table td {
            padding: 15px 12px;
            border-bottom: 1px solid #f0f0f0;
            font-size: 14px;
            color: #333333;
        }
        .commissions-table tr:hover {
            background-color: #f8f9fa;
        }
        .info-box {
            background-color: #e3f2fd;
            border-left: 4px solid #2196f3;
            padding: 15px;
            margin: 20px 0;
            border-radius: 4px;
            font-size: 14px;
            color: #1976d2;
        }
        .footer {
            background-color: #f8f9fa;
            padding: 25px 30px;
            text-align: center;
            color: #666666;
            font-size: 13px;
        }
        .footer a {
            color: #667eea;
            text-decoration: none;
        }
        @media only screen and (max-width: 600px) {
            .container {
                margin: 20px;
            }
            .amount-value {
                font-size: 28px;
            }
        }
    </style>
</head>
<body>
    <div class="container">
        <!-- Header -->
        <div class="header">
            <h1>🎉 {{ $isGrouped ? 'Paiement Groupé' : 'Paiement de Commission' }}</h1>
            <p>{{ $isGrouped ? 'Vos commissions ont été versées' : 'Votre commission a été versée' }} avec succès</p>
        </div>

        <!-- Content -->
        <div class="content">
            <p class="greeting">
                Bonjour <strong>{{ $commission->user->fullname }}</strong>,
            </p>

            @if($isGrouped)
                <p style="color: #555555; line-height: 1.6;">
                    Nous avons le plaisir de vous informer qu'un paiement groupé de 
                    <strong>{{ $commissions->count() }}</strong> commission(s) a été effectué sur votre compte.
                </p>
            @else
                <p style="color: #555555; line-height: 1.6;">
                    Nous avons le plaisir de vous informer que votre commission pour la vente 
                    <strong>{{ $commission->vente->reference ?? 'N/A' }}</strong> a été versée avec succès.
                </p>
            @endif

            <!-- Amount Card -->
            <div class="amount-card">
                <div class="amount-label">{{ $isGrouped ? 'Montant Total Versé' : 'Montant Versé' }}</div>
                <div class="amount-value">{{ number_format($totalMontant, 0, ',', ' ') }} Fcfa</div>
            </div>

            @if($isGrouped)
                <!-- Table for grouped payments -->
                <h3 style="color: #333333; margin-top: 30px;">📋 Détail des Commissions</h3>
                <table class="commissions-table">
                    <thead>
                        <tr>
                            <th>Référence Vente</th>
                            <th>Date</th>
                            <th style="text-align: right;">Montant</th>
                        </tr>
                    </thead>
                    <tbody>
                        @foreach($commissions as $comm)
                        <tr>
                            <td><strong>{{ $comm->vente->reference ?? 'N/A' }}</strong></td>
                            <td>{{ $comm->created_at->format('d/m/Y') }}</td>
                            <td style="text-align: right; font-weight: 600; color: #28a745;">
                                {{ number_format($comm->commission_due, 0, ',', ' ') }} Fcfa
                            </td>
                        </tr>
                        @endforeach
                    </tbody>
                </table>

                <!-- Summary -->
                <div class="info-card">
                    <div class="info-row">
                        <span>Nombre de commissions :</span>
                        <span><strong>{{ $commissions->count() }}</strong></span>
                    </div>
                    <div class="info-row">
                        <span>Votre taux de commission :</span>
                        <span><strong>{{ $commission->user->taux_commission }}%</strong></span>
                    </div>
                    <div class="info-row" style="font-weight: 700; font-size: 16px;">
                        <span>Total versé :</span>
                        <span style="color: #28a745;">{{ number_format($totalMontant, 0, ',', ' ') }} Fcfa</span>
                    </div>
                </div>
            @else
                <!-- Details for single payment -->
                <div class="info-card">
                    <div class="info-row">
                        <span>Référence de la vente :</span>
                        <span><strong>{{ $commission->vente->reference ?? 'N/A' }}</strong></span>
                    </div>
                    <div class="info-row">
                        <span>Montant de la vente :</span>
                        <span><strong>{{ number_format($commission->vente->prix_total ?? 0, 0, ',', ' ') }} Fcfa</strong></span>
                    </div>
                    <div class="info-row">
                        <span>Votre taux de commission :</span>
                        <span><strong>{{ $commission->user->taux_commission }}%</strong></span>
                    </div>
                    <div class="info-row" style="font-weight: 700; font-size: 16px;">
                        <span>Commission versée :</span>
                        <span style="color: #28a745;">{{ number_format($commission->commission_due, 0, ',', ' ') }} Fcfa</span>
                    </div>
                </div>
            @endif

            <!-- Info Box -->
            <div class="info-box">
                ℹ️ <strong>Remarque :</strong> Le montant sera disponible sur votre compte selon les délais bancaires habituels.
            </div>

            <p style="color: #555555; margin-top: 30px;">
                Merci pour votre contribution et votre excellent travail !
            </p>

            <p style="color: #555555;">
                Cordialement,<br>
                <strong>L'équipe de gestion</strong>
            </p>
        </div>

        <!-- Footer -->
        <div class="footer">
            <p style="margin: 0 0 10px 0;">
                Cet email a été envoyé automatiquement, merci de ne pas y répondre.
            </p>
            <p style="margin: 0;">
                Pour toute question, contactez-nous à 
                <a href="mailto:{{ config('mail.from.address') }}">{{ config('mail.from.address') }}</a>
            </p>
            <p style="margin: 15px 0 0 0; color: #999999;">
                © {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.
            </p>
        </div>
    </div>
</body>
</html>