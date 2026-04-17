<?php

declare(strict_types=1);

namespace App\Entity;

use App\Repository\NotificationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: NotificationRepository::class)]
#[ORM\Table(name: 'notifications')]
#[ORM\HasLifecycleCallbacks]
class Notification
{
    public const TYPE_COMMENT_ON_POST = 'comment_on_post';
    public const TYPE_REACTION_ON_POST = 'reaction_on_post';
    public const TYPE_REACTION_ON_COMMENT = 'reaction_on_comment';
    public const TYPE_BAD_WORD_DETECTED = 'bad_word_detected';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'recipient_key', length: 255)]
    private string $recipientKey = '';

    #[ORM\Column(name: 'actor_key', length: 255)]
    private string $actorKey = '';

    #[ORM\Column(name: 'type', length: 64)]
    private string $type = self::TYPE_COMMENT_ON_POST;

    #[ORM\Column(type: Types::TEXT)]
    private string $message = '';

    #[ORM\ManyToOne(targetEntity: Post::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Post $post = null;

    #[ORM\ManyToOne(targetEntity: Comment::class)]
    #[ORM\JoinColumn(name: 'comment_id', referencedColumnName: 'id', nullable: true, onDelete: 'SET NULL')]
    private ?Comment $comment = null;

    #[ORM\Column(name: 'is_read', options: ['default' => 0])]
    private bool $isRead = false;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRecipientKey(): string
    {
        return $this->recipientKey;
    }

    public function setRecipientKey(string $recipientKey): static
    {
        $this->recipientKey = trim($recipientKey);

        return $this;
    }

    public function getActorKey(): string
    {
        return $this->actorKey;
    }

    public function setActorKey(string $actorKey): static
    {
        $this->actorKey = trim($actorKey);

        return $this;
    }

    public function getType(): string
    {
        return $this->type;
    }

    public function setType(string $type): static
    {
        $this->type = $type;

        return $this;
    }

    public function getMessage(): string
    {
        return $this->message;
    }

    public function setMessage(string $message): static
    {
        $this->message = trim($message);

        return $this;
    }

    public function getPost(): ?Post
    {
        return $this->post;
    }

    public function setPost(?Post $post): static
    {
        $this->post = $post;

        return $this;
    }

    public function getComment(): ?Comment
    {
        return $this->comment;
    }

    public function setComment(?Comment $comment): static
    {
        $this->comment = $comment;

        return $this;
    }

    public function isRead(): bool
    {
        return $this->isRead;
    }

    public function setIsRead(bool $isRead): static
    {
        $this->isRead = $isRead;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeImmutable
    {
        return $this->createdAt;
    }

    public function setCreatedAt(\DateTimeImmutable $createdAt): static
    {
        $this->createdAt = $createdAt;

        return $this;
    }

    #[ORM\PrePersist]
    public function touchCreatedAt(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
}
