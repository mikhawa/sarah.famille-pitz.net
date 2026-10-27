# Architecture globale

## Objectif

Site familial **privé** pour partager les photos, la musique et les vidéos de Sarah avec la famille proche.
Rien n'est public : tout le site est derrière une connexion.

## Décisions

| Sujet          | Choix                                                                                   |
|----------------|-----------------------------------------------------------------------------------------|
| Accès          | Comptes individuels par membre de la famille, créés par l'admin (pas d'inscription libre) |
| Administration | EasyAdmin (`easycorp/easyadmin-bundle`) sous `/admin`                                   |
| Stockage       | Fichiers sur le VPS, **hors de `public/`**, servis par un contrôleur qui vérifie les droits |
| Organisation   | Albums, tags, fil chronologique, commentaires des membres                              |

## Rôles

- `ROLE_USER` : membre de la famille. Consulte les médias et commente.
- `ROLE_ADMIN` : gère les comptes, albums, tags, médias et modère les commentaires.

## Sécurité

- `access_control` : tout `^/` exige `ROLE_USER`, sauf `/login` et `/reset-password` ; `^/admin` exige `ROLE_ADMIN`.
- Pas de formulaire d'inscription : l'admin crée le compte, le membre reçoit un e-mail pour définir son mot de passe
  (`symfonycasts/reset-password-bundle`).
- Limitation des tentatives de connexion (`login_throttling`).
- Voters : `MediaVoter` (VIEW, COMMENT), `CommentVoter` (EDIT, DELETE : auteur ou admin).
- Non-indexation : en-tête `X-Robots-Tag: noindex, nofollow` et `robots.txt` qui interdit tout.
- **Protection de l'enfant** : les métadonnées EXIF sensibles (GPS notamment) sont supprimées des photos à l'upload ;
  seule la date de prise de vue est conservée en base.

## Stockage des médias

```
var/storage/media/
├── originals/AAAA/MM/<uuid>.<ext>     # fichier uploadé (photo nettoyée, audio, vidéo)
└── thumbs/AAAA/MM/<uuid>_<taille>.webp # miniatures générées à l'upload (photos)
```

- Les fichiers ne sont jamais accessibles directement par URL.
- `MediaFileController` (`/media/{id}/file`, `/media/{id}/thumb/{taille}`) vérifie `MediaVoter::VIEW` puis renvoie une
  `BinaryFileResponse` (gère les requêtes `Range`, nécessaires pour avancer dans une vidéo ou une musique).
- En prod : délégation à nginx via `X-Accel-Redirect` vers une `location internal` pour les gros fichiers.
- Miniatures générées avec GD (déjà présent dans l'image PHP). Pas de LiipImagine : son cache est écrit dans `public/`.
- Vidéos : formats lus nativement par les navigateurs (MP4/H.264, WebM), sans transcodage dans un premier temps.
  Une conversion ffmpeg via Messenger pourra être ajoutée plus tard.
- Limite d'upload actuelle : 256 Mo par fichier (`docker/php/php.ini`, `docker/nginx/default.conf`).

## Couches

- `src/Entity/` : `User`, `Album`, `Media`, `Tag`, `Comment` — voir [database-schema.md](database-schema.md)
- `src/Service/` : `MediaUploader` (validation, rangement, nettoyage EXIF, miniatures), `MediaStorage` (chemins)
- `src/Security/Voter/` : `MediaVoter`, `CommentVoter`
- `src/Controller/Admin/` : tableau de bord et CRUD EasyAdmin
- `src/Controller/` : front (fil chronologique, albums, tags, fiche média, commentaires), `MediaFileController`

## Front

- Accueil : fil chronologique des médias les plus récents (pagination).
- Pages album, page tag, fiche média avec lecteur `<img>`, `<audio>` ou `<video>` et commentaires.
- Twig + AssetMapper/ImportMap + Stimulus/Turbo (pas de build Node).

## Plan de réalisation

1. **Authentification** : entité `User`, connexion, `access_control`, commande `app:create-admin`.
2. **Administration** : EasyAdmin, CRUD utilisateurs, albums, tags.
3. **Médias** : entité `Media`, upload privé, nettoyage EXIF, miniatures, `MediaFileController`, CRUD admin.
4. **Front** : fil chronologique, albums, tags, fiche média et lecteurs.
5. **Commentaires** : ajout par les membres, modération dans l'admin.
6. **Prod** : `X-Accel-Redirect`, sauvegardes de la BDD et de `var/storage/`.
