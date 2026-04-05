<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\Comment;
use App\Entity\Post;
use App\Entity\Share;
use App\Form\CommentType;
use App\Form\PostType;
use App\Repository\ActivityRepository;
use App\Repository\CommentRepository;
use App\Exception\ActivityMigrationNeededException;
use App\Repository\PostRepository;
use App\Repository\ShareRepository;
use App\Service\PostMediaUploadService;
use App\Service\PostTagIndexBuilder;
use App\Service\ReactionService;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\File\Exception\FileException;
use Symfony\Component\HttpFoundation\File\UploadedFile;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/connect/posts')]
class PostController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly ActivityRepository $activityRepository,
        private readonly ShareRepository $shareRepository,
        private readonly ReactionService $reactionService,
        private readonly EntityManagerInterface $entityManager,
        private readonly PostMediaUploadService $postMediaUploadService,
        private readonly PostTagIndexBuilder $postTagIndexBuilder,
    ) {
    }

    private function getConnectUserKey(Request $request): string
    {
        $fromSession = $request->getSession()->get('connect_user_key');
        if (\is_string($fromSession) && '' !== trim($fromSession)) {
            return trim($fromSession);
        }

        return trim((string) $request->request->get('user_key', ''));
    }

    #[Route(name: 'app_post_index', methods: ['GET'])]
    public function index(Request $request): Response
    {
        $q = trim((string) $request->query->get('q', ''));
        $hashtag = trim((string) $request->query->get('hashtag', ''));
        $dateFromStr = trim((string) $request->query->get('date_from', ''));
        $dateToStr = trim((string) $request->query->get('date_to', ''));
        $dateFrom = $this->parseDateQuery($dateFromStr);
        $dateTo = $this->parseDateQuery($dateToStr);

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = (int) $request->query->get('per_page', 10);
        if (!\in_array($perPage, [5, 10, 20, 50], true)) {
            $perPage = 10;
        }

        $qFilter = '' !== $q ? $q : null;
        $tagFilter = '' !== $hashtag ? $hashtag : null;

        $total = $this->postRepository->countFiltered($qFilter, $tagFilter, $dateFrom, $dateTo);
        $totalPages = $total > 0 ? (int) max(1, (int) ceil($total / $perPage)) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $posts = $this->postRepository->findFilteredPaginated($qFilter, $tagFilter, $dateFrom, $dateTo, $perPage, $offset);

        $ids = array_map(static fn (Post $p) => (int) $p->getId(), $posts);
        $postLikes = [] !== $ids ? $this->activityRepository->countPostReactionsForPosts($ids, Activity::TYPE_LIKE) : [];
        $postDislikes = [] !== $ids ? $this->activityRepository->countPostReactionsForPosts($ids, Activity::TYPE_DISLIKE) : [];
        $postCommentCounts = [] !== $ids ? $this->commentRepository->countCommentsByPostIds($ids) : [];
        $postShareCounts = [] !== $ids ? $this->shareRepository->countSharesByPostIds($ids) : [];

        $displayFrom = $total > 0 ? $offset + 1 : 0;
        $displayTo = $total > 0 ? min($offset + \count($posts), $total) : 0;

        $filtersActive = '' !== $q || '' !== $hashtag || '' !== $dateFromStr || '' !== $dateToStr;

        $filterDateFromValue = $dateFrom instanceof \DateTimeImmutable ? $dateFrom->format('Y-m-d') : '';
        $filterDateToValue = $dateTo instanceof \DateTimeImmutable ? $dateTo->format('Y-m-d') : '';

        $filterParams = array_filter([
            'q' => $q,
            'hashtag' => $hashtag,
            'date_from' => $filterDateFromValue,
            'date_to' => $filterDateToValue,
        ], static fn (string $v): bool => '' !== $v);

        return $this->render('post/index.html.twig', [
            'posts' => $posts,
            'filter_q' => $q,
            'filter_hashtag' => $hashtag,
            'filter_date_from' => $filterDateFromValue,
            'filter_date_to' => $filterDateToValue,
            'filter_params' => $filterParams,
            'filters_active' => $filtersActive,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'display_from' => $displayFrom,
            'display_to' => $displayTo,
            'post_likes' => $postLikes,
            'post_dislikes' => $postDislikes,
            'post_comment_counts' => $postCommentCounts,
            'post_share_counts' => $postShareCounts,
        ]);
    }

    private function parseDateQuery(string $raw): ?\DateTimeImmutable
    {
        $raw = trim($raw);
        if ('' === $raw) {
            return null;
        }

        $formats = [
            '!Y-m-d',
            '!d/m/Y',
            '!j/n/Y',
            '!d-m-Y',
            '!j-n-Y',
        ];

        foreach ($formats as $fmt) {
            $dt = \DateTimeImmutable::createFromFormat($fmt, $raw);
            if ($dt instanceof \DateTimeImmutable) {
                return $dt;
            }
        }

        try {
            return new \DateTimeImmutable($raw);
        } catch (\Exception) {
            return null;
        }
    }

    private function appendHashtagsToContent(Post $post, ?string $hashtags): void
    {
        if (!\is_string($hashtags)) {
            return;
        }
        $extra = trim($hashtags);
        if ('' === $extra) {
            return;
        }

        $base = trim((string) $post->getContent());
        $post->setContent('' === $base ? $extra : $base."\n\n".$extra);
    }

    #[Route('/{id}/comments/{commentId}/edit', name: 'app_comment_edit', requirements: ['id' => '\d+', 'commentId' => '\d+'], methods: ['GET', 'POST'])]
    public function editComment(Request $request, int $id, int $commentId): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Publication introuvable.');
        }

        $comment = $this->commentRepository->find($commentId);
        if (!$comment instanceof Comment || $comment->getPost()?->getId() !== $post->getId()) {
            throw $this->createNotFoundException('Commentaire introuvable.');
        }

        $form = $this->createForm(CommentType::class, $comment);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->entityManager->flush();
            $this->addFlash('success', 'Commentaire mis à jour.');

            return $this->redirectToRoute('app_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('comment/edit.html.twig', [
            'post' => $post,
            'comment' => $comment,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/comments/{commentId}/delete', name: 'app_comment_delete', requirements: ['id' => '\d+', 'commentId' => '\d+'], methods: ['POST'])]
    public function deleteComment(Request $request, int $id, int $commentId): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Publication introuvable.');
        }

        $comment = $this->commentRepository->find($commentId);
        if (!$comment instanceof Comment || $comment->getPost()?->getId() !== $post->getId()) {
            throw $this->createNotFoundException('Commentaire introuvable.');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_comment'.$comment->getId(), $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->entityManager->remove($comment);
        $this->entityManager->flush();
        $this->addFlash('success', 'Commentaire supprimé.');

        return $this->redirectToRoute('app_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/pseudo', name: 'app_post_set_pseudo', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function setPseudo(Request $request, int $id): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Publication introuvable.');
        }

        if (!$this->isCsrfTokenValid('pseudo'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $key = trim((string) $request->request->get('user_key', ''));
        if ('' === $key) {
            $this->addFlash('warning', 'Identifiant vide.');
        } else {
            $request->getSession()->set('connect_user_key', $key);
            $this->addFlash('success', 'Identifiant enregistré pour cette session.');
        }

        return $this->redirectToRoute('app_post_show', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/react', name: 'app_post_react', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function reactPost(Request $request, int $id): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Publication introuvable.');
        }

        if (!$this->isCsrfTokenValid('react_post'.$id, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $type = $this->parseReactionType((string) $request->request->get('type', ''));
        if (null === $type) {
            $this->addFlash('warning', 'Réaction invalide.');

            return $this->redirectToRoute('app_post_show', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $userKey = $this->getConnectUserKey($request);
        if ('' === $userKey) {
            $this->addFlash('warning', 'Indiquez votre identifiant (bloc ci-dessous ou via un commentaire).');
        } else {
            try {
                $this->reactionService->togglePostReaction($post, $userKey, $type);
                $this->addFlash('success', 'Réaction enregistrée.');
            } catch (ActivityMigrationNeededException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_post_show', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/comments/{commentId}/react', name: 'app_comment_react', requirements: ['id' => '\d+', 'commentId' => '\d+'], methods: ['POST'])]
    public function reactComment(Request $request, int $id, int $commentId): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Publication introuvable.');
        }

        $comment = $this->commentRepository->find($commentId);
        if (!$comment instanceof Comment || $comment->getPost()?->getId() !== $post->getId()) {
            throw $this->createNotFoundException('Commentaire introuvable.');
        }

        if (!$this->isCsrfTokenValid('react_comment'.$commentId, (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $type = $this->parseReactionType((string) $request->request->get('type', ''));
        if (null === $type) {
            $this->addFlash('warning', 'Réaction invalide.');

            return $this->redirectToRoute('app_post_show', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $userKey = $this->getConnectUserKey($request);
        if ('' === $userKey) {
            $this->addFlash('warning', 'Indiquez votre identifiant (bloc ci-dessous ou via un commentaire).');
        } else {
            try {
                $this->reactionService->toggleCommentReaction($comment, $userKey, $type);
                $this->addFlash('success', 'Réaction enregistrée.');
            } catch (ActivityMigrationNeededException $e) {
                $this->addFlash('danger', $e->getMessage());
            }
        }

        return $this->redirectToRoute('app_post_show', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/share/log', name: 'app_post_share_log', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function shareLog(Request $request, ValidatorInterface $validator, int $id): JsonResponse
    {
        $post = $this->postRepository->find($id);
        if (!$post instanceof Post) {
            return new JsonResponse(['ok' => false, 'error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isCsrfTokenValid('share_log'.$id, (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        $platform = strtolower(trim((string) $request->request->get('platform', '')));
        $allowed = ['whatsapp', 'facebook', 'twitter', 'linkedin', 'telegram', 'email', 'copie_lien', 'native'];
        if (!\in_array($platform, $allowed, true)) {
            return new JsonResponse(['ok' => false, 'error' => 'platform'], Response::HTTP_BAD_REQUEST);
        }

        $userKey = $this->getConnectUserKey($request);
        if ('' === $userKey) {
            return new JsonResponse(['ok' => true, 'logged' => false]);
        }

        $share = new Share();
        $share->setPost($post);
        $share->setUserKey($userKey);
        $share->setPlatform($platform);

        $violations = $validator->validate($share);
        if (\count($violations) > 0) {
            return new JsonResponse(['ok' => false, 'error' => 'validation'], Response::HTTP_BAD_REQUEST);
        }

        $this->entityManager->persist($share);
        $this->entityManager->flush();

        return new JsonResponse(['ok' => true, 'logged' => true]);
    }

    #[Route('/{id}', name: 'app_post_show', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function show(Request $request, int $id): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Publication introuvable.');
        }

        $sessionUserKey = trim((string) ($request->getSession()->get('connect_user_key') ?? ''));

        $comment = new Comment();
        $comment->setPost($post);
        if ('' !== $sessionUserKey) {
            $comment->setUserKey($sessionUserKey);
        }

        $commentForm = $this->createForm(CommentType::class, $comment);
        $commentForm->handleRequest($request);

        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            $this->entityManager->persist($comment);
            $this->entityManager->flush();
            $request->getSession()->set('connect_user_key', $comment->getUserKey());
            $this->addFlash('success', 'Commentaire publié.');

            return $this->redirectToRoute('app_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        $postLikesCount = $this->activityRepository->countPostReactions($post, Activity::TYPE_LIKE);
        $postDislikesCount = $this->activityRepository->countPostReactions($post, Activity::TYPE_DISLIKE);

        $userPostReaction = null;
        if ('' !== $sessionUserKey) {
            $userPostReaction = $this->activityRepository->findUserReactionOnPost($post, $sessionUserKey)?->getActivityType();
        }

        $commentReactions = [];
        foreach ($post->getComments() as $c) {
            if ('' !== $sessionUserKey) {
                $commentReactions[$c->getId()] = $this->activityRepository->findUserReactionOnComment($c, $sessionUserKey)?->getActivityType();
            } else {
                $commentReactions[$c->getId()] = null;
            }
        }

        $decoded = html_entity_decode(strip_tags((string) $post->getContent()), ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $sharePlain = trim((string) preg_replace('/\s+/u', ' ', $decoded));
        if (mb_strlen($sharePlain, 'UTF-8') > 160) {
            $sharePlain = mb_substr($sharePlain, 0, 160, 'UTF-8').'…';
        }
        $postPublicUrl = $this->generateUrl('app_post_show', ['id' => $post->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $waText = $sharePlain.' '.$postPublicUrl;
        $mailSubject = 'Tabaani Connect — Publication #'.$post->getId();
        $mailBody = $sharePlain."\n\n".$postPublicUrl;

        return $this->render('post/show.html.twig', [
            'post' => $post,
            'commentForm' => $commentForm,
            'sessionUserKey' => $sessionUserKey,
            'postLikesCount' => $postLikesCount,
            'postDislikesCount' => $postDislikesCount,
            'userPostReaction' => $userPostReaction,
            'commentReactions' => $commentReactions,
            'shareCount' => $this->shareRepository->countByPost($post),
            'recentShares' => $this->shareRepository->findRecentByPost($post, 20),
            'postPublicUrl' => $postPublicUrl,
            'sharePlain' => $sharePlain,
            'waText' => $waText,
            'mailSubject' => $mailSubject,
            'mailBody' => $mailBody,
        ]);
    }

    private function parseReactionType(string $raw): ?string
    {
        return match (strtolower(trim($raw))) {
            'like' => Activity::TYPE_LIKE,
            'dislike' => Activity::TYPE_DISLIKE,
            default => null,
        };
    }

    #[Route('/new', name: 'app_post_new', methods: ['GET', 'POST'])]
    public function new(Request $request): Response
    {
        $post = new Post();
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->appendHashtagsToContent($post, $form->has('hashtags') ? $form->get('hashtags')->getData() : null);
            $post->setHashtagsIndex($this->postTagIndexBuilder->build((string) $post->getContent()));
            try {
                $this->postMediaUploadService->addImagesToPost(
                    $post,
                    $this->normalizeUploadedFiles($form->get('imageFiles')->getData())
                );
                $this->postMediaUploadService->applyVideoToPost($post, $form->get('videoFile')->getData());
            } catch (FileException $e) {
                $this->addFlash('danger', 'Impossible d’enregistrer le fichier média. Réessayez.');

                return $this->render('post/new.html.twig', [
                    'post' => $post,
                    'form' => $form,
                ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
            }
            $this->entityManager->persist($post);
            $this->entityManager->flush();
            $this->addFlash('success', 'Publication créée.');

            return $this->redirectToRoute('app_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('post/new.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/edit', name: 'app_post_edit', requirements: ['id' => '\d+'], methods: ['GET', 'POST'])]
    public function edit(Request $request, int $id): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Publication introuvable.');
        }

        $form = $this->createForm(PostType::class, $post, ['allow_media_remove' => true]);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $this->appendHashtagsToContent($post, $form->has('hashtags') ? $form->get('hashtags')->getData() : null);
            $post->setHashtagsIndex($this->postTagIndexBuilder->build((string) $post->getContent()));
            $videoUpload = $form->get('videoFile')->getData();
            $removeImageIds = $form->has('removeImageIds') ? $form->get('removeImageIds')->getData() : [];
            if (is_array($removeImageIds)) {
                $this->postMediaUploadService->removePostImagesByIds($post, $removeImageIds);
            }
            if (!$videoUpload instanceof UploadedFile && $form->get('removeVideo')->getData()) {
                $this->postMediaUploadService->clearVideo($post);
            }
            try {
                $this->postMediaUploadService->addImagesToPost(
                    $post,
                    $this->normalizeUploadedFiles($form->get('imageFiles')->getData())
                );
                $this->postMediaUploadService->applyVideoToPost($post, $videoUpload);
            } catch (FileException $e) {
                $this->addFlash('danger', 'Impossible d’enregistrer le fichier média. Réessayez.');

                return $this->render('post/edit.html.twig', [
                    'post' => $post,
                    'form' => $form,
                ], new Response('', Response::HTTP_UNPROCESSABLE_ENTITY));
            }
            $this->entityManager->flush();
            $this->addFlash('success', 'Publication mise à jour.');

            return $this->redirectToRoute('app_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
        }

        return $this->render('post/edit.html.twig', [
            'post' => $post,
            'form' => $form,
        ]);
    }

    #[Route('/{id}/delete', name: 'app_post_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(Request $request, int $id): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Publication introuvable.');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete'.$post->getId(), $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->postMediaUploadService->purgePostMediaFromDisk($post);
        $this->entityManager->remove($post);
        $this->entityManager->flush();
        $this->addFlash('success', 'Publication supprimée.');

        return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
    }

    private function normalizeUploadedFiles(mixed $data): array
    {
        if ($data instanceof UploadedFile) {
            return [$data];
        }
        if (!is_array($data)) {
            return [];
        }

        $out = [];
        foreach ($data as $file) {
            if ($file instanceof UploadedFile) {
                $out[] = $file;
            }
        }

        return $out;
    }
}
