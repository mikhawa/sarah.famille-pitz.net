# Phase 0.1 : Environnement de développement Docker

> Date : 27 octobre 2026, 18 h 33

## Objectif
Disposer d'un environnement de développement local reproductible (PHP, serveur web, base de données, outils mail et SQL)
pour le site de Sarah, à partir du modèle `pre_cf2m` fourni.

## Actions realisees

### 1. Composition des services
**Fichier :** `docker-compose.yml`
- Nom de projet `sarah`, conteneurs `sarah_php`, `sarah_nginx`, `sarah_db`, `sarah_phpmyadmin`, `sarah_mailpit`.
- Ports : site sur `8085`, MariaDB sur `3307`, phpMyAdmin sur `8181`, Mailpit sur `8025` (web) et `1025` (SMTP).
- `DATABASE_URL` avec `charset=utf8mb4` et `MAILER_DSN` vers Mailpit injectés dans le conteneur PHP.
- Le conteneur PHP attend que MariaDB soit prête (`healthcheck` + `condition: service_healthy`).

### 2. Image PHP
**Fichier :** `docker/php/Dockerfile`
- Base `php:8.3-fpm` ; extensions `pdo_mysql`, `intl`, `zip`, `gd` (JPEG, WebP, FreeType), `exif`, `opcache`.
- Composer 2 et Symfony CLI installés dans l'image.
- Utilisateur non-root `sarah` aux mêmes UID/GID que l'hôte : les fichiers créés dans le conteneur n'appartiennent pas à root.

### 3. Configuration PHP et nginx
**Fichiers :** `docker/php/php.ini`, `docker/nginx/default.conf`
- Limites d'envoi relevées pour les photos et vidéos (256 Mo par fichier, alignées entre PHP, nginx et phpMyAdmin).
- nginx n'exécute que `public/index.php` : tout autre fichier `.php` renvoie 404.

## Fichiers crees
- `docker-compose.yml` : services de développement
- `docker/php/Dockerfile` : image PHP 8.3-FPM du projet
- `docker/php/php.ini` : limites d'envoi et réglages PHP
- `docker/nginx/default.conf` : virtual host Symfony
- `docs/devops/docker-setup.md` : documentation de l'environnement

## Fichiers modifies
Aucun.

## Verifications
| Point | Statut |
|-------|--------|
| Construction de l'image PHP | OK |
| Démarrage de tous les conteneurs | OK |
| nginx → PHP-FPM | OK |
| Connexion PDO à MariaDB | OK |
| Mailpit et phpMyAdmin (HTTP 200) | OK |
