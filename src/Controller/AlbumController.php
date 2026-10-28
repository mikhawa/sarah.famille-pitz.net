<?php

namespace App\Controller;

use App\Entity\Album;
use App\Repository\AlbumRepository;
use App\Repository\MediaRepository;
use App\Repository\TagRepository;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/albums')]
class AlbumController extends AbstractController
{
    #[Route('', name: 'album_index', methods: ['GET'])]
    public function index(AlbumRepository $albumRepository, MediaRepository $mediaRepository, TagRepository $tagRepository): Response
    {
        return $this->render('album/index.html.twig', [
            'albums' => $albumRepository->findAllForListing(),
            'stats' => $mediaRepository->getAlbumStats(),
            'tags' => $tagRepository->findUsedWithMediaCount(),
        ]);
    }

    #[Route('/{slug}', name: 'album_show', methods: ['GET'])]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] Album $album,
        MediaRepository $mediaRepository,
        #[MapQueryParameter(options: ['min_range' => 1])] int $page = 1,
    ): Response {
        $medias = $mediaRepository->paginateByAlbum($album, $page);
        if ($medias->isOutOfRange()) {
            throw $this->createNotFoundException();
        }

        return $this->render('album/show.html.twig', [
            'album' => $album,
            'medias' => $medias,
        ]);
    }
}
