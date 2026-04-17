<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;
use Psr\Log\LoggerInterface;
use Symfony\Component\DependencyInjection\Attribute\Autowire;

class WeatherService
{
    public function __construct(
        private HttpClientInterface $client,
        #[Autowire('%env(OPENWEATHER_API_KEY)%')] private string $apiKey,
        private LoggerInterface $logger
    ) {
    }

    public function getWeather(?string $city = null, ?float $lat = null, ?float $lon = null): ?array
    {
        if (empty($this->apiKey) || $this->apiKey === 'votre_cle_api_openweather_ici') {
            return null;
        }

        try {
            $query = [
                'appid' => $this->apiKey,
                'units' => 'metric', // Pour avoir les degrés Celsius
                'lang'  => 'fr',     // Pour la description en français
            ];

            if ($lat !== null && $lon !== null) {
                $query['lat'] = $lat;
                $query['lon'] = $lon;
            } elseif (!empty($city)) {
                $query['q'] = $city;
            } else {
                return null;
            }

            $response = $this->client->request(
                'GET',
                'https://api.openweathermap.org/data/2.5/weather',
                ['query' => $query]
            );

            if ($response->getStatusCode() === 200) {
                $data = $response->toArray();
                return [
                    'temp' => round($data['main']['temp'] ?? 0),
                    'humidity' => $data['main']['humidity'] ?? 0,
                    'wind' => $data['wind']['speed'] ?? 0,
                    'description' => $data['weather'][0]['description'] ?? 'Inconnu',
                    'icon' => 'https://openweathermap.org/img/wn/' . ($data['weather'][0]['icon'] ?? '01d') . '@2x.png'
                ];
            }
        } catch (\Exception $e) {
            $this->logger->error('Erreur appel API OpenWeather: ' . $e->getMessage());
        }

        return null;
    }
}
