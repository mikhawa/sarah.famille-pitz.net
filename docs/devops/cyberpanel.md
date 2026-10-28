# Mise en production sur un VPS CyberPanel

CyberPanel sert les sites avec **OpenLiteSpeed** (pas nginx) et le PHP de LiteSpeed (`lsphp83`, pas PHP-FPM).
Chaque site a son propre utilisateur Linux. Cette page remplace les sections 1, 2, 4 et 5 de
[vps-preprod.md](vps-preprod.md) ; le déploiement par GitHub (section 6) et les sauvegardes (section 7) restent
valables, avec les adaptations ci-dessous.

Les points marqués **à vérifier** dépendent de la version de CyberPanel et du système (Ubuntu ou AlmaLinux).

Dans cette page :

- `<user>` : l'utilisateur Linux du site, affiché dans CyberPanel → *Websites* → *List Websites*
- `<home>` : son dossier, `/home/sarah.famille-pitz.net`
- `PHP=/usr/local/lsws/lsphp83/bin/php` : le PHP 8.3 de LiteSpeed (le `php` du système peut être une autre version)

## Vue d'ensemble

```
GitHub (main) ──Run workflow──► tests ──► SSH <user>@VPS ──► <home>/app/bin/deploy.sh <commit>
                                                                  │
<home>/app/            code du dépôt (git clone), var/storage/media/ = photos et vidéos
<home>/app/public/     racine web d'OpenLiteSpeed (au lieu de public_html)
<home>/app/.env.local  secrets (hors git)
<home>/app/.deploy.env réglages de deploy.sh pour CyberPanel (hors git)
```

## 1. Créer le site et la base (interface CyberPanel)

1. **Websites → Create Website** : domaine `sarah.famille-pitz.net`, PHP **8.3**.
2. **SSL → Manage SSL** : émettre le certificat Let's Encrypt.
3. **Databases → Create Database** pour ce site. CyberPanel préfixe les noms : **noter le nom exact de la base,
   de l'utilisateur et le mot de passe**.
4. Version de MariaDB, pour `DATABASE_URL` : `mariadb --version` (ou `mysql --version`), par exemple `10.11.8`.

## 2. Paquets et PHP (en root)

```bash
# ffmpeg est obligatoire : sans lui, les vidéos sont refusées (GPS non supprimable)
# AlmaLinux 9
dnf install -y epel-release
dnf config-manager --set-enabled crb
dnf install -y https://mirrors.rpmfusion.org/free/el/rpmfusion-free-release-9.noarch.rpm
dnf install -y ffmpeg git unzip rsync
# Ubuntu : apt install ffmpeg git unzip rsync

$PHP -v                                     # PHP 8.3.x
$PHP -m | grep -iE 'pdo_mysql|intl|zip|gd|exif|mbstring|xml|curl|opcache'
```

Extensions manquantes : **PHP → Install Extensions** dans CyberPanel (ex. `lsphp83-intl`), ou `dnf install lsphp83-intl`
(Ubuntu : `apt install lsphp83-intl`).
GD doit lire le **WebP et le JPEG** : `$PHP -r 'print_r(gd_info());'` (« WebP Support » et « JPEG Support » à 1).

**Composer** : `command -v composer`, sinon installation dans `/usr/local/bin/composer` (https://getcomposer.org/download/).

**SELinux** (AlmaLinux) : `getenforce`. L'installateur de CyberPanel le désactive en général ; s'il affiche
`Enforcing` et qu'une page, un envoi de vidéo ou un e-mail échoue sans explication, `ausearch -m avc -ts recent`
montre ce qui a été bloqué (voir aussi la section SELinux de [vps-preprod.md](vps-preprod.md#selinux)).

**`proc_open` doit être autorisé** (ffmpeg est lancé par Symfony Process) :

```bash
$PHP -r 'var_dump(function_exists("proc_open"));'   # bool(true) attendu
```

### Réglages PHP

**PHP → Edit PHP Configs → PHP 8.3**, ou directement le fichier :

- AlmaLinux / Rocky : `/usr/local/lsws/lsphp83/etc/php.ini`, opcache dans `/usr/local/lsws/lsphp83/etc/php.d/10-opcache.ini`
- Ubuntu : `/usr/local/lsws/lsphp83/etc/php/8.3/litespeed/php.ini` (**à vérifier**)

Valeurs attendues (celles déjà plus élevées, comme `memory_limit = 1024M` ou `post_max_size = 3G`, peuvent rester) :

```ini
memory_limit = 512M
upload_max_filesize = 256M
post_max_size = 1G
max_file_uploads = 50
max_execution_time = 300
max_input_time = 300
date.timezone = Europe/Brussels
expose_php = Off
display_errors = Off
```

Laisser `opcache.validate_timestamps` à sa valeur par défaut (activé) : PHP voit alors le nouveau code après un
déploiement sans redémarrage. Puis redémarrer LiteSpeed : `systemctl restart lsws`.

Les envois de l'admin partent par lots (20 fichiers / 500 Mo au plus) : la limite de taille de requête
d'OpenLiteSpeed (*WebAdmin* `https://<ip>:7080` → *Server Configuration* → *Tuning* → *Max Request Body Size*,
2 Go par défaut) suffit. Si un envoi de grosses vidéos échoue, regarder aussi *Connection Timeout* (même écran).

## 3. Accès SSH de l'utilisateur du site

L'utilisateur du site doit pouvoir ouvrir une session SSH (par clé). Selon la version : **Websites → Manage →
SSH Access**, ou en root `usermod -s /bin/bash <user>` (**à vérifier** : certaines versions donnent `/sbin/nologin`).

Pour la suite, ouvrir une session en tant que `<user>` (`su - <user> -s /bin/bash` depuis root) et ajouter dans
`~/.bashrc` :

```bash
export PATH=/usr/local/lsws/lsphp83/bin:$PATH     # « php » = PHP 8.3 de LiteSpeed, composer compris
```

## 4. Installer le code (en tant que `<user>`)

```bash
cd ~                                   # <home>
git clone https://github.com/mikhawa/sarah.famille-pitz.net.git app
#   dépôt privé : clé SSH en lecture seule (GitHub → Settings → Deploy keys) et git clone git@github.com:…
cd app
cp docs/devops/prod/env.local.example .env.local && chmod 600 .env.local
nano .env.local
```

Dans `.env.local` :

- `DATABASE_URL` : base, utilisateur et mot de passe **créés par CyberPanel**, hôte `127.0.0.1`,
  `serverVersion=<version>-MariaDB` (étape 1.4)
- `APP_SECRET` : `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`
- `MAILER_DSN` : un vrai serveur SMTP. CyberPanel peut créer une boîte mail (*Email → Create Email*) :
  `smtp://noreply%40sarah.famille-pitz.net:<mot de passe>@127.0.0.1:587` (**à vérifier** selon la configuration mail du VPS)
- `MEDIA_X_ACCEL_PREFIX=` **vide** : OpenLiteSpeed ne comprend pas l'en-tête nginx `X-Accel-Redirect`. PHP envoie
  lui-même les fichiers, avance rapide dans les vidéos comprise (testé par la suite de tests).

Réglages du script de déploiement :

```bash
cat > .deploy.env <<'EOF'
PHP_BIN=/usr/local/lsws/lsphp83/bin/php
COMPOSER_BIN=/usr/local/bin/composer        # résultat de « command -v composer »
EOF
chmod 600 .deploy.env
```

Première installation :

```bash
composer install --no-dev --optimize-autoloader --classmap-authoritative
composer dump-env prod
php bin/console importmap:install
php bin/console asset-map:compile
php bin/console doctrine:migrations:migrate -n
php bin/console cache:clear
php bin/console app:create-admin <email> "<Nom>"
chmod 700 var                               # photos, journaux, cache : jamais servis directement
```

## 5. Faire pointer le site sur `app/public` (interface CyberPanel)

**Websites → List Websites → Manage → vHost Conf** : remplacer la ligne `docRoot` par

```
docRoot                   $VH_ROOT/app/public
```

et vérifier que le bloc `rewrite` lit les `.htaccess` (le dépôt fournit `public/.htaccess`, qui envoie tout vers Symfony) :

```
rewrite  {
  enable                  1
  autoLoadHtaccess        1
}
```

Enregistrer, puis redémarrer LiteSpeed (bouton dans CyberPanel ou `systemctl restart lsws`).

Ainsi `.env.local`, `var/` (photos comprises), `vendor/` et `.git/` sont **hors de la racine web**.
Droits : OpenLiteSpeed doit pouvoir lire `app/public/` ; reprendre le groupe de `public_html`
(`ls -ld ~/public_html`, puis `chgrp -R <groupe> ~/app/public && chmod -R g+rX ~/app/public`) si les fichiers
statiques (CSS, JS) répondent 403.

## 6. Déploiement depuis GitHub

Identique à [vps-preprod.md, section 6](vps-preprod.md#6-déploiement-par-github), avec ces différences :

- La clé publique de déploiement va dans `<home>/.ssh/authorized_keys` (`chmod 700 ~/.ssh`, `chmod 600` le fichier) :

  ```
  command="/home/sarah.famille-pitz.net/app/bin/deploy.sh",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty ssh-ed25519 AAAA… github-deploy-sarah
  ```

- **Pas de règle sudoers** : aucun redémarrage n'est nécessaire (opcache relit les fichiers modifiés).
- Secrets GitHub (Settings → Environments → `production`) : `DEPLOY_SSH_KEY`, `DEPLOY_KNOWN_HOSTS`, `DEPLOY_HOST`,
  `DEPLOY_PORT` si le port SSH n'est pas 22, et **`DEPLOY_USER` = `<user>`**.
- Pare-feu : CyberPanel active souvent un pare-feu (*Security → Firewall*) ; le port SSH doit rester ouvert
  aux serveurs de GitHub Actions.

Mettre en ligne : GitHub → **Actions** → « Tests et déploiement » → **Run workflow** → branche `main`.
Les tests sont rejoués ; s'ils passent, le VPS sauvegarde puis installe le dernier commit de `main`.

## 7. Sauvegardes

Comme [vps-preprod.md, section 7](vps-preprod.md#7-sauvegardes), avec :

- Compte MariaDB de sauvegarde créé en root (le mot de passe root MariaDB de CyberPanel est dans
  `/etc/cyberpanel/mysqlPassword`) :

  ```bash
  mariadb -u root -p"$(cat /etc/cyberpanel/mysqlPassword)" -e "
    CREATE USER 'sarah_backup'@'localhost' IDENTIFIED BY '<mot de passe fort>';
    GRANT SELECT, LOCK TABLES, SHOW VIEW, TRIGGER, EVENT ON \`<base>\`.* TO 'sarah_backup'@'localhost';"
  ```

- Dossier de sauvegarde dans le compte du site, base au nom donné par CyberPanel : dans `~/.bashrc` **et** en tête
  de la tâche cron, `BACKUP_DIR=$HOME/backups BACKUP_DB_NAME=<base>`.
- Tâche planifiée : *Websites → Manage → Cron Jobs* (ou `crontab -e` en tant que `<user>`) :

  ```cron
  30 3 * * * BACKUP_DIR=$HOME/backups BACKUP_DB_NAME=<base> $HOME/app/bin/backup.sh >> $HOME/app/var/log/backup.log 2>&1
  ```

  `deploy.sh` lance aussi une sauvegarde avant chaque mise à jour : mettre les deux mêmes variables dans `.deploy.env`
  (`export BACKUP_DIR=…`, `export BACKUP_DB_NAME=…`).
- Copie hors du VPS toujours indispensable.

## 8. Vérifications après mise en ligne

Celles de [vps-preprod.md, section 8](vps-preprod.md#8-vérifications-après-mise-en-ligne), sauf la ligne
`/_media_internal/` (sans objet ici), plus :

```bash
curl -s -o /dev/null -w '%{http_code}\n' https://sarah.famille-pitz.net/.env.local     # 404 (hors racine web)
curl -sI http://sarah.famille-pitz.net/ | grep -iE '^HTTP|location'                    # 301 vers https
```

Puis, connecté : envoyer une photo et une vidéo depuis l'admin, les afficher, avancer dans la vidéo, ouvrir la
visionneuse, vérifier qu'une invitation par e-mail arrive.

## En cas de problème

| Symptôme                                  | Piste                                                                          |
|-------------------------------------------|--------------------------------------------------------------------------------|
| Page blanche ou 500                       | `~/app/var/log/prod.log` ; `/usr/local/lsws/logs/error.log` (en root)          |
| 404 sur toutes les pages sauf l'accueil   | `.htaccess` non lu : bloc `rewrite` du vHost (étape 5)                         |
| CSS / JS en 403                           | droits de lecture de `app/public/` pour OpenLiteSpeed (étape 5)                |
| Vidéos refusées à l'envoi                 | ffmpeg absent ou `proc_open` désactivé (étape 2)                               |
| Le déploiement GitHub échoue en SSH       | `DEPLOY_USER`, `DEPLOY_PORT`, pare-feu, `authorized_keys`, shell du site (étape 3) |
| « Composer detected issues in your platform » | `PHP_BIN` / `COMPOSER_BIN` de `.deploy.env` pointent vers un autre PHP     |
