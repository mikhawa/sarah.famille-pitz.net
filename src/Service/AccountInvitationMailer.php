<?php

namespace App\Service;

use App\Entity\User;
use App\Repository\ResetPasswordRequestRepository;
use Symfony\Bridge\Twig\Mime\TemplatedEmail;
use Symfony\Component\Mailer\MailerInterface;
use SymfonyCasts\Bundle\ResetPassword\ResetPasswordHelperInterface;

/**
 * Envoie à un membre le lien lui permettant de choisir son mot de passe.
 *
 * S'appuie sur le jeton de réinitialisation : un compte créé par l'admin
 * n'a pas de mot de passe utilisable tant que ce lien n'a pas été suivi.
 */
class AccountInvitationMailer
{
    public function __construct(
        private readonly ResetPasswordHelperInterface $resetPasswordHelper,
        private readonly ResetPasswordRequestRepository $resetPasswordRequestRepository,
        private readonly MailerInterface $mailer,
    ) {
    }

    public function invite(User $user): void
    {
        // Annule les liens précédents (et contourne le délai anti-spam du bundle en cas de renvoi)
        $this->resetPasswordRequestRepository->removeRequests($user);

        $resetToken = $this->resetPasswordHelper->generateResetToken($user);

        $email = (new TemplatedEmail())
            ->to((string) $user->getEmail())
            ->subject('Invitation sur le site de Sarah')
            ->htmlTemplate('email/invitation.html.twig')
            ->context([
                'user' => $user,
                'resetToken' => $resetToken,
            ]);

        $this->mailer->send($email);
    }
}
