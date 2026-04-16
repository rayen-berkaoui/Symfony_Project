<?php

namespace App\Repository;

use App\Entity\Utilisateur;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Utilisateur>
 */
class UtilisateurRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Utilisateur::class);
    }

    /**
     * @return Utilisateur|null
     */
    public function findByEmail(string $email): ?Utilisateur
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :email')
            ->setParameter('email', $email)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return Utilisateur[] Returns an array of Utilisateur objects
     */
    public function findAllOrderedByName(): array
    {
        return $this->createQueryBuilder('u')
            ->orderBy('u.nom', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function findByEmailOrNumTel(string $identifier): ?Utilisateur
    {
        $identifier = trim($identifier);
        $numTel = is_numeric($identifier) ? (int) $identifier : -1;

        return $this->createQueryBuilder('u')
            ->andWhere('LOWER(u.email) = LOWER(:identifier) OR u.numTel = :numTel OR LOWER(u.nom) = LOWER(:identifier) OR LOWER(u.prenom) = LOWER(:identifier)')
            ->setParameter('identifier', $identifier)
            ->setParameter('numTel', $numTel)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
