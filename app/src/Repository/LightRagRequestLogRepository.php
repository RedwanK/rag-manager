<?php

namespace App\Repository;

use App\Entity\LightRagRequestLog;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LightRagRequestLog>
 */
class LightRagRequestLogRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LightRagRequestLog::class);
    }
}
