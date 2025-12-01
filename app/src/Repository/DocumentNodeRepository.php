<?php

namespace App\Repository;

use App\Entity\DocumentNode;
use App\Entity\RepositoryConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<DocumentNode>
 */
class DocumentNodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, DocumentNode::class);
    }

    /**
     * @return DocumentNode[]
     */
    public function findByRepository(RepositoryConfig $config): array
    {
        return $this->findBy(['repositoryConfig' => $config], ['path' => 'ASC']);
    }

    /**
     * @return DocumentNode[]
     */
    public function findByRepositoryIncludingDeleted(RepositoryConfig $config): array
    {
        $filters = $this->getEntityManager()->getFilters();
        $hasSoftDeleteFilter = $filters->has('softdeleteable');
        $wasSoftDeleteEnabled = $hasSoftDeleteFilter && $filters->isEnabled('softdeleteable');

        if ($wasSoftDeleteEnabled) {
            $filters->disable('softdeleteable');
        }

        try {
            return $this->findBy(['repositoryConfig' => $config], ['path' => 'ASC']);
        } finally {
            if ($wasSoftDeleteEnabled) {
                $filters->enable('softdeleteable');
            }
        }
    }

    /**
     * @param DocumentNode[] $nodes
     *
     * @return array<string, DocumentNode>
     */
    public function indexByPath(array $nodes): array
    {
        $map = [];

        foreach ($nodes as $node) {
            $map[$node->getPath()] = $node;
        }

        return $map;
    }
}
