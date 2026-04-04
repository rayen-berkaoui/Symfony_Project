<?php

namespace App\Entity;

use App\Repository\CategorieRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity(repositoryClass: CategorieRepository::class)]
#[ORM\Table(name: 'categorie')]
class Categorie
{
    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(name: 'id_categorie', type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(name: 'nom_categorie', type: 'string', length: 100)]
    #[Assert\NotBlank(message: 'Le nom de la catégorie est obligatoire')]
    #[Assert\Length(
        min: 3,
        max: 100,
        minMessage: 'Le nom de la catégorie doit comporter au moins {{ limit }} caractères',
        maxMessage: 'Le nom de la catégorie ne peut pas dépasser {{ limit }} caractères'
    )]
    private ?string $nomCategorie = null;

    #[ORM\Column(name: 'description', type: Types::TEXT, nullable: true, columnDefinition: 'TEXT')]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(
        min: 10,
        minMessage: 'La description doit comporter un minimum de {{ limit }} caractères si elle est fournie'
    )]
    private ?string $description = null;

    #[ORM\Column(name: 'date_creation', type: Types::DATE_MUTABLE, nullable: true)]
    #[Assert\NotBlank(message: 'La date de création est obligatoire')]
    #[Assert\Type("\DateTimeInterface")]
    private ?\DateTimeInterface $dateCreation = null;

    public function getId(): ?int
    {
        return $this->id;
    }

    public function getNomCategorie(): ?string
    {
        return $this->nomCategorie;
    }

    public function setNomCategorie(string $nomCategorie): static
    {
        $this->nomCategorie = $nomCategorie;

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

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(?\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function __toString(): string
    {
        return (string) $this->nomCategorie;
    }
}
