<?php

declare(strict_types=1);

namespace App\Controller;

use App\Repository\NotificationRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/connect/notifications')]
final class NotificationController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    #[Route('', name: 'app_notification_index', methods: ['GET'])]
    public function index(Request $request, NotificationRepository $notificationRepository): Response
    {
        $userKey = trim((string) ($request->getSession()->get('connect_user_key') ?? ''));
        if ('' === $userKey) {
            $this->addFlash('warning', 'Enregistrez un identifiant pour consulter vos notifications.');

            return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
        }

        $notifications = $notificationRepository->findRecentForRecipient($userKey, 100);
        $notificationRepository->markAllReadForRecipient($userKey);

        return $this->render('notification/index.html.twig', [
            'notifications' => $notifications,
            'sessionUserKey' => $userKey,
        ]);
    }

    #[Route('/unread-count', name: 'app_notification_unread_count', methods: ['GET'])]
    public function unreadCount(Request $request, NotificationRepository $notificationRepository): JsonResponse
    {
        $userKey = trim((string) ($request->getSession()->get('connect_user_key') ?? ''));
        if ('' === $userKey) {
            return new JsonResponse(['ok' => true, 'count' => 0]);
        }

        return new JsonResponse([
            'ok' => true,
            'count' => $notificationRepository->countUnreadForRecipient($userKey),
        ]);
    }

    #[Route('/{id}/delete', name: 'app_notification_delete', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function delete(int $id, Request $request, NotificationRepository $notificationRepository): Response
    {
        $userKey = trim((string) ($request->getSession()->get('connect_user_key') ?? ''));
        if ('' === $userKey) {
            $this->addFlash('warning', 'Enregistrez un identifiant pour gérer vos notifications.');

            return $this->redirectToRoute('app_post_index', [], Response::HTTP_SEE_OTHER);
        }

        $token = (string) $request->request->get('_token');
        if (!$this->isCsrfTokenValid('delete_notification'.$id, $token)) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $notification = $notificationRepository->findOneForRecipientById($userKey, $id);
        if (null === $notification) {
            $this->addFlash('warning', 'Notification introuvable.');

            return $this->redirectToRoute('app_notification_index', [], Response::HTTP_SEE_OTHER);
        }

        $this->entityManager->remove($notification);
        $this->entityManager->flush();
        $this->addFlash('success', 'Notification supprimée.');

        return $this->redirectToRoute('app_notification_index', [], Response::HTTP_SEE_OTHER);
    }
}
