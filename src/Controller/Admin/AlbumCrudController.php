<?php

namespace App\Controller\Admin;

use App\Entity\Album;
use App\Enum\MediaType;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;

class AlbumCrudController extends AbstractCrudController
{
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
            ->setSearchFields(['title', 'description']);
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

        // Choix parmi les photos de l'album : n'existe donc qu'en modification
        $album = $this->getContext()?->getEntity()->getInstance();
        yield AssociationField::new('coverMedia', 'Photo de couverture')
            ->setQueryBuilder(static fn (QueryBuilder $qb) => $qb
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
