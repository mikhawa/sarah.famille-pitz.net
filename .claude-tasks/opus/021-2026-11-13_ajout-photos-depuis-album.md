# 021 — Ajout de photos depuis la page d'un album

- **Modèle** : Opus
- **Justification** : envoi de médias et droits d'administration (sécurité des médias de l'enfant) ; réutilisation
  du circuit d'envoi existant plutôt qu'un second point d'entrée.
- **Fichiers modifiés** :
  - `src/Controller/Admin/MediaCrudController.php`
  - `templates/album/show.html.twig`
  - `templates/admin/media/upload.html.twig`
  - `assets/admin/media_upload.js`
  - `tests/Controller/MediaTest.php`
- **Résumé** : bouton « ＋ Ajouter des photos » (administrateurs seulement) sur la page d'un album, vers la page
  d'envoi par lots de l'admin avec `?album=<id>` : album présélectionné, titre de page adapté, lien
  « Revenir à l'album » en fin d'envoi (sans JavaScript : redirection vers l'album si aucun fichier refusé).
  Test fonctionnel ajouté ; 60 tests verts.
