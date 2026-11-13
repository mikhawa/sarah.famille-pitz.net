# 023 — Petits mots lisibles dans la visionneuse

- **Modèle** : Opus (session active) — tâche de niveau Sonnet (Stimulus, Twig, contrôleur, tests), faite directement
  pour garder le contexte de la session.
- **Justification** : nouvelle route en lecture protégée par `MediaVoter::VIEW`, contrôleur Stimulus PhotoSwipe.
- **Fichiers modifiés** :
  - `src/Controller/MediaController.php` (route `media_comments`)
  - `templates/media/_lightbox_comments.html.twig` (créé)
  - `templates/media/_lightbox_attributes.html.twig`
  - `assets/controllers/lightbox_controller.js`
  - `assets/styles/app.css`
  - `tests/Controller/CommentTest.php`, `tests/Controller/FrontTest.php`
- **Résumé** : bouton 💬 (avec le nombre de messages) dans la barre de la visionneuse, qui ouvre / referme un panneau
  translucide en bas de la photo avec les petits mots (fragment HTML chargé à chaque photo). Choix ouvert / fermé
  retenu dans le navigateur. L'ancien bouton « fiche » passe en ℹ️. 62 tests verts.
