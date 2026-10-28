<?php

namespace App\Tests\Controller;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\ResetPasswordRequest;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\MediaType;
use App\Service\MediaStorage;
use App\Service\MediaUploader;
use App\Tests\Support\MediaFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

class MediaTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private MediaFixtures $fixtures;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->fixtures = new MediaFixtures();

        // Ordre imposé par les clés étrangères (couverture d'album → média)
        $this->entityManager->createQuery('UPDATE '.Album::class.' a SET a.coverMedia = NULL')->execute();
        foreach ([Media::class, ResetPasswordRequest::class, User::class, Album::class, Tag::class] as $class) {
            $this->entityManager->createQuery('DELETE FROM '.$class)->execute();
        }
    }

    protected function tearDown(): void
    {
        $this->fixtures->cleanup();
        (new Filesystem())->remove(static::getContainer()->getParameter('app.media_storage_dir'));
        parent::tearDown();
    }

    public function testAnonymousCannotReadMediaFiles(): void
    {
        $media = $this->storeMedia($this->fixtures->png());

        $this->client->request('GET', '/media/'.$media->getId().'/file');
        self::assertResponseRedirects('/login');

        $this->client->request('GET', '/media/'.$media->getId().'/thumb/small');
        self::assertResponseRedirects('/login');
    }

    public function testMemberCanReadPhotoAndThumbnails(): void
    {
        $media = $this->storeMedia($this->fixtures->png());
        $this->client->loginUser($this->createUser('mamy@example.com'));

        $this->client->request('GET', '/media/'.$media->getId().'/file');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/png');
        self::assertStringContainsString('private', (string) $this->client->getResponse()->headers->get('Cache-Control'));
        self::assertStringStartsWith('inline', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $this->client->request('GET', '/media/'.$media->getId().'/file?download=1');
        self::assertStringStartsWith('attachment', (string) $this->client->getResponse()->headers->get('Content-Disposition'));

        $this->client->request('GET', '/media/'.$media->getId().'/thumb/small');
        self::assertResponseIsSuccessful();
        self::assertResponseHeaderSame('Content-Type', 'image/webp');
    }

    public function testVideoSupportsRangeRequests(): void
    {
        $media = $this->storeMedia($this->fixtures->mp4WithLocation());
        $this->client->loginUser($this->createUser('mamy@example.com'));

        $this->client->request('GET', '/media/'.$media->getId().'/file', server: ['HTTP_RANGE' => 'bytes=0-99']);

        self::assertResponseStatusCodeSame(206);
        self::assertResponseHeaderSame('Content-Length', '100');
    }

    public function testInactiveMemberCannotReadMedia(): void
    {
        $media = $this->storeMedia($this->fixtures->png());
        $this->client->loginUser($this->createUser('ancien@example.com', active: false));

        // Déconnecté par DeactivatedUserSubscriber avant même le contrôle de MediaVoter
        $this->client->request('GET', '/media/'.$media->getId().'/file');

        self::assertResponseRedirects('/login');
    }

    public function testUnknownMediaAndMissingThumbnailReturn404(): void
    {
        $audio = $this->storeMedia($this->fixtures->wav());
        $this->client->loginUser($this->createUser('mamy@example.com'));

        $this->client->request('GET', '/media/999999/file');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/media/'.$audio->getId().'/thumb/small');
        self::assertResponseStatusCodeSame(404);

        $this->client->request('GET', '/media/'.$audio->getId().'/thumb/huge');
        self::assertResponseStatusCodeSame(404);
    }

    public function testAdminUploadsSeveralFilesIntoAnAlbumWithTags(): void
    {
        $admin = $this->createUser('papa@example.com', admin: true);
        $album = (new Album())->setTitle('Noël')->setSlug('noel');
        $tag = (new Tag())->setName('Famille')->setSlug('famille');
        $this->entityManager->persist($album);
        $this->entityManager->persist($tag);
        $this->entityManager->flush();
        $this->client->loginUser($admin);

        $crawler = $this->client->request('GET', '/admin/media/upload');
        self::assertResponseIsSuccessful();
        $token = $crawler->filter('input[name="media_upload[_token]"]')->attr('value');

        $this->client->request('POST', '/admin/media/upload', [
            'media_upload' => ['album' => $album->getId(), 'tags' => [$tag->getId()], '_token' => $token],
        ], [
            'media_upload' => ['files' => [
                new UploadedFile($this->fixtures->jpegWithGps(), 'sapin.jpg', test: true),
                new UploadedFile($this->fixtures->wav(), 'chanson.wav', test: true),
                new UploadedFile($this->fixtures->textDisguisedAsJpeg(), '<img src=x onerror=alert(1)>.jpg', test: true),
            ]],
        ]);

        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', '2 médias ajoutés');
        self::assertSelectorTextContains('.alert-danger', 'Type de fichier non pris en charge');
        // Le nom de fichier fourni par le navigateur est échappé (EasyAdmin affiche les flash en |raw)
        self::assertStringNotContainsString('<img src=x', (string) $this->client->getResponse()->getContent());
        self::assertSelectorTextContains('.alert-danger', '<img src=x onerror=alert(1)>.jpg');

        $this->entityManager->clear();
        $medias = $this->entityManager->getRepository(Media::class)->findBy([], ['id' => 'ASC']);
        self::assertCount(2, $medias);
        self::assertSame([MediaType::Photo, MediaType::Audio], array_map(static fn (Media $m) => $m->getType(), $medias));
        self::assertSame('noel', $medias[0]->getAlbum()?->getSlug());
        self::assertSame('famille', $medias[0]->getTags()->first()->getSlug());
        self::assertSame('papa@example.com', $medias[0]->getUploadedBy()?->getEmail());

        // Liste, fiche et modification s'affichent
        foreach (['/admin/media', '/admin/media/'.$medias[0]->getId(), '/admin/media/'.$medias[0]->getId().'/edit', '/admin/album/'.$album->getId().'/edit'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
    }

    public function testUploadWithoutFileIsRejected(): void
    {
        $this->client->loginUser($this->createUser('papa@example.com', admin: true));
        $crawler = $this->client->request('GET', '/admin/media/upload');

        $this->client->request('POST', '/admin/media/upload', ['media_upload' => [
            '_token' => $crawler->filter('input[name="media_upload[_token]"]')->attr('value'),
        ]]);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager->getRepository(Media::class)->count());
    }

    public function testMemberCannotUpload(): void
    {
        $this->client->loginUser($this->createUser('mamy@example.com'));

        $this->client->request('GET', '/admin/media/upload');

        self::assertResponseStatusCodeSame(403);
    }

    public function testDeletingMediaRemovesFilesAndAlbumCover(): void
    {
        $media = $this->storeMedia($this->fixtures->png());
        $album = (new Album())->setTitle('Été')->setSlug('ete')->setCoverMedia($media);
        $media->setAlbum($album);
        $this->entityManager->persist($album);
        $this->entityManager->flush();

        $storage = static::getContainer()->get(MediaStorage::class);
        $paths = [$storage->getOriginalPath($media), $storage->getThumbnailPath($media, 'small'), $storage->getThumbnailPath($media, 'large')];
        foreach ($paths as $path) {
            self::assertFileExists($path);
        }

        $this->entityManager->remove($media);
        $this->entityManager->flush();

        foreach ($paths as $path) {
            self::assertFileDoesNotExist($path);
        }
        $this->entityManager->clear();
        self::assertNull($this->entityManager->getRepository(Album::class)->findOneBy(['slug' => 'ete'])->getCoverMedia());
    }

    private function storeMedia(string $path): Media
    {
        $media = static::getContainer()->get(MediaUploader::class)->upload(new File($path), basename($path));
        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }

    private function createUser(string $email, bool $admin = false, bool $active = true): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setDisplayName(strstr($email, '@', true))
            ->setAdmin($admin)
            ->setIsActive($active)
            ->setPassword('inutilisé');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
