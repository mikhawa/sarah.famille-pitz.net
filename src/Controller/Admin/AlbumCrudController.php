<?php

namespace App\Controller\Admin;

use App\Entity\Album;
use App\Enum\MediaType;
use App\Repository\AlbumRepository;
use App\Repository\CommentRepository;
use App\Repository\MediaRepository;
use App\Security\Voter\AlbumVoter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Context\AdminContext;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\SlugField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

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

    public function configureActions(Actions $actions): Actions
    {
        $deleteMedia = Action::new('deleteMedia', 'Supprimer des médias', 'fa fa-trash')
            ->linkToCrudAction('deleteMedia')
            ->setCssClass('text-danger')
            ->displayIf(fn (Album $album): bool => $this->isGranted(AlbumVoter::DELETE_MEDIA, $album));

        return $actions
            ->add(Crud::PAGE_INDEX, $deleteMedia)
            ->add(Crud::PAGE_DETAIL, $deleteMedia)
            ->add(Crud::PAGE_EDIT, $deleteMedia);
    }

    /**
     * Suppression définitive de médias choisis dans un album (super-administrateur) : lignes en base, fichiers du disque
     * (original et miniatures, par MediaFileCleanupListener) et commentaires (clé étrangère ON DELETE CASCADE).
     */
    #[AdminRoute(path: '/{entityId}/medias', name: 'delete_media', options: ['methods' => ['GET', 'POST']])]
    public function deleteMedia(
        AdminContext $context,
        Request $request,
        MediaRepository $mediaRepository,
        CommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        /** @var Album $album */
        $album = $context->getEntity()->getInstance();
        $this->denyAccessUnlessGranted(AlbumVoter::DELETE_MEDIA, $album);
        $viewer = $this->getCurrentAdmin();

        if ($request->isMethod('POST')) {
            if (!$this->isCsrfTokenValid('delete-album-media-'.$album->getId(), $request->getPayload()->getString('_token'))) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }

            // Seuls les médias de cet album sont pris en compte, quels que soient les identifiants envoyés
            $ids = array_values(array_unique(array_map(intval(...), $request->getPayload()->all('media'))));
            $medias = $mediaRepository->findAllByAlbum($viewer, $album, $ids);

            foreach ($medias as $media) {
                if ($album->getCoverMedia() === $media) {
                    $album->setCoverMedia(null);
                }
                $entityManager->remove($media);
            }
            $entityManager->flush();

            $count = \count($medias);
            if (0 === $count) {
                $this->addFlash('warning', 'Aucun média sélectionné.');
            } else {
                $this->addFlash('success', 1 === $count ? '1 média supprimé.' : \sprintf('%d médias supprimés.', $count));
            }

            // Retour sur la même page (POST → GET) pour continuer le tri
            return $this->redirect($request->getUri(), Response::HTTP_SEE_OTHER);
        }

        $medias = $mediaRepository->findAllByAlbum($viewer, $album);

        return $this->render('admin/album/delete_media.html.twig', [
            'album' => $album,
            'medias' => $medias,
            'comment_counts' => $commentRepository->countByMedia($medias),
        ]);
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
