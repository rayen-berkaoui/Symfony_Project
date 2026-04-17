<?php

declare(strict_types=1);

namespace App\Controller;

use App\Service\SmartHashtagService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;

final class SmartHashtagController extends AbstractController
{
    #[Route('/connect/ai/smart-hashtags/suggest', name: 'app_ai_smart_hashtag_suggest', methods: ['POST'])]
    public function suggest(Request $request, SmartHashtagService $smartHashtagService): Response
    {
        $raw = (string) $request->getContent();
        $payload = [];
        if ('' !== trim($raw)) {
            $decoded = json_decode($raw, true);
            if (\is_array($decoded)) {
                $payload = $decoded;
            }
        }

        $content = (string) ($payload['content'] ?? '');
        $currentHashtags = (string) ($payload['currentHashtags'] ?? '');

        $suggestions = $smartHashtagService->suggestHashtags($content, $currentHashtags, 8);

        return new JsonResponse([
            'ok' => true,
            'suggestions' => $suggestions,
        ]);
    }
}

