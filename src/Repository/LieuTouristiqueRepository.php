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

    public function searchByQuery(string $query)
    {
        $qb = $this->createQueryBuilder('l')
            ->leftJoin('l.categorie', 'c')
            ->leftJoin('l.adresse', 'a');

        if ($query) {
            $qb->where('l.nom LIKE :query')
                ->orWhere('l.description LIKE :query')
                ->orWhere('l.ville LIKE :query')
                ->orWhere('c.nomCategorie LIKE :query')
                ->orWhere('a.rue LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }
        return $qb;
    }
}
