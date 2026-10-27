# Environnement Docker de développement

## Services

| Service      | Conteneur          | Accès hôte                     | Rôle                                  |
|--------------|--------------------|--------------------------------|---------------------------------------|
| `php`        | `sarah_php`        | —                              | PHP 8.3 FPM + Composer + Symfony CLI  |
| `nginx`      | `sarah_nginx`      | http://localhost:8085          | Serveur web (racine `public/`)        |
| `db`         | `sarah_db`         | `localhost:3307`               | MariaDB 11.4                          |
| `phpmyadmin` | `sarah_phpmyadmin` | http://localhost:8181          | Administration BDD                    |
| `mailpit`    | `sarah_mailpit`    | http://localhost:8025 (SMTP 1025) | Capture des e-mails                |

Identifiants BDD (dev uniquement) : base `sarah_db`, utilisateur `sarah_user` / `sarah_pass`, root `root`.

Variables injectées dans le conteneur PHP :

```
DATABASE_URL=mysql://sarah_user:sarah_pass@db:3306/sarah_db?serverVersion=11.4.10-MariaDB&charset=utf8mb4
MAILER_DSN=smtp://mailpit:1025
```

## Fichiers

- `docker-compose.yml` — définition des services
- `docker/php/Dockerfile` — image PHP (extensions : pdo_mysql, intl, zip, gd avec JPEG/WebP, exif, opcache)
- `docker/php/php.ini` — limites d'upload (256 Mo) pour photos et vidéos, fuseau Europe/Brussels
- `docker/nginx/default.conf` — vhost Symfony (`client_max_body_size 260M`)

Le conteneur PHP tourne avec un utilisateur `sarah` dont l'UID/GID correspond à celui de l'hôte (1000 par défaut),
pour que les fichiers créés (vendor, cache, uploads) n'appartiennent pas à root. Pour un autre UID :
`UID=$(id -u) GID=$(id -g) docker compose build php`.

## Commandes courantes

```bash
docker compose up -d --build          # démarrer
docker compose down                   # arrêter
docker compose exec php bash          # shell dans le conteneur PHP
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate
docker compose logs -f nginx          # logs
```

## Installation initiale de Symfony 7.4

Le dossier du projet n'étant pas vide, on crée le squelette dans un dossier temporaire puis on le fusionne :

```bash
docker compose exec php bash -c '
  composer create-project symfony/skeleton:"7.4.*" /tmp/sf --no-interaction &&
  cd /tmp/sf && composer config extra.symfony.docker false &&
  composer require webapp --no-interaction &&
  cp -rn /tmp/sf/. /var/www/html/ && rm -rf /tmp/sf'
```

`extra.symfony.docker false` empêche Symfony Flex de générer ses propres `compose.yaml` / `compose.override.yaml`,
qui entreraient en conflit avec `docker-compose.yml`.

Penser ensuite à fusionner le `.gitignore` généré par Symfony avec celui du dépôt.
