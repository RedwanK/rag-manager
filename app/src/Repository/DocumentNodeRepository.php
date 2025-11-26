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

    public function deleteForRepository(RepositoryConfig $config): void
    {
        $this->createQueryBuilder('n')
            ->delete()
            ->where('n.repositoryConfig = :config')
            ->setParameter('config', $config)
            ->getQuery()
            ->execute();
    }
}
