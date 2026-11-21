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

En dev, aucun e-mail ne part vers une vraie boîte : tous les messages (invitations, mot de passe oublié)
arrivent dans Mailpit, à lire sur http://localhost:8025.

## Rôles

Un seul rôle par compte, hiérarchie `ROLE_SUPER_ADMIN` > `ROLE_ADMIN` > `ROLE_USER` : chaque rôle a aussi les droits du rôle inférieur.

| Rôle               | Voit                                   | Peut aussi                                                                                                   |
|--------------------|----------------------------------------|--------------------------------------------------------------------------------------------------------------|
| `ROLE_USER`        | albums et médias publics               | écrire des petits mots ; supprimer ses propres envois ; envoyer des médias dans les albums publics si un admin l'y autorise (`canUpload`) |
| `ROLE_ADMIN`       | publics et privés                      | régler Public / Privé ; gérer albums, tags, médias et commentaires ; gérer les comptes **membres**          |
| `ROLE_SUPER_ADMIN` | tout, y compris « Super-admin seul »   | **verrouiller** la visibilité (les admins ne peuvent plus la changer) ; nommer / retirer les admins ; supprimer des médias d'un album (base, fichiers, commentaires) : admin → Albums → « Supprimer des médias » |

Visibilité :

- un album est Public, Privé ou Super-admin seul (**privé par défaut**) ;
- un média reprend la visibilité de son album, sauf si on lui en donne une autre ;
- un média public dans un album privé reste caché.

Création d'un compte administrateur :

```bash
docker compose exec php php bin/console app:create-admin <email> "<Nom>" [--super]
```

Les membres sont invités depuis l'administration (`/admin`), qui leur envoie un e-mail.
Détails : [docs/architecture/overview.md](docs/architecture/overview.md), section « Rôles ».

## Démarrage

```bash
docker compose up -d --build
# ou dup
docker compose exec php composer install
# ou uphp
docker compose down
# ou ddo
```

Détails : [docs/devops/docker-setup.md](docs/devops/docker-setup.md)

Mise en production sur le VPS : [docs/devops/vps-preprod.md](docs/devops/vps-preprod.md) (nginx)
ou [docs/devops/cyberpanel.md](docs/devops/cyberpanel.md) (CyberPanel / OpenLiteSpeed)
