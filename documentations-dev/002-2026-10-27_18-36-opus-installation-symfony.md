# Phase 0.2 : Installation de Symfony 7.4 LTS (webapp)

> Date : 27 octobre 2026, 18 h 36

## Objectif
Installer le squelette Symfony 7.4 LTS complet (pack `webapp`) dans le dépôt et le relier à la base MariaDB et à Mailpit.

## Actions realisees

### 1. Installation du squelette
**Fichier :** `composer.json`
- Symfony v7.4.21 avec le pack `webapp` : Doctrine, Twig, Security, AssetMapper/ImportMap, Mailer, Messenger,
  Turbo/Stimulus, PHPUnit, profiler.
- Installé via le conteneur `sarah_php` dans un dossier temporaire, puis fusionné dans le dépôt (qui n'était pas vide).

### 2. Pas de second fichier Docker
**Fichier :** `composer.json`
- `extra.symfony.docker: false` : Symfony Flex ne génère pas de `compose.yaml` concurrent de `docker-compose.yml`.

### 3. Configuration de l'environnement
**Fichier :** `.env`
- `DATABASE_URL` vers MariaDB (`sarah_db`), `MAILER_DSN` vers Mailpit.

## Fichiers crees
- Squelette Symfony : `assets/`, `bin/`, `config/`, `public/`, `src/`, `templates/`, `tests/`, `translations/`
- `composer.json`, `composer.lock`, `symfony.lock`, `importmap.php`, `phpunit.dist.xml`, `.editorconfig`, `.env.dev`, `.env.test`

## Fichiers modifies
- `.gitignore` : fusion de l'existant (`.idea`) avec celui de Symfony
- `.env` : connexion à la base et au serveur mail
- `docs/devops/docker-setup.md` : section installation marquée comme faite

## Verifications
| Point | Statut |
|-------|--------|
| `bin/console` en environnement dev | OK |
| Connexion Doctrine à MariaDB 11.4.10 | OK |
| Page servie par nginx sur http://localhost:8085 (404 « Welcome to Symfony! », normal sans route) | OK |
