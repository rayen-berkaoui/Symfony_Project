<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Repository\UtilisateurRepository;
use Symfony\Component\Security\Core\Exception\UnsupportedUserException;
use Symfony\Component\Security\Core\Exception\UserNotFoundException;
use Symfony\Component\Security\Core\User\UserInterface;
use Symfony\Component\Security\Core\User\UserProviderInterface;

class UtilisateurProvider implements UserProviderInterface
{
    public function __construct(private UtilisateurRepository $repository)
    {
    }

    public function loadUserByIdentifier(string $identifier): UserInterface
    {
        $user = $this->repository->findByEmailOrNumTel($identifier);

        if (!$user) {
            $exception = new UserNotFoundException(sprintf('User "%s" not found.', $identifier));
            $exception->setUserIdentifier($identifier);
            throw $exception;
        }

        return $user;
    }

    public function refreshUser(UserInterface $user): UserInterface
    {
        if (!$user instanceof Utilisateur) {
            throw new UnsupportedUserException(sprintf('Invalid user class "%s".', $user::class));
        }

        $reloaded = $this->repository->find($user->getId());

        if (!$reloaded) {
            throw new UserNotFoundException('User not found.');
        }

        return $reloaded;
    }

    public function supportsClass(string $class): bool
    {
        return $class === Utilisateur::class;
    }
}
