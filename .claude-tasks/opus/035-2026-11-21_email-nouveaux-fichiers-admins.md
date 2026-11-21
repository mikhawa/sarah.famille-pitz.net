# 035 — E-mail aux administrateurs quand de nouveaux fichiers sont envoyés

- **Modèle** : Opus
- **Justification** : destinataires selon les rôles et la visibilité de chaque média, regroupement des lots d'un même
  envoi (session, script d'envoi) pour éviter un e-mail par lot.
- **Fichiers créés** : `src/Service/UploadNotifier.php`, `templates/email/new_media.html.twig`,
  `documentations-dev/035-2026-11-21_18-01-opus-email-nouveaux-fichiers-admins.md`
- **Fichiers modifiés** : `src/Service/MediaImporter.php` (renvoie aussi `medias`), `src/Controller/AlbumController.php`,
  `src/Controller/Admin/MediaCrudController.php`, `assets/admin/media_upload.js`, `tests/Controller/PermissionTest.php`,
  `README.md`
- **Résumé** : après un envoi (admin ou page d'un album), chaque admin / super-admin actif sauf l'expéditeur reçoit un
  e-mail récapitulatif (nombre de fichiers par album, liens) limité aux médias qu'il voit. Les lots portent
  `upload_id` + `upload_last` ; les identifiants sont gardés en session et l'e-mail part au dernier lot. Sans
  JavaScript (une requête), envoi immédiat. Échec SMTP journalisé.
