<?php

namespace App\Tests\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Console\Application;
use Symfony\Bundle\FrameworkBundle\Test\KernelTestCase;
use Symfony\Component\Console\Tester\CommandTester;

class CreateAdminCommandTest extends KernelTestCase
{
    private CommandTester $tester;

    protected function setUp(): void
    {
        $kernel = self::bootKernel();
        static::getContainer()->get(EntityManagerInterface::class)
            ->createQuery('DELETE FROM '.User::class)->execute();

        $this->tester = new CommandTester((new Application($kernel))->find('app:create-admin'));
    }

    public function testCreatesAdmin(): void
    {
        $this->tester->setInputs(['un-mot-de-passe-solide']);
        $this->tester->execute(['email' => ' Papa@Example.com ', 'displayName' => 'Papa']);

        $this->tester->assertCommandIsSuccessful();
        $user = static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'papa@example.com']);
        self::assertNotNull($user);
        self::assertTrue($user->isAdmin());
        self::assertSame('Papa', $user->getDisplayName());
        self::assertNotSame('un-mot-de-passe-solide', $user->getPassword());
    }

    public function testRejectsShortPassword(): void
    {
        $this->tester->setInputs(['court', 'court', 'court']);

        try {
            $this->tester->execute(['email' => 'papa@example.com']);
            self::fail('Un mot de passe trop court doit être refusé.');
        } catch (\RuntimeException $e) {
            self::assertStringContainsString('au moins 12 caractères', $e->getMessage());
        }

        self::assertNull(static::getContainer()->get(UserRepository::class)->findOneBy(['email' => 'papa@example.com']));
    }
}
