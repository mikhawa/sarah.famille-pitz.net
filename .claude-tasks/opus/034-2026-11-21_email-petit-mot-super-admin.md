# 034 — E-mail au super-administrateur à chaque nouveau petit mot

- **Modèle** : Opus
- **Justification** : suite directe de la fiche 033 (même mécanisme d'envoi) ; destinataires définis par le rôle.
- **Fichiers créés** : `src/Service/CommentNotifier.php`, `templates/email/new_comment.html.twig`,
  `documentations-dev/034-2026-11-21_17-59-opus-email-petit-mot-super-admin.md`
- **Fichiers modifiés** : `src/Controller/MediaController.php` (fiche et visionneuse), `tests/Controller/CommentTest.php`,
  `README.md`
- **Résumé** : après l'enregistrement d'un petit mot (fiche du média ou visionneuse), chaque `ROLE_SUPER_ADMIN` actif
  reçoit un e-mail (auteur, média, album, texte échappé, lien vers le petit mot), sauf s'il en est l'auteur. Échec SMTP
  journalisé, le petit mot est quand même enregistré.
