# Phase 6.4 : Mise en production sur un VPS CyberPanel

> Date : 28 octobre 2026, 16 h 14

## Objectif
Adapter la mise en production au VPS réel, administré par CyberPanel (OpenLiteSpeed, PHP LiteSpeed, un utilisateur
Linux par site), en gardant le déploiement depuis GitHub (branche `main`, lancement manuel).

## Actions realisees

### 1. Procédure CyberPanel
**Fichier :** `docs/devops/cyberpanel.md`
- Site, SSL et base créés dans l'interface ; paquets (ffmpeg), extensions et réglages de `lsphp83`, `proc_open`.
- Code cloné dans `<home>/app`, racine web déplacée sur `app/public` (vHost Conf) : `.env.local`, `var/` (photos),
  `vendor/` et `.git/` hors de la racine web.
- Déploiement GitHub : clé limitée à `deploy.sh` dans le compte du site, secret `DEPLOY_USER`, pas de sudo.
- Sauvegardes : compte MariaDB en lecture seule (mot de passe root de CyberPanel), dossier et cron dans le compte du site.
- Tableau de dépannage (500, 404, 403 sur les assets, vidéos refusées, SSH).

### 2. Script de déploiement réglable
**Fichier :** `bin/deploy.sh`
- `.deploy.env` (hors git) : `PHP_BIN` (le `php` du système n'est pas forcément PHP 8.3 sur CyberPanel),
  `COMPOSER_BIN` (Composer lancé avec ce même PHP, sinon son shebang prendrait le `php` du système),
  `DEPLOY_RELOAD_CMD` (rechargement de PHP, désormais facultatif).
- CyberPanel : opcache garde `validate_timestamps` activé, aucun redémarrage ni droit root nécessaire.
- nginx + PHP-FPM : `DEPLOY_RELOAD_CMD="sudo -n /usr/bin/systemctl reload php8.3-fpm"` (procédure mise à jour).

### 3. Serveur web OpenLiteSpeed
**Fichier :** `public/.htaccess`
- OpenLiteSpeed lit les règles de réécriture des `.htaccess` : tout ce qui n'est pas un fichier de `public/` va à `index.php`.
- Redirection HTTP → HTTPS limitée au domaine de production.
- `X-Accel-Redirect` est propre à nginx : `MEDIA_X_ACCEL_PREFIX` reste vide, PHP envoie les fichiers (avance rapide
  dans les vidéos comprise, déjà couverte par les tests).

### 4. Divers
**Fichiers :** `bin/backup.sh`, `.github/workflows/ci-deploy.yml`
- Sauvegarde : `mysqldump` si `mariadb-dump` n'existe pas (anciennes versions de MariaDB).
- Workflow : utilisateur SSH lu dans le secret `DEPLOY_USER` (`sarah` par défaut).

## Fichiers crees
- `docs/devops/cyberpanel.md` : procédure CyberPanel
- `public/.htaccess` : réécriture et HTTPS pour OpenLiteSpeed

## Fichiers modifies
- `bin/deploy.sh` : réglages `.deploy.env`, rechargement facultatif
- `bin/backup.sh` : repli sur `mysqldump`
- `.github/workflows/ci-deploy.yml` : `DEPLOY_USER`
- `.gitignore` : `.deploy.env`
- `docs/devops/vps-preprod.md` : renvoi vers CyberPanel, `DEPLOY_RELOAD_CMD`, secret `DEPLOY_USER`
- `docs/devops/prod/env.local.example` : `MEDIA_X_ACCEL_PREFIX` vide sous CyberPanel
- `README.md`, `.claude/MEMORY.md`

## Verifications
| Point | Statut |
|-------|--------|
| Tests PHPUnit (58 tests, 314 assertions) | OK |
| `deploy.sh` en bac à sable : PHP et Composer de `.deploy.env`, sans rechargement | OK |
| `deploy.sh` en bac à sable : avec `DEPLOY_RELOAD_CMD` | OK |
| Syntaxe de `deploy.sh`, `backup.sh` ; YAML du workflow | OK |
| Installation sur le VPS CyberPanel | À faire (procédure à suivre) |
| Règles `.htaccess` lues par OpenLiteSpeed | À vérifier sur le VPS |

## Problemes rencontres (si applicable)
- Plusieurs détails dépendent de la version de CyberPanel et du système (chemin du `php.ini`, shell de l'utilisateur
  du site, noms des paquets lsphp) : signalés « à vérifier » dans la procédure, avec la commande de contrôle.
