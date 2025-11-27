<?php

namespace App\Entity;

use App\Repository\IngestionQueueItemRepository;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: IngestionQueueItemRepository::class)]
class IngestionQueueItem
{
    use TimestampableEntity;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\OneToOne(inversedBy: 'ingestionQueueItem', cascade: ['persist', 'remove'])]
    #[ORM\JoinColumn(nullable: false)]
    private ?DocumentNode $documentNode = null;

    #[ORM\Column(length: 255)]
    private ?string $status = null;

    #[ORM\Column(length: 255)]
    private ?string $source = null;

    #[ORM\ManyToOne(inversedBy: 'ingestionQueueItems')]
    #[ORM\JoinColumn(nullable: false)]
    private ?User $addedBy = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $startedAt = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $endedAt = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $ragMessage = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $storage_path = null;

    /**
     * @var Collection<int, IngestionLog>
     */
    #[ORM\OneToMany(targetEntity: IngestionLog::class, mappedBy: 'ingestionQueueItem')]
    private Collection $ingestionLogs;

    public function __construct()
    {
        $this->ingestionLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getDocumentNode(): ?DocumentNode
    {
        return $this->documentNode;
    }

    public function setDocumentNode(DocumentNode $documentNode): static
    {
        $this->documentNode = $documentNode;

        return $this;
    }

    public function getStatus(): ?string
    {
        return $this->status;
    }

    public function setStatus(string $status): static
    {
        $this->status = $status;

        return $this;
    }

    public function getSource(): ?string
    {
        return $this->source;
    }

    public function setSource(string $source): static
    {
        $this->source = $source;

        return $this;
    }

    public function getAddedBy(): ?User
    {
        return $this->addedBy;
    }

    public function setAddedBy(?User $addedBy): static
    {
        $this->addedBy = $addedBy;

        return $this;
    }

    public function getStartedAt(): ?\DateTimeImmutable
    {
        return $this->startedAt;
    }

    public function setStartedAt(?\DateTimeImmutable $startedAt): static
    {
        $this->startedAt = $startedAt;

        return $this;
    }

    public function getEndedAt(): ?\DateTimeImmutable
    {
        return $this->endedAt;
    }

    public function setEndedAt(\DateTimeImmutable $endedAt): static
    {
        $this->endedAt = $endedAt;

        return $this;
    }

    public function getRagMessage(): ?string
    {
        return $this->ragMessage;
    }

    public function setRagMessage(?string $ragMessage): static
    {
        $this->ragMessage = $ragMessage;

        return $this;
    }

    public function getStoragePath(): ?string
    {
        return $this->storage_path;
    }

    public function setStoragePath(?string $storage_path): static
    {
        $this->storage_path = $storage_path;

        return $this;
    }

    /**
     * @return Collection<int, IngestionLog>
     */
    public function getIngestionLogs(): Collection
    {
        return $this->ingestionLogs;
    }

    public function addIngestionLog(IngestionLog $ingestionLog): static
    {
        if (!$this->ingestionLogs->contains($ingestionLog)) {
            $this->ingestionLogs->add($ingestionLog);
            $ingestionLog->setIngestionQueueItem($this);
        }

        return $this;
    }

    public function removeIngestionLog(IngestionLog $ingestionLog): static
    {
        if ($this->ingestionLogs->removeElement($ingestionLog)) {
            // set the owning side to null (unless already changed)
            if ($ingestionLog->getIngestionQueueItem() === $this) {
                $ingestionLog->setIngestionQueueItem(null);
            }
        }

        return $this;
    }
}
