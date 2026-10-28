<?php

namespace App\Tests\Controller;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\ResetPasswordRequest;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\MediaType;
use App\Repository\MediaRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;

/**
 * Pages du site : les fichiers ne sont pas lus ici, les médias sont créés directement en base
 */
class FrontTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        $this->entityManager->createQuery('UPDATE '.Album::class.' a SET a.coverMedia = NULL')->execute();
        foreach ([Media::class, ResetPasswordRequest::class, User::class, Album::class, Tag::class] as $class) {
            $this->entityManager->createQuery('DELETE FROM '.$class)->execute();
        }
    }

    public function testFeedListsMediaNewestFirstGroupedByMonth(): void
    {
        $this->createMedia(MediaType::Photo, 'Plage', '2025-08-15 10:00');
        $this->createMedia(MediaType::Video, 'Sapin', '2025-12-24 18:00', duration: 75);
        $this->createMedia(MediaType::Audio, 'Comptine', '2025-12-01 09:00', duration: 3725);
        $this->loginMember();

        $crawler = $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSame(['Décembre 2025', 'Août 2025'], $crawler->filter('.month h2')->each(static fn ($h) => $h->text()));
        self::assertSame(['Sapin', 'Comptine', 'Plage'], $crawler->filter('.grid .tile')->each(static fn ($a) => $a->attr('title')));
        self::assertSelectorTextContains('.tile-video .tile-badge', '1:15');
        self::assertSelectorTextContains('.tile-audio .tile-badge', '1:02:05');
        // Pas de miniature pour la musique
        self::assertCount(0, $crawler->filter('.tile-audio img'));
    }

    public function testFeedFiltersByType(): void
    {
        $this->createMedia(MediaType::Photo, 'Plage');
        $this->createMedia(MediaType::Audio, 'Comptine');
        $this->loginMember();

        $crawler = $this->client->request('GET', '/?type=audio');

        self::assertSame(['Comptine'], $crawler->filter('.grid .tile')->each(static fn ($a) => $a->attr('title')));
        self::assertSelectorTextContains('.filters [aria-current]', 'Musique');

        // Type inconnu : filtre ignoré
        $crawler = $this->client->request('GET', '/?type=inconnu');
        self::assertCount(2, $crawler->filter('.grid .tile'));
    }

    public function testFeedIsPaginated(): void
    {
        for ($i = 0; $i < MediaRepository::PER_PAGE + 1; ++$i) {
            $this->createMedia(MediaType::Photo, 'Photo '.$i, \sprintf('2025-01-01 10:%02d', $i));
        }
        $this->loginMember();

        $crawler = $this->client->request('GET', '/');
        self::assertCount(MediaRepository::PER_PAGE, $crawler->filter('.grid .tile'));
        self::assertSelectorTextContains('.pagination', 'Page 1 sur 2');

        $crawler = $this->client->request('GET', '/?page=2');
        self::assertSame(['Photo 0'], $crawler->filter('.grid .tile')->each(static fn ($a) => $a->attr('title')));

        foreach (['/?page=3', '/?page=0', '/?page=abc'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(404, $url);
        }
    }

    public function testEmptyFeedShowsMessage(): void
    {
        $this->loginMember();

        $this->client->request('GET', '/');

        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.empty');
    }

    public function testAlbumListUsesCoverOrFirstPhotoAndListsTags(): void
    {
        $noel = $this->createAlbum('Noël', 'noel', '2025-12-24');
        $ete = $this->createAlbum('Été', 'ete', '2025-07-01');
        $this->createAlbum('Vide', 'vide');
        $tag = (new Tag())->setName('Famille')->setSlug('famille');
        $this->entityManager->persist($tag);

        $this->createMedia(MediaType::Audio, 'Chanson', album: $noel);
        $sapin = $this->createMedia(MediaType::Photo, 'Sapin', album: $noel, tags: [$tag]);
        $plage = $this->createMedia(MediaType::Photo, 'Plage', album: $ete);
        $baignade = $this->createMedia(MediaType::Video, 'Baignade', album: $ete);
        $ete->setCoverMedia($baignade);
        $this->entityManager->flush();
        $this->loginMember();

        $crawler = $this->client->request('GET', '/albums');

        self::assertResponseIsSuccessful();
        // Album vide créé en dernier : sans date d'événement, classé à sa date de création
        self::assertSame(['Vide', 'Noël', 'Été'], $crawler->filter('.album-title')->each(static fn ($t) => $t->text()));
        $covers = $crawler->filter('.album-card img')->each(static fn ($img) => $img->attr('src'));
        self::assertSame(['/media/'.$sapin->getId().'/thumb/small', '/media/'.$baignade->getId().'/thumb/small'], $covers);
        self::assertSelectorTextContains('.albums li:nth-child(2) .album-meta', '2 souvenirs');
        self::assertSelectorTextContains('.tags', 'Famille 1');
        self::assertNotSame($plage->getId(), $baignade->getId());
    }

    public function testAlbumPageListsOnlyItsMediaOldestFirst(): void
    {
        $noel = $this->createAlbum('Noël', 'noel', '2025-12-24', description: "Chez Mamy\n<script>alert(1)</script>");
        $this->createMedia(MediaType::Photo, 'Cadeaux', '2025-12-25 09:00', album: $noel);
        $this->createMedia(MediaType::Photo, 'Sapin', '2025-12-24 18:00', album: $noel);
        $this->createMedia(MediaType::Photo, 'Ailleurs');
        $this->loginMember();

        $crawler = $this->client->request('GET', '/albums/noel');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Noël');
        self::assertSame(['Sapin', 'Cadeaux'], $crawler->filter('.grid .tile')->each(static fn ($a) => $a->attr('title')));
        self::assertStringNotContainsString('<script>alert(1)', (string) $this->client->getResponse()->getContent());

        $this->client->request('GET', '/albums/inconnu');
        self::assertResponseStatusCodeSame(404);
    }

    public function testTagPageListsTaggedMedia(): void
    {
        $tag = (new Tag())->setName('Anniversaire')->setSlug('anniversaire');
        $this->entityManager->persist($tag);
        $this->createMedia(MediaType::Photo, 'Gâteau', tags: [$tag]);
        $this->createMedia(MediaType::Photo, 'Autre');
        $this->loginMember();

        $crawler = $this->client->request('GET', '/tags/anniversaire');

        self::assertResponseIsSuccessful();
        self::assertSame(['Gâteau'], $crawler->filter('.grid .tile')->each(static fn ($a) => $a->attr('title')));

        $this->client->request('GET', '/tags/inconnu');
        self::assertResponseStatusCodeSame(404);
    }

    public function testMediaPageShowsTheRightPlayer(): void
    {
        $photo = $this->createMedia(MediaType::Photo, 'Plage');
        $video = $this->createMedia(MediaType::Video, 'Sapin', mimeType: 'video/mp4');
        $audio = $this->createMedia(MediaType::Audio, 'Comptine', mimeType: 'audio/mpeg');
        $this->loginMember();

        $this->client->request('GET', '/media/'.$photo->getId());
        self::assertResponseIsSuccessful();
        self::assertSelectorExists('.player img[src="/media/'.$photo->getId().'/thumb/large"]');
        self::assertSelectorExists('a[href="/media/'.$photo->getId().'/file?download=1"]');

        $this->client->request('GET', '/media/'.$video->getId());
        self::assertSelectorExists('video[poster="/media/'.$video->getId().'/thumb/large"] source[type="video/mp4"]');

        $this->client->request('GET', '/media/'.$audio->getId());
        self::assertSelectorExists('audio[src="/media/'.$audio->getId().'/file"]');

        $this->client->request('GET', '/media/999999');
        self::assertResponseStatusCodeSame(404);
    }

    public function testPhotosOpenInTheLightbox(): void
    {
        $large = $this->createMedia(MediaType::Photo, 'Grande', '2025-08-03 10:00');
        $large->setDimensions(4000, 3000);
        $small = $this->createMedia(MediaType::Photo, 'Petite', '2025-08-02 10:00');
        $small->setDimensions(800, 600);
        $gif = $this->createMedia(MediaType::Photo, 'Animée', '2025-08-01 10:00', mimeType: 'image/gif');
        $gif->setDimensions(320, 240);
        $unknown = $this->createMedia(MediaType::Photo, 'Sans dimensions', '2025-07-31 10:00');
        $video = $this->createMedia(MediaType::Video, 'Vidéo', '2025-07-30 10:00', mimeType: 'video/mp4');
        $video->setDimensions(1920, 1080);
        $this->entityManager->flush();
        $this->loginMember();

        $crawler = $this->client->request('GET', '/');
        self::assertCount(1, $crawler->filter('[data-controller="lightbox"]'));
        $tile = static fn (Media $m) => $crawler->filter('a.tile[href="/media/'.$m->getId().'"]');

        // Grande miniature réduite à 1600 px de large, hauteur proportionnelle ; le lien reste la fiche
        self::assertSame('/media/'.$large->getId().'/thumb/large', $tile($large)->attr('data-pswp-src'));
        self::assertSame(['1600', '1200'], [$tile($large)->attr('data-pswp-width'), $tile($large)->attr('data-pswp-height')]);
        // Jamais agrandie
        self::assertSame(['800', '600'], [$tile($small)->attr('data-pswp-width'), $tile($small)->attr('data-pswp-height')]);
        // GIF : l'original, pour garder l'animation
        self::assertSame('/media/'.$gif->getId().'/file', $tile($gif)->attr('data-pswp-src'));
        // Dimensions inconnues ou vidéo : simple lien vers la fiche
        self::assertNull($tile($unknown)->attr('data-pswp-src'));
        self::assertNull($tile($video)->attr('data-pswp-src'));

        $crawler = $this->client->request('GET', '/media/'.$large->getId());
        $photo = $crawler->filter('.player[data-controller="lightbox"] a[data-lightbox-current]');
        self::assertSame('/media/'.$large->getId().'/file', $photo->attr('href'));
        self::assertSame('/media/'.$large->getId().'/thumb/large', $photo->attr('data-pswp-src'));
    }

    public function testMediaNavigationFollowsTheListOrder(): void
    {
        $album = $this->createAlbum('Noël', 'noel');
        $first = $this->createMedia(MediaType::Photo, 'Premier', '2025-12-24 10:00', album: $album);
        $second = $this->createMedia(MediaType::Photo, 'Deuxième', '2025-12-24 10:00', album: $album);
        $third = $this->createMedia(MediaType::Photo, 'Troisième', '2025-12-24 11:00', album: $album);
        $outside = $this->createMedia(MediaType::Photo, 'Hors album', '2025-12-24 10:30');
        $this->loginMember();

        // Dans un album : du plus ancien au plus récent, sans sortir de l'album
        $crawler = $this->client->request('GET', '/media/'.$second->getId());
        self::assertSame('/media/'.$first->getId(), $crawler->filter('a[rel="prev"]')->attr('href'));
        self::assertSame('/media/'.$third->getId(), $crawler->filter('a[rel="next"]')->attr('href'));
        self::assertSelectorTextContains('.media-back', 'Noël');

        $crawler = $this->client->request('GET', '/media/'.$first->getId());
        self::assertCount(0, $crawler->filter('a[rel="prev"]'));

        // Hors album : ordre du fil (du plus récent au plus ancien), sur tous les médias
        $crawler = $this->client->request('GET', '/media/'.$outside->getId());
        self::assertSame('/media/'.$third->getId(), $crawler->filter('a[rel="prev"]')->attr('href'));
        self::assertSame('/media/'.$second->getId(), $crawler->filter('a[rel="next"]')->attr('href'));
    }

    public function testDeactivatedMemberIsLoggedOut(): void
    {
        $media = $this->createMedia(MediaType::Photo, 'Plage');
        $user = $this->loginMember();
        $this->client->request('GET', '/');
        self::assertResponseIsSuccessful();

        // Compte désactivé par l'admin pendant que le membre est connecté
        $user->setIsActive(false);
        $this->entityManager->flush();

        $this->client->request('GET', '/media/'.$media->getId());
        self::assertResponseRedirects('/login');
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testAnonymousIsRedirectedFromEveryPage(): void
    {
        $media = $this->createMedia(MediaType::Photo, 'Plage');

        foreach (['/', '/albums', '/albums/noel', '/tags/famille', '/media/'.$media->getId()] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseRedirects('/login', message: $url);
        }
    }

    public function testAdminLinkOnlyForAdmins(): void
    {
        $this->loginMember();
        $this->client->request('GET', '/');
        self::assertSelectorNotExists('a[href="/admin"]');

        $this->client->loginUser($this->createUser('papa@example.com', admin: true));
        $this->client->request('GET', '/');
        self::assertSelectorExists('a[href="/admin"]');
    }

    /**
     * @param list<Tag> $tags
     */
    private function createMedia(
        MediaType $type,
        string $title,
        ?string $takenAt = null,
        ?Album $album = null,
        array $tags = [],
        ?int $duration = null,
        string $mimeType = 'image/jpeg',
    ): Media {
        $media = (new Media($type, bin2hex(random_bytes(8)).'.bin', $title.'.bin', $mimeType))
            ->setTitle($title)
            ->setTakenAt(null !== $takenAt ? new \DateTimeImmutable($takenAt) : null)
            ->setAlbum($album)
            ->setDuration($duration);
        foreach ($tags as $tag) {
            $media->addTag($tag);
        }
        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }

    private function createAlbum(string $title, string $slug, ?string $eventDate = null, ?string $description = null): Album
    {
        $album = (new Album())
            ->setTitle($title)
            ->setSlug($slug)
            ->setDescription($description)
            ->setEventDate(null !== $eventDate ? new \DateTimeImmutable($eventDate) : null);
        $this->entityManager->persist($album);
        $this->entityManager->flush();

        return $album;
    }

    private function loginMember(): User
    {
        $user = $this->createUser('mamy@example.com');
        $this->client->loginUser($user);

        return $user;
    }

    private function createUser(string $email, bool $admin = false): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setDisplayName(strstr($email, '@', true))
            ->setAdmin($admin)
            ->setPassword('inutilisé');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
