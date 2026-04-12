<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAccountStatusException;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\User\UserCheckerInterface;
use Symfony\Component\Security\Core\User\UserInterface;

class UserStatusChecker implements UserCheckerInterface
{
    public function checkPreAuth(UserInterface $user): void
    {
        $this->assertActive($user);
    }

    public function checkPostAuth(UserInterface $user): void
    {
        $this->assertActive($user);
    }

    private function assertActive(UserInterface $user): void
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        if ($user->getStatut() === Utilisateur::STATUT_BLOQUE) {
            throw new CustomUserMessageAccountStatusException('Your account is blocked. Contact an administrator.');
        }
    }
}