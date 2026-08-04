# Déploiement sur Aeonfree — GSB SINIYAT

## Informations de votre base MySQL Aeonfree

| Paramètre    | Valeur                        |
|-------------|-------------------------------|
| Hôte        | `sql308.hstn.me`              |
| Base        | `mseet_42573994_gsbsiniyat`   |
| Utilisateur | `mseet_42573994`              |
| Mot de passe | *(votre mot de passe)*       |
| PHPMyAdmin  | Accessible depuis le panel    |

---

## Étape 1 — Télécharger le projet depuis Replit

Dans Replit : cliquez sur les **trois points (...)** à côté du nom du projet → **Download as zip**.
Décompressez le zip sur votre PC.

---

## Étape 2 — Configurer les identifiants MySQL

Ouvrez le fichier `config/database.php` et **remplacez** le bloc MySQL par vos vraies valeurs :

```php
// Option A : variables d'environnement (si votre hébergeur le supporte)
// Définir MYSQL_HOST, MYSQL_USER, MYSQL_PASSWORD, MYSQL_DATABASE

// Option B : valeurs directes (pour Aeonfree)
// Modifier directement les lignes dans config/database.php :
$host = 'sql308.hstn.me';
$db   = 'mseet_42573994_gsbsiniyat';
$user = 'mseet_42573994';
$pass = 'VOTRE_MOT_DE_PASSE_ICI';
```

> **Pour Aeonfree (hébergement mutualisé sans panneau env vars)**, éditez directement
> `config/database.php` et remplacez les `getenv(...)` par vos valeurs hardcodées.

---

## Étape 3 — Importer le schéma MySQL via PHPMyAdmin

1. Connectez-vous à **PHPMyAdmin** (lien dans votre panel Aeonfree)
2. Sélectionnez la base **`mseet_42573994_gsbsiniyat`**
3. Cliquez sur **Importer** → choisissez le fichier **`schema_mysql.sql`**
4. Cliquez **Exécuter**

✅ Toutes les tables, les niveaux et le compte admin seront créés.

---

## Étape 4 — Uploader les fichiers sur Aeonfree

Via le **Gestionnaire de fichiers** ou **FTP** (FileZilla) :

Uploadez **tous les fichiers** dans le dossier `public_html/` :

```
public_html/
├── index.php
├── login.php
├── logout.php
├── change_password.php
├── router.php          ← pas nécessaire sur Aeonfree (Apache gère le routing)
├── manifest.json
├── sw.js
├── offline.html
├── logo.png
├── schema_mysql.sql
├── config/
├── includes/
├── admin/
├── secretary/
├── api/
├── pdf/
├── assets/
├── lang/
├── vendor/             ← IMPORTANT : inclure le dossier vendor/ complet
└── scripts/
```

> ⚠️ **N'oubliez pas le dossier `vendor/`** — il contient Dompdf (génération PDF)
> et PhpSpreadsheet (export Excel). Sans lui, les reçus PDF ne fonctionneront pas.

---

## Étape 5 — Créer un fichier .htaccess

Créez un fichier `.htaccess` dans `public_html/` pour que les URLs fonctionnent :

```apache
Options -Indexes
DirectoryIndex index.php

RewriteEngine On
RewriteCond %{REQUEST_FILENAME} !-f
RewriteCond %{REQUEST_FILENAME} !-d
RewriteRule ^(.*)$ index.php [QSA,L]
```

---

## Étape 6 — Vérifier l'installation

Ouvrez votre navigateur et allez sur l'URL de votre site Aeonfree.
Vous devriez voir la page de connexion de GSB SINIYAT.

**Identifiants par défaut :**
- Login : `GSB-Siniyat`
- Mot de passe : `Siniyat@2026`

> ⚠️ Changez le mot de passe immédiatement après la première connexion !

---

## Résolution de problèmes courants

| Problème | Solution |
|----------|----------|
| Page blanche / Erreur 500 | Vérifier que `vendor/` est bien uploadé |
| "Connexion refusée" base de données | Vérifier les identifiants dans `config/database.php` |
| Reçus PDF qui ne s'affichent pas | Vérifier que l'extension PHP `mbstring` est activée |
| Images/CSS manquants | Vérifier que le dossier `assets/` est uploadé |

---

## À propos de la session

Ajoutez cette ligne dans `config/config.php` si votre hébergeur requiert un nom de session unique :

```php
session_name('GSB_SINIYAT_SESSION');
```
