# 015 — Mise en production sur un VPS CyberPanel

- **Modèle** : Opus
- **Justification** : adaptation de l'infrastructure de production (serveur web, PHP, SSH de déploiement).

## Fichiers créés
- `docs/devops/cyberpanel.md` : procédure complète pour CyberPanel / OpenLiteSpeed
- `public/.htaccess` : réécriture vers Symfony et redirection HTTPS pour OpenLiteSpeed

## Fichiers modifiés
- `bin/deploy.sh` : `.deploy.env` (PHP_BIN, COMPOSER_BIN, DEPLOY_RELOAD_CMD), rechargement de PHP facultatif
- `bin/backup.sh` : `mysqldump` si `mariadb-dump` est absent
- `.github/workflows/ci-deploy.yml` : secret `DEPLOY_USER`
- `.gitignore` : `/.deploy.env`
- `docs/devops/vps-preprod.md`, `docs/devops/prod/env.local.example`, `README.md`, `.claude/MEMORY.md`

## Résumé
58 tests verts ; `deploy.sh` testé en bac à sable avec la configuration CyberPanel et avec rechargement.
Détail dans `documentations-dev/015-2026-10-28_16-14-opus-deploiement-cyberpanel.md`.
