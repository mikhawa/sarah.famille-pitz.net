# 019 — Miniatures absentes en production (404)

- **Modèle** : Opus
- **Justification** : bug de production propre à l'environnement (libgd de lsphp83), diagnostic à distance.

## Fichiers modifiés
- `src/Service/MediaUploader.php` : réduction par `imagecopyresampled` au lieu d'`imagescale` ; échec d'écriture
  d'une miniature = envoi refusé ; nouvelle méthode publique `regenerateThumbnails()`
- `src/Command/RegenerateThumbnailsCommand.php` (créé) : `app:media:regenerate-thumbs [--all]`
- `tests/Service/MediaUploaderTest.php` : hauteur de la petite miniature, test de régénération
- `docs/devops/prod/env.local.example` : `MEDIA_X_ACCEL_PREFIX` vide (CyberPanel)
- `docs/devops/cyberpanel.md` : ligne « Miniatures absentes » dans le tableau des problèmes
- `.claude/MEMORY.md`

## Résumé
Détail dans `documentations-dev/019-2026-11-06_14-32-opus-miniatures-prod.md`.
