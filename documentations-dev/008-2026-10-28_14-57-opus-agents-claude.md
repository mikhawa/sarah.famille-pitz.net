# Phase 4.1 : Agents Claude Code du projet

> Date : 28 octobre 2026, 14 h 57

## Objectif
Rendre utilisables les trois agents de `.claude/agents/` (architecte, développeur, CRUD), repris d'un autre projet,
en corrigeant ce qui les empêchait de se charger et ce qui les aurait induits en erreur.

## Actions realisees

### 1. Noms de fichiers
**Fichiers :** `.claude/agents/sarah-architecte.md`, `.claude/agents/sarah-crud.md`
- `sarah-crud` n'avait pas d'extension `.md` : Claude Code l'ignorait.
- `sarah-archtecte.md` (faute de frappe) renommé `sarah-architecte.md`.

### 2. Contenu adapté au projet
**Fichiers :** `.claude/agents/sarah-architecte.md`, `sarah-dev.md`, `sarah-crud.md`
- Stack réelle : PHP 8.3 (et non 8.5), EasyAdmin 5.8 (et non 4), pas de Bootstrap, pas de SPA React ni d'API JSON.
- Rôles réels `ROLE_USER` / `ROLE_ADMIN` (suppression de `ROLE_PEDAGO`, `ROLE_FORMATEUR`…), site privé, protection de l'enfant.
- Lecture de `.claude/MEMORY.md` au lieu de `.claude/CONTEXT.md` et `.claude/memory/*`, qui n'existent pas.
- Tests : `WebTestCase` sans Foundry ni Panther, modèle `tests/Controller/FrontTest.php`.
- Pas de `declare(strict_types=1)`, que le code du projet n'utilise pas.
- `sarah-crud` : suppression de la consigne `asset-map:compile` en dev, qui crée `public/assets/` et masque ensuite
  toute modification CSS/JS.

### 3. Répartition des modèles
**Fichier :** `.claude/models.md`
- Retrait de l'agent `design-review` (inexistant), `cf2m-architecte` remplacé par `sarah-architecte`.

## Fichiers crees
Aucun (renommages uniquement).

## Fichiers modifies
- `.claude/agents/sarah-architecte.md` : renommé et adapté
- `.claude/agents/sarah-dev.md` : adapté
- `.claude/agents/sarah-crud.md` : renommé et adapté
- `.claude/models.md` : références corrigées

## Verifications
| Point | Statut |
|-------|--------|
| Les trois fichiers ont l'extension `.md` et un en-tête `name` / `description` / `model` valide | OK |
| Plus aucune référence à des fichiers ou rôles inexistants | OK |
| Agents visibles dans `/agents` | À vérifier après redémarrage de la session |

## Problemes rencontres (si applicable)
- Claude Code ne lit `.claude/agents/` qu'au démarrage : les agents ne sont pas disponibles dans la session en cours.
