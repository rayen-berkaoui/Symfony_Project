<?php

namespace App\Entity;

use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Validator\Constraints as Assert;
use App\Repository\ActiviteRepository;
use Vich\UploaderBundle\Mapping\Annotation as Vich;
use Symfony\Component\HttpFoundation\File\File;

#[ORM\Entity(repositoryClass: ActiviteRepository::class)]
#[ORM\Table(name: 'activite')]
#[Vich\Uploadable]
class Activite
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'idActivite', type: 'integer')]
    private ?int $idActivite = null;

    #[ORM\Column(name: 'nomActivite', type: 'string', length: 120)]
    #[Assert\NotBlank(message: "Le nom de l'activitÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â© est obligatoire.")]
    #[Assert\Length(
        max: 120,
        maxMessage: "Le nom de l'activitÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â© ne peut pas dÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©passer {{ limit }} caractÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¨res."
    )]
    private ?string $nomActivite = null;

    #[ORM\Column(name: 'description', type: 'text', nullable: true)]
    #[Assert\Length(
        max: 500,
        maxMessage: "La description ne peut pas dÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©passer {{ limit }} caractÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¨res."
    )]
    private ?string $description = null;

    #[ORM\Column(name: 'categorie', type: 'string', length: 60, nullable: true)]
    private ?string $categorie = null;

    #[ORM\Column(name: 'duree', type: 'integer', nullable: true)]
    #[Assert\Range(
        min: 1,
        max: 1440,
        notInRangeMessage: "La durÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©e doit ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âªtre comprise entre {{ min }} et {{ max }} minutes."
    )]
    private ?int $duree = null;

    #[ORM\Column(name: 'niveau', type: 'string', length: 50, nullable: true)]
    private ?string $niveau = null;

    #[ORM\Column(name: 'prix', type: 'decimal', precision: 10, scale: 2, nullable: true)]
    #[Assert\GreaterThanOrEqual(
        value: 0,
        message: "Le prix doit ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âªtre supÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©rieur ou ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©gal ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â  15 DT."
    )]
    private ?string $prix = null;

    #[ORM\Column(name: 'devise', type: 'string', length: 3, nullable: true, options: ['default' => 'TND'])]
    private ?string $devise = 'TND';

    #[ORM\Column(name: 'date_debut', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\Type("\DateTimeInterface")]
    private ?\DateTimeInterface $date_debut = null;

    #[ORM\Column(name: 'date_fin', type: Types::DATETIME_MUTABLE, nullable: true)]
    #[Assert\Type("\DateTimeInterface")]
    #[Assert\GreaterThan(propertyPath: "date_debut", message: "La date de fin doit ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âªtre ultÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©rieure ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â  la date de dÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©but.")]
    private ?\DateTimeInterface $date_fin = null;

    #[ORM\Column(name: 'nb_places', type: 'integer', nullable: true)]
    #[Assert\Positive(message: "Le nombre de places doit ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âªtre supÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©rieur ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â  zÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©ro.")]
    private ?int $nb_places = null;

    #[ORM\Column(name: 'places_dispo', type: 'integer', nullable: true)]
    #[Assert\PositiveOrZero(message: "Le nombre de places disponibles ne peut pas ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âªtre nÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©gatif.")]
    #[Assert\LessThanOrEqual(
        propertyPath: "nb_places",
        message: "Les places disponibles ne peuvent pas excÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©der le nombre de places total."
    )]
    private ?int $places_dispo = null;

    #[ORM\Column(name: 'adresse_depart', type: 'string', length: 180, nullable: true)]
    private ?string $adresse_depart = null;

    #[ORM\Column(name: 'age_min', type: 'integer', nullable: true)]
    #[Assert\GreaterThanOrEqual(
        value: 0,
        message: "L'ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â¢ge minimum doit ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Âªtre d'au moins 12 ans."
    )]
    private ?int $age_min = null;

    #[ORM\Column(name: 'equipement_inclus', type: 'string', length: 255, nullable: true)]
    private ?string $equipement_inclus = null;

    #[ORM\Column(name: 'conditions_annulation', type: 'string', length: 255, nullable: true)]
    private ?string $conditions_annulation = null;

    #[ORM\Column(name: 'statut', type: 'string', columnDefinition: "ENUM('disponible','complete','annulee')", nullable: true)]
    #[Assert\Choice(
        choices: ['disponible', 'complete', 'annulee'],
        message: "SÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©lectionnez un statut valide."
    )]
    private ?string $statut = 'disponible';

    // Foreign Key mapping
    #[ORM\ManyToOne(targetEntity: Etablissement::class)]
    #[ORM\JoinColumn(name: 'idEtablissement', referencedColumnName: 'idEtablissement', nullable: false, onDelete: 'CASCADE')]
    #[Assert\NotNull(message: "Vous devez associer cette activitÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â© ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â  un ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©tablissement existant.")]
    #[Assert\NotBlank(message: "L'ÃƒÆ’Ã†â€™Ãƒâ€šÃ‚Â©tablissement est obligatoire.")]
    private ?Etablissement $etablissement = null;


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

    #[Vich\UploadableField(mapping: 'activite_images', fileNameProperty: 'imageName')]
    private ?File $imageFile = null;

    #[ORM\Column(nullable: true)]
    private ?string $imageName = null;

    #[ORM\Column(nullable: true)]
    private ?\DateTimeImmutable $updatedAt = null;

    public function setImageFile(?File $imageFile = null): void
    {
        $this->imageFile = $imageFile;

        if (null !== $imageFile) {
            $this->updatedAt = new \DateTimeImmutable();
        }
    }

    public function getImageFile(): ?File
    {
        return $this->imageFile;
    }

    public function setImageName(?string $imageName): void
    {
        $this->imageName = $imageName;
    }

    public function getImageName(): ?string
    {
        return $this->imageName;
    }

    public function setUpdatedAt(?\DateTimeImmutable $updatedAt): static
    {
        $this->updatedAt = $updatedAt;
        return $this;
    }

    public function getUpdatedAt(): ?\DateTimeImmutable
    {
        return $this->updatedAt;
    }
}
