# sarah.famille-pitz.net

## Accès Docker en local

| Service        | Adresse                                   | Identifiants                     |
|----------------|-------------------------------------------|----------------------------------|
| Site Symfony   | http://localhost:8085                     | —                                |
| phpMyAdmin     | http://localhost:8181                     | connexion automatique            |
| Mailpit (web)  | http://localhost:8025                     | —                                |
| Mailpit (SMTP) | `localhost:1025`                          | —                                |
| MariaDB        | `localhost:3307` (depuis l'hôte)          | `sarah_user` / `sarah_pass`, base `sarah_db` (root : `root`) |

Depuis les conteneurs, la base est joignable sur `db:3306` et le SMTP sur `mailpit:1025`.

## Démarrage

```bash
docker compose up -d --build
docker compose exec php composer install
```

Détails : [docs/devops/docker-setup.md](docs/devops/docker-setup.md)

Mise en production sur le VPS : [docs/devops/vps-preprod.md](docs/devops/vps-preprod.md) (nginx)
ou [docs/devops/cyberpanel.md](docs/devops/cyberpanel.md) (CyberPanel / OpenLiteSpeed)
