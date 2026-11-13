# 025 — Alignement des boutons de la visionneuse

- **Modèle** : Opus (session active ; correction CSS de niveau Haiku, faite directement)
- **Justification** : bouton de la visionneuse décalé vers le haut sur mobile (capture d'écran de l'utilisateur).
- **Fichiers modifiés** : `assets/styles/app.css` (et `.comment-form label` au-dessus du champ, demandé juste avant)
- **Résumé** : `photoswipe.css`, importé par `lightbox_controller.js`, est chargé après `app.css` et impose
  `display: block` à `.pswp__button` (même spécificité) : `display: grid` des boutons ℹ️ et 💬 était ignoré, l'icône
  restait en haut du bouton. Sélecteurs préfixés par `.pswp`.
