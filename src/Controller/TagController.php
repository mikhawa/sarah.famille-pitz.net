<?php

namespace App\Controller;

use App\Entity\Tag;
use App\Entity\User;
use App\Repository\MediaRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;

class TagController extends AbstractController
{
    #[Route('/tags/{slug}', name: 'tag_show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] Tag $tag,
        #[CurrentUser] User $user,
        MediaRepository $mediaRepository,
        #[MapQueryParameter(options: ['min_range' => 1])] int $page = 1,
    ): Response {
        $medias = $mediaRepository->paginateByTag($user, $tag, $page);
        if ($medias->isOutOfRange()) {
            throw $this->createNotFoundException();
        }

        return $this->render('tag/show.html.twig', [
            'tag' => $tag,
            'medias' => $medias,
        ]);
    }
}
