<?php

namespace App\Security;

use App\Entity\Utilisateur;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Request;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;
use Symfony\Component\Security\Core\Authentication\Token\TokenInterface;
use Symfony\Component\Security\Core\Exception\CustomUserMessageAuthenticationException;
use Symfony\Component\Security\Core\User\PasswordAuthenticatedUserInterface;
use Symfony\Component\Security\Core\Security;
use Symfony\Component\Security\Http\Authenticator\AbstractLoginFormAuthenticator;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\CsrfTokenBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\RememberMeBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Badge\UserBadge;
use Symfony\Component\Security\Http\Authenticator\Passport\Credentials\CustomCredentials;
use Symfony\Component\Security\Http\Authenticator\Passport\Passport;
use Symfony\Component\Security\Http\Util\TargetPathTrait;
use Symfony\Component\PasswordHasher\Hasher\UserPasswordHasherInterface;

class LoginFormAuthenticator extends AbstractLoginFormAuthenticator
{
    use TargetPathTrait;

    public const LOGIN_ROUTE = 'app_login';

    public function __construct(
        private UrlGeneratorInterface $urlGenerator,
        private UserPasswordHasherInterface $passwordHasher
    )
    {
    }

    public function authenticate(Request $request): Passport
    {
        $identifier = trim((string) $request->request->get('identifier', ''));
        $password = (string) $request->request->get('password', '');
        $totpCode = trim((string) $request->request->get('totp_code', ''));
        $csrfToken = (string) $request->request->get('_csrf_token');

        $request->getSession()->set(Security::LAST_USERNAME, $identifier);

        return new Passport(
            new UserBadge($identifier),
            new CustomCredentials(function (mixed $credentials, PasswordAuthenticatedUserInterface $user): bool {
                if (!$this->passwordHasher->isPasswordValid($user, $credentials['password'])) {
                    throw new CustomUserMessageAuthenticationException('Invalid credentials.');
                }

                if ($user instanceof Utilisateur && $user->isTotpEnabled()) {
                    if ($user->getTotpSecret() === null || $user->getTotpSecret() === '') {
                        throw new CustomUserMessageAuthenticationException('Two-factor authentication is enabled, but the account has no configured secret. Contact support.');
                    }

                    if (empty($credentials['totp_code'])) {
                        throw new CustomUserMessageAuthenticationException('Enter your two-factor authentication code.');
                    }

                    if (!$this->isValidTotpCode($user->getTotpSecret(), (string) $credentials['totp_code'])) {
                        throw new CustomUserMessageAuthenticationException('Invalid two-factor authentication code.');
                    }
                }

                return true;
            }, [
                'password' => $password,
                'totp_code' => $totpCode,
            ]),
            [
                new CsrfTokenBadge('authenticate', $csrfToken),
                new RememberMeBadge(),
            ]
        );
    }

    public function onAuthenticationSuccess(Request $request, TokenInterface $token, string $firewallName): ?RedirectResponse
    {
        if (in_array('ROLE_ADMIN', $token->getRoleNames(), true)) {
            return new RedirectResponse($this->urlGenerator->generate('app_admin_dashboard'));
        }

        $targetPath = $this->getTargetPath($request->getSession(), $firewallName);
        if ($targetPath) {
            return new RedirectResponse($targetPath);
        }

        return new RedirectResponse($this->urlGenerator->generate('app_dashboard'));
    }

    protected function getLoginUrl(Request $request): string
    {
        return $this->urlGenerator->generate(self::LOGIN_ROUTE);
    }

    private function isValidTotpCode(string $secret, string $code, int $window = 1): bool
    {
        $normalizedCode = preg_replace('/\D+/', '', $code);
        if ($normalizedCode === null || strlen($normalizedCode) !== 6) {
            return false;
        }

        $counter = (int) floor(time() / 30);

        for ($offset = -$window; $offset <= $window; $offset++) {
            if (hash_equals($this->generateTotpCode($secret, $counter + $offset), $normalizedCode)) {
                return true;
            }
        }

        return false;
    }

    private function generateTotpCode(string $secret, int $counter): string
    {
        $secretKey = $this->base32Decode($secret);
        $binaryCounter = pack('N2', 0, $counter);
        $hash = hash_hmac('sha1', $binaryCounter, $secretKey, true);
        $offset = ord(substr($hash, -1)) & 0x0f;
        $value = unpack('N', substr($hash, $offset, 4))[1] & 0x7fffffff;

        return str_pad((string) ($value % 1000000), 6, '0', STR_PAD_LEFT);
    }

    private function base32Decode(string $value): string
    {
        $alphabet = 'ABCDEFGHIJKLMNOPQRSTUVWXYZ234567';
        $value = strtoupper(preg_replace('/[^A-Z2-7]/', '', $value) ?? '');

        $bits = '';
        foreach (str_split($value) as $character) {
            $position = strpos($alphabet, $character);
            if ($position === false) {
                continue;
            }

            $bits .= str_pad(decbin($position), 5, '0', STR_PAD_LEFT);
        }

        $decoded = '';
        foreach (str_split($bits, 8) as $chunk) {
            if (strlen($chunk) === 8) {
                $decoded .= chr(bindec($chunk));
            }
        }

        return $decoded;
    }
}
