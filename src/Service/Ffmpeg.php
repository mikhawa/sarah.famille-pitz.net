<?php

namespace App\Service;

use Symfony\Component\Process\Exception\ProcessFailedException;
use Symfony\Component\Process\Process;

/**
 * Appels à ffprobe / ffmpeg pour les fichiers audio et vidéo
 */
class Ffmpeg
{
    private const TIMEOUT = 600;

    /**
     * @return array{duration: ?int, width: ?int, height: ?int, createdAt: ?\DateTimeImmutable}
     */
    public function probe(string $path): array
    {
        $process = new Process([
            'ffprobe', '-v', 'error', '-print_format', 'json', '-show_format', '-show_streams', $path,
        ]);
        $process->setTimeout(self::TIMEOUT)->mustRun();

        $data = json_decode($process->getOutput(), true, flags: \JSON_THROW_ON_ERROR);

        $video = null;
        foreach ($data['streams'] ?? [] as $stream) {
            // Une pochette d'album est vue comme un flux vidéo : on l'ignore
            if ('video' === ($stream['codec_type'] ?? null) && !($stream['disposition']['attached_pic'] ?? 0)) {
                $video = $stream;
                break;
            }
        }

        $duration = isset($data['format']['duration']) ? (int) round((float) $data['format']['duration']) : null;
        $createdAt = null;
        if (isset($data['format']['tags']['creation_time'])) {
            try {
                $createdAt = (new \DateTimeImmutable($data['format']['tags']['creation_time']))
                    ->setTimezone(new \DateTimeZone(date_default_timezone_get()));
            } catch (\Exception) {
            }
        }

        [$width, $height] = [isset($video['width']) ? (int) $video['width'] : null, isset($video['height']) ? (int) $video['height'] : null];
        // Vidéo tournée en portrait : le téléphone enregistre en paysage avec une rotation
        $rotation = abs((int) ($video['tags']['rotate'] ?? $video['side_data_list'][0]['rotation'] ?? 0));
        if (90 === $rotation || 270 === $rotation) {
            [$width, $height] = [$height, $width];
        }

        return [
            'duration' => $duration,
            'width' => $width,
            'height' => $height,
            'createdAt' => $createdAt,
        ];
    }

    /**
     * Copie le fichier sans réencodage en retirant toutes les métadonnées (dont la position GPS)
     */
    public function stripMetadata(string $source, string $target): void
    {
        $command = ['ffmpeg', '-v', 'error', '-y', '-i', $source, '-map', '0', '-map_metadata', '-1', '-c', 'copy'];
        if (\in_array(strtolower(pathinfo($target, \PATHINFO_EXTENSION)), ['mp4', 'mov', 'm4v'], true)) {
            // Index en début de fichier : la lecture démarre sans tout télécharger
            $command = [...$command, '-movflags', '+faststart'];
        }

        $this->run([...$command, $target]);
    }

    /**
     * Extrait une image de la vidéo (à 1 s, ou la première si la vidéo est plus courte)
     */
    public function extractFrame(string $videoPath, string $imagePath, ?int $duration): void
    {
        $at = null !== $duration && $duration >= 2 ? '1' : '0';

        $this->run(['ffmpeg', '-v', 'error', '-y', '-ss', $at, '-i', $videoPath, '-frames:v', '1', '-q:v', '2', $imagePath]);
    }

    /**
     * @param list<string> $command
     */
    private function run(array $command): void
    {
        $process = new Process($command);
        $process->setTimeout(self::TIMEOUT);
        $process->run();

        if (!$process->isSuccessful()) {
            throw new ProcessFailedException($process);
        }
    }
}
