<?php

namespace App\Twig;

use App\Repository\ConversationRepository;
use Symfony\Bundle\SecurityBundle\Security;
use Twig\Extension\AbstractExtension;
use Twig\TwigFunction;

class ConversationExtension extends AbstractExtension
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly Security $security
    ) {
    }

    public function getFunctions(): array
    {
        return [
            new TwigFunction('recent_conversations', [$this, 'recentConversations']),
        ];
    }

    /**
     * @return list<array{id: int, title: string, lastActivityAt: ?\DateTimeInterface}>
     */
    public function recentConversations(int $limit = 5): array
    {
        $user = $this->security->getUser();
        if (!$user) {
            return [];
        }

        $conversations = $this->conversationRepository->findRecentForUser($user, $limit);

        return array_map(static fn ($conversation) => [
            'id' => $conversation->getId(),
            'title' => $conversation->getTitle(),
            'lastActivityAt' => $conversation->getLastActivityAt(),
        ], $conversations);
    }
}
