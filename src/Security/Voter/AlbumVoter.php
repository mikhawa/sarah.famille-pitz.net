<?php

namespace App\Security\Voter;

use App\Entity\Album;
use App\Entity\User;
use App\Enum\Visibility;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur un album, selon sa visibilité (voir Album::isVisibleTo() et l'énumération Visibility).
 *
 * @extends Voter<string, Album>
 */
class AlbumVoter extends Voter
{
    public const VIEW = 'ALBUM_VIEW';
    // Envoyer des médias dans l'album : administrateurs (albums qu'ils voient),
    // membres autorisés par un administrateur (albums publics seulement)
    public const UPLOAD = 'ALBUM_UPLOAD';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Sujet null : page « Créer » de l'administration (EasyAdmin vérifie setEntityPermission() sans entité)
        if (self::VIEW === $attribute && null === $subject) {
            return true;
        }

        return \in_array($attribute, [self::VIEW, self::UPLOAD], true) && $subject instanceof Album;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (null === $subject) {
            return $user instanceof User && $user->isActive() && $user->isAdmin();
        }
        if (!$user instanceof User || !$subject->isVisibleTo($user)) {
            return false;
        }

        return match ($attribute) {
            self::VIEW => true,
            self::UPLOAD => $user->isAdmin() || ($user->canUpload() && Visibility::Public === $subject->getVisibility()),
        };
    }
}
