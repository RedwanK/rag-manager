<?php

namespace App\Entity;

use App\Repository\ConversationMessageRepository;
use DateTimeImmutable;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: ConversationMessageRepository::class)]
#[ORM\Table(name: 'conversation_message')]
#[ORM\Index(columns: ['conversation_id', 'created_at'], name: 'conversation_message_conversation_created_at_idx')]
class ConversationMessage
{
    use TimestampableEntity;

    public const ROLE_USER = 'user';
    public const ROLE_ASSISTANT = 'assistant';
    public const ROLE_SYSTEM = 'system';

    public const STATUS_STREAMING = 'streaming';
    public const STATUS_COMPLETED = 'completed';
    public const STATUS_ERROR = 'error';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Conversation::class, inversedBy: 'messages')]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private Conversation $conversation;

    #[ORM\Column(length: 16)]
    private string $role = self::ROLE_USER;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $content = null;

    #[ORM\Column(type: 'json', nullable: true)]
    private ?array $sourceDocuments = null;

    #[ORM\Column(nullable: true)]
    private ?int $tokenCount = null;

    #[ORM\Column(length: 32)]
    private string $status = self::STATUS_COMPLETED;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $errorMessage = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $streamedAt = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?DateTimeImmutable $finishedAt = null;

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

    public function getRole(): string
    {
        return $this->role;
    }

    public function setRole(string $role): self
    {
        $this->role = $role;

        return $this;
    }

    public function getContent(): ?string
    {
        return $this->content;
    }

    public function setContent(?string $content): self
    {
        $this->content = $content;

        return $this;
    }

    public function getSourceDocuments(): ?array
    {
        return $this->sourceDocuments;
    }

    public function setSourceDocuments(?array $sourceDocuments): self
    {
        $this->sourceDocuments = $sourceDocuments;

        return $this;
    }

    public function getTokenCount(): ?int
    {
        return $this->tokenCount;
    }

    public function setTokenCount(?int $tokenCount): self
    {
        $this->tokenCount = $tokenCount;

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

    public function getStreamedAt(): ?DateTimeImmutable
    {
        return $this->streamedAt;
    }

    public function setStreamedAt(?DateTimeImmutable $streamedAt): self
    {
        $this->streamedAt = $streamedAt;

        return $this;
    }

    public function getFinishedAt(): ?DateTimeImmutable
    {
        return $this->finishedAt;
    }

    public function setFinishedAt(?DateTimeImmutable $finishedAt): self
    {
        $this->finishedAt = $finishedAt;

        return $this;
    }
}
