<?php

namespace App\Controller\Admin;

use App\Entity\Album;
use App\Entity\Comment;
use App\Entity\Media;
use App\Entity\Tag;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\Assets;
use EasyCorp\Bundle\EasyAdminBundle\Config\Dashboard;
use EasyCorp\Bundle\EasyAdminBundle\Config\MenuItem;
use EasyCorp\Bundle\EasyAdminBundle\Controller\AbstractDashboardController;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[AdminDashboard(routePath: '/admin', routeName: 'admin')]
#[IsGranted('ROLE_ADMIN')]
class DashboardController extends AbstractDashboardController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function index(): Response
    {
        return $this->render('admin/dashboard.html.twig', [
            'counts' => [
                'Médias' => $this->entityManager->getRepository(Media::class)->count(),
                'Comptes' => $this->entityManager->getRepository(User::class)->count(),
                'Albums' => $this->entityManager->getRepository(Album::class)->count(),
                'Tags' => $this->entityManager->getRepository(Tag::class)->count(),
                'Commentaires' => $this->entityManager->getRepository(Comment::class)->count(),
            ],
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('🌈 Sarah')
            ->setFaviconPath('data:image/svg+xml,<svg xmlns=%22http://www.w3.org/2000/svg%22 viewBox=%220 0 128 128%22><text y=%221.2em%22 font-size=%2296%22>🌈</text></svg>')
            ->setLocales(['fr'])
            // Toujours en clair, comme le site
            ->disableDarkMode();
    }

    public function configureAssets(): Assets
    {
        // Thème accordé au site et envoi des médias par lots (point d'entrée AssetMapper « admin »)
        return Assets::new()->addAssetMapperEntry('admin');
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

        yield MenuItem::section('Contenus');
        yield MenuItem::linkTo(MediaCrudController::class, 'Médias', 'fa fa-photo-film');
        yield MenuItem::linkTo(AlbumCrudController::class, 'Albums', 'fa fa-images');
        yield MenuItem::linkTo(TagCrudController::class, 'Tags', 'fa fa-tags');

        yield MenuItem::section('Famille');
        yield MenuItem::linkTo(UserCrudController::class, 'Comptes', 'fa fa-users');
        yield MenuItem::linkTo(CommentCrudController::class, 'Commentaires', 'fa fa-comments');

        yield MenuItem::section();
        yield MenuItem::linkToRoute('Retour au site', 'fa fa-arrow-left', 'app_home');
    }
}
