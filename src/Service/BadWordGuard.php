<?php

declare(strict_types=1);

namespace App\Service;

/**
 * Détecte les mots interdits dans un texte normalisé.
 */
final class BadWordGuard
{
    /** @var list<string> */
    private array $badWords;

    /**
     * @param list<string> $badWords
     */
    public function __construct(array $badWords)
    {
        $normalized = [];

        foreach ($badWords as $word) {
            $clean = $this->normalizeToken((string) $word);
            if ('' !== $clean) {
                $normalized[$clean] = true;
            }
        }

        $this->badWords = array_keys($normalized);
    }

    public function findFirstMatch(?string $text): ?string
    {
        if (null === $text) {
            return null;
        }

        foreach ($this->tokenize($text) as $token) {
            if (\in_array($token, $this->badWords, true)) {
                return $token;
            }
        }

        return null;
    }

    /**
     * @return list<string>
     */
    private function tokenize(string $text): array
    {
        $parts = preg_split('/[^\p{L}\p{N}_]+/u', mb_strtolower($text, 'UTF-8'), -1, \PREG_SPLIT_NO_EMPTY);

        if (false === $parts) {
            return [];
        }

        $tokens = [];
        foreach ($parts as $part) {
            $token = $this->normalizeToken($part);
            if ('' !== $token) {
                $tokens[] = $token;
            }
        }

        return $tokens;
    }

    private function normalizeToken(string $token): string
    {
        $token = mb_strtolower(trim($token), 'UTF-8');

        return (string) preg_replace('/[^\p{L}\p{N}_]+/u', '', $token);
    }
}
