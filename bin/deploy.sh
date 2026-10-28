#!/usr/bin/env bash
#
# Mise à jour du site de Sarah sur le VPS, déclenchée à la demande depuis GitHub Actions (.github/workflows/ci-deploy.yml).
#
# La clé SSH de GitHub est limitée à ce script dans ~/.ssh/authorized_keys (command="…") : GitHub ne peut rien
# lancer d'autre sur le serveur. Il transmet seulement le commit testé, lu dans SSH_ORIGINAL_COMMAND.
# Lancement manuel possible : bin/deploy.sh <sha du commit>
#
# Étapes : vérification du commit, sauvegarde, code, dépendances, assets, migrations, cache, rechargement de PHP.
#
# Réglages propres au serveur, facultatifs, dans .deploy.env à la racine du projet (ignoré par git) :
#   PHP_BIN            PHP en ligne de commande          (défaut : php ; CyberPanel : /usr/local/lsws/lsphp83/bin/php)
#   COMPOSER_BIN       chemin de composer                (défaut : celui du PATH)
#   DEPLOY_RELOAD_CMD  commande de rechargement de PHP   (défaut : aucune ; nginx + PHP-FPM :
#                      "sudo -n /usr/bin/systemctl reload php8.3-fpm")

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BRANCH="main"
SHA="${SSH_ORIGINAL_COMMAND:-${1:-}}"

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

# Seul un identifiant de commit complet est accepté : rien de ce qu'envoie GitHub n'est exécuté
if [[ ! "$SHA" =~ ^[0-9a-f]{40}$ ]]; then
    echo "Usage : deploy.sh <sha du commit sur 40 caractères>" >&2
    exit 1
fi

cd "$PROJECT_DIR"

if [[ -f .deploy.env ]]; then
    # shellcheck source=/dev/null
    source .deploy.env
fi
PHP_BIN="${PHP_BIN:-php}"
COMPOSER_BIN="${COMPOSER_BIN:-$(command -v composer)}"
DEPLOY_RELOAD_CMD="${DEPLOY_RELOAD_CMD:-}"

# Composer est lancé avec le même PHP que la console (son shebang prendrait le « php » du système)
composer() {
    "$PHP_BIN" "$COMPOSER_BIN" "$@"
}
console() {
    "$PHP_BIN" bin/console "$@"
}

# Un seul déploiement à la fois
exec 9> var/deploy.lock
flock -n 9 || { echo "Un déploiement est déjà en cours." >&2; exit 1; }

log "Récupération de origin/$BRANCH"
git fetch --quiet origin "$BRANCH"
if ! git merge-base --is-ancestor "$SHA" "origin/$BRANCH"; then
    echo "Le commit $SHA n'appartient pas à origin/$BRANCH : déploiement refusé." >&2
    exit 1
fi

# Sauvegarde avant toute modification (migrations comprises)
log "Sauvegarde"
"$PROJECT_DIR/bin/backup.sh"

log "Code → $SHA"
git checkout --quiet --detach "$SHA"

log "Dépendances"
composer install --no-dev --optimize-autoloader --classmap-authoritative --no-interaction --no-progress
composer dump-env prod

log "Assets"
console importmap:install --no-interaction
console asset-map:compile --no-interaction

log "Migrations"
console doctrine:migrations:migrate --no-interaction --allow-no-migration

log "Cache"
console cache:clear --no-interaction

# Nécessaire seulement si opcache ne relit pas les fichiers (validate_timestamps = off, cas du pool PHP-FPM fourni)
if [[ -n "$DEPLOY_RELOAD_CMD" ]]; then
    log "Rechargement de PHP"
    bash -c "$DEPLOY_RELOAD_CMD"
fi

log "Déploiement de $SHA terminé"
