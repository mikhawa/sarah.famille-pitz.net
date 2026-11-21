# 027 — Pagination du front : 48 médias par page

- **Modèle** : Haiku (via l'agent `sarah-crud`)
- **Justification** : changement d'une seule constante (`MediaRepository::PER_PAGE`), sans logique métier ni droits
  d'accès ; les tests existants utilisent déjà la constante.
- **Fichiers créés** : `.claude-tasks/haiku/027-2026-11-21_pagination-48-par-page.md`,
  `documentations-dev/027-2026-11-21_10-20-haiku-pagination-48-par-page.md`
- **Fichiers modifiés** : `src/Repository/MediaRepository.php` (`PER_PAGE` 24 → 48),
  `docs/architecture/overview.md` (pagination 48 médias par page), `.claude/MEMORY.md` (pagination 48/page, compteurs
  de traçabilité : prochain 028).
- **Résumé** : la pagination du front passe de 24 à 48 médias par page. 13 tests de `FrontTest` et 74 tests de la
  suite complète passent.
