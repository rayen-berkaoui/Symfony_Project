<?php

declare(strict_types=1);

namespace App\Service;

final class AutoSummaryService
{
    /**
     * Extractive summary: pick first sentences up to a max length.
     */
    public function summarize(string $content, int $maxLen = 240): string
    {
        $content = trim($content);
        if ('' === $content) {
            return '';
        }

        // Remove HTML tags and normalize whitespace.
        $text = strip_tags($content);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = preg_replace('/\s+/u', ' ', $text) ?? '';
        $text = trim($text);

        if (mb_strlen($text, 'UTF-8') <= $maxLen) {
            return $text;
        }

        // Split into sentences (works for French/English punctuation reasonably well).
        $sentences = preg_split('/(?<=[\.\!\?…])\s+/u', $text, -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        if ([] === $sentences) {
            return $this->truncate($text, $maxLen);
        }

        $out = '';
        foreach ($sentences as $s) {
            $s = trim((string) $s);
            if ('' === $s) {
                continue;
            }

            $candidate = '' === $out ? $s : ($out.' '.$s);
            if (mb_strlen($candidate, 'UTF-8') > $maxLen) {
                break;
            }

            $out = $candidate;
        }

        if ('' === $out) {
            return $this->truncate($text, $maxLen);
        }

        return $this->truncate($out, $maxLen);
    }

    private function truncate(string $text, int $maxLen): string
    {
        $maxLen = max(20, $maxLen);
        if (mb_strlen($text, 'UTF-8') <= $maxLen) {
            return $text;
        }

        return mb_substr($text, 0, $maxLen, 'UTF-8').'…';
    }
}

