<?php

namespace App\Entity;

use Doctrine\Common\Collections\ArrayCollection;
use Doctrine\Common\Collections\Collection;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;

#[ORM\Entity]
#[ORM\Table(name: 'activite')]
class Activite
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'idActivite', type: 'integer')]
    private ?int $idActivite = null;

    #[ORM\Column(name: 'nomActivite', type: 'string', length: 120)]
    #[Assert\NotBlank(message: "Le nom de l'activité est obligatoire")]
    #[Assert\Length(min: 3, max: 120, minMessage: 'Le nom doit contenir au moins {{ limit }} caractères')]
    private ?string $nomActivite = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    #[Assert\NotBlank(message: 'La description est obligatoire')]
    #[Assert\Length(min: 10, minMessage: 'La description doit comporter au moins {{ limit }} caractères')]
    private ?string $description = null;

    #[ORM\Column(name: 'categorie', type: 'string', length: 100, nullable: true)]
    #[Assert\NotBlank(message: 'La catégorie est obligatoire')]
    private ?string $categorie = null;

    #[ORM\Column(name: 'duree', type: 'integer', nullable: true)]
    #[Assert\Positive(message: 'La durée doit être supérieure à zéro')]
    private ?int $duree = null;

    #[ORM\Column(name: 'niveau', type: 'string', length: 50, nullable: true)]
    private ?string $niveau = null;

    #[ORM\Column(name: 'prix', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\NotBlank(message: 'Le prix est obligatoire')]
    #[Assert\PositiveOrZero(message: 'Le prix ne peut pas être négatif')]
    private ?string $prix = null;

    #[ORM\Column(name: 'devise', type: 'string', length: 10, nullable: true, options: ['default' => 'TND'])]
    private ?string $devise = 'TND';

    #[ORM\Column(name: 'date_debut', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\NotBlank(message: 'La date de début est obligatoire')]
    #[Assert\GreaterThanOrEqual('today', message: "La date de début doit être aujourd'hui ou dans le futur")]
    private ?\DateTimeInterface $date_debut = null;

    #[ORM\Column(name: 'date_fin', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\NotBlank(message: 'La date de fin est obligatoire')]
    #[Assert\Expression(expression: 'this.getDateDebut() == null or this.getDateFin() == null or this.getDateDebut() <= this.getDateFin()', message: 'La date de fin ne peut pas être antérieure à la date de début')]
    private ?\DateTimeInterface $date_fin = null;

    #[ORM\Column(name: 'nb_places', type: 'integer', nullable: true)]
    #[Assert\NotBlank(message: 'Le nombre de places est obligatoire')]
    #[Assert\Positive(message: 'Le nombre de places doit être supérieur à zéro')]
    private ?int $nb_places = null;

    #[ORM\Column(name: 'places_dispo', type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero(message: 'Doit être positif ou nul')]
    private ?int $places_dispo = null;

    #[ORM\Column(name: 'adresse_depart', type: 'string', length: 255, nullable: true)]
    #[Assert\Length(max: 255, maxMessage: 'Trop long')]
    private ?string $adresse_depart = null;

    #[ORM\Column(name: 'age_min', type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero(message: 'Doit être positif ou nul')]
    private ?int $age_min = null;

    #[ORM\Column(name: 'equipement_inclus', type: 'text', nullable: true)]
    private ?string $equipement_inclus = null;

    #[ORM\Column(name: 'conditions_annulation', type: 'text', nullable: true)]
    private ?string $conditions_annulation = null;

    #[ORM\Column(name: 'statut', type: 'string', length: 50, nullable: true)]
    private ?string $statut = 'disponible';

    #[ORM\ManyToOne(targetEntity: Etablissement::class)]
    #[ORM\JoinColumn(name: 'idEtablissement', referencedColumnName: 'idEtablissement', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: 'Veuillez sélectionner un établissement')]
    private ?Etablissement $etablissement = null;

    #[ORM\OneToMany(mappedBy: 'activite', targetEntity: ActiviteImage::class, cascade: ['persist', 'remove'], orphanRemoval: true)]
    #[ORM\OrderBy(['ordreAffichage' => 'ASC', 'idImage' => 'ASC'])]
    private Collection $images;

    public function __construct()
    {
        $this->images = new ArrayCollection();
    }

    public function getIdActivite(): ?int
    {
        return $this->idActivite;
    }

    public function getId(): ?int
    {
        return $this->idActivite;
    }

    public function getNomActivite(): ?string
    {
        return $this->nomActivite;
    }

    public function setNomActivite(string $nomActivite): static
    {
        $this->nomActivite = $nomActivite;
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

    public function getCategorie(): ?string
    {
        return $this->categorie;
    }

    public function setCategorie(?string $categorie): static
    {
        $this->categorie = $categorie;
        return $this;
    }

    public function getDuree(): ?int
    {
        return $this->duree;
    }

    public function setDuree(?int $duree): static
    {
        $this->duree = $duree;
        return $this;
    }

    public function getNiveau(): ?string
    {
        return $this->niveau;
    }

    public function setNiveau(?string $niveau): static
    {
        $this->niveau = $niveau;
        return $this;
    }

    public function getPrix(): ?string
    {
        return $this->prix;
    }

    public function setPrix(?string $prix): static
    {
        $this->prix = $prix;
        return $this;
    }

    public function getDevise(): ?string
    {
        return $this->devise;
    }

    public function setDevise(?string $devise): static
    {
        $this->devise = $devise;
        return $this;
    }

    public function getDateDebut(): ?\DateTimeInterface
    {
        return $this->date_debut;
    }

    public function setDateDebut(?\DateTimeInterface $date_debut): static
    {
        $this->date_debut = $date_debut;
        return $this;
    }

    public function getDateFin(): ?\DateTimeInterface
    {
        return $this->date_fin;
    }

    public function setDateFin(?\DateTimeInterface $date_fin): static
    {
        $this->date_fin = $date_fin;
        return $this;
    }

    public function getNbPlaces(): ?int
    {
        return $this->nb_places;
    }

    public function setNbPlaces(?int $nb_places): static
    {
        $this->nb_places = $nb_places;
        return $this;
    }

    public function getPlacesDispo(): ?int
    {
        return $this->places_dispo;
    }

    public function setPlacesDispo(?int $places_dispo): static
    {
        $this->places_dispo = $places_dispo;
        return $this;
    }

    public function getAdresseDepart(): ?string
    {
        return $this->adresse_depart;
    }

    public function setAdresseDepart(?string $adresse_depart): static
    {
        $this->adresse_depart = $adresse_depart;
        return $this;
    }

    public function getAgeMin(): ?int
    {
        return $this->age_min;
    }

    public function setAgeMin(?int $age_min): static
    {
        $this->age_min = $age_min;
        return $this;
    }

    public function getEquipementInclus(): ?string
    {
        return $this->equipement_inclus;
    }

    public function setEquipementInclus(?string $equipement_inclus): static
    {
        $this->equipement_inclus = $equipement_inclus;
        return $this;
    }

    public function getConditionsAnnulation(): ?string
    {
        return $this->conditions_annulation;
    }

    public function setConditionsAnnulation(?string $conditions_annulation): static
    {
        $this->conditions_annulation = $conditions_annulation;
        return $this;
    }

    public function getStatut(): ?string
    {
        return $this->statut;
    }

    public function setStatut(?string $statut): static
    {
        $this->statut = $statut;
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

    /** @return Collection<int, ActiviteImage> */
    public function getImages(): Collection
    {
        return $this->images;
    }

    public function addImageRecord(ActiviteImage $image): static
    {
        if (!$this->images->contains($image)) {
            $this->images->add($image);
            $image->setActivite($this);
        }

        return $this;
    }

    public function removeImageRecord(ActiviteImage $image): static
    {
        if ($this->images->removeElement($image) && $image->getActivite() === $this) {
            $image->setActivite(null);
        }

        return $this;
    }

    public function getImage(): ?string
    {
        foreach ($this->images as $image) {
            if ($image->getImagePath()) {
                return $image->getImagePath();
            }
        }

        return null;
    }

    public function setImage(?string $image): static
    {
        if ($image === null || trim($image) === '') {
            return $this;
        }

        foreach ($this->images as $existing) {
            $existing->setImagePath($image);
            return $this;
        }

        $record = new ActiviteImage();
        $record->setImagePath($image);
        $record->setOrdreAffichage(1);
        $this->addImageRecord($record);

        return $this;
    }

    public function getImageUrl(): ?string
    {
        $image = $this->getImage();
        if (!$image) {
            return null;
        }

        if (str_starts_with($image, 'http://') || str_starts_with($image, 'https://') || str_starts_with($image, 'data:') || str_starts_with($image, '/')) {
            return $image;
        }

        return '/uploads/activites/' . ltrim($image, '/');
    }
}
