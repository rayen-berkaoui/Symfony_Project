<?php

namespace App\Entity;

use Doctrine\ORM\Mapping as ORM;

#[ORM\Entity]
#[ORM\Table(name: 'etablissement_image')]
class EtablissementImage
{
    #[ORM\Id]
    #[ORM\GeneratedValue(strategy: 'IDENTITY')]
    #[ORM\Column(name: 'idImage', type: 'integer')]
    private ?int $idImage = null;

    #[ORM\ManyToOne(targetEntity: Etablissement::class, inversedBy: 'images')]
    #[ORM\JoinColumn(name: 'idEtablissement', referencedColumnName: 'idEtablissement', nullable: false, onDelete: 'CASCADE')]
    private ?Etablissement $etablissement = null;

    #[ORM\Column(name: 'image_path', type: 'string', length: 255)]
    private ?string $imagePath = null;

    #[ORM\Column(name: 'ordre_affichage', type: 'integer', nullable: true, options: ['default' => 1])]
    private ?int $ordreAffichage = 1;

    public function getIdImage(): ?int
    {
        return $this->idImage;
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
