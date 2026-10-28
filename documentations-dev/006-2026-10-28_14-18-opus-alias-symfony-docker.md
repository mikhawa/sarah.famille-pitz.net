# Phase 3.1 : Raccourcis Symfony dans l'image PHP

> Date : 28 octobre 2026, 14 h 18

## Objectif
Rendre les alias de `RACCOURCIS.md` (Symfony, Doctrine, tests, qualité, Composer) disponibles directement dans le conteneur PHP.

## Actions realisees

### 1. Fichier d'alias
**Fichier :** `docker/php/bash_aliases`
- Reprise des alias de la section « Symfony Commands » de `RACCOURCIS.md` (`pbc`, `cc`, `mm`, `migrate`, `test`, `lint`…).

### 2. Copie dans l'image
**Fichier :** `docker/php/Dockerfile`
- `COPY --chown=sarah:sarah docker/php/bash_aliases /home/sarah/.bash_aliases`.
- Le `.bashrc` par défaut de Debian charge `~/.bash_aliases` : aucun autre réglage nécessaire.

### 3. Documentation
**Fichier :** `RACCOURCIS.md`
- Note indiquant que les alias sont intégrés à l'image et disponibles avec `dphp` (bash), pas avec `uphp` (sh).

## Fichiers crees
- `docker/php/bash_aliases` : alias du conteneur PHP

## Fichiers modifies
- `docker/php/Dockerfile` : copie du fichier d'alias
- `RACCOURCIS.md` : note d'utilisation, correction de l'alias `php-cs-fixer`

## Verifications
| Point | Statut |
|-------|--------|
| Reconstruction de l'image | OK |
| `pbc --version` dans `docker compose exec php bash` → Symfony v7.4.21 | OK |

## Problemes rencontres (si applicable)
- L'alias `php-cs-fixer` de `RACCOURCIS.md` était coupé sur deux lignes (invalide) : corrigé.
- L'alias `test` masque la commande intégrée `test` du shell en session interactive (sans effet sur les scripts).
- `wind` et `design` appellent `tailwind:build`, qui n'est pas installé dans le projet.
