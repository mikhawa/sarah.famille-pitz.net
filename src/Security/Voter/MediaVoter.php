<?php

namespace App\Security\Voter;

use App\Entity\Media;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Droits sur un média, selon sa visibilité (voir Media::isVisibleTo() et l'énumération Visibility).
 *
 * @extends Voter<string, Media>
 */
class MediaVoter extends Voter
{
    // Voir le média et ses fichiers (fiche, visionneuse, original, miniatures)
    public const VIEW = 'MEDIA_VIEW';
    // Écrire un petit mot : tout compte qui voit le média
    public const COMMENT = 'MEDIA_COMMENT';
    // Supprimer : les administrateurs qui le voient, ou le membre qui l'a envoyé
    public const DELETE = 'MEDIA_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Sujet null : page « Créer » de l'administration (EasyAdmin vérifie setEntityPermission() sans entité)
        if (self::VIEW === $attribute && null === $subject) {
            return true;
        }

        return \in_array($attribute, [self::VIEW, self::COMMENT, self::DELETE], true) && $subject instanceof Media;
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
            self::VIEW, self::COMMENT => true,
            self::DELETE => $user->isAdmin() || $subject->getUploadedBy()?->getId() === $user->getId(),
        };
    }
}
