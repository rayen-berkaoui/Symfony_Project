<?php

namespace App\Repository;

use App\Entity\Etablissement;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Etablissement>
 */
class EtablissementRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Etablissement::class);
    }

    public function searchAndFilter(?string $search, ?string $type, ?string $gouvernorat, string $sortBy = 'nom', string $sortDirection = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('e');

        if ($search) {
            $qb->andWhere('e.nom LIKE :search OR e.description LIKE :search OR e.adresse LIKE :search OR e.ville LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($type) {
            $qb->andWhere('e.type = :type')
               ->setParameter('type', $type);
        }

        if ($gouvernorat) {
            $qb->andWhere('e.gouvernorat = :gouvernorat')
               ->setParameter('gouvernorat', $gouvernorat);
        }

        $allowedSortFields = ['nom', 'description', 'adresse', 'ville', 'type', 'gouvernorat'];
        if (in_array($sortBy, $allowedSortFields)) {
            $qb->orderBy('e.' . $sortBy, $sortDirection === 'DESC' ? 'DESC' : 'ASC');
        }

        return $qb->getQuery()->getResult();
    }
}
