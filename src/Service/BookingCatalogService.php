<?php

namespace App\Service;

use App\Entity\Etablissement;
use App\Entity\LieuTouristique;
use App\Entity\Panier;
use App\Entity\Activite;
use App\Repository\LieuTouristiqueRepository;
use Doctrine\ORM\EntityManagerInterface;

class BookingCatalogService
{
    public function __construct(
        private readonly LieuTouristiqueRepository $lieuRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function enrichPanier(?Panier $panier): void
    {
        if (!$panier) {
            return;
        }

        $panier->setDisplayData(
            $this->resolveName($panier),
            $this->resolveCity($panier),
            $this->resolveImage($panier),
            $panier->getServiceTypeLabel(),
            sprintf('%s-%s', strtoupper(substr((string) $panier->getTypeService(), 0, 3)), (string) $panier->getServiceId())
        );
    }

    public function enrichMany(iterable $paniers): void
    {
        foreach ($paniers as $panier) {
            if ($panier instanceof Panier) {
                $this->enrichPanier($panier);
            }
        }
    }

    public function resolveLieu(Panier $panier): ?LieuTouristique
    {
        if (!$panier->isLieuLikeService() || !$panier->getServiceId()) {
            return null;
        }

        return $this->lieuRepository->find($panier->getServiceId());
    }

    public function resolveEtablissement(Panier $panier): ?Etablissement
    {
        if ($panier->isLieuLikeService() || !$panier->getServiceId()) {
            return null;
        }

        return $this->entityManager->getRepository(Etablissement::class)->find($panier->getServiceId());
    }

    public function resolveName(Panier $panier): string
    {
        $lieu = $this->resolveLieu($panier);
        if ($lieu) {
            return (string) $lieu->getNom();
        }

        $etablissement = $this->resolveEtablissement($panier);
        if ($etablissement) {
            return (string) $etablissement->getNom();
        }

        return sprintf('%s #%d', $panier->getServiceTypeLabel(), (int) $panier->getServiceId());
    }

    public function resolveCity(Panier $panier): ?string
    {
        $lieu = $this->resolveLieu($panier);
        if ($lieu) {
            return $lieu->getVille();
        }

        $etablissement = $this->resolveEtablissement($panier);
        return $etablissement?->getVille();
    }

    public function resolveImage(Panier $panier): ?string
    {
        $lieu = $this->resolveLieu($panier);
        if ($lieu) {
            return $lieu->getImage();
        }

        $etablissement = $this->resolveEtablissement($panier);
        if ($etablissement && $etablissement->getImageUrl()) {
            return $etablissement->getImageUrl();
        }

        if ($panier->getTypeService() === 'Activite') {
            $activite = $this->entityManager->getRepository(Activite::class)->find($panier->getServiceId());
            if ($activite && method_exists($activite, 'getImageUrl')) {
                return $activite->getImageUrl();
            }
        }

        return null;
    }
}
