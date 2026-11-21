<?php

namespace App\Twig;

use App\Entity\Media;
use App\Service\MediaStorage;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(private readonly UrlGeneratorInterface $urlGenerator)
    {
    }

    public function getFilters(): array
    {
        return [
            new TwigFilter('duration', $this->formatDuration(...)),
        ];
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('thumb_size', $this->thumbSize(...)),
            new TwigFunction('lightbox_image', $this->lightboxImage(...)),
            new TwigFunction('lightbox_items', $this->lightboxItems(...)),
        ];
    }

    /**
     * Durée en secondes → « 3:07 » ou « 1:02:45 »
     */
    public function formatDuration(?int $seconds): string
    {
        if (null === $seconds) {
            return '';
        }

        $hours = intdiv($seconds, 3600);
        $minutes = intdiv($seconds % 3600, 60);

        return $hours > 0
            ? \sprintf('%d:%02d:%02d', $hours, $minutes, $seconds % 60)
            : \sprintf('%d:%02d', $minutes, $seconds % 60);
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
    public function lightboxItems(iterable $medias): array
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
