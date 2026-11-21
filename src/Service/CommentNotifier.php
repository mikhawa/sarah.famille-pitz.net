<?php

namespace App\Service;

use App\Entity\Comment;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeExceptionInterface;

/**
 * Prévient chaque super-administrateur actif par e-mail quand un petit mot est posté (sauf s'il en est l'auteur).
 *
 * Un e-mail refusé par le serveur est noté dans le journal sans bloquer l'envoi du petit mot.
 */
class CommentNotifier
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notifyNewComment(Comment $comment): void
    {
        $author = $comment->getAuthor();

        foreach ($this->userRepository->findActive() as $user) {
            if (!$user->isSuperAdmin() || $user->getId() === $author?->getId()) {
                continue;
            }

            $email = (new TemplatedEmail())
                ->to((string) $user->getEmail())
                ->subject(\sprintf('Nouveau petit mot de %s', $author?->getDisplayName() ?? 'quelqu\'un'))
                ->htmlTemplate('email/new_comment.html.twig')
                ->context([
                    'user' => $user,
                    'comment' => $comment,
                ]);

            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface|MimeExceptionInterface $e) {
                // Serveur SMTP injoignable, ou adresse invalide (ex. MAILER_FROM mal écrit) : journalisé, la page continue
                $this->logger->error('E-mail « nouveau petit mot » non envoyé', ['comment' => $comment->getId(), 'user' => $user->getId(), 'exception' => $e]);
            }
        }
    }
}
