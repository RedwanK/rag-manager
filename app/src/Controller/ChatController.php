<?php

namespace App\Controller;

use App\Entity\Conversation;
use App\Entity\ConversationMessage;
use App\Exception\RateLimitExceededException;
use App\Repository\ConversationMessageRepository;
use App\Repository\ConversationRepository;
use App\Service\ChatService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;

#[Route('/api/chat', name: 'chat_')]
#[IsGranted('ROLE_USER')]
class ChatController extends AbstractController
{
    public function __construct(
        private readonly ConversationRepository $conversationRepository,
        private readonly ConversationMessageRepository $conversationMessageRepository,
        private readonly ChatService $chatService
    ) {
    }

    #[Route('/conversations', name: 'conversations_list', methods: ['GET'])]
    public function listConversations(): JsonResponse
    {
        $user = $this->getUser();
        $conversations = $this->conversationRepository->listForUser($user);

        return $this->json(array_map(fn (Conversation $conversation) => [
            'id' => $conversation->getId(),
            'title' => $conversation->getTitle(),
            'lastActivityAt' => $conversation->getLastActivityAt()->format(DATE_ATOM),
            'createdAt' => $conversation->getCreatedAt()->format(DATE_ATOM),
        ], $conversations));
    }

    #[Route('/conversations', name: 'conversations_create', methods: ['POST'])]
    public function createConversation(Request $request): JsonResponse
    {
        $payload = json_decode($request->getContent(), true) ?? [];
        $title = is_string($payload['title'] ?? null) ? $payload['title'] : null;

        $conversation = $this->chatService->createConversation($this->getUser(), $title);

        return $this->json([
            'id' => $conversation->getId(),
            'title' => $conversation->getTitle(),
            'createdAt' => $conversation->getCreatedAt()->format(DATE_ATOM),
            'lastActivityAt' => $conversation->getLastActivityAt()->format(DATE_ATOM),
        ], JsonResponse::HTTP_CREATED);
    }

    #[Route('/conversations/{id}/messages', name: 'conversations_messages', methods: ['GET'])]
    public function conversationMessages(int $id): JsonResponse
    {
        $conversation = $this->conversationRepository->findOwnedById($id, $this->getUser());
        if (!$conversation) {
            return $this->json(['message' => 'Conversation not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $messages = $this->conversationMessageRepository->findForConversation($conversation);

        return $this->json(array_map(fn (ConversationMessage $message) => [
            'id' => $message->getId(),
            'role' => $message->getRole(),
            'content' => $message->getContent(),
            'status' => $message->getStatus(),
            'error' => $message->getErrorMessage(),
            'sourceDocuments' => $message->getSourceDocuments(),
            'createdAt' => $message->getCreatedAt()->format(DATE_ATOM),
            'streamedAt' => $message->getStreamedAt()?->format(DATE_ATOM),
            'finishedAt' => $message->getFinishedAt()?->format(DATE_ATOM),
        ], $messages));
    }

    #[Route('/conversations/{id}', name: 'conversations_delete', methods: ['DELETE'])]
    public function deleteConversation(int $id): JsonResponse
    {
        $conversation = $this->conversationRepository->findOwnedById($id, $this->getUser());
        if (!$conversation) {
            return $this->json(['message' => 'Conversation not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->chatService->deleteConversation($conversation);

        return $this->json(['message' => 'Conversation archived.']);
    }

    #[Route('/conversations/{id}/title', name: 'conversations_rename', methods: ['PATCH'])]
    public function renameConversation(int $id, Request $request): JsonResponse
    {
        $conversation = $this->conversationRepository->findOwnedById($id, $this->getUser());
        if (!$conversation) {
            return $this->json(['message' => 'Conversation not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $payload = json_decode($request->getContent(), true) ?? [];
        $title = (string) ($payload['title'] ?? '');
        if (trim($title) === '') {
            return $this->json(['message' => 'Title cannot be empty.'], JsonResponse::HTTP_BAD_REQUEST);
        }

        $this->chatService->renameConversation($conversation, $title);

        return $this->json(['id' => $conversation->getId(), 'title' => $conversation->getTitle()]);
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
        if ($conversation->getUser()->getId() !== $this->getUser()->getId()) {
            return $this->json(['message' => 'Message not found.'], JsonResponse::HTTP_NOT_FOUND);
        }

        $this->chatService->cancelAssistantMessage($message);

        return $this->json(['message' => 'cancelled_by_user']);
    }
}
