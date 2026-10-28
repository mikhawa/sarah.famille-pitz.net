# 006 — Alias Symfony intégrés à l'image PHP

- **Modèle** : Opus
- **Justification** : tâche simple (niveau Haiku), réalisée directement dans la session en cours car la délégation coûtait plus que la tâche elle-même.

## Fichiers modifiés
- `docker/php/bash_aliases` (nouveau) : alias Symfony/Doctrine/tests/qualité/Composer issus de `RACCOURCIS.md`
- `docker/php/Dockerfile` : copie du fichier en `/home/sarah/.bash_aliases` (chargé par le `.bashrc` par défaut de Debian)
- `RACCOURCIS.md` : note d'utilisation, correction de l'alias `php-cs-fixer` (coupé sur deux lignes)

## Résumé
Les alias sont disponibles dans `docker compose exec php bash` (alias `dphp`), pas avec `sh` (`uphp`).
Vérifié : image reconstruite, `pbc --version` → Symfony v7.4.21.
