<?php

namespace App\Repository;

use App\Entity\Album;
use App\Entity\User;
use App\Enum\Visibility;
use Doctrine\ORM\QueryBuilder;
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
     * Ne garde que les albums que ce compte peut voir (même règle que Album::isVisibleTo())
     */
    public static function restrictToVisible(QueryBuilder $queryBuilder, ?User $viewer, string $alias = 'a'): QueryBuilder
    {
        return $queryBuilder
            ->andWhere($alias.'.visibility IN (:albumVisibilities)')
            ->setParameter('albumVisibilities', Visibility::values(Visibility::allowedFor($viewer)) ?: ['-']);
    }

    /**
     * Albums visibles, du plus récent au plus ancien, selon la date de l'événement (sinon la date de création)
     *
     * @return list<Album>
     */
    public function findAllForListing(User $viewer): array
    {
        return self::restrictToVisible($this->createQueryBuilder('a'), $viewer)
            ->addSelect('cover')
            ->leftJoin('a.coverMedia', 'cover')
            ->addSelect('COALESCE(a.eventDate, a.createdAt) AS HIDDEN sortDate')
            ->orderBy('sortDate', 'DESC')
            ->addOrderBy('a.id', 'DESC')
            ->getQuery()
            ->getResult();
    }
}
