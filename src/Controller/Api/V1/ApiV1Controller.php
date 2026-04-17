<?php

declare(strict_types=1);

namespace App\Controller\Api\V1;

use App\Api\ApiJsonResponse;
use App\Repository\PostRepository;
use App\Service\SocialShareService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

/**
 * API REST versionnée — point d’entrée documentable (cours / rapport).
 */
#[Route('/api/v1')]
final class ApiV1Controller extends AbstractController
{
    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly SocialShareService $socialShareService,
    ) {
    }

    #[Route('', name: 'api_v1_root', methods: ['GET'])]
    public function index(): JsonResponse
    {
        return ApiJsonResponse::success([
            'service' => 'Tabaani Connect API',
            'version' => ApiJsonResponse::VERSION,
            'documentation' => 'GET /api/v1 — découverte ; partage social sous /api/v1/posts/{id}/share',
            'endpoints' => [
                [
                    'name' => 'share_manifest',
                    'method' => 'GET',
                    'path' => '/api/v1/posts/{postId}/share',
                    'description' => 'Texte de partage, URL canonique, plateformes autorisées, schéma pour journaliser un partage.',
                ],
                [
                    'name' => 'share_event',
                    'method' => 'POST',
                    'path' => '/api/v1/posts/{postId}/share/events',
                    'description' => 'Enregistre un partage (session utilisateur + CSRF). Corps JSON : platform, _token.',
                ],
            ],
        ]);
    }

    #[Route('/posts/{id}/share', name: 'api_v1_post_share_manifest', requirements: ['id' => '\d+'], methods: ['GET'])]
    public function shareManifest(int $id, UrlGeneratorInterface $urlGenerator): JsonResponse
    {
        $post = $this->postRepository->find($id);
        if (null === $post) {
            return ApiJsonResponse::error('not_found', 'Publication introuvable.', Response::HTTP_NOT_FOUND);
        }

        $data = $this->socialShareService->buildApiShareManifest($post, $urlGenerator);
        $data['csrf'] = [
            'token_id' => 'share_log'.$id,
            'hint' => 'Envoyer le même jeton que le formulaire web (share_log{postId}) dans le corps JSON sous la clé _token.',
        ];

        return ApiJsonResponse::success($data);
    }

    #[Route('/posts/{id}/share/events', name: 'api_v1_post_share_events', requirements: ['id' => '\d+'], methods: ['POST'])]
    public function shareEvent(Request $request, int $id): JsonResponse
    {
        $post = $this->postRepository->find($id);
        if (null === $post) {
            return ApiJsonResponse::error('not_found', 'Publication introuvable.', Response::HTTP_NOT_FOUND);
        }

        $raw = (string) $request->getContent();
        $payload = '' !== $raw ? json_decode($raw, true) : [];
        if (!\is_array($payload)) {
            $payload = [];
        }

        $token = (string) ($payload['_token'] ?? '');
        if (!$this->isCsrfTokenValid('share_log'.$id, $token)) {
            return ApiJsonResponse::error('csrf_invalid', 'Jeton CSRF invalide ou absent.', Response::HTTP_FORBIDDEN);
        }

        $platform = (string) ($payload['platform'] ?? '');
        $session = $request->getSession();
        $userKey = trim((string) ($session->get('connect_user_key') ?? ''));

        $result = $this->socialShareService->recordShareLog($post, $userKey, $platform);
        if (isset($result['ok']) && true === $result['ok']) {
            return ApiJsonResponse::success([
                'logged' => $result['logged'],
                'post_id' => $post->getId(),
                'platform' => strtolower(trim($platform)),
            ]);
        }

        $err = (string) ($result['error'] ?? 'unknown');

        return match ($err) {
            'invalid_platform' => ApiJsonResponse::error(
                'invalid_platform',
                'Plateforme inconnue. Utiliser une valeur de data.platforms_allowed du manifeste GET.',
                Response::HTTP_BAD_REQUEST
            ),
            'banned_for_shares' => ApiJsonResponse::error('forbidden', 'Identifiant bloqué pour les partages.', Response::HTTP_FORBIDDEN),
            'validation' => ApiJsonResponse::error('validation_error', 'Données invalides (contraintes métier).', Response::HTTP_BAD_REQUEST),
            default => ApiJsonResponse::error('server_error', 'Enregistrement impossible.', Response::HTTP_INTERNAL_SERVER_ERROR),
        };
    }
}
