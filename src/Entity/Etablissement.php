<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'etablissement')]
class Etablissement
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'idEtablissement', type: 'integer')]
    private ?int $idEtablissement = null;

    #[ORM\Column(name: 'nom', type: 'string', length: 120)]
    #[Assert\NotBlank(message: "Le nom est obligatoire")]
    #[Assert\Length(min: 2, max: 120, minMessage: "Le nom doit comporter au moins 2 caractères")]
    private ?string $nom = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    #[Assert\Length(min: 10, minMessage: "La description doit comporter au moins 10 caractères if provided")]
    private ?string $description = null;

    #[ORM\Column(name: 'adresse', type: 'string', length: 180)]
    #[Assert\NotBlank(message: "L'adresse est obligatoire")]
    private ?string $adresse = null;

    #[ORM\Column(name: 'ville', type: 'string', length: 80)]
    #[Assert\NotBlank(message: "La ville est obligatoire")]
    private ?string $ville = null;

    #[ORM\Column(name: 'telephone', type: 'string', length: 30, nullable: true)]
    #[Assert\Regex(pattern: '/^[0-9\+\-\s]+$/', message: "Le téléphone doit contenir des chiffres et éventuellement un signe +")]
    private ?string $telephone = null;

    #[ORM\Column(name: 'email', type: 'string', length: 120, nullable: true)]
    #[Assert\Email(message: "L'email doit être valide")]
    private ?string $email = null;

    #[ORM\Column(name: 'horaires', type: 'string', length: 255, nullable: true)]
    private ?string $horaires = null;

    #[ORM\Column(name: 'gammePrix', type: 'string', length: 10, nullable: true)]
    private ?string $gammePrix = null;

    #[ORM\Column(name: 'type', type: 'string', columnDefinition: "ENUM('hotel','restaurant','cafe','museum','bar','loisir','autre')", nullable: true)]
    #[Assert\NotBlank(message: "Le type de l'établissement est obligatoire")]
    private ?string $type = 'autre';

    #[ORM\Column(name: 'latitude', type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(min: -90, max: 90, notInRangeMessage: "Latitude invalide")]
    private ?string $latitude = null;

    #[ORM\Column(name: 'longitude', type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(min: -180, max: 180, notInRangeMessage: "Longitude invalide")]
    private ?string $longitude = null;

    #[ORM\Column(length: 255, nullable: true)]
    private ?string $image = null;

    public function getIdEtablissement(): ?int
    {
        return $this->idEtablissement;
    }

    // Since Symfony forms usually expect `getId()`, we alias it:
    public function getId(): ?int
    {
        return $this->idEtablissement;
    }

    public function getNom(): ?string
    {
        return $this->nom;
    }

    public function setNom(string $nom): static
    {
        $this->nom = $nom;
        return $this;
    }

    public function getDescription(): ?string
    {
        return $this->description;
    }

    public function setDescription(?string $description): static
    {
        $this->description = $description;
        return $this;
    }

    public function getAdresse(): ?string
    {
        return $this->adresse;
    }

    public function setAdresse(string $adresse): static
    {
        $this->adresse = $adresse;
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

    public function getTelephone(): ?string
    {
        return $this->telephone;
    }

    public function setTelephone(?string $telephone): static
    {
        $this->telephone = $telephone;
        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(?string $email): static
    {
        $this->email = $email;
        return $this;
    }

    public function getHoraires(): ?string
    {
        return $this->horaires;
    }

    public function setHoraires(?string $horaires): static
    {
        $this->horaires = $horaires;
        return $this;
    }

    public function getGammePrix(): ?string
    {
        return $this->gammePrix;
    }

    public function setGammePrix(?string $gammePrix): static
    {
        $this->gammePrix = $gammePrix;
        return $this;
    }

    public function getType(): ?string
    {
        return $this->type;
    }

    public function setType(?string $type): static
    {
        $this->type = $type;
        return $this;
    }

    public function getLatitude(): ?string
    {
        return $this->latitude;
    }

    public function setLatitude(?string $latitude): static
    {
        $this->latitude = $latitude;
        return $this;
    }

    public function getLongitude(): ?string
    {
        return $this->longitude;
    }

    public function setLongitude(?string $longitude): static
    {
        $this->longitude = $longitude;
        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->nom;
    }

    public function getImage(): ?string
    {
        return $this->image;
    }

    public function setImage(?string $image): static
    {
        $this->image = $image;

        return $this;
    }

    public function getImageUrl(): ?string
    {
        if (!$this->image) {
            return null;
        }
        return '/uploads/etablissements/' . $this->image;
    }
}