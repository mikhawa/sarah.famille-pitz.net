<?php

namespace App\Controller\Admin;

use App\Entity\User;
use App\Security\Voter\UserVoter;
use App\Service\AccountInvitationMailer;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\KeyValueStore;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
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
        $current = $this->getCurrentAdmin();

        yield TextField::new('displayName', 'Nom affiché')
            ->setHelp('Par exemple « Mamy », « Parrain »…');
        yield EmailField::new('email', 'Adresse e-mail');

        yield TextField::new('levelLabel', 'Rôle')->setSortable(false)->hideOnForm();
        // Rôles administrateur et super-administrateur : attribués par le super-administrateur seulement
        if ($current->isSuperAdmin()) {
            yield ChoiceField::new('level', 'Rôle')
                ->setChoices([
                    User::levelLabel(User::LEVEL_MEMBER) => User::LEVEL_MEMBER,
                    User::levelLabel(User::LEVEL_ADMIN) => User::LEVEL_ADMIN,
                    User::levelLabel(User::LEVEL_SUPER_ADMIN) => User::LEVEL_SUPER_ADMIN,
                ])
                ->setHelp('Membre : voit les albums publics et écrit des petits mots. Administrateur : voit les albums privés, gère les contenus et les membres. Super-administrateur : tout, y compris cacher des contenus aux administrateurs.')
                ->onlyOnForms();
        }

        yield BooleanField::new('canUpload', 'Peut envoyer des photos')
            ->renderAsSwitch(false)
            ->setHelp('Le membre peut ajouter des photos, vidéos et musiques dans les albums publics. Les administrateurs le peuvent toujours.');
        yield BooleanField::new('isActive', 'Actif')
            ->renderAsSwitch(false)
            ->setHelp('Un compte inactif ne peut plus se connecter.');
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
    }

    public function configureActions(Actions $actions): Actions
    {
        $canManage = fn (User $user): bool => $this->isGranted(UserVoter::MANAGE, $user);

        $invite = Action::new('invite', 'Envoyer l\'invitation', 'fa fa-envelope')
            ->linkToCrudAction('invite')
            // POST : l'envoi d'un e-mail ne doit pas être déclenchable par un simple lien
            ->renderAsForm()
            ->displayIf(static fn (User $user): bool => $user->isActive() && $canManage($user));

        $canDelete = fn (User $user): bool => !$this->isCurrentUser($user) && $canManage($user);

        return $actions
            ->add(Crud::PAGE_INDEX, $invite)
            ->add(Crud::PAGE_DETAIL, $invite)
            ->add(Crud::PAGE_INDEX, Action::DETAIL)
            // Un administrateur ne gère que les membres (et son propre compte) ; personne ne supprime son propre compte
            ->update(Crud::PAGE_INDEX, Action::EDIT, static fn (Action $a) => $a->displayIf($canManage))
            ->update(Crud::PAGE_DETAIL, Action::EDIT, static fn (Action $a) => $a->displayIf($canManage))
            ->update(Crud::PAGE_INDEX, Action::DELETE, static fn (Action $a) => $a->displayIf($canDelete))
            ->update(Crud::PAGE_DETAIL, Action::DELETE, static fn (Action $a) => $a->displayIf($canDelete));
    }

    // Boutons masqués ci-dessus ; vérifié ici aussi pour un accès direct par URL
    public function edit(AdminContext $context): KeyValueStore|Response
    {
        $this->denyAccessUnlessGranted(UserVoter::MANAGE, $context->getEntity()->getInstance());

        return parent::edit($context);
    }

    public function delete(AdminContext $context): KeyValueStore|Response
    {
        $this->denyAccessUnlessGranted(UserVoter::MANAGE, $context->getEntity()->getInstance());

        return parent::delete($context);
    }

    public function persistEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var User $entityInstance */
        // Seul le super-administrateur crée des administrateurs
        if (!$this->getCurrentAdmin()->isSuperAdmin()) {
            $entityInstance->setLevel(User::LEVEL_MEMBER);
        }
        // Mot de passe aléatoire inconnu de tous : le membre choisit le sien via l'invitation
        $entityInstance->setPassword($this->passwordHasher->hashPassword($entityInstance, bin2hex(random_bytes(32))));

        parent::persistEntity($entityManager, $entityInstance);

        if ($entityInstance->isActive()) {
            $this->invitationMailer->invite($entityInstance);
            $this->addFlash('success', \sprintf('Invitation envoyée à %s.', htmlspecialchars((string) $entityInstance->getEmail())));
        }
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var User $entityInstance */
        $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entityInstance);
        $originalLevel = User::levelFromRoles($original['roles'] ?? []);
        $originalActive = (bool) ($original['isActive'] ?? true);

        $isSelf = $this->isCurrentUser($entityInstance);
        // En modifiant son propre compte, le formulaire modifie aussi le compte connecté (même objet) : rôle d'origine
        $actorIsSuperAdmin = $isSelf ? User::LEVEL_SUPER_ADMIN === $originalLevel : $this->getCurrentAdmin()->isSuperAdmin();

        // Personne ne baisse son propre rôle ni ne désactive son propre compte (on ne s'enferme pas dehors)
        if ($isSelf && ($entityInstance->getLevel() < $originalLevel || ($originalActive && !$entityInstance->isActive()))) {
            $entityManager->refresh($entityInstance);
            $this->addFlash('danger', 'Vous ne pouvez pas baisser votre propre rôle ni désactiver votre propre compte.');

            return;
        }
        // Compte d'un administrateur modifié par un autre administrateur (modification en ligne de la liste comprise)
        if (!$actorIsSuperAdmin && $originalLevel >= User::LEVEL_ADMIN && !$isSelf) {
            $entityManager->refresh($entityInstance);
            $this->addFlash('danger', 'Seul le super-administrateur peut modifier le compte d\'un administrateur.');

            return;
        }
        // Changement de rôle : super-administrateur seulement (pas de champ « Rôle » pour les autres, mais on vérifie)
        if ($entityInstance->getLevel() !== $originalLevel && !$actorIsSuperAdmin) {
            $entityManager->refresh($entityInstance);
            $this->addFlash('danger', 'Seul le super-administrateur peut changer le rôle d\'un compte.');

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
        if (!$this->isGranted(UserVoter::MANAGE, $entityInstance)) {
            $this->addFlash('danger', htmlspecialchars(\sprintf('Seul le super-administrateur peut supprimer le compte de %s.', $entityInstance->getDisplayName())));

            return;
        }

        parent::deleteEntity($entityManager, $entityInstance);
    }

    #[AdminRoute(path: '/{entityId}/invite', name: 'invite', options: ['methods' => ['POST']])]
    public function invite(AdminContext $context): Response
    {
        /** @var User $user */
        $user = $context->getEntity()->getInstance();
        $this->denyAccessUnlessGranted(UserVoter::MANAGE, $user);

        if ($user->isActive()) {
            $this->invitationMailer->invite($user);
            $this->addFlash('success', \sprintf('Invitation envoyée à %s.', htmlspecialchars((string) $user->getEmail())));
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

    private function getCurrentAdmin(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
