<?php

declare(strict_types=1);

namespace App\Repository;

use App\Chat\ChatAdmin;
use App\Entity\Notification;
use App\Entity\Post;
use Doctrine\Bundle\DoctrineBundle\Repository\ServiceEntityRepository;
use Doctrine\Persistence\ManagerRegistry;

/**
 * @extends ServiceEntityRepository<Notification>
 */
class NotificationRepository extends ServiceEntityRepository
{
    public function __construct(ManagerRegistry $registry)
    {
        parent::__construct($registry, Notification::class);
    }

    public function countUnreadForRecipient(string $recipientKey): int
    {
        $recipientKey = trim($recipientKey);
        if ('' === $recipientKey) {
            return 0;
        }

        return (int) $this->createQueryBuilder('n')
            ->select('COUNT(n.id)')
            ->andWhere('n.recipientKey = :rk')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('rk', $recipientKey)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getSingleScalarResult();
    }

    public function findOneForRecipientById(string $recipientKey, int $id): ?Notification
    {
        $recipientKey = trim($recipientKey);
        if ('' === $recipientKey || $id < 1) {
            return null;
        }

        return $this->createQueryBuilder('n')
            ->andWhere('n.id = :id')
            ->andWhere('n.recipientKey = :rk')
            ->setParameter('id', $id)
            ->setParameter('rk', $recipientKey)
            ->getQuery()
            ->getOneOrNullResult();
    }

    /**
     * @return list<Notification>
     */
    public function findRecentForRecipient(string $recipientKey, int $limit = 20): array
    {
        $recipientKey = trim($recipientKey);
        if ('' === $recipientKey) {
            return [];
        }

        return $this->createQueryBuilder('n')
            ->andWhere('n.recipientKey = :rk')
            ->setParameter('rk', $recipientKey)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(max(1, min(100, $limit)))
            ->getQuery()
            ->getResult();
    }

    public function markAllReadForRecipient(string $recipientKey): void
    {
        $recipientKey = trim($recipientKey);
        if ('' === $recipientKey) {
            return;
        }

        $this->getEntityManager()->createQueryBuilder()
            ->update(Notification::class, 'n')
            ->set('n.isRead', ':isRead')
            ->where('n.recipientKey = :rk')
            ->andWhere('n.isRead = :current')
            ->setParameter('isRead', true)
            ->setParameter('current', false)
            ->setParameter('rk', $recipientKey)
            ->getQuery()
            ->execute();
    }

    public function deleteByPost(Post $post): int
    {
        return (int) $this->getEntityManager()->createQueryBuilder()
            ->delete(Notification::class, 'n')
            ->where('n.post = :post')
            ->setParameter('post', $post)
            ->getQuery()
            ->execute();
    }

    /**
     * @return list<Notification>
     */
    public function findUnreadBadWordAlertsForAdmin(int $limit = 50): array
    {
        return $this->createQueryBuilder('n')
            ->andWhere('n.recipientKey = :rk')
            ->andWhere('n.type = :type')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('rk', ChatAdmin::BROADCAST_USER_KEY)
            ->setParameter('type', Notification::TYPE_BAD_WORD_DETECTED)
            ->setParameter('isRead', false)
            ->orderBy('n.createdAt', 'DESC')
            ->setMaxResults(max(1, min(200, $limit)))
            ->getQuery()
            ->getResult();
    }

    public function findUnreadBadWordAlertByIdForAdmin(int $id): ?Notification
    {
        if ($id < 1) {
            return null;
        }

        return $this->createQueryBuilder('n')
            ->andWhere('n.id = :id')
            ->andWhere('n.recipientKey = :rk')
            ->andWhere('n.type = :type')
            ->andWhere('n.isRead = :isRead')
            ->setParameter('id', $id)
            ->setParameter('rk', ChatAdmin::BROADCAST_USER_KEY)
            ->setParameter('type', Notification::TYPE_BAD_WORD_DETECTED)
            ->setParameter('isRead', false)
            ->getQuery()
            ->getOneOrNullResult();
    }
}
