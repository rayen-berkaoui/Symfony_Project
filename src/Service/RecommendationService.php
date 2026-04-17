<?php

namespace App\Service;

use Symfony\Contracts\HttpClient\HttpClientInterface;

class RecommendationService
{
    public function __construct(private HttpClientInterface $client)
    {
    }

    public function getRecommendations(?string $ville, ?string $budget, ?string $type, ?string $categorie): array
    {
        $response = $this->client->request('GET', 'http://127.0.0.1:5001/recommend', [
            'query' => [
                'ville' => $ville,
                'budget' => $budget,
                'type' => $type,
                'categorie' => $categorie,
                'top_n' => 5,
            ]
        ]);

        return $response->toArray();
    }
}