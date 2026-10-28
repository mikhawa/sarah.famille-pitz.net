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
  (`AccountInvitationMailer`, qui réutilise le jeton de `symfonycasts/reset-password-bundle` ; liens valables 48 h).
  L'admin peut renvoyer l'invitation depuis la liste des comptes.
- L'admin connecté ne peut ni se retirer ses droits, ni se désactiver, ni supprimer son propre compte.
- E-mails envoyés de façon synchrone (pas encore de worker Messenger).
- Limitation des tentatives de connexion (`login_throttling`).
- Voters : `MediaVoter` (VIEW, COMMENT : membre actif), `CommentVoter` (DELETE : auteur ou admin).
- Non-indexation : en-tête `X-Robots-Tag: noindex, nofollow` et `robots.txt` qui interdit tout.
- **Protection de l'enfant** : les métadonnées EXIF sensibles (GPS notamment) sont supprimées des photos à l'upload ;
  seule la date de prise de vue est conservée en base.

## Stockage des médias

```
var/storage/media/                       (var/storage/media_test/ en environnement de test)
├── originals/AAAA/MM/<hex32>.<ext>      # fichier nettoyé (photo réencodée, vidéo sans métadonnées, audio)
└── thumbs/AAAA/MM/<hex32>_<taille>.webp # miniatures small (400 px) et large (1600 px)
```

- Les fichiers ne sont jamais accessibles directement par URL (`var/` est hors de `public/`, et ignoré par git).
- `MediaFileController` (`/media/{id}/file`, `/media/{id}/thumb/{small|large}`) vérifie `MediaVoter::VIEW` (membre actif)
  puis renvoie une `BinaryFileResponse` (requêtes `Range` : avance rapide dans les vidéos et la musique).
  `?download=1` force le téléchargement. Cache navigateur privé 24 h.
- En prod (étape 6) : délégation à nginx via `X-Accel-Redirect` vers une `location internal` pour les gros fichiers.
- Suppression d'un média (admin ou autre) : `MediaFileCleanupListener` efface l'original et les miniatures.

### Traitement à l'envoi (`MediaUploader`)

Le type est détecté sur le **contenu** du fichier, pas sur son extension.

| Type   | Formats acceptés          | Traitement                                                                           |
|--------|---------------------------|--------------------------------------------------------------------------------------|
| Photo  | JPEG, PNG, WebP, GIF      | Orientation EXIF appliquée puis **réencodage GD** : toutes les métadonnées (GPS compris) disparaissent. Date de prise de vue conservée en base. GIF copié tel quel (animation). 40 mégapixels max. |
| Vidéo  | MP4, WebM, MOV            | `ffmpeg -map_metadata -1 -c copy` : métadonnées (GPS compris) retirées **sans réencodage**, `faststart` pour MP4/MOV. Durée, dimensions et date de tournage lues avant nettoyage. Image d'aperçu extraite à 1 s. |
| Audio  | MP3, M4A, OGG, WAV, FLAC  | Copié tel quel, durée lue avec ffprobe.                                               |

- HEIC (iPhone) refusé avec un message : GD ne sait pas le lire. Exporter en JPEG (réglage iPhone « Le plus compatible »).
- Pas de transcodage vidéo : une vidéo HEVC/MOV peut ne pas se lire dans tous les navigateurs.
- Limites : 256 Mo par fichier, 1 Go et 50 fichiers par envoi (`docker/php/php.ini`, `docker/nginx/default.conf`).
- ffmpeg/ffprobe doivent être installés sur le serveur (présents dans l'image Docker).

## Couches

- `src/Entity/` : `User`, `Album`, `Media`, `Tag`, `Comment` — voir [database-schema.md](database-schema.md)
- `src/Enum/MediaType` : `photo`, `audio`, `video`
- `src/Service/` : `MediaUploader` (validation, nettoyage, miniatures), `MediaStorage` (chemins), `Ffmpeg` (ffprobe/ffmpeg),
  `AccountInvitationMailer`
- `src/Security/Voter/` : `MediaVoter`, `CommentVoter`
- `src/Controller/Admin/` : tableau de bord et CRUD EasyAdmin ; `MediaCrudController::upload` (`/admin/media/upload`)
  pour l'envoi de plusieurs fichiers à la fois, rangés dans un album avec des tags
- `src/Controller/` : front (fil chronologique, albums, tags, fiche média, commentaires), `MediaFileController`

## Front

| Route          | URL               | Contenu                                                                     |
|----------------|-------------------|-----------------------------------------------------------------------------|
| `app_home`     | `/`               | Fil chronologique, du plus récent au plus ancien, regroupé par mois ; filtre `?type=photo\|video\|audio` |
| `album_index`  | `/albums`         | Albums (date de l'événement, sinon de création) et thèmes (tags utilisés)   |
| `album_show`   | `/albums/{slug}`  | Médias de l'album, du plus ancien au plus récent                            |
| `tag_show`     | `/tags/{slug}`    | Médias du tag, du plus récent au plus ancien                                |
| `media_show`   | `/media/{id}`     | Lecteur `<img>`, `<video>` ou `<audio>`, infos, tags, téléchargement        |

- Date du fil : `Media::getSortDate()` = date de prise de vue, sinon date d'envoi (`COALESCE` dans `MediaRepository`).
- Pagination : 24 médias par page (`App\Pagination\Page`, `?page=N`, 404 au-delà de la dernière page).
- Fiche média : précédent/suivant dans l'album du média (ordre de l'album), sinon dans le fil ;
  flèches du clavier et glissement du doigt (contrôleur Stimulus `media-nav`).
- Album sans couverture choisie : première photo ou vidéo de l'album.
- Un membre désactivé pendant qu'il est connecté est déconnecté à sa requête suivante (`DeactivatedUserSubscriber`).
- Dates en français via `twig/intl-extra` (`format_date`) ; filtre Twig `duration` (`App\Twig\AppExtension`).
- Twig + AssetMapper/ImportMap + Stimulus/Turbo (pas de build Node) ; feuille de style unique `assets/styles/app.css`.

## Commentaires

- Formulaire « petit mot » sous chaque média (`media_show` en GET et POST, `CommentType`, CSRF du composant Form) :
  succès → redirection 303 vers `#comment-{id}`, erreur → 422 (attendu par Turbo).
- Affichés du plus ancien au plus récent ; texte échappé, retours à la ligne conservés ; 2000 caractères maximum.
- Un membre supprime son propre commentaire (`POST /comments/{id}/delete`, jeton CSRF `delete-comment-{id}`) ;
  l'admin peut supprimer tous les commentaires. Pas de modification du texte après coup.
- Modération (`CommentCrudController`, `/admin/comment`) : liste, filtres (masqué, auteur, média), interrupteur « Masqué ».
  L'admin ne peut ni écrire ni réécrire un commentaire. Un commentaire masqué reste en base, visible seulement des admins
  (signalé « Masqué par l'administration »).
- Suppression d'un média → ses commentaires aussi (`ON DELETE CASCADE`) ; suppression d'un compte → commentaires
  conservés, affichés « Ancien membre » (`ON DELETE SET NULL`).

## Plan de réalisation

1. ✅ **Authentification** : entité `User`, connexion, `access_control`, commande `app:create-admin`.
2. ✅ **Administration** : EasyAdmin, CRUD utilisateurs (avec invitation), albums, tags.
3. ✅ **Médias** : entité `Media`, upload privé, nettoyage EXIF/GPS, miniatures, `MediaFileController`, CRUD admin.
4. ✅ **Front** : fil chronologique, albums, tags, fiche média et lecteurs.
5. ✅ **Commentaires** : ajout par les membres, modération dans l'admin.
6. **Prod** : `X-Accel-Redirect`, sauvegardes de la BDD et de `var/storage/`.
