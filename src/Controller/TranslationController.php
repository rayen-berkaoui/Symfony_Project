<?php

namespace App\Controller;

use App\Service\TranslationService;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\JsonResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Annotation\Route;

class TranslationController extends AbstractController
{
    #[Route('/api/translate', name: 'app_api_translate', methods: ['POST'])]
    public function translate(Request $request, TranslationService $translationService): JsonResponse
    {
        $payload = json_decode($request->getContent(), true);
        if (!is_array($payload)) {
            return $this->json(['error' => 'Invalid JSON payload.'], 400);
        }

        $target = isset($payload['target']) && is_string($payload['target']) ? strtolower(trim($payload['target'])) : 'fr';
        $allowedTargets = ['fr', 'en', 'ar'];
        if (!in_array($target, $allowedTargets, true)) {
            return $this->json(['error' => 'Unsupported language.'], 400);
        }

        $texts = [];
        if (isset($payload['texts']) && is_array($payload['texts'])) {
            foreach ($payload['texts'] as $text) {
                if (!is_string($text)) {
                    continue;
                }

                $normalized = trim(preg_replace('/\s+/', ' ', $text) ?? '');
                if ($normalized !== '') {
                    $texts[] = $normalized;
                }
            }
        }

        if ($texts === []) {
            return $this->json(['translations' => []]);
        }

        if ($target === 'fr') {
            $translations = [];
            foreach ($texts as $text) {
                $translations[$text] = $text;
            }

            return $this->json(['translations' => $translations]);
        }

        return $this->json([
            'translations' => $translationService->translateBatch($texts, 'auto', $target),
        ]);
    }
}
