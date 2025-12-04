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

    public function findRecentUserMessageByContent(Conversation $conversation, string $content, int $seconds): ?ConversationMessage
    {
        $threshold = (new \DateTimeImmutable())->modify(sprintf('-%d seconds', $seconds));

        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.role = :role')
            ->andWhere('m.content = :content')
            ->andWhere('m.createdAt >= :threshold')
            ->setParameter('conversation', $conversation)
            ->setParameter('role', ConversationMessage::ROLE_USER)
            ->setParameter('content', $content)
            ->setParameter('threshold', $threshold)
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findAssistantMessageAfter(Conversation $conversation, \DateTimeImmutable $after): ?ConversationMessage
    {
        return $this->createQueryBuilder('m')
            ->andWhere('m.conversation = :conversation')
            ->andWhere('m.role = :role')
            ->andWhere('m.createdAt >= :after')
            ->setParameter('conversation', $conversation)
            ->setParameter('role', ConversationMessage::ROLE_USER)
            ->setParameter('after', $after)
            ->orderBy('m.createdAt', 'DESC')
            ->addOrderBy('m.id', 'DESC')
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
