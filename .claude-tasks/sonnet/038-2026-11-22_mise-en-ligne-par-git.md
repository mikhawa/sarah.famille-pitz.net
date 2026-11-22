# 038 — Mise en ligne par git uniquement (branche main), suppression de deploy.sh et backup.sh

- **Modèle** : Sonnet
- **Justification** : changement de procédure de mise en production (CI/CD et documentation) ; pas de choix d'architecture à trancher, décision déjà prise par l'utilisateur.
- **Fichiers créés** : `documentations-dev/038-2026-11-22_10-57-sonnet-mise-en-ligne-par-git.md`
- **Fichiers supprimés** : `bin/deploy.sh`, `bin/backup.sh`
- **Fichiers modifiés** : `.github/workflows/ci-deploy.yml`, `.gitignore`, `docs/devops/cyberpanel.md`,
  `docs/devops/vps-preprod.md`, `docs/architecture/overview.md`
- **Résumé** : plus de déploiement depuis GitHub ni de clé SSH GitHub → VPS ; le workflow ne fait plus que les tests
  (`name: Tests`, `workflow_dispatch` gardé pour les relancer à la main). La mise en ligne se fait à la main sur le VPS,
  en tant qu'utilisateur du site : `git fetch` / `checkout main` / `pull --ff-only`, puis les commandes de l'ancien
  `deploy.sh` écrites dans la doc. Choix de l'utilisateur : pas de sauvegarde automatique pour le moment (section
  « Sauvegardes » réduite à une phrase).
