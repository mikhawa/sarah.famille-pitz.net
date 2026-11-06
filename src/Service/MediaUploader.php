<?php

namespace App\Service;

use App\Entity\Media;
use App\Entity\User;
use App\Enum\MediaType;
use App\Exception\UnsupportedMediaException;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\Exception\ExceptionInterface as ProcessException;

/**
 * Range un fichier envoyé dans le stockage privé et crée l'entité Media correspondante.
 *
 * - Photos : réencodées avec GD, ce qui supprime toutes les métadonnées EXIF (GPS compris),
 *   après application de l'orientation ; seule la date de prise de vue est gardée en base.
 * - Vidéos : métadonnées (GPS compris) retirées par ffmpeg, sans réencodage ; image d'aperçu extraite.
 * - Audio : copié tel quel.
 */
class MediaUploader
{
    /**
     * Type MIME détecté sur le contenu (pas sur l'extension) => [type, extension stockée]
     */
    private const ALLOWED = [
        'image/jpeg' => [MediaType::Photo, 'jpg'],
        'image/png' => [MediaType::Photo, 'png'],
        'image/webp' => [MediaType::Photo, 'webp'],
        'image/gif' => [MediaType::Photo, 'gif'],
        'audio/mpeg' => [MediaType::Audio, 'mp3'],
        'audio/mp4' => [MediaType::Audio, 'm4a'],
        'audio/x-m4a' => [MediaType::Audio, 'm4a'],
        'audio/ogg' => [MediaType::Audio, 'ogg'],
        'audio/wav' => [MediaType::Audio, 'wav'],
        'audio/x-wav' => [MediaType::Audio, 'wav'],
        'audio/flac' => [MediaType::Audio, 'flac'],
        'audio/x-flac' => [MediaType::Audio, 'flac'],
        'video/mp4' => [MediaType::Video, 'mp4'],
        'video/webm' => [MediaType::Video, 'webm'],
        'video/quicktime' => [MediaType::Video, 'mov'],
    ];

    // Au-delà, le décodage GD dépasserait la mémoire disponible
    private const MAX_PIXELS = 40_000_000;

    private const JPEG_QUALITY = 90;
    private const THUMB_QUALITY = 80;

    private readonly Filesystem $filesystem;

    public function __construct(
        private readonly MediaStorage $storage,
        private readonly Ffmpeg $ffmpeg,
    ) {
        $this->filesystem = new Filesystem();
    }

    /**
     * @throws UnsupportedMediaException fichier refusé ou illisible (rien n'est conservé)
     */
    public function upload(File $file, string $originalFilename, ?User $uploadedBy = null): Media
    {
        $mimeType = (string) $file->getMimeType();

        if (\in_array($mimeType, ['image/heic', 'image/heif'], true)) {
            throw new UnsupportedMediaException('Format HEIC (iPhone) non pris en charge : exportez la photo en JPEG.');
        }
        if (!isset(self::ALLOWED[$mimeType])) {
            throw new UnsupportedMediaException(\sprintf('Type de fichier non pris en charge (%s).', $mimeType));
        }

        [$type, $extension] = self::ALLOWED[$mimeType];
        $storagePath = $this->storage->generateStoragePath($extension);
        $target = $this->storage->getOriginalPathFor($storagePath);
        $this->storage->ensureDirectoryFor($target);

        $media = new Media($type, $storagePath, $originalFilename, $mimeType);

        try {
            match ($type) {
                MediaType::Photo => $this->storePhoto($file->getPathname(), $media),
                MediaType::Video => $this->storeVideo($file->getPathname(), $media),
                MediaType::Audio => $this->storeAudio($file->getPathname(), $media),
            };
        } catch (UnsupportedMediaException $e) {
            $this->storage->deleteFiles($storagePath);
            throw $e;
        } catch (ProcessException|\ValueError|\JsonException $e) {
            $this->storage->deleteFiles($storagePath);
            throw new UnsupportedMediaException('Fichier illisible ou endommagé.', previous: $e);
        }

        clearstatcache(true, $target);
        $media->setSize((int) filesize($target));
        $media->setUploadedBy($uploadedBy);

        return $media;
    }

    private function storePhoto(string $source, Media $media): void
    {
        $info = @getimagesize($source);
        if (false === $info) {
            throw new UnsupportedMediaException('Image illisible ou endommagée.');
        }
        if ($info[0] * $info[1] > self::MAX_PIXELS) {
            throw new UnsupportedMediaException('Image trop grande (40 mégapixels maximum).');
        }

        $target = $this->storage->getOriginalPath($media);

        // GIF conservé tel quel pour ne pas perdre l'animation (pas d'EXIF dans ce format)
        if ('image/gif' === $media->getMimeType()) {
            $this->filesystem->copy($source, $target, true);
            $image = @imagecreatefromgif($source);
            if (false === $image) {
                throw new UnsupportedMediaException('Image illisible ou endommagée.');
            }
            $media->setDimensions($info[0], $info[1]);
            $this->writeThumbnails($image, $media);

            return;
        }

        $exif = 'image/jpeg' === $media->getMimeType() ? (@exif_read_data($source) ?: []) : [];
        $media->setTakenAt($this->readExifDate($exif));

        $image = match ($media->getMimeType()) {
            'image/jpeg' => @imagecreatefromjpeg($source),
            'image/png' => @imagecreatefrompng($source),
            'image/webp' => @imagecreatefromwebp($source),
        };
        if (false === $image) {
            throw new UnsupportedMediaException('Image illisible ou endommagée.');
        }

        $image = $this->applyOrientation($image, (int) ($exif['Orientation'] ?? 1));

        // Le réencodage n'écrit aucune métadonnée : EXIF, GPS, XMP disparaissent
        $written = match ($media->getMimeType()) {
            'image/jpeg' => imagejpeg($image, $target, self::JPEG_QUALITY),
            'image/png' => $this->saveWithAlpha($image, static fn ($img) => imagepng($img, $target, 6)),
            'image/webp' => $this->saveWithAlpha($image, static fn ($img) => imagewebp($img, $target, self::JPEG_QUALITY)),
        };
        if (!$written) {
            throw new UnsupportedMediaException('Impossible d\'enregistrer l\'image.');
        }

        $media->setDimensions(imagesx($image), imagesy($image));
        $this->writeThumbnails($image, $media);
    }

    private function storeVideo(string $source, Media $media): void
    {
        // Lecture avant nettoyage : la date de tournage fait partie des métadonnées supprimées
        $probe = $this->ffmpeg->probe($source);
        $media
            ->setDuration($probe['duration'])
            ->setDimensions($probe['width'], $probe['height'])
            ->setTakenAt($probe['createdAt']);

        $target = $this->storage->getOriginalPath($media);
        $this->ffmpeg->stripMetadata($source, $target);

        $frame = $this->filesystem->tempnam(sys_get_temp_dir(), 'frame_', '.jpg');
        try {
            $this->ffmpeg->extractFrame($target, $frame, $probe['duration']);
            $image = @imagecreatefromjpeg($frame);
            // Une vidéo sans image exploitable reste acceptée, simplement sans aperçu
            if (false !== $image) {
                $this->writeThumbnails($image, $media);
            }
        } catch (ProcessException) {
        } finally {
            $this->filesystem->remove($frame);
        }
    }

    private function storeAudio(string $source, Media $media): void
    {
        $probe = $this->ffmpeg->probe($source);
        $media->setDuration($probe['duration']);

        $this->filesystem->copy($source, $this->storage->getOriginalPath($media), true);
    }

    /**
     * Recrée les miniatures d'un média déjà stocké, à partir de son original (commande app:media:regenerate-thumbs)
     *
     * @return bool false si ce type de média n'a pas de miniature (musique)
     *
     * @throws UnsupportedMediaException original illisible ou miniature impossible à écrire
     * @throws ProcessException          échec de ffmpeg (vidéo)
     */
    public function regenerateThumbnails(Media $media): bool
    {
        $original = $this->storage->getOriginalPath($media);
        if (!is_file($original)) {
            throw new UnsupportedMediaException(\sprintf('Original introuvable : %s', $original));
        }

        if (MediaType::Video === $media->getType()) {
            $frame = $this->filesystem->tempnam(sys_get_temp_dir(), 'frame_', '.jpg');
            try {
                $this->ffmpeg->extractFrame($original, $frame, $media->getDuration());
                $image = @imagecreatefromjpeg($frame);
            } finally {
                $this->filesystem->remove($frame);
            }
        } elseif (MediaType::Photo === $media->getType()) {
            // Original déjà réencodé et redressé à l'envoi : aucune orientation à appliquer
            $image = match ($media->getMimeType()) {
                'image/jpeg' => @imagecreatefromjpeg($original),
                'image/png' => @imagecreatefrompng($original),
                'image/webp' => @imagecreatefromwebp($original),
                'image/gif' => @imagecreatefromgif($original),
                default => false,
            };
        } else {
            return false;
        }

        if (false === $image) {
            throw new UnsupportedMediaException(\sprintf('Image illisible : %s', $original));
        }

        $this->writeThumbnails($image, $media);

        return true;
    }

    /**
     * @throws UnsupportedMediaException miniature impossible à créer : l'envoi est refusé plutôt que d'enregistrer
     *                                   un média sans aperçu (404 sur les galeries)
     */
    private function writeThumbnails(\GdImage $image, Media $media): void
    {
        $width = imagesx($image);

        foreach (MediaStorage::THUMB_SIZES as $size => $maxWidth) {
            $thumb = $width > $maxWidth ? $this->resizeToWidth($image, $maxWidth) : $image;

            $path = $this->storage->getThumbnailPath($media, $size);
            $this->storage->ensureDirectoryFor($path);
            if (!$this->saveWithAlpha($thumb, static fn ($img) => imagewebp($img, $path, self::THUMB_QUALITY))) {
                throw new UnsupportedMediaException(\sprintf('Impossible d\'enregistrer la miniature « %s ».', $size));
            }
        }
    }

    /**
     * Réduction en largeur, proportions conservées (même calcul que AppExtension::thumbSize).
     *
     * imagecopyresampled plutôt qu'imagescale : avec la libgd système (lsphp de LiteSpeed en prod),
     * imagescale(…, IMG_BICUBIC) renvoie false et aucune miniature réduite n'était créée.
     */
    private function resizeToWidth(\GdImage $image, int $width): \GdImage
    {
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $height = max(1, (int) round($sourceHeight * $width / $sourceWidth));

        $resized = imagecreatetruecolor($width, $height);
        if (false === $resized) {
            throw new UnsupportedMediaException('Mémoire insuffisante pour créer la miniature.');
        }

        // Fond transparent conservé (PNG, WebP, GIF)
        imagealphablending($resized, false);
        imagesavealpha($resized, true);
        imagefill($resized, 0, 0, (int) imagecolorallocatealpha($resized, 0, 0, 0, 127));

        if (!imagecopyresampled($resized, $image, 0, 0, 0, 0, $width, $height, $sourceWidth, $sourceHeight)) {
            throw new UnsupportedMediaException('Impossible de réduire l\'image.');
        }

        return $resized;
    }

    /**
     * Redresse la photo selon le tag EXIF Orientation (perdu au réencodage)
     */
    private function applyOrientation(\GdImage $image, int $orientation): \GdImage
    {
        $rotated = match ($orientation) {
            3, 4 => imagerotate($image, 180, 0),
            5, 6 => imagerotate($image, -90, 0),
            7, 8 => imagerotate($image, 90, 0),
            default => $image,
        };
        if (false === $rotated) {
            return $image;
        }

        // Orientations « miroir »
        if (\in_array($orientation, [2, 4, 5, 7], true)) {
            imageflip($rotated, \IMG_FLIP_HORIZONTAL);
        }

        return $rotated;
    }

    /**
     * @param array<string, mixed> $exif
     */
    private function readExifDate(array $exif): ?\DateTimeImmutable
    {
        $raw = $exif['DateTimeOriginal'] ?? $exif['DateTime'] ?? null;
        if (!\is_string($raw)) {
            return null;
        }

        $date = \DateTimeImmutable::createFromFormat('Y:m:d H:i:s', trim($raw));

        // Les appareils mal réglés écrivent parfois 0000:00:00 00:00:00
        return false !== $date && (int) $date->format('Y') > 1900 ? $date : null;
    }

    /**
     * @param callable(\GdImage): bool $save
     */
    private function saveWithAlpha(\GdImage $image, callable $save): bool
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);

        return $save($image);
    }
}
