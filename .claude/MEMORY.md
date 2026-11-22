# Mémoire du projet — état d'avancement

Dernière mise à jour : 2026-11-22 — branche `main` (PR #12 `feature/09-mail-to-users-product` fusionnée + commits e-mails sur `main`) ;
`bin/deploy.sh` / `bin/backup.sh` supprimés (commit `a581b14`, fiche 038) ; VPS mis à jour par git sur `a581b14` (2026-11-22)

## Où on en est

Plan en 6 étapes (détail : `docs/architecture/overview.md`, section « Plan de réalisation ») :

1. ✅ Authentification — `User`, login (CSRF, throttling, remember me), site entièrement privé, `app:create-admin`
2. ✅ Administration EasyAdmin 5.8 — comptes (invitation par e-mail via reset-password-bundle), albums, tags
3. ✅ Médias — envoi groupé privé, nettoyage EXIF/GPS (photos GD, vidéos ffmpeg), miniatures, `MediaFileController` + `MediaVoter`
4. ✅ Front — fil chronologique par mois (filtre par type), albums + thèmes, pages tag, fiche média (lecteurs,
   précédent/suivant, clavier/glissement), pagination 72/page ; déconnexion des comptes désactivés
5. ✅ Commentaires — entité `Comment`, ajout par les membres, `CommentVoter`, modération (`is_hidden`) dans l'admin — « petits mots » sous chaque média, suppression par l'auteur, interrupteur « Masqué » dans `/admin/comment`
6. ✅ Prod (préparée, **pas encore déployée**) — `X-Accel-Redirect` (`MEDIA_X_ACCEL_PREFIX`, actif aussi en Docker dev),
   configs `docs/devops/prod/`, procédure `docs/devops/cyberpanel.md`, logs prod en fichiers tournants
   **Mise en ligne manuelle par git, branche `main` seule** (décision du 2026-11-22, remplace le déploiement GitHub) :
   sur le VPS, en tant qu'utilisateur du site, `git pull` de `main` puis composer / assets / migrations / cache
   (commandes dans `cyberpanel.md`). `bin/deploy.sh` et `bin/backup.sh` supprimés ; le workflow GitHub ne fait plus
   que les tests (préprod) : plus de clé SSH GitHub → VPS ni de secrets `DEPLOY_*`.
   **Pas de sauvegarde** pour le moment (choix de l'utilisateur, même pas celles de CyberPanel).

Le VPS est sous **AlmaLinux 9** + **CyberPanel** (OpenLiteSpeed, lsphp83, un utilisateur Linux par site ;
`vps-preprod.md` = variante nginx, adaptée aussi à AlmaLinux 9 : dnf, Remi, RPM Fusion, SELinux) : procédure
`docs/devops/cyberpanel.md` ; `MEDIA_X_ACCEL_PREFIX` vide (pas de X-Accel), PHP CLI `/usr/local/lsws/lsphp83/bin/php`
(composer lancé avec lui), racine web `<projet>/public` (vHost Conf), `public/.htaccess`.

### Miniatures en prod (2026-11-06, branche `fix/02-miniatures-prod`)

- `imagescale(…, IMG_BICUBIC)` renvoie false avec la libgd de lsphp83 → miniatures réduites jamais créées (404).
  Remplacé par `imagecopyresampled` ; un échec de miniature refuse désormais l'envoi.
- Réparation des médias existants : `php bin/console app:media:regenerate-thumbs` (`--all` pour tout refaire).
- ✅ ffmpeg installé sur le VPS (2026-11-22, `/usr/bin/ffmpeg`, via EPEL + CRB + RPM Fusion) — envoi de vidéo à confirmer.

### Installation du VPS (en cours, suivie avec l'utilisateur, procédure `docs/devops/cyberpanel.md`)

- ✅ `php.ini` de lsphp83 (`/usr/local/lsws/lsphp83/etc/php.ini`) : max_execution_time / max_input_time 300,
  max_file_uploads 50, date.timezone Europe/Brussels, opcache.validate_timestamps On ; le reste était déjà bon
  (memory_limit 1024M, post_max_size 3G, disable_functions vide). En CLI, PHP force max_execution_time 0 et
  max_input_time -1 (même via get_cfg_var) : vérifier ces deux-là par `grep` dans le fichier.
- ✅ Dépôt GitHub **public** : pas de deploy key pour cloner.
- ❌ Clé de déploiement GitHub → VPS abandonnée (2026-11-22) ; plus de ligne `github-deploy-sarah` dans
  `/root/.ssh/authorized_keys` (vérifié).
- ✅ Première mise à jour par git (2026-11-22) : `.git/FETCH_HEAD` appartenait à root (git lancé en root un jour) →
  « Permission denied » ; réparé en root par `find "$APP" ! -user "$SITEUSER" -exec chown "$SITEUSER:$SITEGROUP" {} +`,
  puis mise à jour complète (section 6 de cyberpanel.md, migration `Version20261121165628` comprise).
- ✅ Site en ligne, admin comprise (2026-11-02). **Chemin réel** : `/home/famille-pitz.net/sarah.famille-pitz.net/sarah.famille-pitz.net`
  (sous-domaine de famille-pitz.net → utilisateur Linux du domaine parent), pas `/home/sarah.famille-pitz.net/app`
  (`cyberpanel.md` corrigé). `/admin` était en 500 (« mkdir … var/cache/prod/asset_mapper :
  Permission denied ») : `var/` créé par root → `chown -R` vers le propriétaire du projet + `cache:clear` en tant que lui.
  Toujours lancer git / console / composer en tant qu'utilisateur du site (`su - <user> -s /bin/bash`, vérifier `whoami`), jamais en root.
- ⏳ Déploiement auto (2026-11-22) : utilisateur du site **`famil6315`**, SSH sur le port **7899** (pas 22 → secret
  `DEPLOY_PORT=7899`, `ssh-keyscan -p 7899`), domaine non proxifié (`DEPLOY_HOST=sarah.famille-pitz.net`). Clé créée,
  ligne `command="…"` ajoutée, test `ssh -i ~/deploy_sarah -p 7899 … famil6315@localhost` ✅ jusqu'au bout (après
  un 2ᵉ `chown` : `git pull` + console lancés en root à 11:44–12:02). « PTY allocation request failed » = normal (no-pty).
  Reste : 5 secrets dans l'environnement GitHub `production`, `rm ~/deploy_sarah*`, commit + push → premier déploiement.
- Reste : vérifications dans le navigateur (section 8 de cyberpanel.md) — envoi de vidéo, « mot de passe oublié » et
  réception des e-mails, pastille 💬, « Supprimer des médias », « Prévenir la famille » ; super-admin et albums publics
  (voir « Rôles et visibilité ») si pas encore fait.

À faire ensuite : finir l'installation du VPS (ci-dessus), confirmer que les tests GitHub passent au vert
(2 échecs SMTP corrigés par MAILER_DSN=null://null, non confirmé), design adapté à Sarah (6 ans).

### Rôles et visibilité (PR #10, fusionnée dans `main`)

Voir « Décisions » ci-dessous. En prod, après déploiement : `app:create-admin <email> --super` (utilisateur du site),
puis rendre publics les albums à partager (la migration a tout passé en privé).

### Agents du projet (`.claude/agents/`, vérifiés le 2026-11-21)

`sarah-architecte` (Opus), `sarah-dev` (Sonnet), `sarah-crud` (Haiku) : chargés et fonctionnels (essai réel de
`sarah-crud` → bien sur Haiku, outils OK). Un sous-agent ne connaît pas son propre nom : c'est normal.

### Visionneuse sur toute la liste (2026-11-21, PR #11, fusionnée dans `main`)

- Albums et tags : la visionneuse (PhotoSwipe, `lightbox_controller.js`) parcourt **toutes les photos** (pas les vidéos/audio),
  pas seulement la page. `MediaRepository::findPhotosByAlbum/Tag()` partent du **même QueryBuilder** que la pagination
  (`createAlbumQueryBuilder` / `createTagQueryBuilder`) → mêmes droits et même ordre : ne jamais dupliquer ces critères.
  Liste JSON dans `data-lightbox-items-value` (gabarit `media/_lightbox_grid.html.twig`, fonctions Twig `lightbox_items()`
  et `lightbox_image()`), ouverture à l'index global par `loadAndOpen` ; ℹ️ et 💬 lisent `href`/`commentsUrl` dans les
  données de la diapo (filtre `domItemData` pour le mode sans liste : fiche média).
- Accueil : **chargement à la demande** de la liste (`GET /visionneuse/fil?type=`, route `feed_lightbox`, `LightboxController`,
  service `LightboxItems`) au premier clic, car le fil peut contenir des milliers de photos — pas de JSON dans la page ;
  échec ou session expirée → on suit le lien vers la fiche.
- Vérifié dans le navigateur par l'utilisateur (2026-11-21) : albums, tags, accueil ; le zoom part bien de la vignette
  (filtre `itemData` → `element`), fondu seulement pour une photo hors de la page.

Suite de tests : 87 tests verts (`docker compose exec php php bin/phpunit`).
Traçabilité : `.claude-tasks/` 001 à 039 (opus 001–026 et 031–037, haiku 027–028, sonnet 029–030 et 038–039 ; numérotation globale, prochain **040**) ; journal `documentations-dev/001` à `039` (prochain **040**).

### Suppression de médias depuis un album (2026-11-21, PR #12, fusionnée dans `main`)

Super-admin seul (`AlbumVoter::DELETE_MEDIA`) : admin → Albums → « Supprimer des médias » → `/admin/album/{id}/medias`,
médias à cocher ; base + fichiers (`MediaFileCleanupListener`) + commentaires (`ON DELETE CASCADE`). Pas encore essayé dans le navigateur.
Vignettes (même branche) : pastille rouge « 💬 N » en haut à droite, fonction Twig `comment_counts()` (une requête par
page, masqués comptés pour les admins seulement).
E-mails (même branche) : « Prévenir la famille » (admin → Albums, `/admin/album/{id}/prevenir`, `AlbumNotifier`, album
public seulement, tous ceux qui voient ≥ 1 média sauf l'expéditeur, `Album::notifiedAt`, migration `Version20261121165628`
à passer en prod) ; nouveau petit mot → e-mail à l'expéditeur du média, aux participants (déjà écrit sur ce média) et aux super-admins,
actifs et voyant le média, sauf l'auteur, un seul chacun (`CommentNotifier`, choix « conversation » : pas de bouton Répondre) ;
nouveaux fichiers → admins + super-admins actifs qui les voient, sauf l'expéditeur (`UploadNotifier`) : **un e-mail par
envoi**, lots reliés par `upload_id` / `upload_last` (media_upload.js), médias gardés en session jusqu'au dernier lot.
Choix de l'utilisateur : bouton manuel (pas d'envoi automatique), destinataires = tous ceux qui voient l'album.
Erreurs 500 en prod (2026-11-21, envoi de photos et mot de passe oublié) : `RfcComplianceException`
« admin%40michaeljpitz.com » → `%40` dans une adresse (MAILER_FROM) de `.env.local` du VPS. ✅ Corrigé par l'utilisateur
(2026-11-21 : `@`, `composer dump-env prod`, `cache:clear`), envoi de photos confirmé. Les notificateurs attrapent aussi
les erreurs MIME (fiche 036, à déployer). Reste à confirmer : « mot de passe oublié » et réception réelle des e-mails.

## Décisions prises avec l'utilisateur

- **Mise en ligne (2026-11-22, révisée le même jour)** : déploiement **automatique à chaque push sur `main`**, après
  les tests (GitHub Actions → SSH utilisateur du site). Pas de script dans le dépôt : la commande de mise à jour
  (git pull + composer + assets + migrations + cache) est écrite en dur dans `command="…"` de `authorized_keys`
  sur le VPS (fiche 039, `cyberpanel.md` 6.4 ; secrets `DEPLOY_*` dans l'environnement GitHub `production`). Procédure manuelle gardée en secours. **Pas de sauvegarde** pour le moment
  (choix de l'utilisateur) : ne pas en reproposer sans demande.

- **Rôles et visibilité (2026-11-13, fusionné par la PR #10)** : `ROLE_USER` < `ROLE_ADMIN`
  < `ROLE_SUPER_ADMIN` ; albums/médias Public / Privé / Super-admin seul + verrou du super-admin ; **tout l'existant
  privé** après migration ; envoi des membres autorisés (`canUpload`) dans les albums publics, visible tout de suite ;
  admins gèrent les membres, super-admin gère tout. Détail : `docs/architecture/overview.md` (« Rôles »).
  En prod : lancer `app:create-admin <email> --super` (utilisateur du site) puis rendre les albums publics.

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
- Tests : `MAILER_DSN=null://null` forcé dans `phpunit.dist.xml`, sinon ils envoient à Mailpit (absent sur GitHub → échecs SMTP)
- CSRF sans état (login, formulaires) : pour tester avec curl, cookie `csrf-token_<jeton>=csrf-token` + champ `<jeton>` (≥ 24 caractères) + en-tête `Origin`
- Thème EasyAdmin : `assets/styles/admin.css` hors cascade layers → redéfinir les variables (`--ea-primary`…) suffit ; mode sombre désactivé
- Plus de 50 fichiers par requête → avertissement PHP avant Symfony → « session_id(): … headers already sent » ; en prod,
  fichiers en trop ignorés sans message. Envoi par lots côté navigateur (`assets/admin/media_upload.js`)
- L'admin n'a pas Stimulus : point d'entrée AssetMapper `admin` (`assets/admin.js`) qui importe directement
  `csrf_protection_controller.js`. Sans lui, les formulaires à jeton sans état (`token_id: submit`, ex. envoi de médias)
  échouent en 422 dès que la session a déjà utilisé le double-submit (« double-submit info … is now missing »)
- Twig : le résultat d'`include()` mis dans une variable (`set`) est ré-échappé à l'affichage → l'appeler directement dans la balise
- Dev : ne jamais laisser `public/assets/` (issu d'`asset-map:compile`) : AssetMapper sert alors ces fichiers figés
  et les modifications de `assets/` (JS, CSS) n'apparaissent plus. `rm -rf public/assets` + `cache:clear`
- VPS : un `git`/`composer`/`console` lancé en root rend des fichiers (`.git/`, `var/`) illisibles pour l'utilisateur
  du site → `find "$APP" ! -user "$SITEUSER"` pour les repérer, `chown` pour réparer
- Fins de ligne : l'IDE Windows convertit en CRLF, le dépôt est en LF
- `FrontTest::testFeedIsPaginated` génère `PER_PAGE + 1` médias horodatés : garder des heures valides
  (`%02d:%02d` avec `intdiv`/modulo) — `10:%02d` cassait dès PER_PAGE > 59
- Claude ne crée ni branche ni commit (règle de `CLAUDE.md`) : proposer un message de commit, l'utilisateur commite
