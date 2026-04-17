<?php

declare(strict_types=1);

namespace App\Api;

use Symfony\Component\HttpFoundation\JsonResponse;

/**
 * Enveloppe JSON stable pour l’API REST (versionnée) — prête pour la soutenance / clients.
 */
final class ApiJsonResponse
{
    public const VERSION = '1.0';

    /**
     * @param array<string, mixed> $data
     */
    public static function success(array $data, int $status = 200): JsonResponse
    {
        return new JsonResponse([
            'success' => true,
            'data' => $data,
            'meta' => [
                'api_version' => self::VERSION,
            ],
        ], $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }

    /**
     * @param array<string, mixed> $extra
     */
    public static function error(string $code, string $message, int $status = 400, array $extra = []): JsonResponse
    {
        return new JsonResponse([
            'success' => false,
            'error' => array_merge(
                [
                    'code' => $code,
                    'message' => $message,
                ],
                $extra
            ),
            'meta' => [
                'api_version' => self::VERSION,
            ],
        ], $status, ['Content-Type' => 'application/json; charset=UTF-8']);
    }
}
