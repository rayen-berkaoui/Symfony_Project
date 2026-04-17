<?php

declare(strict_types=1);

namespace App\Controller;

use App\Chat\ChatAdmin;
use App\Entity\ChatBan;
use App\Entity\ChatMessage;
use App\Entity\Comment;
use App\Entity\Notification;
use App\Entity\Post;
use App\Repository\ChatBanRepository;
use App\Repository\ChatMessageRepository;
use App\Repository\CommentRepository;
use App\Repository\NotificationRepository;
use App\Repository\PostRepository;
use App\Service\AdminAnalyticsService;
use App\Service\AdminAssistantService;
use App\Service\AdminBriefingService;
use App\Service\ChatPresenceService;
use App\Service\PdfExportService;
use App\Service\PostMediaUploadService;
use App\Validation\ValidationLimits;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Security\Http\Attribute\IsGranted;
use Symfony\Component\Validator\Constraints as Assert;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/admin')]
#[IsGranted('ROLE_ADMIN')]
class AdminController extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly CommentRepository $commentRepository,
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly ChatBanRepository $chatBanRepository,
        private readonly NotificationRepository $notificationRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly PostMediaUploadService $postMediaUploadService,
        private readonly ValidatorInterface $validator,
        private readonly AdminAnalyticsService $adminAnalytics,
        private readonly AdminAssistantService $adminAssistant,
        private readonly AdminBriefingService $adminBriefing,
        private readonly ChatPresenceService $chatPresenceService,
        private readonly PdfExportService $pdfExportService,
    ) {
    }

    #[Route('/posts', name: 'app_admin_posts_legacy', methods: ['GET'])]
    public function redirectLegacyPosts(): Response
    {
        return $this->redirectToRoute('app_admin_posts', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('/chat-bans', name: 'app_admin_chat_bans_legacy', methods: ['GET'])]
    public function redirectLegacyChatBans(): Response
    {
        return $this->redirectToRoute('app_admin_blocked', [], Response::HTTP_MOVED_PERMANENTLY);
    }

    #[Route('', name: 'app_admin_dashboard', methods: ['GET'])]
    public function dashboard(): Response
    {
        $daily = $this->adminAnalytics->getDailyActivityLastDays(30);
        $reactions = $this->adminAnalytics->getPostReactionTotals();
        $shareRows = $this->adminAnalytics->getTotalShareRows();
        $topTags = $this->adminAnalytics->getTopHashtags(10);
        $topCommenters = $this->adminAnalytics->getTopCommenters(10);
        $hourly = $this->adminAnalytics->getHourlyPostsToday();
        $mostCommented = $this->adminAnalytics->getMostCommentedPosts(10);
        $warnings = $this->adminAnalytics->getWarningBanBreakdown();

        return $this->render('admin/dashboard.html.twig', [
            'post_count' => $this->postRepository->count([]),
            'comment_count' => $this->commentRepository->count([]),
            'hashtag_distinct_count' => $this->adminAnalytics->countDistinctHashtags(),
            'share_count' => $shareRows,
            'signalement_pending' => 0,
            'signalement_resolved' => 0,
            'signalement_rejected' => 0,
            'ban_active_count' => $this->chatBanRepository->countCurrentlyActiveBans(),
            'daily_activity' => $daily,
            'pie_like' => $reactions['like'],
            'pie_dislike' => $reactions['dislike'],
            'pie_share' => $shareRows,
            'top_hashtags' => $topTags,
            'top_commenters' => $topCommenters,
            'hourly_posts' => $hourly,
            'most_commented_posts' => $mostCommented,
            'warnings' => $warnings,
        ]);
    }

    #[Route('/export/pdf/dashboard', name: 'app_admin_export_pdf_dashboard', methods: ['GET'])]
    public function exportPdfDashboard(Request $request): Response
    {
        $daily = $this->adminAnalytics->getDailyActivityLastDays(30);
        $reactions = $this->adminAnalytics->getPostReactionTotals();
        $shareRows = $this->adminAnalytics->getTotalShareRows();

        return $this->pdfExportService->buildPdfResponse('pdf/dashboard_report.html.twig', [
            'post_count' => $this->postRepository->count([]),
            'comment_count' => $this->commentRepository->count([]),
            'hashtag_distinct_count' => $this->adminAnalytics->countDistinctHashtags(),
            'share_count' => $shareRows,
            'ban_active_count' => $this->chatBanRepository->countCurrentlyActiveBans(),
            'daily_activity' => $daily,
            'pie_like' => $reactions['like'],
            'pie_dislike' => $reactions['dislike'],
            'top_hashtags' => $this->adminAnalytics->getTopHashtags(50),
            'pdf_source_url' => $request->getSchemeAndHttpHost().$this->generateUrl('app_admin_dashboard'),
            'pdf_admin_user' => $this->getUser()?->getUserIdentifier(),
            'pdf_logo_data_uri' => $this->pdfExportService->getOptionalLogoDataUri(),
        ], 'tabaani-connect-dashboard-'.(new \DateTimeImmutable())->format('Ymd-His'));
    }

    #[Route('/export/pdf/publications', name: 'app_admin_export_pdf_posts', methods: ['GET'])]
    public function exportPdfPosts(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 20;
        $total = $this->postRepository->count([]);
        $totalPages = $total > 0 ? (int) max(1, (int) ceil($total / $perPage)) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;

        $posts = $this->postRepository->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();

        return $this->pdfExportService->buildPdfResponse('pdf/posts_list.html.twig', [
            'posts' => $posts,
            'list_page' => $page,
            'list_total_pages' => $totalPages,
            'list_per_page' => $perPage,
            'list_total' => $total,
            'pdf_source_url' => $request->getSchemeAndHttpHost().$this->generateUrl('app_admin_posts', ['page' => $page]),
            'pdf_admin_user' => $this->getUser()?->getUserIdentifier(),
            'pdf_logo_data_uri' => $this->pdfExportService->getOptionalLogoDataUri(),
        ], 'tabaani-connect-publications-p'.$page.'-'.(new \DateTimeImmutable())->format('Ymd'));
    }

    #[Route('/export/pdf/publication/{id}', name: 'app_admin_export_pdf_post', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function exportPdfPost(Request $request, int $id): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Publication introuvable.');
        }

        $post->getComments()->toArray();

        $publicUrl = $request->getSchemeAndHttpHost().$this->generateUrl('app_post_show', ['id' => $id]);

        return $this->pdfExportService->buildPdfResponse('pdf/post_detail.html.twig', [
            'post' => $post,
            'pdf_doc_title' => 'Fiche publication #'.$post->getId().' (administration)',
            'pdf_source_url' => $publicUrl,
            'pdf_admin_user' => $this->getUser()?->getUserIdentifier(),
            'pdf_logo_data_uri' => $this->pdfExportService->getOptionalLogoDataUri(),
        ], 'tabaani-connect-publication-'.$id.'-'.(new \DateTimeImmutable())->format('Ymd'));
    }

    #[Route('/export/pdf/commentaires', name: 'app_admin_export_pdf_comments', methods: ['GET'])]
    public function exportPdfComments(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = 25;
        $total = $this->commentRepository->count([]);
        $totalPages = $total > 0 ? (int) max(1, (int) ceil($total / $perPage)) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $comments = $this->commentRepository->findAllPaginated($perPage, $offset);

        return $this->pdfExportService->buildPdfResponse('pdf/comments_list.html.twig', [
            'comments' => $comments,
            'list_page' => $page,
            'list_total_pages' => $totalPages,
            'list_per_page' => $perPage,
            'list_total' => $total,
            'pdf_source_url' => $request->getSchemeAndHttpHost().$this->generateUrl('app_admin_comments', ['page' => $page]),
            'pdf_admin_user' => $this->getUser()?->getUserIdentifier(),
            'pdf_logo_data_uri' => $this->pdfExportService->getOptionalLogoDataUri(),
        ], 'tabaani-connect-commentaires-p'.$page.'-'.(new \DateTimeImmutable())->format('Ymd'));
    }

    #[Route('/publications', name: 'app_admin_posts', methods: ['GET'])]
    public function posts(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = (int) $request->query->get('per_page', 10);
        if (!\in_array($perPage, [10, 20, 50], true)) {
            $perPage = 10;
        }
        $total = $this->postRepository->count([]);
        $totalPages = $total > 0 ? (int) max(1, (int) ceil($total / $perPage)) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $displayFrom = $total > 0 ? $offset + 1 : 0;

        $posts = $this->postRepository->createQueryBuilder('p')
            ->orderBy('p.createdAt', 'DESC')
            ->setFirstResult($offset)
            ->setMaxResults($perPage)
            ->getQuery()
            ->getResult();
        $displayTo = $total > 0 ? min($offset + \count($posts), $total) : 0;

        return $this->render('admin/posts.html.twig', [
            'posts' => $posts,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'display_from' => $displayFrom,
            'display_to' => $displayTo,
        ]);
    }

    #[Route('/publications/{id}/delete', name: 'app_admin_post_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deletePost(Request $request, int $id): Response
    {
        $post = $this->postRepository->find($id);
        if (!$post instanceof Post) {
            throw $this->createNotFoundException('Publication introuvable.');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_delete_post'.$post->getId(), $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->postMediaUploadService->purgePostMediaFromDisk($post);
        $this->entityManager->remove($post);
        $this->entityManager->flush();
        $this->addFlash('success', 'Publication supprimée.');

        return $this->redirectToRoute('app_admin_posts', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/commentaires', name: 'app_admin_comments', methods: ['GET'])]
    public function comments(Request $request): Response
    {
        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = (int) $request->query->get('per_page', 10);
        if (!\in_array($perPage, [10, 20, 50], true)) {
            $perPage = 10;
        }
        $total = $this->commentRepository->count([]);
        $totalPages = $total > 0 ? (int) max(1, (int) ceil($total / $perPage)) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $displayFrom = $total > 0 ? $offset + 1 : 0;
        $comments = $this->commentRepository->findAllPaginated($perPage, $offset);
        $displayTo = $total > 0 ? min($offset + \count($comments), $total) : 0;

        return $this->render('admin/comments.html.twig', [
            'comments' => $comments,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'display_from' => $displayFrom,
            'display_to' => $displayTo,
        ]);
    }

    #[Route('/commentaires/{id}/delete', name: 'app_admin_comment_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function deleteComment(Request $request, int $id): Response
    {
        $comment = $this->commentRepository->find($id);
        if (!$comment instanceof Comment) {
            throw $this->createNotFoundException('Commentaire introuvable.');
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_delete_comment'.$comment->getId(), $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $this->entityManager->remove($comment);
        $this->entityManager->flush();
        $this->addFlash('success', 'Commentaire supprimé.');

        return $this->redirectToRoute('app_admin_comments', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/hashtags', name: 'app_admin_hashtags', methods: ['GET', 'POST'])]
    public function hashtags(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('admin_hashtag_preview', $token)) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $raw = trim((string) $request->request->get('hashtag', ''));
            $violations = $this->validator->validate($raw, [
                new Assert\NotBlank(message: 'Le hashtag est obligatoire.'),
                new Assert\Length(max: 200, maxMessage: 'Le hashtag ne peut pas dépasser {{ limit }} caractères.'),
                new Assert\Regex(
                    pattern: '/^#?[\p{L}\p{N}_]+$/u',
                    message: 'Format hashtag invalide (lettres, chiffres et _ uniquement).'
                ),
            ]);
            if (\count($violations) > 0) {
                $this->addFlash('danger', (string) $violations[0]->getMessage());

                return $this->redirectToRoute('app_admin_hashtags', [], Response::HTTP_SEE_OTHER);
            }

            $n = $this->adminAnalytics->countPostsContainingHashtag($raw);
            $this->addFlash('success', sprintf('Occurrences estimées dans l’index des publications : %d (mot-clé « %s »).', $n, $raw));

            return $this->redirectToRoute('app_admin_hashtags', [], Response::HTTP_SEE_OTHER);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = (int) $request->query->get('per_page', 10);
        if (!\in_array($perPage, [10, 20, 50], true)) {
            $perPage = 10;
        }
        $total = $this->adminAnalytics->countDistinctHashtags();
        $totalPages = $total > 0 ? (int) max(1, (int) ceil($total / $perPage)) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $rows = array_slice($this->adminAnalytics->getTopHashtags($total), $offset, $perPage);
        $displayFrom = $total > 0 ? $offset + 1 : 0;
        $displayTo = $total > 0 ? min($offset + \count($rows), $total) : 0;

        return $this->render('admin/hashtags.html.twig', [
            'rows' => $rows,
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'display_from' => $displayFrom,
            'display_to' => $displayTo,
        ]);
    }

    #[Route('/chat', name: 'app_admin_chat', methods: ['GET', 'POST'])]
    public function chatControl(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('_action', 'broadcast');

            if ('delete_message' === $action) {
                return $this->handleChatMessageDelete($request);
            }

            if ('quick_ban' === $action) {
                return $this->handleQuickBanFromChat($request);
            }

            if ('kick_user' === $action) {
                return $this->handleKickUserFromChat($request);
            }

            if ('purge_all_messages' === $action) {
                return $this->handlePurgeAllChatMessages($request);
            }

            $token = (string) $request->request->get('_token');
            if (!$this->isCsrfTokenValid('admin_chat_broadcast', $token)) {
                throw $this->createAccessDeniedException('Jeton CSRF invalide.');
            }
            $raw = trim((string) $request->request->get('message', ''));
            if ('' === $raw) {
                $this->addFlash('warning', 'Saisissez un message à diffuser.');

                return $this->redirectToRoute('app_admin_chat', [], Response::HTTP_SEE_OTHER);
            }

            $chatMessage = new ChatMessage();
            $chatMessage->setUserKey(ChatAdmin::BROADCAST_USER_KEY);
            $chatMessage->setContent('【Annonce】 '.$raw);

            $violations = $this->validator->validate($chatMessage);
            if (\count($violations) > 0) {
                $this->addFlash('danger', (string) $violations[0]->getMessage());

                return $this->redirectToRoute('app_admin_chat', [], Response::HTTP_SEE_OTHER);
            }

            $this->entityManager->persist($chatMessage);
            $this->entityManager->flush();
            $this->addFlash('success', 'Annonce publiée dans le salon de chat.');

            return $this->redirectToRoute('app_admin_chat', [], Response::HTTP_SEE_OTHER);
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = (int) $request->query->get('per_page', 15);
        if (!\in_array($perPage, [10, 15, 25, 50], true)) {
            $perPage = 15;
        }
        $filterUser = trim((string) $request->query->get('user', ''));
        $search = trim((string) $request->query->get('q', ''));

        $total = $this->chatMessageRepository->countForAdmin('' !== $filterUser ? $filterUser : null, '' !== $search ? $search : null);
        $totalPages = $total > 0 ? (int) max(1, (int) ceil($total / $perPage)) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $displayFrom = $total > 0 ? $offset + 1 : 0;
        $messages = $this->chatMessageRepository->findPaginatedForAdmin($perPage, $offset, '' !== $filterUser ? $filterUser : null, '' !== $search ? $search : null);
        $displayTo = $total > 0 ? min($offset + \count($messages), $total) : 0;

        $ref = new \DateTimeImmutable();
        $onlineCount = $this->chatMessageRepository->countDistinctUsersSince($ref->modify('-30 minutes'));
        $msg24h = $this->chatMessageRepository->countSince($ref->modify('-24 hours'));
        $msg7d = $this->chatMessageRepository->countSince($ref->modify('-7 days'));
        $totalMsgs = $this->chatMessageRepository->countAll();
        $topSpeakers = $this->chatMessageRepository->getTopSpeakersSince((new \DateTimeImmutable())->modify('-7 days'), 8);

        $bans = $this->chatBanRepository->findAllOrderedByNewest();

        $queryParams = [];
        if ('' !== $filterUser) {
            $queryParams['user'] = $filterUser;
        }
        if ('' !== $search) {
            $queryParams['q'] = $search;
        }
        if (15 !== $perPage) {
            $queryParams['per_page'] = $perPage;
        }

        return $this->render('admin/chat_control.html.twig', [
            'chat_messages' => $messages,
            'chat_total' => $total,
            'total_messages_db' => $totalMsgs,
            'messages_24h' => $msg24h,
            'messages_7d' => $msg7d,
            'online_count' => $onlineCount,
            'top_speakers' => $topSpeakers,
            'bans' => $bans,
            'page' => $page,
            'per_page' => $perPage,
            'total_pages' => $totalPages,
            'display_from' => $displayFrom,
            'display_to' => $displayTo,
            'filter_user' => $filterUser,
            'search_q' => $search,
            'list_query_params' => $queryParams,
        ]);
    }

    private function handleChatMessageDelete(Request $request): Response
    {
        $id = (int) $request->request->get('message_id', 0);
        $token = (string) $request->request->get('_token');
        if ($id < 1 || !$this->isCsrfTokenValid('admin_chat_message_delete'.$id, $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $msg = $this->chatMessageRepository->find($id);
        if (!$msg instanceof ChatMessage) {
            throw $this->createNotFoundException('Message introuvable.');
        }

        $this->entityManager->remove($msg);
        $this->entityManager->flush();
        $this->addFlash('success', 'Message supprimé.');

        return $this->redirectToRoute('app_admin_chat', $request->query->all(), Response::HTTP_SEE_OTHER);
    }

    private function handleQuickBanFromChat(Request $request): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_chat_quick_ban', $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $userKey = trim((string) $request->request->get('user_key', ''));
        $reason = trim((string) $request->request->get('reason', ''));

        $violations = $this->validator->validate($userKey, ValidationLimits::userKeyValueConstraints());
        if (\count($violations) > 0) {
            $this->addFlash('danger', (string) $violations[0]->getMessage());

            return $this->redirectToRoute('app_admin_chat', $request->query->all(), Response::HTTP_SEE_OTHER);
        }

        $normalized = mb_strtolower($userKey, 'UTF-8');
        $existing = $this->chatBanRepository->findOneByUserKeyInsensitive($normalized);
        if ($existing instanceof ChatBan) {
            $this->addFlash('warning', 'Un bannissement existe déjà pour cet identifiant.');

            return $this->redirectToRoute('app_admin_chat', $request->query->all(), Response::HTTP_SEE_OTHER);
        }

        $ban = (new ChatBan())
            ->setUserKey($normalized)
            ->setBlockChat(true)
            ->setBlockPosts(true)
            ->setBlockComments(true)
            ->setBlockReactions(true)
            ->setBlockShares(true)
            ->setReason('' !== $reason ? $reason : 'Blocage rapide — console chat.');

        $violationsBan = $this->validator->validate($ban);
        if (\count($violationsBan) > 0) {
            $this->addFlash('danger', (string) $violationsBan[0]->getMessage());

            return $this->redirectToRoute('app_admin_chat', $request->query->all(), Response::HTTP_SEE_OTHER);
        }

        $this->entityManager->persist($ban);
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Blocage enregistré pour %s.', $normalized));

        return $this->redirectToRoute('app_admin_chat', $request->query->all(), Response::HTTP_SEE_OTHER);
    }

    private function handleKickUserFromChat(Request $request): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_chat_kick', $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $userKey = trim((string) $request->request->get('user_key', ''));
        $violations = $this->validator->validate($userKey, ValidationLimits::userKeyValueConstraints());
        if (\count($violations) > 0) {
            $this->addFlash('danger', (string) $violations[0]->getMessage());

            return $this->redirectToRoute('app_admin_chat', $request->query->all(), Response::HTTP_SEE_OTHER);
        }

        $normalized = mb_strtolower($userKey, 'UTF-8');
        if (mb_strtolower(ChatAdmin::BROADCAST_USER_KEY, 'UTF-8') === $normalized) {
            $this->addFlash('warning', 'Impossible d’expulser le pseudo système Admin.');

            return $this->redirectToRoute('app_admin_chat', $request->query->all(), Response::HTTP_SEE_OTHER);
        }

        $existing = $this->chatBanRepository->findOneByUserKeyInsensitive($normalized);
        $kickUntil = (new \DateTimeImmutable())->modify('+30 minutes');

        if ($existing instanceof ChatBan) {
            $existing->setBlockChat(true);
            if (null === $existing->getExpiresAt() || $existing->getExpiresAt() < $kickUntil) {
                $existing->setExpiresAt($kickUntil);
            }
            if (null === $existing->getReason() || '' === trim((string) $existing->getReason())) {
                $existing->setReason('Expulsion session chat (admin).');
            }
        } else {
            $ban = (new ChatBan())
                ->setUserKey($normalized)
                ->setBlockChat(true)
                ->setBlockPosts(false)
                ->setBlockComments(false)
                ->setBlockReactions(false)
                ->setBlockShares(false)
                ->setExpiresAt($kickUntil)
                ->setReason('Expulsion session chat (admin).');

            $violationsBan = $this->validator->validate($ban);
            if (\count($violationsBan) > 0) {
                $this->addFlash('danger', (string) $violationsBan[0]->getMessage());

                return $this->redirectToRoute('app_admin_chat', $request->query->all(), Response::HTTP_SEE_OTHER);
            }
            $this->entityManager->persist($ban);
        }

        $this->chatPresenceService->leaveUser($normalized);
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Utilisateur %s expulsé du chat (blocage chat 30 min).', $normalized));

        return $this->redirectToRoute('app_admin_chat', $request->query->all(), Response::HTTP_SEE_OTHER);
    }

    private function handlePurgeAllChatMessages(Request $request): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_chat_purge_all', $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $deleted = $this->chatMessageRepository->deleteAll();
        $this->chatPresenceService->resetPublicTimelineNow();
        $this->addFlash('success', sprintf('Conversation supprimée: %d message(s) retiré(s).', $deleted));

        return $this->redirectToRoute('app_admin_chat', $request->query->all(), Response::HTTP_SEE_OTHER);
    }

    #[Route('/bloques', name: 'app_admin_blocked', methods: ['GET', 'POST'])]
    public function blocked(Request $request): Response
    {
        if ($request->isMethod('POST')) {
            $action = (string) $request->request->get('_action', '');

            if ('add' === $action) {
                return $this->handleChatBanAdd($request);
            }

            if ('delete' === $action) {
                return $this->handleChatBanDelete($request);
            }

            if ('alert_block' === $action) {
                return $this->handleBadWordAlertBlock($request);
            }

            if ('alert_ignore' === $action) {
                return $this->handleBadWordAlertIgnore($request);
            }
        }

        $page = max(1, (int) $request->query->get('page', 1));
        $perPage = (int) $request->query->get('per_page', 10);
        if (!\in_array($perPage, [10, 20, 50], true)) {
            $perPage = 10;
        }
        $total = $this->chatBanRepository->count([]);
        $totalPages = $total > 0 ? (int) max(1, (int) ceil($total / $perPage)) : 1;
        if ($page > $totalPages) {
            $page = $totalPages;
        }
        $offset = ($page - 1) * $perPage;
        $displayFrom = $total > 0 ? $offset + 1 : 0;

        $bans = $this->chatBanRepository->findPaginatedOrderedByNewest($perPage, $offset);
        $displayTo = $total > 0 ? min($offset + \count($bans), $total) : 0;
        $now = new \DateTimeImmutable();
        $badWordAlerts = $this->notificationRepository->findUnreadBadWordAlertsForAdmin(50);

        return $this->render('admin/blocked.html.twig', [
            'bans' => $bans,
            'now' => $now,
            'bad_word_alerts' => $badWordAlerts,
            'ban_active_count' => $this->chatBanRepository->countCurrentlyActiveBans(),
            'page' => $page,
            'per_page' => $perPage,
            'total' => $total,
            'total_pages' => $totalPages,
            'display_from' => $displayFrom,
            'display_to' => $displayTo,
        ]);
    }

    #[Route('/assistant', name: 'app_admin_assistant', methods: ['GET'])]
    public function assistant(Request $request): Response
    {
        $snap = $this->adminAssistant->buildSnapshot();
        $range = (string) $request->query->get('range', '24h');

        return $this->render('admin/assistant.html.twig', [
            'assistant_snapshot' => $snap,
            'briefing' => $this->adminBriefing->buildPageBriefing($snap, $range),
        ]);
    }

    #[Route('/assistant/ask', name: 'app_admin_assistant_ask', methods: ['POST'])]
    public function assistantAsk(Request $request): JsonResponse
    {
        $raw = (string) $request->getContent();
        $data = '' !== $raw ? json_decode($raw, true) : [];
        if (!\is_array($data)) {
            $data = [];
        }
        $token = (string) ($data['_token'] ?? '');
        if (!$this->isCsrfTokenValid('admin_assistant_ask', $token)) {
            return new JsonResponse(['ok' => false, 'error' => 'csrf'], Response::HTTP_FORBIDDEN);
        }

        $message = trim((string) ($data['message'] ?? ''));
        $violations = $this->validator->validate($message, [
            new Assert\NotBlank(message: 'Message vide.'),
            new Assert\Length(max: 2000, maxMessage: 'Message trop long ({{ limit }} caractères max).'),
        ]);
        if (\count($violations) > 0) {
            return new JsonResponse([
                'ok' => false,
                'error' => 'validation',
                'message' => (string) $violations[0]->getMessage(),
            ], Response::HTTP_UNPROCESSABLE_ENTITY);
        }

        $out = $this->adminAssistant->answer($message);
        $related = [];
        foreach ($out['related'] as $item) {
            $route = (string) ($item['route'] ?? '');
            if ('' === $route) {
                continue;
            }
            $params = \is_array($item['params'] ?? null) ? $item['params'] : [];
            $related[] = [
                'label' => (string) ($item['label'] ?? $route),
                'url' => $this->generateUrl($route, $params),
            ];
        }

        return new JsonResponse([
            'ok' => true,
            'reply' => $out['reply'],
            'intent' => $out['intent'],
            'confidence' => $out['confidence'] ?? 'high',
            'envelope' => $out['envelope'] ?? null,
            'related' => $related,
        ]);
    }

    private function handleChatBanAdd(Request $request): Response
    {
        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('admin_chat_ban_add', $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $userKey = trim((string) $request->request->get('user_key', ''));
        $reason = trim((string) $request->request->get('reason', ''));
        $expiresRaw = trim((string) $request->request->get('expires_at', ''));
        $blockAll = '1' === (string) $request->request->get('scope_all', '');
        $blockChat = $blockAll || '1' === (string) $request->request->get('scope_chat', '');
        $blockPosts = $blockAll || '1' === (string) $request->request->get('scope_posts', '');
        $blockComments = $blockAll || '1' === (string) $request->request->get('scope_comments', '');
        $blockReactions = $blockAll || '1' === (string) $request->request->get('scope_reactions', '');
        $blockShares = $blockAll || '1' === (string) $request->request->get('scope_shares', '');

        $violations = $this->validator->validate($userKey, ValidationLimits::userKeyValueConstraints());
        if (\count($violations) > 0) {
            $this->addFlash('danger', (string) $violations[0]->getMessage());

            return $this->redirectToRoute('app_admin_blocked', [], Response::HTTP_SEE_OTHER);
        }
        if (!$blockChat && !$blockPosts && !$blockComments && !$blockReactions && !$blockShares) {
            $this->addFlash('danger', 'Sélectionnez au moins une portée de blocage (ou "Tout").');

            return $this->redirectToRoute('app_admin_blocked', [], Response::HTTP_SEE_OTHER);
        }

        $normalizedUserKey = mb_strtolower($userKey, 'UTF-8');
        $existing = $this->chatBanRepository->findOneByUserKeyInsensitive($normalizedUserKey);
        if ($existing instanceof ChatBan) {
            $this->addFlash('warning', 'Un bannissement existe déjà pour cet identifiant.');

            return $this->redirectToRoute('app_admin_blocked', [], Response::HTTP_SEE_OTHER);
        }

        $ban = new ChatBan();
        $ban->setUserKey($normalizedUserKey);
        $ban->setBlockChat($blockChat);
        $ban->setBlockPosts($blockPosts);
        $ban->setBlockComments($blockComments);
        $ban->setBlockReactions($blockReactions);
        $ban->setBlockShares($blockShares);
        if ('' !== $reason) {
            $ban->setReason($reason);
        }

        if ('' !== $expiresRaw) {
            try {
                $expires = new \DateTimeImmutable($expiresRaw);
                if ($expires <= new \DateTimeImmutable()) {
                    $this->addFlash('danger', 'La date d’expiration doit être dans le futur.');

                    return $this->redirectToRoute('app_admin_blocked', [], Response::HTTP_SEE_OTHER);
                }
                $ban->setExpiresAt($expires);
            } catch (\Exception) {
                $this->addFlash('danger', 'Date d’expiration invalide.');

                return $this->redirectToRoute('app_admin_blocked', [], Response::HTTP_SEE_OTHER);
            }
        }

        $violationsBan = $this->validator->validate($ban);
        if (\count($violationsBan) > 0) {
            $this->addFlash('danger', (string) $violationsBan[0]->getMessage());

            return $this->redirectToRoute('app_admin_blocked', [], Response::HTTP_SEE_OTHER);
        }

        $this->entityManager->persist($ban);
        $this->entityManager->flush();
        $this->addFlash('success', 'Bannissement enregistré.');

        return $this->redirectToRoute('app_admin_blocked', [], Response::HTTP_SEE_OTHER);
    }

    private function handleChatBanDelete(Request $request): Response
    {
        $id = (int) $request->request->get('id', 0);
        $token = (string) $request->request->get('_token');
        if ($id < 1 || !$this->isCsrfTokenValid('admin_chat_ban_delete'.$id, $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $ban = $this->chatBanRepository->find($id);
        if (!$ban instanceof ChatBan) {
            throw $this->createNotFoundException('Bannissement introuvable.');
        }

        $this->entityManager->remove($ban);
        $this->entityManager->flush();
        $this->addFlash('success', 'Bannissement levé.');

        return $this->redirectToRoute('app_admin_blocked', [], Response::HTTP_SEE_OTHER);
    }

    private function handleBadWordAlertBlock(Request $request): Response
    {
        $id = (int) $request->request->get('alert_id', 0);
        $token = (string) $request->request->get('_token');
        if ($id < 1 || !$this->isCsrfTokenValid('admin_badword_alert_action'.$id, $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $alert = $this->notificationRepository->findUnreadBadWordAlertByIdForAdmin($id);
        if (!$alert instanceof Notification) {
            throw $this->createNotFoundException('Alerte introuvable.');
        }

        $userKey = trim($alert->getActorKey());
        $violations = $this->validator->validate($userKey, ValidationLimits::userKeyValueConstraints());
        if (\count($violations) > 0) {
            $alert->setIsRead(true);
            $this->entityManager->flush();
            $this->addFlash('warning', 'Alerte marquée, mais blocage impossible: identifiant invalide.');

            return $this->redirectToRoute('app_admin_blocked', [], Response::HTTP_SEE_OTHER);
        }

        $normalized = mb_strtolower($userKey, 'UTF-8');
        $existing = $this->chatBanRepository->findOneByUserKeyInsensitive($normalized);
        if (!$existing instanceof ChatBan) {
            $ban = (new ChatBan())
                ->setUserKey($normalized)
                ->setBlockChat(true)
                ->setBlockPosts(true)
                ->setBlockComments(true)
                ->setBlockReactions(true)
                ->setBlockShares(true)
                ->setReason('Blocage confirmé depuis alerte bad words.');
            $this->entityManager->persist($ban);
        }

        $alert->setIsRead(true);
        $this->entityManager->flush();
        $this->addFlash('success', sprintf('Blocage confirmé pour %s.', $normalized));

        return $this->redirectToRoute('app_admin_blocked', [], Response::HTTP_SEE_OTHER);
    }

    private function handleBadWordAlertIgnore(Request $request): Response
    {
        $id = (int) $request->request->get('alert_id', 0);
        $token = (string) $request->request->get('_token');
        if ($id < 1 || !$this->isCsrfTokenValid('admin_badword_alert_action'.$id, $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $alert = $this->notificationRepository->findUnreadBadWordAlertByIdForAdmin($id);
        if (!$alert instanceof Notification) {
            throw $this->createNotFoundException('Alerte introuvable.');
        }

        $alert->setIsRead(true);
        $this->entityManager->flush();
        $this->addFlash('success', 'Alerte ignorée.');

        return $this->redirectToRoute('app_admin_blocked', [], Response::HTTP_SEE_OTHER);
    }
}
