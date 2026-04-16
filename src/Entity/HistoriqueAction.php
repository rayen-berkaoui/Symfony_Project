<?php

namespace App\Entity;

use App\Repository\HistoriqueActionRepository;
use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity(repositoryClass: HistoriqueActionRepository::class)]
#[ORM\Table(name: 'historique_action')]
#[ORM\Index(columns: ['created_at'], name: 'idx_historique_action_created_at')]
#[ORM\Index(columns: ['route_name'], name: 'idx_historique_action_route_name')]
class HistoriqueAction
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class)]
    #[ORM\JoinColumn(name: 'utilisateur_id', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(name: 'action_label', type: 'string', length: 255)]
    private ?string $actionLabel = null;

    #[ORM\Column(name: 'route_name', type: 'string', length: 180, nullable: true)]
    private ?string $routeName = null;

    #[ORM\Column(name: 'http_method', type: 'string', length: 10)]
    private ?string $httpMethod = null;

    #[ORM\Column(name: 'path_info', type: 'string', length: 255)]
    private ?string $pathInfo = null;

    #[ORM\Column(name: 'status_code', type: 'integer')]
    private int $statusCode = 200;

    #[ORM\Column(name: 'details', type: 'text', nullable: true)]
    private ?string $details = null;

    #[ORM\Column(name: 'created_at', type: 'datetime_immutable')]
    private ?\DateTimeImmutable $createdAt = null;

    public function __construct()
    {
        if ($this->createdAt === null) {
            $this->createdAt = new \DateTimeImmutable();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(Utilisateur $utilisateur): static
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getActionLabel(): ?string
    {
        return $this->actionLabel;
    }

    public function setActionLabel(string $actionLabel): static
    {
        $this->actionLabel = $actionLabel;

        return $this;
    }

    public function getRouteName(): ?string
    {
        return $this->routeName;
    }

    public function setRouteName(?string $routeName): static
    {
        $this->routeName = $routeName;

        return $this;
    }

    public function getHttpMethod(): ?string
    {
        return $this->httpMethod;
    }

    public function setHttpMethod(string $httpMethod): static
    {
        $this->httpMethod = strtoupper($httpMethod);

        return $this;
    }

    public function getPathInfo(): ?string
    {
        return $this->pathInfo;
    }

    public function setPathInfo(string $pathInfo): static
    {
        $this->pathInfo = $pathInfo;

        return $this;
    }

    public function getStatusCode(): int
    {
        return $this->statusCode;
    }

    public function setStatusCode(int $statusCode): static
    {
        $this->statusCode = $statusCode;

        return $this;
    }

    public function getDetails(): ?string
    {
        return $this->details;
    }

    public function setDetails(?string $details): static
    {
        $this->details = $details;

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
}