<?php

namespace App\Repository;

use App\Entity\Activite;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Activite>
 */
class ActiviteRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activite::class);
    }

    public function searchAndFilter(?string $search, ?string $categorie, ?int $etablissementId, string $sortBy = 'nomActivite', string $sortDirection = 'ASC'): array
    {
        $qb = $this->createQueryBuilder('a');

        if ($search) {
            $qb->andWhere('a.nomActivite LIKE :search OR a.description LIKE :search')
               ->setParameter('search', '%' . $search . '%');
        }

        if ($categorie) {
            $qb->andWhere('a.categorie = :categorie')
               ->setParameter('categorie', $categorie);
        }

        if ($etablissementId) {
            $qb->andWhere('a.etablissement = :etablissementId')
               ->setParameter('etablissementId', $etablissementId);
        }

        $allowedSortFields = ['nomActivite', 'description', 'categorie', 'duree', 'date'];
        if (in_array($sortBy, $allowedSortFields)) {
            $qb->orderBy('a.' . $sortBy, $sortDirection === 'DESC' ? 'DESC' : 'ASC');
        }

        return $qb->getQuery()->getResult();
    }
}
