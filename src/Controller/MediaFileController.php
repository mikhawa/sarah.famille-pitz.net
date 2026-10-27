<?php

namespace App\Controller;

use App\Entity\Media;
use App\Security\Voter\MediaVoter;
use App\Service\MediaStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Seul point d'accès aux fichiers médias : vérifie les droits avant chaque envoi
 */
#[Route('/media/{id}', requirements: ['id' => '\d+'])]
class MediaFileController extends AbstractController
{
    public function __construct(
        private readonly MediaStorage $storage,
    ) {
    }

    #[Route('/file', name: 'media_file', methods: ['GET'])]
    #[IsGranted(MediaVoter::VIEW, subject: 'media')]
    public function original(Media $media, Request $request): BinaryFileResponse
    {
        $disposition = $request->query->getBoolean('download')
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;

        // BinaryFileResponse gère les requêtes Range : avance rapide dans les vidéos et la musique
        return $this->send($this->storage->getOriginalPath($media), $media->getMimeType())
            ->setContentDisposition($disposition, $media->getOriginalFilename(), $this->asciiFilename($media));
    }

    #[Route('/thumb/{size}', name: 'media_thumb', requirements: ['size' => 'small|large'], methods: ['GET'])]
    #[IsGranted(MediaVoter::VIEW, subject: 'media')]
    public function thumbnail(Media $media, string $size): BinaryFileResponse
    {
        return $this->send($this->storage->getThumbnailPath($media, $size), 'image/webp');
    }

    private function send(string $path, string $mimeType): BinaryFileResponse
    {
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        $response = new BinaryFileResponse($path, autoEtag: true, autoLastModified: true);
        $response->headers->set('Content-Type', $mimeType);
        // Contenu privé : mis en cache par le navigateur uniquement, jamais par un proxy
        $response->setPrivate();
        $response->setMaxAge(86400);
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        return $response;
    }

    private function asciiFilename(Media $media): string
    {
        $ascii = preg_replace('/[^\x20-\x7E]/', '_', $media->getOriginalFilename());

        return str_replace(['/', '\\', '%'], '_', (string) $ascii);
    }
}
