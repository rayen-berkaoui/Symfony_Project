<?php

declare(strict_types=1);

namespace App\Service;

final class PostTagIndexBuilder
{
    public function parseFilterInput(string $raw): array
    {
        $raw = trim($raw);
        if ('' === $raw) {
            return [];
        }

        $chunks = preg_split('/\s*(?:,|\||\s+ou\s+|\s+or\s+|\s+أو\s+)\s*/iu', $raw, -1, \PREG_SPLIT_NO_EMPTY);
        if (false === $chunks) {
            $chunks = [$raw];
        }

        $seen = [];
        $out = [];

        foreach ($chunks as $chunk) {
            $chunk = trim((string) $chunk);
            if ('' === $chunk) {
                continue;
            }

            $parts = preg_split('/\s+(?=#)/u', $chunk, -1, \PREG_SPLIT_NO_EMPTY);
            if (false === $parts) {
                $parts = [$chunk];
            }

            foreach ($parts as $part) {
                $part = trim($part);
                if ('' === $part) {
                    continue;
                }
                $part = preg_replace('/^#+/u', '', $part);
                if ('' === $part || !preg_match('/^[\p{L}\p{N}_]+$/u', $part)) {
                    continue;
                }
                $key = $this->lower($part);
                if (isset($seen[$key])) {
                    continue;
                }
                $seen[$key] = true;
                $out[] = $key;
            }
        }

        return $out;
    }

    public function build(string $content): string
    {
        $content = trim($content);
        if ('' === $content) {
            return '';
        }

        $stripped = (string) preg_replace('/<!--.*?-->/us', ' ', $content);
        $tags = [];

        if (preg_match_all('/#([\p{L}\p{N}_]+)/u', $stripped, $m)) {
            foreach ($m[1] as $w) {
                $tags[$this->lower($w)] = true;
            }
        }

        if (preg_match('/\n\n(.+)\z/us', $stripped, $blk)) {
            $tail = trim($blk[1]);
            if ('' !== $tail && $this->tailLooksLikeTagBlock($tail)) {
                foreach (preg_split('/[\s,;|]+/u', $tail, -1, \PREG_SPLIT_NO_EMPTY) as $token) {
                    $token = preg_replace('/^#+/u', '', trim($token));
                    if ('' !== $token && preg_match('/^[\p{L}\p{N}_]+$/u', $token)) {
                        $tags[$this->lower($token)] = true;
                    }
                }
            }
        }

        $lines = array_values(array_filter(
            preg_split('/\R+/u', trim($stripped)) ?: [],
            static fn (string $l): bool => '' !== trim($l)
        ));
        if ([] !== $lines) {
            $last = trim((string) $lines[array_key_last($lines)]);
            if ('' !== $last && preg_match('/^#?([\p{L}\p{N}_]+)$/u', $last, $lm)) {
                $tags[$this->lower($lm[1])] = true;
            }
        }

        if ([] === $tags) {
            return '';
        }

        $keys = array_keys($tags);
        sort($keys, \SORT_STRING);

        return '|'.implode('|', $keys).'|';
    }

    public function escapeLike(string $value): string
    {
        return str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $value);
    }

    private function tailLooksLikeTagBlock(string $tail): bool
    {
        if (mb_strlen($tail, 'UTF-8') > 600) {
            return false;
        }

        return !preg_match('/[.!?]["\']?\s+\p{L}/u', $tail);
    }

    private function lower(string $s): string
    {
        if (\function_exists('mb_strtolower')) {
            return mb_strtolower($s, 'UTF-8');
        }

        return strtolower($s);
    }
}
