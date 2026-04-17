<?php

declare(strict_types=1);

namespace App\Chat;

/**
 * Identifiant utilisateur réservé aux messages système (annonces admin).
 */
final class ChatAdmin
{
    public const BROADCAST_USER_KEY = 'Admin';

    private function __construct()
    {
    }
}
