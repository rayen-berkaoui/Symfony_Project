<?php

namespace App\Service;

use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(default::LIBRETRANSLATE_BASE_URL)%')]
        private readonly string $baseUrl = 'https://libretranslate.com',
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->baseUrl) !== '';
    }

    public function translate(string $text, string $target = 'en'): ?string
    {
        $text = trim($text);
        if ($text === '') {
            return null;
        }

        $endpoint = rtrim($this->baseUrl ?: 'https://libretranslate.com', '/') . '/translate';

        try {
            $response = $this->httpClient->request('POST', $endpoint, [
                'body' => [
                    'q' => $text,
                    'source' => 'auto',
                    'target' => $target,
                    'format' => 'text',
                ],
                'timeout' => 8,
            ]);
            $data = $response->toArray(false);
            $translated = $data['translatedText'] ?? null;
            return is_string($translated) && trim($translated) !== '' ? $translated : null;
        } catch (\Throwable) {
            return null;
        }
    }

    /** @return array<string, string|null> */
    public function translateSummarySet(string $text): array
    {
        return [
            'en' => $this->translate($text, 'en'),
            'ar' => $this->translate($text, 'ar'),
        ];
    }
}
