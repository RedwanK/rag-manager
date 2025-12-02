<?php

namespace App\Controller;

use App\Entity\ConversationMessage;
use App\Repository\ConversationMessageRepository;
use App\Repository\ConversationRepository;
use App\Service\ChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/sse/chat', name: 'chat_stream_')]
#[IsGranted('ROLE_USER')]
class ChatStreamController extends AbstractController
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly ConversationMessageRepository $conversationMessageRepository,
        private readonly ChatService $chatService,
    ) {
    }

    #[Route('/{conversationId}/{messageId}', name: 'stream', methods: ['GET'])]
    public function stream(int $conversationId, int $messageId): StreamedResponse
    {
        $conversation = $this->conversationRepository->findOwnedById($conversationId, $this->getUser());
        if (!$conversation) {
            throw $this->createNotFoundException();
        }

        $message = $this->conversationMessageRepository->find($messageId);
        if (!$message || $message->getConversation()->getId() !== $conversation->getId()) {
            throw $this->createNotFoundException();
        }

        if ($message->getRole() !== ConversationMessage::ROLE_ASSISTANT) {
            throw $this->createNotFoundException();
        }

        $response = new StreamedResponse(function () use ($conversation, $message): void {
            $emit = function (string $event, mixed $payload = null): void {
                echo "event: {$event}\n";
                if ($payload !== null) {
                    echo 'data: ' . json_encode($payload) . "\n";
                }
                echo "\n";
                ob_flush();
                flush();
            };

            $this->chatService->streamAssistantMessage($conversation, $message, $this->getUser(), $emit);
        });

        $response->headers->set('Content-Type', 'text/event-stream');
        $response->headers->set('Cache-Control', 'no-cache');
        $response->headers->set('Connection', 'keep-alive');

        return $response;
    }
}
