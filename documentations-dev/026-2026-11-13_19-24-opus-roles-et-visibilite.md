# Phase 8 : Rôles et visibilité des albums et médias

> Date : 13 novembre 2026

## Objectif
Trois rôles (`ROLE_USER`, `ROLE_ADMIN`, `ROLE_SUPER_ADMIN`) et une visibilité par album et par média :
public (famille), privé (admins), super-admin seul ; verrou du super-admin prioritaire sur les admins ;
droit d'envoi accordé aux membres dans les albums publics.

## Actions realisees

### 1. Modèle
- `App\Enum\Visibility` (`public`, `private`, `super_admin`) : niveau requis, `allowedFor(User)`.
- `User` : un seul rôle (`getLevel()` / `setLevel()`, `isSuperAdmin()`), `canUpload`.
- `Album` : `visibility` (privé par défaut), `visibilityLocked`. `Media` : `visibility` nullable (« comme l'album »,
  privé sans album), `visibilityLocked`, `getEffectiveVisibility()`.
- Règle unique : `Media::isVisibleTo()` (l'album est une barrière) et son équivalent DQL
  `MediaRepository::restrictToVisible()` ; `AlbumRepository::restrictToVisible()`.
- `security.yaml` : `role_hierarchy` (super-admin ⊃ admin ⊃ membre).
- Migration `Version20261113181739` : tout l'existant devient privé (choix de l'utilisateur).

### 2. Site
- Fil, albums (et compteurs, couvertures), tags, voisins précédent/suivant : filtrés selon le compte connecté.
- Album, média, fichiers, miniatures, petits mots invisibles : **404** (`#[IsGranted(…, statusCode: 404)]`).
- `/albums/{slug}/envoyer` : envoi par lots pour les membres autorisés (`AlbumVoter::UPLOAD`, albums publics),
  via `MediaImporter` (partagé avec l'administration) ; `media_upload.js` chargé aussi par `app.js`.
- Fiche : « Supprimer ce souvenir » (`MediaVoter::DELETE` : admins, ou membre auteur de l'envoi).
- Badge 🔒 de visibilité pour les admins sur les albums non publics.

### 3. Administration
- `VisibilityCrudTrait` : champ « Visibilité » (Public / Privé, + « Super-admin seul » pour le super-admin ;
  « Comme l'album » pour les médias), « Verrouillé » réservé au super-admin, verrou rétabli côté serveur.
- Albums, médias, commentaires : listes filtrées (`createIndexQueryBuilder`) et accès direct refusé
  (`setEntityPermission`) ; listes déroulantes (album, couverture, filtres) limitées au visible.
- Comptes : rôle à 3 niveaux (super-admin seul), « Peut envoyer des photos » ; un admin ne gère que les membres et
  son propre compte (`UserVoter::MANAGE`) ; personne ne baisse son rôle ni ne se désactive.
- `app:create-admin … --super` (jamais de rétrogradation).

## Fichiers crees
- `src/Enum/Visibility.php`, `src/Security/Voter/AlbumVoter.php`, `src/Security/Voter/UserVoter.php`
- `src/Service/MediaImporter.php`, `src/Controller/Admin/VisibilityCrudTrait.php`
- `templates/album/upload.html.twig`, `src/Migrations/Version20261113181739.php`, `tests/Controller/PermissionTest.php`

## Fichiers modifies
Voir `.claude-tasks/opus/026-2026-11-13_roles-et-visibilite.md`.

## Verifications
| Point | Statut |
|-------|--------|
| Membre / admin / super-admin : listes et accès directs (9 cas de visibilité) | OK |
| Fichiers et miniatures d'un média caché : 404 | OK |
| Admin : Public / Privé seulement ; verrou contourné → valeur d'origine | OK |
| « Super-admin seul » invisible des admins dans l'administration | OK |
| Envoi par un membre autorisé (album public), refus sinon | OK |
| Suppression d'un média : auteur / admin, CSRF | OK |
| Comptes : admin limité aux membres, promotion par le super-admin, garde-fous sur soi | OK |
| `app:create-admin --super` | OK |
| Suite complète (74 tests), `doctrine:schema:validate`, `lint:twig` | OK |
| Essai dans un navigateur | À faire (utilisateur) |

## Problemes rencontres
- Filtre DQL : un média sans album ni visibilité (privé) était exclu même pour les admins — corrigé, couvert par test.
- EasyAdmin vérifie `setEntityPermission()` avec un sujet `null` sur « Créer » : les voters l'acceptent pour un admin.
- En modifiant son propre compte, le formulaire modifie aussi l'utilisateur connecté (même objet Doctrine) :
  droits évalués sur le rôle d'origine.
- Champs virtuels EasyAdmin affichés « Inaccessible » : accesseurs `getVisibilityLabel()` / `getLevelLabel()`.
