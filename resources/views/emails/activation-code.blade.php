<!DOCTYPE html>
<html lang="fr">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>Code d'activation</title>
    <style>
        * {
            margin: 0;
            padding: 0;
            box-sizing: border-box;
        }
        
        body {
            font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, 'Helvetica Neue', Arial, sans-serif;
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            min-height: 100vh;
            padding: 20px;
            line-height: 1.6;
        }
        
        .email-container {
            max-width: 600px;
            margin: 0 auto;
            background: rgba(255, 255, 255, 0.95);
            backdrop-filter: blur(10px);
            border-radius: 20px;
            overflow: hidden;
            box-shadow: 0 20px 40px rgba(0, 0, 0, 0.1);
            border: 1px solid rgba(255, 255, 255, 0.2);
        }
        
        .header {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            padding: 40px 30px;
            text-align: center;
            color: white;
            position: relative;
            overflow: hidden;
        }
        
        .header::before {
            content: '';
            position: absolute;
            top: -50%;
            left: -50%;
            width: 200%;
            height: 200%;
            background: radial-gradient(circle, rgba(255,255,255,0.1) 0%, transparent 70%);
            animation: float 6s ease-in-out infinite;
        }
        
        @keyframes float {
            0%, 100% { transform: translateY(0px) rotate(0deg); }
            50% { transform: translateY(-20px) rotate(180deg); }
        }
        
        .header h1 {
            font-size: 2.5em;
            font-weight: 700;
            margin-bottom: 10px;
            position: relative;
            z-index: 2;
        }
        
        .header p {
            font-size: 1.1em;
            opacity: 0.9;
            position: relative;
            z-index: 2;
        }
        
        .content {
            padding: 40px 30px;
            color: #333;
        }
        
        .greeting {
            font-size: 1.2em;
            margin-bottom: 20px;
            color: #444;
        }
        
        .code-section {
            background: linear-gradient(135deg, #f093fb 0%, #f5576c 100%);
            border-radius: 15px;
            padding: 30px;
            text-align: center;
            margin: 30px 0;
            box-shadow: 0 10px 30px rgba(240, 147, 251, 0.3);
            position: relative;
            overflow: hidden;
        }
        
        .code-section::before {
            content: '';
            position: absolute;
            top: 0;
            left: 0;
            right: 0;
            bottom: 0;
            background: url('data:image/svg+xml,<svg xmlns="http://www.w3.org/2000/svg" viewBox="0 0 100 100"><defs><pattern id="grain" width="100" height="100" patternUnits="userSpaceOnUse"><circle cx="20" cy="20" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="80" cy="40" r="1" fill="rgba(255,255,255,0.1)"/><circle cx="40" cy="70" r="1" fill="rgba(255,255,255,0.1)"/></pattern></defs><rect width="100" height="100" fill="url(%23grain)"/></svg>');
            pointer-events: none;
        }
        
        .code-label {
            color: white;
            font-size: 1.1em;
            margin-bottom: 15px;
            font-weight: 600;
            position: relative;
            z-index: 2;
        }
        
        .activation-code {
            background: rgba(255, 255, 255, 0.2);
            border: 2px solid rgba(255, 255, 255, 0.3);
            border-radius: 12px;
            padding: 20px;
            font-family: 'SF Mono', Monaco, 'Cascadia Code', 'Roboto Mono', Consolas, 'Courier New', monospace;
            font-size: 2em;
            font-weight: 700;
            color: white;
            letter-spacing: 4px;
            word-spacing: 8px;
            backdrop-filter: blur(10px);
            position: relative;
            z-index: 2;
            transition: all 0.3s ease;
        }
        
        .activation-code:hover {
            transform: scale(1.05);
            box-shadow: 0 5px 20px rgba(255, 255, 255, 0.3);
        }
        
        .instructions {
            background: linear-gradient(135deg, #ffecd2 0%, #fcb69f 100%);
            border-radius: 15px;
            padding: 25px;
            margin: 30px 0;
            border-left: 4px solid #ff6b6b;
        }
        
        .instructions-title {
            color: #d63031;
            font-weight: 700;
            font-size: 1.1em;
            margin-bottom: 15px;
            display: flex;
            align-items: center;
        }
        
        .instructions-title::before {
            content: '⚠️';
            margin-right: 10px;
            font-size: 1.2em;
        }
        
        .instructions ul {
            list-style: none;
            padding: 0;
        }
        
        .instructions li {
            color: #6c5ce7;
            margin-bottom: 8px;
            padding-left: 25px;
            position: relative;
        }
        
        .instructions li::before {
            content: '✓';
            position: absolute;
            left: 0;
            color: #00b894;
            font-weight: bold;
        }
        
        .role-info {
            background: linear-gradient(135deg, #a8edea 0%, #fed6e3 100%);
            border-radius: 15px;
            padding: 20px;
            margin: 25px 0;
            text-align: center;
        }
        
        .footer {
            background: #f8f9fa;
            padding: 30px;
            text-align: center;
            border-top: 1px solid #e9ecef;
        }
        
        .footer p {
            color: #6c757d;
            font-size: 0.9em;
            margin-bottom: 10px;
        }
        
        .company-name {
            background: linear-gradient(135deg, #667eea 0%, #764ba2 100%);
            -webkit-background-clip: text;
            -webkit-text-fill-color: transparent;
            background-clip: text;
            font-weight: 700;
            font-size: 1.1em;
        }
        
        @media (max-width: 600px) {
            .email-container {
                margin: 10px;
                border-radius: 15px;
            }
            
            .header, .content {
                padding: 25px 20px;
            }
            
            .header h1 {
                font-size: 2em;
            }
            
            .activation-code {
                font-size: 1.5em;
                letter-spacing: 2px;
            }
        }
    </style>
</head>
<body>
    <div class="email-container">
        <div class="header">
            <h1>🚀 Bienvenue !</h1>
            <p>Activation de votre compte {{ ucfirst($user->type) }}</p>
        </div>

        <div class="content">
            <div class="greeting">
                Bonjour <strong>{{ $user->fullname }}</strong>,
            </div>
            
            <p>Félicitations ! Votre compte {{ $user->role }} a été créé avec succès. Pour commencer à utiliser notre plateforme, vous devez d'abord activer votre compte.</p>

            <div class="code-section">
                <div class="code-label">Votre code d'activation</div>
                <div class="activation-code">{{ $user->activation_code }}</div>
            </div>

            <div class="instructions">
                <div class="instructions-title">Instructions importantes</div>
                <ul>
                    <li>Ce code est valable pendant <strong>7 jours</strong></li>
                    <li>Utilisez ce code pour activer votre compte</li>
                    <li>Après activation, vous pourrez créer votre mot de passe</li>
                    <li>Ne partagez jamais ce code avec d'autres personnes</li>
                </ul>
            </div>

            @if($employeIntermediaire->type === 'employe')
                <div class="role-info">
                    <strong>🎯 En tant qu'employé</strong>, vous aurez accès aux fonctionnalités qui vous seront attribuées par l'administrateur.
                </div>
            @else
                <div class="role-info">
                    <strong>💼 En tant qu'intermédiaire</strong>, vous pourrez suivre vos commissions et gérer vos clients.
                </div>
            @endif

            <p>Si vous avez des questions, n'hésitez pas à contacter notre équipe support.</p>

            <p>Cordialement,<br>
            <span class="company-name">L'équipe {{ config('app.name') }}</span></p>
        </div>

        <div class="footer">
            <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
            <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.</p>
        </div>
    </div>
</body>
</html>