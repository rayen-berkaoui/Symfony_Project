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

    /**
     * Récupérer les Catégories d'activités les plus tendances
     */
    public function findTrendingCategories(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.categorie', 'COUNT(a.idActivite) as total')
            ->groupBy('a.categorie')
            ->orderBy('total', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    /**
     * Répartition par statut pour statistiques circulaires
     */
    public function getDistributionByStatut(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.statut', 'COUNT(a.idActivite) as total')
            ->groupBy('a.statut')
            ->getQuery()
            ->getResult();
    }

    /**
     * Répartition par niveau
     */
    public function getDistributionByNiveau(): array
    {
        return $this->createQueryBuilder('a')
            ->select('a.niveau', 'COUNT(a.idActivite) as total')
            ->groupBy('a.niveau')
            ->orderBy('total', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    /**
     * Recherche intelligente basée sur une phrase en langage naturel
     */
    public function intelligentSearch(string $query): array
    {
        $motsCles = array_filter(explode(' ', strtolower(trim($query))), function($mot) {
            return strlen($mot) > 2;
        });

        $qb = $this->createQueryBuilder('a');

        foreach ($motsCles as $index => $mot) {
            $param = 'val' . $index;
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('LOWER(a.nomActivite)', ':' . $param),
                $qb->expr()->like('LOWER(a.description)', ':' . $param),
                $qb->expr()->like('LOWER(a.categorie)', ':' . $param),
                $qb->expr()->like('LOWER(a.niveau)', ':' . $param),
                $qb->expr()->like('LOWER(a.statut)', ':' . $param)
            ))
            ->setParameter($param, '%' . $mot . '%');
        }

        return $qb->getQuery()->getResult();
    }
}
