<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation', indexes: [
    new ORM\Index(name: 'fk_reservation_panier', columns: ['id_panier']),
])]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_reservation', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Panier::class)]
    #[ORM\JoinColumn(name: 'id_panier', referencedColumnName: 'id_panier', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Le panier est obligatoire')]
    private ?Panier $panier = null;

    #[ORM\Column(name: 'date_paiement', type: Types::DATETIME_MUTABLE)]
    #[Assert\NotBlank(message: 'La date de paiement est obligatoire')]
    private ?\DateTimeInterface $datePaiement = null;

    #[ORM\Column(name: 'montant_total', type: Types::DECIMAL, precision: 10, scale: 2)]
    #[Assert\NotBlank(message: 'Le montant total est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le montant doit être positif ou zéro')]
    private ?string $montantTotal = null;

    #[ORM\Column(name: 'mode_paiement', type: 'string', length: 50)]
    #[Assert\NotBlank(message: 'Le mode de paiement est obligatoire')]
    #[Assert\Choice(choices: ['Carte', 'Espèces', 'Virement', 'PayPal'], message: 'Mode de paiement invalide')]
    private ?string $modePaiement = null;

    #[ORM\Column(name: 'statut_paiement', type: 'string', length: 50, options: ['default' => 'En cours de paiement'])]
    private ?string $statutPaiement = 'En cours de paiement';

    #[ORM\Column(name: 'code_confirmation', type: 'string', length: 50)]
    private ?string $codeConfirmation = null;

    #[ORM\Column(name: 'rating', type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'La note doit être entre {{ min }} et {{ max }}')]
    private ?int $rating = null;

    #[ORM\Column(name: 'review_comment', type: Types::TEXT, nullable: true)]
    private ?string $reviewComment = null;

    public function __construct()
    {
        $this->datePaiement = new \DateTime();
        $this->statutPaiement = 'En cours de paiement';
        $this->codeConfirmation = $this->generateConfirmationCode();
    }

    /**
     * Generate a unique confirmation code
     */
    private function generateConfirmationCode(): string
    {
        return 'RES-' . strtoupper(substr(bin2hex(random_bytes(4)), 0, 8));
    }

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getPanier(): ?Panier
    {
        return $this->panier;
    }

    public function setPanier(?Panier $panier): static
    {
        $this->panier = $panier;
        return $this;
    }

    public function getDatePaiement(): ?\DateTimeInterface
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(?\DateTimeInterface $datePaiement): static
    {
        $this->datePaiement = $datePaiement;
        return $this;
    }

    public function getMontantTotal(): ?string
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(?string $montantTotal): static
    {
        $this->montantTotal = $montantTotal;
        return $this;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?string $modePaiement): static
    {
        $this->modePaiement = $modePaiement;
        return $this;
    }

    public function getStatutPaiement(): ?string
    {
        return $this->statutPaiement;
    }

    public function setStatutPaiement(?string $statutPaiement): static
    {
        $this->statutPaiement = $statutPaiement;
        return $this;
    }

    public function getCodeConfirmation(): ?string
    {
        return $this->codeConfirmation;
    }

    public function setCodeConfirmation(?string $codeConfirmation): static
    {
        $this->codeConfirmation = $codeConfirmation;
        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): static
    {
        $this->rating = $rating;
        return $this;
    }

    public function getReviewComment(): ?string
    {
        return $this->reviewComment;
    }

    public function setReviewComment(?string $reviewComment): static
    {
        $this->reviewComment = $reviewComment;
        return $this;
    }

    /**
     * Get status badge class for display
     */
    public function getStatutBadgeClass(): string
    {
        return match($this->statutPaiement) {
            'Payé' => 'bg-green-500/10 text-green-400 border-green-500/20',
            'En cours de paiement' => 'bg-yellow-500/10 text-yellow-400 border-yellow-500/20',
            'Remboursé' => 'bg-blue-500/10 text-blue-400 border-blue-500/20',
            'Annulé' => 'bg-red-500/10 text-red-400 border-red-500/20',
            default => 'bg-gray-500/10 text-gray-400 border-gray-500/20',
        };
    }

    /**
     * Get payment mode icon
     */
    public function getModePaiementIcon(): string
    {
        return match($this->modePaiement) {
            'Carte' => 'fa-credit-card',
            'Espèces' => 'fa-money-bill-wave',
            'Virement' => 'fa-building-columns',
            'PayPal' => 'fa-paypal',
            default => 'fa-wallet',
        };
    }

    /**
     * Check if the reservation can be rated
     */
    public function canBeRated(): bool
    {
        return $this->statutPaiement === 'Payé' && $this->rating === null;
    }

    /**
     * Get star rating display
     */
    public function getStarRating(): string
    {
        if ($this->rating === null) {
            return 'Non évalué';
        }
        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }
}
