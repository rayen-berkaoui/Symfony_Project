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
        $numTel = is_numeric($identifier) ? (int) $identifier : -1;

        return $this->createQueryBuilder('u')
            ->andWhere('u.email = :identifier OR u.numTel = :numTel OR u.nom = :identifier')
            ->setParameter('identifier', $identifier)
            ->setParameter('numTel', $numTel)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function findOneByOauthAccount(string $provider, string $oauthId): ?Utilisateur
    {
        return $this->createQueryBuilder('u')
            ->andWhere('u.oauthProvider = :provider')
            ->andWhere('u.oauthId = :oauthId')
            ->setParameter('provider', strtolower($provider))
            ->setParameter('oauthId', $oauthId)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
