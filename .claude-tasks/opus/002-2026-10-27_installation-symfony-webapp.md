# 002 — Installation de Symfony 7.4 webapp

- **Modèle utilisé** : Opus
- **Justification** : installation standard (niveau Haiku/Sonnet), réalisée dans la session Opus en cours à la suite de la tâche 001.

## Fichiers créés / modifiés

- Squelette Symfony complet : `assets/`, `bin/`, `config/`, `public/`, `src/`, `templates/`, `tests/`, `translations/`,
  `composer.json`, `composer.lock`, `symfony.lock`, `importmap.php`, `phpunit.dist.xml`, `.editorconfig`, `.env`, `.env.dev`, `.env.test`
- `.gitignore` : fusion de l'existant (`.idea`) avec celui de Symfony
- `.env` : `DATABASE_URL` vers MariaDB (`sarah_db`) et `MAILER_DSN` vers Mailpit
- `docs/devops/docker-setup.md` : section installation marquée comme faite

## Résumé

Installation de Symfony v7.4.21 avec le pack `webapp` (Doctrine, Twig, Security, AssetMapper/ImportMap, Mailer, Messenger,
Turbo/Stimulus, PHPUnit, profiler…) via le conteneur `sarah_php`, dans un dossier temporaire puis fusion dans le dépôt.
La génération Docker de Symfony Flex a été désactivée (`extra.symfony.docker: false`) pour ne pas créer de `compose.yaml`
concurrent de `docker-compose.yml`.

Vérifié : `bin/console` fonctionne (env dev), connexion Doctrine à MariaDB 11.4.10 OK, page d'accueil Symfony servie
par nginx sur http://localhost:8085 (404 « Welcome to Symfony! », normal sans route).
