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

    /**
     * @return list<ChatBan>
     */
    private function normalizeUserKey(string $userKey): string
    {
        return mb_strtolower(trim($userKey), 'UTF-8');
    }

    public function findOneByUserKeyInsensitive(string $userKey): ?ChatBan
    {
        $normalized = $this->normalizeUserKey($userKey);
        if ('' === $normalized) {
            return null;
        }

        return $this->createQueryBuilder('b')
            ->andWhere('LOWER(TRIM(b.userKey)) = :uk')
            ->setParameter('uk', $normalized)
            ->setMaxResults(1)
            ->getQuery()
            ->getOneOrNullResult();
    }

    public function countCurrentlyActiveBans(): int
    {
        $now = new \DateTimeImmutable();

        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.expiresAt IS NULL OR b.expiresAt > :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * Bans encore actifs mais dont la date d’expiration tombe dans les prochaines heures.
     */
    public function countActiveBansExpiringWithinHours(int $hours): int
    {
        $hours = max(1, min(168, $hours));
        $now = new \DateTimeImmutable();
        $until = $now->modify('+'.$hours.' hours');

        return (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('b.expiresAt IS NOT NULL')
            ->andWhere('b.expiresAt > :now')
            ->andWhere('b.expiresAt <= :until')
            ->setParameter('now', $now)
            ->setParameter('until', $until)
            ->getQuery()
            ->getSingleScalarResult();
    }

    /**
     * @return list<ChatBan>
     */
    public function findAllOrderedByNewest(): array
    {
        return $this->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    /**
     * @return list<ChatBan>
     */
    public function findPaginatedOrderedByNewest(int $limit, int $offset): array
    {
        $limit = max(1, $limit);
        $offset = max(0, $offset);

        return $this->createQueryBuilder('b')
            ->orderBy('b.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->setFirstResult($offset)
            ->getQuery()
            ->getResult();
    }

    public function isUserCurrentlyBanned(string $userKey): bool
    {
        $key = $this->normalizeUserKey($userKey);
        if ('' === $key) {
            return false;
        }

        $now = new \DateTimeImmutable();

        $count = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('LOWER(TRIM(b.userKey)) = :uk')
            ->setParameter('uk', $key)
            ->andWhere('b.expiresAt IS NULL OR b.expiresAt > :now')
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }

    public function isUserCurrentlyBannedForScope(string $userKey, string $scope): bool
    {
        $key = $this->normalizeUserKey($userKey);
        if ('' === $key) {
            return false;
        }

        $field = match ($scope) {
            ChatBan::SCOPE_CHAT => 'b.blockChat',
            ChatBan::SCOPE_POSTS => 'b.blockPosts',
            ChatBan::SCOPE_COMMENTS => 'b.blockComments',
            ChatBan::SCOPE_REACTIONS => 'b.blockReactions',
            ChatBan::SCOPE_SHARES => 'b.blockShares',
            default => null,
        };

        if (null === $field) {
            return false;
        }

        $now = new \DateTimeImmutable();
        $count = (int) $this->createQueryBuilder('b')
            ->select('COUNT(b.id)')
            ->andWhere('LOWER(TRIM(b.userKey)) = :uk')
            ->setParameter('uk', $key)
            ->andWhere('b.expiresAt IS NULL OR b.expiresAt > :now')
            ->andWhere($field.' = :enabled')
            ->setParameter('enabled', true)
            ->setParameter('now', $now)
            ->getQuery()
            ->getSingleScalarResult();

        return $count > 0;
    }
}
