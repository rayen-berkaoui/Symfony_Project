<?php

declare(strict_types=1);

namespace App\Service;

use Doctrine\ORM\EntityManagerInterface;

/**
 * Agrégations pour le tableau de bord admin (graphiques et cartes).
 */
final class AdminAnalyticsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    /**
     * @return array{posts: list<array{date: string, count: int}>, comments: list<array{date: string, count: int}>}
     */
    public function getDailyActivityLastDays(int $days): array
    {
        $days = max(1, min(90, $days));
        $conn = $this->entityManager->getConnection();
        $start = (new \DateTimeImmutable('today'))->modify(sprintf('-%d days', $days - 1))->setTime(0, 0);

        $postRows = $conn->fetchAllAssociative(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM posts WHERE created_at >= :start GROUP BY DATE(created_at)',
            ['start' => $start->format('Y-m-d H:i:s')]
        );
        $commentRows = $conn->fetchAllAssociative(
            'SELECT DATE(created_at) AS d, COUNT(*) AS c FROM comments WHERE created_at >= :start GROUP BY DATE(created_at)',
            ['start' => $start->format('Y-m-d H:i:s')]
        );

        $postMap = [];
        foreach ($postRows as $row) {
            $postMap[(string) $row['d']] = (int) $row['c'];
        }
        $comMap = [];
        foreach ($commentRows as $row) {
            $comMap[(string) $row['d']] = (int) $row['c'];
        }

        $posts = [];
        $comments = [];
        for ($i = 0; $i < $days; ++$i) {
            $d = $start->modify('+'.$i.' days')->format('Y-m-d');
            $posts[] = ['date' => $d, 'count' => $postMap[$d] ?? 0];
            $comments[] = ['date' => $d, 'count' => $comMap[$d] ?? 0];
        }

        return ['posts' => $posts, 'comments' => $comments];
    }

    /**
     * @return list<int> 24 entrées (heures 0–23), publications créées aujourd’hui.
     */
    public function getHourlyPostsToday(): array
    {
        $conn = $this->entityManager->getConnection();
        $today = (new \DateTimeImmutable('today'))->format('Y-m-d');
        $rows = $conn->fetchAllAssociative(
            'SELECT HOUR(created_at) AS h, COUNT(*) AS c FROM posts
             WHERE DATE(created_at) = :d GROUP BY HOUR(created_at)',
            ['d' => $today]
        );
        $map = array_fill(0, 24, 0);
        foreach ($rows as $row) {
            $h = (int) $row['h'];
            if ($h >= 0 && $h <= 23) {
                $map[$h] = (int) $row['c'];
            }
        }

        return $map;
    }

    /**
     * @return array{like: int, dislike: int, share_activity: int}
     */
    public function getPostReactionTotals(): array
    {
        $conn = $this->entityManager->getConnection();
        $rows = $conn->fetchAllAssociative(
            "SELECT UPPER(TRIM(activity_type)) AS t, COUNT(*) AS c FROM activities
             WHERE comment_id IS NULL OR comment_id = 0
             GROUP BY UPPER(TRIM(activity_type))"
        );
        $out = ['like' => 0, 'dislike' => 0, 'share_activity' => 0];
        foreach ($rows as $row) {
            $t = (string) $row['t'];
            $c = (int) $row['c'];
            if ('LIKE' === $t) {
                $out['like'] = $c;
            } elseif ('DISLIKE' === $t) {
                $out['dislike'] = $c;
            } elseif ('SHARE' === $t) {
                $out['share_activity'] = $c;
            }
        }

        return $out;
    }

    /**
     * @return array<string, int>
     */
    private function aggregateHashtagCounts(): array
    {
        $conn = $this->entityManager->getConnection();
        $rows = $conn->fetchAllAssociative(
            'SELECT hashtags_index FROM posts WHERE hashtags_index IS NOT NULL AND TRIM(hashtags_index) != \'\''
        );
        $counts = [];
        foreach ($rows as $row) {
            $raw = trim((string) ($row['hashtags_index'] ?? ''));
            if ('' === $raw) {
                continue;
            }
            foreach (preg_split('/\s+/u', $raw, -1, \PREG_SPLIT_NO_EMPTY) ?: [] as $token) {
                $tag = mb_strtolower(trim($token), 'UTF-8');
                if ('' === $tag) {
                    continue;
                }
                $counts[$tag] = ($counts[$tag] ?? 0) + 1;
            }
        }

        return $counts;
    }

    /**
     * @return list<array{tag: string, count: int}>
     */
    public function getTopHashtags(int $limit = 10): array
    {
        $limit = max(1, min(5000, $limit));
        $counts = $this->aggregateHashtagCounts();
        arsort($counts, \SORT_NUMERIC);
        $out = [];
        foreach ($counts as $tag => $c) {
            $out[] = ['tag' => $tag, 'count' => $c];
            if (\count($out) >= $limit) {
                break;
            }
        }

        return $out;
    }

    public function countDistinctHashtags(): int
    {
        return \count($this->aggregateHashtagCounts());
    }

    public function countPostsContainingHashtag(string $normalizedTag): int
    {
        $tag = mb_strtolower(trim($normalizedTag), 'UTF-8');
        $tag = ltrim($tag, '#');
        if ('' === $tag || !preg_match('/^[\p{L}\p{N}_]+$/u', $tag)) {
            return 0;
        }

        $conn = $this->entityManager->getConnection();

        return (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM posts WHERE hashtags_index IS NOT NULL AND LOWER(CONCAT(\' \', hashtags_index, \' \')) LIKE ?',
            ['% '.$tag.' %']
        );
    }

    public function getTotalShareRows(): int
    {
        return (int) $this->entityManager->getConnection()->fetchOne('SELECT COUNT(*) FROM shares');
    }

    /**
     * @return list<array{user_key: string, count: int}>
     */
    public function getTopCommenters(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $conn = $this->entityManager->getConnection();
        $rows = $conn->fetchAllAssociative(
            'SELECT user_key, COUNT(*) AS c FROM comments GROUP BY user_key ORDER BY c DESC LIMIT '.$limit
        );
        $out = [];
        foreach ($rows as $row) {
            $out[] = ['user_key' => (string) $row['user_key'], 'count' => (int) $row['c']];
        }

        return $out;
    }

    /**
     * @return list<array{post_id: int, comment_count: int, excerpt: string}>
     */
    public function getMostCommentedPosts(int $limit = 10): array
    {
        $limit = max(1, min(50, $limit));
        $conn = $this->entityManager->getConnection();
        $rows = $conn->fetchAllAssociative(
            'SELECT p.id AS pid, COUNT(c.id) AS cnt, p.content AS content
                FROM posts p
                INNER JOIN comments c ON c.post_id = p.id
                GROUP BY p.id, p.content
                ORDER BY cnt DESC
                LIMIT '.$limit
        );
        $out = [];
        foreach ($rows as $row) {
            $content = (string) $row['content'];
            $excerpt = mb_strlen($content, 'UTF-8') > 80 ? mb_substr($content, 0, 77, 'UTF-8').'…' : $content;
            $out[] = [
                'post_id' => (int) $row['pid'],
                'comment_count' => (int) $row['cnt'],
                'excerpt' => $excerpt,
            ];
        }

        return $out;
    }

    /**
     * Avertissements : bans avec expiration vs permanents (approximation des « alertes »).
     *
     * @return array{alert1: int, alert2: int, blocked: int}
     */
    public function getWarningBanBreakdown(): array
    {
        $conn = $this->entityManager->getConnection();
        $now = (new \DateTimeImmutable())->format('Y-m-d H:i:s');
        $withExpiry = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM chat_bans WHERE expires_at IS NOT NULL AND expires_at > ?',
            [$now]
        );
        $permanent = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM chat_bans WHERE expires_at IS NULL'
        );
        $expired = (int) $conn->fetchOne(
            'SELECT COUNT(*) FROM chat_bans WHERE expires_at IS NOT NULL AND expires_at <= ?',
            [$now]
        );

        return [
            'alert1' => $withExpiry,
            'alert2' => $expired,
            'blocked' => $permanent,
        ];
    }
}
