<?php

namespace App\Repository;

use App\Entity\Conversation;
use App\Entity\ConversationMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<ConversationMessage>
 */
class ConversationMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ConversationMessage::class);
    }

    /**
     * @return ConversationMessage[]
     */
    public function findForConversation(Conversation $conversation): array
    {
        return $this->findBy(['conversation' => $conversation], ['createdAt' => 'ASC']);
    }

    /**
     * @return ConversationMessage[]
     */
    public function findLatestForConversation(Conversation $conversation, int $limit): array
    {
        return $this->findBy(
            ['conversation' => $conversation],
            ['createdAt' => 'DESC', 'id' => 'DESC'],
            $limit
        );
    }

    public function findLastUserMessage(Conversation $conversation): ?ConversationMessage
    {
        return $this->findOneBy(
            ['conversation' => $conversation, 'role' => ConversationMessage::ROLE_USER],
            ['createdAt' => 'DESC', 'id' => 'DESC']
        );
    }
}
