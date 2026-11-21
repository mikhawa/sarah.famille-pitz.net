<?php

namespace App\Tests\Service;

use App\Entity\Comment;
use App\Entity\Media;
use App\Entity\User;
use App\Enum\MediaType;
use App\Enum\Visibility;
use App\Repository\MediaRepository;
use App\Repository\UserRepository;
use App\Service\CommentNotifier;
use App\Service\UploadNotifier;
use PHPUnit\Framework\TestCase;
use Psr\Log\LoggerInterface;
use Symfony\Component\Mailer\Envelope;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Exception\RfcComplianceException;
use Symfony\Component\Mime\RawMessage;

/**
 * Une adresse refusée (ex. MAILER_FROM écrit « admin%40exemple.com ») est journalisée sans faire échouer la page
 * (envoi de photos, petit mot) : erreur 500 vue en prod le 2026-11-21.
 */
class NotifierMailFailureTest extends TestCase
{
    public function testInvalidAddressIsLoggedNotThrown(): void
    {
        $admin = $this->user(1, User::LEVEL_SUPER_ADMIN);
        $member = $this->user(2, User::LEVEL_MEMBER);

        $users = $this->createStub(UserRepository::class);
        $users->method('findActive')->willReturn([$admin, $member]);

        $mailer = new class implements MailerInterface {
            public function send(RawMessage $message, ?Envelope $envelope = null): void
            {
                throw new RfcComplianceException('Email "admin%40exemple.com" does not comply with addr-spec of RFC 2822.');
            }
        };

        $logger = $this->createMock(LoggerInterface::class);
        $logger->expects(self::exactly(2))->method('error');

        $media = (new Media(MediaType::Photo, 'a.jpg', 'a.jpg', 'image/jpeg'))->setVisibility(Visibility::Public);

        (new UploadNotifier($users, $this->createStub(MediaRepository::class), $mailer, $logger))->notify($member, [$media]);
        (new CommentNotifier($users, $mailer, $logger))->notifyNewComment((new Comment($media, $member))->setContent('Coucou'));
    }

    private function user(int $id, int $level): User
    {
        $user = (new User())->setEmail("u$id@example.com")->setDisplayName("U$id")->setLevel($level)->setPassword('x');
        (new \ReflectionProperty(User::class, 'id'))->setValue($user, $id);

        return $user;
    }
}
