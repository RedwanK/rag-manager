<?php

namespace App\Entity;

use App\Repository\ConversationRepository;
use DateTimeImmutable;
use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\ORM\Mapping as ORM;
use Gedmo\Mapping\Annotation as Gedmo;
use Gedmo\SoftDeleteable\Traits\SoftDeleteableEntity;
use Gedmo\Timestampable\Traits\TimestampableEntity;

#[ORM\Entity(repositoryClass: ConversationRepository::class)]
#[ORM\Table(name: 'conversation')]
#[Gedmo\SoftDeleteable(fieldName: 'deletedAt')]
#[ORM\Index(columns: ['user_id', 'last_activity_at'], name: 'conversation_user_last_activity_idx')]
class Conversation
{
    use TimestampableEntity;
    use SoftDeleteableEntity;

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: User::class)]
    #[ORM\JoinColumn(nullable: false, onDelete: 'CASCADE')]
    private User $user;

    #[ORM\Column(length: 255)]
    private string $title = 'Nouvelle discussion';

    #[ORM\Column(type: 'datetime_immutable')]
    private DateTimeImmutable $lastActivityAt;

    /**
     * @var Collection<int, ConversationMessage>
     */
    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: ConversationMessage::class, orphanRemoval: true)]
    #[ORM\OrderBy(['createdAt' => 'ASC'])]
    private Collection $messages;

    /**
     * @var Collection<int, LightRagRequestLog>
     */
    #[ORM\OneToMany(mappedBy: 'conversation', targetEntity: LightRagRequestLog::class, orphanRemoval: true)]
    private Collection $lightRagRequestLogs;

    public function __construct()
    {
        $this->lastActivityAt = new DateTimeImmutable();
        $this->messages = new ArrayCollection();
        $this->lightRagRequestLogs = new ArrayCollection();
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getTitle(): string
    {
        return $this->title;
    }

    public function setTitle(string $title): self
    {
        $this->title = $title;

        return $this;
    }

    public function getLastActivityAt(): DateTimeImmutable
    {
        return $this->lastActivityAt;
    }

    public function setLastActivityAt(DateTimeImmutable $lastActivityAt): self
    {
        $this->lastActivityAt = $lastActivityAt;

        return $this;
    }

    /**
     * @return Collection<int, ConversationMessage>
     */
    public function getMessages(): Collection
    {
        return $this->messages;
    }

    public function addMessage(ConversationMessage $message): self
    {
        if (!$this->messages->contains($message)) {
            $this->messages->add($message);
            $message->setConversation($this);
        }

        return $this;
    }

    public function removeMessage(ConversationMessage $message): self
    {
        if ($this->messages->removeElement($message)) {
            if ($message->getConversation() === $this) {
                $message->setConversation($this);
            }
        }

        return $this;
    }

    /**
     * @return Collection<int, LightRagRequestLog>
     */
    public function getLightRagRequestLogs(): Collection
    {
        return $this->lightRagRequestLogs;
    }

    public function addLightRagRequestLog(LightRagRequestLog $requestLog): self
    {
        if (!$this->lightRagRequestLogs->contains($requestLog)) {
            $this->lightRagRequestLogs->add($requestLog);
            $requestLog->setConversation($this);
        }

        return $this;
    }

    public function removeLightRagRequestLog(LightRagRequestLog $requestLog): self
    {
        if ($this->lightRagRequestLogs->removeElement($requestLog)) {
            if ($requestLog->getConversation() === $this) {
                $requestLog->setConversation($this);
            }
        }

        return $this;
    }
}
