# Schéma de la base de données

MariaDB 11.4, `utf8mb4`. Tables et colonnes en `snake_case`.

## `user`

| Colonne        | Type          | Notes                                  |
|----------------|---------------|----------------------------------------|
| id             | int PK        |                                        |
| email          | varchar(180)  | unique, identifiant de connexion       |
| password       | varchar(255)  | hash                                   |
| roles          | json          | `ROLE_USER`, `ROLE_ADMIN`              |
| display_name   | varchar(100)  | nom affiché (« Mamy », « Papa »…)      |
| is_active      | bool          | désactiver un compte sans le supprimer |
| created_at     | datetime_immutable |                                   |

## `album`

| Colonne        | Type          | Notes                                  |
|----------------|---------------|----------------------------------------|
| id             | int PK        |                                        |
| title          | varchar(150)  |                                        |
| slug           | varchar(160)  | unique                                 |
| description    | text null     |                                        |
| event_date     | date null     | date de l'événement                    |
| cover_media_id | FK media null | photo de couverture, `ON DELETE SET NULL` |
| created_at     | datetime_immutable |                                   |

## `media`

| Colonne           | Type          | Notes                                         |
|-------------------|---------------|-----------------------------------------------|
| id                | int PK        |                                               |
| type              | varchar(10)   | enum PHP `App\Enum\MediaType` : `photo`, `audio`, `video` |
| title             | varchar(150) null |                                           |
| description       | text null     |                                               |
| storage_path      | varchar(255)  | unique, relatif à `var/storage/media/originals/` |
| original_filename | varchar(255)  |                                               |
| mime_type         | varchar(100)  |                                               |
| size              | int           | octets (fichier stocké, 256 Mo max)           |
| width / height    | int null      | photos et vidéos                              |
| duration          | int null      | secondes, audio et vidéo                      |
| taken_at          | datetime_immutable null | date EXIF ou saisie ; sert au fil chronologique |
| album_id          | FK album null | `ON DELETE SET NULL`                          |
| uploaded_by_id    | FK user null  | `ON DELETE SET NULL` : le média survit au compte |
| created_at        | datetime_immutable |                                          |

Index : `(taken_at)`, `(created_at)`, `(album_id)`.

## `tag`

| Colonne | Type         | Notes  |
|---------|--------------|--------|
| id      | int PK       |        |
| name    | varchar(60)  | unique |
| slug    | varchar(70)  | unique |

## `media_tag` (ManyToMany)

`media_id` FK, `tag_id` FK, clé primaire composite.

## `reset_password_request`

Jetons du `symfonycasts/reset-password-bundle` (invitation et mot de passe oublié). `user_id` FK `ON DELETE CASCADE`.

## `comment`

| Colonne    | Type          | Notes                               |
|------------|---------------|-------------------------------------|
| id         | int PK        |                                     |
| media_id   | FK media      | `ON DELETE CASCADE`                 |
| author_id  | FK user       |                                     |
| content    | text          |                                     |
| is_hidden  | bool          | masqué par l'admin (modération)     |
| created_at | datetime_immutable |                                |

## Relations

```
user 1──n media (uploaded_by)        album 1──n media
user 1──n comment                    media 1──n comment
media n──n tag                       album n──1 media (cover)
```
