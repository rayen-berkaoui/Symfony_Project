<?php

declare(strict_types=1);

namespace App\Service;

use App\Chat\ChatAdmin;
use App\Entity\Activity;
use App\Entity\Comment;
use App\Entity\Notification;
use App\Entity\Post;
use Doctrine\ORM\EntityManagerInterface;

final class NotificationService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public function notifyCommentOnPost(Post $post, Comment $comment): void
    {
        $recipient = trim((string) $post->getAuthorKey());
        $actor = trim((string) $comment->getUserKey());

        if ('' === $recipient || '' === $actor || $recipient === $actor) {
            return;
        }

        $notification = (new Notification())
            ->setRecipientKey($recipient)
            ->setActorKey($actor)
            ->setType(Notification::TYPE_COMMENT_ON_POST)
            ->setPost($post)
            ->setComment($comment)
            ->setMessage(sprintf('%s a commenté votre publication #%d.', $actor, $post->getId()));

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function notifyReactionOnPost(Post $post, string $actorKey, string $reactionType): void
    {
        $recipient = trim((string) $post->getAuthorKey());
        $actorKey = trim($actorKey);

        if ('' === $recipient || '' === $actorKey || $recipient === $actorKey) {
            return;
        }

        $label = Activity::TYPE_LIKE === strtoupper(trim($reactionType)) ? 'aimé' : 'réagi à';

        $notification = (new Notification())
            ->setRecipientKey($recipient)
            ->setActorKey($actorKey)
            ->setType(Notification::TYPE_REACTION_ON_POST)
            ->setPost($post)
            ->setMessage(sprintf('%s a %s votre publication #%d.', $actorKey, $label, $post->getId()));

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function notifyReactionOnComment(Comment $comment, string $actorKey, string $reactionType): void
    {
        $recipient = trim((string) $comment->getUserKey());
        $actorKey = trim($actorKey);

        if ('' === $recipient || '' === $actorKey || $recipient === $actorKey) {
            return;
        }

        $label = Activity::TYPE_LIKE === strtoupper(trim($reactionType)) ? 'aimé' : 'réagi à';

        $notification = (new Notification())
            ->setRecipientKey($recipient)
            ->setActorKey($actorKey)
            ->setType(Notification::TYPE_REACTION_ON_COMMENT)
            ->setPost($comment->getPost())
            ->setComment($comment)
            ->setMessage(sprintf('%s a %s votre commentaire #%d.', $actorKey, $label, $comment->getId()));

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }

    public function notifyBadWordDetected(?string $actorKey, string $word, string $source): void
    {
        $actorKey = trim((string) $actorKey);
        if ('' === $actorKey) {
            $actorKey = 'unknown_user';
        }

        $word = trim($word);
        $source = trim($source);
        if ('' === $source) {
            $source = 'content';
        }

        $notification = (new Notification())
            ->setRecipientKey(ChatAdmin::BROADCAST_USER_KEY)
            ->setActorKey($actorKey)
            ->setType(Notification::TYPE_BAD_WORD_DETECTED)
            ->setMessage(sprintf(
                'Alerte bad words: "%s" détecté dans %s par %s. Confirmez le blocage si nécessaire.',
                $word,
                $source,
                $actorKey
            ));

        $this->entityManager->persist($notification);
        $this->entityManager->flush();
    }
}
