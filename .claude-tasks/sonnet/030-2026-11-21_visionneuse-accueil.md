# 030 — Visionneuse : toutes les photos du fil de l'accueil (chargement à la demande)

- **Modèle** : Sonnet (agent `sarah-dev`)
- **Justification** : route JSON, service, repository et contrôleur Stimulus ; réutilise les droits et le mécanisme de la
  fiche 029, sans nouveau choix de sécurité.
- **Fichiers créés** : `src/Controller/LightboxController.php`, `src/Service/LightboxItems.php`,
  `.claude-tasks/sonnet/030-2026-11-21_visionneuse-accueil.md`,
  `documentations-dev/030-2026-11-21_10-40-sonnet-visionneuse-accueil.md`
- **Fichiers modifiés** : `src/Repository/MediaRepository.php`, `src/Twig/AppExtension.php`,
  `templates/home/index.html.twig`, `assets/controllers/lightbox_controller.js`, `tests/Controller/FrontTest.php`,
  `docs/architecture/overview.md`, `.claude/MEMORY.md`
- **Résumé** : la visionneuse de l'accueil parcourt toutes les photos du fil (filtre `?type=` respecté). La liste est
  chargée au premier clic depuis `/visionneuse/fil` (JSON, `private, no-store`) ; échec ou photo absente : la fiche est suivie.
