<?php

namespace App\Controller;

use App\Entity\User;
use App\Enum\MediaType;
use App\Repository\MediaRepository;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class HomeController extends AbstractController
{
    // Fil chronologique : tous les médias visibles par ce compte, du plus récent au plus ancien, filtrables par type
    #[Route('/', name: 'app_home', methods: ['GET'])]
    public function index(
        #[CurrentUser] User $user,
        MediaRepository $mediaRepository,
        #[MapQueryParameter] ?string $type = null,
        #[MapQueryParameter(options: ['min_range' => 1])] int $page = 1,
    ): Response {
        $mediaType = null !== $type ? MediaType::tryFrom($type) : null;
        $medias = $mediaRepository->paginateFeed($user, $page, $mediaType);
        if ($medias->isOutOfRange()) {
            throw $this->createNotFoundException();
        }

        return $this->render('home/index.html.twig', [
            'medias' => $medias,
            'current_type' => $mediaType,
            'types' => MediaType::cases(),
        ]);
    }
}
