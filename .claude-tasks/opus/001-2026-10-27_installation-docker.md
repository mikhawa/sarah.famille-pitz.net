# 001 — Installation de l'environnement Docker

- **Modèle utilisé** : Opus
- **Justification** : tâche de type DevOps (niveau Sonnet), réalisée directement dans la session Opus en cours plutôt que déléguée, vu sa taille réduite.

## Fichiers créés

- `docker-compose.yml`
- `docker/php/Dockerfile`
- `docker/php/php.ini`
- `docker/nginx/default.conf`
- `docs/devops/docker-setup.md`

## Résumé

Mise en place de la stack de dev à partir du modèle `pre_cf2m` fourni, renommée en `sarah_*` (conteneurs, base, utilisateur) :
PHP 8.3-FPM, nginx, MariaDB 11.4, phpMyAdmin, Mailpit.

Ajouts par rapport au modèle :
- Dockerfile PHP avec extensions pdo_mysql, intl, zip, gd (JPEG/WebP), exif, opcache + Composer + Symfony CLI
- utilisateur non-root aligné sur l'UID/GID de l'hôte
- limites d'upload à 256 Mo (photos/vidéos) côté PHP, nginx et phpMyAdmin
- `MAILER_DSN` vers Mailpit, `charset=utf8mb4` dans `DATABASE_URL`
- nom de projet compose `sarah`

Vérifié : build OK, tous les conteneurs démarrent, nginx → PHP-FPM répond, connexion PDO à MariaDB OK, Mailpit et phpMyAdmin répondent (HTTP 200).
