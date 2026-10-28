<?php

namespace App\Pagination;

use Doctrine\ORM\QueryBuilder;
use Doctrine\ORM\Tools\Pagination\Paginator;

/**
 * Une page de résultats d'une requête Doctrine
 *
 * @template T
 */
final class Page
{
    /**
     * @param list<T> $items
     */
    private function __construct(
        public readonly array $items,
        public readonly int $current,
        public readonly int $lastPage,
        public readonly int $totalItems,
    ) {
    }

    /**
     * La requête ne doit pas joindre de collection (pagination SQL simple, sans sous-requête)
     *
     * @return self<mixed>
     */
    public static function fromQueryBuilder(QueryBuilder $queryBuilder, int $page, int $perPage): self
    {
        $paginator = new Paginator(
            $queryBuilder->setFirstResult(($page - 1) * $perPage)->setMaxResults($perPage),
            fetchJoinCollection: false,
        );
        $total = \count($paginator);

        return new self(
            array_values(iterator_to_array($paginator)),
            $page,
            max(1, (int) ceil($total / $perPage)),
            $total,
        );
    }

    /**
     * Page demandée au-delà de la dernière (la page 1 d'une liste vide reste valide)
     */
    public function isOutOfRange(): bool
    {
        return $this->current < 1 || $this->current > $this->lastPage;
    }

    public function hasPrevious(): bool
    {
        return $this->current > 1;
    }

    public function hasNext(): bool
    {
        return $this->current < $this->lastPage;
    }
}
