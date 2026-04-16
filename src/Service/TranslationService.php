<?php

namespace App\Service;

use Stichoza\GoogleTranslate\GoogleTranslate;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TranslationService
{
    public function __construct(private HttpClientInterface $client)
    {
    }

    public function translate(string $text, string $source = 'fr', string $target = 'en'): ?string
    {
        $text = trim($text);
        if ($text === '' || $source === $target) {
            return $text;
        }

        $response = $this->client->request('GET', 'https://api.mymemory.translated.net/get', [
            'query' => [
                'q' => $text,
                'langpair' => $source . '|' . $target,
            ],
        ]);

        $data = $response->toArray();

        return $data['responseData']['translatedText'] ?? null;
    }

    /**
     * @param list<string> $texts
     * @return array<string, string>
     */
    public function translateBatch(array $texts, string $source = 'fr', string $target = 'en'): array
    {
        $texts = array_values(array_unique(array_filter(array_map(
            static fn ($text) => is_string($text) ? trim($text) : '',
            $texts
        ))));

        if ($texts === [] || $source === $target) {
            return array_combine($texts, $texts) ?: [];
        }

        $separator = '[[[TSEP]]]';
        $translator = new GoogleTranslate($target, $source === '' ? null : $source, [
            'timeout' => 15,
        ]);
        $translator->preserveParameters('/\[\[\[TSEP\]\]\]/');

        $results = [];
        $chunks = array_chunk($texts, 25);

        foreach ($chunks as $chunk) {
            $payload = implode(" {$separator} ", $chunk);

            try {
                $translatedPayload = $translator->translate($payload);
            } catch (\Throwable) {
                $translatedPayload = null;
            }

            if (!is_string($translatedPayload) || trim($translatedPayload) === '') {
                foreach ($chunk as $text) {
                    $translated = $this->translate($text, $source, $target);
                    if ($translated !== null) {
                        $results[$text] = $translated;
                    }
                }

                continue;
            }

            $translatedParts = preg_split('/\s*\[\[\[TSEP\]\]\]\s*/u', $translatedPayload) ?: [];
            if (count($translatedParts) !== count($chunk)) {
                foreach ($chunk as $text) {
                    $translated = $this->translate($text, $source, $target);
                    if ($translated !== null) {
                        $results[$text] = $translated;
                    }
                }

                continue;
            }

            foreach ($chunk as $index => $text) {
                $results[$text] = trim($translatedParts[$index]);
            }
        }

        return $results;
    }
}
