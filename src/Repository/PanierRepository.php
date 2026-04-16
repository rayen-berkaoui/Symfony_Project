<?php

namespace App\Repository;

use App\Entity\Panier;
use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\ORM\QueryBuilder;
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

    public function findPendingByUser(Utilisateur $user): array
    {
        return $this->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')->addSelect('u')
            ->where('p.utilisateur = :user')
            ->andWhere('LOWER(p.statutItem) LIKE :status')
            ->setParameter('user', $user)
            ->setParameter('status', '%attente%')
            ->orderBy('p.id', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countPendingByUser(Utilisateur $user): int
    {
        return (int) $this->createQueryBuilder('p')
            ->select('COUNT(p.id)')
            ->where('p.utilisateur = :user')
            ->andWhere('LOWER(p.statutItem) LIKE :status')
            ->setParameter('user', $user)
            ->setParameter('status', '%attente%')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function totalPendingByUser(Utilisateur $user): float
    {
        return (float) ($this->createQueryBuilder('p')
            ->select('SUM(p.prixEstime)')
            ->where('p.utilisateur = :user')
            ->andWhere('LOWER(p.statutItem) LIKE :status')
            ->setParameter('user', $user)
            ->setParameter('status', '%attente%')
            ->getQuery()
            ->getSingleScalarResult() ?? 0);
    }

    public function searchByFilters(?string $search, ?string $status, ?string $type): QueryBuilder
    {
        $qb = $this->createQueryBuilder('p')
            ->leftJoin('p.utilisateur', 'u')
            ->addSelect('u');

        if ($search) {
            $qb
                ->andWhere('CAST(p.id AS string) LIKE :search OR CAST(p.serviceId AS string) LIKE :search OR p.typeService LIKE :search OR p.statutItem LIKE :search OR u.nom LIKE :search OR u.prenom LIKE :search OR u.email LIKE :search')
                ->setParameter('search', '%' . $search . '%');
        }

        if ($status) {
            $qb->andWhere('LOWER(p.statutItem) LIKE :status')
                ->setParameter('status', '%' . strtolower($status) . '%');
        }

        if ($type) {
            $qb->andWhere('p.typeService = :type')->setParameter('type', $type);
        }

        return $qb->orderBy('p.id', 'DESC');
    }

    public function getStats(): array
    {
        $total = (int) $this->count([]);
        $pending = (int) $this->createQueryBuilder('p')->select('COUNT(p.id)')->where('LOWER(p.statutItem) LIKE :status')->setParameter('status', '%attente%')->getQuery()->getSingleScalarResult();
        $confirmed = (int) $this->createQueryBuilder('p')->select('COUNT(p.id)')->where('LOWER(p.statutItem) LIKE :status')->setParameter('status', '%conf%')->getQuery()->getSingleScalarResult();
        $cancelled = (int) $this->createQueryBuilder('p')->select('COUNT(p.id)')->where('LOWER(p.statutItem) LIKE :status')->setParameter('status', '%annul%')->getQuery()->getSingleScalarResult();
        $estimate = (float) ($this->createQueryBuilder('p')->select('SUM(p.prixEstime)')->getQuery()->getSingleScalarResult() ?? 0);

        return [
            'total' => $total,
            'en_attente' => $pending,
            'confirme' => $confirmed,
            'annule' => $cancelled,
            'total_prix' => $estimate,
        ];
    }

    public function getTopClients(int $limit = 5): array
    {
        return $this->createQueryBuilder('p')
            ->select('u.nom AS nom, u.prenom AS prenom, u.email AS email, COUNT(p.id) AS panier_count, SUM(p.prixEstime) AS total_estime')
            ->join('p.utilisateur', 'u')
            ->groupBy('u.id')
            ->orderBy('panier_count', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getArrayResult();
    }

    public function getTypeBreakdown(): array
    {
        return $this->createQueryBuilder('p')
            ->select('p.typeService AS type_service, COUNT(p.id) AS total')
            ->groupBy('p.typeService')
            ->orderBy('total', 'DESC')
            ->getQuery()
            ->getArrayResult();
    }
}
