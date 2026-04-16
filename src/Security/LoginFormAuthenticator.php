<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\SecurityRequestAttributes;
use Symfony\Component\Security\Http\Util\TargetPathTrait;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserPasswordHasherInterface $passwordHasher,
        private EntityManagerInterface $entityManager,
    ) {
    }

    public function authenticate(Request $request): Passport
    {
        $identifier = $request->request->get('identifier', '');
        $password = $request->request->get('password', '');
        $csrfToken = $request->request->get('_csrf_token');

        if ($request->getContentTypeFormat() === 'json') {
            $data = json_decode($request->getContent(), true);
            $identifier = $data['identifier'] ?? '';
            $password = $data['password'] ?? '';
            $csrfToken = $data['_csrf_token'] ?? '';
        } elseif ($request->getPayload() && $request->getPayload()->has('_csrf_token')) {
            $identifier = $request->getPayload()->getString('identifier');
            $password = $request->getPayload()->getString('password');
            $csrfToken = $request->getPayload()->getString('_csrf_token');
        }

        $identifier = trim((string) $identifier);
        $request->getSession()->set(SecurityRequestAttributes::LAST_USERNAME, $identifier);

        return new Passport(
            new UserBadge($identifier),
            new CustomCredentials(function (string $credentials, $user): bool {
                if (!$user instanceof Utilisateur) {
                    throw new CustomUserMessageAuthenticationException('Invalid credentials.');
                }

                $credentials = trim($credentials);
                $stored = trim((string) $user->getPassword());

                if ($stored === '' || $credentials == '') {
                    throw new CustomUserMessageAuthenticationException('Invalid credentials.');
                }

                $isHashed = str_starts_with($stored, '$2y$')
                    || str_starts_with($stored, '$argon2')
                    || str_starts_with($stored, '$2a$')
                    || str_starts_with($stored, '$2b$');

                if ($isHashed) {
                    if (!$this->passwordHasher->isPasswordValid($user, $credentials)) {
                        throw new CustomUserMessageAuthenticationException('Invalid credentials.');
                    }

                    return true;
                }

                $legacyMatches = hash_equals($stored, $credentials)
                    || hash_equals(mb_strtolower($stored), mb_strtolower($credentials));

                if (!$legacyMatches) {
                    throw new CustomUserMessageAuthenticationException('Invalid credentials.');
                }

                $user->setPassword($this->passwordHasher->hashPassword($user, $credentials));
                $this->entityManager->persist($user);
                $this->entityManager->flush();

                return true;
            }, (string) $password),
            [
                new CsrfTokenBadge('authenticate', (string) $csrfToken),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        if ($targetPath = $this->getTargetPath($request->getSession(), $firewallName)) {
            return new RedirectResponse($targetPath);
        }

        $user = $token->getUser();
        if ($user instanceof Utilisateur) {
            $stored = (string) $user->getPassword();
            $isHashed = str_starts_with($stored, '$2y$') || str_starts_with($stored, '$argon2') || str_starts_with($stored, '$2a$') || str_starts_with($stored, '$2b$');
            if ($isHashed) {
                // already modern hash
            } else {
                $this->entityManager->persist($user);
                $this->entityManager->flush();
            }
        }
        if ($user instanceof Utilisateur && in_array('ROLE_ADMIN', $user->getRoles(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_user_accueil'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }
}
