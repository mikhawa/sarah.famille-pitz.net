# Phase 16 : E-mail au super-administrateur pour chaque nouveau petit mot

> Date : 21 novembre 2026

## Objectif
Le `ROLE_SUPER_ADMIN` reçoit un e-mail dès qu'un petit mot est posté.

## Actions realisees
- Service `CommentNotifier::notifyNewComment()` : chaque super-administrateur actif sauf l'auteur, un e-mail chacun
  (`email/new_comment.html.twig` : auteur, média, album, texte échappé avec retours à la ligne, lien
  `/media/{id}#comment-{id}`) ; échec SMTP journalisé, sans effet sur l'enregistrement du petit mot.
- Appelé après le `flush()` dans `MediaController::show()` (fiche) et `::comments()` (visionneuse).
- Test `CommentTest::testActiveSuperAdminsAreEmailedForEachNewComment` : fiche et visionneuse, ni l'admin ni le
  super-admin désactivé, contenu échappé, pas d'e-mail pour son propre petit mot.

## Fichiers crees
- `src/Service/CommentNotifier.php`, `templates/email/new_comment.html.twig`,
  `.claude-tasks/opus/034-2026-11-21_email-petit-mot-super-admin.md`

## Fichiers modifies
- `src/Controller/MediaController.php`, `tests/Controller/CommentTest.php`, `README.md`

## Verifications
| Point | Statut |
|-------|--------|
| `lint:twig`, `lint:container` | OK |
| `docker compose exec -T php php bin/phpunit` → `OK (84 tests, 577 assertions)` | OK |
| Essai dans le navigateur / Mailpit | Non fait (à vérifier par l'utilisateur) |

## Problemes rencontres
- Aucun.
