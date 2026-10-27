<?php

namespace App\Command;

use App\Entity\User;
use App\Repository\UserRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Console\Attribute\AsCommand;
use Symfony\Component\Console\Command\Command;
use Symfony\Component\Console\Input\InputArgument;
use Symfony\Component\Console\Input\InputInterface;
use Symfony\Component\Console\Output\OutputInterface;
use Symfony\Component\Console\Question\Question;
use Symfony\Component\Console\Style\SymfonyStyle;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[AsCommand(
    name: 'app:create-admin',
    description: 'Crée un compte administrateur (ou promeut un compte existant)',
)]
class CreateAdminCommand extends Command
{
    private const MIN_PASSWORD_LENGTH = 12;

    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly UserRepository $userRepository,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly ValidatorInterface $validator,
    ) {
        parent::__construct();
    }

    protected function configure(): void
    {
        $this
            ->addArgument('email', InputArgument::REQUIRED, 'Adresse e-mail de connexion')
            ->addArgument('displayName', InputArgument::OPTIONAL, 'Nom affiché', 'Admin');
    }

    protected function execute(InputInterface $input, OutputInterface $output): int
    {
        $io = new SymfonyStyle($input, $output);
        $email = mb_strtolower(trim($input->getArgument('email')));

        $user = $this->userRepository->findOneBy(['email' => $email]);
        $isNew = null === $user;
        $user ??= new User();

        $user
            ->setEmail($email)
            ->setDisplayName($isNew ? $input->getArgument('displayName') : $user->getDisplayName())
            ->setRoles(['ROLE_ADMIN'])
            ->setIsActive(true);

        // Le mot de passe est demandé de façon masquée pour ne pas apparaître dans l'historique du shell
        $question = (new Question('Mot de passe (12 caractères minimum)'))
            ->setHidden(true)
            ->setHiddenFallback(false)
            ->setMaxAttempts(3)
            ->setValidator(function (?string $value): string {
                if (null === $value || mb_strlen($value) < self::MIN_PASSWORD_LENGTH) {
                    throw new \RuntimeException(\sprintf('Le mot de passe doit contenir au moins %d caractères.', self::MIN_PASSWORD_LENGTH));
                }

                return $value;
            });
        $plainPassword = $io->askQuestion($question);
        $user->setPassword($this->passwordHasher->hashPassword($user, $plainPassword));

        $errors = $this->validator->validate($user);
        if (\count($errors) > 0) {
            foreach ($errors as $error) {
                $io->error($error->getPropertyPath().' : '.$error->getMessage());
            }

            return Command::FAILURE;
        }

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        $io->success($isNew
            ? \sprintf('Administrateur %s créé.', $email)
            : \sprintf('Compte %s promu administrateur et mot de passe mis à jour.', $email));

        return Command::SUCCESS;
    }
}
