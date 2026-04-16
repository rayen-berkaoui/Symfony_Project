<?php

namespace App\Entity;

use App\Repository\ReservationRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Doctrine\ORM\EntityNotFoundException;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: ReservationRepository::class)]
#[ORM\Table(name: 'reservation')]
class Reservation
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_reservation', type: 'integer')]
    private ?int $id = null;

    #[ORM\ManyToOne(targetEntity: Panier::class)]
    #[ORM\JoinColumn(name: 'id_panier', referencedColumnName: 'id_panier', nullable: true, onDelete: 'SET NULL')]
    private ?Panier $panier = null;

    #[ORM\Column(name: 'date_paiement', type: Types::DATETIME_MUTABLE)]
    private ?\DateTimeInterface $datePaiement = null;

    #[ORM\Column(name: 'montant_total', type: Types::DECIMAL, precision: 10, scale: 2)]
    private ?string $montantTotal = '0.00';

    #[ORM\Column(name: 'mode_paiement', type: 'string', length: 50)]
    #[Assert\Choice(choices: ['Paymee', 'Especes'], message: 'Mode de paiement invalide')]
    private ?string $modePaiement = null;

    #[ORM\Column(name: 'statut_paiement', type: 'string', length: 50)]
    private ?string $statutPaiement = 'En attente';

    #[ORM\Column(name: 'code_confirmation', type: 'string', length: 255)]
    private ?string $codeConfirmation = null;

    #[ORM\Column(name: 'rating', type: 'integer', nullable: true)]
    #[Assert\Range(min: 1, max: 5, notInRangeMessage: 'La note doit être entre {{ min }} et {{ max }}')]
    private ?int $rating = null;

    #[ORM\Column(name: 'review_comment', type: Types::TEXT, nullable: true)]
    private ?string $reviewComment = null;

    public function __construct()
    {
        $this->datePaiement = new \DateTime();
        $this->codeConfirmation = $this->generateConfirmationCode();
    }

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
        try {
            return $this->panier;
        } catch (EntityNotFoundException) {
            $this->panier = null;
            return null;
        }
    }

    public function setPanier(?Panier $panier): self
    {
        $this->panier = $panier;
        return $this;
    }

    public function getDatePaiement(): ?\DateTimeInterface
    {
        return $this->datePaiement;
    }

    public function setDatePaiement(?\DateTimeInterface $datePaiement): self
    {
        $this->datePaiement = $datePaiement;
        return $this;
    }

    public function getMontantTotal(): ?string
    {
        return $this->montantTotal;
    }

    public function setMontantTotal(?string $montantTotal): self
    {
        $this->montantTotal = $montantTotal;
        return $this;
    }

    public function getModePaiement(): ?string
    {
        return $this->modePaiement;
    }

    public function setModePaiement(?string $modePaiement): self
    {
        $this->modePaiement = $modePaiement;
        return $this;
    }

    public function getStatutPaiement(): ?string
    {
        return $this->statutPaiement;
    }

    public function setStatutPaiement(?string $statutPaiement): self
    {
        $this->statutPaiement = $statutPaiement;
        return $this;
    }

    public function getCodeConfirmation(): ?string
    {
        return $this->codeConfirmation;
    }

    public function setCodeConfirmation(?string $codeConfirmation): self
    {
        $this->codeConfirmation = $codeConfirmation;
        return $this;
    }

    public function getRating(): ?int
    {
        return $this->rating;
    }

    public function setRating(?int $rating): self
    {
        $this->rating = $rating;
        return $this;
    }

    public function getReviewComment(): ?string
    {
        return $this->reviewComment;
    }

    public function setReviewComment(?string $reviewComment): self
    {
        $this->reviewComment = $reviewComment;
        return $this;
    }

    public function getNormalizedStatutPaiement(): string
    {
        $value = strtolower((string) $this->statutPaiement);

        if (str_contains($value, 'pay')) {
            return 'Paye';
        }
        if (str_contains($value, 'cours')) {
            return 'En cours de paiement';
        }
        if (str_contains($value, 'rembours')) {
            return 'Rembourse';
        }
        if (str_contains($value, 'attente')) {
            return 'En attente';
        }
        if (str_contains($value, 'annul')) {
            return 'Annule';
        }

        return (string) $this->statutPaiement;
    }

    public function isPaid(): bool
    {
        return $this->getNormalizedStatutPaiement() === 'Paye';
    }

    public function getStatutBadgeClass(): string
    {
        return match ($this->getNormalizedStatutPaiement()) {
            'Paye' => 'bg-emerald-500/10 text-emerald-300 border-emerald-500/20',
            'En cours de paiement' => 'bg-amber-500/10 text-amber-300 border-amber-500/20',
            'Rembourse' => 'bg-sky-500/10 text-sky-300 border-sky-500/20',
            'Annule' => 'bg-rose-500/10 text-rose-300 border-rose-500/20',
            default => 'bg-slate-500/10 text-slate-300 border-slate-500/20',
        };
    }

    public function getModePaiementLabel(): string
    {
        return match ($this->modePaiement) {
            'Paymee' => 'Paymee',
            'Especes' => 'Espèces',
            default => (string) $this->modePaiement,
        };
    }

    public function getModePaiementIcon(): string
    {
        return match ($this->modePaiement) {
            'Paymee' => 'credit-card',
            'Especes' => 'money-bill-wave',
            default => 'wallet',
        };
    }

    public function canBeRated(): bool
    {
        return $this->isPaid() && $this->rating === null;
    }

    public function getStarRating(): string
    {
        if ($this->rating === null) {
            return 'Non évalué';
        }

        return str_repeat('★', $this->rating) . str_repeat('☆', 5 - $this->rating);
    }
}
