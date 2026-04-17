<?php

namespace App\Controller;

use App\Entity\ChatBan;
use App\Entity\ChatMessage;
use App\Form\ChatMessageType;
use App\Repository\ChatBanRepository;
use App\Repository\ChatMessageRepository;
use App\Service\ChatPresenceService;
use App\Validation\ValidationLimits;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Validator\Validator\ValidatorInterface;

#[Route('/connect/chat')]
class ChatController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly ChatBanRepository $chatBanRepository,
        private readonly ChatPresenceService $chatPresenceService,
        private readonly ValidatorInterface $validator,
    ) {
    }

    #[Route('', name: 'app_chat_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $sessionUserKey = trim((string) ($request->getSession()->get('connect_user_key') ?? ''));
        if ('' !== $sessionUserKey && $this->chatBanRepository->isUserCurrentlyBannedForScope($sessionUserKey, ChatBan::SCOPE_CHAT)) {
            $this->chatPresenceService->leaveUser($sessionUserKey);
            $request->getSession()->remove('connect_user_key');
            $this->addFlash('warning', 'Votre accès au chat est temporairement bloqué.');
            $sessionUserKey = '';
        }

        if ('' !== $sessionUserKey) {
            if (0 === $this->chatPresenceService->countActiveUsers()) {
                $this->chatPresenceService->resetPublicTimelineNow();
            }
            $this->chatPresenceService->touchUser($sessionUserKey);
        }

        $chatMessage = new ChatMessage();
        if ('' !== $sessionUserKey) {
            $chatMessage->setUserKey($sessionUserKey);
        }

        $form = $this->createForm(ChatMessageType::class, $chatMessage);
        $form->remove('userKey');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userKey = trim((string) $chatMessage->getUserKey());
            if ($this->chatBanRepository->isUserCurrentlyBannedForScope($userKey, ChatBan::SCOPE_CHAT)) {
                $this->chatPresenceService->leaveUser($userKey);
                $request->getSession()->remove('connect_user_key');
                $this->addFlash('warning', 'Votre identifiant est exclu du chat (bannissement actif).');

                return $this->redirectToRoute('app_chat_index', [], Response::HTTP_SEE_OTHER);
            }

            $request->getSession()->set('connect_user_key', $userKey);
            $this->chatPresenceService->touchUser($userKey);
            $this->entityManager->persist($chatMessage);
            $this->entityManager->flush();
            $this->addFlash('success', 'Message publié.');

            return $this->redirectToRoute('app_chat_index', [], Response::HTTP_SEE_OTHER);
        }

        $messages = $this->chatMessageRepository->findRecentForPublic($this->chatPresenceService->getPublicTimelineCutoff(), 150);
        $onlineCount = $this->chatPresenceService->countActiveUsers();

        return $this->render('chat/index.html.twig', [
            'form' => $form,
            'messages' => $messages,
            'sessionUserKey' => $sessionUserKey,
            'online_count' => $onlineCount,
        ]);
    }

    #[Route('/leave', name: 'app_chat_leave', methods: ['POST'])]
    public function leave(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('chat_leave', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $sessionUserKey = trim((string) ($request->getSession()->get('connect_user_key') ?? ''));
        if ('' !== $sessionUserKey) {
            $this->chatPresenceService->leaveUser($sessionUserKey);
        }
        $request->getSession()->remove('connect_user_key');
        if (0 === $this->chatPresenceService->countActiveUsers()) {
            $this->chatPresenceService->resetPublicTimelineNow();
            $this->addFlash('success', 'Vous avez quitté le salon. Tous les messages sont masqués du salon public jusqu’à la prochaine session.');

            return $this->redirectToRoute('app_chat_index', [], Response::HTTP_SEE_OTHER);
        }
        $this->addFlash('success', 'Vous avez quitté le salon.');

        return $this->redirectToRoute('app_chat_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/presence-ping', name: 'app_chat_presence_ping', methods: ['GET'])]
    public function presencePing(Request $request): Response
    {
        $sessionUserKey = trim((string) ($request->getSession()->get('connect_user_key') ?? ''));
        if ('' !== $sessionUserKey && !$this->chatBanRepository->isUserCurrentlyBannedForScope($sessionUserKey, ChatBan::SCOPE_CHAT)) {
            $this->chatPresenceService->touchUser($sessionUserKey);
        }

        return $this->json([
            'ok' => true,
            'online' => $this->chatPresenceService->countActiveUsers(),
        ]);
    }

    #[Route('/pseudo', name: 'app_chat_set_pseudo', methods: ['POST'])]
    public function setPseudo(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('pseudo_chat', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $key = trim((string) $request->request->get('user_key', ''));
        $violations = $this->validator->validate($key, ValidationLimits::userKeyValueConstraints());
        if (\count($violations) > 0) {
            $this->addFlash('warning', (string) $violations[0]->getMessage());
        } else {
            $request->getSession()->set('connect_user_key', $key);
            $this->chatPresenceService->touchUser($key);
            $this->addFlash('success', 'Identifiant enregistré pour cette session.');
        }

        $target = (string) $request->request->get('_redirect', '');
        $route = match ($target) {
            'app_home' => 'app_home',
            default => 'app_chat_index',
        };

        return $this->redirectToRoute($route, [], Response::HTTP_SEE_OTHER);
    }
}
