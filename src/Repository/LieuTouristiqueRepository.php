<?php

namespace App\Repository;

use App\Entity\LieuTouristique;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<LieuTouristique>
 */
class LieuTouristiqueRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, LieuTouristique::class);
    }

    public function searchByQuery(
        string $query,
        ?int $categorieId = null,
        ?string $prixMin = null,
        ?string $prixMax = null,
        ?bool $statut = null,
        ?float $aroundLat = null,
        ?float $aroundLng = null,
        ?float $aroundRadiusKm = null
    )
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.categorie', 'c')
            ->leftJoin('l.adresse', 'a');

        if ($query) {
            $qb->andWhere('l.nom LIKE :query OR l.description LIKE :query OR l.ville LIKE :query OR c.nomCategorie LIKE :query OR a.rue LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        if ($categorieId !== null) {
            $qb->andWhere('l.categorie = :cat')->setParameter('cat', $categorieId);
        }

        if ($prixMin !== null && $prixMin !== '') {
            $qb->andWhere('l.prix >= :pmin')->setParameter('pmin', $prixMin);
        }

        if ($prixMax !== null && $prixMax !== '') {
            $qb->andWhere('l.prix <= :pmax')->setParameter('pmax', $prixMax);
        }

        if ($statut !== null) {
            $qb->andWhere('l.statut = :statut')->setParameter('statut', $statut);
        }

        if ($aroundLat !== null && $aroundLng !== null && $aroundRadiusKm !== null) {
            $latDelta = $aroundRadiusKm / 111.0;
            $cosLat = cos(deg2rad($aroundLat));
            $safeCosLat = max(0.01, abs($cosLat));
            $lngDelta = $aroundRadiusKm / (111.0 * $safeCosLat);

            $qb
                ->andWhere('a.latitude BETWEEN :latMin AND :latMax')
                ->andWhere('a.longitude BETWEEN :lngMin AND :lngMax')
                ->setParameter('latMin', $aroundLat - $latDelta)
                ->setParameter('latMax', $aroundLat + $latDelta)
                ->setParameter('lngMin', $aroundLng - $lngDelta)
                ->setParameter('lngMax', $aroundLng + $lngDelta)
                ->addSelect('(ABS(a.latitude - :aroundLat) + ABS(a.longitude - :aroundLng)) AS HIDDEN distanceScore')
                ->setParameter('aroundLat', $aroundLat)
                ->setParameter('aroundLng', $aroundLng)
                ->addOrderBy('distanceScore', 'ASC');
        } else {
            $qb->addOrderBy('l.id', 'DESC');
        }

        return $qb;
    }
}
