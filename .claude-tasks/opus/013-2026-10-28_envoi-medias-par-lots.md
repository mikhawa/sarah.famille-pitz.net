# 013 — Envoi de médias par lots (correctif « session_id() … headers already sent » puis erreur 422)

- **Modèle** : Opus
- **Justification** : bug à causes multiples (limites PHP, CSRF sans état) touchant la sécurité des formulaires.

## Fichiers créés
- `assets/admin.js` : point d'entrée AssetMapper de l'administration (thème, CSRF, envoi par lots)
- `assets/admin/media_upload.js` : découpage de la sélection en lots, bilan affiché sur la page

## Fichiers modifiés
- `importmap.php` : entrée `admin`
- `src/Controller/Admin/DashboardController.php` : `addAssetMapperEntry('admin')` au lieu de la seule feuille CSS
- `src/Controller/Admin/MediaCrudController.php` : bilan JSON pour les envois par lots
- `src/Form/MediaUploadType.php`, `templates/admin/media/upload.html.twig` : textes, `data-list-url`
- `tests/Controller/MediaTest.php` : test du mode JSON
- `docs/architecture/overview.md`, `.claude/MEMORY.md`

## Résumé
57 tests verts ; parcours complet vérifié contre le serveur de dev (3 lots, 46 médias, 1 refus). Détail dans
`documentations-dev/013-2026-10-28_15-56-opus-envoi-par-lots.md`.
