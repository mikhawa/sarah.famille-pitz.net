# 037 — Petit mot : e-mail à la personne qui a envoyé le média et aux participants de la conversation

- **Modèle** : Opus
- **Justification** : suite des fiches 034/036 ; destinataires selon les rôles, la visibilité et la déduplication.
- **Fichiers créés** : `documentations-dev/037-2026-11-21_19-03-opus-email-petit-mot-proprietaire-conversation.md`
- **Fichiers modifiés** : `src/Service/CommentNotifier.php`, `src/Repository/UserRepository.php`
  (`findActiveCommentersOf`), `templates/email/new_comment.html.twig`, `tests/Controller/CommentTest.php`, `README.md`
- **Résumé** : choix de l'utilisateur « conversation » (pas de bouton Répondre). Destinataires d'un nouveau petit mot :
  expéditeur du média, auteurs des petits mots précédents, super-administrateurs ; comptes actifs voyant le média, sauf
  l'auteur, un e-mail chacun avec la raison la plus proche (« que tu as partagé », « où tu avais écrit », modération).
