<?php

namespace App\Service;

use App\Entity\Etablissement;
use App\Entity\Panier;
use Doctrine\ORM\EntityManagerInterface;

class CartInsightsService
{
    public function __construct(
        private readonly EntityManagerInterface $entityManager,
        private readonly BookingCatalogService $catalogService,
        private readonly GeminiInsightService $geminiInsightService,
        private readonly WeatherAdvisorService $weatherAdvisorService,
    ) {
    }

    public function buildPurchaseInsight(array $paniers): array
    {
        $suggestions = $this->buildComplementarySuggestions($paniers);
        $summary = $this->buildPurchaseSummary($paniers, $suggestions);

        return [
            'summary' => $summary,
            'suggestions' => $suggestions,
            'ai_enabled' => $this->geminiInsightService->isConfigured(),
        ];
    }

    public function buildWeatherInsight(array $paniers): array
    {
        $items = $this->weatherAdvisorService->buildAdvisories($paniers);
        $summary = $this->buildWeatherSummary($items);

        return [
            'summary' => $summary,
            'items' => $items,
            'ai_enabled' => $this->geminiInsightService->isConfigured(),
        ];
    }

    private function buildComplementarySuggestions(array $paniers): array
    {
        $suggestions = [];
        $seen = [];

        foreach ($paniers as $panier) {
            if (!$panier instanceof Panier) {
                continue;
            }

            $city = $panier->getDisplayCity() ?: $this->catalogService->resolveCity($panier);
            if (!$city) {
                continue;
            }

            $normalizedCity = mb_strtolower($city);
            $type = $panier->getTypeService();

            if ($panier->isLieuLikeService()) {
                if (!$this->cartHasTypeInCity($paniers, ['Hotel'], $normalizedCity)) {
                    $hotel = $this->findEtablissementByCityAndTypes($city, ['hotel']);
                    if ($hotel) {
                        $key = 'hotel-' . $hotel->getId();
                        if (!isset($seen[$key])) {
                            $suggestions[] = $this->hydrateSuggestion($hotel, 'Hébergement conseillé', "Ajoutez une nuit sur place pour éviter un aller-retour serré le jour même.");
                            $seen[$key] = true;
                        }
                    }
                }

                if (!$this->cartHasTypeInCity($paniers, ['Restaurant', 'Cafe'], $normalizedCity)) {
                    $food = $this->findEtablissementByCityAndTypes($city, ['restaurant', 'cafe']);
                    if ($food) {
                        $key = 'food-' . $food->getId();
                        if (!isset($seen[$key])) {
                            $suggestions[] = $this->hydrateSuggestion($food, 'Pause repas utile', "Complétez la visite par un repas ou un café dans la même ville pour garder un parcours cohérent.");
                            $seen[$key] = true;
                        }
                    }
                }
            }

            if ($type === 'Hotel') {
                if (!$this->cartHasTypeInCity($paniers, ['Restaurant', 'Cafe'], $normalizedCity)) {
                    $food = $this->findEtablissementByCityAndTypes($city, ['restaurant', 'cafe']);
                    if ($food) {
                        $key = 'hotel-food-' . $food->getId();
                        if (!isset($seen[$key])) {
                            $suggestions[] = $this->hydrateSuggestion($food, 'Sortie à proximité', "Vous avez déjà réservé l'hébergement : ajoutez une table proche pour compléter la soirée.");
                            $seen[$key] = true;
                        }
                    }
                }

                if (!$this->cartHasTypeInCity($paniers, ['Spa'], $normalizedCity)) {
                    $spa = $this->findEtablissementByCityAndTypes($city, ['spa']);
                    if ($spa) {
                        $key = 'spa-' . $spa->getId();
                        if (!isset($seen[$key])) {
                            $suggestions[] = $this->hydrateSuggestion($spa, 'Option détente', 'Un soin ou une pause bien-être peut valoriser un séjour hôtelier de plusieurs jours.');
                            $seen[$key] = true;
                        }
                    }
                }
            }
        }

        return array_slice($suggestions, 0, 3);
    }

    private function buildPurchaseSummary(array $paniers, array $suggestions): string
    {
        $context = [];
        foreach ($paniers as $panier) {
            if ($panier instanceof Panier) {
                $context[] = sprintf('%s à %s (%s)', $panier->getDisplayName(), $panier->getDisplayCity() ?: 'Tunisie', $panier->getServiceTypeLabel());
            }
        }
        $suggestionText = [];
        foreach ($suggestions as $suggestion) {
            $suggestionText[] = sprintf('%s — %s (%s)', $suggestion['name'], $suggestion['city'], $suggestion['headline']);
        }

        $fallback = $suggestions
            ? "Votre panier est cohérent. Pour le rendre plus complet, ajoutez de préférence un service complémentaire dans la même ville afin de limiter les trajets inutiles et d'améliorer l'expérience sur place."
            : "Votre panier est déjà bien équilibré. Aucun achat complémentaire évident n'est nécessaire pour le moment.";

        $ai = $this->geminiInsightService->generate(
            'Panier actuel : ' . implode('; ', $context) . "\n" . 'Suggestions disponibles : ' . implode('; ', $suggestionText),
            'Tu es un assistant de voyage e-commerce. Réponds en français en 2 phrases maximum, ton premium et pratique, sans markdown. Dis quel achat complémentaire est le plus pertinent et pourquoi.'
        );

        return $ai ?: $fallback;
    }

    private function buildWeatherSummary(array $items): string
    {
        if ($items === []) {
            return "Aucune recommandation météo n'est disponible pour le moment.";
        }

        $fallback = 'Consultez les éléments du panier : certaines sorties sont favorables, tandis que d’autres méritent une vérification météo avant confirmation.';
        $lines = [];
        foreach ($items as $item) {
            $lines[] = sprintf('%s le %s à %s : %s (%s)', $item['service'], $item['date'], $item['city'], $item['label'], $item['reason']);
        }

        $ai = $this->geminiInsightService->generate(
            implode("\n", $lines),
            'Tu es un conseiller voyage. Réponds en français, en 2 phrases maximum, sans markdown. Résume rapidement si le voyageur doit maintenir son programme, rester prudent ou reporter certains créneaux selon la météo.'
        );

        return $ai ?: $fallback;
    }

    private function hydrateSuggestion(Etablissement $etablissement, string $headline, string $reason): array
    {
        return [
            'id' => $etablissement->getId(),
            'entity_type' => 'etablissement',
            'name' => $etablissement->getNom(),
            'city' => $etablissement->getVille(),
            'type' => ucfirst((string) $etablissement->getType()),
            'headline' => $headline,
            'reason' => $reason,
            'image' => $etablissement->getImageUrl(),
        ];
    }

    private function cartHasTypeInCity(array $paniers, array $types, string $normalizedCity): bool
    {
        foreach ($paniers as $panier) {
            if (!$panier instanceof Panier) {
                continue;
            }

            $city = mb_strtolower((string) ($panier->getDisplayCity() ?: $this->catalogService->resolveCity($panier)));
            if ($city !== $normalizedCity) {
                continue;
            }

            if (in_array($panier->getTypeService(), $types, true)) {
                return true;
            }
        }

        return false;
    }

    private function findEtablissementByCityAndTypes(string $city, array $types): ?Etablissement
    {
        $qb = $this->entityManager->createQueryBuilder();
        $qb->select('e')
            ->from(Etablissement::class, 'e')
            ->where('LOWER(e.ville) = :city')
            ->andWhere($qb->expr()->in('LOWER(e.type)', ':types'))
            ->setParameter('city', mb_strtolower($city))
            ->setParameter('types', array_map('mb_strtolower', $types))
            ->setMaxResults(1);

        return $qb->getQuery()->getOneOrNullResult();
    }
}
