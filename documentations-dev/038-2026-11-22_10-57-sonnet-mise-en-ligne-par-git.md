# Phase 20 : Mise en ligne par git uniquement (branche main)

> Date : 22 novembre 2026

## Objectif
Supprimer `bin/deploy.sh` et `bin/backup.sh` : la mise en ligne n'utilise plus que git sur la branche `main`, à la main
sur le VPS. Plus de déploiement automatique depuis GitHub, plus de clé SSH GitHub → VPS.

## Actions realisees
- Suppression de `bin/deploy.sh` (après relecture de ses commandes, reprises dans la doc) et de `bin/backup.sh`.
- Workflow GitHub : job `deploy` retiré, étapes de test inchangées, `name: Tests`, commentaires d'en-tête mis à jour ;
  `workflow_dispatch` conservé pour relancer les tests à la main.
- `.gitignore` : entrée `/.deploy.env` retirée.
- `cyberpanel.md` : section 6 réécrite (« Mise à jour du site (git, branche main) » : devenir l'utilisateur du site,
  `git fetch origin main`, `git checkout main`, `git pull --ff-only origin main`, puis composer install / dump-env,
  importmap:install, asset-map:compile, migrations, cache:clear avec `/usr/local/lsws/lsphp83/bin/php`) ; clé SSH,
  secrets `DEPLOY_*`, « Run workflow » et `.deploy.env` supprimés (vue d'ensemble, section 4, tableau des problèmes).
- `vps-preprod.md` : même logique (utilisateur `sarah`, `/var/www/sarah`, rechargement manuel de php-fpm) ;
  compte MariaDB et dossier `/var/backups/sarah` de sauvegarde retirés.
- **Choix de l'utilisateur : pas de sauvegarde automatique pour le moment** ; la section « Sauvegardes » des deux pages
  tient en une phrase, sans procédure.
- `overview.md` : mention de `bin/deploy.sh` et des sauvegardes remplacée.

## Fichiers crees
- `.claude-tasks/sonnet/038-2026-11-22_mise-en-ligne-par-git.md`

## Fichiers modifies
- `.github/workflows/ci-deploy.yml`, `.gitignore`, `docs/devops/cyberpanel.md`, `docs/devops/vps-preprod.md`,
  `docs/architecture/overview.md`
- Supprimés : `bin/deploy.sh`, `bin/backup.sh`

## Verifications
| Point | Statut |
|-------|--------|
| YAML du workflow valide (PyYAML : un seul job `tests`) | OK |
| grep `deploy.sh|backup.sh|.deploy.env|DEPLOY_|Run workflow` hors historique | OK (reste seulement `.claude/MEMORY.md`, non touché) |
| `README.md` ne parle pas du déploiement ni des sauvegardes | OK |
| Tests PHPUnit | Non lancés (aucun code PHP modifié) |
| Essai de la procédure sur le VPS | Non fait (à faire par l'utilisateur) |

## Problemes rencontres
- Un premier script Python en heredoc contenait lui-même une ligne `EOF` : le shell a exécuté la suite du texte comme
  des commandes (un fichier parasite `**Piège` créé puis supprimé, un `git fetch/checkout main/pull` sans effet : déjà
  à jour). Aucun fichier du dépôt n'a été altéré ; les modifications ont ensuite été faites par un script en fichier.
- `docs/devops/docker-setup.md` parle de « sauvegardes » pour `var/storage/media/` (dev) : laissé tel quel.
- `.claude/MEMORY.md` décrit encore l'ancien déploiement (historique) : à mettre à jour par l'utilisateur ou l'agent principal.
