<?php

namespace App\Service;

use App\Entity\Panier;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class PlacesReviewService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly BookingCatalogService $catalogService,
        #[Autowire('%env(default::OPENTRIPMAP_API_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        $key = trim($this->apiKey);
        return $key !== '' && !str_starts_with($key, 'METTEZ_');
    }

    /** @param Panier[] $paniers */
    public function enrich(array $paniers): array
    {
        $results = [];
        foreach ($paniers as $panier) {
            if (!$panier instanceof Panier) {
                continue;
            }
            $results[$panier->getId() ?? spl_object_id($panier)] = $this->lookupPanier($panier);
        }

        return $results;
    }

    private function lookupPanier(Panier $panier): array
    {
        $fallback = [
            'name' => $panier->getDisplayName(),
            'rating' => null,
            'user_rating_count' => null,
            'summary' => 'Ajoutez OPENTRIPMAP_API_KEY pour récupérer un résumé touristique gratuit.',
            'maps_url' => null,
            'configured' => $this->isConfigured(),
        ];

        if (!$this->isConfigured()) {
            return $fallback;
        }

        $name = trim($panier->getDisplayName());
        $city = trim($panier->getDisplayCity() ?: $this->catalogService->resolveCity($panier) ?: 'Tunisia');

        try {
            $xid = $this->findByName($name) ?: $this->findByRadius($city, $name);
            if (!$xid) {
                return $fallback;
            }

            $details = $this->httpClient->request('GET', 'https://api.opentripmap.com/0.1/en/places/xid/' . rawurlencode($xid), [
                'query' => ['apikey' => $this->apiKey],
                'timeout' => 8,
            ])->toArray(false);

            return [
                'name' => (string) ($details['name'] ?? $name),
                'rating' => isset($details['rate']) ? (float) $details['rate'] : null,
                'user_rating_count' => null,
                'summary' => (string) ($details['wikipedia_extracts']['text'] ?? $details['info']['descr'] ?? 'Résumé touristique OpenTripMap.'),
                'maps_url' => $details['otm'] ?? null,
                'configured' => true,
            ];
        } catch (\Throwable) {
            return $fallback;
        }
    }

    private function findByName(string $name): ?string
    {
        if ($name === '') {
            return null;
        }

        try {
            $geo = $this->httpClient->request('GET', 'https://api.opentripmap.com/0.1/en/places/geoname', [
                'query' => [
                    'name' => $name,
                    'apikey' => $this->apiKey,
                ],
                'timeout' => 8,
            ])->toArray(false);

            $xid = $geo['xid'] ?? null;
            return is_string($xid) && $xid !== '' ? $xid : null;
        } catch (\Throwable) {
            return null;
        }
    }

    private function findByRadius(string $city, string $name): ?string
    {
        try {
            $geo = $this->httpClient->request('GET', 'https://api.opentripmap.com/0.1/en/places/geoname', [
                'query' => [
                    'name' => $city,
                    'apikey' => $this->apiKey,
                ],
                'timeout' => 8,
            ])->toArray(false);
            $lat = $geo['lat'] ?? null;
            $lon = $geo['lon'] ?? null;
            if (!is_numeric($lat) || !is_numeric($lon)) {
                return null;
            }

            $rows = $this->httpClient->request('GET', 'https://api.opentripmap.com/0.1/en/places/radius', [
                'query' => [
                    'apikey' => $this->apiKey,
                    'radius' => 15000,
                    'lon' => $lon,
                    'lat' => $lat,
                    'limit' => 12,
                    'format' => 'json',
                ],
                'timeout' => 8,
            ])->toArray(false);

            $needle = mb_strtolower($name);
            foreach ($rows as $item) {
                if (!is_array($item) || empty($item['name']) || empty($item['xid'])) {
                    continue;
                }
                if (str_contains(mb_strtolower((string) $item['name']), $needle)) {
                    return (string) $item['xid'];
                }
            }
        } catch (\Throwable) {
        }

        return null;
    }
}
