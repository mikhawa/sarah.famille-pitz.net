# Phase 12 : Visionneuse sur toutes les photos du fil de l'accueil

> Date : 21 novembre 2026

## Objectif
Étendre à l'accueil la visionneuse « toutes les photos » (fiche 029), sans embarquer des milliers de photos dans la page.

## Actions realisees

### 1. Service et Twig
- `App\Service\LightboxItems` : `thumbSize()`, `lightboxImage()`, `items()` (logique sortie de `AppExtension`, qui délègue).

### 2. Repository
- `createFeedQueryBuilder()` partagé par `paginateFeed()` et `findPhotosForFeed()` ; liste vide sans requête pour les types
  vidéo et musique.

### 3. Route JSON
- `LightboxController::feed` : `GET /visionneuse/fil?type=` (`feed_lightbox`), `Cache-Control: private, no-store`.

### 4. Accueil et JS
- La grille porte `data-lightbox-items-url-value`. `lightbox_controller.js` (valeur `itemsUrl`) : liste chargée au
  premier clic puis gardée en mémoire, clics ignorés pendant le chargement ; échec ou photo absente de la liste : la fiche
  de la photo est suivie. `setupFullList()` partagé avec la liste inline.

### 5. Tests (`FrontTest`)
- Route JSON : toutes les photos au-delà d'une page, ordre du fil, vidéo / média super-admin / sans dimensions exclus,
  filtre de type, liste vide pour video et audio, URL portée par la page, anonyme redirigé vers `/login`.

## Fichiers crees
- `src/Controller/LightboxController.php`, `src/Service/LightboxItems.php`
- `.claude-tasks/sonnet/030-2026-11-21_visionneuse-accueil.md`

## Fichiers modifies
- `src/Repository/MediaRepository.php`, `src/Twig/AppExtension.php`, `templates/home/index.html.twig`,
  `assets/controllers/lightbox_controller.js`, `tests/Controller/FrontTest.php`, `docs/architecture/overview.md`,
  `.claude/MEMORY.md`

## Verifications
| Point | Statut |
|-------|--------|
| `lint:twig templates/` | OK |
| `node --check` sur le contrôleur JS | OK |
| `docker compose exec -T php php bin/phpunit` → `OK (78 tests, 523 assertions)` | OK |
| Essai dans le navigateur | Non fait (à vérifier par l'utilisateur) |

## Problemes rencontres
- Aucun.
