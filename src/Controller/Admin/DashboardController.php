<?php

namespace App\Controller\Admin;

use App\Entity\Album;
use App\Entity\Tag;
use App\Entity\User;
use Doctrine\ORM\EntityManagerInterface;
use EasyCorp\Bundle\EasyAdminBundle\Attribute\AdminDashboard;
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
                'Comptes' => $this->entityManager->getRepository(User::class)->count(),
                'Albums' => $this->entityManager->getRepository(Album::class)->count(),
                'Tags' => $this->entityManager->getRepository(Tag::class)->count(),
            ],
        ]);
    }

    public function configureDashboard(): Dashboard
    {
        return Dashboard::new()
            ->setTitle('Site de Sarah — Administration')
            ->setLocales(['fr']);
    }

    public function configureMenuItems(): iterable
    {
        yield MenuItem::linkToDashboard('Tableau de bord', 'fa fa-home');

        yield MenuItem::section('Contenus');
        yield MenuItem::linkTo(AlbumCrudController::class, 'Albums', 'fa fa-images');
        yield MenuItem::linkTo(TagCrudController::class, 'Tags', 'fa fa-tags');

        yield MenuItem::section('Famille');
        yield MenuItem::linkTo(UserCrudController::class, 'Comptes', 'fa fa-users');

        yield MenuItem::section();
        yield MenuItem::linkToRoute('Retour au site', 'fa fa-arrow-left', 'app_home');
    }
}
