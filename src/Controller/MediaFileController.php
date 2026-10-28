<?php

namespace App\Controller;

use App\Entity\Media;
use App\Security\Voter\MediaVoter;
use App\Service\MediaStorage;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\HttpFoundation\BinaryFileResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpFoundation\ResponseHeaderBag;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

/**
 * Seul point d'accès aux fichiers médias : vérifie les droits avant chaque envoi.
 *
 * Avec MEDIA_X_ACCEL_PREFIX (prod), PHP ne lit pas le fichier : il répond par un en-tête X-Accel-Redirect
 * et nginx envoie le fichier depuis une location « internal » (inaccessible directement par URL).
 */
#[Route('/media/{id}', requirements: ['id' => '\d+'])]
class MediaFileController extends AbstractController
{
    public function __construct(
        private readonly MediaStorage $storage,
        #[Autowire('%env(MEDIA_X_ACCEL_PREFIX)%')]
        private readonly string $xAccelPrefix,
    ) {
    }

    #[Route('/file', name: 'media_file', methods: ['GET'])]
    #[IsGranted(MediaVoter::VIEW, subject: 'media')]
    public function original(Media $media, Request $request): Response
    {
        $disposition = $request->query->getBoolean('download')
            ? ResponseHeaderBag::DISPOSITION_ATTACHMENT
            : ResponseHeaderBag::DISPOSITION_INLINE;

        // Requêtes Range (avance rapide dans les vidéos et la musique) : gérées par BinaryFileResponse ou par nginx
        $response = $this->send($this->storage->getOriginalPath($media), $media->getMimeType());
        $response->headers->set('Content-Disposition', $response->headers->makeDisposition(
            $disposition,
            $media->getOriginalFilename(),
            $this->asciiFilename($media),
        ));

        return $response;
    }

    #[Route('/thumb/{size}', name: 'media_thumb', requirements: ['size' => 'small|large'], methods: ['GET'])]
    #[IsGranted(MediaVoter::VIEW, subject: 'media')]
    public function thumbnail(Media $media, string $size): Response
    {
        return $this->send($this->storage->getThumbnailPath($media, $size), 'image/webp');
    }

    private function send(string $path, string $mimeType): Response
    {
        if (!is_file($path)) {
            throw $this->createNotFoundException();
        }

        if ('' !== $this->xAccelPrefix) {
            $response = new Response();
            $response->headers->set('X-Accel-Redirect', $this->xAccelPrefix.$this->storage->getRelativePath($path));
        } else {
            $response = new BinaryFileResponse($path, autoEtag: true, autoLastModified: true);
        }

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
