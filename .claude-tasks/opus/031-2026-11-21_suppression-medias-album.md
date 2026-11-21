# 031 — Suppression de médias choisis depuis l'admin d'un album (super-administrateur)

- **Modèle** : Opus
- **Justification** : droits d'accès (nouvel attribut de voter réservé au super-administrateur) et suppression définitive
  de fichiers et de données ; sécurité en jeu.
- **Fichiers créés** : `templates/admin/album/delete_media.html.twig`, `assets/admin/album_media_delete.js`,
  `.claude-tasks/opus/031-2026-11-21_suppression-medias-album.md`,
  `documentations-dev/031-2026-11-21_15-43-opus-suppression-medias-album.md`
- **Fichiers modifiés** : `src/Security/Voter/AlbumVoter.php`, `src/Controller/Admin/AlbumCrudController.php`,
  `src/Repository/MediaRepository.php`, `src/Repository/CommentRepository.php`, `assets/admin.js`,
  `assets/styles/admin.css`, `tests/Controller/PermissionTest.php`, `README.md`
- **Résumé** : action « Supprimer des médias » (liste, fiche et modification d'un album, super-administrateur seul,
  `AlbumVoter::DELETE_MEDIA`) → page `/admin/album/{id}/medias` : grille de vignettes à cocher, « Tout cocher », compteur,
  confirmation. Suppression en base ; fichiers effacés par `MediaFileCleanupListener` ; commentaires par la clé étrangère
  `ON DELETE CASCADE` ; couverture de l'album remise à vide. Seuls les médias de l'album sont pris en compte. CSRF vérifié.
