# Mise en production sur le VPS (AlmaLinux 9)

Le site tourne **sans Docker** sur un VPS **AlmaLinux 9** : nginx + PHP-FPM 8.3 + MariaDB 11.4, sous un utilisateur
dédié `sarah`. Toutes les commandes `sudo` se lancent depuis un compte administrateur (ou en root, sans `sudo`).

> **VPS sous CyberPanel (OpenLiteSpeed)** : suivre [cyberpanel.md](cyberpanel.md), qui adapte cette procédure.

Fichiers de configuration prêts à copier : [`docs/devops/prod/`](prod/).

| Fichier                         | Destination sur le VPS                                  |
|---------------------------------|---------------------------------------------------------|
| `prod/nginx-sarah.conf`         | `/etc/nginx/conf.d/sarah.famille-pitz.net.conf`         |
| `prod/php-fpm-sarah.conf`       | `/etc/php-fpm.d/sarah.conf`                             |
| `prod/env.local.example`        | `/var/www/sarah/.env.local` (à compléter, `chmod 600`)  |

## 1. Paquets

AlmaLinux 9 ne fournit ni PHP 8.3 ni MariaDB 11.4 ni ffmpeg dans ses dépôts de base : on ajoute **EPEL**, **Remi**
(PHP 8.3), **RPM Fusion** (ffmpeg) et le dépôt officiel **MariaDB**.

```bash
# EPEL et CRB (dépendances d'EPEL et de RPM Fusion)
sudo dnf install -y epel-release
sudo dnf config-manager --set-enabled crb

# PHP 8.3 (dépôt Remi, en remplacement du module PHP d'AlmaLinux)
sudo dnf install -y https://rpms.remirepo.net/enterprise/remi-release-9.rpm
sudo dnf module reset -y php
sudo dnf module enable -y php:remi-8.3
sudo dnf install -y php-fpm php-cli php-mysqlnd php-intl php-zip php-gd php-mbstring php-xml php-opcache composer

# ffmpeg (RPM Fusion « free »)
sudo dnf install -y https://mirrors.rpmfusion.org/free/el/rpmfusion-free-release-9.noarch.rpm
sudo dnf install -y ffmpeg

# MariaDB 11.4 (dépôt officiel : https://mariadb.org/download/?t=repo-config)
curl -LsS https://r.mariadb.com/downloads/mariadb_repo_setup | sudo bash -s -- --mariadb-server-version=11.4
sudo dnf install -y MariaDB-server MariaDB-client

# Serveur web, certificats, outils
sudo dnf install -y nginx certbot python3-certbot-nginx git unzip rsync policycoreutils-python-utils

sudo systemctl enable --now mariadb php-fpm nginx
```

Vérifications :

```bash
php -v                                         # PHP 8.3.x
php -m | grep -iE 'pdo_mysql|intl|zip|gd|exif|mbstring|opcache'
php -r 'print_r(array_intersect_key(gd_info(), ["WebP Support" => 1, "JPEG Support" => 1]));'   # 1 et 1
ffmpeg -version | head -1
mariadb --version                              # 11.4.x, pour serverVersion dans DATABASE_URL
nginx -v                                       # 1.20 ou plus
```

**ffmpeg est obligatoire** : sans lui, les vidéos sont refusées (métadonnées GPS non supprimables).

## 2. Utilisateur, dossiers et pare-feu

```bash
sudo useradd --create-home --shell /bin/bash sarah
sudo usermod -aG sarah nginx               # nginx doit lire public/ et var/storage/media/
sudo mkdir -p /var/www/sarah
sudo chown sarah:sarah /var/www/sarah
sudo chmod 750 /var/www/sarah              # aucun accès pour les autres utilisateurs du serveur

# Pare-feu (firewalld) : HTTP et HTTPS ; SSH est ouvert par défaut
sudo firewall-cmd --permanent --add-service=http --add-service=https
sudo firewall-cmd --reload
```

### SELinux

AlmaLinux 9 active SELinux (`getenforce` → `Enforcing`). Il faut indiquer que nginx et PHP-FPM peuvent lire le site,
écrire dans `var/` et joindre la base et le serveur SMTP. **Ne pas désactiver SELinux.**

```bash
sudo semanage fcontext -a -t httpd_sys_content_t    "/var/www/sarah(/.*)?"
sudo semanage fcontext -a -t httpd_sys_rw_content_t "/var/www/sarah/var(/.*)?"
sudo restorecon -Rv /var/www/sarah

sudo setsebool -P httpd_can_network_connect_db 1   # MariaDB par 127.0.0.1
sudo setsebool -P httpd_can_network_connect 1      # serveur SMTP (invitations, mots de passe oubliés)
```

`restorecon` est à relancer si des fichiers sont créés ailleurs puis déplacés dans `/var/www/sarah`.
En cas de refus inexpliqué (page 500, envoi de vidéo qui échoue, e-mail qui ne part pas) :
`sudo ausearch -m avc -ts recent` montre ce que SELinux a bloqué.

## 3. Base de données

```bash
sudo mariadb-secure-installation   # répondre oui partout (pas d'utilisateur anonyme, pas de base test)
sudo mariadb                       # root se connecte par le compte système, sans mot de passe
```

```sql
CREATE DATABASE sarah_db CHARACTER SET utf8mb4 COLLATE utf8mb4_unicode_ci;
CREATE USER 'sarah_user'@'127.0.0.1' IDENTIFIED BY '<mot de passe fort>';
GRANT ALL ON sarah_db.* TO 'sarah_user'@'127.0.0.1';
```

MariaDB n'écoute que localement (`bind-address = 127.0.0.1`, valeur par défaut).

## 4. Première installation du code

En tant que `sarah` (`sudo -iu sarah`) :

```bash
git clone https://github.com/mikhawa/sarah.famille-pitz.net.git /var/www/sarah
cd /var/www/sarah
mkdir -p var/sessions                       # sessions PHP (voir prod/php-fpm-sarah.conf)
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
# PHP-FPM : pool dédié sous l'utilisateur sarah (le pool www d'origine tourne sous apache)
sudo cp /var/www/sarah/docs/devops/prod/php-fpm-sarah.conf /etc/php-fpm.d/sarah.conf
sudo mv /etc/php-fpm.d/www.conf /etc/php-fpm.d/www.conf.disabled
sudo systemctl restart php-fpm

# Certificat : nginx sert d'abord le seul défi Let's Encrypt en HTTP
sudo mkdir -p /var/www/letsencrypt
sudo tee /etc/nginx/conf.d/sarah.famille-pitz.net.conf >/dev/null <<'EOF'
server {
    listen 80;
    server_name sarah.famille-pitz.net;
    location /.well-known/acme-challenge/ { root /var/www/letsencrypt; }
}
EOF
sudo nginx -t && sudo systemctl reload nginx
sudo certbot certonly --webroot -w /var/www/letsencrypt -d sarah.famille-pitz.net

# Puis la configuration complète
sudo cp /var/www/sarah/docs/devops/prod/nginx-sarah.conf /etc/nginx/conf.d/sarah.famille-pitz.net.conf
sudo nginx -t && sudo systemctl reload nginx
sudo systemctl enable --now certbot-renew.timer     # renouvellement automatique du certificat
```

`nginx-sarah.conf` inclut `/etc/letsencrypt/options-ssl-nginx.conf`, fourni par `python3-certbot-nginx`.
S'il manque : `sudo certbot install --nginx` le crée, ou retirer la ligne `include` (nginx garde ses réglages TLS par défaut).

### Envoi des fichiers par nginx (`X-Accel-Redirect`)

`MediaFileController` vérifie les droits, puis répond par un en-tête `X-Accel-Redirect: /_media_internal/…` au lieu de
lire le fichier. nginx envoie alors le fichier depuis la location `internal` : PHP-FPM est libéré tout de suite,
et nginx gère les plages d'octets (avance rapide dans les vidéos) et l'`ETag`.
Le préfixe `MEDIA_X_ACCEL_PREFIX` de `.env.local` doit correspondre à la location nginx. Vide : PHP envoie lui-même.

## 6. Mise à jour du site (git, branche main)

Chaque push sur `main` met le VPS à jour **automatiquement**, une fois les tests verts (`.github/workflows/ci-deploy.yml`,
job `deploy`). Un push sur une autre branche ou une pull request ne déploie jamais. Même principe que sur CyberPanel :
la procédure détaillée (clé, secrets GitHub, dépannage) est dans [cyberpanel.md, section 6.4](cyberpanel.md#64-mise-à-jour-automatique-depuis-github-push-sur-main) ;
seules les valeurs changent ici (utilisateur `sarah`, `/var/www/sarah`, `php` et `composer` du système). **Pas de
sauvegarde avant les migrations** (choix de l'utilisateur).

En tant que `sarah` (jamais root : un composer ou une console lancés en root rendent `var/` illisible pour PHP-FPM) :

```bash
whoami; echo "$HOME"                   # sarah, pas root
ssh-keygen -t ed25519 -N '' -C github-deploy-sarah -f ~/deploy_sarah

APP=/var/www/sarah
PHP=$(command -v php)
COMPOSER=$(command -v composer)
echo "$PHP $COMPOSER"                  # deux chemins absolus, sinon s'arrêter

# Le pool PHP-FPM fourni coupe opcache.validate_timestamps : sans rechargement, l'ancien code resterait en mémoire.
# Le rechargement passe par sudo sans mot de passe, limité à cette seule commande (voir plus bas).
CMD="cd $APP && git diff --quiet && git diff --cached --quiet && git pull --ff-only origin main && $PHP $COMPOSER install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress && $PHP $COMPOSER dump-env prod && $PHP bin/console importmap:install --no-interaction && $PHP bin/console asset-map:compile --no-interaction && $PHP bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration && $PHP bin/console cache:clear --no-interaction && sudo -n /usr/bin/systemctl reload php-fpm"

mkdir -p ~/.ssh && chmod 700 ~/.ssh
echo "command=\"$CMD\",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty $(cat ~/deploy_sarah.pub)" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
```

Autorisation du rechargement (en root ou avec `sudo`, une seule fois) :

```bash
echo 'sarah ALL=(root) NOPASSWD: /usr/bin/systemctl reload php-fpm' | sudo tee /etc/sudoers.d/sarah-reload
sudo chmod 440 /etc/sudoers.d/sarah-reload && sudo visudo -cf /etc/sudoers.d/sarah-reload
```

(Si `opcache.validate_timestamps` était activé, le `&& sudo -n … reload php-fpm` final et ce fichier sudoers seraient inutiles.)

Test depuis le VPS : `ssh -i ~/deploy_sarah -o BatchMode=yes -o StrictHostKeyChecking=accept-new sarah@localhost`. Secrets (*Settings* → *Environments* →
`production`) : `DEPLOY_SSH_KEY` (`cat ~/deploy_sarah`), `DEPLOY_KNOWN_HOSTS` (`ssh-keyscan -p 22 <hôte>`), `DEPLOY_HOST`,
`DEPLOY_USER` (`sarah`), `DEPLOY_PORT` (facultatif) ; puis `rm -f ~/deploy_sarah ~/deploy_sarah.pub`.

**Secours à la main** (même utilisateur) :

```bash
cd /var/www/sarah
git status --short                     # doit être vide
git pull --ff-only origin main         # refuse s'il faut fusionner : ne rien forcer
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress
composer dump-env prod
php bin/console importmap:install --no-interaction
php bin/console asset-map:compile --no-interaction
php bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
php bin/console cache:clear --no-interaction
sudo systemctl reload php-fpm
```

Si le dépôt GitHub est privé, cloner en SSH avec une *deploy key* en lecture seule (GitHub → Settings → Deploy keys).
`var/storage/media/` et `.env.local` ne sont pas dans git : la mise à jour n'y touche pas.

## 7. Sauvegardes

Pas de sauvegarde automatique pour le moment (choix de l'utilisateur).

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

- Surveillance (disponibilité, espace disque de `var/storage/`).
