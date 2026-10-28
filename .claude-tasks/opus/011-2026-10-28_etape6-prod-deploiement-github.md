# 011 — Étape 6 : production (X-Accel-Redirect, sauvegardes, déploiement par GitHub)

- **Modèle** : Opus
- **Justification** : sécurité du serveur et des données de l'enfant (fichiers privés, sauvegardes, clé de déploiement).

## Fichiers créés
- `bin/backup.sh` : sauvegarde de la base et des médias, rotation
- `bin/deploy.sh` : mise à jour du VPS, lancée par GitHub Actions
- `.github/workflows/ci-deploy.yml` : tests (préprod) à chaque push ; déploiement (prod) seulement à la demande (`workflow_dispatch`)
- `docs/devops/vps-preprod.md` : procédure complète
- `docs/devops/prod/nginx-sarah.conf`, `php-fpm-sarah.conf`, `env.local.example`

## Fichiers modifiés
- `src/Controller/MediaFileController.php`, `src/Service/MediaStorage.php` : mode `X-Accel-Redirect`
- `.env` : `MEDIA_X_ACCEL_PREFIX` ; `docker-compose.yml`, `docker/nginx/default.conf` : activé en dev
- `phpunit.dist.xml` : préfixe forcé à vide en test ; `tests/Controller/MediaTest.php` : test du mode nginx
- `config/packages/monolog.yaml` : journaux de prod en fichiers tournants
- `docs/architecture/overview.md`, `docs/devops/docker-setup.md`, `README.md`, `.claude/MEMORY.md`

## Résumé
55 tests verts. Circuit nginx vérifié de bout en bout en dev ; sauvegarde, rotation et restauration testées ;
garde-fous de `deploy.sh` testés en bac à sable ; configs nginx et PHP-FPM validées. Workflow GitHub jamais exécuté.
Détail dans `documentations-dev/011-2026-10-28_15-29-opus-production.md`.
