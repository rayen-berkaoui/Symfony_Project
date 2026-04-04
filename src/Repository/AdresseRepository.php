<?php

namespace App\Repository;

use App\Entity\Adresse;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Adresse>
 */
class AdresseRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Adresse::class);
    }

    public function searchByQuery(string $query)
    {
        $qb = $this->createQueryBuilder('a');
        if ($query) {
            $qb->where('a.rue LIKE :query')
                ->orWhere('a.ville LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }
        return $qb;
    }
}
