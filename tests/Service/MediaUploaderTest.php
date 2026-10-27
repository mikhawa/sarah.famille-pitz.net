<?php

namespace App\Tests\Service;

use App\Enum\MediaType;
use App\Exception\UnsupportedMediaException;
use App\Service\Ffmpeg;
use App\Service\MediaStorage;
use App\Service\MediaUploader;
use App\Tests\Support\MediaFixtures;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\Process\Process;

class MediaUploaderTest extends KernelTestCase
{
    private MediaFixtures $fixtures;
    private MediaUploader $uploader;
    private MediaStorage $storage;

    protected function setUp(): void
    {
        self::bootKernel();
        $this->fixtures = new MediaFixtures();
        $this->uploader = static::getContainer()->get(MediaUploader::class);
        $this->storage = static::getContainer()->get(MediaStorage::class);
    }

    protected function tearDown(): void
    {
        $this->fixtures->cleanup();
        (new Filesystem())->remove(static::getContainer()->getParameter('app.media_storage_dir'));
        parent::tearDown();
    }

    public function testPhotoIsStrippedOfGpsAndStraightened(): void
    {
        $source = $this->fixtures->jpegWithGps();
        // Contrôle du fichier de test lui-même
        $exif = exif_read_data($source);
        self::assertSame('N', $exif['GPSLatitudeRef'] ?? null);
        self::assertSame(6, $exif['Orientation'] ?? null);

        $media = $this->uploader->upload(new File($source), 'Noël.jpg');

        self::assertSame(MediaType::Photo, $media->getType());
        self::assertSame('Noël.jpg', $media->getOriginalFilename());
        self::assertSame('2025-12-24 18:30:00', $media->getTakenAt()?->format('Y-m-d H:i:s'));
        // Orientation appliquée : 40×20 devient 20×40
        self::assertSame([20, 40], [$media->getWidth(), $media->getHeight()]);

        $stored = $this->storage->getOriginalPath($media);
        self::assertFileExists($stored);
        self::assertStringNotContainsString($this->storagePathRoot(), $media->getStoragePath());
        $storedExif = @exif_read_data($stored) ?: [];
        self::assertArrayNotHasKey('GPSLatitudeRef', $storedExif);
        self::assertArrayNotHasKey('GPSLatitude', $storedExif);
        self::assertArrayNotHasKey('Orientation', $storedExif);
        self::assertStringNotContainsString('Exif', (string) file_get_contents($stored, length: 200));
        self::assertSame(filesize($stored), $media->getSize());

        foreach (array_keys(MediaStorage::THUMB_SIZES) as $size) {
            self::assertFileExists($this->storage->getThumbnailPath($media, $size));
        }
    }

    public function testLargePhotoThumbnailsAreResized(): void
    {
        $media = $this->uploader->upload(new File($this->fixtures->png()), 'image.png');

        self::assertSame([800, 600], [$media->getWidth(), $media->getHeight()]);
        self::assertSame(400, getimagesize($this->storage->getThumbnailPath($media, 'small'))[0]);
        // Plus petite que 1600 px : pas d'agrandissement
        self::assertSame(800, getimagesize($this->storage->getThumbnailPath($media, 'large'))[0]);
    }

    public function testVideoIsStrippedOfLocation(): void
    {
        $source = $this->fixtures->mp4WithLocation();
        self::assertStringContainsString('50.8503', $this->formatTags($source));

        $media = $this->uploader->upload(new File($source), 'anniversaire.mp4');

        self::assertSame(MediaType::Video, $media->getType());
        self::assertSame([320, 240], [$media->getWidth(), $media->getHeight()]);
        self::assertSame(3, $media->getDuration());
        self::assertSame('2025-08-15', $media->getTakenAt()?->format('Y-m-d'));

        $stored = $this->storage->getOriginalPath($media);
        self::assertStringNotContainsString('50.8503', $this->formatTags($stored));
        self::assertStringNotContainsString('50.8503', (string) file_get_contents($stored));
        self::assertFileExists($this->storage->getThumbnailPath($media, 'small'));
    }

    public function testAudioIsStored(): void
    {
        $media = $this->uploader->upload(new File($this->fixtures->wav()), 'berceuse.wav');

        self::assertSame(MediaType::Audio, $media->getType());
        self::assertSame(2, $media->getDuration());
        self::assertFileExists($this->storage->getOriginalPath($media));
        self::assertFileDoesNotExist($this->storage->getThumbnailPath($media, 'small'));
    }

    public function testFileTypeIsCheckedOnContentNotExtension(): void
    {
        $this->expectException(UnsupportedMediaException::class);
        $this->expectExceptionMessage('Type de fichier non pris en charge');

        $this->uploader->upload(new File($this->fixtures->textDisguisedAsJpeg()), 'faux.jpg');
    }

    public function testNothingIsLeftOnDiskWhenAFileIsRejected(): void
    {
        try {
            $this->uploader->upload(new File($this->fixtures->textDisguisedAsJpeg()), 'faux.jpg');
        } catch (UnsupportedMediaException) {
        }

        $root = $this->storagePathRoot();
        $files = is_dir($root) ? iterator_to_array(new \RecursiveIteratorIterator(new \RecursiveDirectoryIterator($root, \FilesystemIterator::SKIP_DIRS))) : [];
        self::assertCount(0, $files);
    }

    private function formatTags(string $path): string
    {
        return (new Process(['ffprobe', '-v', 'error', '-show_entries', 'format_tags', '-of', 'json', $path]))->mustRun()->getOutput();
    }

    private function storagePathRoot(): string
    {
        return static::getContainer()->getParameter('app.media_storage_dir');
    }
}
