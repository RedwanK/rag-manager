<?php

namespace App\Entity;

use App\Repository\LightRagRequestLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: LightRagRequestLogRepository::class)]
#[ORM\Table(name: 'light_rag_request_log')]
#[ORM\Index(columns: ['conversation_id'], name: 'light_rag_request_log_conversation_idx')]
#[ORM\Index(columns: ['message_id'], name: 'light_rag_request_log_message_idx')]
class LightRagRequestLog
{
    use TimestampableEntity;

    public const STATUS_SUCCESS = 'success';
    public const STATUS_ERROR = 'error';
    public const STATUS_CANCELLED = 'cancelled';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'lightRagRequestLogs')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    #[ORM\ManyToOne(targetEntity: ConversationMessage::class)]
    #[ORM\JoinColumn(nullable: true, onDelete: 'SET NULL')]
    private ?ConversationMessage $message = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(type: 'integer')]
    private int $durationMs = 0;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_SUCCESS;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getConversation(): Conversation
    {
        return $this->conversation;
    }

    public function setConversation(Conversation $conversation): self
    {
        $this->conversation = $conversation;

        return $this;
    }

    public function getMessage(): ?ConversationMessage
    {
        return $this->message;
    }

    public function setMessage(?ConversationMessage $message): self
    {
        $this->message = $message;

        return $this;
    }

    public function getUser(): User
    {
        return $this->user;
    }

    public function setUser(User $user): self
    {
        $this->user = $user;

        return $this;
    }

    public function getDurationMs(): int
    {
        return $this->durationMs;
    }

    public function setDurationMs(int $durationMs): self
    {
        $this->durationMs = $durationMs;

        return $this;
    }

    public function getStatus(): string
    {
        return $this->status;
    }

    public function setStatus(string $status): self
    {
        $this->status = $status;

        return $this;
    }

    public function getErrorMessage(): ?string
    {
        return $this->errorMessage;
    }

    public function setErrorMessage(?string $errorMessage): self
    {
        $this->errorMessage = $errorMessage;

        return $this;
    }
}
