<?php

namespace App\Repository;

use App\Entity\Album;
use App\Entity\Media;
use App\Entity\Tag;
use App\Entity\User;
use App\Enum\MediaType;
use App\Enum\Visibility;
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
    public function paginateFeed(User $viewer, int $page, ?MediaType $type = null): Page
    {
        $queryBuilder = $this->createSortedQueryBuilder($viewer, 'DESC');
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
    public function paginateByAlbum(User $viewer, Album $album, int $page): Page
    {
        $queryBuilder = $this->createSortedQueryBuilder($viewer, 'ASC')
            ->andWhere('m.album = :album')
            ->setParameter('album', $album);

        return Page::fromQueryBuilder($queryBuilder, $page, self::PER_PAGE);
    }

    /**
     * @return Page<Media>
     */
    public function paginateByTag(User $viewer, Tag $tag, int $page): Page
    {
        $queryBuilder = $this->createSortedQueryBuilder($viewer, 'DESC')
            ->andWhere(':tag MEMBER OF m.tags')
            ->setParameter('tag', $tag);

        return Page::fromQueryBuilder($queryBuilder, $page, self::PER_PAGE);
    }

    /**
     * Média voisin dans le temps, au sein du même album si le média en a un, sinon dans tout le fil
     * (parmi les médias que ce compte peut voir)
     */
    public function findAdjacent(User $viewer, Media $media, bool $newer): ?Media
    {
        $comparison = $newer ? '>' : '<';
        $queryBuilder = $this->createSortedQueryBuilder($viewer, $newer ? 'ASC' : 'DESC')
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
     * Nombre de médias visibles par album et première photo ou vidéo visible (couverture par défaut)
     *
     * @return array<int, array{count: int, firstVisualId: ?int}> indexé par id d'album
     */
    public function getAlbumStats(User $viewer): array
    {
        $rows = self::restrictToVisible($this->createQueryBuilder('m'), $viewer)
            ->select('IDENTITY(m.album) AS albumId', 'COUNT(m.id) AS mediaCount')
            ->addSelect('MIN(CASE WHEN m.type IN (:visual) THEN m.id ELSE :none END) AS firstVisualId')
            ->andWhere('m.album IS NOT NULL')
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

    /**
     * Ne garde que les médias que ce compte peut voir : même règle que Media::isVisibleTo().
     *
     * - l'album, s'il existe, doit être visible (barrière) ;
     * - la visibilité propre du média, ou celle de l'album quand elle est vide (« comme l'album »), doit être visible ;
     *   sans album ni visibilité propre, le média est privé.
     *
     * Ajoute une jointure « visibleAlbum » sur l'album du média.
     */
    public static function restrictToVisible(QueryBuilder $queryBuilder, ?User $viewer, string $alias = 'm'): QueryBuilder
    {
        $allowed = Visibility::allowedFor($viewer);
        // Sans visibilité propre : celle de l'album (déjà vérifiée ci-dessous), ou privé sans album
        $inherited = \in_array(Visibility::Private, $allowed, true)
            ? '%1$s.visibility IS NULL'
            : '%1$s.visibility IS NULL AND visibleAlbum.id IS NOT NULL';

        return $queryBuilder
            ->leftJoin($alias.'.album', 'visibleAlbum')
            ->andWhere('visibleAlbum.id IS NULL OR visibleAlbum.visibility IN (:visibilities)')
            ->andWhere(\sprintf('%1$s.visibility IN (:visibilities) OR ('.$inherited.')', $alias))
            // Liste jamais vide pour IN () : un compte désactivé ne voit rien
            ->setParameter('visibilities', Visibility::values($allowed) ?: ['-']);
    }

    private function createSortedQueryBuilder(User $viewer, string $direction): QueryBuilder
    {
        // L'id départage les médias pris à la même seconde (rafale de photos)
        return self::restrictToVisible($this->createQueryBuilder('m'), $viewer)
            ->addSelect(self::SORT_DATE.' AS HIDDEN sortDate')
            ->orderBy('sortDate', $direction)
            ->addOrderBy('m.id', $direction);
    }
}
