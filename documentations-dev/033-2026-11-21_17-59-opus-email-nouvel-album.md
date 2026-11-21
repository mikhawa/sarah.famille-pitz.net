# Phase 15 : E-mail « nouvel album » à la famille

> Date : 21 novembre 2026

## Objectif
Prévenir la famille par e-mail quand un album est prêt, au moment choisi par un administrateur.

## Actions realisees

### 1. Données et droits
- `Album::$notifiedAt` (migration `Version20261121165628`, colonne `notified_at`) : date du dernier envoi.
- `AlbumVoter::NOTIFY` : administrateurs (albums qu'ils voient).
- `UserRepository::findActive()`.

### 2. Service `AlbumNotifier`
- `getRecipients()` : rien si l'album n'est pas public ; sinon comptes actifs qui voient l'album et au moins un de ses
  médias (`MediaRepository::findAllByAlbum()` avec leurs droits), sauf l'expéditeur, avec ce nombre de médias.
- `notify()` : un e-mail par personne (`email/new_album.html.twig`), échec SMTP journalisé sans arrêter les suivants,
  `notifiedAt` mis à jour si au moins un envoi.

### 3. Administration
- Action « Prévenir la famille » (liste, fiche, modification) → `GET|POST /admin/album/{id}/prevenir`
  (`admin_album_notify`) : avertissement si l'album est privé ou sans destinataire, rappel du dernier envoi, liste des
  destinataires, bouton POST avec jeton CSRF `notify-album-<id>` (403 sinon) ; messages flash échappés.
- Les actions `renderAsForm()` d'EasyAdmin n'ont pas de jeton CSRF, d'où la page de confirmation.
- Colonne « Famille prévenue le ».

### 4. Tests (`PermissionTest`)
- Album public : destinataires (ni l'expéditeur ni le compte désactivé, nombre de médias selon le rôle), 2 e-mails
  séparés, sujet, lien, date enregistrée.
- Album privé : avertissement, aucun e-mail même avec un jeton valide ; jeton faux → 403 ; membre → 403.

## Fichiers crees
- `src/Service/AlbumNotifier.php`, `templates/email/new_album.html.twig`, `templates/admin/album/notify.html.twig`,
  `src/Migrations/Version20261121165628.php`, `.claude-tasks/opus/033-2026-11-21_email-nouvel-album.md`

## Fichiers modifies
- `src/Entity/Album.php`, `src/Repository/UserRepository.php`, `src/Security/Voter/AlbumVoter.php`,
  `src/Controller/Admin/AlbumCrudController.php`, `tests/Controller/PermissionTest.php`, `README.md`

## Verifications
| Point | Statut |
|-------|--------|
| Migration appliquée en dev et en test, `doctrine:schema:validate` (test) | OK |
| `lint:twig`, `lint:container` | OK |
| `docker compose exec -T php php bin/phpunit` → `OK (84 tests, 577 assertions)` | OK |
| Essai dans le navigateur / Mailpit | Non fait (à vérifier par l'utilisateur) |

## Problemes rencontres
- Sélecteur `main li` sans résultat dans la page EasyAdmin : remplacé par `.content-body li` dans le test.
