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

    public function findOwnedById(int $id, User $user): ?Conversation
    {
        return $this->findOneBy([
            'id' => $id,
            'user' => $user,
        ]);
    }
}
