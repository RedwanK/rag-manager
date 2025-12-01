<?php

namespace App\Entity;

use App\Repository\DocumentNodeRepository;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: DocumentNodeRepository::class)]
#[ORM\Table(name: 'document_node')]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
class DocumentNode
{
    use TimestampableEntity;
    
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: RepositoryConfig::class)]
    #[ORM\JoinColumn(nullable: false)]
    private RepositoryConfig $repositoryConfig;

    #[ORM\Column(length: 2048)]
    private string $path;

    #[ORM\Column(length: 32)]
    private string $type;

    #[ORM\Column(type: 'bigint', nullable: true)]
    private ?int $size = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastModified = null;

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSyncedAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $lastSyncStatus = null;

    #[ORM\OneToOne(mappedBy: 'documentNode', cascade: ['persist'])]
    private ?IngestionQueueItem $ingestionQueueItem = null;

    #[ORM\Column(type: 'datetime', nullable: true)]
    private ?\DateTimeInterface $deletedAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRepositoryConfig(): RepositoryConfig
    {
        return $this->repositoryConfig;
    }

    public function setRepositoryConfig(RepositoryConfig $repositoryConfig): self
    {
        $this->repositoryConfig = $repositoryConfig;
        return $this;
    }

    public function getPath(): string
    {
        return $this->path;
    }

    public function setPath(string $path): self
    {
        $this->path = $path;
        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): self
    {
        $this->type = $type;
        return $this;
    }

    public function getSize(): ?int
    {
        return $this->size;
    }

    public function setSize(?int $size): self
    {
        $this->size = $size;
        return $this;
    }

    public function getLastModified(): ?\DateTimeImmutable
    {
        return $this->lastModified;
    }

    public function setLastModified(?\DateTimeImmutable $lastModified): self
    {
        $this->lastModified = $lastModified;
        return $this;
    }

    public function getLastSyncedAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncedAt;
    }

    public function setLastSyncedAt(?\DateTimeImmutable $lastSyncedAt): self
    {
        $this->lastSyncedAt = $lastSyncedAt;
        return $this;
    }

    public function getLastSyncStatus(): ?string
    {
        return $this->lastSyncStatus;
    }

    public function setLastSyncStatus(?string $lastSyncStatus): self
    {
        $this->lastSyncStatus = $lastSyncStatus;
        return $this;
    }

    public function getIngestionQueueItem(): ?IngestionQueueItem
    {
        return $this->ingestionQueueItem;
    }

    public function setIngestionQueueItem(IngestionQueueItem $ingestionQueueItem): static
    {
        // set the owning side of the relation if necessary
        if ($ingestionQueueItem->getDocumentNode() !== $this) {
            $ingestionQueueItem->setDocumentNode($this);
        }

        $this->ingestionQueueItem = $ingestionQueueItem;

        return $this;
    }

    public function getDeletedAt(): ?\DateTimeInterface
    {
        return $this->deletedAt;
    }

    public function setDeletedAt(?\DateTimeInterface $deletedAt): self
    {
        $this->deletedAt = $deletedAt;

        return $this;
    }
}
