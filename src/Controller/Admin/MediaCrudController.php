<?php

namespace App\Controller\Admin;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\User;
use App\Enum\MediaType;
use App\Form\MediaUploadType;
use App\Repository\AlbumRepository;
use App\Repository\MediaRepository;
use App\Security\Voter\AlbumVoter;
use App\Security\Voter\MediaVoter;
use App\Service\MediaImporter;
use Doctrine\ORM\EntityManagerInterface;
use Doctrine\ORM\QueryBuilder;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FieldCollection;
use EasyCorp\Bundle\EasyAdminBundle\Collection\FilterCollection;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Dto\EntityDto;
use EasyCorp\Bundle\EasyAdminBundle\Dto\SearchDto;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MediaCrudController extends AbstractCrudController
{
    use VisibilityCrudTrait;

    public function __construct(
        private readonly MediaImporter $importer,
        private readonly AdminUrlGenerator $adminUrlGenerator,
    ) {
    }

    public static function getEntityFqcn(): string
    {
        return Media::class;
    }

    public function configureCrud(Crud $crud): Crud
    {
        return $crud
            ->setEntityLabelInSingular('Média')
            ->setEntityLabelInPlural('Médias')
            ->setDefaultSort(['createdAt' => 'DESC'])
            ->setSearchFields(['title', 'description', 'originalFilename'])
            ->setPaginatorPageSize(30)
            // Fiche, modification, suppression d'un média invisible pour ce compte : refusées
            ->setEntityPermission(MediaVoter::VIEW);
    }

    public function createIndexQueryBuilder(SearchDto $searchDto, EntityDto $entityDto, FieldCollection $fields, FilterCollection $filters): QueryBuilder
    {
        return MediaRepository::restrictToVisible(parent::createIndexQueryBuilder($searchDto, $entityDto, $fields, $filters), $this->getCurrentAdmin(), 'entity');
    }

    public function updateEntity(EntityManagerInterface $entityManager, $entityInstance): void
    {
        /** @var Media $entityInstance */
        $this->enforceVisibilityLock($entityManager, $entityInstance);

        parent::updateEntity($entityManager, $entityInstance);
    }

    public function configureFields(string $pageName): iterable
    {
        yield Field::new('preview', 'Aperçu')
            ->setVirtual(true)
            ->setTemplatePath('admin/field/media_preview.html.twig')
            ->hideOnForm();
        yield ChoiceField::new('type', 'Type')
            ->setChoices(array_combine(
                array_map(static fn (MediaType $t) => $t->label(), MediaType::cases()),
                MediaType::cases(),
            ))
            ->hideOnForm();
        yield TextField::new('title', 'Titre')
            ->setHelp('Facultatif : à défaut, le nom du fichier est affiché.');
        yield TextField::new('originalFilename', 'Fichier d\'origine')->hideOnForm()->hideOnIndex();
        yield TextareaField::new('description', 'Description')->hideOnIndex();
        yield DateTimeField::new('takenAt', 'Pris le')
            ->setHelp('Lu automatiquement dans la photo ou la vidéo quand c\'est possible.');
        $viewer = $this->getCurrentAdmin();
        yield AssociationField::new('album', 'Album')
            ->setQueryBuilder(static fn (QueryBuilder $qb) => AlbumRepository::restrictToVisible($qb, $viewer, 'entity'));
        yield from $this->visibilityFields(inheritable: true);
        yield AssociationField::new('tags', 'Tags')
            ->setFormTypeOption('by_reference', false)
            ->hideOnIndex();
        yield IntegerField::new('size', 'Taille')
            ->formatValue(static fn (?int $size) => null === $size ? '' : self::humanSize($size))
            ->hideOnForm();
        yield TextField::new('dimensionsLabel', 'Dimensions')
            ->setVirtual(true)
            ->formatValue(static fn ($value, Media $media) => $media->getWidth() ? $media->getWidth().' × '.$media->getHeight() : '—')
            ->onlyOnDetail();
        yield IntegerField::new('duration', 'Durée')
            ->formatValue(static fn (?int $s) => null === $s ? '—' : \sprintf('%d:%02d', intdiv($s, 60), $s % 60))
            ->onlyOnDetail();
        yield AssociationField::new('uploadedBy', 'Envoyé par')->onlyOnDetail();
        yield DateTimeField::new('createdAt', 'Envoyé le')->hideOnForm();
    }

    public function configureFilters(Filters $filters): Filters
    {
        return $filters
            ->add(ChoiceFilter::new('type', 'Type')->setChoices([
                'Photo' => MediaType::Photo->value,
                'Musique' => MediaType::Audio->value,
                'Vidéo' => MediaType::Video->value,
            ]))
            ->add(EntityFilter::new('album', 'Album')
                ->setFormTypeOption('value_type_options.query_builder', fn (\Doctrine\ORM\EntityRepository $r) => AlbumRepository::restrictToVisible($r->createQueryBuilder('a'), $this->getCurrentAdmin())))
            ->add(EntityFilter::new('tags', 'Tags'));
    }

    public function configureActions(Actions $actions): Actions
    {
        $upload = Action::new('upload', 'Ajouter des médias', 'fa fa-upload')
            ->linkToCrudAction('upload')
            ->createAsGlobalAction()
            ->asPrimaryAction();

        return $actions
            // Les médias arrivent uniquement par l'envoi de fichiers, pas par un formulaire vide
            ->disable(Action::NEW)
            ->add(Crud::PAGE_INDEX, $upload)
            ->add(Crud::PAGE_INDEX, Action::DETAIL);
    }

    #[AdminRoute(path: '/upload', name: 'upload', options: ['methods' => ['GET', 'POST']])]
    public function upload(Request $request, AlbumRepository $albumRepository): Response
    {
        // Arrivée depuis la page d'un album (bouton « Ajouter des photos ») : album présélectionné, retour vers cet album.
        // Le paramètre reste dans l'URL lors de l'envoi (le formulaire est posté sur l'adresse courante).
        $preselectedAlbum = $request->query->has('album') ? $albumRepository->find($request->query->getInt('album')) : null;
        if (null !== $preselectedAlbum && !$this->isGranted(AlbumVoter::VIEW, $preselectedAlbum)) {
            $preselectedAlbum = null;
        }

        $user = $this->getUser();
        \assert($user instanceof User);
        $form = $this->createForm(MediaUploadType::class, ['album' => $preselectedAlbum], ['viewer' => $user]);
        $form->handleRequest($request);
        $indexUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->unset('album') // recopié sinon depuis l'adresse courante
            ->generateUrl();
        // Album choisi au final (il a pu être changé dans la liste), sinon liste des médias
        $returnAlbum = null === $preselectedAlbum ? null : ($form->isSubmitted() ? $form->get('album')->getData() : $preselectedAlbum);
        $returnUrl = $returnAlbum instanceof Album ? $this->generateUrl('album_show', ['slug' => $returnAlbum->getSlug()]) : $indexUrl;
        // Envoi par lots du script assets/admin/media_upload.js : bilan en JSON, affiché par la page elle-même
        $isBatch = 'json' === $request->getPreferredFormat();

        if ($form->isSubmitted() && $form->isValid()) {
            ['saved' => $saved, 'errors' => $errors] = $this->importer->import(
                $form->get('files')->getData(),
                $user,
                $form->get('album')->getData(),
                $form->get('tags')->getData(),
            );

            if ($isBatch) {
                return $this->json(['saved' => $saved, 'errors' => $errors]);
            }

            if ($saved > 0) {
                $this->addFlash('success', 1 === $saved ? '1 média ajouté.' : \sprintf('%d médias ajoutés.', $saved));
            }
            foreach ($errors as $error) {
                // EasyAdmin affiche les messages flash sans échappement : le nom du fichier vient du navigateur
                $this->addFlash('danger', htmlspecialchars($error));
            }

            // Le site n'affiche que les messages de succès : en cas de refus, liste de l'admin pour voir les erreurs
            return $this->redirect([] === $errors ? $returnUrl : $indexUrl);
        }

        if ($isBatch && $form->isSubmitted()) {
            $errors = array_map(static fn (FormError $error) => $error->getMessage(), iterator_to_array($form->getErrors(true)));

            return $this->json(['saved' => 0, 'errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('admin/media/upload.html.twig', [
            'form' => $form,
            // Lien affiché par le script d'envoi par lots une fois tous les lots envoyés
            'index_url' => $returnUrl,
            'index_label' => $returnAlbum instanceof Album ? 'Revenir à l\'album' : 'Voir la liste des médias',
            'preselected_album' => $preselectedAlbum,
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }

    private static function humanSize(int $bytes): string
    {
        foreach (['o', 'Ko', 'Mo', 'Go'] as $unit) {
            if ($bytes < 1024 || 'Go' === $unit) {
                return ('o' === $unit ? $bytes : number_format($bytes, 1, ',', ' ')).' '.$unit;
            }
            $bytes /= 1024;
        }

        return '';
    }
}
