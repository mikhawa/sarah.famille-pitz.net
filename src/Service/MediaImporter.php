<?php

namespace App\Service;

use App\Entity\Album;
use App\Entity\Tag;
use App\Entity\User;
use App\Exception\UnsupportedMediaException;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Enregistre un lot de fichiers envoyés (administration ou page d'un album) : nettoyage par MediaUploader,
 * rangement dans l'album, tags. La visibilité des médias reste « comme l'album » (Media::$visibility à null).
 */
class MediaImporter
{
    public function __construct(
        private readonly MediaUploader $uploader,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @param iterable<UploadedFile> $files
     * @param iterable<Tag>          $tags
     *
     * @return array{saved: int, errors: list<string>} erreurs en texte brut (nom du fichier venu du navigateur : à échapper)
     */
    public function import(iterable $files, ?User $uploadedBy, ?Album $album, iterable $tags = []): array
    {
        $saved = 0;
        $errors = [];

        foreach ($files as $file) {
            try {
                $media = $this->uploader->upload($file, $file->getClientOriginalName(), $uploadedBy);
            } catch (UnsupportedMediaException $e) {
                $errors[] = \sprintf('%s : %s', $file->getClientOriginalName(), $e->getMessage());
                continue;
            }

            $media->setAlbum($album);
            foreach ($tags as $tag) {
                $media->addTag($tag);
            }

            // Enregistrement fichier par fichier : un échec n'annule pas les envois précédents
            $this->entityManager->persist($media);
            $this->entityManager->flush();
            ++$saved;
        }

        return ['saved' => $saved, 'errors' => $errors];
    }
}
