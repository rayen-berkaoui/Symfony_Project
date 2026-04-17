<?php

declare(strict_types=1);

namespace App\Twig;

use Twig\Extension\AbstractExtension;
use Twig\TwigFilter;

/**
 * Couleur de pseudo stable par utilisateur (HSL sur fond sombre).
 */
final class ChatUserColorExtension extends AbstractExtension
{
    public function getFilters(): array
    {
        return [
            new TwigFilter('chat_user_hue', [$this, 'hueFromUserKey']),
        ];
    }

    /**
     * Teinte 0–359 dérivée du pseudo (même pseudo ⇒ même couleur).
     */
    public function hueFromUserKey(string $userKey): int
    {
        $userKey = trim($userKey);
        if ('' === $userKey) {
            return 48;
        }

        $bin = hash('sha256', $userKey, true);
        $words = unpack('N', substr($bin, 0, 4));
        $n = (int) (($words[1] ?? 0));

        return $n % 360;
    }
}
