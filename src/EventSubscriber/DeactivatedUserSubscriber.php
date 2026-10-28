<?php

namespace App\EventSubscriber;

use App\Entity\User;
use Symfony\Bundle\SecurityBundle\Security;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpKernel\Event\RequestEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Déconnecte un membre dont le compte a été désactivé alors qu'il était connecté :
 * UserChecker ne bloque que la connexion, pas une session ou un « rester connecté » déjà ouverts
 */
class DeactivatedUserSubscriber implements EventSubscriberInterface
{
    public function __construct(
        private readonly Security $security,
        private readonly UrlGeneratorInterface $urlGenerator,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        // Juste après le pare-feu (priorité 8), qui recharge l'utilisateur depuis la base
        return [KernelEvents::REQUEST => ['onKernelRequest', 7]];
    }

    public function onKernelRequest(RequestEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $user = $this->security->getUser();
        if (!$user instanceof User || $user->isActive()) {
            return;
        }

        $event->setResponse(
            $this->security->logout(validateCsrfToken: false)
            ?? new RedirectResponse($this->urlGenerator->generate('app_login'))
        );
    }
}
