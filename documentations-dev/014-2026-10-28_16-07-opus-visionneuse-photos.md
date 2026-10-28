# Phase 6.3 : Visionneuse plein écran des photos

> Date : 28 octobre 2026, 16 h 07

## Objectif
Ouvrir les photos en grand dans une visionneuse (lightbox) gratuite, agréable sur tablette : glisser d'une photo à
l'autre, zoomer avec les doigts.

## Actions realisees

### 1. Choix de PhotoSwipe 5
**Fichier :** `importmap.php`
- PhotoSwipe 5.4.4 : licence MIT, sans dépendance, gestes tactiles natifs (glisser, pincer, double-toucher), clavier.
- Ajouté par `importmap:require` (fichiers dans `assets/vendor/`, ignoré par git, réinstallé par `importmap:install`
  en CI et au déploiement) : pas de build Node.

### 2. Contrôleur Stimulus
**Fichier :** `assets/controllers/lightbox_controller.js`
- Chaque lien `a[data-pswp-src]` de l'élément devient une photo de la visionneuse ; le `href` reste la fiche du média
  (lien suivi sans JavaScript). Vidéos et musique ne sont pas concernées.
- Textes en français, bouton 💬 « Voir la fiche et les petits mots » vers la page du média (masqué quand on y est déjà).
- `destroy()` à la déconnexion (navigation Turbo).

### 3. Gabarits
**Fichiers :** `templates/media/_lightbox_attributes.html.twig`, `templates/media/_tile.html.twig`, `templates/media/show.html.twig`
- Fil chronologique : une seule visionneuse pour toute la page (on glisse d'un mois à l'autre) ; albums et tags : la grille.
- Fiche média : un clic sur la photo l'ouvre en grand ; sans JavaScript, le lien ouvre l'original comme avant.
- Image affichée : grande miniature (1600 px de large au plus) ; GIF : l'original, pour garder l'animation.

### 4. Dimensions des images
**Fichier :** `src/Twig/AppExtension.php`
- PhotoSwipe doit connaître la taille de l'image avant de la charger : fonction Twig `thumb_size(media, 'large')`,
  même calcul que `MediaUploader` (réduction en largeur, jamais d'agrandissement). Dimensions inconnues : pas de visionneuse.

### 5. Navigation clavier de la fiche
**Fichier :** `assets/controllers/media_nav_controller.js`
- Les flèches changeaient de fiche même visionneuse ouverte : ignorées tant que PhotoSwipe est affiché.

## Fichiers crees
- `assets/controllers/lightbox_controller.js` : visionneuse
- `templates/media/_lightbox_attributes.html.twig` : attributs PhotoSwipe d'une photo

## Fichiers modifies
- `importmap.php` : PhotoSwipe
- `src/Twig/AppExtension.php` : `thumb_size`
- `templates/media/_tile.html.twig`, `templates/media/show.html.twig`, `templates/home/index.html.twig`,
  `templates/album/show.html.twig`, `templates/tag/show.html.twig`
- `assets/controllers/media_nav_controller.js`, `assets/styles/app.css`
- `tests/Controller/FrontTest.php` : test `testPhotosOpenInTheLightbox`
- `docs/architecture/overview.md`, `.claude/MEMORY.md`

## Verifications
| Point | Statut |
|-------|--------|
| Tests PHPUnit (58 tests, 314 assertions) | OK |
| Attributs : 4000×3000 → 1600×1200, 800×600 inchangé, GIF → original, vidéo et dimensions inconnues sans visionneuse | OK |
| Importmap : PhotoSwipe, son CSS et le contrôleur servis (HTTP 200) | OK |
| `lint:twig` | OK |
| Essai dans un vrai navigateur (clic, glisser, zoom, bouton 💬) | À faire |

## Problemes rencontres (si applicable)
- Le résultat d'`include()` rangé dans une variable Twig est ré-échappé à l'affichage (guillemets des attributs cassés) :
  appel direct dans la balise.
- `importmap:require` a ajouté aussi `photoswipe.min.css`, inutile : retiré.
