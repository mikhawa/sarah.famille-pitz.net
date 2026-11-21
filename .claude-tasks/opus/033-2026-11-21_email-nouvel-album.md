# 033 — E-mail « nouvel album » à la famille (bouton « Prévenir la famille »)

- **Modèle** : Opus
- **Justification** : envoi d'e-mails à toute la famille selon les droits de visibilité (qui reçoit quoi), nouveau droit
  de voter, migration ; choix tranchés avec l'utilisateur (bouton manuel, destinataires = tous ceux qui voient l'album).
- **Fichiers créés** : `src/Service/AlbumNotifier.php`, `templates/email/new_album.html.twig`,
  `templates/admin/album/notify.html.twig`, `src/Migrations/Version20261121165628.php`,
  `documentations-dev/033-2026-11-21_17-59-opus-email-nouvel-album.md`
- **Fichiers modifiés** : `src/Entity/Album.php` (`notifiedAt`), `src/Repository/UserRepository.php` (`findActive`),
  `src/Security/Voter/AlbumVoter.php` (`NOTIFY`), `src/Controller/Admin/AlbumCrudController.php`,
  `tests/Controller/PermissionTest.php`, `README.md`
- **Résumé** : admin → Albums → « Prévenir la famille » → `/admin/album/{id}/prevenir` : destinataires listés (avec le
  nombre de médias que chacun verra), date du dernier envoi, bouton POST avec jeton CSRF. Album public seulement ;
  destinataires = comptes actifs voyant au moins un média, sauf l'expéditeur ; un e-mail par personne ; échec SMTP
  journalisé sans bloquer les autres. Colonne « Famille prévenue le ».
