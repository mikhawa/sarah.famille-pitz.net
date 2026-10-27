<?php

namespace App\Tests\Controller;

use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\KernelBrowser;
use Symfony\Bundle\FrameworkBundle\Test\WebTestCase;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class SecurityControllerTest extends WebTestCase
{
    private const PASSWORD = 'mot-de-passe-de-test';

    private KernelBrowser $client;
    private EntityManagerInterface $entityManager;

    protected function setUp(): void
    {
        $this->client = static::createClient();
        $this->entityManager = static::getContainer()->get(EntityManagerInterface::class);

        // Base de test vidée avant chaque test
        $this->entityManager->createQuery('DELETE FROM '.User::class)->execute();
    }

    public function testAnonymousIsRedirectedToLogin(): void
    {
        $this->client->request('GET', '/');

        self::assertResponseRedirects('/login');
    }

    public function testLoginPageIsPublicAndNotIndexed(): void
    {
        $this->client->request('GET', '/login');

        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('h1', 'Connexion');
        self::assertResponseHeaderSame('X-Robots-Tag', 'noindex, nofollow');
    }

    public function testMemberCanLogInAndLogOut(): void
    {
        $this->createUser('mamy@example.com', 'Mamy');

        $this->login('mamy@example.com', self::PASSWORD);
        self::assertResponseRedirects('/');

        $this->client->followRedirect();
        self::assertResponseIsSuccessful();
        self::assertSelectorTextContains('header', 'Bonjour Mamy');

        $this->client->request('GET', '/logout');
        $this->client->followRedirect();
        $this->client->request('GET', '/');
        self::assertResponseRedirects('/login');
    }

    public function testWrongPasswordIsRejected(): void
    {
        $this->createUser('mamy@example.com', 'Mamy');

        $this->login('mamy@example.com', 'mauvais-mot-de-passe');
        self::assertResponseRedirects('/login');

        $this->client->followRedirect();
        self::assertSelectorExists('.alert-error');
    }

    public function testInactiveAccountCannotLogIn(): void
    {
        $this->createUser('ancien@example.com', 'Ancien', active: false);

        $this->login('ancien@example.com', self::PASSWORD);
        $this->client->followRedirect();

        self::assertSelectorTextContains('.alert-error', 'Ce compte est désactivé.');
    }

    private function createUser(string $email, string $displayName, bool $active = true): User
    {
        $user = (new User())
            ->setEmail($email)
            ->setDisplayName($displayName)
            ->setIsActive($active);

        $hasher = static::getContainer()->get(UserPasswordHasherInterface::class);
        $user->setPassword($hasher->hashPassword($user, self::PASSWORD));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function login(string $email, string $password): void
    {
        $this->client->request('GET', '/login');
        $this->client->submitForm('Se connecter', [
            '_username' => $email,
            '_password' => $password,
        ]);
    }
}
