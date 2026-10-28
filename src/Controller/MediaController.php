<?php

namespace App\Controller;

use App\Entity\Media;
use App\Repository\MediaRepository;
use App\Security\Voter\MediaVoter;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MediaController extends AbstractController
{
    // Les fichiers eux-mêmes sont servis par MediaFileController (/media/{id}/file et /thumb)
    #[Route('/media/{id}', name: 'media_show', requirements: ['id' => '\d+'], methods: ['GET'])]
    #[IsGranted(MediaVoter::VIEW, subject: 'media')]
    public function show(Media $media, MediaRepository $mediaRepository): Response
    {
        $older = $mediaRepository->findAdjacent($media, newer: false);
        $newer = $mediaRepository->findAdjacent($media, newer: true);

        // Même sens de lecture que la liste d'où l'on vient : album du plus ancien au plus récent, fil à l'inverse
        $inAlbum = null !== $media->getAlbum();

        return $this->render('media/show.html.twig', [
            'media' => $media,
            'previous' => $inAlbum ? $older : $newer,
            'next' => $inAlbum ? $newer : $older,
        ]);
    }
}
