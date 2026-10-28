<?php

namespace App\Repository;

use App\Entity\Album;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Album>
 */
class AlbumRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Album::class);
    }

    /**
     * Albums du plus récent au plus ancien, selon la date de l'événement (sinon la date de création)
     *
     * @return list<Album>
     */
    public function findAllForListing(): array
    {
        return $this->createQueryBuilder('a')
            ->addSelect('cover')
            ->leftJoin('a.coverMedia', 'cover')
            ->addSelect('COALESCE(a.eventDate, a.createdAt) AS HIDDEN sortDate')
            ->orderBy('sortDate', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
