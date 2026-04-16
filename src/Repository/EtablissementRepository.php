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

    /**
     * Recherche intelligente basée sur une phrase en langage naturel
     */
    public function intelligentSearch(string $query): array
    {
        // 1. Nettoyer et séparer les mots de la requête
        $motsCles = array_filter(explode(' ', strtolower(trim($query))), function($mot) {
            // Ignorer les mots de liaison trop courts
            return strlen($mot) > 2;
        });

        $qb = $this->createQueryBuilder('e');

        // 2. Construire la requête dynamiquement pour chaque mot clé
        foreach ($motsCles as $index => $mot) {
            $param = 'val' . $index;
            // On cherche dans le nom, la description, la ville ou le type
            $qb->andWhere($qb->expr()->orX(
                $qb->expr()->like('LOWER(e.nom)', ':' . $param),
                $qb->expr()->like('LOWER(e.description)', ':' . $param),
                $qb->expr()->like('LOWER(e.ville)', ':' . $param),
                $qb->expr()->like('LOWER(e.type)', ':' . $param)
            ))
            ->setParameter($param, '%' . $mot . '%');
        }

        return $qb->getQuery()->getResult();
    }

    /**
     * Récupérer les Villes les plus populaires (Tendances)
     */
    public function findPopularCities(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.ville', 'COUNT(e.idEtablissement) as total')
            ->groupBy('e.ville')
            ->orderBy('total', 'DESC')
            ->setMaxResults(5)
            ->getQuery()
            ->getResult();
    }

    /**
     * Répartition par type d'établissement pour statistiques circulaires
     */
    public function getDistributionByType(): array
    {
        return $this->createQueryBuilder('e')
            ->select('e.type', 'COUNT(e.idEtablissement) as total')
            ->groupBy('e.type')
            ->getQuery()
            ->getResult();
    }
}
