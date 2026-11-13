# Phase 7.2 : Réduction automatique des grandes photos

> Date : 13 novembre 2026, 15 h 17

## Objectif
Accepter les photos très grandes (ex. agrandies par IA avec Real-ESRGAN) au lieu de les refuser au-delà de 40 Mpx.

## Actions realisees

### 1. Réduction automatique
**Fichier :** `src/Service/MediaUploader.php`
- `MAX_DIMENSION = 6000` : plus grand côté ramené à 6000 px, proportions conservées (`limitDimensions`,
  qui réutilise `resizeToWidth`).
- Réduction faite **avant** `applyOrientation` : la copie tournée porte sur l'image réduite.
- GIF non concernés (copiés tels quels pour garder l'animation).

### 2. Seuil de refus selon la mémoire
- `canDecode()` remplace la constante `MAX_PIXELS` (40 Mpx) : mémoire nécessaire = (pixels de l'image + pixels de
  la copie réduite) × 5 octets, comparée à 80 % de la mémoire libre (`memory_limit` − `memory_get_usage()`).
  Plafond absolu de 150 Mpx ; pas de limite si `memory_limit = -1`.
- Message de refus : dimensions, mégapixels et invitation à réduire la photo.

### 3. Texte d'aide et documentation
- `src/Form/MediaUploadType.php` : aide du champ « Fichiers » mentionnant la réduction.
- `docs/architecture/overview.md` : tableau des formats mis à jour.

## Fichiers crees
Aucun.

## Fichiers modifies
- `src/Service/MediaUploader.php`
- `src/Form/MediaUploadType.php`
- `docs/architecture/overview.md`
- `tests/Service/MediaUploaderTest.php` (`testOversizedPhotoIsScaledDown`)
- `tests/Support/MediaFixtures.php` (`png()` paramétrable)

## Verifications
| Point | Statut |
|-------|--------|
| Paysage 8000 × 400 → 6000 × 300, portrait 300 × 9000 → 200 × 6000 | OK |
| Mesure JPEG 9000 × 6000 (54 Mpx), 512 Mo : accepté, pic 399 Mo | OK |
| 512 Mo : 77 Mpx et plus refusés proprement (pas de dépassement mémoire) | OK |
| 1 Go (prod) : 77, 96 et 144 Mpx acceptés, pics 481 / 564 / 722 Mo | OK |
| Suite complète (61 tests) | OK |

## Problemes rencontres
- Première version du seuil trop prudente (copie réservée à 6000 × 6000) : ~40 Mpx seulement avec 512 Mo ;
  remplacée par le calcul de la copie réelle de chaque image.
