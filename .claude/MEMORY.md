# Mémoire du projet — état d'avancement

Dernière mise à jour : 2026-10-28 — branche `feature/02-admin-suite`

## Où on en est

Plan en 6 étapes (détail : `docs/architecture/overview.md`, section « Plan de réalisation ») :

1. ✅ Authentification — `User`, login (CSRF, throttling, remember me), site entièrement privé, `app:create-admin`
2. ✅ Administration EasyAdmin 5.8 — comptes (invitation par e-mail via reset-password-bundle), albums, tags
3. ✅ Médias — envoi groupé privé, nettoyage EXIF/GPS (photos GD, vidéos ffmpeg), miniatures, `MediaFileController` + `MediaVoter`
4. ✅ Front — fil chronologique par mois (filtre par type), albums + thèmes, pages tag, fiche média (lecteurs,
   précédent/suivant, clavier/glissement), pagination 24/page ; déconnexion des comptes désactivés
5. ✅ Commentaires — entité `Comment`, ajout par les membres, `CommentVoter`, modération (`is_hidden`) dans l'admin — « petits mots » sous chaque média, suppression par l'auteur, interrupteur « Masqué » dans `/admin/comment`
6. ✅ Prod (préparée, **pas encore déployée**) — `X-Accel-Redirect` (`MEDIA_X_ACCEL_PREFIX`, actif aussi en Docker dev),
   `bin/backup.sh`, configs `docs/devops/prod/`, procédure `docs/devops/vps-preprod.md`, logs prod en fichiers tournants
   **Déploiement uniquement par GitHub, jamais automatique** (décisions de l'utilisateur) : `.github/workflows/ci-deploy.yml`
   = tests (préprod) à chaque push/PR ; déploiement seulement par « Run workflow » (`workflow_dispatch`) sur `main`,
   après les tests → SSH → `bin/deploy.sh <sha>` (clé limitée par `command=` dans authorized_keys)

À faire ensuite : installation réelle du VPS + secrets GitHub (section 6 de vps-preprod.md), premier passage du
workflow sur GitHub (jamais exécuté), design adapté à Sarah (6 ans).

Suite de tests : 55 tests verts (`docker compose exec php php bin/phpunit`).
Traçabilité : `.claude-tasks/opus/001` à `011` (prochain **012**) ; journal `documentations-dev/001` à `011` (prochain **012**).

## Décisions prises avec l'utilisateur

- Accès : comptes famille individuels créés par l'admin, pas d'inscription libre
- Admin : EasyAdmin ; stockage : fichiers sur le VPS hors `public/` ; organisation : albums, tags, fil chronologique, commentaires
- Le choix de modèle (Haiku/Sonnet/Opus) et la fiche `.claude-tasks/` sont obligatoires (voir `CLAUDE.md`) En résumé :

| Complexité    | Modèle     | Exemples                                     |
|---------------|------------|----------------------------------------------|
| Complexe      | **Opus**   | Architecture, sécurité, refactoring majeur   |
| Intermédiaire | **Sonnet** | Controllers, Services, tests, GitHub Actions |
| Simple        | **Haiku**  | CRUD, migrations, typos, questions syntaxe   |

**Claude doit choisir lui-même le modèle le plus adapté** à chaque tâche, sans attendre que l'utilisateur le précise. Si la tâche en cours relève d'un modèle différent du modèle actif, Claude le signale et utilise l'`Agent` tool avec le paramètre `model` approprié (`opus`, `sonnet` ou `haiku`).


## Reprendre sur un nouveau poste

```bash
git clone git@github.com:mikhawa/sarah.famille-pitz.net.git && cd sarah.famille-pitz.net
git checkout feature/01-admin
docker compose up -d --build
docker compose exec php composer install
docker compose exec php php bin/console doctrine:migrations:migrate -n
# base de test (voir docs/devops/docker-setup.md, section « Base de test »)
docker compose exec php php bin/console app:create-admin <email> "<Nom>"
```

Les médias envoyés (`var/storage/media/`) et la base ne sont pas dans git : ils ne suivent pas d'un poste à l'autre.

## Pièges rencontrés (à ne pas refaire)

- EasyAdmin 5 : dans les gabarits, `ea` est une **fonction** (`ea().crud.currentPage`), pas une variable
- EasyAdmin 5 affiche les messages flash en `|raw` → toujours `htmlspecialchars()` sur les données utilisateur dans `addFlash`
- Les boutons des formulaires EasyAdmin sont hors de `<form>` : dans les tests, poster directement (voir `tests/Controller/Admin/AdminTest.php`)
- Une route inexistante répond 404 **avant** le pare-feu : tester `/admin` seulement sur des routes réelles
- Ne pas nommer une action de contrôleur `file()` (conflit avec `AbstractController::file()`)
- E-mails envoyés en synchrone (routage Messenger `SendEmailMessage` commenté) : pas de worker pour l'instant
- Symfony Flex : `extra.symfony.docker: false` pour ne pas générer de `compose.yaml` concurrent
- HEIC refusé (GD ne le lit pas) ; vidéos non transcodées (HEVC pas lisible partout)
- Un compte désactivé gardait sa session : `UserChecker` ne bloque que la connexion → `DeactivatedUserSubscriber`
- `#[IsCsrfTokenValid]` : un jeton invalide renvoie vers `/login` (exception d'authentification) ; vérifier le jeton dans l'action pour obtenir un 403
- `TextareaType` vide → `null` : mettre `empty_data: ''` sur un champ `string` non nullable
- Variables d'environnement du conteneur PHP (`docker-compose.yml`) : prioritaires sur `.env.test` → les neutraliser dans `phpunit.dist.xml` avec `<env … force="true"/>` (`<server>` ne suffit pas : Symfony lit `$_ENV` d'abord)
- CSRF sans état (login, formulaires) : pour tester avec curl, cookie `csrf-token_<jeton>=csrf-token` + champ `<jeton>` (≥ 24 caractères) + en-tête `Origin`
- Fins de ligne : l'IDE Windows convertit en CRLF, le dépôt est en LF
