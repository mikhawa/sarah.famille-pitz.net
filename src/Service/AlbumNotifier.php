<?php

namespace App\Service;

use App\Entity\Album;
use App\Entity\User;
use App\Enum\Visibility;
use App\Repository\MediaRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;

/**
 * E-mail « nouvel album » à la famille, déclenché par un administrateur (bouton « Prévenir la famille »).
 *
 * Seulement pour un album public : destinataires = comptes actifs qui voient l'album et au moins un de ses médias,
 * sauf l'expéditeur. Un e-mail par personne : les adresses ne sont jamais montrées aux autres destinataires.
 */
class AlbumNotifier
{
    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * Destinataires et nombre de médias que chacun verra dans l'album
     *
     * @return list<array{user: User, count: int}>
     */
    public function getRecipients(Album $album, User $sender): array
    {
        if (Visibility::Public !== $album->getVisibility()) {
            return [];
        }

        $recipients = [];
        foreach ($this->userRepository->findActive() as $user) {
            if ($user->getId() === $sender->getId() || !$album->isVisibleTo($user)) {
                continue;
            }
            // Mêmes droits que la page de l'album : un album vide (pour cette personne) ne lui est pas annoncé
            $count = \count($this->mediaRepository->findAllByAlbum($user, $album));
            if ($count > 0) {
                $recipients[] = ['user' => $user, 'count' => $count];
            }
        }

        return $recipients;
    }

    /**
     * Envoie l'e-mail à chaque destinataire ; un envoi refusé n'empêche pas les suivants.
     *
     * @return array{sent: list<User>, failed: list<User>}
     */
    public function notify(Album $album, User $sender): array
    {
        $result = ['sent' => [], 'failed' => []];

        foreach ($this->getRecipients($album, $sender) as ['user' => $user, 'count' => $count]) {
            $email = (new TemplatedEmail())
                ->to((string) $user->getEmail())
                ->subject(\sprintf('Nouvel album sur le site de Sarah : %s', $album->getTitle()))
                ->htmlTemplate('email/new_album.html.twig')
                ->context([
                    'user' => $user,
                    'album' => $album,
                    'count' => $count,
                    'sender' => $sender,
                ]);

            try {
                $this->mailer->send($email);
                $result['sent'][] = $user;
            } catch (TransportExceptionInterface $e) {
                $this->logger->error('E-mail « nouvel album » non envoyé', ['album' => $album->getId(), 'user' => $user->getId(), 'exception' => $e]);
                $result['failed'][] = $user;
            }
        }

        if ([] !== $result['sent']) {
            $album->setNotifiedAt(new \DateTimeImmutable());
        }

        return $result;
    }
}
