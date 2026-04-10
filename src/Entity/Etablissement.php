<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\EtablissementRepository;

#[ORM\Entity(repositoryClass: EtablissementRepository::class)]
#[ORM\Table(name: 'etablissement')]
class Etablissement
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'idEtablissement', type: 'integer')]
    private ?int $idEtablissement = null;

    #[ORM\Column(name: 'nom', type: 'string', length: 120)]
    #[Assert\NotBlank(message: "Le nom de l'établissement est obligatoire.")]
    #[Assert\Length(
        min: 3,
        max: 120,
        minMessage: "Le nom doit comporter au moins {{ limit }} caractères.",
        maxMessage: "Le nom ne peut pas dépasser {{ limit }} caractères."
    )]
    #[Assert\Regex(
        pattern: "/^[a-zA-Z0-9\s\-\'\&]+$/",
        message: "Le nom contient des caractères non autorisés."
    )]
    private ?string $nom = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    #[Assert\Length(
        max: 200,
        maxMessage: "La description ne peut pas dépasser {{ limit }} caractères."
    )]
    private ?string $description = null;

    #[ORM\Column(name: 'adresse', type: 'string', length: 180)]
    #[Assert\NotBlank(message: "L'adresse est obligatoire.")]
    #[Assert\Length(
        min: 5,
        max: 180,
        minMessage: "L'adresse doit être plus détaillée (au moins {{ limit }} caractères).",
        maxMessage: "L'adresse est trop longue."
    )]
    private ?string $adresse = null;

    #[ORM\Column(name: 'ville', type: 'string', length: 80)]
    #[Assert\NotBlank(message: "La ville est obligatoire.")]
    private ?string $ville = null;

    #[ORM\Column(name: 'telephone', type: 'string', length: 30, nullable: true)]
    #[Assert\Regex(
        pattern: "/^\+?[0-9]{8,15}$/",
        message: "Le numéro de téléphone n'est pas valide (il doit contenir entre 8 et 15 chiffres, avec éventuellement un '+' au début)."
    )]
    private ?string $telephone = null;

    #[ORM\Column(name: 'email', type: 'string', length: 120, nullable: true)]
    #[Assert\Email(
        message: "L'adresse email '{{ value }}' n'est pas valide."
    )]
    #[Assert\Length(max: 120)]
    private ?string $email = null;

    #[ORM\Column(name: 'horaires', type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255)]
    private ?string $horaires = null;

    #[ORM\Column(name: 'gammePrix', type: 'string', length: 10, nullable: true)]
    #[Assert\Length(max: 10)]
    private ?string $gammePrix = null;

    #[ORM\Column(name: 'type', type: 'string', columnDefinition: "ENUM('hotel','restaurant','cafe','museum','bar','loisir','autre')", nullable: true)]
    #[Assert\Choice(
        choices: ['hotel', 'restaurant', 'cafe', 'museum', 'bar', 'loisir', 'autre'],
        message: "Sélectionnez un type d'établissement valide parmi la liste."
    )]
    private ?string $type = 'autre';

    #[ORM\Column(name: 'latitude', type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(
        notInRangeMessage: "La latitude doit être comprise entre {{ min }} et {{ max }}.",
        min: -90,
        max: 90
    )]
    private ?string $latitude = null;

    #[ORM\Column(name: 'longitude', type: 'decimal', precision: 10, scale: 7, nullable: true)]
    #[Assert\Range(
        notInRangeMessage: "La longitude doit être comprise entre {{ min }} et {{ max }}.",
        min: -180,
        max: 180
    )]
    private ?string $longitude = null;

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
}