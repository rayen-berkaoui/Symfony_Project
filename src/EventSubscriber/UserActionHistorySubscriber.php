<?php

namespace App\EventSubscriber;

use App\Entity\HistoriqueAction;
use App\Entity\Utilisateur;
use Doctrine\ORM\EntityManagerInterface;
use Symfony\Component\EventDispatcher\EventSubscriberInterface;
use Symfony\Component\HttpKernel\Event\TerminateEvent;
use Symfony\Component\HttpKernel\KernelEvents;
use Symfony\Component\Security\Core\Authentication\Token\Storage\TokenStorageInterface;

class UserActionHistorySubscriber implements EventSubscriberInterface
{
    private const EXCLUDED_ROUTE_PREFIXES = ['_wdt', '_profiler'];

    public function __construct(
        private readonly TokenStorageInterface $tokenStorage,
        private readonly EntityManagerInterface $entityManager,
    ) {
    }

    public static function getSubscribedEvents(): array
    {
        return [
            KernelEvents::TERMINATE => 'onKernelTerminate',
        ];
    }

    public function onKernelTerminate(TerminateEvent $event): void
    {
        if (!$event->isMainRequest()) {
            return;
        }

        $request = $event->getRequest();
        $routeName = (string) ($request->attributes->get('_route') ?? '');

        if ($routeName === '' || $this->isExcludedRoute($routeName)) {
            return;
        }

        $token = $this->tokenStorage->getToken();
        $user = $token?->getUser();
        if (!$user instanceof Utilisateur) {
            return;
        }

        $method = strtoupper($request->getMethod());
        $pathInfo = $request->getPathInfo();
        $routeParameters = $this->extractRouteParameters($request->attributes->all());

        $history = (new HistoriqueAction())
            ->setUtilisateur($user)
            ->setActionLabel($this->buildActionLabel($routeName, $method, $routeParameters))
            ->setRouteName($routeName)
            ->setHttpMethod($method)
            ->setPathInfo($pathInfo)
            ->setStatusCode($event->getResponse()->getStatusCode())
            ->setDetails($this->buildDetails($routeParameters, $request->query->all()));

        try {
            if (!$this->entityManager->isOpen()) {
                return;
            }

            $this->entityManager->persist($history);
            $this->entityManager->flush();
        } catch (\Throwable) {
            // Do not block the user flow if history logging fails.
        }
    }

    private function isExcludedRoute(string $routeName): bool
    {
        foreach (self::EXCLUDED_ROUTE_PREFIXES as $prefix) {
            if (str_starts_with($routeName, $prefix)) {
                return true;
            }
        }

        return false;
    }

    /**
     * @param array<string, mixed> $attributes
     *
     * @return array<string, scalar|null>
     */
    private function extractRouteParameters(array $attributes): array
    {
        $result = [];

        foreach ($attributes as $key => $value) {
            if (str_starts_with((string) $key, '_')) {
                continue;
            }

            if (is_scalar($value) || $value === null) {
                $result[(string) $key] = $value;
            }
        }

        return $result;
    }

    /**
     * @param array<string, scalar|null> $routeParameters
     */
    private function buildActionLabel(string $routeName, string $method, array $routeParameters): string
    {
        if ($routeName === 'app_login' && $method === 'POST') {
            return 'Connexion utilisateur';
        }

        if ($routeName === 'app_dashboard') {
            return 'Consultation vue d ensemble';
        }

        $resource = $this->extractResourceName($routeName);
        $entityId = $this->extractEntityId($routeParameters);

        if (str_contains($routeName, '_new') && $method === 'POST') {
            return sprintf('Creation %s', $resource);
        }

        if (str_contains($routeName, '_edit') && in_array($method, ['POST', 'PUT', 'PATCH'], true)) {
            return sprintf('Modification %s %s', $resource, $entityId);
        }

        if (str_contains($routeName, '_delete') && $method === 'POST') {
            return sprintf('Suppression %s %s', $resource, $entityId);
        }

        if (str_contains($routeName, '_show') && $method === 'GET') {
            return sprintf('Consultation details %s %s', $resource, $entityId);
        }

        if (str_contains($routeName, '_index') && $method === 'GET') {
            return sprintf('Consultation liste %s', $resource);
        }

        return sprintf('%s %s', $method, str_replace('_', ' ', $routeName));
    }

    private function extractResourceName(string $routeName): string
    {
        $normalized = preg_replace('/^(app_|utilisateur_)/', '', $routeName) ?? $routeName;
        $normalized = preg_replace('/_(index|new|show|edit|delete|pdf|excel|dashboard|toggle_status|duplicate)$/', '', $normalized) ?? $normalized;
        $normalized = str_replace('_', ' ', $normalized);

        return trim($normalized) !== '' ? trim($normalized) : 'element';
    }

    /**
     * @param array<string, scalar|null> $routeParameters
     */
    private function extractEntityId(array $routeParameters): string
    {
        foreach (['id', 'idEtablissement', 'idActivite'] as $key) {
            if (array_key_exists($key, $routeParameters) && $routeParameters[$key] !== null) {
                return sprintf('#%s', (string) $routeParameters[$key]);
            }
        }

        return '';
    }

    /**
     * @param array<string, scalar|null> $routeParameters
     * @param array<string, mixed>        $queryParameters
     */
    private function buildDetails(array $routeParameters, array $queryParameters): ?string
    {
        $payload = [];

        if ($routeParameters !== []) {
            $payload['route_params'] = $routeParameters;
        }

        if ($queryParameters !== []) {
            $payload['query_params'] = $queryParameters;
        }

        if ($payload === []) {
            return null;
        }

        $encoded = json_encode($payload, JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES);

        return $encoded === false ? null : $encoded;
    }
}