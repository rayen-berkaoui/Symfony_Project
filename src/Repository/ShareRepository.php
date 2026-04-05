<?php

namespace App\Repository;

use App\Entity\Post;
use App\Entity\Share;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ShareRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Share::class);
    }

    public function countByPost(Post $post): int
    {
        return (int) $this->createQueryBuilder('s')
            ->select('COUNT(s.id)')
            ->andWhere('s.post = :post')
            ->setParameter('post', $post)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function countSharesByPostIds(array $postIds): array
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));
        if ([] === $postIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('s')
            ->select('IDENTITY(s.post) AS pid, COUNT(s.id) AS cnt')
            ->where('s.post IN (:ids)')
            ->groupBy('s.post')
            ->setParameter('ids', $postIds)
            ->getQuery()
            ->getArrayResult();

        $out = array_fill_keys($postIds, 0);
        foreach ($rows as $row) {
            $out[(int) $row['pid']] = (int) $row['cnt'];
        }

        return $out;
    }

    public function findRecentByPost(Post $post, int $limit = 25): array
    {
        return $this->createQueryBuilder('s')
            ->andWhere('s.post = :post')
            ->setParameter('post', $post)
            ->orderBy('s.createdAt', 'DESC')
            ->setMaxResults($limit)
            ->getQuery()
            ->getResult();
    }
}
