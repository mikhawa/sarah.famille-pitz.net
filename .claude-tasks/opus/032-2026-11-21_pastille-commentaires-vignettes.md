# 032 — Nombre de commentaires en pastille rouge sur les vignettes

- **Modèle** : Opus (session en cours ; tâche de niveau Sonnet : gabarits, Twig, repository, test)
- **Justification** : petite évolution du front, sans nouveau choix de sécurité ; faite dans la suite directe de la fiche 031.
- **Fichiers créés** : `.claude-tasks/opus/032-2026-11-21_pastille-commentaires-vignettes.md`,
  `documentations-dev/032-2026-11-21_17-35-opus-pastille-commentaires-vignettes.md`
- **Fichiers modifiés** : `src/Repository/CommentRepository.php`, `src/Twig/AppExtension.php`,
  `templates/media/_tile.html.twig`, `templates/media/_lightbox_grid.html.twig`, `templates/home/index.html.twig`,
  `assets/styles/app.css`, `tests/Controller/FrontTest.php`
- **Résumé** : pastille rouge « 💬 N » en haut à droite des vignettes (accueil, albums, tags), absente sans commentaire.
  Fonction Twig `comment_counts(medias)` : une requête par page ; masqués comptés pour les administrateurs seulement,
  comme sur la fiche du média.
