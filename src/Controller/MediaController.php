<?php

namespace App\Controller;

use App\Entity\Comment;
use App\Entity\Media;
use App\Entity\User;
use App\Form\CommentType;
use App\Repository\CommentRepository;
use App\Repository\MediaRepository;
use App\Security\Voter\CommentVoter;
use App\Security\Voter\MediaVoter;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

class MediaController extends AbstractController
{
    // Les fichiers eux-mêmes sont servis par MediaFileController (/media/{id}/file et /thumb)
    #[Route('/media/{id}', name: 'media_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    #[IsGranted(MediaVoter::VIEW, subject: 'media')]
    public function show(
        Media $media,
        Request $request,
        MediaRepository $mediaRepository,
        CommentRepository $commentRepository,
        EntityManagerInterface $entityManager,
    ): Response {
        $user = $this->getUser();
        \assert($user instanceof User);

        $comment = new Comment($media, $user);
        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted()) {
            $this->denyAccessUnlessGranted(MediaVoter::COMMENT, $media);

            if ($form->isValid()) {
                $entityManager->persist($comment);
                $entityManager->flush();

                // 303 : Turbo suit la redirection après l'envoi du formulaire
                return $this->redirect(
                    $this->generateUrl('media_show', ['id' => $media->getId()]).'#comment-'.$comment->getId(),
                    Response::HTTP_SEE_OTHER,
                );
            }
        }

        $older = $mediaRepository->findAdjacent($media, newer: false);
        $newer = $mediaRepository->findAdjacent($media, newer: true);

        // Même sens de lecture que la liste d'où l'on vient : album du plus ancien au plus récent, fil à l'inverse
        $inAlbum = null !== $media->getAlbum();

        // Formulaire invalide : render() répond en 422, ce qu'attend Turbo pour afficher les erreurs
        return $this->render('media/show.html.twig', [
            'media' => $media,
            'previous' => $inAlbum ? $older : $newer,
            'next' => $inAlbum ? $newer : $older,
            'comments' => $commentRepository->findForMedia($media, includeHidden: $this->isGranted('ROLE_ADMIN')),
            'comment_form' => $form,
        ]);
    }

    #[Route('/comments/{id}/delete', name: 'comment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    #[IsGranted(CommentVoter::DELETE, subject: 'comment')]
    public function deleteComment(Comment $comment, Request $request, EntityManagerInterface $entityManager): Response
    {
        // Vérifié ici plutôt qu'avec #[IsCsrfTokenValid] : son exception renverrait vers la connexion au lieu d'un 403
        if (!$this->isCsrfTokenValid('delete-comment-'.$comment->getId(), $request->getPayload()->getString('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $mediaId = $comment->getMedia()->getId();
        $entityManager->remove($comment);
        $entityManager->flush();

        $this->addFlash('success', 'Commentaire supprimé.');

        return $this->redirectToRoute('media_show', ['id' => $mediaId, '_fragment' => 'comments'], Response::HTTP_SEE_OTHER);
    }
}
