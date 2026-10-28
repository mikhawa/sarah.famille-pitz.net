<?php

namespace App\Service;

use App\Entity\Media;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Component\Filesystem\Filesystem;

/**
 * Emplacements des fichiers médias, hors de public/ :
 *
 *   <racine>/originals/AAAA/MM/<uuid>.<ext>
 *   <racine>/thumbs/AAAA/MM/<uuid>_<taille>.webp
 */
class MediaStorage
{
    /**
     * Largeur maximale en pixels de chaque taille de miniature
     */
    public const THUMB_SIZES = [
        'small' => 400,
        'large' => 1600,
    ];

    private readonly Filesystem $filesystem;

    public function __construct(
        #[Autowire('%app.media_storage_dir%')]
        private readonly string $rootDir,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * Nouveau chemin relatif unique, rangé par année et mois d'envoi
     */
    public function generateStoragePath(string $extension): string
    {
        return \sprintf('%s/%s.%s', date('Y/m'), bin2hex(random_bytes(16)), strtolower($extension));
    }

    public function getOriginalPath(Media $media): string
    {
        return $this->getOriginalPathFor($media->getStoragePath());
    }

    public function getOriginalPathFor(string $storagePath): string
    {
        return $this->rootDir.'/originals/'.$storagePath;
    }

    public function getThumbnailPath(Media $media, string $size): string
    {
        return $this->getThumbnailPathFor($media->getStoragePath(), $size);
    }

    public function getThumbnailPathFor(string $storagePath, string $size): string
    {
        if (!isset(self::THUMB_SIZES[$size])) {
            throw new \InvalidArgumentException(\sprintf('Taille de miniature inconnue : "%s".', $size));
        }

        $info = pathinfo($storagePath);

        return \sprintf('%s/thumbs/%s/%s_%s.webp', $this->rootDir, $info['dirname'], $info['filename'], $size);
    }

    /**
     * Chemin d'un fichier du stockage relatif à la racine, segments encodés pour une URL (ex. /originals/2026/10/<uuid>.jpg)
     */
    public function getRelativePath(string $path): string
    {
        if (!str_starts_with($path, $this->rootDir.'/')) {
            throw new \InvalidArgumentException(\sprintf('Fichier hors du stockage des médias : "%s".', $path));
        }

        return implode('/', array_map(rawurlencode(...), explode('/', substr($path, \strlen($this->rootDir)))));
    }

    public function ensureDirectoryFor(string $path): void
    {
        $this->filesystem->mkdir(\dirname($path));
    }

    /**
     * Supprime l'original et toutes ses miniatures
     */
    public function delete(Media $media): void
    {
        $this->deleteFiles($media->getStoragePath());
    }

    public function deleteFiles(string $storagePath): void
    {
        $paths = [$this->getOriginalPathFor($storagePath)];
        foreach (array_keys(self::THUMB_SIZES) as $size) {
            $paths[] = $this->getThumbnailPathFor($storagePath, $size);
        }

        $this->filesystem->remove($paths);
    }
}
