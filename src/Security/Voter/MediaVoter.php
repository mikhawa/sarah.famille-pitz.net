<?php

namespace App\Security\Voter;

use App\Entity\Media;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Media>
 */
class MediaVoter extends Voter
{
    public const VIEW = 'MEDIA_VIEW';
    public const COMMENT = 'MEDIA_COMMENT';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return \in_array($attribute, [self::VIEW, self::COMMENT], true) && $subject instanceof Media;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();

        // Tous les membres actifs voient et commentent tous les médias : la frontière est entre la famille et le reste du monde
        return $user instanceof User && $user->isActive();
    }
}
