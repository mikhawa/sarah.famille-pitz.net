# Mémoire du projet — état d'avancement

Dernière mise à jour : 2026-10-27 — branche `feature/01-admin`

## Où on en est

Plan en 6 étapes (détail : `docs/architecture/overview.md`, section « Plan de réalisation ») :

1. ✅ Authentification — `User`, login (CSRF, throttling, remember me), site entièrement privé, `app:create-admin`
2. ✅ Administration EasyAdmin 5.8 — comptes (invitation par e-mail via reset-password-bundle), albums, tags
3. ✅ Médias — envoi groupé privé, nettoyage EXIF/GPS (photos GD, vidéos ffmpeg), miniatures, `MediaFileController` + `MediaVoter`
4. ⏭️ **Front (prochaine étape)** — fil chronologique (accueil), pages album, pages tag, fiche média avec lecteurs
   `<img>`/`<audio>`/`<video>`, pagination ; remplacer l'accueil provisoire `HomeController`
5. Commentaires — entité `Comment`, ajout par les membres, `CommentVoter`, modération (`is_hidden`) dans l'admin
6. Prod — `X-Accel-Redirect` pour les fichiers, ffmpeg sur le VPS, sauvegardes BDD + `var/storage/media/`, `.env.local`/secrets

Suite de tests : 32 tests verts (`docker compose exec php php bin/phpunit`).
Traçabilité : `.claude-tasks/opus/001` à `005` ; prochain numéro **006**.

## Décisions prises avec l'utilisateur

- Accès : comptes famille individuels créés par l'admin, pas d'inscription libre
- Admin : EasyAdmin ; stockage : fichiers sur le VPS hors `public/` ; organisation : albums, tags, fil chronologique, commentaires
- Le choix de modèle (Haiku/Sonnet/Opus) et la fiche `.claude-tasks/` sont obligatoires (voir `CLAUDE.md`)

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
- Fins de ligne : l'IDE Windows convertit en CRLF, le dépôt est en LF
