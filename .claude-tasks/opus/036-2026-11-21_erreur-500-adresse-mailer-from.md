# 036 — Erreur 500 en prod à l'envoi de photos : adresse d'expéditeur invalide

- **Modèle** : Opus
- **Justification** : diagnostic d'un bug de prod (journaux VPS) et durcissement des envois d'e-mails.
- **Fichiers créés** : `tests/Service/NotifierMailFailureTest.php`,
  `documentations-dev/036-2026-11-21_18-11-opus-erreur-500-adresse-mailer-from.md`
- **Fichiers modifiés** : `src/Service/AlbumNotifier.php`, `src/Service/CommentNotifier.php`,
  `src/Service/UploadNotifier.php`, `docs/devops/cyberpanel.md`
- **Résumé** : `RfcComplianceException` « admin%40michaeljpitz.com » : `%40` écrit dans une adresse (MAILER_FROM) au lieu
  de `@` dans `.env.local` du VPS → tout e-mail échoue ; l'e-mail « nouveaux fichiers » faisait échouer l'envoi en 500
  (médias pourtant enregistrés). Les trois notificateurs attrapent aussi les exceptions MIME (journalisées).
  À corriger sur le VPS : `.env.local`, `composer dump-env prod`, `cache:clear`. Le « mot de passe oublié » reste en
  500 tant que l'adresse n'est pas corrigée.
