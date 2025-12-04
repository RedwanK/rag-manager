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

class ChatService
{
    public function __construct(
        private readonly ConversationMessageRepository $conversationMessageRepository,
        private readonly LightRagRequestLogRepository $lightRagRequestLogRepository,
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
            $existingAssistant = $this->conversationMessageRepository->findAssistantMessageAfter($conversation, $duplicateUserMessage->getCreatedAt());
            if ($existingAssistant !== null) {
                return [
                    'userMessage' => $duplicateUserMessage,
                    'assistantMessage' => $existingAssistant,
                ];
            }
        }

        if (trim($conversation->getTitle()) === '' || $conversation->getTitle() === 'Nouvelle discussion') {
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
    public function streamAssistantMessage(Conversation $conversation, ConversationMessage $assistantMessage, User $user, callable $emit): void
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
                function (string $event, mixed $payload) use ($assistantMessage, $conversation, $emit): void {
                    if ($this->cancellationManager->isCancelled((int) $assistantMessage->getId())) {
                        throw new ChatStreamCancelledException('cancelled_by_user');
                    }

                    $this->handleStreamEvent($assistantMessage, $conversation, $emit, $event, $payload);
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
            $emit('error', ['message' => $cancelledException->getMessage()]);
        } catch (\Throwable $error) {
            $assistantMessage
                ->setStatus(ConversationMessage::STATUS_ERROR)
                ->setErrorMessage($error->getMessage())
                ->setFinishedAt(new DateTimeImmutable());
            $log->setStatus(LightRagRequestLog::STATUS_ERROR);
            $emit('error', ['message' => $error->getMessage()]);
            $this->logger->error('LightRag stream failed', [
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
            $emit('done', null);
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
        callable $emit,
        string $event,
        mixed $payload
    ): void {
        if ($event === 'token') {
            $text = is_array($payload) && array_key_exists('text', $payload) ? (string) $payload['text'] : (string) $payload;
            $assistantMessage->setContent(($assistantMessage->getContent() ?? '') . $text);
            $emit('token', ['text' => $text, 'format' => 'markdown']);
        } elseif ($event === 'sources') {
            $sources = is_array($payload) ? $payload : [];
            $assistantMessage->setSourceDocuments($sources);
            $emit('sources', $sources);
        } elseif ($event === 'error') {
            $message = is_array($payload) && array_key_exists('message', $payload)
                ? (string) $payload['message']
                : (string) ($payload ?? 'Erreur inconnue');
            if ($message === '') {
                $message = 'Erreur inconnue';
            }
            $assistantMessage
                ->setStatus(ConversationMessage::STATUS_ERROR)
                ->setErrorMessage($message)
                ->setFinishedAt(new DateTimeImmutable());
            $emit('error', ['message' => $message]);
            $this->logger->error('LightRag returned error event', [
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
            return 'Nouvelle discussion';
        }

        $normalized = preg_replace('/\s+/', ' ', $trimmed) ?? $trimmed;

        return mb_substr($normalized, 0, 120);
    }
}
