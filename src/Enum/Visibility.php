<?php

namespace App\Enum;

use App\Entity\User;

/**
 * Qui voit un album ou un média.
 *
 * - Public : tous les membres actifs (ROLE_USER et au-dessus)
 * - Privé : administrateurs et super-administrateurs
 * - Super-admin seul : super-administrateurs uniquement
 *
 * Les administrateurs choisissent entre Public et Privé ; seul le super-administrateur peut choisir
 * « Super-admin seul » et verrouiller le réglage (voir Album / Media::isVisibilityLocked()).
 */
enum Visibility: string
{
    case Public = 'public';
    case Private = 'private';
    case SuperAdmin = 'super_admin';

    public function label(): string
    {
        return match ($this) {
            self::Public => 'Public (toute la famille)',
            self::Private => 'Privé (administrateurs)',
            self::SuperAdmin => 'Super-admin seul',
        };
    }

    public function requiredLevel(): int
    {
        return match ($this) {
            self::Public => User::LEVEL_MEMBER,
            self::Private => User::LEVEL_ADMIN,
            self::SuperAdmin => User::LEVEL_SUPER_ADMIN,
        };
    }

    /**
     * Niveaux que ce compte peut voir (aucun pour un compte désactivé)
     *
     * @return list<self>
     */
    public static function allowedFor(?User $user): array
    {
        if (null === $user || !$user->isActive()) {
            return [];
        }

        return array_values(array_filter(self::cases(), static fn (self $v) => $v->requiredLevel() <= $user->getLevel()));
    }

    /**
     * Niveaux qu'un compte peut attribuer : jamais au-dessus du sien (un admin ne se cache rien à lui-même)
     *
     * @return list<self>
     */
    public static function assignableBy(User $user): array
    {
        return self::allowedFor($user);
    }

    /**
     * Valeurs pour un paramètre de requête DQL « IN (:visibilities) »
     *
     * @param list<self> $visibilities
     *
     * @return list<string>
     */
    public static function values(array $visibilities): array
    {
        return array_map(static fn (self $v) => $v->value, $visibilities);
    }
}
