# Phase 13 : Suppression de médias depuis l'administration d'un album (super-administrateur)

> Date : 21 novembre 2026

## Objectif
Permettre au super-administrateur de supprimer définitivement, depuis l'admin des albums, des médias choisis d'un album :
lignes en base, fichiers du disque (original et miniatures) et commentaires.

## Actions realisees

### 1. Droits
- `AlbumVoter::DELETE_MEDIA` (`ALBUM_DELETE_MEDIA`) : super-administrateur seulement (et album visible).

### 2. Repository
- `MediaRepository::findAllByAlbum($viewer, $album, ?$ids)` : part de `createAlbumQueryBuilder()` (mêmes droits, même
  ordre que la page de l'album) ; `$ids` restreint à la sélection, donc aux seuls médias de cet album.
- `CommentRepository::countByMedia($medias)` : nombre de commentaires par média (affiché sur les vignettes).

### 3. Administration
- `AlbumCrudController` : action « Supprimer des médias » (liste, fiche, modification), affichée selon le voter ;
  route `admin_album_delete_media` (`GET|POST /admin/album/{entityId}/medias`), droit revérifié dans l'action, jeton CSRF
  `delete-album-media-<id>` (403 si invalide). Couverture de l'album remise à vide si elle est supprimée, puis un seul
  `flush()` ; retour sur la page (303) avec un message.
- Fichiers : `MediaFileCleanupListener` (postRemove) ; commentaires : `ON DELETE CASCADE`.
- Gabarit `admin/album/delete_media.html.twig` : grille de vignettes à cocher (type, date, nombre de commentaires,
  « couverture »), liens vers l'album et vers la page du site.
- `assets/admin/album_media_delete.js` : « Tout cocher », compteur sur le bouton (désactivé à 0), confirmation.

### 4. Tests (`PermissionTest`)
- Super-administrateur : le média coché est supprimé avec ses fichiers et son commentaire, couverture remise à vide,
  le média d'un autre album glissé dans la requête est ignoré, l'autre média de l'album est conservé.
- Administrateur : pas de bouton, 403 en GET et en POST ; super-administrateur sans jeton valide : 403.

## Fichiers crees
- `templates/admin/album/delete_media.html.twig`, `assets/admin/album_media_delete.js`
- `.claude-tasks/opus/031-2026-11-21_suppression-medias-album.md`

## Fichiers modifies
- `src/Security/Voter/AlbumVoter.php`, `src/Controller/Admin/AlbumCrudController.php`, `src/Repository/MediaRepository.php`,
  `src/Repository/CommentRepository.php`, `assets/admin.js`, `assets/styles/admin.css`,
  `tests/Controller/PermissionTest.php`, `README.md`

## Verifications
| Point | Statut |
|-------|--------|
| `lint:twig templates/admin/album` | OK |
| `lint:container` | OK |
| `docker compose exec -T php php bin/phpunit` → `OK (80 tests, 543 assertions)` | OK |
| Essai dans le navigateur | Non fait (à vérifier par l'utilisateur) |

## Problemes rencontres
- Aucun.
