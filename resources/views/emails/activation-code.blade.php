<!DOCTYPE html>
<html lang="fr">
<head>
  <meta charset="UTF-8">
  <meta name="viewport" content="width=device-width, initial-scale=1.0">
  <title>Activation de votre compte</title>
  <style>
    body {
      font-family: -apple-system, BlinkMacSystemFont, 'Segoe UI', Roboto, Arial, sans-serif;
      background: #f9fafb;
      padding: 30px;
      margin: 0;
      color: #111827;
    }

    .email-container {
      max-width: 600px;
      margin: 0 auto;
      background: #ffffff;
      border-radius: 8px;
      border: 1px solid #e5e7eb;
      box-shadow: 0 4px 12px rgba(0,0,0,0.05);
      overflow: hidden;
    }

    .header {
      background: #1E3A8A;
      color: #ffffff;
      text-align: center;
      padding: 30px 20px;
    }

    .header h1 {
      font-size: 1.8em;
      margin: 0;
    }

    .header p {
      margin: 8px 0 0;
      font-size: 1em;
      opacity: 0.9;
    }

    .content {
      padding: 30px 25px;
    }

    .greeting {
      font-size: 1.1em;
      margin-bottom: 15px;
    }

    .code-section {
      background: #ffffff;
      border: 1px solid #e5e7eb;
      border-radius: 8px;
      text-align: center;
      padding: 25px 20px;
      margin: 25px 0;
    }

    .code-label {
      color: #374151;
      font-size: 15px;
      margin-bottom: 10px;
      font-weight: 500;
    }

    .activation-code {
      background: #f9fafb;
      border: 1px solid #d1d5db;
      border-radius: 6px;
      display: inline-block;
      padding: 15px 20px;
      font-family: Consolas, monospace;
      font-size: 22px;
      font-weight: 700;
      letter-spacing: 3px;
      color: #111827;
      margin-bottom: 20px;
    }

    .cta-button {
      display: inline-block;
      background: #1E3A8A;
      color: #ffffff;
      text-decoration: none;
      padding: 12px 24px;
      border-radius: 6px;
      font-weight: 600;
      font-size: 15px;
    }

    .instructions {
      background: #f3f4f6;
      border-left: 4px solid #1E3A8A;
      padding: 20px;
      border-radius: 6px;
      margin: 30px 0;
    }

    .instructions-title {
      font-weight: 600;
      color: #111827;
      margin-bottom: 10px;
      font-size: 15px;
    }

    .instructions ul {
      padding-left: 18px;
      margin: 0;
    }

    .instructions li {
      margin-bottom: 8px;
      color: #374151;
      font-size: 14px;
    }

    .footer {
      background: #f9fafb;
      padding: 20px;
      text-align: center;
      font-size: 13px;
      color: #6b7280;
      border-top: 1px solid #e5e7eb;
    }

    .company-name {
      color: #1E3A8A;
      font-weight: 600;
    }
  </style>
</head>
<body>
  <div class="email-container">
    <div class="header">
      <h1>Activation de votre compte</h1>
      <p>Bienvenue sur notre plateforme</p>
    </div>

    <div class="content">
      <div class="greeting">
        Bonjour <strong>{{ $user->fullname }}</strong>,
      </div>

      <p>
        Félicitations ! Votre compte <strong>{{ $user->role }}</strong> a été créé avec succès.
        Pour commencer à utiliser notre plateforme, vous devez d’abord activer votre compte.
      </p>

      <div class="code-section">
        <div class="code-label">Votre code d’activation</div>
        <div class="activation-code">{{ $user->activation_code }}</div>
        <br>
        <a href="{{ $activationLink }}" class="cta-button">Activer mon compte</a>
      </div>

      <div class="instructions">
        <div class="instructions-title">Instructions importantes :</div>
        <ul>
          <li>Utilisez ce code pour activer votre compte.</li>
          <li>Après activation, vous pourrez créer votre mot de passe.</li>
          <li>Ne partagez jamais ce code avec d’autres personnes.</li>
        </ul>
      </div>

      <p>
        Si vous avez des questions, n’hésitez pas à contacter notre équipe support.<br>
        Cordialement,<br>
        <span class="company-name">L’équipe {{ config('app.name') }}</span>
      </p>
    </div>

    <div class="footer">
      <p>Cet email a été envoyé automatiquement, merci de ne pas y répondre.</p>
      <p>&copy; {{ date('Y') }} {{ config('app.name') }}. Tous droits réservés.</p>
    </div>
  </div>
</body>
</html>
