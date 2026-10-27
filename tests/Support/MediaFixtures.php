<?php

namespace App\Tests\Support;

use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\Process\Process;

/**
 * Fabrique des fichiers médias de test (aucun binaire versionné dans le dépôt)
 */
final class MediaFixtures
{
    private string $dir;

    public function __construct()
    {
        $this->dir = sys_get_temp_dir().'/sarah_fixtures_'.bin2hex(random_bytes(4));
        (new Filesystem())->mkdir($this->dir);
    }

    public function cleanup(): void
    {
        (new Filesystem())->remove($this->dir);
    }

    /**
     * JPEG 40×20 contenant une position GPS, une orientation 6 (à pivoter de 90°) et une date de prise de vue
     */
    public function jpegWithGps(): string
    {
        $image = imagecreatetruecolor(40, 20);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 50, 50));
        ob_start();
        imagejpeg($image);
        $jpeg = (string) ob_get_clean();

        $app1 = "Exif\0\0".$this->buildTiff();
        $segment = "\xFF\xE1".pack('n', \strlen($app1) + 2).$app1;

        // Segment EXIF inséré juste après le marqueur SOI
        return $this->write('photo.jpg', substr($jpeg, 0, 2).$segment.substr($jpeg, 2));
    }

    public function png(): string
    {
        $image = imagecreatetruecolor(800, 600);
        ob_start();
        imagepng($image);

        return $this->write('image.png', (string) ob_get_clean());
    }

    /**
     * Vidéo MP4 de 3 s en 320×240 avec une position GPS et une date de tournage dans les métadonnées
     */
    public function mp4WithLocation(): string
    {
        $path = $this->dir.'/video.mp4';
        (new Process([
            'ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'testsrc=duration=3:size=320x240:rate=10',
            '-c:v', 'libx264', '-pix_fmt', 'yuv420p',
            '-metadata', 'location=+50.8503+004.3517/',
            '-metadata', 'creation_time=2025-08-15T10:30:00Z',
            $path,
        ]))->mustRun();

        return $path;
    }

    public function wav(): string
    {
        $path = $this->dir.'/son.wav';
        (new Process(['ffmpeg', '-v', 'error', '-y', '-f', 'lavfi', '-i', 'sine=frequency=440:duration=2', $path]))->mustRun();

        return $path;
    }

    public function textDisguisedAsJpeg(): string
    {
        return $this->write('faux.jpg', "Ceci n'est pas une image\n");
    }

    private function write(string $name, string $content): string
    {
        $path = $this->dir.'/'.$name;
        file_put_contents($path, $content);

        return $path;
    }

    /**
     * Structure TIFF minimale (petit-boutiste) : IFD0 → Orientation, sous-IFD EXIF (date), sous-IFD GPS
     */
    private function buildTiff(): string
    {
        $entry = static fn (int $tag, int $type, int $count, string $value): string => pack('vvV', $tag, $type, $count).str_pad($value, 4, "\0");

        $exifIfdOffset = 50;
        $dateOffset = 68;
        $gpsIfdOffset = 88;
        $rationalOffset = 118;

        $tiff = "II\x2A\x00".pack('V', 8);
        // IFD0 (3 entrées, triées par tag)
        $tiff .= pack('v', 3)
            .$entry(0x0112, 3, 1, pack('v', 6))
            .$entry(0x8769, 4, 1, pack('V', $exifIfdOffset))
            .$entry(0x8825, 4, 1, pack('V', $gpsIfdOffset))
            .pack('V', 0);
        // IFD EXIF : DateTimeOriginal
        $tiff .= pack('v', 1).$entry(0x9003, 2, 20, pack('V', $dateOffset)).pack('V', 0);
        $tiff .= "2025:12:24 18:30:00\0";
        // IFD GPS : latitude 50° 50' N
        $tiff .= pack('v', 2)
            .$entry(0x0001, 2, 2, "N\0")
            .$entry(0x0002, 5, 3, pack('V', $rationalOffset))
            .pack('V', 0);
        $tiff .= pack('VVVVVV', 50, 1, 50, 1, 0, 1);

        \assert(\strlen($tiff) === $rationalOffset + 24);

        return $tiff;
    }
}
