<?php

namespace App\Controller;

use App\Entity\Album;
use App\Entity\User;
use App\Form\MediaUploadType;
use App\Repository\AlbumRepository;
use App\Repository\MediaRepository;
use App\Repository\TagRepository;
use App\Security\Voter\AlbumVoter;
use App\Service\MediaImporter;
use Symfony\Bridge\Doctrine\Attribute\MapEntity;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\Form\FormError;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\HttpKernel\Attribute\MapQueryParameter;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\CurrentUser;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/albums')]
class AlbumController extends AbstractController
{
    #[Route('', name: 'album_index', methods: ['GET'])]
    public function index(
        #[CurrentUser] User $user,
        AlbumRepository $albumRepository,
        MediaRepository $mediaRepository,
        TagRepository $tagRepository,
    ): Response {
        return $this->render('album/index.html.twig', [
            'albums' => $albumRepository->findAllForListing($user),
            'stats' => $mediaRepository->getAlbumStats($user),
            'tags' => $tagRepository->findUsedWithMediaCount($user),
        ]);
    }

    // Album invisible pour ce compte : 404, comme s'il n'existait pas
    #[Route('/{slug}', name: 'album_show', methods: ['GET'])]
    #[IsGranted(AlbumVoter::VIEW, subject: 'album', statusCode: 404)]
    public function show(
        #[MapEntity(mapping: ['slug' => 'slug'])] Album $album,
        #[CurrentUser] User $user,
        MediaRepository $mediaRepository,
        #[MapQueryParameter(options: ['min_range' => 1])] int $page = 1,
    ): Response {
        $medias = $mediaRepository->paginateByAlbum($user, $album, $page);
        if ($medias->isOutOfRange()) {
            throw $this->createNotFoundException();
        }

        return $this->render('album/show.html.twig', [
            'album' => $album,
            'medias' => $medias,
        ]);
    }

    /**
     * Envoi de médias par un membre autorisé (albums publics) ; les administrateurs passent par l'administration.
     * Même envoi par lots que l'administration (assets/admin/media_upload.js, réponses JSON).
     */
    #[Route('/{slug}/envoyer', name: 'album_upload', methods: ['GET', 'POST'])]
    #[IsGranted(AlbumVoter::VIEW, subject: 'album', statusCode: 404)]
    #[IsGranted(AlbumVoter::UPLOAD, subject: 'album')]
    public function upload(
        #[MapEntity(mapping: ['slug' => 'slug'])] Album $album,
        #[CurrentUser] User $user,
        Request $request,
        MediaImporter $importer,
    ): Response {
        $form = $this->createForm(MediaUploadType::class, null, ['viewer' => $user, 'with_classification' => false]);
        $form->handleRequest($request);
        $albumUrl = $this->generateUrl('album_show', ['slug' => $album->getSlug()]);
        $isBatch = 'json' === $request->getPreferredFormat();

        if ($form->isSubmitted() && $form->isValid()) {
            // Visibilité « comme l'album » : visibles tout de suite par la famille (album public)
            ['saved' => $saved, 'errors' => $errors] = $importer->import($form->get('files')->getData(), $user, $album);

            if ($isBatch) {
                return $this->json(['saved' => $saved, 'errors' => $errors]);
            }
            if ($saved > 0) {
                $this->addFlash('success', 1 === $saved ? '1 souvenir ajouté.' : \sprintf('%d souvenirs ajoutés.', $saved));
            }
            if ([] === $errors) {
                return $this->redirect($albumUrl, Response::HTTP_SEE_OTHER);
            }
            // Sans JavaScript : fichiers refusés affichés sur la page d'envoi (échappés par Twig)
            foreach ($errors as $error) {
                $form->addError(new FormError($error));
            }
        } elseif ($isBatch && $form->isSubmitted()) {
            $errors = array_map(static fn (FormError $error) => $error->getMessage(), iterator_to_array($form->getErrors(true)));

            return $this->json(['saved' => 0, 'errors' => $errors], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        return $this->render('album/upload.html.twig', [
            'album' => $album,
            'form' => $form,
            'album_url' => $albumUrl,
        ], new Response(null, $form->isSubmitted() ? 422 : 200));
    }
}
