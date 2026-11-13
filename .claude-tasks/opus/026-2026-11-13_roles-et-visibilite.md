# 026 — Rôles (membre, admin, super-admin) et visibilité des contenus

- **Modèle** : Opus
- **Justification** : sécurité et architecture transverse (qui voit les photos de l'enfant) : rôles, voters, filtrage
  de toutes les requêtes, administration, migration de données.
- **Décisions de l'utilisateur** : 3 niveaux (Public / Privé / Super-admin seul) + verrou du super-admin ; tout
  l'existant et les nouveaux albums privés ; envoi d'un membre visible tout de suite ; admins gèrent les membres,
  le super-admin gère tout.
- **Fichiers créés** : `src/Enum/Visibility.php`, `src/Security/Voter/AlbumVoter.php`, `src/Security/Voter/UserVoter.php`,
  `src/Service/MediaImporter.php`, `src/Controller/Admin/VisibilityCrudTrait.php`, `templates/album/upload.html.twig`,
  `src/Migrations/Version20261113181739.php`, `tests/Controller/PermissionTest.php`
- **Fichiers modifiés** : entités `User`, `Album`, `Media` ; dépôts `Media`, `Album`, `Tag` ; voters `Media`, `Comment` ;
  contrôleurs `Album`, `Home`, `Tag`, `Media`, `MediaFile` ; CRUD `Album`, `Media`, `Comment`, `User` ;
  `MediaUploadType` ; `CreateAdminCommand` (`--super`) ; `security.yaml` (`role_hierarchy`) ; gabarits `album/index`,
  `album/show`, `media/show` ; `assets/app.js`, `assets/styles/app.css` ; docs `overview.md`, `database-schema.md` ;
  tests existants (contenus rendus publics) et `CreateAdminCommandTest`.
- **Résumé** : voir `documentations-dev/026-…`. 74 tests verts.
