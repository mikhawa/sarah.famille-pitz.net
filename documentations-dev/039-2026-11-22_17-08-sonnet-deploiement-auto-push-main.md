# Phase 21 : Déploiement automatique à chaque push sur main

> Date : 22 novembre 2026

## Objectif
Mettre le VPS à jour automatiquement à chaque push sur `main` (après les tests), sans script dans le dépôt.

## Actions realisees
- Workflow : `name: Tests et déploiement`, job `tests` inchangé, nouveau job `deploy` (`needs: tests`,
  `if` push sur `refs/heads/main`, `environment: production`, `concurrency` groupe `production` sans annulation).
  Étapes : écriture de la clé (`~/.ssh/deploy_key`, 600) et de `known_hosts` (pas de `StrictHostKeyChecking=no`), `ssh` sans
  commande distante (`BatchMode=yes`), nettoyage avec `if: always()`. Un push sur une autre branche, une PR ou
  `workflow_dispatch` ne déploie jamais.
- `cyberpanel.md` : nouvelle section 6.4 (clé en tant qu'utilisateur du site, ligne `authorized_keys` avec `command="…"` en
  chemins absolus, `git diff --quiet` au lieu de `git status`, test en `localhost`, secrets, tableau de problèmes) ;
  6.1 à 6.3 gardées en secours.
- `vps-preprod.md` : même principe (utilisateur `sarah`, `/var/www/sarah`) ; `sudo -n /usr/bin/systemctl reload php-fpm` en fin
  de chaîne car le pool coupe `opcache.validate_timestamps` ; règle sudoers limitée à cette commande.
- `overview.md` : ligne « Prod » mise à jour.
- Pas de sauvegarde avant les migrations (choix de l'utilisateur).

## Fichiers crees
- `.claude-tasks/sonnet/039-2026-11-22_deploiement-auto-push-main.md`

## Fichiers modifies
- `.github/workflows/ci-deploy.yml`, `docs/devops/cyberpanel.md`, `docs/devops/vps-preprod.md`, `docs/architecture/overview.md`

## Verifications
| Point | Statut |
|-------|--------|
| YAML du workflow valide (PyYAML : jobs tests et deploy) | OK |
| Bloc `authorized_keys` testé en local (une seule ligne, 2 guillemets, lue par `ssh-keygen -l`) | OK |
| Tests PHPUnit | Non lancés (aucun code PHP modifié) |
| Essai réel sur le VPS et sur GitHub | Non fait (à faire par l'utilisateur : clé, secrets, premier push) |

## Problemes rencontres
- Aucun. Le bloc shell a été testé avec un HOME factice dans le scratchpad.
