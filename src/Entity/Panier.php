<?php

namespace App\Entity;

use App\Repository\PanierRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PanierRepository::class)]
#[ORM\Table(name: 'panier', indexes: [
    new ORM\Index(name: 'fk_panier_lieu', columns: ['id_lieu']),
])]
class Panier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_panier', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'paniers')]
    #[ORM\JoinColumn(name: 'id_utilisateur', referencedColumnName: 'id', nullable: true, onDelete: 'CASCADE')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\ManyToOne(targetEntity: LieuTouristique::class)]
    #[ORM\JoinColumn(name: 'id_lieu', referencedColumnName: 'id_lieu', nullable: true, onDelete: 'CASCADE')]
    private ?LieuTouristique $lieuTouristique = null;

    #[ORM\ManyToOne(targetEntity: Etablissement::class)]
    #[ORM\JoinColumn(name: 'id_etablissement', referencedColumnName: 'idEtablissement', nullable: true, onDelete: 'CASCADE')]
    private ?Etablissement $etablissement = null;

    #[ORM\Column(name: 'session_id', type: 'string', length: 255)]
    private ?string $sessionId = null;

    #[ORM\Column(name: 'type_service', type: 'string', length: 50, options: ['default' => 'Visite'])]
    private ?string $typeService = 'Visite';

    #[ORM\Column(name: 'date_debut', type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de début est obligatoire')]
    #[Assert\GreaterThanOrEqual('today', message: 'La date de début doit être aujourd\'hui ou plus tard')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'date_fin', type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de fin est obligatoire')]
    #[Assert\GreaterThan(propertyPath: 'dateDebut', message: 'La date de fin doit être après la date de début')]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(name: 'nb_personnes', type: 'integer', options: ['default' => 1])]
    #[Assert\NotBlank(message: 'Le nombre de personnes est obligatoire')]
    #[Assert\Positive(message: 'Le nombre de personnes doit être positif')]
    private ?int $nbPersonnes = 1;

    #[ORM\Column(name: 'nb_adultes', type: 'integer', options: ['default' => 1])]
    #[Assert\NotBlank(message: 'Le nombre d\'adultes est obligatoire')]
    #[Assert\Positive(message: 'Le nombre d\'adultes doit être positif')]
    #[Assert\LessThanOrEqual(value: 20, message: 'Le nombre d\'adultes ne peut dépasser 20')]
    private ?int $nbAdultes = 1;

    #[ORM\Column(name: 'nb_enfants', type: 'integer', options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Le nombre d\'enfants ne peut pas être négatif')]
    #[Assert\LessThanOrEqual(value: 20, message: 'Le nombre d\'enfants ne peut dépasser 20')]
    private ?int $nbEnfants = 0;

    #[ORM\Column(name: 'prix_estime', type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le prix estimé est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le prix doit être positif ou zéro')]
    private ?string $prixEstime = null;

    #[ORM\Column(name: 'statut_item', type: 'string', length: 50, options: ['default' => 'en_attente'])]
    private ?string $statutItem = 'en_attente';

    #[ORM\Column(name: 'created_at', type: Types::DATETIME_MUTABLE, options: ['default' => 'CURRENT_TIMESTAMP'])]
    private ?\DateTimeInterface $createdAt = null;

    public function __construct()
    {
        $this->createdAt = new \DateTime();
        $this->statutItem = 'en_attente';
        $this->typeService = 'Visite';
        $this->nbPersonnes = 1;
        $this->nbAdultes = 1;
        $this->nbEnfants = 0;
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getUtilisateur(): ?Utilisateur
    {
        return $this->utilisateur;
    }

    public function setUtilisateur(?Utilisateur $utilisateur): self
    {
        $this->utilisateur = $utilisateur;

        return $this;
    }

    public function getLieuTouristique(): ?LieuTouristique
    {
        return $this->lieuTouristique;
    }

    public function setLieuTouristique(?LieuTouristique $lieuTouristique): static
    {
        $this->lieuTouristique = $lieuTouristique;
        return $this;
    }

    public function getEtablissement(): ?Etablissement
    {
        return $this->etablissement;
    }

    public function setEtablissement(?Etablissement $etablissement): static
    {
        $this->etablissement = $etablissement;
        return $this;
    }

    public function getSessionId(): ?string
    {
        return $this->sessionId;
    }

    public function setSessionId(?string $sessionId): static
    {
        $this->sessionId = $sessionId;
        return $this;
    }

    public function getTypeService(): ?string
    {
        return $this->typeService;
    }

    public function setTypeService(?string $typeService): static
    {
        $this->typeService = $typeService;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): static
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): static
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getNbPersonnes(): ?int
    {
        return $this->nbPersonnes;
    }

    public function setNbPersonnes(?int $nbPersonnes): static
    {
        $this->nbPersonnes = $nbPersonnes;
        return $this;
    }

    public function getNbAdultes(): ?int
    {
        return $this->nbAdultes;
    }

    public function setNbAdultes(?int $nbAdultes): static
    {
        $this->nbAdultes = $nbAdultes;
        return $this;
    }

    public function getNbEnfants(): ?int
    {
        return $this->nbEnfants;
    }

    public function setNbEnfants(?int $nbEnfants): static
    {
        $this->nbEnfants = $nbEnfants;
        return $this;
    }

    public function getPrixEstime(): ?string
    {
        return $this->prixEstime;
    }

    public function setPrixEstime(?string $prixEstime): static
    {
        $this->prixEstime = $prixEstime;
        return $this;
    }

    public function getStatutItem(): ?string
    {
        return $this->statutItem;
    }

    public function setStatutItem(?string $statutItem): static
    {
        $this->statutItem = $statutItem;
        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->createdAt;
    }

    public function setCreatedAt(?\DateTimeInterface $createdAt): static
    {
        $this->createdAt = $createdAt;
        return $this;
    }

    /**
     * Calculate number of days for the reservation
     */
    public function getNbJours(): int
    {
        if ($this->dateDebut && $this->dateFin) {
            $diff = $this->dateDebut->diff($this->dateFin);
            return max(1, $diff->days);
        }
        return 1;
    }

    /**
     * Get status badge class for display
     */
    public function getStatutBadgeClass(): string
    {
        return match($this->statutItem) {
            'en_attente' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
            'confirmé' => 'bg-green-500/10 text-green-400 border-green-500/20',
            'annulé' => 'bg-red-500/10 text-red-400 border-red-500/20',
            default => 'bg-gray-500/10 text-gray-400 border-gray-500/20',
        };
    }

    /**
     * Get status label in French
     */
    public function getStatutLabel(): string
    {
        return match($this->statutItem) {
            'en_attente' => 'En attente',
            'confirmé' => 'Confirmé',
            'annulé' => 'Annulé',
            default => $this->statutItem,
        };
    }
}
