# 028 — Pagination du front : 72 médias par page

- **Modèle** : Haiku (via l'agent `sarah-crud`)
- **Justification** : changement d'une seule constante (`MediaRepository::PER_PAGE`), sans logique métier ni droits
  d'accès ; les tests existants utilisent déjà la constante.
- **Fichiers créés** : `.claude-tasks/haiku/028-2026-11-21_pagination-72-par-page.md`,
  `documentations-dev/028-2026-11-21_10-30-haiku-pagination-72-par-page.md`
- **Fichiers modifiés** : `src/Repository/MediaRepository.php` (`PER_PAGE` 48 → 72),
  `docs/architecture/overview.md` (pagination 72 médias par page), `.claude/MEMORY.md` (pagination 72/page, compteurs
  de traçabilité : prochain 029), `tests/Controller/FrontTest.php` (horodatage valide au-delà de 60 médias).
- **Résumé** : la pagination du front passe de 48 à 72 médias par page. `FrontTest::testFeedIsPaginated` construisait
  l'horodatage avec `10:%02d` (« 10:60 » dès 61 médias) : corrigé par Opus, suite complète verte (74 tests).
