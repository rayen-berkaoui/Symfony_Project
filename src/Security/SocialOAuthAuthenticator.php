<?php

namespace App\Security;

use App\Entity\Utilisateur;
use App\Repository\RoleRepository;
use App\Repository\UtilisateurRepository;
use Doctrine\ORM\EntityManagerInterface;
use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use KnpU\OAuth2ClientBundle\Security\Authenticator\OAuth2Authenticator;
use League\OAuth2\Client\Provider\ResourceOwnerInterface;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\AuthenticationException;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Authenticator\Passport\SelfValidatingPassport;
use Symfony\Component\String\UnicodeString;

class SocialOAuthAuthenticator extends OAuth2Authenticator
{
    private const ROUTE_PROVIDER_MAP = [
        'app_connect_google_check' => 'google',
        'app_connect_github_check' => 'github',
    ];

    private const PROVIDER_CLIENTS = [
        'google' => 'google_main',
        'github' => 'github_main',
    ];

    public function __construct(
        private readonly ClientRegistry $clientRegistry,
        private readonly UrlGeneratorInterface $urlGenerator,
        private readonly UtilisateurRepository $utilisateurRepository,
        private readonly RoleRepository $roleRepository,
        private readonly EntityManagerInterface $entityManager,
        private readonly UserPasswordHasherInterface $passwordHasher
    ) {
    }

    public function supports(Request $request): ?bool
    {
        return isset(self::ROUTE_PROVIDER_MAP[(string) $request->attributes->get('_route', '')]);
    }

    public function authenticate(Request $request): Passport
    {
        $route = (string) $request->attributes->get('_route', '');
        $provider = self::ROUTE_PROVIDER_MAP[$route] ?? '';
        $clientName = self::PROVIDER_CLIENTS[$provider] ?? null;

        if ($clientName === null) {
            throw new CustomUserMessageAuthenticationException('Unsupported OAuth provider.');
        }

        $client = $this->clientRegistry->getClient($clientName);
        $accessToken = $this->fetchAccessToken($client);

        return new SelfValidatingPassport(new UserBadge($accessToken->getToken(), function () use ($client, $accessToken, $provider): Utilisateur {
            $oauthUser = $client->fetchUserFromToken($accessToken);
            $email = $this->extractEmail($oauthUser);
            $oauthId = $this->extractOauthId($oauthUser);

            if ($email === null) {
                throw new CustomUserMessageAuthenticationException('Your social account did not provide an email address. Please use a different provider or your password login.');
            }

            if ($oauthId === null) {
                throw new CustomUserMessageAuthenticationException('Unable to identify your social account. Please try again.');
            }

            $existingOauthUser = $this->utilisateurRepository->findOneByOauthAccount($provider, $oauthId);
            if ($existingOauthUser instanceof Utilisateur) {
                return $existingOauthUser;
            }

            $userByEmail = $this->utilisateurRepository->findByEmail($email);
            if ($userByEmail instanceof Utilisateur) {
                $userByEmail
                    ->setOauthProvider($provider)
                    ->setOauthId($oauthId);

                $this->entityManager->flush();

                return $userByEmail;
            }

            return $this->createNewSocialUser($oauthUser, $provider, $oauthId, $email);
        }));
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?Response
    {
        $user = $token->getUser();
        if ($user instanceof Utilisateur && $user->needsPhoneUpdate()) {
            $request->getSession()->getFlashBag()->add('warning', 'Please add your real phone number to complete your account setup.');

            return new RedirectResponse($this->urlGenerator->generate('app_profile', ['phone_required' => 1]));
        }

        if (in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    public function onAuthenticationFailure(Request $request, AuthenticationException $exception): ?Response
    {
        $request->getSession()->getFlashBag()->add('error', 'Social login failed: ' . $exception->getMessageKey());

        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    public function start(Request $request, AuthenticationException $authException = null): Response
    {
        return new RedirectResponse($this->urlGenerator->generate('app_login'));
    }

    private function createNewSocialUser(
        ResourceOwnerInterface $oauthUser,
        string $provider,
        string $oauthId,
        string $email
    ): Utilisateur {
        [$nom, $prenom] = $this->extractNameParts($oauthUser, $provider);

        $defaultRole = $this->roleRepository->findOneBy(['nom' => 'TOURISTE'])
            ?? $this->roleRepository->findOneBy([], ['id' => 'ASC']);

        if ($defaultRole === null) {
            throw new CustomUserMessageAuthenticationException('No default role is configured for new users.');
        }

        $user = new Utilisateur();
        $user
            ->setEmail($email)
            ->setNom($nom)
            ->setPrenom($prenom)
            ->setNumTel(null)
            ->setNeedsPhoneUpdate(true)
            ->setRole($defaultRole)
            ->setStatut(Utilisateur::STATUT_ACTIF)
            ->setOauthProvider($provider)
            ->setOauthId($oauthId);

        $randomPassword = bin2hex(random_bytes(24));
        $user->setMotDePasse($this->passwordHasher->hashPassword($user, $randomPassword));

        $this->entityManager->persist($user);
        $this->entityManager->flush();

        return $user;
    }

    private function extractEmail(ResourceOwnerInterface $oauthUser): ?string
    {
        $email = null;

        if (method_exists($oauthUser, 'getEmail')) {
            $email = $oauthUser->getEmail();
        }

        if (!$email && method_exists($oauthUser, 'toArray')) {
            $raw = $oauthUser->toArray();
            $email = $raw['email'] ?? null;
        }

        if (!is_string($email) || $email === '') {
            return null;
        }

        return mb_strtolower(trim($email));
    }

    private function extractOauthId(ResourceOwnerInterface $oauthUser): ?string
    {
        $oauthId = null;

        if (method_exists($oauthUser, 'getId')) {
            $oauthId = $oauthUser->getId();
        }

        if (!$oauthId && method_exists($oauthUser, 'toArray')) {
            $raw = $oauthUser->toArray();
            $oauthId = $raw['id'] ?? null;
        }

        if (!is_scalar($oauthId) || (string) $oauthId === '') {
            return null;
        }

        return (string) $oauthId;
    }

    private function extractNameParts(ResourceOwnerInterface $oauthUser, string $provider): array
    {
        $fullName = null;

        if (method_exists($oauthUser, 'getName')) {
            $fullName = $oauthUser->getName();
        }

        if ((!is_string($fullName) || trim($fullName) === '') && method_exists($oauthUser, 'toArray')) {
            $raw = $oauthUser->toArray();
            $fullName = $raw['name'] ?? $raw['login'] ?? null;
        }

        if (!is_string($fullName) || trim($fullName) === '') {
            $fullName = ucfirst($provider) . ' User';
        }

        $parts = preg_split('/\s+/', trim($fullName)) ?: [];
        $firstName = array_shift($parts) ?: ucfirst($provider);
        $lastName = implode(' ', $parts);

        if ($lastName === '') {
            $lastName = 'User';
        }

        // Keep names within DB constraints.
        $safeNom = (new UnicodeString($lastName))->truncate(100)->toString();
        $safePrenom = (new UnicodeString($firstName))->truncate(100)->toString();

        return [$safeNom, $safePrenom];
    }

}
