<?php

namespace App\Entity;

use App\Repository\RepositoryConfigRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: RepositoryConfigRepository::class)]
#[ORM\Table(name: 'repository_config')]
class RepositoryConfig
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $owner;

    #[ORM\Column(length: 255)]
    #[Assert\NotBlank]
    private string $name;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $defaultBranch = null;

    #[ORM\Column(length: 2048)]
    #[Assert\NotBlank]
    private string $token = '';

    #[ORM\Column(type: 'datetime_immutable', nullable: true)]
    private ?\DateTimeImmutable $lastSyncAt = null;

    #[ORM\Column(length: 64, nullable: true)]
    private ?string $lastSyncStatus = null;

    #[ORM\Column(type: 'text', nullable: true)]
    private ?string $lastSyncMessage = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getOwner(): string
    {
        return $this->owner;
    }

    public function setOwner(string $owner): self
    {
        $this->owner = $owner;
        return $this;
    }

    public function getName(): string
    {
        return $this->name;
    }

    public function setName(string $name): self
    {
        $this->name = $name;
        return $this;
    }

    public function getDefaultBranch(): ?string
    {
        return $this->defaultBranch;
    }

    public function setDefaultBranch(?string $defaultBranch): self
    {
        $this->defaultBranch = $defaultBranch;
        return $this;
    }

    public function getEncryptedToken(): string
    {
        return $this->token;
    }

    public function setEncryptedToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getToken(): string
    {
        return $this->token;
    }

    public function setToken(string $token): self
    {
        $this->token = $token;
        return $this;
    }

    public function getLastSyncAt(): ?\DateTimeImmutable
    {
        return $this->lastSyncAt;
    }

    public function setLastSyncAt(?\DateTimeImmutable $lastSyncAt): self
    {
        $this->lastSyncAt = $lastSyncAt;
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

    public function getLastSyncMessage(): ?string
    {
        return $this->lastSyncMessage;
    }

    public function setLastSyncMessage(?string $lastSyncMessage): self
    {
        $this->lastSyncMessage = $lastSyncMessage;
        return $this;
    }

    public function getRepositorySlug(): string
    {
        return sprintf('%s/%s', $this->owner, $this->name);
    }

    public function getRedactedToken(?string $plainToken = null): string
    {
        $token = $plainToken ?? $this->token;
        $length = strlen($token);
        if ($length <= 8) {
            return str_repeat('*', $length);
        }

        return substr($token, 0, 4) . str_repeat('*', max(4, $length - 8)) . substr($token, -4);
    }
}
