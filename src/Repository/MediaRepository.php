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
    public const PER_PAGE = 72;

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
        return Page::fromQueryBuilder($this->createFeedQueryBuilder($viewer, $type), $page, self::PER_PAGE);
    }

    /**
     * Toutes les photos du fil (même filtre de type que paginateFeed()), pages confondues : mêmes droits et même ordre.
     * Chargées à la demande par la visionneuse de l'accueil (voir LightboxController). Vidéo ou musique : aucune photo,
     * sans requête.
     *
     * @return list<Media>
     */
    public function findPhotosForFeed(User $viewer, ?MediaType $type = null): array
    {
        if (null !== $type && MediaType::Photo !== $type) {
            return [];
        }

        return $this->getPhotos($this->createFeedQueryBuilder($viewer, $type));
    }

    /**
     * Médias d'un album, dans l'ordre où ils ont été pris (on revit l'événement du début à la fin)
     *
     * @return Page<Media>
     */
    public function paginateByAlbum(User $viewer, Album $album, int $page): Page
    {
        return Page::fromQueryBuilder($this->createAlbumQueryBuilder($viewer, $album), $page, self::PER_PAGE);
    }

    /**
     * Tous les médias d'un album (photos, vidéos, musique), pages confondues : mêmes droits et même ordre que
     * paginateByAlbum(). Page « Supprimer des médias » de l'administration ; $ids restreint à une sélection.
     *
     * @param list<int>|null $ids
     *
     * @return list<Media>
     */
    public function findAllByAlbum(User $viewer, Album $album, ?array $ids = null): array
    {
        $queryBuilder = $this->createAlbumQueryBuilder($viewer, $album);
        if (null !== $ids) {
            // Liste jamais vide pour IN ()
            $queryBuilder->andWhere('m.id IN (:ids)')->setParameter('ids', $ids ?: [0]);
        }

        return $queryBuilder->getQuery()->getResult();
    }

    /**
     * Toutes les photos d'un album (sans vidéos ni musique), pages confondues : mêmes droits de visibilité et même
     * ordre que paginateByAlbum(). Alimente la visionneuse, qui parcourt tout l'album quelle que soit la page.
     *
     * @return list<Media>
     */
    public function findPhotosByAlbum(User $viewer, Album $album): array
    {
        return $this->getPhotos($this->createAlbumQueryBuilder($viewer, $album));
    }

    /**
     * Toutes les photos d'un tag (sans vidéos ni musique), pages confondues : mêmes droits et même ordre que
     * paginateByTag(). Alimente la visionneuse.
     *
     * @return list<Media>
     */
    public function findPhotosByTag(User $viewer, Tag $tag): array
    {
        return $this->getPhotos($this->createTagQueryBuilder($viewer, $tag));
    }

    /**
     * @return Page<Media>
     */
    public function paginateByTag(User $viewer, Tag $tag, int $page): Page
    {
        return Page::fromQueryBuilder($this->createTagQueryBuilder($viewer, $tag), $page, self::PER_PAGE);
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

    // Médias visibles du fil, du plus récent au plus ancien, éventuellement d'un seul type (base commune page / visionneuse)
    private function createFeedQueryBuilder(User $viewer, ?MediaType $type): QueryBuilder
    {
        $queryBuilder = $this->createSortedQueryBuilder($viewer, 'DESC');
        if (null !== $type) {
            $queryBuilder->andWhere('m.type = :type')->setParameter('type', $type);
        }

        return $queryBuilder;
    }

    // Médias visibles d'un album, dans l'ordre où ils ont été pris (base commune de la page et de la visionneuse)
    private function createAlbumQueryBuilder(User $viewer, Album $album): QueryBuilder
    {
        return $this->createSortedQueryBuilder($viewer, 'ASC')
            ->andWhere('m.album = :album')
            ->setParameter('album', $album);
    }

    // Médias visibles d'un tag, du plus récent au plus ancien (base commune de la page et de la visionneuse)
    private function createTagQueryBuilder(User $viewer, Tag $tag): QueryBuilder
    {
        return $this->createSortedQueryBuilder($viewer, 'DESC')
            ->andWhere(':tag MEMBER OF m.tags')
            ->setParameter('tag', $tag);
    }

    /**
     * Restreint la requête aux photos et la lit en entier (critères de visibilité et ordre déjà dans la requête)
     *
     * @return list<Media>
     */
    private function getPhotos(QueryBuilder $queryBuilder): array
    {
        return $queryBuilder
            ->andWhere('m.type = :photo')
            ->setParameter('photo', MediaType::Photo)
            ->getQuery()
            ->getResult();
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
