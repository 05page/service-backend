# API – Gestion de Services (Laravel + Sanctum)

> Doc destinée au front & aux intégrateurs. Une seule page, zéro bla-bla inutile, juste ce qu’il faut pour brancher vite et bien ⚡

## Sommaire
- [Aperçu](#aperçu)
- [Base URL](#base-url)
- [Authentification](#authentification)
- [Codes de statut & format d’erreur](#codes-de-statut--format-derreur)
- [Workflow recommandé](#workflow-recommandé)
- [Endpoints publics](#endpoints-publics)
  - [Inscription admin](#post-apiregister)
  - [Connexion](#post-apilogin)
  - [Vérification d’email](#get-apiemailverifyidhash)
  - [Définir le mot de passe (employé)](#post-apiset-password)
- [Endpoints protégés](#endpoints-protégés-authsanctum)
  - [Profil](#get-apiprofil)
  - [Mise à jour profil](#put-apiupdate)
  - [Changer le mot de passe](#post-apipasswordchange)
  - [Déconnexion](#post-apilogout)
- [Endpoints Admin – Employés](#endpoints-admin--employés)
- [Endpoints Admin – Permissions](#endpoints-admin--permissions)
- [Sécurité & rôles](#sécurité--rôles)
- [Exemples cURL](#exemples-curl)
- [Annexes](#annexes)

---

## Aperçu
API d’authentification et de gestion des utilisateurs (**admin, employés**) + gestion des **permissions**.  
Stack : **Laravel** + **Sanctum** (auth par token Bearer).

## Base URL
En local, via `php artisan serve` :  
```
http://localhost:8000/api
```

> Toutes les routes documentées ci-dessous sont *préfixées* par `/api` dans `routes/api.php`.

## Authentification
Les routes protégées utilisent un **token Bearer** émis à la connexion.

```
Authorization: Bearer <TOKEN>
Content-Type: application/json
Accept: application/json
```

Le token est créé via `Sanctum` : `AuthController@login` → `$user->createToken('auth_token')->plainTextToken`.

## Codes de statut & format d’erreur
- `201` → Succès
- `400` → Requête invalide (ex. set-password refusé)
- `401` → Identifiants invalides
- `403` → Compte désactivé
- `422` → Erreurs de validation
- `500` → Erreur serveur

**Format type (validation)** – renvoyé par des `ValidationException` :
```json
{
  "success": false,
  "message": "Erreur de validation",
  "errors": {
    "email": ["Le champ email est obligatoire."],
    "password": ["Le mot de passe doit faire au moins 8 caractères."]
  }
}
```

**Format type (erreur serveur)** :
```json
{
  "success": false,
  "message": "Erreur lors de l'inscription",
  "error": "Message technique / stack"
}
```

---

## Workflow recommandé
1. **Admin** s’inscrit (`POST /register`) → reçoit un token.
2. **Admin** crée des employés via endpoints Admin.
3. **Employé** définit son mot de passe via `POST /set-password` (si compte créé sans mot de passe).
4. **Employé**/Admin se connecte (`POST /login`) → reçoit token.
5. Accès aux routes protégées avec `Authorization: Bearer <TOKEN>`.
6. **Permissions** gérées par l’admin (activation/désactivation).

---

## Endpoints publics

### `POST /api/register`
Inscrire un *admin*. Le contrôleur n’autorise que `role = admin`.  
Validation : 
- `fullname` (string, <= 300) **requis**
- `email` (email unique) **requis**
- `telephone` (string, <= 10) **requis**
- `adresse` (string, <= 255) **requis**
- `password` (confirmé, min 8, *mixedCase*, chiffres, symboles) **requis**
- `role` (optionnel, **admin** uniquement – défaut `admin`)

**Body**
```json
{
  "fullname": "Jean Dupont",
  "email": "jean.dupont@example.com",
  "telephone": "0102030405",
  "adresse": "Abidjan",
  "password": "Password@123",
  "password_confirmation": "Password@123",
  "role": "admin"
}
```

**Réponse – 201**
```json
{
  "success": true,
  "data": {
    "user": { "id": 1, "fullname": "Jean Dupont", "email": "jean.dupont@example.com", "role": "admin", "active": true },
    "token": "string_token",
    "email_verified": false
  },
  "message": "Inscription réussie. Un email de vérification a été envoyé."
}
```

**Réponse – 422 (validation)** : voir section [Codes de statut & format d’erreur](#codes-de-statut--format-derreur).

---

### `POST /api/login`
Connexion par email/mot de passe.  
Validation : `email` (email), `password` (string).

**Body**
```json
{ "email": "jean.dupont@example.com", "password": "Password@123" }
```

**Réponses**
- `201` – Succès, retourne `user`, `token`, `email_verified`.
- `401` – Identifiants incorrects.
- `403` – Compte désactivé (`user.active = false`).

**Réponse – 201**
```json
{
  "success": true,
  "data": {
    "user": { "id": 1, "fullname": "Jean Dupont", "email": "jean.dupont@example.com", "role": "admin", "active": true },
    "token": "string_token",
    "email_verified": true
  },
  "message": "Connexion réussie"
}
```

---

### `GET /api/email/verify/{id}/{hash}`
Vérifie l’email de l’utilisateur.  
- Vérifie la **signature** du lien (`$request->hasValidSignature()`).
- Compare le **hash** à `sha1($user->getEmailForVerification())`.
- Si OK → `email_verified_at` est renseigné et l’événement `Verified` est dispatché.

**Réponse – 200**
```json
{
  "response_code": 200,
  "status": "success",
  "message": "Email vérifié avec succès !",
  "email_verified": true,
  "user": { "id": 1, "email": "jean.dupont@example.com", "email_verified_at": "2025-08-24T12:34:56Z" }
}
```

**Erreurs**
- `400` – Lien invalide/expiré ou hash invalide.
- `500` – Erreur interne.

---

### `POST /api/set-password`
Permet à un **employé** (ou utilisateur sans mot de passe) de définir son mot de passe.  
Validation :
- `email` (existant en base) **requis**
- `password` (confirmé, min 8, *mixedCase*, chiffres, symboles) **requis**

**Préconditions métier** (d’après le contrôleur) :
- Le compte doit être « activable » au sens de `activate_code()` (mécanisme d’activation côté `User`).
- Le compte ne doit **pas** déjà avoir un mot de passe.

**Body**
```json
{
  "email": "employe@example.com",
  "password": "Employe@123",
  "password_confirmation": "Employe@123"
}
```

**Réponses**
- `200` – Mot de passe défini.
- `400` – Compte non activé *ou* mot de passe déjà existant.
- `422` – Erreurs de validation.
- `500` – Erreur serveur.

**Réponse – 200**
```json
{ "success": true, "message": "Mot de passe défini avec succès ! Vous pouvez maintenant vous connecter." }
```

---

## Endpoints protégés (`auth:sanctum`)

### `GET /api/profil`
Retourne les infos de l’utilisateur authentifié.

**Réponse – 200 (exemple)**
```json
{
  "user": {
    "id": 1,
    "fullname": "Jean Dupont",
    "email": "jean.dupont@example.com",
    "role": "admin",
    "active": true
  }
}
```

---

### `PUT /api/update`
Met à jour le profil de l’utilisateur **courant**.  
> Voir `ProfileController@updateProfile` pour la liste exacte des champs acceptés.

**Réponses**
- `200` – Profil mis à jour.
- `422` – Erreurs de validation.

---

### `POST /api/password/change`
Change le mot de passe de l’utilisateur **courant**.  
> Voir `PasswordController@changePassword` pour la structure exacte (habituellement `current_password`, `password`, `password_confirmation`).

**Réponse – 200**
```json
{ "response_code": 200, "status": "success", "message": "Mot de passe modifié" }
```

---

### `POST /api/logout`
Révoque le **token courant** (`$request->user()->currentAccessToken()->delete()`).
- `200` – Déconnexion réussie.
- `500` – Erreur interne.

**Réponse – 200**
```json
{ "response_code": 200, "status": "success", "message": "Déconnexion réussie" }
```

---

## Endpoints Admin – Employés
> Préfixe : `/api/admin` – **auth requis** (+ rôle admin attendu côté middleware/policy).

- `POST /admin/createUser` → créer un employé
- `GET /admin/showEmploye` → lister les employés
- `PUT /admin/updateEmploye` → mettre à jour un employé
- `POST /admin/deleteEmployes` → supprimer plusieurs employés
- `POST /admin/deleteEmploye/{id}` → supprimer un employé

> Les payloads exacts dépendent de `EmployeIntermediaireController`. Conventionnellement, prévoir : `fullname`, `email`, `telephone`, `adresse`, `active`, ainsi que l’assignation de permissions si exposée par ce contrôleur.

---

## Endpoints Admin – Permissions
> Préfixe : `/api/admin` – **auth requis** (+ rôle admin attendu).

- `POST /admin/createPermission` → créer une permission
- `GET /admin/showPermissions` → lister toutes les permissions
- `GET /admin/showPermission/{id}` → voir une permission
- `POST /admin/permission/{id}` → activer/désactiver une permission

> La structure exacte des permissions dépend de `PermissionsController`. Prévoyez au minimum des champs `name`, éventuellement `description`, et un statut `active` pour l’activation/désactivation.

---

## Sécurité & rôles
- **Une seule table `users`** (recommandée) avec un champ `role` (`admin`, `employe`) ou un système de rôles/permissions (ex. Spatie).
- **Seul l’admin** crée les comptes employés.
- Le champ `active` contrôle l’accès (bloque la connexion si `false`).
- Les **permissions** déterminent l’accès aux fonctionnalités (middleware `role`/`permission` recommandé côté routes).

---

## Exemples cURL

### Connexion
```bash
curl -X POST http://localhost:8000/api/login \
  -H "Content-Type: application/json" \
  -d '{"email":"jean.dupont@example.com","password":"Password@123"}'
```

### Appeler une route protégée
```bash
curl http://localhost:8000/api/profil \
  -H "Authorization: Bearer <TOKEN>"
```

### Définir le mot de passe (employé)
```bash
curl -X POST http://localhost:8000/api/set-password \
  -H "Content-Type: application/json" \
  -d '{"email":"employe@example.com","password":"Employe@123","password_confirmation":"Employe@123"}'
```

---
