# Mise en production sur un VPS CyberPanel

CyberPanel sert les sites avec **OpenLiteSpeed** (pas nginx) et le PHP de LiteSpeed (`lsphp83`, pas PHP-FPM).
Chaque site a son propre utilisateur Linux. Cette page remplace les sections 1, 2, 4 et 5 de
[vps-preprod.md](vps-preprod.md) ; la mise à jour du site (section 6) la remplace aussi : elle se fait avec git (branche `main`),
**automatiquement à chaque push sur `main`** une fois les tests verts (section 6.4), ou à la main en secours (6.1 à 6.3).

Les points marqués **à vérifier** dépendent de la version de CyberPanel et du système (Ubuntu ou AlmaLinux).

Dans cette page :

- `<user>` : l'utilisateur Linux du site. `sarah.famille-pitz.net` est un **sous-domaine (child domain) de
  `famille-pitz.net`** : il appartient à l'utilisateur du domaine parent, affiché dans CyberPanel → *Websites* →
  *List Websites* sur la ligne `famille-pitz.net`
- `<home>` : son dossier, `/home/famille-pitz.net`
- `<site>` : le dossier du sous-domaine créé par CyberPanel, `<home>/sarah.famille-pitz.net` (contient `public_html`)
- `<app>` : le code, `<site>/sarah.famille-pitz.net`, soit
  **`/home/famille-pitz.net/sarah.famille-pitz.net/sarah.famille-pitz.net`**
- `PHP=/usr/local/lsws/lsphp83/bin/php` : le PHP 8.3 de LiteSpeed (le `php` du système peut être une autre version)

> **Tout se fait en tant que `<user>`, jamais en root** (sauf la section 2 et le début de la section 6.1 marqués « en root ») : un
> `composer` ou un `bin/console` lancé en root crée dans `var/` des fichiers que PHP ne peut plus modifier
> (erreur 500 rencontrée sur `/admin` : « Failed to create …/var/cache/prod/asset_mapper: mkdir(): Permission denied »).
> Réparation, en root : `cd <app> && chown -R "$(stat -c '%U:%G' .)" var/`, puis
> `su -s /bin/bash "$(stat -c '%U' .)" -c "$PHP bin/console cache:clear"`.

## Vue d'ensemble

```
GitHub (branche main) ──► tests ──► SSH (clé limitée) ──► <user>@VPS : git pull + composer + migrations (section 6.4)
                    ◄── git fetch / git pull ── <user>@VPS   (secours à la main, sections 6.1 à 6.3)

<app>/            code du dépôt (git clone), var/storage/media/ = photos et vidéos
<app>/public/     racine web d'OpenLiteSpeed (au lieu de <site>/public_html)
<app>/.env.local  secrets (hors git)
```

## 1. Créer le site et la base (interface CyberPanel)

1. **Websites → Create Website** (ou *Child Domains* de `famille-pitz.net`, cas de ce VPS) :
   domaine `sarah.famille-pitz.net`, PHP **8.3**.
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
cd ~/sarah.famille-pitz.net            # <site>
git clone https://github.com/mikhawa/sarah.famille-pitz.net.git   # crée <app>
#   dépôt privé : clé SSH en lecture seule (GitHub → Settings → Deploy keys) et git clone git@github.com:…
cd sarah.famille-pitz.net              # <app>
cp docs/devops/prod/env.local.example .env.local && chmod 600 .env.local
nano .env.local
```

Dans `.env.local` :

- `DATABASE_URL` : base, utilisateur et mot de passe **créés par CyberPanel**, hôte `127.0.0.1`,
  `serverVersion=<version>-MariaDB` (étape 1.4)
- `APP_SECRET` : `php -r 'echo bin2hex(random_bytes(32)), PHP_EOL;'`
- `MAILER_DSN` : un vrai serveur SMTP. CyberPanel peut créer une boîte mail (*Email → Create Email*) :
  `smtp://noreply%40sarah.famille-pitz.net:<mot de passe>@127.0.0.1:587` (**à vérifier** selon la configuration mail du VPS)
- `MAILER_FROM` : adresse d'expéditeur avec un **vrai `@`** (`"Site de Sarah <noreply@sarah.famille-pitz.net>"`).
  Le `%40` ne s'écrit **que** dans l'identifiant de `MAILER_DSN` ; ailleurs, Symfony refuse l'adresse
  (`RfcComplianceException … does not comply with addr-spec of RFC 2822`) et tout envoi d'e-mail échoue.
  Après toute modification de `.env.local` : `composer dump-env prod` (le site lit `.env.local.php`) puis `cache:clear`.
- `MEDIA_X_ACCEL_PREFIX=` **vide** : OpenLiteSpeed ne comprend pas l'en-tête nginx `X-Accel-Redirect`. PHP envoie
  lui-même les fichiers, avance rapide dans les vidéos comprise (testé par la suite de tests).

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

## 5. Faire pointer le site sur `<app>/public` (interface CyberPanel)

**Websites → List Websites → Manage → vHost Conf** (du sous-domaine) : remplacer la ligne `docRoot` par

```
docRoot                   /home/famille-pitz.net/sarah.famille-pitz.net/sarah.famille-pitz.net/public
```

(chemin complet, plus sûr que `$VH_ROOT`, dont la valeur diffère entre domaine et sous-domaine)

et vérifier que le bloc `rewrite` lit les `.htaccess` (le dépôt fournit `public/.htaccess`, qui envoie tout vers Symfony) :

```
rewrite  {
  enable                  1
  autoLoadHtaccess        1
}
```

Enregistrer, puis redémarrer LiteSpeed (bouton dans CyberPanel ou `systemctl restart lsws`).

Ainsi `.env.local`, `var/` (photos comprises), `vendor/` et `.git/` sont **hors de la racine web**.
Droits : OpenLiteSpeed doit pouvoir lire `<app>/public/` ; reprendre le groupe de `public_html`
(`ls -ld <site>/public_html`, puis `chgrp -R <groupe> <app>/public && chmod -R g+rX <app>/public`) si les fichiers
statiques (CSS, JS) répondent 403.

## 6. Mise à jour du site (git, branche main)

Chaque push sur `main` met le VPS à jour **automatiquement**, une fois les tests verts (section 6.4). Les sections
6.1 à 6.3 décrivent la même mise à jour **à la main** : c'est la procédure de secours (clé SSH ou GitHub indisponibles)
et celle du premier démarrage. Le dépôt GitHub est **public** : le VPS fait `git fetch` / `git pull` sans clé.

Prérequis : sections 3 (accès SSH de l'utilisateur du site) et 4 (code dans `<app>`) terminées.

### 6.1 Devenir l'utilisateur du site (depuis root) — et le vérifier

```bash
APP=/home/famille-pitz.net/sarah.famille-pitz.net/sarah.famille-pitz.net
SITEUSER=$(stat -c %U "$APP")          # propriétaire du code = utilisateur du site
echo "$SITEUSER"                       # ne doit PAS être root
su - "$SITEUSER" -s /bin/bash
whoami          # l'utilisateur du site, jamais root
echo "$HOME"    # /home/famille-pitz.net
```

> **Piège rencontré** : lancer composer ou la console en root crée dans `var/` des fichiers que PHP ne peut plus
> modifier (voir l'encadré en tête de page). Si `stat` affiche `root`, le code a été cloné en root :
> `chown -R <user>:<groupe> "$APP"` (nom indiqué dans CyberPanel → *Websites* → *List Websites*).

### 6.2 Récupérer la branche main

```bash
cd /home/famille-pitz.net/sarah.famille-pitz.net/sarah.famille-pitz.net     # <app>
git status --short                     # doit être vide (aucune modification locale sur le serveur)
git fetch origin main
git checkout main
git pull --ff-only origin main         # refuse s'il faut fusionner : ne rien forcer, chercher pourquoi
git log -1 --oneline                   # le commit attendu
```

### 6.3 Dépendances, assets, migrations, cache

Avec le PHP de LiteSpeed (composer lancé par ce PHP, car son « php » par défaut peut être une autre version) :

```bash
PHP=/usr/local/lsws/lsphp83/bin/php
COMPOSER=$(command -v composer)        # généralement /usr/local/bin/composer

$PHP "$COMPOSER" install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress
$PHP "$COMPOSER" dump-env prod
$PHP bin/console importmap:install --no-interaction
$PHP bin/console asset-map:compile --no-interaction
$PHP bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration
$PHP bin/console cache:clear --no-interaction
```

- **Pas de redémarrage** de LiteSpeed : `opcache.validate_timestamps` est activé, PHP relit les fichiers modifiés.
- `var/storage/media/` et `.env.local` ne sont pas dans git : la mise à jour n'y touche pas.
- Retour en arrière : noter `git log -1` avant la mise à jour, puis `git checkout <commit précédent>` et les mêmes
  commandes ; une migration déjà jouée ne se défait pas toute seule.

### 6.4 Mise à jour automatique depuis GitHub (push sur main)

Principe : `.github/workflows/ci-deploy.yml` lance les tests ; s'ils passent et que le push vise `main`, le job
`deploy` ouvre une connexion SSH vers le VPS avec une clé dédiée. Cette clé ne sait **rien faire d'autre** que la mise
à jour : la commande est écrite en dur dans `~/.ssh/authorized_keys` (option `command="…"`), GitHub n'envoie aucune
commande. Un push sur une autre branche ou une pull request ne déploie jamais. **Pas de sauvegarde avant les
migrations** (choix de l'utilisateur).

La mise à jour refuse de s'exécuter si la copie du serveur contient des modifications (`git diff` non vide) et ne fait
que des `git pull --ff-only` : en cas d'échec, rien n'est forcé et le job GitHub passe au rouge.

**a) Créer la clé, en tant que `<user>`** (jamais en root : vérifier `whoami` et `$HOME`, voir 6.1)

```bash
whoami; echo "$HOME"                   # <user> et /home/famille-pitz.net, surtout pas root ni /root
ssh-keygen -t ed25519 -N '' -C github-deploy-sarah -f ~/deploy_sarah
```

**b) Imposer la commande dans `authorized_keys`** (le `PATH` d'une session SSH non interactive est minimal : tout est en
chemins absolus ; la ligne ne contient aucun guillemet double imbriqué)

```bash
APP=/home/famille-pitz.net/sarah.famille-pitz.net/sarah.famille-pitz.net
PHP=/usr/local/lsws/lsphp83/bin/php
COMPOSER=$(command -v composer)
echo "$COMPOSER"                       # doit afficher un chemin (ex. /usr/local/bin/composer), sinon s'arrêter

CMD="cd $APP && git diff --quiet && git diff --cached --quiet && git pull --ff-only origin main && $PHP $COMPOSER install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress && $PHP $COMPOSER dump-env prod && $PHP bin/console importmap:install --no-interaction && $PHP bin/console asset-map:compile --no-interaction && $PHP bin/console doctrine:migrations:migrate --no-interaction --allow-no-migration && $PHP bin/console cache:clear --no-interaction"

mkdir -p ~/.ssh && chmod 700 ~/.ssh
echo "command=\"$CMD\",no-port-forwarding,no-agent-forwarding,no-X11-forwarding,no-pty $(cat ~/deploy_sarah.pub)" >> ~/.ssh/authorized_keys
chmod 600 ~/.ssh/authorized_keys
wc -l ~/.ssh/authorized_keys           # une ligne de plus qu'avant : la nouvelle clé tient sur UNE seule ligne
```

**c) Tester depuis le VPS lui-même** (doit lancer la mise à jour complète et afficher la sortie de git et de composer)

```bash
ssh -i ~/deploy_sarah -o BatchMode=yes -o StrictHostKeyChecking=accept-new "$(whoami)"@localhost
```

Si `localhost` refuse la connexion, voir le tableau « En cas de problème » plus bas (SSH et `~/.ssh`).

**d) Créer les secrets GitHub** : dépôt → *Settings* → *Environments* → **production** (créer l'environnement s'il n'existe
pas) → *Environment secrets*.

| Secret                | Valeur                                                                                              |
|-----------------------|-----------------------------------------------------------------------------------------------------|
| `DEPLOY_SSH_KEY`      | sortie de `cat ~/deploy_sarah` (clé privée, lignes `BEGIN`/`END` comprises)                          |
| `DEPLOY_KNOWN_HOSTS`  | sortie de `ssh-keyscan -p 22 <DEPLOY_HOST>` (mêmes hôte et port que ci-dessous)                     |
| `DEPLOY_HOST`         | nom d'hôte ou **IP** du VPS ; derrière Cloudflare en mode proxy (nuage orange), utiliser l'IP du VPS, pas le nom |
| `DEPLOY_USER`         | sortie de `whoami` (l'utilisateur du site)                                                          |
| `DEPLOY_PORT`         | facultatif : port SSH, `22` par défaut                                                              |

Une fois les cinq valeurs copiées dans GitHub, **supprimer la clé du serveur** (la clé publique reste dans
`authorized_keys`) :

```bash
rm -f ~/deploy_sarah ~/deploy_sarah.pub
```

**e) Premier passage** : pousser (ou fusionner) sur `main`, puis suivre l'onglet *Actions* : tests, puis job `deploy`.
Vérifications : section 8. Pour révoquer l'accès : supprimer la ligne `github-deploy-sarah` de `~/.ssh/authorized_keys`.

## 7. Sauvegardes

Pas de sauvegarde automatique pour le moment (choix de l'utilisateur).

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
| Page blanche ou 500                       | `grep CRITICAL <app>/var/log/prod-*.log` ; `/usr/local/lsws/logs/error.log` (en root) |
| 500 « mkdir(): Permission denied » dans `var/` | commande lancée en root : `chown -R` de `var/` (encadré en tête de page)   |
| 404 sur toutes les pages sauf l'accueil   | `.htaccess` non lu : bloc `rewrite` du vHost (étape 5)                         |
| CSS / JS en 403                           | droits de lecture de `<app>/public/` pour OpenLiteSpeed (étape 5)              |
| Vidéos refusées à l'envoi                 | ffmpeg absent ou `proc_open` désactivé (étape 2)                               |
| Miniatures absentes (404 sur `/media/<id>/thumb/…`) | `php bin/console app:media:regenerate-thumbs` (recrée les miniatures manquantes depuis les originaux) |
| Job `deploy` : « Connection timed out » / « refused » | le port SSH est fermé : pare-feu CyberPanel (*Security* → *Firewall*) ou `firewalld` ; **GitHub n'a pas d'IP fixe** : le port doit être ouvert à toutes les adresses (clé seule, pas de mot de passe) ; port différent de 22 → secret `DEPLOY_PORT` |
| Job `deploy` : « Permission denied (publickey) » | mauvais `DEPLOY_USER` (doit être `whoami` de l'utilisateur du site), clé mal copiée, droits : `chmod 700 ~/.ssh && chmod 600 ~/.ssh/authorized_keys`, propriétaire de `~` et `~/.ssh` = `<user>` |
| Job `deploy` : « Host key verification failed » | `DEPLOY_KNOWN_HOSTS` ne correspond pas à `DEPLOY_HOST` (mêmes nom/IP et port) : refaire `ssh-keyscan` |
| « Permission denied » sur `.git` ou `var/` pendant la mise à jour | fichiers appartenant à root (mise à jour lancée en root un jour) : en root, `cd <app> && find . ! -user "$(stat -c %U .)"` puis `chown -R` (voir l'encadré en tête de page) |
| La mise à jour s'arrête tout de suite, sans message de composer | copie locale modifiée : sur le VPS, `cd <app> && git diff` (et `git diff --cached`) ; annuler ou valider ces modifications, ne jamais forcer |
| `git pull --ff-only` refuse               | modifications locales ou historique divergent sur le serveur : `git status`, ne rien forcer sans comprendre |
| « Composer detected issues in your platform » | composer lancé avec un autre PHP : utiliser `$PHP "$COMPOSER" …` (étape 6.3) |
