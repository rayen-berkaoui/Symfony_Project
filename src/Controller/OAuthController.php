<?php

namespace App\Controller;

use KnpU\OAuth2ClientBundle\Client\ClientRegistry;
use Symfony\Bundle\FrameworkBundle\Controller\AbstractController;
use Symfony\Component\HttpFoundation\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;
use Symfony\Component\Routing\Attribute\Route;
use Symfony\Component\Routing\Generator\UrlGeneratorInterface;

class OAuthController extends AbstractController
{
    #[Route('/connect/google', name: 'app_connect_google', methods: ['GET'])]
    public function connectGoogle(ClientRegistry $clientRegistry, UrlGeneratorInterface $urlGenerator): RedirectResponse
    {
        return $clientRegistry
            ->getClient('google_main')
            ->redirect(
                ['openid', 'profile', 'email'],
                ['redirect_uri' => $urlGenerator->generate('app_connect_google_check', [], UrlGeneratorInterface::ABSOLUTE_URL)]
            );
    }

    #[Route('/connect/github', name: 'app_connect_github', methods: ['GET'])]
    public function connectGithub(ClientRegistry $clientRegistry, UrlGeneratorInterface $urlGenerator): RedirectResponse
    {
        return $clientRegistry
            ->getClient('github_main')
            ->redirect(
                ['user:email'],
                ['redirect_uri' => $urlGenerator->generate('app_connect_github_check', [], UrlGeneratorInterface::ABSOLUTE_URL)]
            );
    }

    #[Route('/connect/google/check', name: 'app_connect_google_check', host: 'localhost', methods: ['GET'])]
    public function connectGoogleCheck(): Response
    {
        throw new \LogicException('This route is handled by the SocialOAuthAuthenticator.');
    }

    #[Route('/connect/github/check', name: 'app_connect_github_check', host: 'localhost', methods: ['GET'])]
    public function connectGithubCheck(): Response
    {
        throw new \LogicException('This route is handled by the SocialOAuthAuthenticator.');
    }
}
