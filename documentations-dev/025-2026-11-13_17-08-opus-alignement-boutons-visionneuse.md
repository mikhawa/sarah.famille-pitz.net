# Phase 7.5 : Alignement des boutons de la visionneuse et étiquette du formulaire

> Date : 13 novembre 2026

## Objectif
Corriger l'icône décalée vers le haut dans la barre de la visionneuse (mobile) et placer « Ton petit mot » au-dessus
du champ.

## Actions realisees
**Fichier :** `assets/styles/app.css`
- `.comment-form label` en bloc, `textarea` pleine largeur (fiche et visionneuse).
- `.pswp .pswp__button--media-page` / `.pswp .pswp__button--comments-toggle` : `photoswipe.css` (importé dans le
  contrôleur, donc chargé après `app.css`) imposait `display: block` à `.pswp__button` à spécificité égale.

## Fichiers crees
Aucun.

## Fichiers modifies
- `assets/styles/app.css`

## Verifications
| Point | Statut |
|-------|--------|
| CSS servi par le serveur de dev | OK |
| Essai sur mobile après déploiement | À faire (utilisateur) |

## Problemes rencontres
Les captures venaient de la prod (`main`), qui ne contient pas encore la branche `feature/06-messages-on-front`.
