<?php

namespace App\Service;

use App\Entity\Panier;
use Symfony\Contracts\HttpClient\HttpClientInterface;

class WeatherAdvisorService
{
    public function __construct(
        private readonly HttpClientInterface $httpClient,
        private readonly BookingCatalogService $catalogService,
    ) {
    }

    public function buildAdvisories(array $paniers): array
    {
        $advisories = [];

        foreach ($paniers as $panier) {
            if (!$panier instanceof Panier || !$panier->getDateDebut()) {
                continue;
            }

            $coordinates = $this->resolveCoordinates($panier);
            if (!$coordinates) {
                $advisories[] = [
                    'service' => $panier->getDisplayName(),
                    'city' => $panier->getDisplayCity() ?: 'Localisation inconnue',
                    'date' => $panier->getDateDebut()->format('Y-m-d'),
                    'verdict' => 'inconnu',
                    'label' => 'Données météo indisponibles',
                    'reason' => "Aucune coordonnée fiable n'a pu être trouvée pour ce service.",
                ];
                continue;
            }

            $forecast = $this->fetchForecast($coordinates['latitude'], $coordinates['longitude'], $panier->getDateDebut());
            if (!$forecast) {
                $advisories[] = [
                    'service' => $panier->getDisplayName(),
                    'city' => $coordinates['city'],
                    'date' => $panier->getDateDebut()->format('Y-m-d'),
                    'verdict' => 'inconnu',
                    'label' => 'Prévision indisponible',
                    'reason' => "La météo n'est pas encore disponible pour cette date ou cette destination.",
                ];
                continue;
            }

            $precip = (float) ($forecast['precipitation_probability_max'] ?? 0);
            $weatherCode = (int) ($forecast['weather_code'] ?? -1);
            $tempMax = isset($forecast['temperature_2m_max']) ? (float) $forecast['temperature_2m_max'] : null;
            $tempMin = isset($forecast['temperature_2m_min']) ? (float) $forecast['temperature_2m_min'] : null;

            $verdict = 'go';
            $label = 'Conditions favorables';
            $reason = 'La météo paraît adaptée à cette sortie.';

            if ($weatherCode >= 95 || $precip >= 75) {
                $verdict = 'avoid';
                $label = 'Déconseillé';
                $reason = "Risque fort d'orages ou de fortes précipitations sur le créneau prévu.";
            } elseif (($weatherCode >= 45 && $weatherCode <= 48) || ($weatherCode >= 51 && $weatherCode <= 82) || $precip >= 45) {
                $verdict = 'caution';
                $label = 'À confirmer';
                $reason = 'Des conditions humides, brumeuses ou incertaines sont attendues.';
            }

            $advisories[] = [
                'service' => $panier->getDisplayName(),
                'city' => $coordinates['city'],
                'date' => $panier->getDateDebut()->format('Y-m-d'),
                'verdict' => $verdict,
                'label' => $label,
                'reason' => $reason,
                'weather_code' => $weatherCode,
                'temperature_max' => $tempMax,
                'temperature_min' => $tempMin,
                'precipitation_probability_max' => $precip,
            ];
        }

        return $advisories;
    }

    private function resolveCoordinates(Panier $panier): ?array
    {
        $city = $panier->getDisplayCity() ?: $this->catalogService->resolveCity($panier) ?: 'Tunisie';

        $lieu = $this->catalogService->resolveLieu($panier);
        if ($lieu?->getAdresse()?->getLatitude() !== null && $lieu?->getAdresse()?->getLongitude() !== null) {
            return [
                'latitude' => (float) $lieu->getAdresse()->getLatitude(),
                'longitude' => (float) $lieu->getAdresse()->getLongitude(),
                'city' => $city,
            ];
        }

        $etablissement = $this->catalogService->resolveEtablissement($panier);
        if ($etablissement?->getLatitude() !== null && $etablissement?->getLongitude() !== null) {
            return [
                'latitude' => (float) $etablissement->getLatitude(),
                'longitude' => (float) $etablissement->getLongitude(),
                'city' => $city,
            ];
        }

        return $this->geocodeCity($city);
    }

    private function geocodeCity(string $city): ?array
    {
        try {
            $response = $this->httpClient->request('GET', 'https://geocoding-api.open-meteo.com/v1/search', [
                'query' => [
                    'name' => $city,
                    'count' => 1,
                    'language' => 'fr',
                    'format' => 'json',
                ],
            ]);

            $data = $response->toArray(false);
            if (!isset($data['results'][0]['latitude'], $data['results'][0]['longitude'])) {
                return null;
            }

            return [
                'latitude' => (float) $data['results'][0]['latitude'],
                'longitude' => (float) $data['results'][0]['longitude'],
                'city' => (string) ($data['results'][0]['name'] ?? $city),
            ];
        } catch (\Throwable) {
            return null;
        }
    }

    private function fetchForecast(float $latitude, float $longitude, \DateTimeInterface $date): ?array
    {
        $today = new \DateTimeImmutable('today');
        $targetDate = \DateTimeImmutable::createFromInterface($date)->setTime(0, 0);
        if ($targetDate < $today || $targetDate > $today->modify('+15 days')) {
            return null;
        }

        try {
            $response = $this->httpClient->request('GET', 'https://api.open-meteo.com/v1/forecast', [
                'query' => [
                    'latitude' => $latitude,
                    'longitude' => $longitude,
                    'timezone' => 'auto',
                    'start_date' => $targetDate->format('Y-m-d'),
                    'end_date' => $targetDate->format('Y-m-d'),
                    'daily' => 'weather_code,temperature_2m_max,temperature_2m_min,precipitation_probability_max,precipitation_sum',
                ],
            ]);

            $data = $response->toArray(false);
            if (!isset($data['daily']['time'][0])) {
                return null;
            }

            return [
                'weather_code' => $data['daily']['weather_code'][0] ?? null,
                'temperature_2m_max' => $data['daily']['temperature_2m_max'][0] ?? null,
                'temperature_2m_min' => $data['daily']['temperature_2m_min'][0] ?? null,
                'precipitation_probability_max' => $data['daily']['precipitation_probability_max'][0] ?? null,
                'precipitation_sum' => $data['daily']['precipitation_sum'][0] ?? null,
            ];
        } catch (\Throwable) {
            return null;
        }
    }
}
