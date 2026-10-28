#!/usr/bin/env bash
#
# Sauvegarde du site de Sarah : base MariaDB + fichiers médias (var/storage/media/).
# À lancer chaque nuit sur le VPS par cron, avec l'utilisateur du site (voir docs/devops/vps-preprod.md).
#
#   <dossier de sauvegarde>/
#   ├── db/sarah_db_AAAA-MM-JJ_HHMM.sql.gz   # une copie complète de la base par sauvegarde
#   └── media/AAAA-MM-JJ_HHMM/               # instantané complet des médias ; les fichiers inchangés
#       media/latest -> AAAA-MM-JJ_HHMM      # sont des liens physiques vers l'instantané précédent
#
# Variables (toutes facultatives) :
#   BACKUP_DIR           dossier de destination            (défaut : /var/backups/sarah)
#   BACKUP_DB_NAME       base à sauvegarder                (défaut : sarah_db)
#   BACKUP_MYSQL_CNF     fichier d'identifiants MariaDB    (défaut : ~/.my.cnf)
#   BACKUP_RETENTION     nombre de jours conservés         (défaut : 14)
#
# Le mot de passe de la base n'apparaît jamais sur la ligne de commande : il est lu dans BACKUP_MYSQL_CNF
# ([client] user=… password=…, fichier en chmod 600).

set -euo pipefail

PROJECT_DIR="$(cd "$(dirname "${BASH_SOURCE[0]}")/.." && pwd)"
BACKUP_DIR="${BACKUP_DIR:-/var/backups/sarah}"
BACKUP_DB_NAME="${BACKUP_DB_NAME:-sarah_db}"
BACKUP_MYSQL_CNF="${BACKUP_MYSQL_CNF:-$HOME/.my.cnf}"
BACKUP_RETENTION="${BACKUP_RETENTION:-14}"
MEDIA_DIR="$PROJECT_DIR/var/storage/media"
STAMP="$(date +%Y-%m-%d_%H%M)"

# Photos et vidéos d'une enfant : sauvegardes lisibles uniquement par leur propriétaire
umask 077

log() {
    echo "[$(date '+%Y-%m-%d %H:%M:%S')] $*"
}

if [[ ! -r "$BACKUP_MYSQL_CNF" ]]; then
    echo "Fichier d'identifiants MariaDB introuvable : $BACKUP_MYSQL_CNF" >&2
    exit 1
fi
if [[ ! -d "$MEDIA_DIR" ]]; then
    echo "Dossier des médias introuvable : $MEDIA_DIR" >&2
    exit 1
fi

mkdir -p "$BACKUP_DIR/db" "$BACKUP_DIR/media"

# --- Base de données ---
# Écrit d'abord dans un fichier temporaire : une sauvegarde interrompue ne remplace jamais une sauvegarde valide
db_file="$BACKUP_DIR/db/${BACKUP_DB_NAME}_$STAMP.sql.gz"
log "Base $BACKUP_DB_NAME → $db_file"
# mariadb-dump (MariaDB ≥ 10.5), sinon mysqldump (anciennes versions, certains panneaux comme CyberPanel)
dump_bin="$(command -v mariadb-dump || command -v mysqldump)"
"$dump_bin" --defaults-extra-file="$BACKUP_MYSQL_CNF" \
    --single-transaction --routines --triggers --default-character-set=utf8mb4 \
    "$BACKUP_DB_NAME" | gzip -9 > "$db_file.tmp"
mv "$db_file.tmp" "$db_file"

# --- Médias ---
media_snapshot="$BACKUP_DIR/media/$STAMP"
link_dest=()
if [[ -d "$BACKUP_DIR/media/latest" ]]; then
    link_dest=(--link-dest="$BACKUP_DIR/media/latest/")
fi
log "Médias → $media_snapshot"
rsync -a --delete "${link_dest[@]}" "$MEDIA_DIR/" "$media_snapshot.tmp/"
mv "$media_snapshot.tmp" "$media_snapshot"
# rsync -a recopie la date du dossier source : on date l'instantané du jour, sinon la rotation le jugerait ancien
touch "$media_snapshot"
ln -sfn "$STAMP" "$BACKUP_DIR/media/latest"

# --- Rotation ---
log "Suppression des sauvegardes de plus de $BACKUP_RETENTION jours"
find "$BACKUP_DIR/db" -maxdepth 1 -name '*.sql.gz' -mtime "+$BACKUP_RETENTION" -print -delete
find "$BACKUP_DIR/media" -mindepth 1 -maxdepth 1 -type d -name '20*' -mtime "+$BACKUP_RETENTION" \
    ! -name "$STAMP" -print -exec rm -rf {} +

log "Sauvegarde terminée ($(du -sh "$BACKUP_DIR" | cut -f1) au total)"
