<?php

namespace App\Repository;

use App\Entity\Categorie;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Categorie>
 */
class CategorieRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Categorie::class);
    }

    public function searchByQuery(string $query)
    {
        $qb = $this->createQueryBuilder('c');
        if ($query) {
            $qb->where('c.nomCategorie LIKE :query')
                ->orWhere('c.description LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }
        return $qb;
    }
}
