<?php

declare(strict_types=1);

namespace App\Service;

use App\Repository\PostRepository;

final class SmartHashtagService
{
    /**
     * Common stopwords to reduce noise during keyword extraction.
     *
     * @var array<int, string>
     */
    private array $stopWords = [
        // English
        'the', 'and', 'for', 'with', 'from', 'into', 'over', 'under', 'after', 'before',
        'that', 'this', 'these', 'those', 'are', 'is', 'was', 'were', 'be', 'been',
        'by', 'or', 'as', 'at', 'it', 'its', 'an',
        // French (ASCII only)
        'le', 'la', 'les', 'de', 'des', 'du', 'des', 'un', 'une', 'et', 'ou', 'mais',
        'dans', 'sur', 'sous', 'avec', 'sans', 'plus', 'moins', 'pour', 'par', 'au', 'aux',
        // Misc
        'www', 'http', 'https',
    ];

    public function __construct(
        private readonly PostRepository $postRepository,
        private readonly PostTagIndexBuilder $postTagIndexBuilder,
    ) {
    }

    /**
     * @return array<int, array{tag: string, count: int}>
     */
    public function suggestHashtags(string $content, string $currentHashtags, int $limit = 8): array
    {
        $content = trim($content);
        $currentHashtags = trim($currentHashtags);

        if ('' === $content) {
            return [];
        }

        $limit = max(1, min(12, $limit));

        $currentTags = $this->postTagIndexBuilder->parseFilterInput($currentHashtags);
        $currentSet = array_fill_keys($currentTags, true);

        $keywords = $this->extractKeywords($content);
        if ([] === $keywords) {
            // Fallback: if we cannot extract keywords, only try existing tags.
            return [];
        }

        // Keep candidate set small to reduce DB queries.
        $candidateTags = array_slice(array_keys($keywords), 0, 12);
        $candidateTags = array_values(array_filter(
            $candidateTags,
            static fn (string $t): bool => !isset($currentSet[$t])
        ));

        $counts = [];
        foreach ($candidateTags as $tag) {
            $count = $this->postRepository->countPostsContainingHashtag($tag);
            if ($count > 0) {
                $counts[] = ['tag' => $tag, 'count' => $count];
            }
        }

        if ([] !== $counts) {
            usort($counts, static function (array $a, array $b): int {
                if ($a['count'] === $b['count']) {
                    return strcmp($a['tag'], $b['tag']);
                }

                return $b['count'] <=> $a['count'];
            });

            return array_slice($counts, 0, $limit);
        }

        // Fallback: return first candidates with count=0 (still useful for UX).
        $fallback = [];
        foreach ($candidateTags as $tag) {
            $fallback[] = ['tag' => $tag, 'count' => 0];
            if (\count($fallback) >= $limit) {
                break;
            }
        }

        return $fallback;
    }

    /**
     * @return array<string, int>  tag => frequency
     */
    private function extractKeywords(string $content): array
    {
        $text = strip_tags($content);
        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');
        $text = mb_strtolower($text, 'UTF-8');

        // Keep letters/numbers/_ only (hashtagsIndex uses same policy).
        $text = preg_replace('/[^\p{L}\p{N}_]+/u', ' ', $text) ?? '';
        $parts = preg_split('/\s+/u', trim($text), -1, \PREG_SPLIT_NO_EMPTY) ?: [];
        if ([] === $parts) {
            return [];
        }

        $freq = [];
        foreach ($parts as $p) {
            $token = trim((string) $p);
            if ('' === $token) {
                continue;
            }
            if (mb_strlen($token, 'UTF-8') < 4) {
                continue;
            }
            if (\in_array($token, $this->stopWords, true)) {
                continue;
            }
            if (!preg_match('/^[\p{L}\p{N}_]+$/u', $token)) {
                continue;
            }
            $freq[$token] = ($freq[$token] ?? 0) + 1;
        }

        if ([] === $freq) {
            return [];
        }

        arsort($freq, \SORT_NUMERIC);
        return $freq;
    }
}

