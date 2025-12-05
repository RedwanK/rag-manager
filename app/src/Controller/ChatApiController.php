<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\ConversationMessage;
use App\Exception\RateLimitExceededException;
use App\Repository\ConversationMessageRepository;
use App\Repository\ConversationRepository;
use App\Service\ChatService;
use App\Service\DocumentService;
use Psr\Log\LoggerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/chat', name: 'chat_')]
#[IsGranted('ROLE_USER')]
class ChatApiController extends AbstractController
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly ConversationMessageRepository $conversationMessageRepository,
        private readonly ChatService $chatService,
        private readonly DocumentService $documentService
    ) {
    }

    #[Route('/conversations/{id}/messages', name: 'conversations_messages', methods: ['GET'])]
    public function conversationMessages(int $id): JsonResponse
    {
        $conversation = $this->conversationRepository->findOwnedById($id, $this->getUser());
        if (!$conversation) {
            return $this->json(['message' => 'Conversation not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $messages = $this->conversationMessageRepository->findForConversation($conversation);

        $docNodes = $this->documentService->mapDocumentNodes($messages[1]->getSourceDocuments());

        return $this->json(array_map(fn (ConversationMessage $message) => [
            'id' => $message->getId(),
            'role' => $message->getRole(),
            'content' => $message->getContent(),
            'status' => $message->getStatus(),
            'error' => $message->getErrorMessage(),
            'sourceDocuments' => $docNodes,
            'format' => 'markdown',
            'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
            'streamedAt' => $message->getStreamedAt()?->format(DATE_ATOM),
            'finishedAt' => $message->getFinishedAt()?->format(DATE_ATOM),
        ], $messages));
    }

    #[Route('/conversations/{id}/prompt', name: 'conversations_prompt', methods: ['POST'])]
    public function sendPrompt(int $id, Request $request): JsonResponse
    {
        $conversation = $this->conversationRepository->findOwnedById($id, $this->getUser());
        if (!$conversation) {
            return $this->json(['message' => 'Conversation not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $prompt = is_string($payload['prompt'] ?? null) ? trim($payload['prompt']) : '';

        if ($prompt === '') {
            return $this->json(['message' => 'Prompt cannot be empty.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        try {
            $messages = $this->chatService->sendPrompt($conversation, $this->getUser(), $prompt);
        } catch (RateLimitExceededException $exception) {
            return $this->json(
                ['message' => $exception->getMessage(), 'retryAfter' => $exception->getRetryAfterSeconds()],
                JsonResponse::HTTP_TOO_MANY_REQUESTS
            );
        }

        return $this->json([
            'conversationId' => $conversation->getId(),
            'userMessageId' => $messages['userMessage']->getId(),
            'assistantMessageId' => $messages['assistantMessage']->getId(),
        ], JsonResponse::HTTP_ACCEPTED);
    }

    #[Route('/messages/{id}/cancel', name: 'messages_cancel', methods: ['POST'])]
    public function cancelMessage(int $id): JsonResponse
    {
        $message = $this->conversationMessageRepository->find($id);
        if (!$message || $message->getRole() !== ConversationMessage::ROLE_ASSISTANT) {
            return $this->json(['message' => 'Message not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $conversation = $message->getConversation();
        /**
         * @var User $user
         */
        $user = $this->getUser();
        if ($conversation->getUser()->getId() !== $user->getId()) {
            return $this->json(['message' => 'Message not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->chatService->cancelAssistantMessage($message);

        return $this->json(['message' => 'cancelled_by_user']);
    }
}
