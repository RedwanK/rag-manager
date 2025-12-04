<?php

namespace App\Service;

use App\Entity\Conversation;
use App\Entity\ConversationMessage;
use App\Entity\LightRagRequestLog;
use App\Entity\User;
use App\Exception\ChatStreamCancelledException;
use App\Exception\RateLimitExceededException;
use App\Repository\ConversationMessageRepository;
use App\Repository\LightRagRequestLogRepository;
use DateTimeImmutable;
use Doctrine\ORM\EntityManagerInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\HttpFoundation\EventStreamResponse;
use Symfony\Component\HttpFoundation\ServerEvent;
use Symfony\Contracts\Translation\TranslatorInterface;

class ChatService
{
    const DEFAULT_CONVERSATION_TITLE = "chat.new_conversation";
    const SSE_EVENT_TOKEN = 'token';
    const SSE_EVENT_SOURCES = 'sources';
    const SSE_EVENT_ERROR = 'error';
    const SSE_EVENT_DONE = 'done';

    public function __construct(
        private readonly ConversationMessageRepository $conversationMessageRepository,
        private readonly TranslatorInterface $translator,
        private readonly EntityManagerInterface $em,
        private readonly LightRagClient $lightRagClient,
        private readonly PromptRateLimiter $promptRateLimiter,
        private readonly ChatCancellationManager $cancellationManager,
        private readonly LoggerInterface $logger,
        private readonly int $chatHistoryLimit,
    ) {
    }

    public function createConversation(User $user, ?string $title = null): Conversation
    {
        $conversation = new Conversation();
        $conversation
            ->setUser($user)
            ->setLastActivityAt(new DateTimeImmutable());

        if ($title !== null && trim($title) !== '') {
            $conversation->setTitle($title);
        }

        $this->em->persist($conversation);
        $this->em->flush();

        return $conversation;
    }

    public function renameConversation(Conversation $conversation, string $title): Conversation
    {
        $conversation->setTitle($title);

        $this->em->flush();

        return $conversation;
    }

    public function deleteConversation(Conversation $conversation): void
    {
        $conversation->setDeletedAt(new \DateTime());
        $this->em->flush();
    }

    /**
     * @return array{userMessage: ConversationMessage, assistantMessage: ConversationMessage}
     */
    public function sendPrompt(Conversation $conversation, User $user, string $prompt): array
    {
        $this->promptRateLimiter->assertWithinLimit($user);

        $now = new DateTimeImmutable();
        $conversation->setLastActivityAt($now);

        // Deduplicate quick successive submits with identical prompt on the same conversation
        $duplicateUserMessage = $this->conversationMessageRepository->findRecentUserMessageByContent($conversation, $prompt, 5);
        if ($duplicateUserMessage !== null) {
            $existingAssistant = $this->conversationMessageRepository->findAssistantMessageAfter(
                $conversation, 
                DateTimeImmutable::createFromMutable($duplicateUserMessage->getCreatedAt())
            );

            if ($existingAssistant !== null) {
                return [
                    'userMessage' => $duplicateUserMessage,
                    'assistantMessage' => $existingAssistant,
                ];
            }
        }

        if (trim($conversation->getTitle()) === '' || 
            $conversation->getTitle() === $this->translator->trans(self::DEFAULT_CONVERSATION_TITLE)) {
            
            $conversation->setTitle($this->generateTitle($prompt));
        }

        $userMessage = (new ConversationMessage())
            ->setConversation($conversation)
            ->setRole(ConversationMessage::ROLE_USER)
            ->setContent($prompt)
            ->setStatus(ConversationMessage::STATUS_COMPLETED)
            ->setFinishedAt($now);

        $assistantMessage = (new ConversationMessage())
            ->setConversation($conversation)
            ->setRole(ConversationMessage::ROLE_ASSISTANT)
            ->setStatus(ConversationMessage::STATUS_STREAMING);

        $this->em->persist($userMessage);
        $this->em->persist($assistantMessage);
        $this->em->flush();

        return [
            'userMessage' => $userMessage,
            'assistantMessage' => $assistantMessage,
        ];
    }

    /**
     * @param callable(string, mixed): void $emit
     */
    public function streamAssistantMessage(Conversation $conversation, ConversationMessage $assistantMessage, User $user, EventStreamResponse $response): void
    {
        $start = microtime(true);

        $log = (new LightRagRequestLog())
            ->setConversation($conversation)
            ->setMessage($assistantMessage)
            ->setUser($user)
            ->setStatus(LightRagRequestLog::STATUS_SUCCESS);

        $assistantMessage->setStreamedAt(new DateTimeImmutable());

        $this->em->persist($log);
        $this->em->flush();

        $history = $this->buildHistoryPayload($conversation, $assistantMessage);
        $prompt = $this->conversationMessageRepository->findLastUserMessage($conversation)?->getContent() ?? '';

        try {
            $this->lightRagClient->streamQuery(
                $prompt,
                $history,
                function (string $event, mixed $payload) use ($assistantMessage, $conversation, $response): void {
                    if ($this->cancellationManager->isCancelled((int) $assistantMessage->getId())) {
                        throw new ChatStreamCancelledException('cancelled_by_user');
                    }

                    $this->handleStreamEvent($assistantMessage, $conversation, $response, $event, $payload);
                },
                fn () => $this->cancellationManager->isCancelled((int) $assistantMessage->getId())
            );

            if ($assistantMessage->getStatus() !== ConversationMessage::STATUS_ERROR) {
                $assistantMessage
                    ->setStatus(ConversationMessage::STATUS_COMPLETED)
                    ->setFinishedAt(new DateTimeImmutable());
                $conversation->setLastActivityAt(new DateTimeImmutable());
            }

        } catch (RateLimitExceededException $rateLimitExceededException) {
            $assistantMessage
                ->setStatus(ConversationMessage::STATUS_ERROR)
                ->setErrorMessage($rateLimitExceededException->getMessage());
            $log->setStatus(LightRagRequestLog::STATUS_ERROR);

        } catch (ChatStreamCancelledException $cancelledException) {
            $assistantMessage
                ->setStatus(ConversationMessage::STATUS_ERROR)
                ->setErrorMessage($cancelledException->getMessage())
                ->setFinishedAt(new DateTimeImmutable());
            $log->setStatus(LightRagRequestLog::STATUS_CANCELLED);
            $response->sendEvent(new ServerEvent(['message' => $cancelledException->getMessage()], self::SSE_EVENT_ERROR));
            
        } catch (\Throwable $error) {
            $assistantMessage
                ->setStatus(ConversationMessage::STATUS_ERROR)
                ->setErrorMessage($error->getMessage())
                ->setFinishedAt(new DateTimeImmutable());
            $log->setStatus(LightRagRequestLog::STATUS_ERROR);
            $response->sendEvent(new ServerEvent(['message' => $error->getMessage()], self::SSE_EVENT_ERROR));
            $this->logger->error($this->translator->trans('chat.errors.lightrag_stream_failed'), [
                'conversation' => $conversation->getId(),
                'message' => $assistantMessage->getId(),
                'error' => $error->getMessage(),
            ]);

        } finally {
            if ($assistantMessage->getStatus() === ConversationMessage::STATUS_ERROR && $log->getStatus() === LightRagRequestLog::STATUS_SUCCESS) {
                $log->setStatus(LightRagRequestLog::STATUS_ERROR);
            }

            $duration = (int) ((microtime(true) - $start) * 1000);
            $log->setDurationMs($duration);
            $this->em->flush();
            // Here we send '-' because we need to send something, it could be anything, like a sandwich
            $response->sendEvent(new ServerEvent('-', self::SSE_EVENT_DONE));
        }
    }

    public function cancelAssistantMessage(ConversationMessage $assistantMessage): void
    {
        $assistantMessage
            ->setStatus(ConversationMessage::STATUS_ERROR)
            ->setErrorMessage('cancelled_by_user')
            ->setFinishedAt(new DateTimeImmutable());

        $this->cancellationManager->cancel((int) $assistantMessage->getId());
        $assistantMessage->getConversation()->setLastActivityAt(new DateTimeImmutable());
        $this->em->flush();
    }

    /**
     * @return list<array{role: string, content: string|null}>
     */
    private function buildHistoryPayload(Conversation $conversation, ?ConversationMessage $skipMessage = null): array
    {
        $messages = $this->conversationMessageRepository->findLatestForConversation($conversation, $this->chatHistoryLimit);
        $history = [];

        foreach (array_reverse($messages) as $message) {
            if ($skipMessage !== null && $skipMessage->getId() === $message->getId()) {
                continue;
            }

            $history[] = [
                'role' => $message->getRole(),
                'content' => $message->getContent(),
            ];
        }

        return $history;
    }

    private function handleStreamEvent(
        ConversationMessage $assistantMessage,
        Conversation $conversation,
        EventStreamResponse $response,
        string $event,
        mixed $payload
    ): void {
        if ($event === self::SSE_EVENT_TOKEN) {
            $text = is_array($payload) && array_key_exists('text', $payload) ? (string) $payload['text'] : (string) $payload;
            $assistantMessage->setContent(($assistantMessage->getContent() ?? '') . $text);
            $response->sendEvent(new ServerEvent($this->normalizeSsePayload($text), self::SSE_EVENT_TOKEN));
        } elseif ($event === self::SSE_EVENT_SOURCES) {
            $assistantMessage->setSourceDocuments($payload);
            $sources = !empty($payload) ? $payload : "-";
            $response->sendEvent(new ServerEvent(json_encode($sources), self::SSE_EVENT_SOURCES));
        } elseif ($event === self::SSE_EVENT_ERROR) {
            $message = is_array($payload) && array_key_exists('message', $payload)
                ? (string) $payload['message']
                : (string) ($payload ?? $this->translator->trans('chat.errors.unknown'));
            if ($message === '') {
                $message = $this->translator->trans('chat.errors.unknown');
            }
            $assistantMessage
                ->setStatus(ConversationMessage::STATUS_ERROR)
                ->setErrorMessage($message)
                ->setFinishedAt(new DateTimeImmutable());
            $response->sendEvent(new ServerEvent(['message' => $message], 'error'));    
            $this->logger->error($this->translator->trans('chat.errors.lightrag_return_error'), [
                'conversation' => $conversation->getId(),
                'message' => $assistantMessage->getId(),
                'payload' => $payload,
            ]);
        }

        $conversation->setLastActivityAt(new DateTimeImmutable());
        $this->em->flush();
    }

    private function generateTitle(string $prompt): string
    {
        $trimmed = trim($prompt);
        if ($trimmed === '') {
            return $this->translator->trans(self::DEFAULT_CONVERSATION_TITLE);
        }

        $normalized = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;

        return mb_substr($normalized, 0, 120);
    }

    /**
     * SSE data must not contain raw newlines; split them so EventSource reconstructs them client-side.
     *
     * @return string|list<string>
     */
    private function normalizeSsePayload(string $text): array|string
    {
        if (!str_contains($text, "\n") && !str_contains($text, "\r")) {
            return $text;
        }

        $normalized = str_replace("\r\n", "\n", $text);

        return explode("\n", $normalized);
    }
}
