<?php

namespace App\Enum;

enum MediaType: string
{
    case Photo = 'photo';
    case Audio = 'audio';
    case Video = 'video';

    public function label(): string
    {
        return match ($this) {
            self::Photo => 'Photo',
            self::Audio => 'Musique',
            self::Video => 'Vidéo',
        };
    }

    /**
     * Les photos et les vidéos ont une miniature (image d'aperçu pour les vidéos)
     */
    public function hasThumbnail(): bool
    {
        return self::Audio !== $this;
    }
}
