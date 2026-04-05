<?php

namespace App\Repository;

use App\Entity\Activity;
use App\Entity\Comment;
use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

class ActivityRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Activity::class);
    }

    public function findUserReactionOnPost(Post $post, string $userKey): ?Activity
    {
        $id = $this->getEntityManager()->getConnection()->fetchOne(
            <<<'SQL'
                SELECT id FROM activities
                WHERE post_id = :pid
                  AND TRIM(user_key) = TRIM(:uk)
                  AND UPPER(TRIM(activity_type)) IN ('LIKE', 'DISLIKE')
                  AND (comment_id IS NULL OR comment_id = 0)
                ORDER BY id ASC
                LIMIT 1
                SQL,
            ['pid' => $post->getId(), 'uk' => $userKey]
        );

        if (false === $id || null === $id) {
            return null;
        }

        return $this->find((int) $id);
    }

    public function findUserReactionOnComment(Comment $comment, string $userKey): ?Activity
    {
        $id = $this->getEntityManager()->getConnection()->fetchOne(
            <<<'SQL'
                SELECT id FROM activities
                WHERE comment_id = :cid
                  AND TRIM(user_key) = TRIM(:uk)
                  AND UPPER(TRIM(activity_type)) IN ('LIKE', 'DISLIKE')
                ORDER BY id ASC
                LIMIT 1
                SQL,
            ['cid' => $comment->getId(), 'uk' => $userKey]
        );

        if (false === $id || null === $id) {
            return null;
        }

        return $this->find((int) $id);
    }

    public function countPostReactions(Post $post, string $type): int
    {
        $count = $this->getEntityManager()->getConnection()->fetchOne(
            <<<'SQL'
                SELECT COUNT(*) FROM activities
                WHERE post_id = :pid
                  AND UPPER(TRIM(activity_type)) = UPPER(TRIM(:type))
                  AND (comment_id IS NULL OR comment_id = 0)
                SQL,
            ['pid' => $post->getId(), 'type' => $type]
        );

        return (int) $count;
    }

    public function countPostReactionsForPosts(array $postIds, string $type): array
    {
        $postIds = array_values(array_unique(array_map('intval', $postIds)));
        if ([] === $postIds) {
            return [];
        }

        $in = implode(',', $postIds);
        $typeNorm = strtoupper(trim($type));
        $rows = $this->getEntityManager()->getConnection()->fetchAllAssociative(
            <<<SQL
                SELECT post_id, COUNT(*) AS c FROM activities
                WHERE post_id IN ($in)
                  AND UPPER(TRIM(activity_type)) = ?
                  AND (comment_id IS NULL OR comment_id = 0)
                GROUP BY post_id
                SQL,
            [$typeNorm]
        );

        $out = array_fill_keys($postIds, 0);
        foreach ($rows as $row) {
            $out[(int) $row['post_id']] = (int) $row['c'];
        }

        return $out;
    }

    public function countCommentReactions(Comment $comment, string $type): int
    {
        $count = $this->getEntityManager()->getConnection()->fetchOne(
            <<<'SQL'
                SELECT COUNT(*) FROM activities
                WHERE comment_id = :cid
                  AND UPPER(TRIM(activity_type)) = UPPER(TRIM(:type))
                SQL,
            ['cid' => $comment->getId(), 'type' => $type]
        );

        return (int) $count;
    }
}
