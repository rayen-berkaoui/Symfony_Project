<?php

namespace App\Repository;

use App\Entity\Comment;
use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class CommentRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Comment::class);
    }

    public function findByPostOrdered(Post $post): array
    {
        return $this->createQueryBuilder('c')
            ->andWhere('c.post = :post')
            ->setParameter('post', $post)
            ->orderBy('c.createdAt', 'ASC')
            ->getQuery()
            ->getResult();
    }

    public function countCommentsByPostIds(array $postIds): array
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));
        if ([] === $postIds) {
            return [];
        }

        $rows = $this->createQueryBuilder('c')
            ->select('IDENTITY(c.post) AS pid, COUNT(c.id) AS cnt')
            ->where('c.post IN (:ids)')
            ->groupBy('c.post')
            ->setParameter('ids', $postIds)
            ->getQuery()
            ->getArrayResult();

        $out = array_fill_keys($postIds, 0);
        foreach ($rows as $row) {
            $out[(int) $row['pid']] = (int) $row['cnt'];
        }

        return $out;
    }
}
