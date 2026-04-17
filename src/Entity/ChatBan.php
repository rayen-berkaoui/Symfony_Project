<?php

namespace App\Entity;

use App\Repository\ChatBanRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ChatBanRepository::class)]
#[ORM\Table(name: 'chat_bans')]
#[ORM\UniqueConstraint(name: 'uniq_chat_bans_user_key', columns: ['user_key'])]
#[ORM\HasLifecycleCallbacks]
class ChatBan
{
    public const SCOPE_CHAT = 'chat';
    public const SCOPE_POSTS = 'posts';
    public const SCOPE_COMMENTS = 'comments';
    public const SCOPE_REACTIONS = 'reactions';
    public const SCOPE_SHARES = 'shares';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\Column(name: 'user_key', length: 255)]
    #[Assert\NotBlank]
    #[Assert\Length(max: 255)]
    private ?string $userKey = null;

    #[ORM\Column(length: 500, nullable: true)]
    #[Assert\Length(max: 500)]
    private ?string $reason = null;

    #[ORM\Column(name: 'expires_at', type: Types::DATETIME_IMMUTABLE, nullable: true)]
    private ?\DateTimeImmutable $expiresAt = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    #[ORM\Column(name: 'block_chat', options: ['default' => true])]
    private bool $blockChat = true;

    #[ORM\Column(name: 'block_posts', options: ['default' => true])]
    private bool $blockPosts = true;

    #[ORM\Column(name: 'block_comments', options: ['default' => true])]
    private bool $blockComments = true;

    #[ORM\Column(name: 'block_reactions', options: ['default' => true])]
    private bool $blockReactions = true;

    #[ORM\Column(name: 'block_shares', options: ['default' => true])]
    private bool $blockShares = true;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUserKey(): ?string
    {
        return $this->userKey;
    }

    public function setUserKey(string $userKey): static
    {
        $this->userKey = $userKey;

        return $this;
    }

    public function getReason(): ?string
    {
        return $this->reason;
    }

    public function setReason(?string $reason): static
    {
        $this->reason = $reason;

        return $this;
    }

    public function getExpiresAt(): ?\DateTimeImmutable
    {
        return $this->expiresAt;
    }

    public function setExpiresAt(?\DateTimeImmutable $expiresAt): static
    {
        $this->expiresAt = $expiresAt;

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

    public function isBlockChat(): bool
    {
        return $this->blockChat;
    }

    public function setBlockChat(bool $blockChat): static
    {
        $this->blockChat = $blockChat;

        return $this;
    }

    public function isBlockPosts(): bool
    {
        return $this->blockPosts;
    }

    public function setBlockPosts(bool $blockPosts): static
    {
        $this->blockPosts = $blockPosts;

        return $this;
    }

    public function isBlockComments(): bool
    {
        return $this->blockComments;
    }

    public function setBlockComments(bool $blockComments): static
    {
        $this->blockComments = $blockComments;

        return $this;
    }

    public function isBlockReactions(): bool
    {
        return $this->blockReactions;
    }

    public function setBlockReactions(bool $blockReactions): static
    {
        $this->blockReactions = $blockReactions;

        return $this;
    }

    public function isBlockShares(): bool
    {
        return $this->blockShares;
    }

    public function setBlockShares(bool $blockShares): static
    {
        $this->blockShares = $blockShares;

        return $this;
    }

    public function blocksScope(string $scope): bool
    {
        return match ($scope) {
            self::SCOPE_CHAT => $this->blockChat,
            self::SCOPE_POSTS => $this->blockPosts,
            self::SCOPE_COMMENTS => $this->blockComments,
            self::SCOPE_REACTIONS => $this->blockReactions,
            self::SCOPE_SHARES => $this->blockShares,
            default => false,
        };
    }

    /**
     * @return list<string>
     */
    public function getEnabledScopes(): array
    {
        $scopes = [];
        if ($this->blockChat) {
            $scopes[] = self::SCOPE_CHAT;
        }
        if ($this->blockPosts) {
            $scopes[] = self::SCOPE_POSTS;
        }
        if ($this->blockComments) {
            $scopes[] = self::SCOPE_COMMENTS;
        }
        if ($this->blockReactions) {
            $scopes[] = self::SCOPE_REACTIONS;
        }
        if ($this->blockShares) {
            $scopes[] = self::SCOPE_SHARES;
        }

        return $scopes;
    }

    #[ORM\PrePersist]
    public function touchCreatedAt(): void
    {
        if (null === $this->createdAt) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }
}
