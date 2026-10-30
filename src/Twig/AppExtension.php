<?php

namespace App\Twig;

use App\Entity\Media;
use App\Service\MediaStorage;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
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
}
