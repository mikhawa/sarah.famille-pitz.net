<?php

namespace App\Security\Voter;

use App\Entity\Comment;
use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * @extends Voter<string, Comment>
 */
class CommentVoter extends Voter
{
    // Voir le commentaire (modération dans l'administration) : il suit la visibilité de son média
    public const VIEW = 'COMMENT_VIEW';
    public const DELETE = 'COMMENT_DELETE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        // Sujet null : page « Créer » de l'administration (EasyAdmin vérifie setEntityPermission() sans entité)
        if (self::VIEW === $attribute && null === $subject) {
            return true;
        }

        return \in_array($attribute, [self::VIEW, self::DELETE], true) && $subject instanceof Comment;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (null === $subject) {
            return $user instanceof User && $user->isActive() && $user->isAdmin();
        }
        if (!$user instanceof User || !$subject->getMedia()->isVisibleTo($user)) {
            return false;
        }

        // L'auteur retire son propre commentaire ; l'administrateur modère tous les commentaires qu'il voit
        return match ($attribute) {
            self::VIEW => true,
            self::DELETE => $user->isAdmin() || $subject->getAuthor()?->getId() === $user->getId(),
        };
    }
}
