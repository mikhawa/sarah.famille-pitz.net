# Phase 11 : Visionneuse sur toutes les photos de l'album et du tag

> Date : 21 novembre 2026

## Objectif
Sur les pages d'album et de tag (paginées par 72), la visionneuse parcourt toutes les photos (sans vidéos ni musique),
avec l'indicateur « N sur total », quelle que soit la page.

## Actions realisees

### 1. Repository
- `findPhotosByAlbum()` / `findPhotosByTag()` : construits sur les mêmes QueryBuilder privés que `paginateByAlbum()` /
  `paginateByTag()` (visibilité et ordre non dupliqués), restreints au type photo.

### 2. Twig
- `AppExtension` : fonctions `lightbox_image()` (miniature `large` ou original GIF + dimensions) et `lightbox_items()`
  (JSON : id, src, width, height, href, commentsUrl, alt ; photos sans dimensions ignorées). Le gabarit
  `_lightbox_attributes` utilise `lightbox_image()` (plus de duplication) et ajoute `data-lightbox-id`.
- `media/_lightbox_grid.html.twig` : grille commune album / tag, `data-lightbox-items-value` échappé par Twig.

### 3. JavaScript
- `lightbox_controller.js` : valeur Stimulus `items` ; si fournie, clic sur une vignette → `loadAndOpen(index global,
  liste)` ; filtre `itemData` pour rattacher la vignette (animation zoom) si elle est sur la page, sinon fondu ;
  bouton ℹ️ et petits mots lisent `href` / `commentsUrl` dans les données de la diapo (filtre `domItemData` pour le
  mode sans liste). Sans liste (accueil, fiche média) : comportement inchangé.

### 4. Tests (`FrontTest`)
- Album et tag de PER_PAGE + 3 photos : la page 1 fournit toutes les photos ; vidéo, photo « super-admin seul » et
  photo sans dimensions exclues.

## Fichiers crees
- `templates/media/_lightbox_grid.html.twig`
- `.claude-tasks/sonnet/029-2026-11-21_visionneuse-tout-l-album.md`

## Fichiers modifies
- `src/Repository/MediaRepository.php`, `src/Twig/AppExtension.php`, `src/Controller/AlbumController.php`,
  `src/Controller/TagController.php`, `templates/album/show.html.twig`, `templates/tag/show.html.twig`,
  `templates/media/_lightbox_attributes.html.twig`, `assets/controllers/lightbox_controller.js`,
  `tests/Controller/FrontTest.php`, `docs/architecture/overview.md`, `.claude/MEMORY.md`

## Verifications
| Point | Statut |
|-------|--------|
| `lint:twig templates/` | OK |
| `node --check` sur le contrôleur JS | OK |
| `docker compose exec -T php php bin/phpunit` → `OK (76 tests, 503 assertions)` | OK |
| Essai dans le navigateur | Non fait (à vérifier par l'utilisateur) |

## Problemes rencontres
- Aucun. Le test d'ouverture à l'index global et l'animation de zoom ne sont pas couverts par PHPUnit (JavaScript).
