<?php

namespace App\Entity;

use App\Repository\AdresseRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: AdresseRepository::class)]
#[ORM\Table(name: 'adresse')]
class Adresse
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_adresse', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'rue', type: 'string', length: 255)]
    #[Assert\NotBlank(message: 'La rue est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 255,
        minMessage: 'Le nom de la rue doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom de la rue ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $rue = null;

    #[ORM\Column(name: 'ville', type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'La ville est obligatoire')]
    #[Assert\Length(
        min: 2,
        max: 100,
        minMessage: 'Le nom de la ville doit contenir au moins {{ limit }} caractères',
        maxMessage: 'Le nom de la ville ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $ville = null;

    #[ORM\Column(name: 'latitude', type: 'float')]
    #[Assert\NotBlank(message: 'La latitude est obligatoire')]
    #[Assert\NotNull(message: 'La latitude est obligatoire')]
    #[Assert\Range(
        min: -90,
        max: 90,
        notInRangeMessage: 'La latitude doit être comprise entre {{ min }} et {{ max }} degrés'
    )]
    private ?float $latitude = null;

    #[ORM\Column(name: 'longitude', type: 'float')]
    #[Assert\NotBlank(message: 'La longitude est obligatoire')]
    #[Assert\NotNull(message: 'La longitude est obligatoire')]
    #[Assert\Range(
        min: -180,
        max: 180,
        notInRangeMessage: 'La longitude doit être comprise entre {{ min }} et {{ max }} degrés'
    )]
    private ?float $longitude = null;

    #[ORM\Column(name: 'altitude', type: 'float', nullable: true)]
    #[Assert\NotBlank(message: 'L\'altitude est obligatoire')]
    #[Assert\Type(type: 'float', message: 'L\'altitude doit être un nombre valide')]
    private ?float $altitude = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getRue(): ?string
    {
        return $this->rue;
    }

    public function setRue(string $rue): static
    {
        $this->rue = $rue;

        return $this;
    }

    public function getVille(): ?string
    {
        return $this->ville;
    }

    public function setVille(string $ville): static
    {
        $this->ville = $ville;

        return $this;
    }

    public function getLatitude(): ?float
    {
        return $this->latitude;
    }

    public function setLatitude(float $latitude): static
    {
        $this->latitude = $latitude;

        return $this;
    }

    public function getLongitude(): ?float
    {
        return $this->longitude;
    }

    public function setLongitude(float $longitude): static
    {
        $this->longitude = $longitude;

        return $this;
    }

    public function getAltitude(): ?float
    {
        return $this->altitude;
    }

    public function setAltitude(?float $altitude): static
    {
        $this->altitude = $altitude;

        return $this;
    }

    public function __toString(): string
    {
        return $this->rue . ', ' . $this->ville;
    }
}
