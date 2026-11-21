<?php

namespace App\Service;

use App\Entity\Media;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * Données de la visionneuse PhotoSwipe : image affichée, dimensions et liste JSON des photos.
 * Partagé par les fonctions Twig (attributs des vignettes, listes d'album et de tag) et par la route JSON du fil.
 */
class LightboxItems
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    /**
     * Dimensions d'une miniature, calculées comme MediaUploader (réduction en largeur seulement, jamais d'agrandissement).
     * Nécessaires à la visionneuse PhotoSwipe, qui place l'image avant de l'avoir chargée.
     *
     * @return array{width: int, height: int}|null null si les dimensions du média sont inconnues
     */
    public function thumbSize(Media $media, string $size): ?array
    {
        $width = $media->getWidth();
        $height = $media->getHeight();
        if (null === $width || null === $height || $width < 1) {
            return null;
        }

        $maxWidth = MediaStorage::THUMB_SIZES[$size] ?? throw new \InvalidArgumentException(\sprintf('Taille de miniature inconnue : "%s".', $size));
        if ($width <= $maxWidth) {
            return ['width' => $width, 'height' => $height];
        }

        return ['width' => $maxWidth, 'height' => (int) round($height * $maxWidth / $width)];
    }

    /**
     * Image affichée par la visionneuse pour une photo : grande miniature, ou l'original pour un GIF (la miniature
     * WebP est fixe, l'original garde l'animation).
     *
     * @return array{src: string, width: int, height: int}|null null si les dimensions sont inconnues
     */
    public function lightboxImage(Media $media): ?array
    {
        if ('image/gif' === $media->getMimeType()) {
            $width = $media->getWidth();
            $height = $media->getHeight();
            $size = null !== $width && null !== $height ? ['width' => $width, 'height' => $height] : null;
            $src = $this->urlGenerator->generate('media_file', ['id' => $media->getId()]);
        } else {
            $size = $this->thumbSize($media, 'large');
            $src = $this->urlGenerator->generate('media_thumb', ['id' => $media->getId(), 'size' => 'large']);
        }

        return null === $size ? null : ['src' => $src] + $size;
    }

    /**
     * Données JSON de la visionneuse pour une liste de photos (les photos sans dimensions connues sont ignorées).
     *
     * @param iterable<Media> $medias
     *
     * @return list<array{id: int|null, src: string, width: int, height: int, href: string, commentsUrl: string, alt: string}>
     */
    public function items(iterable $medias): array
    {
        $items = [];
        foreach ($medias as $media) {
            $image = $this->lightboxImage($media);
            if (null === $image) {
                continue;
            }
            $items[] = ['id' => $media->getId()] + $image + [
                'href' => $this->urlGenerator->generate('media_show', ['id' => $media->getId()]),
                'commentsUrl' => $this->urlGenerator->generate('media_comments', ['id' => $media->getId()]),
                'alt' => $media->getDisplayTitle(),
            ];
        }

        return $items;
    }
}
