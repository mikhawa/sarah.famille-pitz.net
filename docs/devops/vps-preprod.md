# Mise en production sur le VPS

Le site tourne **sans Docker** sur le VPS : nginx + PHP-FPM 8.3 + MariaDB 11.4, sous un utilisateur dédié `sarah`.

> **VPS sous CyberPanel (OpenLiteSpeed)** : suivre [cyberpanel.md](cyberpanel.md), qui adapte cette procédure.
Fichiers de configuration prêts à copier : [`docs/devops/prod/`](prod/).

| Fichier                         | Destination sur le VPS                                  |
|---------------------------------|---------------------------------------------------------|
| `prod/nginx-sarah.conf`         | `/etc/nginx/sites-available/sarah.famille-pitz.net`     |
| `prod/php-fpm-sarah.conf`       | `/etc/php/8.3/fpm/pool.d/sarah.conf`                    |
| `prod/env.local.example`        | `/var/www/sarah/.env.local` (à compléter, `chmod 600`)  |

## 1. Paquets

Ubuntu 24.04 LTS fournit PHP 8.3 ; MariaDB 11.4 vient du dépôt officiel MariaDB
(Ubuntu n'a que la 10.11 : https://mariadb.org/download/?t=repo-config).

```bash
sudo apt install nginx git unzip rsync ffmpeg certbot python3-certbot-nginx \
    php8.3-fpm php8.3-cli php8.3-mysql php8.3-intl php8.3-zip php8.3-gd php8.3-mbstring php8.3-xml php8.3-curl
# php8.3-exif et opcache sont inclus dans php8.3-common sur Ubuntu
sudo apt install mariadb-server   # après avoir ajouté le dépôt MariaDB 11.4
# Composer : https://getcomposer.org/download/ (installé dans /usr/local/bin/composer)
```

**ffmpeg est obligatoire** : sans lui, les vidéos sont refusées (métadonnées GPS non supprimables).

## 2. Utilisateur et dossiers

```bash
sudo adduser --disabled-password --gecos "" sarah
sudo usermod -aG sarah www-data            # nginx doit lire public/ et var/storage/media/
sudo mkdir -p /var/www/sarah /var/backups/sarah
sudo chown sarah:sarah /var/www/sarah /var/backups/sarah
sudo chmod 750 /var/www/sarah              # aucun accès pour les autres utilisateurs du serveur
sudo chmod 700 /var/backups/sarah
```

## 3. Base de données

```sql
CREATE DATABASE sarah_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sarah_user'@'127.0.0.1' IDENTIFIED BY '<mot de passe fort>';
GRANT ALL ON sarah_db.* TO 'sarah_user'@'127.0.0.1';
-- Compte en lecture seule, réservé aux sauvegardes
CREATE USER 'sarah_backup'@'localhost' IDENTIFIED BY '<autre mot de passe fort>';
GRANT SELECT, LOCK TABLES, SHOW VIEW, TRIGGER, EVENT ON sarah_db.* TO 'sarah_backup'@'localhost';
```

MariaDB n'écoute que localement (`bind-address = 127.0.0.1`, valeur par défaut).

## 4. Première installation du code

En tant que `sarah` (`sudo -iu sarah`) :

```bash
git clone https://github.com/mikhawa/sarah.famille-pitz.net.git /var/www/sarah
cd /var/www/sarah
cp docs/devops/prod/env.local.example .env.local && chmod 600 .env.local
nano .env.local                             # APP_SECRET, DATABASE_URL, MAILER_DSN…

composer install --no-dev --optimize-autoloader --classmap-authoritative
composer dump-env prod                      # .env.local.php, ignoré par git
php bin/console importmap:install
php bin/console asset-map:compile           # public/assets/, servi directement par nginx
php bin/console doctrine:migrations:migrate -n
php bin/console cache:clear
php bin/console app:create-admin <email> "<Nom>"
```

## 5. nginx, HTTPS et PHP-FPM

```bash
sudo cp docs/devops/prod/php-fpm-sarah.conf /etc/php/8.3/fpm/pool.d/sarah.conf
sudo rm /etc/php/8.3/fpm/pool.d/www.conf    # pool par défaut inutile
sudo systemctl restart php8.3-fpm

sudo mkdir -p /var/www/letsencrypt
sudo certbot certonly --webroot -w /var/www/letsencrypt -d sarah.famille-pitz.net
#   (la première fois, nginx doit déjà servir /.well-known/ : activer d'abord le seul bloc « listen 80 »)
sudo cp docs/devops/prod/nginx-sarah.conf /etc/nginx/sites-available/sarah.famille-pitz.net
sudo ln -s /etc/nginx/sites-available/sarah.famille-pitz.net /etc/nginx/sites-enabled/
sudo nginx -t && sudo systemctl reload nginx
```

Le renouvellement du certificat est automatique (timer `certbot.timer`).

### Envoi des fichiers par nginx (`X-Accel-Redirect`)

`MediaFileController` vérifie les droits, puis répond par un en-tête `X-Accel-Redirect: /_media_internal/…` au lieu de
lire le fichier. nginx envoie alors le fichier depuis la location `internal` : PHP-FPM est libéré tout de suite,
et nginx gère les plages d'octets (avance rapide dans les vidéos) et l'`ETag`.
Le préfixe `MEDIA_X_ACCEL_PREFIX` de `.env.local` doit correspondre à la location nginx. Vide : PHP envoie lui-même.

## 6. Déploiement par GitHub

Le site ne se met **jamais** à jour à la main : tout passe par GitHub (`.github/workflows/ci-deploy.yml`).

```
push sur une branche ou pull request ──► tests (préprod GitHub Actions : MariaDB 11.4, ffmpeg, PHPUnit, lint)
« Run workflow » sur main (à la main) ──► tests ──► si verts : SSH vers le VPS ──► bin/deploy.sh <commit testé>
```

Un push sur `main` ne déploie **pas** : pour mettre en ligne, GitHub → Actions → « Tests et déploiement » →
**Run workflow** → branche `main`. Les tests sont rejoués, puis le dernier commit de `main` est déployé.

`bin/deploy.sh` vérifie que le commit appartient à `origin/main`, lance une sauvegarde, se place sur ce commit, installe
les dépendances, compile les assets, joue les migrations, vide le cache et recharge PHP-FPM. Deux déploiements ne
peuvent pas se chevaucher (verrou côté serveur et `concurrency` côté GitHub).

### Mise en place (une seule fois)

**1. Clé SSH réservée au déploiement**, générée sur un poste de confiance :

```bash
ssh-keygen -t ed25519 -N "" -C "github-deploy-sarah" -f deploy_sarah
```

Sur le VPS, ajouter la clé publique dans `~sarah/.ssh/authorized_keys`, **limitée au script de déploiement** :

```
command="/var/www/sarah/bin/deploy.sh",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty ssh-ed25519 AAAA… github-deploy-sarah
```

Même volée, cette clé ne permet que de redéployer un commit déjà présent sur `main`.

**2. Rechargement de PHP-FPM sans mot de passe**, pour cette seule commande (`sudo visudo -f /etc/sudoers.d/sarah-deploy`) :

```
sarah ALL=(root) NOPASSWD: /usr/bin/systemctl reload php8.3-fpm
```

et dans `/var/www/sarah/.deploy.env` (ignoré par git, lu par `bin/deploy.sh`) :

```bash
DEPLOY_RELOAD_CMD="sudo -n /usr/bin/systemctl reload php8.3-fpm"
```

Le pool fourni coupe `opcache.validate_timestamps` : sans ce rechargement, l'ancien code resterait en mémoire.

**3. Accès du VPS au dépôt** : si le dépôt GitHub est privé, cloner en SSH avec une *deploy key* en lecture seule
(GitHub → Settings → Deploy keys), distincte de la clé ci-dessus.

**4. Secrets GitHub**, dans Settings → Environments → `production` (l'environnement peut aussi exiger une
validation manuelle avant chaque déploiement) :

| Secret               | Valeur                                                                  |
|----------------------|-------------------------------------------------------------------------|
| `DEPLOY_SSH_KEY`     | contenu de la clé privée `deploy_sarah` (à supprimer du poste ensuite)   |
| `DEPLOY_KNOWN_HOSTS` | résultat de `ssh-keyscan -p <port> <hôte>`, **vérifié** contre l'empreinte affichée sur le VPS (`ssh-keygen -lf /etc/ssh/ssh_host_ed25519_key.pub`) |
| `DEPLOY_HOST`        | nom ou adresse IP du VPS                                                |
| `DEPLOY_PORT`        | port SSH (facultatif, 22 par défaut)                                    |
| `DEPLOY_USER`        | utilisateur Linux du site (facultatif, `sarah` par défaut)              |

**5. Premier déploiement** : la section 4 (installation manuelle) doit avoir été faite une fois, et les identifiants
de sauvegarde (section 7) être en place, car `deploy.sh` commence par une sauvegarde ; ensuite, chaque mise en ligne
se lance avec « Run workflow ». En cas de panne de GitHub, le même script se lance à la main en tant que `sarah` :
`bin/deploy.sh $(git ls-remote origin main | cut -f1)`.

`var/storage/media/` et `.env.local` ne sont pas dans git : le déploiement n'y touche pas.

## 7. Sauvegardes

`bin/backup.sh` sauvegarde la base (dump compressé) et les médias (instantanés `rsync` : seuls les nouveaux fichiers
sont copiés, les autres sont des liens physiques). 14 jours conservés par défaut. Détail des variables en tête du script.

Identifiants du compte de sauvegarde, en tant que `sarah` :

```bash
printf '[client]\nuser=sarah_backup\npassword=<mot de passe>\n' > ~/.my.cnf && chmod 600 ~/.my.cnf
```

Tâche planifiée (`crontab -e` en tant que `sarah`) :

```cron
30 3 * * * /var/www/sarah/bin/backup.sh >> /var/www/sarah/var/log/backup.log 2>&1
```

**Copie hors du VPS indispensable** : une sauvegarde sur le même serveur ne protège ni d'une panne du disque,
ni d'une perte du VPS. Copier régulièrement `/var/backups/sarah/` ailleurs (autre serveur avec `rsync` par SSH,
ou stockage chiffré avec `rclone crypt`). Ce sont les photos d'une enfant : la copie distante doit être chiffrée
ou sur une machine de la famille.

### Restaurer

```bash
# Base (écrase le contenu actuel)
zcat /var/backups/sarah/db/sarah_db_<date>.sql.gz | mariadb -u sarah_user -p sarah_db
# Médias
rsync -a --delete /var/backups/sarah/media/<date>/ /var/www/sarah/var/storage/media/
```

Tester une restauration de temps en temps dans une base jetable (`CREATE DATABASE sarah_restore_check`, import, vérification,
`DROP DATABASE`) : une sauvegarde jamais relue n'est pas une sauvegarde.

## 8. Vérifications après mise en ligne

```bash
curl -sI http://sarah.famille-pitz.net/ | head -1                         # 301 vers https
curl -sI https://sarah.famille-pitz.net/ | grep -iE '^HTTP|location'      # 302 vers /login
curl -sI https://sarah.famille-pitz.net/login | grep -i x-robots-tag      # noindex, nofollow
curl -s -o /dev/null -w '%{http_code}\n' https://sarah.famille-pitz.net/media/1/file             # 302 (non connecté)
curl -s -o /dev/null -w '%{http_code}\n' https://sarah.famille-pitz.net/_media_internal/originals/  # 404
curl -s -o /dev/null -w '%{http_code}\n' https://sarah.famille-pitz.net/.env                     # 404
```

Puis, connecté dans un navigateur : envoyer une photo et une vidéo depuis l'admin, les afficher, avancer dans la vidéo,
vérifier qu'une invitation par e-mail arrive.

## Reste à faire

- Surveillance (disponibilité, espace disque de `var/storage/` et `/var/backups/`).
- Copie des sauvegardes hors du VPS (section 7).
