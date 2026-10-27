# 004 — Étape 2 : administration EasyAdmin, comptes, albums, tags

- **Modèle utilisé** : Opus
- **Justification** : gestion des comptes et des mots de passe (invitation, réinitialisation, garde-fous admin) → sécurité.

## Paquets ajoutés

- `easycorp/easyadmin-bundle` 5.8
- `symfonycasts/reset-password-bundle` 1.25

## Fichiers créés / modifiés

- Entités : `src/Entity/Album.php`, `src/Entity/Tag.php`, `src/Entity/ResetPasswordRequest.php` (généré, `ON DELETE CASCADE`),
  `src/Entity/User.php` (`setAdmin()`)
- Repositories : `AlbumRepository`, `TagRepository`, `ResetPasswordRequestRepository` (généré)
- Admin : `src/Controller/Admin/{Dashboard,User,Album,Tag}CrudController.php`, `templates/admin/dashboard.html.twig`
- Invitation : `src/Service/AccountInvitationMailer.php`, `templates/email/invitation.html.twig`
- Réinitialisation (généré par `make:reset-password`, traduit et adapté) : `src/Controller/ResetPasswordController.php`,
  `src/Form/{ChangePassword,ResetPasswordRequest}FormType.php`, `templates/reset_password/*`
- `templates/security/login.html.twig` : lien « Mot de passe oublié ? », message de succès
- Config : `security.yaml` (`/reset-password` public), `mailer.yaml` (expéditeur `MAILER_FROM`),
  `messenger.yaml` (e-mails synchrones), `reset_password.yaml` (liens valables 48 h), `.env` (`MAILER_FROM`, `DEFAULT_URI`)
- Migration `src/Migrations/Version20261027181432.php` : tables `album`, `tag`, `reset_password_request`
- Tests : `tests/Controller/Admin/AdminTest.php`

## Résumé

- `/admin` réservé à `ROLE_ADMIN` (access_control + `#[IsGranted]` sur le dashboard).
- Création d'un compte par l'admin → mot de passe aléatoire inutilisable + e-mail d'invitation ; le membre choisit
  son mot de passe via le lien (réutilise le jeton du reset-password-bundle). Action « Envoyer l'invitation »
  (POST uniquement) pour renvoyer le lien ; les anciens liens sont annulés.
- Garde-fous : l'admin ne peut ni se retirer ses droits, ni se désactiver, ni supprimer son compte (y compris en suppression groupée).
- « Mot de passe oublié » : ne révèle pas si un compte existe ; rien n'est envoyé aux comptes désactivés ; un compte
  désactivé ne peut pas utiliser un lien déjà reçu.
- Albums (titre, adresse, date de l'événement, description) et tags (nom, adresse) avec adresses uniques validées.
- E-mails envoyés de façon synchrone : pas de worker Messenger à ce stade.

Vérifié : 17 tests / 82 assertions OK, `lint:container` et `lint:twig` OK, schéma Doctrine synchronisé,
`/admin` → 302 vers `/login` via nginx, `/reset-password` → 200, envoi SMTP vers Mailpit OK.

Non fait : la couverture d'album (`cover_media_id`) attend l'entité `Media` (étape 3).
