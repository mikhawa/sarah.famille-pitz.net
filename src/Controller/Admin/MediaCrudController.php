<?php

namespace App\Controller\Admin;

use App\Entity\Media;
use App\Entity\User;
use App\Enum\MediaType;
use App\Exception\UnsupportedMediaException;
use App\Form\MediaUploadType;
use App\Service\MediaUploader;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminRoute;
use EasyCorp\Bundle\EasyAdminBundle\Config\Action;
use EasyCorp\Bundle\EasyAdminBundle\Config\Actions;
use EasyCorp\Bundle\EasyAdminBundle\Config\Crud;
use EasyCorp\Bundle\EasyAdminBundle\Config\Filters;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractCrudController;
use EasyCorp\Bundle\EasyAdminBundle\Field\AssociationField;
use EasyCorp\Bundle\EasyAdminBundle\Field\ChoiceField;
use EasyCorp\Bundle\EasyAdminBundle\Field\DateTimeField;
use EasyCorp\Bundle\EasyAdminBundle\Field\Field;
use EasyCorp\Bundle\EasyAdminBundle\Field\IntegerField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextareaField;
use EasyCorp\Bundle\EasyAdminBundle\Field\TextField;
use EasyCorp\Bundle\EasyAdminBundle\Filter\ChoiceFilter;
use EasyCorp\Bundle\EasyAdminBundle\Filter\EntityFilter;
use EasyCorp\Bundle\EasyAdminBundle\Router\AdminUrlGenerator;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;

class MediaCrudController extends AbstractCrudController
{
    public function __construct(
        private readonly MediaUploader $uploader,
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
            ->setPaginatorPageSize(30);
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
        yield AssociationField::new('album', 'Album');
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
            ->add(EntityFilter::new('album', 'Album'))
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
    public function upload(Request $request, EntityManagerInterface $entityManager): Response
    {
        $form = $this->createForm(MediaUploadType::class);
        $form->handleRequest($request);
        $indexUrl = $this->adminUrlGenerator
            ->setController(self::class)
            ->setAction(Action::INDEX)
            ->generateUrl();
        // Envoi par lots du script assets/admin/media_upload.js : bilan en JSON, affiché par la page elle-même
        $isBatch = 'json' === $request->getPreferredFormat();

        if ($form->isSubmitted() && $form->isValid()) {
            $user = $this->getUser();
            $album = $form->get('album')->getData();
            $tags = $form->get('tags')->getData();
            $saved = 0;
            $errors = [];

            /** @var UploadedFile $file */
            foreach ($form->get('files')->getData() as $file) {
                try {
                    $media = $this->uploader->upload($file, $file->getClientOriginalName(), $user instanceof User ? $user : null);
                } catch (UnsupportedMediaException $e) {
                    $errors[] = \sprintf('%s : %s', $file->getClientOriginalName(), $e->getMessage());
                    continue;
                }

                $media->setAlbum($album);
                foreach ($tags as $tag) {
                    $media->addTag($tag);
                }

                // Enregistrement fichier par fichier : un échec n'annule pas les envois précédents
                $entityManager->persist($media);
                $entityManager->flush();
                ++$saved;
            }

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

            return $this->redirect($indexUrl);
        }

        if ($isBatch && $form->isSubmitted()) {
            $errors = array_map(static fn (FormError $error) => $error->getMessage(), iterator_to_array($form->getErrors(true)));

            return $this->json(['saved' => 0, 'errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('admin/media/upload.html.twig', [
            'form' => $form,
            // Lien affiché par le script d'envoi par lots une fois tous les lots envoyés
            'index_url' => $indexUrl,
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
