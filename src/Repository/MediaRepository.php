<?php

namespace App\Repository;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\Tag;
use App\Enum\MediaType;
use App\Pagination\Page;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Media>
 */
class MediaRepository extends ServiceEntityRepository
{
    public const PER_PAGE = 24;

    // Date du fil chronologique : prise de vue, sinon date d'envoi (voir Media::getSortDate())
    private const SORT_DATE = 'COALESCE(m.takenAt, m.createdAt)';

    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Media::class);
    }

    /**
     * Fil chronologique, du plus récent au plus ancien
     *
     * @return Page<Media>
     */
    public function paginateFeed(int $page, ?MediaType $type = null): Page
    {
        $queryBuilder = $this->createSortedQueryBuilder('DESC');
        if (null !== $type) {
            $queryBuilder->andWhere('m.type = :type')->setParameter('type', $type);
        }

        return Page::fromQueryBuilder($queryBuilder, $page, self::PER_PAGE);
    }

    /**
     * Médias d'un album, dans l'ordre où ils ont été pris (on revit l'événement du début à la fin)
     *
     * @return Page<Media>
     */
    public function paginateByAlbum(Album $album, int $page): Page
    {
        $queryBuilder = $this->createSortedQueryBuilder('ASC')
            ->andWhere('m.album = :album')
            ->setParameter('album', $album);

        return Page::fromQueryBuilder($queryBuilder, $page, self::PER_PAGE);
    }

    /**
     * @return Page<Media>
     */
    public function paginateByTag(Tag $tag, int $page): Page
    {
        $queryBuilder = $this->createSortedQueryBuilder('DESC')
            ->andWhere(':tag MEMBER OF m.tags')
            ->setParameter('tag', $tag);

        return Page::fromQueryBuilder($queryBuilder, $page, self::PER_PAGE);
    }

    /**
     * Média voisin dans le temps, au sein du même album si le média en a un, sinon dans tout le fil
     */
    public function findAdjacent(Media $media, bool $newer): ?Media
    {
        $comparison = $newer ? '>' : '<';
        $queryBuilder = $this->createSortedQueryBuilder($newer ? 'ASC' : 'DESC')
            ->andWhere(\sprintf('%1$s %2$s :date OR (%1$s = :date AND m.id %2$s :id)', self::SORT_DATE, $comparison))
            ->setParameter('date', $media->getSortDate())
            ->setParameter('id', $media->getId())
            ->setMaxResults(1);

        if (null !== $media->getAlbum()) {
            $queryBuilder->andWhere('m.album = :album')->setParameter('album', $media->getAlbum());
        }

        return $queryBuilder->getQuery()->getOneOrNullResult();
    }

    /**
     * Nombre de médias par album et première photo ou vidéo (couverture par défaut)
     *
     * @return array<int, array{count: int, firstVisualId: ?int}> indexé par id d'album
     */
    public function getAlbumStats(): array
    {
        $rows = $this->createQueryBuilder('m')
            ->select('IDENTITY(m.album) AS albumId', 'COUNT(m.id) AS mediaCount')
            ->addSelect('MIN(CASE WHEN m.type IN (:visual) THEN m.id ELSE :none END) AS firstVisualId')
            ->where('m.album IS NOT NULL')
            ->groupBy('m.album')
            ->setParameter('visual', [MediaType::Photo->value, MediaType::Video->value])
            ->setParameter('none', null)
            ->getQuery()
            ->getArrayResult();

        $stats = [];
        foreach ($rows as $row) {
            $stats[(int) $row['albumId']] = [
                'count' => (int) $row['mediaCount'],
                'firstVisualId' => null !== $row['firstVisualId'] ? (int) $row['firstVisualId'] : null,
            ];
        }

        return $stats;
    }

    private function createSortedQueryBuilder(string $direction): QueryBuilder
    {
        // L'id départage les médias pris à la même seconde (rafale de photos)
        return $this->createQueryBuilder('m')
            ->addSelect(self::SORT_DATE.' AS HIDDEN sortDate')
            ->orderBy('sortDate', $direction)
            ->addOrderBy('m.id', $direction);
    }
}
