<?php

namespace App\Tests\Controller;

use App\Entity\Album;
use App\Entity\Comment;
use App\Entity\Media;
use App\Entity\ResetPasswordRequest;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\MediaType;
use App\Enum\Visibility;
use App\Service\MediaUploader;
use App\Tests\Support\MediaFixtures;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\DomCrawler\Crawler;
use Symfony\Component\Filesystem\Filesystem;
use Symfony\Component\HttpFoundation\File\File;
use Symfony\Component\HttpFoundation\File\UploadedFile;

/**
 * Rôles (membre, administrateur, super-administrateur), visibilité des albums et médias, verrou, envoi par les membres
 */
class PermissionTest extends WebTestCase
{
    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;
    private MediaFixtures $fixtures;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);
        $this->fixtures = new MediaFixtures();

        $this->entityManager->createQuery('UPDATE '.Album::class.' a SET a.coverMedia = NULL')->execute();
        foreach ([Comment::class, Media::class, ResetPasswordRequest::class, User::class, Album::class, Tag::class] as $class) {
            $this->entityManager->createQuery('DELETE FROM '.$class)->execute();
        }
    }

    protected function tearDown(): void
    {
        $this->fixtures->cleanup();
        (new Filesystem())->remove(static::getContainer()->getParameter('app.media_storage_dir'));
        parent::tearDown();
    }

    public function testEachRoleSeesOnlyWhatItsLevelAllows(): void
    {
        $public = $this->createAlbum('ete', Visibility::Public);
        $private = $this->createAlbum('secret', Visibility::Private);
        $superOnly = $this->createAlbum('coffre', Visibility::SuperAdmin);

        $inPublic = $this->createMedia('Plage', $public);
        $privateInPublic = $this->createMedia('Brouillon', $public, Visibility::Private);
        $inPrivate = $this->createMedia('Cadeau', $private);
        // L'album est une barrière : un média public dans un album privé reste caché aux membres
        $publicInPrivate = $this->createMedia('Fuite', $private, Visibility::Public);
        $inSuperOnly = $this->createMedia('Coffre', $superOnly);
        // Sans album ni visibilité propre : privé
        $loose = $this->createMedia('Seule');

        $expected = [
            'membre' => [User::LEVEL_MEMBER, ['ete'], [$inPublic]],
            'admin' => [User::LEVEL_ADMIN, ['ete', 'secret'], [$inPublic, $privateInPublic, $inPrivate, $publicInPrivate, $loose]],
            'super' => [User::LEVEL_SUPER_ADMIN, ['ete', 'secret', 'coffre'], [$inPublic, $privateInPublic, $inPrivate, $publicInPrivate, $inSuperOnly, $loose]],
        ];
        $allMedia = [$inPublic, $privateInPublic, $inPrivate, $publicInPrivate, $inSuperOnly, $loose];

        foreach ($expected as $name => [$level, $albums, $visibleMedia]) {
            $this->client->loginUser($this->createUser($name.'@example.com', $level));

            // Liste des albums
            $crawler = $this->client->request('GET', '/albums');
            $listed = $crawler->filter('.album-card')->each(static fn (Crawler $card) => basename((string) $card->attr('href')));
            self::assertEqualsCanonicalizing($albums, $listed, $name);

            // Pages des albums : 404 si invisible
            foreach (['ete', 'secret', 'coffre'] as $slug) {
                $this->client->request('GET', '/albums/'.$slug);
                self::assertResponseStatusCodeSame(\in_array($slug, $albums, true) ? 200 : 404, $name.' /albums/'.$slug);
            }

            // Fil chronologique et fiches : 404 si invisible
            $crawler = $this->client->request('GET', '/');
            self::assertCount(\count($visibleMedia), $crawler->filter('.grid li'), $name.' fil');
            foreach ($allMedia as $media) {
                $visible = \in_array($media, $visibleMedia, true);
                $this->client->request('GET', '/media/'.$media->getId());
                self::assertResponseStatusCodeSame($visible ? 200 : 404, $name.' '.$media->getTitle());
                $this->client->request('GET', '/media/'.$media->getId().'/comments');
                self::assertResponseStatusCodeSame($visible ? 200 : 404, $name.' petits mots '.$media->getTitle());
            }
        }
    }

    public function testFilesOfAHiddenMediaAreNotServed(): void
    {
        $album = $this->createAlbum('secret', Visibility::Private);
        $media = $this->storeMedia($this->fixtures->png(), $album);
        $this->client->loginUser($this->createUser('mamy@example.com'));

        foreach (['/file', '/thumb/small', '/thumb/large'] as $suffix) {
            $this->client->request('GET', '/media/'.$media->getId().$suffix);
            self::assertResponseStatusCodeSame(404, $suffix);
        }

        // Album rendu public : fichiers servis (entité rechargée : le client réinitialise Doctrine après chaque requête)
        $this->entityManager->find(Album::class, $album->getId())->setVisibility(Visibility::Public);
        $this->entityManager->flush();
        $this->client->request('GET', '/media/'.$media->getId().'/thumb/small');
        self::assertResponseIsSuccessful();
    }

    public function testAdminChoosesPublicOrPrivateButNotSuperAdminOnly(): void
    {
        $album = $this->createAlbum('ete', Visibility::Private);
        $this->client->loginUser($this->createUser('papa@example.com', User::LEVEL_ADMIN));

        $crawler = $this->client->request('GET', '/admin/album/'.$album->getId().'/edit');
        $options = $crawler->filter('select[name="Album[visibility]"] option')->each(static fn (Crawler $o) => $o->text());
        self::assertSame([Visibility::Public->label(), Visibility::Private->label()], array_values(array_filter($options)));
        // Le verrou est réservé au super-administrateur
        self::assertSelectorNotExists('input[name="Album[visibilityLocked]"]');

        $this->postAdminForm('/admin/album/'.$album->getId().'/edit', 'Album', [
            'title' => 'Été',
            'slug' => 'ete',
            'visibility' => $this->optionValue($crawler, 'Album[visibility]', Visibility::Public),
        ]);
        self::assertResponseRedirects();
        $this->entityManager->clear();
        self::assertSame(Visibility::Public, $this->entityManager->find(Album::class, $album->getId())->getVisibility());
    }

    public function testLockedVisibilityCannotBeChangedByAnAdmin(): void
    {
        $album = $this->createAlbum('ete', Visibility::Private, locked: true);
        $this->client->loginUser($this->createUser('papa@example.com', User::LEVEL_ADMIN));

        $crawler = $this->client->request('GET', '/admin/album/'.$album->getId().'/edit');
        self::assertSelectorExists('select[name="Album[visibility]"][disabled]');
        $publicValue = $this->optionValue($crawler, 'Album[visibility]', Visibility::Public);

        // Champ désactivé contourné en postant la valeur quand même : la visibilité d'origine est conservée
        $this->postAdminForm('/admin/album/'.$album->getId().'/edit', 'Album', [
            'title' => 'Été',
            'slug' => 'ete',
            'visibility' => $publicValue,
            'visibilityLocked' => '0',
        ]);
        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Album::class, $album->getId());
        self::assertSame(Visibility::Private, $reloaded->getVisibility());
        self::assertTrue($reloaded->isVisibilityLocked());

        // Le super-administrateur, lui, change la visibilité et retire le verrou
        $this->client->loginUser($this->createUser('mamy@example.com', User::LEVEL_SUPER_ADMIN));
        $crawler = $this->client->request('GET', '/admin/album/'.$album->getId().'/edit');
        $this->postAdminForm('/admin/album/'.$album->getId().'/edit', 'Album', [
            'title' => 'Été',
            'slug' => 'ete',
            'visibility' => $this->optionValue($crawler, 'Album[visibility]', Visibility::SuperAdmin),
        ]);
        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(Album::class, $album->getId());
        self::assertSame(Visibility::SuperAdmin, $reloaded->getVisibility());
        self::assertFalse($reloaded->isVisibilityLocked());
    }

    public function testSuperAdminOnlyContentIsHiddenFromAdminsInTheBackOffice(): void
    {
        $vault = $this->createAlbum('coffre', Visibility::SuperAdmin);
        $this->createAlbum('ete', Visibility::Public);
        $media = $this->createMedia('Coffre', $vault);
        $comment = (new Comment($media, $this->createUser('mamy@example.com')))->setContent('Secret');
        $this->entityManager->persist($comment);
        $this->entityManager->flush();

        $this->client->loginUser($this->createUser('papa@example.com', User::LEVEL_ADMIN));
        $crawler = $this->client->request('GET', '/admin/album');
        self::assertStringNotContainsString('coffre', $crawler->filter('table')->text());
        foreach (['/admin/album/'.$vault->getId().'/edit', '/admin/media/'.$media->getId(), '/admin/comment/'.$comment->getId()] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }
        $crawler = $this->client->request('GET', '/admin/comment');
        self::assertStringNotContainsString('Secret', $crawler->filter('body')->text());

        // Ni dans la liste des albums de la page d'envoi
        $crawler = $this->client->request('GET', '/admin/media/upload?album='.$vault->getId());
        self::assertCount(0, $crawler->filter('select[name="media_upload[album]"] option[selected]'));
        self::assertStringNotContainsString('coffre', $crawler->filter('select[name="media_upload[album]"]')->text());
    }

    public function testAuthorizedMemberUploadsIntoPublicAlbumsOnly(): void
    {
        $public = $this->createAlbum('ete', Visibility::Public);
        $this->createAlbum('secret', Visibility::Private);
        $mamy = $this->createUser('mamy@example.com');
        $this->client->loginUser($mamy);

        // Sans autorisation : pas de bouton, envoi refusé
        $this->client->request('GET', '/albums/ete');
        self::assertSelectorNotExists('a[href="/albums/ete/envoyer"]');
        $this->client->request('GET', '/albums/ete/envoyer');
        self::assertResponseStatusCodeSame(403);

        $mamy = $this->entityManager->find(User::class, $mamy->getId())->setCanUpload(true);
        $this->entityManager->flush();
        $this->client->loginUser($mamy);

        $crawler = $this->client->request('GET', '/albums/ete');
        self::assertCount(1, $crawler->filter('a[href="/albums/ete/envoyer"]'));
        // Album privé : invisible, donc 404
        $this->client->request('GET', '/albums/secret/envoyer');
        self::assertResponseStatusCodeSame(404);

        $crawler = $this->client->request('GET', '/albums/ete/envoyer');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('select[name="media_upload[album]"]');
        $token = $crawler->filter('input[name="media_upload[_token]"]')->attr('value');

        // Envoi par lots (JSON), comme le script assets/admin/media_upload.js
        $this->client->request('POST', '/albums/ete/envoyer', ['media_upload' => ['_token' => $token]], [
            'media_upload' => ['files' => [
                new UploadedFile($this->fixtures->png(), 'plage.png', test: true),
                new UploadedFile($this->fixtures->textDisguisedAsJpeg(), 'faux.jpg', test: true),
            ]],
        ], ['HTTP_ACCEPT' => 'application/json']);
        self::assertResponseIsSuccessful();
        $summary = json_decode((string) $this->client->getResponse()->getContent(), true);
        self::assertSame(1, $summary['saved']);
        self::assertCount(1, $summary['errors']);

        // Rangé dans l'album, « comme l'album » : visible tout de suite par les autres membres
        $this->entityManager->clear();
        $media = $this->entityManager->getRepository(Media::class)->findOneBy([]);
        self::assertSame($public->getId(), $media->getAlbum()?->getId());
        self::assertNull($media->getVisibility());
        self::assertSame('mamy@example.com', $media->getUploadedBy()?->getEmail());

        $this->client->loginUser($this->createUser('papy@example.com'));
        $this->client->request('GET', '/media/'.$media->getId());
        self::assertResponseIsSuccessful();
    }

    public function testUploaderAndAdminsCanDeleteAMediaOthersCannot(): void
    {
        $album = $this->createAlbum('ete', Visibility::Public);
        $mamy = $this->createUser('mamy@example.com');
        $media = $this->createMedia('Plage', $album);
        $media->setUploadedBy($mamy);
        $this->entityManager->flush();

        // Autre membre : pas de bouton, suppression refusée
        $this->client->loginUser($this->createUser('papy@example.com'));
        $crawler = $this->client->request('GET', '/media/'.$media->getId());
        self::assertSelectorNotExists('form.media-delete');
        $this->client->request('POST', '/media/'.$media->getId().'/delete', ['_token' => 'x']);
        self::assertResponseStatusCodeSame(403);

        // Auteur de l'envoi : jeton CSRF exigé, puis suppression
        $this->client->loginUser($mamy);
        $crawler = $this->client->request('GET', '/media/'.$media->getId());
        $this->client->request('POST', '/media/'.$media->getId().'/delete', ['_token' => 'invalide']);
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/media/'.$media->getId().'/delete', [
            '_token' => $crawler->filter('form.media-delete input[name="_token"]')->attr('value'),
        ]);
        self::assertResponseRedirects('/albums/ete', 303);
        self::assertSame(0, $this->entityManager->getRepository(Media::class)->count());

        // Administrateur : n'importe quel média qu'il voit
        $other = $this->createMedia('Autre', $this->entityManager->find(Album::class, $album->getId()));
        $this->client->loginUser($this->createUser('papa@example.com', User::LEVEL_ADMIN));
        $crawler = $this->client->request('GET', '/media/'.$other->getId());
        self::assertSelectorExists('form.media-delete');
    }

    public function testAdminManagesMembersOnlyAndCannotGrantRoles(): void
    {
        $member = $this->createUser('mamy@example.com');
        $otherAdmin = $this->createUser('tonton@example.com', User::LEVEL_ADMIN);
        $this->client->loginUser($this->createUser('papa@example.com', User::LEVEL_ADMIN));

        // Membre : modifiable, droit d'envoi accordé ; pas de champ « Rôle »
        $crawler = $this->client->request('GET', '/admin/user/'.$member->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSelectorNotExists('[name="User[level]"]');
        $this->postAdminForm('/admin/user/'.$member->getId().'/edit', 'User', [
            'displayName' => 'Mamy',
            'email' => 'mamy@example.com',
            'canUpload' => '1',
            'isActive' => '1',
            // Tentative de promotion : champ inexistant pour un administrateur, ignoré
            'level' => (string) User::LEVEL_ADMIN,
        ]);
        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(User::class, $member->getId());
        self::assertTrue($reloaded->canUpload());
        self::assertFalse($reloaded->isAdmin());

        // Compte d'un autre administrateur : ni modification, ni suppression, ni invitation
        $this->client->request('GET', '/admin/user/'.$otherAdmin->getId().'/edit');
        self::assertResponseStatusCodeSame(403);
        $this->client->request('POST', '/admin/user/'.$otherAdmin->getId().'/invite');
        self::assertResponseStatusCodeSame(403);
    }

    public function testSuperAdminPromotesAMember(): void
    {
        $member = $this->createUser('mamy@example.com');
        $this->client->loginUser($this->createUser('papa@example.com', User::LEVEL_SUPER_ADMIN));

        $this->client->request('GET', '/admin/user/'.$member->getId().'/edit');
        self::assertSelectorExists('select[name="User[level]"]');
        $this->postAdminForm('/admin/user/'.$member->getId().'/edit', 'User', [
            'displayName' => 'Mamy',
            'email' => 'mamy@example.com',
            'level' => (string) User::LEVEL_ADMIN,
            'isActive' => '1',
        ]);
        self::assertResponseRedirects();

        $this->entityManager->clear();
        $reloaded = $this->entityManager->find(User::class, $member->getId());
        self::assertTrue($reloaded->isAdmin());
        self::assertFalse($reloaded->isSuperAdmin());
        // Modification d'un média : visibilité « comme l'album » par défaut, verrou disponible
        $media = $this->createMedia('Plage', $this->createAlbum('ete', Visibility::Public));
        $crawler = $this->client->request('GET', '/admin/media/'.$media->getId().'/edit');
        self::assertResponseIsSuccessful();
        self::assertSame('Comme l\'album', $crawler->filter('select[name="Media[visibility]"] option')->first()->text());
        self::assertSelectorExists('input[name="Media[visibilityLocked]"]');
        $this->client->request('GET', '/admin/media');
        self::assertSelectorTextContains('table', 'Comme l\'album (Public');
    }

    /**
     * @param array<string, string> $fields
     */
    private function postAdminForm(string $url, string $formName, array $fields): void
    {
        $crawler = $this->client->request('GET', $url);
        $fields['_token'] = $crawler->filter(\sprintf('input[name="%s[_token]"]', $formName))->attr('value');

        // Les boutons d'EasyAdmin sont hors de la balise <form> : on poste directement
        $this->client->request('POST', $url, [$formName => $fields, 'ea' => ['newForm' => ['btn' => 'saveAndReturn']]]);
    }

    private function optionValue(Crawler $crawler, string $selectName, Visibility $visibility): string
    {
        return (string) $crawler->filter(\sprintf('select[name="%s"] option', $selectName))
            ->reduce(static fn (Crawler $o) => $o->text() === $visibility->label())
            ->attr('value');
    }

    private function createAlbum(string $slug, Visibility $visibility, bool $locked = false): Album
    {
        $album = (new Album())
            ->setTitle(ucfirst($slug))
            ->setSlug($slug)
            ->setVisibility($visibility)
            ->setVisibilityLocked($locked);
        $this->entityManager->persist($album);
        $this->entityManager->flush();

        return $album;
    }

    private function createMedia(string $title, ?Album $album = null, ?Visibility $visibility = null): Media
    {
        $media = (new Media(MediaType::Photo, bin2hex(random_bytes(8)).'.jpg', $title.'.jpg', 'image/jpeg'))
            ->setTitle($title)
            ->setAlbum($album)
            ->setVisibility($visibility);
        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }

    private function storeMedia(string $path, ?Album $album): Media
    {
        $media = static::getContainer()->get(MediaUploader::class)->upload(new File($path), basename($path));
        $media->setAlbum($album);
        $this->entityManager->persist($media);
        $this->entityManager->flush();

        return $media;
    }

    private function createUser(string $email, int $level = User::LEVEL_MEMBER): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setDisplayName(strstr($email, '@', true))
            ->setLevel($level)
            ->setPassword('inutilisé');
        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }
}
