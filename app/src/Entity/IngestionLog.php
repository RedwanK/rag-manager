<?php

namespace App\Entity;

use App\Repository\IngestionLogRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: IngestionLogRepository::class)]
class IngestionLog
{
    use TimestampableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(inversedBy: 'ingestionLogs')]
    #[ORM\JoinColumn(nullable: false)]
    private ?IngestionQueueItem $ingestionQueueItem = null;

    #[ORM\Column(length: 255)]
    private ?string $level = null;

    #[ORM\Column(length: 255)]
    private ?string $message = null;

    #[ORM\Column(nullable: true)]
    private ?array $context = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getIngestionQueueItem(): ?IngestionQueueItem
    {
        return $this->ingestionQueueItem;
    }

    public function setIngestionQueueItem(?IngestionQueueItem $ingestionQueueItem): static
    {
        $this->ingestionQueueItem = $ingestionQueueItem;

        return $this;
    }

    public function getLevel(): ?string
    {
        return $this->level;
    }

    public function setLevel(string $level): static
    {
        $this->level = $level;

        return $this;
    }

    public function getMessage(): ?string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = $message;

        return $this;
    }

    public function getContext(): ?array
    {
        return $this->context;
    }

    public function setContext(?array $context): static
    {
        $this->context = $context;

        return $this;
    }
}
