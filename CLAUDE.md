# Projet : Site de ma fille Sarah Pitz

**Objectif** : Plateforme de gestion des de photos, de liens et de vidéos.

**Public cible** :Ma fille Sarah Pitz, 6 ans, et sa famille proche.

**Langue du projet** : Ce projet se fait entièrement en français : code (commentaires, messages de validation, noms de commits), documentation, et échanges.

**Stack** : Symfony 7.4 LTS --webapp | PHP 8.3 | MariaDB 11.4 | Docker | ImportMap 

**Environnements** : dev (Docker local) → préprod (GitHub CI) → prod (VPS ).

**Repo** : https://github.com/mikhawa/sarah.famille-pitz.net

## Mémoire persistante
`.claude/MEMORY.md` est versionné dans git et chargé automatiquement via symlink.
Sur un nouveau poste après `git clone`, exécuter depuis la racine du projet :

```bash
SLUG=$(pwd | sed 's#[/.]#-#g')
mkdir -p ~/.claude/projects/$SLUG/memory
ln -sfn "$(pwd)/.claude/MEMORY.md" ~/.claude/projects/$SLUG/memory/MEMORY.md
```

État d'avancement et reprise du travail : voir `.claude/MEMORY.md`.


## Règles d'utilisation des modèles Claude
Voir `.claude/models.md` pour les critères détaillés.

### Sélection obligatoire avant chaque tâche
Évaluer la complexité et choisir le modèle adapté :
- **Haiku** → CRUD simple, migrations, typos, questions syntaxe
- **Sonnet** → Controllers métier, services, tests, CI/CD
- **Opus** → Architecture, sécurité, refactoring majeur, bugs complexes

Pour déléguer à un modèle supérieur/inférieur, utiliser l'outil `Agent` avec le paramètre `model: "opus"` ou `model: "haiku"`.

### Traçabilité obligatoire (.claude-tasks/)
Pour **toute tâche modifiant du code**, créer un fichier dans `.claude-tasks/` :
- **Chemin** : `.claude-tasks/haiku/`, `.claude-tasks/sonnet/` ou `.claude-tasks/opus/` selon le modèle
- **Format** : `NNN-YYYY-MM-DD_description-courte.md`
- **Numérotation** : auto-incrémentée sur 3 chiffres, globale (tous sous-dossiers confondus), indépendante de `documentations-dev/`
- **Contenu minimal** : modèle utilisé, justification, fichiers modifiés, résumé
- Vérifier le dernier numéro existant dans tous les sous-dossiers de `.claude-tasks/` avant de créer un fichier

## Contexte rapide
- Architecture : MVC + couche Service + Repository pattern
- Auth : Symfony Security (voters custom)


## Fichiers clés à connaître
- Architecture globale : `docs/architecture/overview.md`
- Docker dev : `docs/devops/docker-setup.md`
- Schéma BDD : `docs/architecture/database-schema.md`
- Déploiement : `docs/devops/vps-preprod.md`

## Conventions de code
- Entités : `src/Entity/` — annotations Doctrine en attributs PHP 8
- Services métier : `src/Service/`
- Repositories custom : `src/Repository/`
- Nommage : PascalCase classes, snake_case BDD, camelCase JS
