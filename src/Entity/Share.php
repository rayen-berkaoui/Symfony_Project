<?php

namespace App\Entity;

use App\Repository\ShareRepository;
use App\Validation\ValidationLimits;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ShareRepository::class)]
#[ORM\Table(name: 'shares')]
#[ORM\HasLifecycleCallbacks]
class Share
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Post::class)]
    #[ORM\JoinColumn(name: 'post_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Post $post = null;

    #[ORM\Column(name: 'user_key', length: 255)]
    #[Assert\NotBlank(message: 'L’identifiant utilisateur est obligatoire.')]
    #[Assert\Length(max: ValidationLimits::USER_KEY_MAX)]
    #[Assert\Regex(pattern: ValidationLimits::USER_KEY_PATTERN, message: 'Identifiant : lettres, chiffres, _, -, . uniquement (1–255 caractères).')]
    private ?string $userKey = null;

    #[ORM\Column(length: 100)]
    #[Assert\NotBlank(message: 'La plateforme est obligatoire.')]
    #[Assert\Choice(choices: ValidationLimits::SHARE_PLATFORMS, message: 'Plateforme de partage non reconnue.')]
    private ?string $platform = null;

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_IMMUTABLE)]
    private ?\DateTimeImmutable $createdAt = null;

    public function getId(): ?int
    {
        return $this->id;
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

    public function getUserKey(): ?string
    {
        return $this->userKey;
    }

    public function setUserKey(string $userKey): static
    {
        $this->userKey = $userKey;

        return $this;
    }

    public function getPlatform(): ?string
    {
        return $this->platform;
    }

    public function setPlatform(string $platform): static
    {
        $this->platform = $platform;

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
