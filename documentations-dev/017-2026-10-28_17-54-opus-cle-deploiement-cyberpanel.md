# Phase 6.6 : Clé de déploiement GitHub → VPS CyberPanel

> Date : 28 octobre 2026, 17 h 54

## Objectif
Garder dans le dépôt la marche à suivre complète pour créer la clé SSH de déploiement sur le VPS, et l'état
d'avancement de l'installation, pour la reprendre depuis un autre poste.

## Actions realisees

### 1. Procédure pas à pas
**Fichier :** `docs/devops/cyberpanel.md`
- Dépôt GitHub public (vérifié avec `git ls-remote` sans identifiants) : une seule clé, celle de GitHub Actions vers le VPS.
- Clé créée sur le serveur par l'utilisateur du site, autorisée avec `command="…/app/bin/deploy.sh"`, testée
  (réponse attendue : message d'usage de `deploy.sh`), puis clé privée copiée dans GitHub et supprimée du serveur.
- `ssh-keyscan` lancé sur le serveur lui-même : empreinte forcément authentique ; cas Cloudflare (adresse IP).
- Tableau des secrets GitHub, dont `DEPLOY_USER`.

### 2. Piège rencontré
**Fichier :** `docs/devops/cyberpanel.md`
- Premier essai lancé en root : clé ajoutée à `/root/.ssh/authorized_keys` avec un chemin inexistant
  (« /root/app/bin/deploy.sh: No such file or directory »). Commandes de nettoyage et vérification `whoami` / `$HOME`
  ajoutées avant toute création de clé.

### 3. État d'avancement
**Fichier :** `.claude/MEMORY.md`
- `php.ini` réglé et vérifié, clé de déploiement à refaire en tant qu'utilisateur du site, étapes restantes.

## Fichiers crees
Aucun.

## Fichiers modifies
- `docs/devops/cyberpanel.md` : section 6
- `.claude/MEMORY.md` : installation du VPS

## Verifications
| Point | Statut |
|-------|--------|
| Dépôt lisible sans identifiants (`git ls-remote`) | OK |
| Clé de déploiement installée et testée sur le VPS | À faire (en tant qu'utilisateur du site) |

## Problemes rencontres (si applicable)
- Voir action 2.
