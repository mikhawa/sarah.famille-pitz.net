<?php

namespace App\Service;

use App\Entity\Media;
use App\Entity\User;
use App\Repository\MediaRepository;
use App\Repository\UserRepository;
use Psr\Log\LoggerInterface;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Mailer\Exception\TransportExceptionInterface;
use Symfony\Component\Mailer\MailerInterface;
use Symfony\Component\Mime\Exception\ExceptionInterface as MimeExceptionInterface;

/**
 * Prévient les administrateurs et super-administrateurs actifs par e-mail quand de nouveaux fichiers sont envoyés
 * (administration ou page d'un album), sauf la personne qui les envoie.
 *
 * Un seul e-mail par envoi : le navigateur découpe la sélection en lots (assets/admin/media_upload.js) qui portent
 * tous le même « upload_id » ; les médias enregistrés sont gardés en session et l'e-mail part avec le dernier lot
 * (« upload_last » = 1). Sans upload_id (envoi sans JavaScript), l'e-mail part tout de suite.
 * Chaque destinataire ne voit compter que les médias qu'il a le droit de voir.
 */
class UploadNotifier
{
    private const SESSION_PREFIX = 'upload_notify.';

    public function __construct(
        private readonly UserRepository $userRepository,
        private readonly MediaRepository $mediaRepository,
        private readonly MailerInterface $mailer,
        private readonly LoggerInterface $logger,
    ) {
    }

    /**
     * @param list<Media> $medias médias enregistrés par cette requête
     */
    public function afterImport(Request $request, User $uploader, array $medias): void
    {
        $uploadId = $request->getPayload()->getString('upload_id');
        if (1 !== preg_match('/^[A-Za-z0-9-]{8,64}$/', $uploadId) || !$request->hasSession()) {
            $this->notify($uploader, $medias);

            return;
        }

        $session = $request->getSession();
        $key = self::SESSION_PREFIX.$uploadId;
        $ids = [...$session->get($key, []), ...array_map(static fn (Media $m) => $m->getId(), $medias)];

        if (!$request->getPayload()->getBoolean('upload_last')) {
            $session->set($key, $ids);

            return;
        }

        $session->remove($key);
        $this->notify($uploader, [] === $ids ? [] : $this->mediaRepository->findBy(['id' => $ids], ['takenAt' => 'ASC']));
    }

    /**
     * @param list<Media> $medias
     */
    public function notify(User $uploader, array $medias): void
    {
        if ([] === $medias) {
            return;
        }

        foreach ($this->userRepository->findActive() as $user) {
            if (!$user->isAdmin() || $user->getId() === $uploader->getId()) {
                continue;
            }

            $visible = array_values(array_filter($medias, static fn (Media $m) => $m->isVisibleTo($user)));
            if ([] === $visible) {
                continue;
            }

            // Regroupement par album (null : sans album)
            $groups = [];
            foreach ($visible as $media) {
                $album = $media->getAlbum();
                $key = $album?->getId() ?? 0;
                $groups[$key] ??= ['album' => $album, 'count' => 0];
                ++$groups[$key]['count'];
            }

            $email = (new TemplatedEmail())
                ->to((string) $user->getEmail())
                ->subject(\sprintf('%s a ajouté %d %s', $uploader->getDisplayName(), \count($visible), \count($visible) > 1 ? 'nouveaux fichiers' : 'nouveau fichier'))
                ->htmlTemplate('email/new_media.html.twig')
                ->context([
                    'user' => $user,
                    'uploader' => $uploader,
                    'total' => \count($visible),
                    'groups' => array_values($groups),
                ]);

            try {
                $this->mailer->send($email);
            } catch (TransportExceptionInterface|MimeExceptionInterface $e) {
                // Serveur SMTP injoignable, ou adresse invalide (ex. MAILER_FROM mal écrit) : journalisé, la page continue
                $this->logger->error('E-mail « nouveaux fichiers » non envoyé', ['user' => $user->getId(), 'exception' => $e]);
            }
        }
    }
}
