<?php

namespace App\Service;

use App\Entity\Panier;
use Symfony\Component\DependencyInjection\Attribute\Autowire;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class TransportAdvisorService
{
    private const ORIGIN = ['city' => 'Tunis', 'lat' => 36.8065, 'lon' => 10.1815];
    private const CITY_COORDS = [
        'Tunis' => ['lat' => 36.8065, 'lon' => 10.1815],
        'Hammamet' => ['lat' => 36.4, 'lon' => 10.6167],
        'Sidi Bou Said' => ['lat' => 36.8692, 'lon' => 10.3497],
        'La Marsa' => ['lat' => 36.8783, 'lon' => 10.3250],
        'Djerba' => ['lat' => 33.8076, 'lon' => 10.8451],
        'Douz' => ['lat' => 33.45, 'lon' => 8.1167],
        'El Jem' => ['lat' => 35.2961, 'lon' => 10.7139],
        'Sousse' => ['lat' => 35.8256, 'lon' => 10.6369],
        'Gammarth' => ['lat' => 36.9167, 'lon' => 10.2833],
        'Carthage' => ['lat' => 36.8528, 'lon' => 10.3233],
    ];
    private const AIRPORT_CITIES = ['Djerba', 'Sfax', 'Tozeur', 'Tabarka', 'Monastir'];

    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly BookingCatalogService $catalogService,
        #[Autowire('%env(default::OPENTRIPMAP_API_KEY)%')]
        private readonly string $apiKey,
    ) {
    }

    /** @param Panier[] $paniers */
    public function adviseForCart(array $paniers): array
    {
        $advice = [];
        foreach ($paniers as $panier) {
            if (!$panier instanceof Panier) {
                continue;
            }
            $advice[$panier->getId() ?? spl_object_id($panier)] = $this->advise($panier);
        }

        return $advice;
    }

    public function advise(Panier $panier): array
    {
        $city = trim($panier->getDisplayCity() ?: $this->catalogService->resolveCity($panier) ?: 'Tunis');
        $coords = $this->resolveCityCoordinates($city);
        $distance = $this->haversine(self::ORIGIN['lat'], self::ORIGIN['lon'], $coords['lat'], $coords['lon']);
        $people = max(1, (int) ($panier->getNbPersonnes() ?? 1));

        $busPerPerson = max(7.0, round(2.5 + ($distance * 0.08), 2));
        $bus = [
            'mode' => 'Bus',
            'available' => $distance <= 550,
            'price_per_person' => $busPerPerson,
            'total_price' => round($busPerPerson * $people, 2),
            'duration_hours' => round(max(0.75, ($distance / 70) + 0.75), 1),
            'comfort' => $people >= 5 ? 'Moyen pour les groupes' : 'Bon budget',
        ];

        $planeAvailable = $distance >= 250 || in_array($city, self::AIRPORT_CITIES, true);
        $planePerPerson = round(85 + ($distance * 0.18), 2);
        $plane = [
            'mode' => 'Avion',
            'available' => $planeAvailable,
            'price_per_person' => $planePerPerson,
            'total_price' => round($planePerPerson * $people, 2),
            'duration_hours' => round(max(1.0, ($distance / 550) + 1.2), 1),
            'comfort' => 'Le plus rapide si la destination est éloignée',
        ];

        $options = array_values(array_filter([$bus, $plane], static fn (array $opt) => $opt['available']));
        if ($options === []) {
            $options[] = $bus;
        }

        usort($options, fn (array $a, array $b) => ($a['total_price'] <=> $b['total_price']) ?: ($a['duration_hours'] <=> $b['duration_hours']));
        $cheapest = $options[0];
        $fastest = array_reduce($options, function (?array $carry, array $item) {
            if ($carry === null || $item['duration_hours'] < $carry['duration_hours']) {
                return $item;
            }
            return $carry;
        });

        $recommended = $cheapest;
        $reason = 'Le meilleur rapport budget/groupe.';
        if ($people <= 2 && $fastest && ($fastest['duration_hours'] + 2) < $cheapest['duration_hours'] && ($fastest['total_price'] <= $cheapest['total_price'] * 1.8)) {
            $recommended = $fastest;
            $reason = 'Pour un petit groupe, le gain de temps justifie le supplément.';
        }

        return [
            'city' => $city,
            'distance_km' => round($distance, 1),
            'people' => $people,
            'options' => $options,
            'recommended' => $recommended,
            'reason' => $reason,
        ];
    }

    private function resolveCityCoordinates(string $city): array
    {
        if (isset(self::CITY_COORDS[$city])) {
            return self::CITY_COORDS[$city];
        }

        if (trim($this->apiKey) !== '') {
            try {
                $geo = $this->httpClient->request('GET', 'https://api.opentripmap.com/0.1/en/places/geoname', [
                    'query' => ['name' => $city, 'apikey' => $this->apiKey],
                    'timeout' => 8,
                ])->toArray(false);
                if (is_numeric($geo['lat'] ?? null) && is_numeric($geo['lon'] ?? null)) {
                    return ['lat' => (float) $geo['lat'], 'lon' => (float) $geo['lon']];
                }
            } catch (\Throwable) {
            }
        }

        return self::ORIGIN;
    }

    private function haversine(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $earthRadius = 6371.0;
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);
        $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLon / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
        return $earthRadius * $c;
    }
}
