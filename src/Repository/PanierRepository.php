<?php

namespace App\Repository;

use App\Entity\Panier;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Panier>
 */
class PanierRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Panier::class);
    }

    /**
     * Find all cart items for a session
     */
    public function findBySessionId(string $sessionId): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.lieuTouristique', 'l')
            ->addSelect('l')
            ->where('p.sessionId = :sessionId')
            ->andWhere('p.statutItem = :statut')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('statut', 'en_attente')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Count cart items for a session
     */
    public function countBySessionId(string $sessionId): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.sessionId = :sessionId')
            ->andWhere('p.statutItem = :statut')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('statut', 'en_attente')
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get total price for a session's cart
     */
    public function totalBySessionId(string $sessionId): float
    {
        $result = $this->createQueryBuilder('p')
            ->select('SUM(p.prixEstime)')
            ->where('p.sessionId = :sessionId')
            ->andWhere('p.statutItem = :statut')
            ->setParameter('sessionId', $sessionId)
            ->setParameter('statut', 'en_attente')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Search panier items (for admin)
     */
    public function searchByQuery(string $query)
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.lieuTouristique', 'l')
            ->addSelect('l');

        if ($query) {
            $qb->where('l.nom LIKE :query')
                ->orWhere('l.ville LIKE :query')
                ->orWhere('p.sessionId LIKE :query')
                ->orWhere('p.typeService LIKE :query')
                ->orWhere('p.statutItem LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        return $qb->orderBy('p.createdAt', 'DESC');
    }

    /**
     * Get all cart items (admin)
     */
    public function findAllWithLieu()
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.lieuTouristique', 'l')
            ->addSelect('l')
            ->orderBy('p.createdAt', 'DESC');
    }

    /**
     * Count by status
     */
    public function countByStatut(string $statut): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.statutItem = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get stats for dashboard
     */
    public function getStats(): array
    {
        $total = (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $enAttente = $this->countByStatut('en_attente');
        $confirme = $this->countByStatut('confirmé');

        $totalPrix = (float) $this->createQueryBuilder('p')
            ->select('SUM(p.prixEstime)')
            ->where('p.statutItem = :statut')
            ->setParameter('statut', 'en_attente')
            ->getQuery()
            ->getSingleScalarResult() ?? 0;

        return [
            'total' => $total,
            'en_attente' => $enAttente,
            'confirme' => $confirme,
            'total_prix' => $totalPrix,
        ];
    }
}
