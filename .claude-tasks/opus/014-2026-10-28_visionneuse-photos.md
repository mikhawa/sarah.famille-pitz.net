# 014 — Visionneuse plein écran des photos (PhotoSwipe)

- **Modèle** : Opus
- **Justification** : intégration front (niveau Sonnet), réalisée dans la session en cours.

## Fichiers créés
- `assets/controllers/lightbox_controller.js` : contrôleur Stimulus PhotoSwipe
- `templates/media/_lightbox_attributes.html.twig` : attributs `data-pswp-*` d'une photo

## Fichiers modifiés
- `importmap.php` : `photoswipe`, `photoswipe/lightbox`, `photoswipe/dist/photoswipe.css` (5.4.4)
- `src/Twig/AppExtension.php` : fonction `thumb_size`
- `templates/media/_tile.html.twig`, `templates/media/show.html.twig`, `templates/home/index.html.twig`,
  `templates/album/show.html.twig`, `templates/tag/show.html.twig`
- `assets/controllers/media_nav_controller.js` : flèches ignorées quand la visionneuse est ouverte
- `assets/styles/app.css` : bouton 💬 et fond de la visionneuse
- `tests/Controller/FrontTest.php`, `docs/architecture/overview.md`, `.claude/MEMORY.md`

## Résumé
58 tests verts ; modules et CSS PhotoSwipe servis (200). Pas d'essai dans un vrai navigateur. Détail dans
`documentations-dev/014-2026-10-28_16-07-opus-visionneuse-photos.md`.
