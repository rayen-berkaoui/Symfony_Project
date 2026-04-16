<?php

namespace App\Entity;

use App\Repository\PanierRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: PanierRepository::class)]
#[ORM\Table(name: 'panier')]
class Panier
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_panier', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Utilisateur::class, inversedBy: 'paniers')]
    #[ORM\JoinColumn(name: 'id_client', referencedColumnName: 'id', nullable: false, onDelete: 'CASCADE')]
    private ?Utilisateur $utilisateur = null;

    #[ORM\Column(name: 'id_etablissement', type: 'integer')]
    private ?int $serviceId = null;

    #[ORM\Column(name: 'type_service', type: 'string', length: 50)]
    private ?string $typeService = 'Lieu';

    #[ORM\Column(name: 'date_debut', type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de début est obligatoire')]
    private ?\DateTimeInterface $dateDebut = null;

    #[ORM\Column(name: 'date_fin', type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de fin est obligatoire')]
    #[Assert\GreaterThan(propertyPath: 'dateDebut', message: 'La date de fin doit être après la date de début')]
    private ?\DateTimeInterface $dateFin = null;

    #[ORM\Column(name: 'nb_personnes', type: 'integer')]
    #[Assert\Positive(message: 'Le nombre de personnes doit être positif')]
    private ?int $nbPersonnes = 1;

    #[ORM\Column(name: 'prix_estime', type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\PositiveOrZero(message: 'Le prix doit être positif ou zéro')]
    private ?string $prixEstime = '0.00';

    #[ORM\Column(name: 'statut_item', type: 'string', length: 50)]
    private ?string $statutItem = 'en_attente';

    #[ORM\Column(name: 'nb_adultes', type: 'integer', options: ['default' => 1])]
    #[Assert\Positive(message: 'Le nombre d\'adultes doit être positif')]
    private ?int $nbAdultes = 1;

    #[ORM\Column(name: 'nb_enfants', type: 'integer', options: ['default' => 0])]
    #[Assert\PositiveOrZero(message: 'Le nombre d\'enfants ne peut pas être négatif')]
    private ?int $nbEnfants = 0;

    #[ORM\Column(name: 'nb_chambres', type: 'integer', options: ['default' => 1])]
    #[Assert\Positive(message: 'Le nombre de chambres doit être positif')]
    private ?int $nbChambres = 1;

    private ?string $displayName = null;
    private ?string $displayCity = null;
    private ?string $displayImage = null;
    private ?string $displayKind = null;
    private ?string $displayReference = null;

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

    public function getServiceId(): ?int
    {
        return $this->serviceId;
    }

    public function setServiceId(?int $serviceId): self
    {
        $this->serviceId = $serviceId;
        return $this;
    }

    public function getTypeService(): ?string
    {
        return $this->typeService;
    }

    public function setTypeService(?string $typeService): self
    {
        $this->typeService = $typeService;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->dateDebut;
    }

    public function setDateDebut(?\DateTimeInterface $dateDebut): self
    {
        $this->dateDebut = $dateDebut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->dateFin;
    }

    public function setDateFin(?\DateTimeInterface $dateFin): self
    {
        $this->dateFin = $dateFin;
        return $this;
    }

    public function getNbPersonnes(): ?int
    {
        return $this->nbPersonnes;
    }

    public function setNbPersonnes(?int $nbPersonnes): self
    {
        $this->nbPersonnes = $nbPersonnes;
        return $this;
    }

    public function getPrixEstime(): ?string
    {
        return $this->prixEstime;
    }

    public function setPrixEstime(?string $prixEstime): self
    {
        $this->prixEstime = $prixEstime;
        return $this;
    }

    public function getStatutItem(): ?string
    {
        return $this->statutItem;
    }

    public function setStatutItem(?string $statutItem): self
    {
        $this->statutItem = $statutItem;
        return $this;
    }

    public function getNbAdultes(): ?int
    {
        return $this->nbAdultes;
    }

    public function setNbAdultes(?int $nbAdultes): self
    {
        $this->nbAdultes = $nbAdultes;
        return $this;
    }

    public function getNbEnfants(): ?int
    {
        return $this->nbEnfants;
    }

    public function setNbEnfants(?int $nbEnfants): self
    {
        $this->nbEnfants = $nbEnfants;
        return $this;
    }

    public function getNbChambres(): ?int
    {
        return $this->nbChambres;
    }

    public function setNbChambres(?int $nbChambres): self
    {
        $this->nbChambres = $nbChambres;
        return $this;
    }

    public function syncPeopleCount(): self
    {
        $this->nbPersonnes = max(1, (int) $this->nbAdultes + (int) $this->nbEnfants);
        return $this;
    }

    public function getNbJours(): int
    {
        if ($this->dateDebut && $this->dateFin) {
            $diff = $this->dateDebut->diff($this->dateFin);
            return max(1, (int) $diff->days);
        }

        return 1;
    }

    public function getNormalizedStatutItem(): string
    {
        $value = strtolower((string) $this->statutItem);

        if (str_contains($value, 'conf')) {
            return 'confirme';
        }

        if (str_contains($value, 'annul')) {
            return 'annule';
        }

        return 'en_attente';
    }

    public function getStatutBadgeClass(): string
    {
        return match ($this->getNormalizedStatutItem()) {
            'confirme' => 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20',
            'annule' => 'bg-rose-500/10 text-rose-300 border-rose-500/20',
            default => 'bg-amber-500/10 text-amber-300 border-amber-500/20',
        };
    }

    public function getStatutLabel(): string
    {
        return match ($this->getNormalizedStatutItem()) {
            'confirme' => 'Confirmé',
            'annule' => 'Annulé',
            default => 'En attente',
        };
    }

    public function isLieuLikeService(): bool
    {
        return in_array($this->typeService, ['Voyage', 'Lieu'], true);
    }

    public function getServiceTypeLabel(): string
    {
        return match ($this->typeService) {
            'Voyage' => 'Lieu touristique',
            'Lieu' => 'Lieu',
            'Hotel' => 'Hôtel',
            'Restaurant' => 'Restaurant',
            'Cafe', 'CafÚ' => 'Café',
            'Spa' => 'Spa',
            default => (string) $this->typeService,
        };
    }

    public function setDisplayData(?string $name, ?string $city = null, ?string $image = null, ?string $kind = null, ?string $reference = null): self
    {
        $this->displayName = $name;
        $this->displayCity = $city;
        $this->displayImage = $image;
        $this->displayKind = $kind;
        $this->displayReference = $reference;
        return $this;
    }

    public function getDisplayName(): string
    {
        return $this->displayName ?: sprintf('%s #%d', $this->getServiceTypeLabel(), (int) $this->serviceId);
    }

    public function getDisplayCity(): ?string
    {
        return $this->displayCity;
    }

    public function getDisplayImage(): ?string
    {
        if (!$this->displayImage) {
            return null;
        }

        if (preg_match('/^[A-Za-z]:\\\\/', $this->displayImage) === 1) {
            return null;
        }

        return $this->displayImage;
    }

    public function getDisplayKind(): string
    {
        return $this->displayKind ?: $this->getServiceTypeLabel();
    }

    public function getDisplayReference(): string
    {
        return $this->displayReference ?: sprintf('REF-%d', (int) $this->serviceId);
    }
}
