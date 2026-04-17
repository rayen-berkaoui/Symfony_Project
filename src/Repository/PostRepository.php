<?php

namespace App\Repository;

use App\Entity\Post;
use App\Service\PostTagIndexBuilder;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\DBAL\Types\Types;
use Doctrine\ORM\QueryBuilder;
use Doctrine\Persistence\ManagerRegistry;

class PostRepository extends ServiceEntityRepository
{
    public function __construct(
        ManagerRegistry $registry,
        private readonly PostTagIndexBuilder $postTagIndexBuilder,
    ) {
        parent::__construct($registry, Post::class);
    }

    public function findAllOrderedByNewest(): array
    {
        return $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->getQuery()
            ->getResult();
    }

    public function countFiltered(?string $q, ?string $hashtag, ?\DateTimeImmutable $dateFrom, ?\DateTimeImmutable $dateTo): int
    {
        $qb = $this->createQueryBuilder('p')->select('COUNT(p.id)');
        $this->applyListFilters($qb, $q, $hashtag, $dateFrom, $dateTo);

        return (int) $qb->getQuery()->getSingleScalarResult();
    }

    public function findFilteredPaginated(?string $q, ?string $hashtag, ?\DateTimeImmutable $dateFrom, ?\DateTimeImmutable $dateTo, int $limit, int $offset): array
    {
        $qb = $this->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($limit);
        $this->applyListFilters($qb, $q, $hashtag, $dateFrom, $dateTo);

        return $qb->getQuery()->getResult();
    }

    public function countPostsContainingHashtag(string $normalizedTag): int
    {
        $tag = mb_strtolower(trim($normalizedTag), 'UTF-8');
        $tag = ltrim($tag, '#');
        if ('' === $tag || !preg_match('/^[\p{L}\p{N}_]+$/u', $tag)) {
            return 0;
        }

        return (int) $this->getEntityManager()->getConnection()->fetchOne(
            'SELECT COUNT(*) FROM posts WHERE hashtags_index IS NOT NULL AND LOWER(CONCAT(\' \', hashtags_index, \' \')) LIKE ?',
            ['%|'.$tag.'|%']
        );
    }

    private function applyListFilters(QueryBuilder $qb, ?string $q, ?string $hashtag, ?\DateTimeImmutable $dateFrom, ?\DateTimeImmutable $dateTo): void
    {
        if (null !== $q && '' !== $q) {
            $needle = '%'.$this->normalizeForLike($q).'%';
            $qb->andWhere('LOWER(p.content) LIKE :tbn_q')
                ->setParameter('tbn_q', $needle);
        }

        if (null !== $hashtag && '' !== trim($hashtag)) {
            $tags = $this->postTagIndexBuilder->parseFilterInput($hashtag);
            if ([] !== $tags) {
                $ors = [];
                foreach ($tags as $i => $tag) {
                    $param = 'tbn_ht_'.$i;
                    $esc = $this->postTagIndexBuilder->escapeLike($tag);
                    $needle = '%|'.$esc.'|%';
                    $qb->setParameter($param, $needle);
                    $ors[] = 'LOWER(COALESCE(p.hashtagsIndex, \'\')) LIKE :'.$param;
                }
                $qb->andWhere('('.implode(' OR ', $ors).')');
            }
        }

        if ($dateFrom instanceof \DateTimeImmutable) {
            $from = $dateFrom->setTime(0, 0, 0);
            $qb->andWhere('p.createdAt >= :tbn_df')
                ->setParameter('tbn_df', $from, Types::DATETIME_IMMUTABLE);
        }

        if ($dateTo instanceof \DateTimeImmutable) {
            $to = $dateTo->setTime(23, 59, 59);
            $qb->andWhere('p.createdAt <= :tbn_dt')
                ->setParameter('tbn_dt', $to, Types::DATETIME_IMMUTABLE);
        }
    }

    private function normalizeForLike(string $value): string
    {
        if (\function_exists('mb_strtolower')) {
            return mb_strtolower($value, 'UTF-8');
        }

        return strtolower($value);
    }

}
