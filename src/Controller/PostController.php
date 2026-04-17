<?php

namespace App\Controller;

use App\Entity\Activity;
use App\Entity\ChatBan;
use App\Entity\Comment;
use App\Entity\Post;
use App\Form\CommentType;
use App\Form\PostType;
use App\Repository\ActivityRepository;
use App\Repository\ChatBanRepository;
use App\Repository\CommentRepository;
use App\Exception\ActivityMigrationNeededException;
use App\Repository\NotificationRepository;
use App\Repository\PostRepository;
use App\Repository\ShareRepository;
use App\Service\AutoSummaryService;
use App\Service\NotificationService;
use App\Service\PdfExportService;
use App\Service\PostMediaUploadService;
use App\Service\PostTagIndexBuilder;
use App\Service\ReactionService;
use App\Service\SocialShareService;
use App\Validation\ValidationLimits;
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
        private readonly NotificationRepository $notificationRepository,
        private readonly ChatBanRepository $chatBanRepository,
        private readonly ReactionService $reactionService,
        private readonly NotificationService $notificationService,
        private readonly EntityManagerInterface $entityManager,
        private readonly PostMediaUploadService $postMediaUploadService,
        private readonly PostTagIndexBuilder $postTagIndexBuilder,
        private readonly AutoSummaryService $autoSummaryService,
        private readonly SocialShareService $socialShareService,
        private readonly ValidatorInterface $validator,
        private readonly PdfExportService $pdfExportService,
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

    private function getSessionUserKey(Request $request): ?string
    {
        $fromSession = $request->getSession()->get('connect_user_key');
        if (!\is_string($fromSession)) {
            return null;
        }

        $key = trim($fromSession);

        return '' !== $key ? $key : null;
    }

    private function isScopeBlocked(string $userKey, string $scope): bool
    {
        return $this->chatBanRepository->isUserCurrentlyBannedForScope($userKey, $scope);
    }

    private function isSessionPostAuthor(Request $request, Post $post): bool
    {
        $sessionKey = $this->getSessionUserKey($request);
        if (null === $sessionKey) {
            return false;
        }

        $authorKey = $post->getAuthorKey();
        if (null === $authorKey) {
            return false;
        }

        $authorKey = trim((string) $authorKey);
        if ('' === $authorKey) {
            return false;
        }

        return mb_strtolower($sessionKey, 'UTF-8') === mb_strtolower($authorKey, 'UTF-8');
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

        $postSummaries = [];
        foreach ($posts as $p) {
            if (!$p instanceof Post) {
                continue;
            }
            $postSummaries[(int) $p->getId()] = $this->autoSummaryService->summarize((string) ($p->getContent() ?? ''), 240);
        }

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
            'post_summaries' => $postSummaries,
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

        $sessionKey = trim((string) ($request->getSession()->get('connect_user_key') ?? ''));
        if ('' === $sessionKey || $sessionKey !== trim((string) $comment->getUserKey())) {
            $this->addFlash('warning', 'Identifiez-vous sur l’accueil avec le même pseudo que ce commentaire pour le modifier.');

            return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
        }

        $form = $this->createForm(CommentType::class, $comment);
        $form->remove('userKey');
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

        $sessionKey = trim((string) ($request->getSession()->get('connect_user_key') ?? ''));
        if ('' === $sessionKey || $sessionKey !== trim((string) $comment->getUserKey())) {
            $this->addFlash('warning', 'Seul l’auteur du commentaire peut le supprimer (même pseudo que sur l’accueil).');

            return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
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
        $violations = $this->validator->validate($key, ValidationLimits::userKeyValueConstraints());
        if (\count($violations) > 0) {
            $this->addFlash('warning', (string) $violations[0]->getMessage());
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
            $this->addFlash('warning', 'Enregistrez votre pseudo sur l’accueil pour réagir.');

            return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isScopeBlocked($userKey, ChatBan::SCOPE_REACTIONS)) {
            $this->addFlash('warning', 'Votre identifiant est bloqué pour les réactions.');

            return $this->redirectToRoute('app_post_show', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        try {
            $this->reactionService->togglePostReaction($post, $userKey, $type);
            $this->addFlash('success', 'Réaction enregistrée.');
        } catch (ActivityMigrationNeededException $e) {
            $this->addFlash('danger', $e->getMessage());
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
            $this->addFlash('warning', 'Enregistrez votre pseudo sur l’accueil pour réagir.');

            return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
        }

        if ($this->isScopeBlocked($userKey, ChatBan::SCOPE_REACTIONS)) {
            $this->addFlash('warning', 'Votre identifiant est bloqué pour les réactions.');

            return $this->redirectToRoute('app_post_show', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        try {
            $this->reactionService->toggleCommentReaction($comment, $userKey, $type);
            $this->addFlash('success', 'Réaction enregistrée.');
        } catch (ActivityMigrationNeededException $e) {
            $this->addFlash('danger', $e->getMessage());
        }

        return $this->redirectToRoute('app_post_show', ['id' => $id], Response::HTTP_SEE_OTHER);
    }

    #[Route('/{id}/share/log', name: 'app_post_share_log', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function shareLog(Request $request, int $id): JsonResponse
    {
        $post = $this->postRepository->find($id);
        if (!$post instanceof Post) {
            return new JsonResponse(['ok' => false, 'error' => 'not_found'], Response::HTTP_NOT_FOUND);
        }

        if (!$this->isCsrfTokenValid('share_log'.$id, (string) $request->request->get('_token'))) {
            return new JsonResponse(['ok' => false, 'error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        $platform = strtolower(trim((string) $request->request->get('platform', '')));
        $userKey = $this->getConnectUserKey($request);
        $result = $this->socialShareService->recordShareLog($post, $userKey, $platform);

        if (!($result['ok'] ?? false)) {
            return match ((string) ($result['error'] ?? '')) {
                'invalid_platform' => new JsonResponse(['ok' => false, 'error' => 'platform'], Response::HTTP_BAD_REQUEST),
                'banned_for_shares' => new JsonResponse(['ok' => false, 'error' => 'banned_for_shares'], Response::HTTP_FORBIDDEN),
                'validation' => new JsonResponse(['ok' => false, 'error' => 'validation'], Response::HTTP_BAD_REQUEST),
                default => new JsonResponse(['ok' => false, 'error' => 'validation'], Response::HTTP_BAD_REQUEST),
            };
        }

        return new JsonResponse(['ok' => true, 'logged' => (bool) ($result['logged'] ?? false)]);
    }

    #[Route('/{id}/export/pdf', name: 'app_post_export_pdf', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function exportPostPdf(Request $request, int $id): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Publication introuvable.');
        }

        if (!$this->isSessionPostAuthor($request, $post)) {
            if (null === $post->getAuthorKey() || '' === trim((string) $post->getAuthorKey())) {
                $this->addFlash('warning', 'Cette publication ne peut pas être exportée en PDF auteur : aucun auteur enregistré (publication ancienne).');
            } else {
                $this->addFlash('warning', 'Export PDF réservé à l’auteur : connectez-vous avec le même identifiant que celui enregistré pour la publication.');
            }

            return $this->redirectToRoute('app_post_show', ['id' => $id], Response::HTTP_SEE_OTHER);
        }

        $post->getComments()->toArray();

        $publicUrl = $this->generateUrl('app_post_show', ['id' => $id], UrlGeneratorInterface::ABSOLUTE_URL);
        $issuer = $this->getSessionUserKey($request) ?? 'auteur';

        return $this->pdfExportService->buildPdfResponse('pdf/post_detail.html.twig', [
            'post' => $post,
            'pdf_doc_title' => 'Export auteur — publication #'.$post->getId(),
            'pdf_source_url' => $publicUrl,
            'pdf_admin_user' => $issuer.' (auteur)',
            'pdf_logo_data_uri' => $this->pdfExportService->getOptionalLogoDataUri(),
        ], 'tabaani-connect-publication-auteur-'.$id.'-'.(new \DateTimeImmutable())->format('Ymd'));
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
        $commentForm->remove('userKey');
        $commentForm->handleRequest($request);

        if ($commentForm->isSubmitted() && '' === trim((string) ($request->getSession()->get('connect_user_key') ?? ''))) {
            $this->addFlash('warning', 'Enregistrez votre pseudo sur l’accueil pour commenter.');

            return $this->redirectToRoute('app_home', [], Response::HTTP_SEE_OTHER);
        }

        if ($commentForm->isSubmitted() && $commentForm->isValid()) {
            $commentUserKey = trim((string) ($request->getSession()->get('connect_user_key') ?? ''));
            $comment->setUserKey($commentUserKey);
            if ($this->isScopeBlocked($commentUserKey, ChatBan::SCOPE_COMMENTS)) {
                $this->addFlash('warning', 'Votre identifiant est bloqué pour les commentaires.');

                return $this->redirectToRoute('app_post_show', ['id' => $post->getId()], Response::HTTP_SEE_OTHER);
            }

            $this->entityManager->persist($comment);
            $this->entityManager->flush();
            $this->notificationService->notifyCommentOnPost($post, $comment);
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

        $postSummary = $this->autoSummaryService->summarize((string) ($post->getContent() ?? ''), 260);

        $postPublicUrl = $this->generateUrl('app_post_show', ['id' => $post->getId()], UrlGeneratorInterface::ABSOLUTE_URL);
        $shareData = $this->socialShareService->buildSharePageData($post, $postPublicUrl);

        return $this->render('post/show.html.twig', [
            'post' => $post,
            'commentForm' => $commentForm,
            'sessionUserKey' => $sessionUserKey,
            'viewer_is_author' => $this->isSessionPostAuthor($request, $post),
            'postLikesCount' => $postLikesCount,
            'postDislikesCount' => $postDislikesCount,
            'userPostReaction' => $userPostReaction,
            'commentReactions' => $commentReactions,
            'post_summary' => $postSummary,
            'postPublicUrl' => $shareData['post_public_url'],
            'sharePlain' => $shareData['share_plain'],
            'waText' => $shareData['wa_text'],
            'mailSubject' => $shareData['mail_subject'],
            'mailBody' => $shareData['mail_body'],
            'shareCount' => $this->shareRepository->countByPost($post),
            'recentShares' => $this->shareRepository->findRecentByPost($post, 20),
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
        $post->setAuthorKey($this->getSessionUserKey($request));
        $form = $this->createForm(PostType::class, $post);
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $authorKey = trim((string) $post->getAuthorKey());
            if ($this->isScopeBlocked($authorKey, ChatBan::SCOPE_POSTS)) {
                $this->addFlash('warning', 'Votre identifiant est bloqué pour les publications.');

                return $this->redirectToRoute('app_post_new', [], Response::HTTP_SEE_OTHER);
            }

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
            'viewer_is_author' => $this->isSessionPostAuthor($request, $post),
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
        $this->notificationRepository->deleteByPost($post);
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
