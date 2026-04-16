<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'activite_image')]
class ActiviteImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'idImage', type: 'integer')]
    private ?int $idImage = null;

    #[ORM\ManyToOne(targetEntity: Activite::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'idActivite', referencedColumnName: 'idActivite', nullable: false, onDelete: 'CASCADE')]
    private ?Activite $activite = null;

    #[ORM\Column(name: 'image_path', type: 'string', length: 255)]
    private ?string $imagePath = null;

    #[ORM\Column(name: 'ordre_affichage', type: 'integer', nullable: true, options: ['default' => 1])]
    private ?int $ordreAffichage = 1;

    public function getIdImage(): ?int
    {
        return $this->idImage;
    }

    public function getActivite(): ?Activite
    {
        return $this->activite;
    }

    public function setActivite(?Activite $activite): static
    {
        $this->activite = $activite;
        return $this;
    }

    public function getImagePath(): ?string
    {
        return $this->imagePath;
    }

    public function setImagePath(string $imagePath): static
    {
        $this->imagePath = $imagePath;
        return $this;
    }

    public function getOrdreAffichage(): ?int
    {
        return $this->ordreAffichage;
    }

    public function setOrdreAffichage(?int $ordreAffichage): static
    {
        $this->ordreAffichage = $ordreAffichage;
        return $this;
    }
}
