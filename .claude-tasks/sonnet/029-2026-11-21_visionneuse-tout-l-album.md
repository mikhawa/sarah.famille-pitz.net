# 029 — Visionneuse : toutes les photos de l'album et du tag

- **Modèle** : Sonnet (agent `sarah-dev`)
- **Justification** : contrôleur Stimulus, requêtes de repository (droits de visibilité réutilisés, sans les dupliquer),
  gabarits Twig et tests fonctionnels ; aucun choix d'architecture ou de sécurité nouveau.
- **Fichiers créés** : `templates/media/_lightbox_grid.html.twig`,
  `.claude-tasks/sonnet/029-2026-11-21_visionneuse-tout-l-album.md`,
  `documentations-dev/029-2026-11-21_10-37-sonnet-visionneuse-tout-l-album.md`
- **Fichiers modifiés** : `src/Repository/MediaRepository.php`, `src/Twig/AppExtension.php`,
  `src/Controller/AlbumController.php`, `src/Controller/TagController.php`, `templates/album/show.html.twig`,
  `templates/tag/show.html.twig`, `templates/media/_lightbox_attributes.html.twig`,
  `assets/controllers/lightbox_controller.js`, `tests/Controller/FrontTest.php`,
  `docs/architecture/overview.md`, `.claude/MEMORY.md`
- **Résumé** : sur une page d'album ou de tag, la visionneuse PhotoSwipe parcourt toutes les photos (pas seulement les
  72 de la page). Liste JSON fournie par `findPhotosByAlbum()` / `findPhotosByTag()` (mêmes filtres et ordre que la
  pagination) ; le JS ouvre la visionneuse à l'index global (`dataSource`). Accueil inchangé.
