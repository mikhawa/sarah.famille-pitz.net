# Phase 14 : Nombre de commentaires sur les vignettes

> Date : 21 novembre 2026

## Objectif
Afficher le nombre de petits mots de chaque média en pastille rouge, en haut à droite de sa vignette.

## Actions realisees

### 1. Comptage
- `CommentRepository::countByMedia($medias, $includeHidden)` : une requête groupée pour toute la page.
- Fonction Twig `comment_counts(medias)` (`AppExtension`) : commentaires masqués comptés pour les administrateurs
  seulement, comme sur la fiche du média.

### 2. Gabarits et style
- `home/index.html.twig` et `media/_lightbox_grid.html.twig` (albums, tags) appellent `comment_counts()` une fois ;
  `media/_tile.html.twig` affiche `.tile-comments` (« 💬 N », titre « N petits mots », texte pour lecteur d'écran)
  seulement s'il y a au moins un commentaire.
- `.tile-comments` : fond rouge, en haut à droite (la durée des vidéos reste en bas à droite).

### 3. Test (`FrontTest::testTilesShowTheNumberOfComments`)
- Membre : accueil et album, 2 sur 3 (un masqué), pas de pastille sans commentaire ; administrateur : 3.

## Fichiers crees
- `.claude-tasks/opus/032-2026-11-21_pastille-commentaires-vignettes.md`

## Fichiers modifies
- `src/Repository/CommentRepository.php`, `src/Twig/AppExtension.php`, `templates/media/_tile.html.twig`,
  `templates/media/_lightbox_grid.html.twig`, `templates/home/index.html.twig`, `assets/styles/app.css`,
  `tests/Controller/FrontTest.php`

## Verifications
| Point | Statut |
|-------|--------|
| `lint:twig templates/` | OK |
| `docker compose exec -T php php bin/phpunit` → `OK (81 tests, 548 assertions)` | OK |
| Essai dans le navigateur | Non fait (à vérifier par l'utilisateur) |

## Problemes rencontres
- Aucun.
