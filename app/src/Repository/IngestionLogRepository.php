<?php

namespace App\Repository;

use App\Entity\IngestionLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\Tools\Pagination\Paginator;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<IngestionLog>
 */
class IngestionLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, IngestionLog::class);
    }

    /**
     * @param array{
     *     level?: string|null,
     *     status?: string|null,
     *     path?: string|null,
     *     user?: string|null,
     *     date_from?: \DateTimeImmutable|null,
     *     date_to?: \DateTimeImmutable|null,
     * } $filters
     *
     * @return array{items: list<IngestionLog>, total: int}
     */
    public function searchWithFilters(array $filters, int $page, int $limit): array
    {
        $qb = $this->createQueryBuilder('log')
            ->leftJoin('log.ingestionQueueItem', 'item')
            ->leftJoin('item.documentNode', 'document')
            ->leftJoin('item.addedBy', 'addedBy')
            ->addSelect('item', 'document', 'addedBy')
            ->orderBy('log.createdAt', 'DESC');

        if (!empty($filters['level'])) {
            $qb->andWhere('log.level = :level')->setParameter('level', $filters['level']);
        }

        if (!empty($filters['status'])) {
            $qb->andWhere('item.status = :status')->setParameter('status', $filters['status']);
        }

        if (!empty($filters['path'])) {
            $qb->andWhere('document.path LIKE :path')->setParameter('path', '%' . $filters['path'] . '%');
        }

        if (!empty($filters['user'])) {
            $qb->andWhere('addedBy.email LIKE :user')->setParameter('user', '%' . $filters['user'] . '%');
        }

        if (!empty($filters['date_from'])) {
            $qb->andWhere('log.createdAt >= :date_from')->setParameter('date_from', $filters['date_from']);
        }

        if (!empty($filters['date_to'])) {
            $qb->andWhere('log.createdAt <= :date_to')->setParameter('date_to', $filters['date_to']);
        }

        $qb->setMaxResults($limit)->setFirstResult(($page - 1) * $limit);

        $paginator = new Paginator($qb->getQuery());
        $total = count($paginator);

        return [
            'items' => iterator_to_array($paginator->getIterator()),
            'total' => $total,
        ];
    }
}
