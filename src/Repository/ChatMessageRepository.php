<?php

declare(strict_types=1);

namespace App\Repository;

use App\Entity\ChatMessage;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ChatMessageRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, ChatMessage::class);
    }

    public function findRecent(int $limit = 100): array
    {
        return $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }

    public function findRecentForPublic(?\DateTimeImmutable $cutoff, int $limit = 100): array
    {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC')
            ->setMaxResults(max(1, min(300, $limit)));

        if ($cutoff instanceof \DateTimeImmutable) {
            $qb->andWhere('m.createdAt >= :cutoff')
                ->setParameter('cutoff', $cutoff);
        }

        return $qb->getQuery()->getResult();
    }

    public function countDistinctUsersSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(DISTINCT m.userKey)')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countAll(): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function deleteByUserKeyInsensitive(string $userKey): int
    {
        $userKey = mb_strtolower(trim($userKey), 'UTF-8');
        if ('' === $userKey) {
            return 0;
        }

        return (int) $this->getEntityManager()->createQueryBuilder()
            ->delete(ChatMessage::class, 'm')
            ->where('LOWER(TRIM(m.userKey)) = :uk')
            ->setParameter('uk', $userKey)
            ->getQuery()
            ->execute();
    }

    public function deleteAll(): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->delete(ChatMessage::class, 'm')
            ->getQuery()
            ->execute();
    }

    public function countSince(\DateTimeImmutable $since): int
    {
        return (int) $this->createQueryBuilder('m')
            ->select('COUNT(m.id)')
            ->andWhere('m.createdAt >= :since')
            ->setParameter('since', $since)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function getTopSpeakersSince(\DateTimeImmutable $since, int $limit = 8): array
    {
        $limit = max(1, min(30, $limit));
        $conn = $this->getEntityManager()->getConnection();
        $rows = $conn->fetchAllAssociative(
            'SELECT user_key AS user_key, COUNT(*) AS c FROM chat_messages WHERE created_at >= ? GROUP BY user_key ORDER BY c DESC LIMIT '.$limit,
            [$since->format('Y-m-d H:i:s')]
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = ['user_key' => (string) $row['user_key'], 'msg_count' => (int) $row['c']];
        }

        return $out;
    }

    public function findPaginatedForAdmin(int $limit, int $offset, ?string $filterUser, ?string $search): array
    {
        $qb = $this->createQueryBuilder('m')
            ->orderBy('m.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults(max(1, min(100, $limit)));

        $this->applyAdminFilters($qb, $filterUser, $search);

        return $qb->getQuery()->getResult();
    }

    public function countForAdmin(?string $filterUser, ?string $search): int
    {
        $qb = $this->createQueryBuilder('m')
            ->select('COUNT(m.id)');
        $this->applyAdminFilters($qb, $filterUser, $search);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    private function applyAdminFilters(\Doctrine\ORM\QueryBuilder $qb, ?string $filterUser, ?string $search): void
    {
        $filterUser = null !== $filterUser ? trim($filterUser) : '';
        if ('' !== $filterUser) {
            $qb->andWhere('LOWER(m.userKey) LIKE :fuk')
                ->setParameter('fuk', '%'.mb_strtolower($filterUser).'%');
        }

        $search = null !== $search ? trim($search) : '';
        if ('' !== $search) {
            $qb->andWhere('m.content LIKE :sq OR m.userKey LIKE :sq2')
                ->setParameter('sq', '%'.$search.'%')
                ->setParameter('sq2', '%'.$search.'%');
        }
    }
}
