<?php

namespace App\Security\Voter;

use App\Entity\User;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Authorization\Voter\Vote;
use Symfony\Component\Security\Core\Authorization\Voter\Voter;

/**
 * Gestion des comptes dans l'administration.
 *
 * - Super-administrateur : tous les comptes ; lui seul attribue les rôles (champ « Rôle », UserCrudController).
 * - Administrateur : les comptes membres (création, activation, droit d'envoi, invitation, suppression) et son propre
 *   compte (nom, e-mail), sans pouvoir changer de rôle.
 *
 * @extends Voter<string, User>
 */
class UserVoter extends Voter
{
    public const MANAGE = 'USER_MANAGE';

    protected function supports(string $attribute, mixed $subject): bool
    {
        return self::MANAGE === $attribute && $subject instanceof User;
    }

    protected function voteOnAttribute(string $attribute, mixed $subject, TokenInterface $token, ?Vote $vote = null): bool
    {
        $user = $token->getUser();
        if (!$user instanceof User || !$user->isActive() || !$user->isAdmin()) {
            return false;
        }
        if ($user->isSuperAdmin()) {
            return true;
        }

        $isSelf = null !== $subject->getId() && $subject->getId() === $user->getId();

        // Les rôles se changent dans UserCrudController (super-administrateur seulement)
        return $isSelf || !$subject->isAdmin();
    }
}
