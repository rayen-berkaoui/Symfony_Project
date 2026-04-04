<?php

namespace App\Repository;

use App\Entity\Reservation;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Reservation>
 */
class ReservationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Reservation::class);
    }

    /**
     * Find all reservations for a session (via panier)
     */
    public function findBySessionId(string $sessionId): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.panier', 'p')
            ->leftJoin('p.lieuTouristique', 'l')
            ->addSelect('p', 'l')
            ->where('p.sessionId = :sessionId')
            ->setParameter('sessionId', $sessionId)
            ->orderBy('r.datePaiement', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * Search reservations (for admin)
     */
    public function searchByQuery(string $query)
    {
        $qb = $this->createQueryBuilder('r')
            ->leftJoin('r.panier', 'p')
            ->leftJoin('p.lieuTouristique', 'l')
            ->addSelect('p', 'l');

        if ($query) {
            $qb->where('r.codeConfirmation LIKE :query')
                ->orWhere('r.modePaiement LIKE :query')
                ->orWhere('r.statutPaiement LIKE :query')
                ->orWhere('l.nom LIKE :query')
                ->orWhere('l.ville LIKE :query')
                ->setParameter('query', '%' . $query . '%');
        }

        return $qb->orderBy('r.datePaiement', 'DESC');
    }

    /**
     * Get all reservations (admin)
     */
    public function findAllWithDetails()
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.panier', 'p')
            ->leftJoin('p.lieuTouristique', 'l')
            ->addSelect('p', 'l')
            ->orderBy('r.datePaiement', 'DESC');
    }

    /**
     * Count by payment status
     */
    public function countByStatut(string $statut): int
    {
        return (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->where('r.statutPaiement = :statut')
            ->setParameter('statut', $statut)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Get total revenue (paid reservations)
     */
    public function getTotalRevenue(): float
    {
        $result = $this->createQueryBuilder('r')
            ->select('SUM(r.montantTotal)')
            ->where('r.statutPaiement = :statut')
            ->setParameter('statut', 'Payé')
            ->getQuery()
            ->getSingleScalarResult();

        return (float) ($result ?? 0);
    }

    /**
     * Get stats for dashboard
     */
    public function getStats(): array
    {
        $total = (int) $this->createQueryBuilder('r')
            ->select('COUNT(r.id)')
            ->getQuery()
            ->getSingleScalarResult();

        $paye = $this->countByStatut('Payé');
        $enCours = $this->countByStatut('En cours de paiement');
        $revenue = $this->getTotalRevenue();

        $avgRating = $this->createQueryBuilder('r')
            ->select('AVG(r.rating)')
            ->where('r.rating IS NOT NULL')
            ->getQuery()
            ->getSingleScalarResult();

        return [
            'total' => $total,
            'paye' => $paye,
            'en_cours' => $enCours,
            'revenue' => $revenue,
            'avg_rating' => $avgRating ? round((float)$avgRating, 1) : null,
        ];
    }

    /**
     * Get recent reservations
     */
    public function findRecent(int $limit = 5): array
    {
        return $this->createQueryBuilder('r')
            ->leftJoin('r.panier', 'p')
            ->leftJoin('p.lieuTouristique', 'l')
            ->addSelect('p', 'l')
            ->orderBy('r.datePaiement', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
