# Phase 1 : Architecture et authentification

> Date : 27 octobre 2026, 19 h 07 – 19 h 10

## Objectif
Poser l'architecture du site (privé, familial) et réserver tout le site aux membres connectés,
avec une commande pour créer le premier administrateur.

## Actions realisees

### 1. Documentation d'architecture
**Fichiers :** `docs/architecture/overview.md`, `docs/architecture/database-schema.md`
- Décisions : comptes individuels créés par l'admin (pas d'inscription), EasyAdmin, fichiers hors de `public/`,
  organisation en albums, tags, fil chronologique et commentaires.
- Plan de réalisation en 6 étapes et schéma de la base.

### 2. Entité utilisateur
**Fichier :** `src/Entity/User.php`
- Connexion par e-mail, nom affiché, `is_active` pour désactiver un compte sans le supprimer.
- Le hash du mot de passe est exclu de la session (`__serialize` n'y met qu'une empreinte CRC32C).

### 3. Pare-feu et contrôle d'accès
**Fichier :** `config/packages/security.yaml`
- Formulaire de connexion avec jeton CSRF, « rester connecté » 30 jours, 5 tentatives par minute (`login_throttling`).
- Site entièrement privé : seule `/login` est publique, `/admin` exige `ROLE_ADMIN`, tout le reste `ROLE_USER`.
- `UserChecker` refuse la connexion des comptes désactivés.

### 4. Création de l'administrateur
**Fichier :** `src/Command/CreateAdminCommand.php`
- `app:create-admin <email> [nom]` : mot de passe demandé de façon masquée (pas d'historique shell), 12 caractères minimum.
- Promeut un compte existant si l'e-mail existe déjà.

### 5. Non-indexation
**Fichiers :** `src/EventSubscriber/NoIndexSubscriber.php`, `public/robots.txt`
- En-tête `X-Robots-Tag: noindex, nofollow` sur toutes les réponses, `robots.txt` qui interdit tout.

## Fichiers crees
- `src/Entity/User.php`, `src/Repository/UserRepository.php`
- `src/Security/UserChecker.php` : refus des comptes désactivés
- `src/Controller/SecurityController.php` : `/login`, `/logout`
- `src/Controller/HomeController.php` : accueil provisoire
- `src/Command/CreateAdminCommand.php` : commande `app:create-admin`
- `src/EventSubscriber/NoIndexSubscriber.php` : en-tête de non-indexation
- `src/Migrations/Version20261027180903.php` : tables `user` et `messenger_messages`
- `templates/security/login.html.twig`, `templates/home/index.html.twig`
- `tests/Controller/SecurityControllerTest.php`, `tests/Command/CreateAdminCommandTest.php`
- `docs/architecture/overview.md`, `docs/architecture/database-schema.md`

## Fichiers modifies
- `config/packages/security.yaml` : pare-feu, connexion, contrôle d'accès
- `config/packages/translation.yaml` : locale `fr`
- `templates/base.html.twig` : en-tête avec le nom du membre et la déconnexion
- `public/robots.txt` : tout interdire
- `composer.json` / `composer.lock` : `symfony/rate-limiter`
- `docs/devops/docker-setup.md` : base de test

## Verifications
| Point | Statut |
|-------|--------|
| Tests PHPUnit (7 tests, 25 assertions) | OK |
| `lint:container` | OK |
| `/` → 302 vers `/login` via nginx | OK |
| `/login` → 200 avec `X-Robots-Tag: noindex, nofollow` | OK |

## Problemes rencontres (si applicable)
- Une route inexistante répond 404 **avant** le pare-feu : impossible de tester l'accès à `/admin` tant qu'aucune route
  n'existe. Tests reportés à la phase 2.
- La base de test `sarah_db_test` n'est pas créée par défaut et `sarah_user` n'y a pas de droits : création et `GRANT`
  documentés dans `docs/devops/docker-setup.md`.
