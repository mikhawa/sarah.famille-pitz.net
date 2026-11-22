# 039 — Déploiement automatique du VPS à chaque push sur main

- **Modèle** : Sonnet
- **Justification** : configuration GitHub Actions et documentation de mise en production ; décisions déjà prises par l'utilisateur (aucun script dans le dépôt, commande imposée côté serveur).
- **Fichiers créés** : `documentations-dev/039-2026-11-22_17-08-sonnet-deploiement-auto-push-main.md`
- **Fichiers modifiés** : `.github/workflows/ci-deploy.yml`, `docs/devops/cyberpanel.md`, `docs/devops/vps-preprod.md`,
  `docs/architecture/overview.md`
- **Résumé** : job `deploy` (needs `tests`, seulement sur push vers `main`, environnement `production`) qui se connecte en SSH
  avec une clé dédiée ; la commande de mise à jour est écrite en dur dans `authorized_keys` du VPS (`command="…"`).
  Secrets : DEPLOY_SSH_KEY, DEPLOY_KNOWN_HOSTS, DEPLOY_HOST, DEPLOY_USER, DEPLOY_PORT (facultatif). Procédure manuelle gardée en secours.
  Pas de sauvegarde avant les migrations (choix de l'utilisateur).
