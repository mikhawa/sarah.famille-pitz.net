# Répartition des tâches par modèle

Chaque niveau de complexité correspond à un subagent défini dans `.claude/agents/`. Claude Code délègue automatiquement à l'agent dont la `description` correspond à la tâche.

| Agent | Modèle | Pour |
|---|---|---|
| `sarah-architecte` | Opus | Architecture, sécurité, refactoring transverse, bugs complexes, doc d'architecture |
| `sarah-dev` | Sonnet | Contrôleurs métier, services, API JSON, tests, API externes, GitHub Actions |
| `sarah-crud.md` | Haiku | CRUD, migrations, Stimulus simple, typos, syntaxe |

## Appeler un agent

- Automatique : décrire la tâche, Claude choisit l'agent.
- Explicite : « utilise l'agent sarah-architecte pour… ».
- Garanti : taper `@` et choisir l'agent dans la liste.
  Pour vérifier quel modèle tourne réellement, ouvrir `/tasks` pendant l'exécution.

## Points d'attention

- Les agents chargent `CLAUDE.md`, mais pas la mémoire automatique : c'est pourquoi chacun lit lui-même `.claude/MEMORY.md`.
- `CLAUDE_CODE_SUBAGENT_MODEL` n'est qu'une valeur par défaut : le champ `model` des agents reste prioritaire, sauf si `CLAUDE_CODE_SUBAGENT_MODEL_FORCE=1` est défini.
 
