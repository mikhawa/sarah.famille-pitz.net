<?php

namespace App\Service;

use App\Entity\Comment;
use App\Entity\User;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeExceptionInterface;

/**
 * E-mail pour chaque nouveau petit mot, à :
 * - la personne qui a envoyé le média (« sur ta photo ») ;
 * - celles qui ont déjà écrit un petit mot sur ce média (la conversation continue) ;
 * - chaque super-administrateur (suivi et modération).
 *
 * Jamais à l'auteur du petit mot ; un seul e-mail par personne (raison la plus proche retenue) ; seulement aux comptes
 * actifs qui voient le média. Un e-mail refusé est journalisé sans bloquer l'envoi du petit mot.
 */
class CommentNotifier
{
    public const REASON_OWNER = 'owner';
    public const REASON_PARTICIPANT = 'participant';
    public const REASON_SUPER_ADMIN = 'super_admin';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    public function notifyNewComment(Comment $comment): void
    {
        foreach ($this->getRecipients($comment) as ['user' => $user, 'reason' => $reason]) {
            $email = (new TemplatedEmail())
                ->to((string) $user->getEmail())
                ->subject(\sprintf('Nouveau petit mot de %s', $comment->getAuthor()?->getDisplayName() ?? 'quelqu\'un'))
                ->htmlTemplate('email/new_comment.html.twig')
                ->context([
                    'user' => $user,
                    'comment' => $comment,
                    'reason' => $reason,
                ]);

            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface|MimeExceptionInterface $e) {
                // Serveur SMTP injoignable, ou adresse invalide (ex. MAILER_FROM mal écrit) : journalisé, la page continue
                $this->logger->error('E-mail « nouveau petit mot » non envoyé', ['comment' => $comment->getId(), 'user' => $user->getId(), 'exception' => $e]);
            }
        }
    }

    /**
     * @return list<array{user: User, reason: string}>
     */
    public function getRecipients(Comment $comment): array
    {
        $media = $comment->getMedia();
        $authorId = $comment->getAuthor()?->getId();

        // Ordre = priorité de la raison : propriétaire, puis participant, puis super-administrateur
        $candidates = [];
        $owner = $media->getUploadedBy();
        if (null !== $owner && $owner->isActive()) {
            $candidates[] = [$owner, self::REASON_OWNER];
        }
        foreach ($this->userRepository->findActiveCommentersOf($media) as $user) {
            $candidates[] = [$user, self::REASON_PARTICIPANT];
        }
        foreach ($this->userRepository->findActive() as $user) {
            if ($user->isSuperAdmin()) {
                $candidates[] = [$user, self::REASON_SUPER_ADMIN];
            }
        }

        $recipients = [];
        foreach ($candidates as [$user, $reason]) {
            $id = $user->getId();
            if ($id === $authorId || isset($recipients[$id]) || !$media->isVisibleTo($user)) {
                continue;
            }
            $recipients[$id] = ['user' => $user, 'reason' => $reason];
        }

        return array_values($recipients);
    }
}
