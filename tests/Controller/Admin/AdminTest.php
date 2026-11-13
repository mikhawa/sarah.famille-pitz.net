<?php

namespace App\Tests\Controller\Admin;

use App\Entity\Album;
use App\Entity\ResetPasswordRequest;
use App\Entity\Tag;
use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\Mime\Email;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class AdminTest extends WebTestCase
{
    private const PASSWORD = 'mot-de-passe-de-test';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        foreach ([ResetPasswordRequest::class, User::class, Album::class, Tag::class] as $class) {
            $this->entityManager->createQuery('DELETE FROM '.$class)->execute();
        }
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/admin');

        self::assertResponseRedirects('/login');
    }

    public function testMemberIsForbidden(): void
    {
        $this->client->loginUser($this->createUser('papy@example.com', 'Papy'));

        foreach (['/admin', '/admin/user', '/admin/album', '/admin/tag'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseStatusCodeSame(403, $url);
        }
    }

    public function testAdminCanBrowseAllSections(): void
    {
        $this->client->loginUser($this->createUser('papa@example.com', 'Papa', admin: true));

        foreach (['/admin', '/admin/user', '/admin/album', '/admin/tag', '/admin/user/new', '/admin/album/new', '/admin/tag/new'] as $url) {
            $this->client->request('GET', $url);
            self::assertResponseIsSuccessful($url);
        }
    }

    public function testAdminUsesTheSiteTheme(): void
    {
        $this->client->loginUser($this->createUser('papa@example.com', 'Papa', admin: true));

        $crawler = $this->client->request('GET', '/admin');
        self::assertSelectorTextContains('.ea-sidebar-logo', 'Sarah');
        // Toujours en clair, comme le site
        self::assertSame('false', $crawler->filter('body')->attr('data-ea-dark-scheme-is-enabled'));

        $stylesheet = $crawler->filter('link[rel="stylesheet"][href*="/assets/styles/admin-"]');
        self::assertCount(1, $stylesheet);
        $this->client->request('GET', $stylesheet->attr('href'));
        self::assertResponseIsSuccessful();
        self::assertStringContainsString('--ea-primary', (string) $this->client->getInternalResponse()->getContent());
    }

    public function testCreatingAMemberSendsAnInvitationThatLetsThemChooseAPassword(): void
    {
        $this->client->loginUser($this->createUser('papa@example.com', 'Papa', admin: true));

        $this->submitNewForm('/admin/user/new', 'User', [
            'displayName' => 'Mamy',
            'email' => 'Mamy@Example.com',
            'isActive' => '1',
        ]);
        self::assertResponseRedirects();

        $mamy = $this->findUser('mamy@example.com');
        self::assertNotNull($mamy);
        self::assertFalse($mamy->isAdmin());

        self::assertEmailCount(1);
        $email = self::getMailerMessage();
        self::assertEmailAddressContains($email, 'To', 'mamy@example.com');
        self::assertEmailAddressContains($email, 'From', 'noreply@sarah.famille-pitz.net');
        self::assertEmailHtmlBodyContains($email, 'Bonjour Mamy');
        $link = $this->extractResetLink($email);

        // Le membre suit le lien depuis un navigateur non connecté
        $this->client->request('GET', '/logout');
        $this->client->request('GET', $link);
        $this->client->followRedirect();
        self::assertSelectorTextContains('h1', 'Choisir votre mot de passe');

        $newPassword = 'Une phrase de passe tres solide 2026 !';
        $this->client->submitForm('Enregistrer', [
            'change_password_form[plainPassword][first]' => $newPassword,
            'change_password_form[plainPassword][second]' => $newPassword,
        ]);
        self::assertResponseRedirects('/login');
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-success', 'mot de passe est enregistré');

        $this->client->submitForm('Se connecter', ['_username' => 'mamy@example.com', '_password' => $newPassword]);
        self::assertResponseRedirects('/');
        $this->client->followRedirect();
        self::assertSelectorTextContains('header', 'Bonjour Mamy');

        // Un lien ne sert qu'une fois
        $this->client->request('GET', '/logout');
        $this->client->request('GET', $link);
        $this->client->followRedirect();
        self::assertResponseRedirects('/reset-password');
    }

    public function testAdminCanResendInvitation(): void
    {
        $this->client->loginUser($this->createUser('papa@example.com', 'Papa', admin: true));
        $mamy = $this->createUser('mamy@example.com', 'Mamy');

        // Deux envois de suite : le délai anti-spam du bundle ne doit pas bloquer l'admin
        foreach ([1, 2] as $_) {
            $this->client->request('POST', '/admin/user/'.$mamy->getId().'/invite');
            self::assertResponseRedirects();
            self::assertEmailCount(1);
        }

        self::assertSame(1, $this->entityManager->getRepository(ResetPasswordRequest::class)->count());
    }

    public function testInvitationCannotBeTriggeredByGet(): void
    {
        $this->client->loginUser($this->createUser('papa@example.com', 'Papa', admin: true));
        $mamy = $this->createUser('mamy@example.com', 'Mamy');

        $this->client->request('GET', '/admin/user/'.$mamy->getId().'/invite');

        self::assertResponseStatusCodeSame(405);
        self::assertEmailCount(0);
    }

    public function testNobodyCanLowerTheirOwnRoleOrDeactivateThemself(): void
    {
        // Administrateur : pas de champ « Rôle » ; il tente de désactiver son propre compte
        $papa = $this->createUser('papa@example.com', 'Papa', admin: true);
        $this->client->loginUser($papa);
        $this->postEditForm('/admin/user/'.$papa->getId().'/edit', 'User', [
            'displayName' => 'Papa',
            'email' => 'papa@example.com',
            // case « Actif » décochée
        ]);
        self::assertResponseRedirects();
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'désactiver votre propre compte');

        // Super-administrateur : il tente de se rétrograder membre
        $mamy = $this->createUser('mamy@example.com', 'Mamy');
        $mamy->setLevel(User::LEVEL_SUPER_ADMIN);
        $this->entityManager->flush();
        $this->client->loginUser($mamy);
        $this->postEditForm('/admin/user/'.$mamy->getId().'/edit', 'User', [
            'displayName' => 'Mamy',
            'email' => 'mamy@example.com',
            'level' => (string) User::LEVEL_MEMBER,
            'isActive' => '1',
        ]);
        $this->client->followRedirect();
        self::assertSelectorTextContains('.alert-danger', 'baisser votre propre rôle');

        $this->entityManager->clear();
        self::assertTrue($this->findUser('papa@example.com')?->isActive());
        self::assertTrue($this->findUser('mamy@example.com')?->isSuperAdmin());
    }

    public function testAdminCanCreateAlbumAndTag(): void
    {
        $this->client->loginUser($this->createUser('papa@example.com', 'Papa', admin: true));

        $this->submitNewForm('/admin/album/new', 'Album', [
            'title' => 'Vacances à la mer',
            'slug' => 'vacances-a-la-mer',
            'eventDate' => '2026-07-14',
            'description' => 'Une semaine au soleil',
        ]);
        self::assertResponseRedirects();

        $this->submitNewForm('/admin/tag/new', 'Tag', ['name' => 'Plage', 'slug' => 'plage']);
        self::assertResponseRedirects();

        $album = $this->entityManager->getRepository(Album::class)->findOneBy(['slug' => 'vacances-a-la-mer']);
        self::assertSame('Vacances à la mer', $album?->getTitle());
        self::assertSame('2026-07-14', $album->getEventDate()?->format('Y-m-d'));
        self::assertNotNull($this->entityManager->getRepository(Tag::class)->findOneBy(['slug' => 'plage']));
    }

    public function testInvalidSlugIsRejected(): void
    {
        $this->client->loginUser($this->createUser('papa@example.com', 'Papa', admin: true));

        $this->submitNewForm('/admin/tag/new', 'Tag', ['name' => 'Plage', 'slug' => 'Pas Valide!']);

        self::assertResponseStatusCodeSame(422);
        self::assertSame(0, $this->entityManager->getRepository(Tag::class)->count());
    }

    public function testForgotPasswordDoesNotRevealUnknownOrInactiveAccounts(): void
    {
        $this->createUser('ancien@example.com', 'Ancien', active: false);

        foreach (['inconnu@example.com', 'ancien@example.com'] as $email) {
            $this->client->request('GET', '/reset-password');
            $this->client->submitForm('Envoyer le lien', ['reset_password_request_form[email]' => $email]);
            self::assertResponseRedirects('/reset-password/check-email');
        }

        self::assertEmailCount(0);
    }

    /**
     * @param array<string, string> $fields
     */
    private function submitNewForm(string $url, string $formName, array $fields): void
    {
        $crawler = $this->client->request('GET', $url);
        $fields['_token'] = $crawler->filter(\sprintf('input[name="%s[_token]"]', $formName))->attr('value');

        // Les boutons d'EasyAdmin sont hors de la balise <form> : on poste directement
        $this->client->request('POST', $url, [$formName => $fields, 'ea' => ['newForm' => ['btn' => 'saveAndReturn']]]);
    }

    /**
     * @param array<string, string> $fields
     */
    private function postEditForm(string $url, string $formName, array $fields): void
    {
        $crawler = $this->client->request('GET', $url);
        $fields['_token'] = $crawler->filter(\sprintf('input[name="%s[_token]"]', $formName))->attr('value');

        $this->client->request('POST', $url, [$formName => $fields, 'ea' => ['newForm' => ['btn' => 'saveAndReturn']]]);
    }

    private function createUser(string $email, string $displayName, bool $admin = false, bool $active = true): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setDisplayName($displayName)
            ->setAdmin($admin)
            ->setIsActive($active);
        $user->setPassword(static::getContainer()->get(UserPasswordHasherInterface::class)->hashPassword($user, self::PASSWORD));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function findUser(string $email): ?User
    {
        return static::getContainer()->get(UserRepository::class)->findOneBy(['email' => $email]);
    }

    private function extractResetLink(Email $email): string
    {
        preg_match('#href="https?://[^/"]+(/reset-password/reset/[^"]+)"#', (string) $email->getHtmlBody(), $matches);
        self::assertNotEmpty($matches, 'Lien de réinitialisation introuvable dans l\'e-mail.');

        return $matches[1];
    }
}
