# 010 — Étape 5 : commentaires et modération

- **Modèle** : Opus
- **Justification** : droits d'accès (voters, CSRF, modération) et données saisies par les membres → sécurité.

## Fichiers créés
- `src/Entity/Comment.php`, `src/Repository/CommentRepository.php`
- `src/Security/Voter/CommentVoter.php`
- `src/Form/CommentType.php`
- `src/Controller/Admin/CommentCrudController.php`
- `src/Migrations/Version20261028141611.php` : table `comment`
- `tests/Controller/CommentTest.php` (10 tests)

## Fichiers modifiés
- `src/Security/Voter/MediaVoter.php` : attribut `COMMENT`
- `src/Controller/MediaController.php` : formulaire de commentaire, action `comment_delete`
- `src/Controller/Admin/DashboardController.php` : menu et compteur
- `templates/media/show.html.twig`, `templates/base.html.twig` (messages flash), `assets/styles/app.css`
- `docs/architecture/overview.md`, `docs/architecture/database-schema.md`, `.claude/MEMORY.md`

## Résumé
54 tests verts, `lint:twig`, `lint:container` et `doctrine:schema:validate` OK. Détail dans
`documentations-dev/010-2026-10-28_15-18-opus-commentaires.md`.
