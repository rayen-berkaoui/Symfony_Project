<?php

namespace App\Controller;

use App\Entity\ChatMessage;
use App\Form\ChatMessageType;
use App\Repository\ChatBanRepository;
use App\Repository\ChatMessageRepository;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

#[Route('/connect/chat')]
class ChatController extends AbstractController
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly ChatMessageRepository $chatMessageRepository,
        private readonly ChatBanRepository $chatBanRepository,
    ) {
    }

    #[Route('', name: 'app_chat_index', methods: ['GET', 'POST'])]
    public function index(Request $request): Response
    {
        $sessionUserKey = trim((string) ($request->getSession()->get('connect_user_key') ?? ''));

        $chatMessage = new ChatMessage();
        if ('' !== $sessionUserKey) {
            $chatMessage->setUserKey($sessionUserKey);
        }

        $form = $this->createForm(ChatMessageType::class, $chatMessage);
        $form->remove('userKey');
        $form->handleRequest($request);

        if ($form->isSubmitted() && $form->isValid()) {
            $userKey = trim((string) $chatMessage->getUserKey());
            if ($this->chatBanRepository->isUserCurrentlyBanned($userKey)) {
                $this->addFlash('warning', 'Votre identifiant est exclu du chat (bannissement actif).');

                return $this->redirectToRoute('app_chat_index', [], Response::HTTP_SEE_OTHER);
            }

            $request->getSession()->set('connect_user_key', $userKey);
            $this->entityManager->persist($chatMessage);
            $this->entityManager->flush();
            $this->addFlash('success', 'Message publié.');

            return $this->redirectToRoute('app_chat_index', [], Response::HTTP_SEE_OTHER);
        }

        $messages = $this->chatMessageRepository->findRecent(150);
        $onlineCount = $this->chatMessageRepository->countDistinctUsersSince(new \DateTimeImmutable('-30 minutes'));

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

        $request->getSession()->remove('connect_user_key');
        $this->addFlash('success', 'Vous avez quitté le salon.');

        return $this->redirectToRoute('app_chat_index', [], Response::HTTP_SEE_OTHER);
    }

    #[Route('/pseudo', name: 'app_chat_set_pseudo', methods: ['POST'])]
    public function setPseudo(Request $request): Response
    {
        if (!$this->isCsrfTokenValid('pseudo_chat', (string) $request->request->get('_token'))) {
            throw $this->createAccessDeniedException('Jeton CSRF invalide.');
        }

        $key = trim((string) $request->request->get('user_key', ''));
        if ('' === $key) {
            $this->addFlash('warning', 'Identifiant vide.');
        } else {
            $request->getSession()->set('connect_user_key', $key);
            $this->addFlash('success', 'Identifiant enregistré pour cette session.');
        }

        return $this->redirectToRoute('app_chat_index', [], Response::HTTP_SEE_OTHER);
    }
}
