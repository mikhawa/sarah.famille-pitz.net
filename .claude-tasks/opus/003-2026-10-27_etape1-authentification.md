# 003 — Étape 1 : authentification et compte administrateur

- **Modèle utilisé** : Opus
- **Justification** : sécurité (pare-feu, contrôle d'accès, gestion des mots de passe) → Opus selon `.claude/models.md`.

## Fichiers créés / modifiés

- `src/Entity/User.php`, `src/Repository/UserRepository.php`
- `src/Security/UserChecker.php` — refuse les comptes désactivés
- `src/Controller/SecurityController.php` — `/login`, `/logout`
- `src/Controller/HomeController.php` — accueil provisoire `/`
- `src/Command/CreateAdminCommand.php` — `app:create-admin`
- `src/EventSubscriber/NoIndexSubscriber.php` — en-tête `X-Robots-Tag`
- `src/Migrations/Version20261027180903.php` — tables `user` et `messenger_messages`
- `templates/base.html.twig`, `templates/security/login.html.twig`, `templates/home/index.html.twig`
- `config/packages/security.yaml`, `config/packages/translation.yaml` (locale `fr`)
- `public/robots.txt`
- `tests/Controller/SecurityControllerTest.php`, `tests/Command/CreateAdminCommandTest.php`
- `composer.json` / `composer.lock` — ajout de `symfony/rate-limiter`

## Résumé

- Fournisseur d'utilisateurs Doctrine (`email`), formulaire de connexion avec CSRF, « rester connecté » 30 jours,
  limitation à 5 tentatives par minute.
- Site entièrement privé : seule `/login` est publique, `/admin` réservé à `ROLE_ADMIN`.
- Hash du mot de passe exclu de la session (`__serialize`).
- `app:create-admin <email> [nom]` : mot de passe demandé de façon masquée, 12 caractères minimum ;
  promeut un compte existant si l'e-mail existe déjà.
- Base de test `sarah_db_test` créée et droits accordés à `sarah_user`.

Vérifié : 7 tests / 25 assertions OK, `lint:container` OK, via nginx `/` → 302 vers `/login`,
`/login` → 200 avec `X-Robots-Tag: noindex, nofollow`.

Les tests d'accès à `/admin` seront ajoutés à l'étape 2 (aucune route `/admin` n'existe encore : Symfony renvoie 404
avant le pare-feu).
