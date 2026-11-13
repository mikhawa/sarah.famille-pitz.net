<?php

namespace App\Controller\Admin;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use App\Enum\Visibility;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

/**
 * Champs « Visibilité » et « Verrouillé » des albums et des médias, et application du verrou côté serveur.
 *
 * - Un administrateur choisit Public ou Privé ; le super-administrateur peut aussi choisir « Super-admin seul ».
 * - Le super-administrateur seul voit et modifie le verrou ; un élément verrouillé garde sa visibilité
 *   quoi que tente un administrateur (champ désactivé, et valeur d'origine remise à l'enregistrement).
 */
trait VisibilityCrudTrait
{
    /**
     * @param bool $inheritable true pour un média : choix vide = « comme l'album »
     */
    private function visibilityFields(bool $inheritable): iterable
    {
        $user = $this->getCurrentAdmin();
        $entity = $this->getContext()?->getEntity()->getInstance();
        $locked = ($entity instanceof Album || $entity instanceof Media) && $entity->isVisibilityLocked();

        yield TextField::new('visibilityLabel', 'Visibilité')->setSortable(false)->hideOnForm();

        $choices = [];
        foreach (Visibility::assignableBy($user) as $visibility) {
            $choices[$visibility->label()] = $visibility;
        }

        $field = ChoiceField::new('visibility', 'Visibilité')
            ->setChoices($choices)
            ->onlyOnForms()
            ->setHelp($locked && !$user->isSuperAdmin()
                ? 'Verrouillée par le super-administrateur.'
                : ($inheritable
                    ? 'Vide : comme l\'album (privé sans album). Un album privé cache tous ses médias, même publics.'
                    : 'Public : toute la famille. Privé : administrateurs seulement.'));
        if ($inheritable) {
            $field->setRequired(false)->setFormTypeOption('placeholder', 'Comme l\'album');
        }
        if ($locked && !$user->isSuperAdmin()) {
            $field->setDisabled();
        }
        yield $field;

        if ($user->isSuperAdmin()) {
            yield BooleanField::new('visibilityLocked', 'Verrouillé')
                ->renderAsSwitch(false)
                ->setHelp('Les administrateurs ne pourront plus changer la visibilité.')
                ->onlyOnForms();
        }
    }

    /**
     * À appeler dans updateEntity() : remet la visibilité d'origine si un administrateur a contourné le verrou
     */
    private function enforceVisibilityLock(EntityManagerInterface $entityManager, Album|Media $entity): void
    {
        if ($this->getCurrentAdmin()->isSuperAdmin()) {
            return;
        }

        $original = $entityManager->getUnitOfWork()->getOriginalEntityData($entity);
        if (($original['visibilityLocked'] ?? false) === true) {
            $entity->setVisibility($original['visibility']);
        }
        $entity->setVisibilityLocked((bool) ($original['visibilityLocked'] ?? false));

        // « Super-admin seul » n'est jamais attribuable par un administrateur
        if (Visibility::SuperAdmin === $entity->getVisibility() && Visibility::SuperAdmin !== ($original['visibility'] ?? null)) {
            $entity->setVisibility($original['visibility'] ?? Visibility::Private);
        }
    }

    private function getCurrentAdmin(): User
    {
        $user = $this->getUser();
        \assert($user instanceof User);

        return $user;
    }
}
