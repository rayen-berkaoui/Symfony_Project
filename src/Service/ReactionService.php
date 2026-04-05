<?php

namespace App\Service;

use App\Entity\Activity;
use App\Entity\Comment;
use App\Entity\Post;
use App\Exception\ActivityMigrationNeededException;
use App\Repository\ActivityRepository;
use Doctrine\DBAL\Exception\UniqueConstraintViolationException;
use Doctrine\ORM\EntityManagerInterface;

class ReactionService
{
    public function __construct(
        private readonly ActivityRepository $activityRepository,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function togglePostReaction(Post $post, string $userKey, string $desiredType): void
    {
        $userKey = trim($userKey);
        if ('' === $userKey) {
            return;
        }

        if (!\in_array($desiredType, [Activity::TYPE_LIKE, Activity::TYPE_DISLIKE], true)) {
            return;
        }

        $existing = $this->activityRepository->findUserReactionOnPost($post, $userKey);

        if (null === $existing) {
            $this->persistNewReaction($post, null, $userKey, $desiredType);

            return;
        }

        if ($this->sameReactionType($existing->getActivityType(), $desiredType)) {
            $this->entityManager->remove($existing);
            $this->flushActivityState();

            return;
        }

        $existing->setActivityType($desiredType);
        $this->flushActivityState();
    }

    public function toggleCommentReaction(Comment $comment, string $userKey, string $desiredType): void
    {
        $userKey = trim($userKey);
        if ('' === $userKey) {
            return;
        }

        if (!\in_array($desiredType, [Activity::TYPE_LIKE, Activity::TYPE_DISLIKE], true)) {
            return;
        }

        $post = $comment->getPost();
        if (!$post instanceof Post) {
            return;
        }

        $existing = $this->activityRepository->findUserReactionOnComment($comment, $userKey);

        if (null === $existing) {
            $this->persistNewReaction($post, $comment, $userKey, $desiredType);
            $this->syncCommentCounters($comment);

            return;
        }

        if ($this->sameReactionType($existing->getActivityType(), $desiredType)) {
            $this->entityManager->remove($existing);
            $this->flushActivityState();
            $this->syncCommentCounters($comment);

            return;
        }

        $existing->setActivityType($desiredType);
        $this->flushActivityState();
        $this->syncCommentCounters($comment);
    }

    private function persistNewReaction(Post $post, ?Comment $comment, string $userKey, string $type): void
    {
        $activity = new Activity();
        $activity->setPost($post);
        $activity->setComment($comment);
        $activity->setUserKey($userKey);
        $activity->setActivityType($type);

        $this->entityManager->persist($activity);
        $this->flushActivityState();
    }

    private function syncCommentCounters(Comment $comment): void
    {
        $likes = $this->activityRepository->countCommentReactions($comment, Activity::TYPE_LIKE);
        $dislikes = $this->activityRepository->countCommentReactions($comment, Activity::TYPE_DISLIKE);
        $comment->setLikesCount($likes);
        $comment->setDislikesCount($dislikes);
        $this->entityManager->flush();
    }

    private function sameReactionType(string $stored, string $desired): bool
    {
        return strtoupper(trim($stored)) === strtoupper(trim($desired));
    }

    private function flushActivityState(): void
    {
        try {
            $this->entityManager->flush();
        } catch (\Throwable $e) {
            if ($this->isUniqueActivityViolation($e)) {
                throw ActivityMigrationNeededException::forUniqueActivity();
            }

            throw $e;
        }
    }

    private function isUniqueActivityViolation(\Throwable $e): bool
    {
        $current = $e;
        while (true) {
            if ($current instanceof UniqueConstraintViolationException) {
                return true;
            }

            if (str_contains($current->getMessage(), 'unique_activity')) {
                return true;
            }

            $prev = $current->getPrevious();
            if (!$prev instanceof \Throwable) {
                break;
            }
            $current = $prev;
        }

        return false;
    }
}
