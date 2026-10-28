<?php

namespace App\Repository;

use App\Entity\Media;
use App\Entity\Tag;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Tag>
 */
class TagRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Tag::class);
    }

    /**
     * Tags utilisés par au moins un média, par ordre alphabétique
     *
     * @return list<array{name: string, slug: string, mediaCount: int}>
     */
    public function findUsedWithMediaCount(): array
    {
        $rows = $this->getEntityManager()->createQueryBuilder()
            ->select('t.name', 't.slug', 'COUNT(m.id) AS mediaCount')
            ->from(Media::class, 'm')
            ->join('m.tags', 't')
            ->groupBy('t.id', 't.name', 't.slug')
            ->orderBy('t.name', 'ASC')
            ->getQuery()
            ->getArrayResult();

        return array_map(static fn (array $row): array => [...$row, 'mediaCount' => (int) $row['mediaCount']], $rows);
    }
}
