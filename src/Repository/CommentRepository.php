<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Media;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Comment>
 */
class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    /**
     * Commentaires d'un média, du plus ancien au plus récent (lecture comme une conversation)
     *
     * @return list<Comment>
     */
    public function findForMedia(Media $media, bool $includeHidden): array
    {
        $queryBuilder = $this->createQueryBuilder('c')
            ->addSelect('author')
            ->leftJoin('c.author', 'author')
            ->where('c.media = :media')
            ->setParameter('media', $media)
            ->orderBy('c.createdAt', 'ASC')
            ->addOrderBy('c.id', 'ASC');

        if (!$includeHidden) {
            $queryBuilder->andWhere('c.isHidden = false');
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Nombre de commentaires (masqués compris) par média
     *
     * @param list<Media> $medias
     *
     * @return array<int, int> identifiant du média => nombre (absent si aucun)
     */
    public function countByMedia(array $medias): array
    {
        if ([] === $medias) {
            return [];
        }

        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.media) AS mediaId', 'COUNT(c.id) AS total')
            ->where('c.media IN (:medias)')
            ->setParameter('medias', $medias)
            ->groupBy('c.media')
            ->getQuery()
            ->getArrayResult();

        return array_column(array_map(static fn (array $row) => [(int) $row['mediaId'], (int) $row['total']], $rows), 1, 0);
    }

    public function countHidden(): int
    {
        return $this->count(['isHidden' => true]);
    }
}
