<?php

namespace App\Controller\Admin;

use App\Entity\Album;
use App\Enum\MediaType;
use App\Repository\AlbumRepository;
use App\Repository\MediaRepository;
use App\Security\Voter\AlbumVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;

class AlbumCrudController extends AbstractCrudController
{
    use VisibilityCrudTrait;

    public static function getEntityFqcn(): string
    {
        return Album::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Album')
            ->setEntityLabelInPlural('Albums')
            ->setDefaultSort(['eventDate' => 'DESC', 'createdAt' => 'DESC'])
            ->setSearchFields(['title', 'description'])
            // Fiche, modification, suppression d'un album invisible pour ce compte (« Super-admin seul ») : refusées
            ->setEntityPermission(AlbumVoter::VIEW);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return AlbumRepository::restrictToVisible(parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters), $this->getCurrentAdmin(), 'entity');
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Album $entityInstance */
        $this->enforceVisibilityLock($entityManager, $entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        yield TextField::new('title', 'Titre');
        yield SlugField::new('slug', 'Adresse')
            ->setTargetFieldName('title')
            ->setHelp('Utilisée dans l\'URL de l\'album, générée depuis le titre.')
            ->hideOnIndex();
        yield DateField::new('eventDate', 'Date de l\'événement');
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield from $this->visibilityFields(inheritable: false);

        // Choix parmi les photos de l'album : n'existe donc qu'en modification
        $album = $this->getContext()?->getEntity()->getInstance();
        $viewer = $this->getCurrentAdmin();
        yield AssociationField::new('coverMedia', 'Photo de couverture')
            ->setQueryBuilder(static fn (QueryBuilder $qb) => MediaRepository::restrictToVisible($qb, $viewer, 'entity')
                ->andWhere('entity.type = :photo')
                ->andWhere('entity.album = :album')
                ->setParameter('photo', MediaType::Photo)
                ->setParameter('album', $album instanceof Album ? $album->getId() : 0)
                ->orderBy('entity.takenAt', 'ASC'))
            ->setHelp('Parmi les photos de cet album. Ajoutez d\'abord des photos dans l\'album.')
            ->onlyWhenUpdating();
        yield DateTimeField::new('createdAt', 'Créé le')->hideOnForm();
    }
}
