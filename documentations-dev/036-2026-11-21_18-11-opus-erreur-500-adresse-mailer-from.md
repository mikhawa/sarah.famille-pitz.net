# Phase 18 : Erreur 500 en prod à l'envoi de photos (adresse d'expéditeur invalide)

> Date : 21 novembre 2026

## Objectif
Comprendre et corriger l'erreur 500 « Le lot 1 sur 1 n'a pas pu être envoyé » sur le VPS (super-administrateur).

## Actions realisees
- Journal `var/log/prod-2026-11-21.log` : `Symfony\Component\Mime\Exception\RfcComplianceException: Email
  "admin%40michaeljpitz.com" does not comply with addr-spec of RFC 2822.` — `%40` (encodage réservé à l'identifiant de
  `MAILER_DSN`) écrit dans une adresse, vraisemblablement `MAILER_FROM`, dans `.env.local` du VPS.
- Effet : l'e-mail « nouveaux fichiers » (fiche 035) levait cette exception, non attrapée (seules les erreurs de
  transport l'étaient) → 500 alors que les médias étaient enregistrés. Même cause probable pour le 500 du
  « mot de passe oublié ».
- `AlbumNotifier`, `CommentNotifier`, `UploadNotifier` : attrapent aussi `Symfony\Component\Mime\Exception\ExceptionInterface`
  (journalisé, la page continue).
- `docs/devops/cyberpanel.md` : `MAILER_FROM` avec un vrai `@`, `dump-env` + `cache:clear` après modification.
- Test unitaire `NotifierMailFailureTest` : mailer qui lève `RfcComplianceException` → journalisé, pas d'exception.

## Fichiers crees
- `tests/Service/NotifierMailFailureTest.php`, `.claude-tasks/opus/036-2026-11-21_erreur-500-adresse-mailer-from.md`

## Fichiers modifies
- `src/Service/AlbumNotifier.php`, `src/Service/CommentNotifier.php`, `src/Service/UploadNotifier.php`,
  `docs/devops/cyberpanel.md`

## Verifications
| Point | Statut |
|-------|--------|
| `docker compose exec -T php php bin/phpunit` → `OK (86 tests, 587 assertions)` | OK |
| Correction de `.env.local` sur le VPS | À faire par l'utilisateur |

## Problemes rencontres
- Le journal OpenLiteSpeed et `stderr.log` ne montraient rien d'utile : la cause n'était que dans le journal Symfony.
