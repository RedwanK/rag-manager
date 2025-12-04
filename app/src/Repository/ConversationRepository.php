<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\User;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Conversation>
 */
class ConversationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Conversation::class);
    }

    /**
     * @return Conversation[]
     */
    public function listForUser(User $user): array
    {
        return $this->findBy(
            ['user' => $user],
            ['lastActivityAt' => 'DESC', 'id' => 'DESC']
        );
    }

    /**
     * @return Conversation[]
     */
    public function findRecentForUser(User $user, int $limit = 5): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.user = :user')
            ->andWhere('c.deletedAt IS NULL')
            ->setParameter('user', $user)
            ->orderBy('c.lastActivityAt', 'DESC')
            ->addOrderBy('c.id', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findOwnedById(int $id, User $user): ?Conversation
    {
        return $this->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);
    }
}
