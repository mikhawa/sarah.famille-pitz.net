<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\MediaType;
use App\Repository\MediaRepository;
use App\Service\LightboxItems;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class LightboxController extends AbstractController
{
    /**
     * Liste de toutes les photos du fil de l'accueil (même filtre ?type= que la page), chargée à la demande par la
     * visionneuse : le fil peut en contenir des milliers, la page ne les embarque pas. Site privé : pare-feu en place.
     */
    #[Route('/visionneuse/fil', name: 'feed_lightbox', methods: ['GET'])]
    public function feed(
        #[CurrentUser] User $user,
        MediaRepository $mediaRepository,
        LightboxItems $lightboxItems,
        #[MapQueryParameter] ?string $type = null,
    ): JsonResponse {
        $mediaType = null !== $type ? MediaType::tryFrom($type) : null;
        $response = new JsonResponse($lightboxItems->items($mediaRepository->findPhotosForFeed($user, $mediaType)));
        // Dépend du compte (droits de visibilité) : jamais gardée dans un cache partagé
        $response->headers->set('Cache-Control', 'private, no-store');

        return $response;
    }
}
