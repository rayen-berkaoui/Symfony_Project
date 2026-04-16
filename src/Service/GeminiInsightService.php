<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class GeminiInsightService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(GEMINI_API_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== '' && !str_starts_with(trim($this->apiKey), 'METTEZ_');
    }

    public function generate(string $prompt, ?string $systemInstruction = null): ?string
    {
        if (!$this->isConfigured()) {
            return null;
        }

        $message = trim(($systemInstruction ? trim($systemInstruction) . "\n\n" : '') . trim($prompt));

        try {
            $response = $this->httpClient->request('POST', 'https://generativelanguage.googleapis.com/v1beta/models/gemini-2.5-flash:generateContent', [
                'headers' => [
                    'Content-Type' => 'application/json',
                    'x-goog-api-key' => $this->apiKey,
                ],
                'json' => [
                    'contents' => [[
                        'parts' => [[
                            'text' => $message,
                        ]],
                    ]],
                    'generationConfig' => [
                        'temperature' => 0.6,
                        'maxOutputTokens' => 350,
                    ],
                ],
            ]);

            $data = $response->toArray(false);
            return isset($data['candidates'][0]['content']['parts'][0]['text'])
                ? trim((string) $data['candidates'][0]['content']['parts'][0]['text'])
                : null;
        } catch (\Throwable) {
            return null;
        }
    }
}
