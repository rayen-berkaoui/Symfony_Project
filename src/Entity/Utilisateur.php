<?php

namespace App\Entity;

use App\Repository\UtilisateurRepository;
use Doctrine\ORM\Mapping as ORM;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\User\UserInterface;

#[ORM\Entity(repositoryClass: UtilisateurRepository::class)]
#[ORM\Table(name: 'utilisateur')]
class Utilisateur implements UserInterface, PasswordAuthenticatedUserInterface
{
    public const STATUT_ACTIF = 'ACTIF';
    public const STATUT_BLOQUE = 'BLOQUE';
    public const STATUT_EN_ATTENTE = 'EN_ATTENTE';

    #[ORM\Id]
    #[ORM\GeneratedValue]
    #[ORM\Column(type: 'integer')]
    private ?int $id = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $nom = null;

    #[ORM\Column(type: 'string', length: 100)]
    private ?string $prenom = null;

    #[ORM\Column(type: 'string', length: 150, unique: true)]
    private ?string $email = null;

    #[ORM\Column(name: 'mot_de_passe', type: 'string', length: 255)]
    private ?string $motDePasse = null;

    #[ORM\Column(
        name: 'statut',
        type: 'string',
        length: 20,
        nullable: true,
        columnDefinition: "ENUM('ACTIF','BLOQUE','EN_ATTENTE')"
    )]
    private ?string $statut = self::STATUT_ACTIF;

    #[ORM\Column(name: 'date_creation', type: 'datetime')]
    private ?\DateTimeInterface $dateCreation = null;

    #[ORM\Column(name: 'num_tel', type: 'integer')]
    private ?int $numTel = null;

    #[ORM\Column(name: 'nfc_id', type: 'string', length: 100, nullable: true)]
    private ?string $nfcId = null;

    #[ORM\ManyToOne(targetEntity: Role::class, inversedBy: 'utilisateurs')]
    #[ORM\JoinColumn(name: 'role_id', referencedColumnName: 'id', nullable: false)]
    private ?Role $role = null;

    #[ORM\Column(name: 'profile_picture', type: 'text', nullable: true, columnDefinition: 'LONGTEXT')]
    private ?string $profilePicture = null;

    #[ORM\Column(name: 'face_encoding', type: 'text', nullable: true, columnDefinition: 'LONGTEXT')]
    private ?string $faceEncoding = null;

    #[ORM\Column(name: 'face_confidence', type: 'float', nullable: true)]
    private ?float $faceConfidence = 0.0;

    #[ORM\Column(name: 'face_samples_count', type: 'integer', nullable: true)]
    private ?int $faceSamplesCount = 0;

    #[ORM\Column(name: 'last_face_login', type: 'date', nullable: true)]
    private ?\DateTimeInterface $lastFaceLogin = null;

    #[ORM\Column(name: 'totp_secret', type: 'string', length: 255, nullable: true)]
    private ?string $totpSecret = null;

    #[ORM\Column(name: 'totp_enabled', type: 'boolean', nullable: true)]
    private ?bool $totpEnabled = false;

    #[ORM\Column(name: 'loyalty_points', type: 'integer', nullable: true)]
    private ?int $loyaltyPoints = 0;

    #[ORM\Column(name: 'theme_preference', type: 'string', length: 50, nullable: true)]
    private ?string $themePreference = 'SYSTEM';

    #[ORM\Column(name: 'language', type: 'string', length: 10, nullable: true)]
    private ?string $language = 'fr';

    #[ORM\OneToMany(mappedBy: 'utilisateur', targetEntity: Panier::class, cascade: ['remove'])]
    private \Doctrine\Common\Collections\Collection $paniers;

    public function __construct()
    {
        $this->paniers = new \Doctrine\Common\Collections\ArrayCollection();
        if ($this->dateCreation === null) {
            $this->dateCreation = new \DateTime();
        }
    }

    public function getId(): ?int
    {
        return $this->id;
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

    public function getPrenom(): ?string
    {
        return $this->prenom;
    }

    public function setPrenom(string $prenom): static
    {
        $this->prenom = $prenom;

        return $this;
    }

    public function getEmail(): ?string
    {
        return $this->email;
    }

    public function setEmail(string $email): static
    {
        $this->email = $email;

        return $this;
    }

    public function getUserIdentifier(): string
    {
        return (string) $this->email;
    }

    public function getRoles(): array
    {
        $roles = ['ROLE_USER'];

        if ($this->role !== null && $this->role->getNom() !== null) {
            $roleName = strtoupper($this->role->getNom());
            $roles[] = str_starts_with($roleName, 'ROLE_') ? $roleName : 'ROLE_' . $roleName;
        }

        return array_values(array_unique($roles));
    }

    public function eraseCredentials(): void
    {
        // No transient sensitive data stored.
    }

    public function getMotDePasse(): ?string
    {
        return $this->motDePasse;
    }

    public function setMotDePasse(string $motDePasse): static
    {
        $this->motDePasse = $motDePasse;

        return $this;
    }

    public function getPassword(): ?string
    {
        return $this->motDePasse;
    }

    public function setPassword(string $motDePasse): static
    {
        $this->motDePasse = $motDePasse;

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

    public function getDateCreation(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setDateCreation(\DateTimeInterface $dateCreation): static
    {
        $this->dateCreation = $dateCreation;

        return $this;
    }

    public function getCreatedAt(): ?\DateTimeInterface
    {
        return $this->dateCreation;
    }

    public function setCreatedAt(\DateTimeInterface $createdAt): static
    {
        $this->dateCreation = $createdAt;

        return $this;
    }

    public function getNumTel(): ?int
    {
        return $this->numTel;
    }

    public function setNumTel(int $numTel): static
    {
        $this->numTel = $numTel;

        return $this;
    }

    public function getNfcId(): ?string
    {
        return $this->nfcId;
    }

    public function setNfcId(?string $nfcId): static
    {
        $this->nfcId = $nfcId;

        return $this;
    }

    public function getRole(): ?Role
    {
        return $this->role;
    }

    public function setRole(?Role $role): static
    {
        $this->role = $role;

        return $this;
    }

    public function getProfilePicture(): ?string
    {
        return $this->profilePicture;
    }

    public function setProfilePicture(?string $profilePicture): static
    {
        $this->profilePicture = $profilePicture;

        return $this;
    }

    public function getFaceEncoding(): ?string
    {
        return $this->faceEncoding;
    }

    public function setFaceEncoding(?string $faceEncoding): static
    {
        $this->faceEncoding = $faceEncoding;

        return $this;
    }

    public function getFaceConfidence(): ?float
    {
        return $this->faceConfidence;
    }

    public function setFaceConfidence(?float $faceConfidence): static
    {
        $this->faceConfidence = $faceConfidence;

        return $this;
    }

    public function getFaceSamplesCount(): ?int
    {
        return $this->faceSamplesCount;
    }

    public function setFaceSamplesCount(?int $faceSamplesCount): static
    {
        $this->faceSamplesCount = $faceSamplesCount;

        return $this;
    }

    public function getLastFaceLogin(): ?\DateTimeInterface
    {
        return $this->lastFaceLogin;
    }

    public function setLastFaceLogin(?\DateTimeInterface $lastFaceLogin): static
    {
        $this->lastFaceLogin = $lastFaceLogin;

        return $this;
    }

    public function getTotpSecret(): ?string
    {
        return $this->totpSecret;
    }

    public function setTotpSecret(?string $totpSecret): static
    {
        $this->totpSecret = $totpSecret;

        return $this;
    }

    public function isTotpEnabled(): ?bool
    {
        return $this->totpEnabled;
    }

    public function setTotpEnabled(?bool $totpEnabled): static
    {
        $this->totpEnabled = $totpEnabled;

        return $this;
    }

    public function getLoyaltyPoints(): ?int
    {
        return $this->loyaltyPoints;
    }

    public function setLoyaltyPoints(?int $loyaltyPoints): static
    {
        $this->loyaltyPoints = $loyaltyPoints;

        return $this;
    }

    public function getThemePreference(): ?string
    {
        return $this->themePreference;
    }

    public function setThemePreference(?string $themePreference): static
    {
        $this->themePreference = $themePreference;

        return $this;
    }

    public function getLanguage(): ?string
    {
        return $this->language;
    }

    public function setLanguage(?string $language): static
    {
        $this->language = $language;

        return $this;
    }

    /**
     * @return \Doctrine\Common\Collections\Collection<int, Panier>
     */
    public function getPaniers(): \Doctrine\Common\Collections\Collection
    {
        return $this->paniers;
    }

    public function addPanier(Panier $panier): self
    {
        if (!$this->paniers->contains($panier)) {
            $this->paniers->add($panier);
            $panier->setUtilisateur($this);
        }

        return $this;
    }

    public function removePanier(Panier $panier): self
    {
        if ($this->paniers->removeElement($panier)) {
            // set the owning side to null (unless already changed)
            if ($panier->getUtilisateur() === $this) {
                $panier->setUtilisateur(null);
            }
        }

        return $this;
    }
}
