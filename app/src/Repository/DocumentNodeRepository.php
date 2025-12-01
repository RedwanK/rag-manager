<?php

namespace App\Repository;

use App\Entity\DocumentNode;
use App\Entity\RepositoryConfig;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;
use Psr\Log\LoggerInterface;

/**
 * @extends ServiceEntityRepository<DocumentNode>
 */
class DocumentNodeRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry, protected LoggerInterface $logger)
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
            $docs = $this->findBy(['repositoryConfig' => $config], ['path' => 'ASC']);
        } catch (\Exception $e) {
            $this->logger->error($e->getMessage());
            $docs = [];
        } finally {
            if ($wasSoftDeleteEnabled) {
                $filters->enable('softdeleteable');
            }
        }

        return $docs;
    }
}
