<?php

namespace App\Repository;

use App\Entity\ChatBan;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChatBanRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatBan::class);
    }

    public function isUserCurrentlyBanned(string $userKey): bool
    {
        $key = trim($userKey);
        if ('' === $key) {
            return false;
        }

        $now = new \DateTimeImmutable();

        $count = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.userKey = :uk')
            ->setParameter('uk', $key)
            ->andWhere('b.expiresAt IS NULL OR b.expiresAt > :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
