# Phase 6.7 : Chemin réel sur le VPS et erreur 500 de l'admin

> Date : 2 novembre 2026, 17 h 30

## Objectif
Corriger l'erreur 500 de `/admin` en production et aligner la procédure CyberPanel sur l'installation réelle.

## Actions realisees

### 1. Diagnostic de l'erreur 500
- Journal `var/log/prod-*.log` : « Failed to create …/var/cache/prod/asset_mapper: mkdir(): Permission denied »,
  levée par `@EasyAdmin/includes/_importmap.html.twig` (seule l'admin crée ce dossier, d'où un front fonctionnel).
- Cause : `var/` créé par root. Réparation sur le VPS : `chown -R` de `var/` vers le propriétaire du projet, puis
  `cache:clear` en tant que lui. Admin de nouveau accessible.

### 2. Procédure corrigée
**Fichier :** `docs/devops/cyberpanel.md`
- Le site est un sous-domaine de `famille-pitz.net` : utilisateur du domaine parent, code dans
  `/home/famille-pitz.net/sarah.famille-pitz.net/sarah.famille-pitz.net` (et non `/home/sarah.famille-pitz.net/app`).
- Notations `<home>`, `<site>`, `<app>` ; variable `APP` pour la clé de déploiement (`command="$APP/bin/deploy.sh"`).
- `docRoot` en chemin complet ; chemins du cron de sauvegarde ; encadré « jamais en root » avec la réparation ;
  ligne ajoutée au tableau des problèmes ; journaux tournants `prod-*.log`.

## Fichiers crees
Aucun.

## Fichiers modifies
- `docs/devops/cyberpanel.md`
- `.claude/MEMORY.md`

## Verifications
| Point | Statut |
|-------|--------|
| `/admin` s'affiche en production après réparation des droits | OK |
| Plus aucune référence à `app/` ni à `/home/sarah.famille-pitz.net` dans `cyberpanel.md` | OK |
| Clé de déploiement installée avec le bon chemin | À faire |

## Problemes rencontres
- Commandes lancées en root sur le VPS : fichiers de `var/` inaccessibles à PHP (erreur 500 sur l'admin seulement).
