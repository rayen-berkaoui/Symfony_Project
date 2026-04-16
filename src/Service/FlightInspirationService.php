<?php

namespace App\Service;

use App\Entity\Panier;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class FlightInspirationService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        #[Autowire('%env(default::OPENTRIPMAP_API_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    public function isConfigured(): bool
    {
        return trim($this->apiKey) !== '';
    }

    /** @param Panier[] $paniers */
    public function getSuggestions(array $paniers): array
    {
        if (!$this->isConfigured()) {
            return [];
        }

        $city = 'Tunis';
        foreach ($paniers as $panier) {
            if ($panier instanceof Panier && $panier->getDisplayCity()) {
                $city = $panier->getDisplayCity();
                break;
            }
        }

        try {
            $geo = $this->httpClient->request('GET', 'https://api.opentripmap.com/0.1/en/places/geoname', [
                'query' => [
                    'name' => $city,
                    'apikey' => $this->apiKey,
                ],
            ])->toArray(false);

            $lat = $geo['lat'] ?? null;
            $lon = $geo['lon'] ?? null;
            if (!is_numeric($lat) || !is_numeric($lon)) {
                return [];
            }

            $response = $this->httpClient->request('GET', 'https://api.opentripmap.com/0.1/en/places/radius', [
                'query' => [
                    'apikey' => $this->apiKey,
                    'radius' => 15000,
                    'lon' => $lon,
                    'lat' => $lat,
                    'limit' => 4,
                    'rate' => 2,
                    'format' => 'json',
                ],
            ]);
            $data = $response->toArray(false);

            $rows = [];
            foreach ($data as $item) {
                if (!is_array($item) || empty($item['name'])) {
                    continue;
                }
                $rows[] = [
                    'name' => (string) $item['name'],
                    'kinds' => str_replace(',', ', ', (string) ($item['kinds'] ?? 'point of interest')),
                    'rate' => isset($item['rate']) ? (float) $item['rate'] : null,
                    'distance' => isset($item['dist']) ? (int) round((float) $item['dist']) : null,
                ];
                if (count($rows) >= 4) {
                    break;
                }
            }

            return $rows;
        } catch (\Throwable) {
            return [];
        }
    }
}
