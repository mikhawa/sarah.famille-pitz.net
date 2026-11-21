<?php

namespace App\Twig;

use App\Entity\Media;
use App\Repository\CommentRepository;
use App\Service\LightboxItems;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;
use Twig\TwigFunction;

class AppExtension extends AbstractExtension
{
    public function __construct(
        private readonly LightboxItems $lightboxItems,
        private readonly CommentRepository $commentRepository,
        private readonly Security $security,
    ) {
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
            new TwigFunction('comment_counts', $this->commentCounts(...)),
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

    /**
     * Nombre de petits mots de chaque média d'une liste (pastille des vignettes), en une seule requête.
     * Mêmes commentaires que la fiche du média : les masqués ne comptent que pour les administrateurs.
     *
     * @param iterable<Media> $medias
     *
     * @return array<int, int> identifiant du média => nombre (absent si aucun)
     */
    public function commentCounts(iterable $medias): array
    {
        $medias = \is_array($medias) ? array_values($medias) : iterator_to_array($medias, false);

        return $this->commentRepository->countByMedia($medias, includeHidden: $this->security->isGranted('ROLE_ADMIN'));
    }
}
