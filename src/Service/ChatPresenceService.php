<?php

declare(strict_types=1);

namespace App\Service;

final class ChatPresenceService
{
    private const STORE_PATH = '/var/chat_presence_state.json';
    private const ACTIVE_TTL_SECONDS = 90;

    public function __construct(
        private readonly string $projectDir,
    ) {
    }

    public function touchUser(string $userKey): void
    {
        $key = $this->normalize($userKey);
        if ('' === $key) {
            return;
        }

        $this->withStore(function (array $state) use ($key): array {
            $state = $this->prune($state);
            $state['users'][$key] = time();

            return $state;
        });
    }

    public function leaveUser(string $userKey): void
    {
        $key = $this->normalize($userKey);
        if ('' === $key) {
            return;
        }

        $this->withStore(function (array $state) use ($key): array {
            $state = $this->prune($state);
            unset($state['users'][$key]);

            return $state;
        });
    }

    public function countActiveUsers(): int
    {
        $state = $this->withStore(function (array $state): array {
            return $this->prune($state);
        });

        return \count($state['users']);
    }

    public function resetPublicTimelineNow(): void
    {
        $this->withStore(function (array $state): array {
            $state = $this->prune($state);
            $state['public_cutoff'] = time();

            return $state;
        });
    }

    public function getPublicTimelineCutoff(): ?\DateTimeImmutable
    {
        $state = $this->withStore(function (array $state): array {
            return $this->prune($state);
        });

        $ts = (int) ($state['public_cutoff'] ?? 0);
        if ($ts <= 0) {
            return null;
        }

        return (new \DateTimeImmutable())->setTimestamp($ts);
    }

    private function normalize(string $userKey): string
    {
        return mb_strtolower(trim($userKey), 'UTF-8');
    }

    private function prune(array $state): array
    {
        $users = \is_array($state['users'] ?? null) ? $state['users'] : [];
        $now = time();
        foreach ($users as $key => $seenAt) {
            if (!\is_int($seenAt) || $seenAt < ($now - self::ACTIVE_TTL_SECONDS)) {
                unset($users[$key]);
            }
        }

        return [
            'users' => $users,
            'public_cutoff' => (int) ($state['public_cutoff'] ?? 0),
        ];
    }

    private function withStore(\Closure $mutator): array
    {
        $path = $this->projectDir.self::STORE_PATH;
        $dir = \dirname($path);
        if (!is_dir($dir)) {
            @mkdir($dir, 0777, true);
        }

        $fp = @fopen($path, 'c+');
        if (false === $fp) {
            return ['users' => [], 'public_cutoff' => 0];
        }

        try {
            @flock($fp, \LOCK_EX);
            $raw = stream_get_contents($fp);
            $decoded = \is_string($raw) && '' !== trim($raw) ? json_decode($raw, true) : [];
            $state = \is_array($decoded) ? $decoded : [];
            $next = $mutator($state);
            $next = $this->prune(\is_array($next) ? $next : []);

            ftruncate($fp, 0);
            rewind($fp);
            fwrite($fp, json_encode($next, \JSON_THROW_ON_ERROR));
            fflush($fp);
            @flock($fp, \LOCK_UN);

            return $next;
        } finally {
            fclose($fp);
        }
    }
}

