# 022 — Réduction automatique des grandes photos

- **Modèle** : Opus
- **Justification** : traitement des médias (mémoire PHP/GD, risque de plantage à l'envoi), seuil calculé selon
  `memory_limit`.
- **Fichiers modifiés** :
  - `src/Service/MediaUploader.php`
  - `src/Form/MediaUploadType.php`
  - `docs/architecture/overview.md`
  - `tests/Service/MediaUploaderTest.php`
  - `tests/Support/MediaFixtures.php`
- **Résumé** : une photo refusée (« 40 mégapixels maximum », image agrandie par IA) → les photos dont le plus grand
  côté dépasse 6000 px sont réduites à 6000 px (avant la rotation EXIF, pour économiser la mémoire). Le refus fixe à
  40 Mpx est remplacé par un contrôle de la mémoire réellement nécessaire (image + copie réduite, ~5 octets/pixel,
  80 % de la mémoire libre, plafond 150 Mpx) avec un message invitant à réduire la photo. GIF inchangés (animation).
  61 tests verts.
