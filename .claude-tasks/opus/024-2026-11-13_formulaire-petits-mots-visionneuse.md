# 024 — Formulaire des petits mots dans la visionneuse

- **Modèle** : Opus (session active ; tâche de niveau Sonnet, faite directement pour garder le contexte)
- **Justification** : envoi en POST (CSRF sans état, voter `MEDIA_COMMENT`) depuis un contrôleur Stimulus.
- **Fichiers modifiés** :
  - `src/Controller/MediaController.php` (`media_comments` en GET + POST, formulaire nommé `lightbox_comment`)
  - `templates/media/_lightbox_comments.html.twig`
  - `assets/controllers/lightbox_controller.js`
  - `assets/styles/app.css`
  - `tests/Controller/CommentTest.php`
- **Résumé** : le panneau 💬 contient le formulaire ; envoi par fetch (jeton CSRF + en-tête), réponse = fragment à jour
  (200) ou avec l'erreur (422), sans quitter la photo. Saisie protégée des raccourcis de PhotoSwipe ; brouillon
  conservé à l'ouverture / fermeture du panneau ; fiche rechargée à la fermeture si un petit mot y a été ajouté.
  64 tests verts.
