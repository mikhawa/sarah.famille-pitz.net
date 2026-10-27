<?php

namespace App\EventListener;

use App\Entity\Media;
use App\Service\MediaStorage;
use Doctrine\Bundle\DoctrineBundle\Attribute\AsEntityListener;
use Doctrine\ORM\Events;

/**
 * Supprime les fichiers du disque quand un média est supprimé, quel que soit le chemin (admin, commande…)
 */
#[AsEntityListener(event: Events::postRemove, entity: Media::class)]
class MediaFileCleanupListener
{
    public function __construct(
        private readonly MediaStorage $storage,
    ) {
    }

    // postRemove : après la suppression en base, pour ne pas perdre les fichiers si elle échoue
    public function postRemove(Media $media): void
    {
        $this->storage->delete($media);
    }
}
