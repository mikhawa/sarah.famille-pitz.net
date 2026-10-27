<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Service\AccountInvitationMailer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\EmailField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class UserCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly AccountInvitationMailer $invitationMailer,
        private readonly UserPasswordHasherInterface $passwordHasher,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return User::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Compte')
            ->setEntityLabelInPlural('Comptes')
            ->setDefaultSort(['displayName' => 'ASC'])
            ->setSearchFields(['displayName', 'email'])
            ->setHelp(Crud::PAGE_NEW, 'Un e-mail d\'invitation est envoyé automatiquement : le membre y choisit son mot de passe.');
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('displayName', 'Nom affiché')
            ->setHelp('Par exemple « Mamy », « Parrain »…');
        yield EmailField::new('email', 'Adresse e-mail');
        yield BooleanField::new('admin', 'Administrateur')
            ->renderAsSwitch(false)
            ->setSortable(false);
        yield BooleanField::new('isActive', 'Actif')
            ->renderAsSwitch(false)
            ->setHelp('Un compte inactif ne peut plus se connecter.');
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $invite = Action::new('invite', 'Envoyer l\'invitation', 'fa fa-envelope')
            ->linkToCrudAction('invite')
            // POST : l'envoi d'un e-mail ne doit pas être déclenchable par un simple lien
            ->renderAsForm()
            ->displayIf(static fn (User $user): bool => $user->isActive());

        $isNotCurrentUser = fn (User $user): bool => !$this->isCurrentUser($user);

        return $actions
            ->add(Crud::PAGE_INDEX, $invite)
            ->add(Crud::PAGE_DETAIL, $invite)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            // L'admin ne peut pas supprimer son propre compte
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $a) => $a->displayIf($isNotCurrentUser))
            ->update(Crud::PAGE_DETAIL, Action::DELETE, static fn (Action $a) => $a->displayIf($isNotCurrentUser));
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var User $entityInstance */
        // Mot de passe aléatoire inconnu de tous : le membre choisit le sien via l'invitation
        $entityInstance->setPassword($this->passwordHasher->hashPassword($entityInstance, bin2hex(random_bytes(32))));

        parent::persistEntity($entityManager, $entityInstance);

        if ($entityInstance->isActive()) {
            $this->invitationMailer->invite($entityInstance);
            $this->addFlash('success', \sprintf('Invitation envoyée à %s.', $entityInstance->getEmail()));
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var User $entityInstance */
        // Empêche l'admin connecté de se retirer ses propres droits ou de se bloquer
        if ($this->isCurrentUser($entityInstance) && (!$entityInstance->isAdmin() || !$entityInstance->isActive())) {
            $entityManager->refresh($entityInstance);
            $this->addFlash('danger', 'Vous ne pouvez pas retirer vos droits d\'administrateur ni désactiver votre propre compte.');

            return;
        }

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function deleteEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var User $entityInstance */
        // Couvre aussi la suppression groupée, où le bouton ne peut pas être masqué ligne par ligne
        if ($this->isCurrentUser($entityInstance)) {
            $this->addFlash('danger', 'Vous ne pouvez pas supprimer votre propre compte.');

            return;
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    #[AdminRoute(path: '/{entityId}/invite', name: 'invite', options: ['methods' => ['POST']])]
    public function invite(AdminContext $context): Response
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();

        if ($user->isActive()) {
            $this->invitationMailer->invite($user);
            $this->addFlash('success', \sprintf('Invitation envoyée à %s.', $user->getEmail()));
        } else {
            $this->addFlash('warning', 'Ce compte est désactivé : réactivez-le avant d\'envoyer une invitation.');
        }

        return $this->redirect($this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->unset('entityId')
            ->generateUrl());
    }

    private function isCurrentUser(User $user): bool
    {
        $current = $this->getUser();

        return $current instanceof User && null !== $user->getId() && $current->getId() === $user->getId();
    }
}
