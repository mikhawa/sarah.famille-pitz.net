<?php

namespace App\Controller\Admin;

use App\Entity\Comment;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\BooleanField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\BooleanFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;

/**
 * Modération : l'admin lit, masque ou supprime les commentaires ; il n'en écrit ni n'en modifie le texte
 */
class CommentCrudController extends AbstractCrudController
{
    public static function getEntityFqcn(): string
    {
        return Comment::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Commentaire')
            ->setEntityLabelInPlural('Commentaires')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['content', 'author.displayName'])
            ->setPaginatorPageSize(30);
    }

    public function configureFields(string $pageName): iterable
    {
        yield AssociationField::new('media', 'Média')->setDisabled();
        yield AssociationField::new('author', 'Auteur')
            ->formatValue(static fn ($value) => $value ?? 'Ancien membre')
            ->setDisabled();
        yield TextareaField::new('content', 'Commentaire')
            ->setMaxLength(120)
            ->setDisabled();
        // Interrupteur directement dans la liste pour masquer ou réafficher
        yield BooleanField::new('isHidden', 'Masqué')
            ->setHelp('Un commentaire masqué n\'est plus visible des membres, mais reste conservé.');
        yield DateTimeField::new('createdAt', 'Écrit le')->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(BooleanFilter::new('isHidden', 'Masqué'))
            ->add(EntityFilter::new('author', 'Auteur'))
            ->add(EntityFilter::new('media', 'Média'));
    }

    public function configureActions(Actions $actions): Actions
    {
        return $actions
            // Les commentaires s'écrivent sur le site, pas dans l'administration
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }
}
