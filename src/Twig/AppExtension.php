<?php

namespace App\Twig;

use App\Entity\Media;
use App\Service\LightboxItems;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(private readonly LightboxItems $lightboxItems)
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
            new TwigFunction('lightbox_items', $this->lightboxItemsList(...)),
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

    public function thumbSize(Media $media, string $size): ?array
    {
        return $this->lightboxItems->thumbSize($media, $size);
    }

    public function lightboxImage(Media $media): ?array
    {
        return $this->lightboxItems->lightboxImage($media);
    }

    /**
     * @param iterable<Media> $medias
     */
    public function lightboxItemsList(iterable $medias): array
    {
        return $this->lightboxItems->items($medias);
    }
}
