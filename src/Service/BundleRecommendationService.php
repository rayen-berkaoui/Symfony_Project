<?php

namespace App\Service;

use App\Entity\Panier;

class BundleRecommendationService
{
    public function __construct(private readonly BookingCatalogService $catalogService)
    {
    }

    /**
     * @param Panier[] $paniers
     * @return array<int, array<string, mixed>>
     */
    public function buildForCart(array $paniers): array
    {
        $stats = $this->buildStats($paniers);

        $bundles = [
            $this->makeBundle('stay_experience', 'Stay + Experience', 'Hôtel + activité proche', ['Hotel', 'Activite'], $stats, 'Idéal pour transformer une simple nuitée en séjour mémorable.'),
            $this->makeBundle('dinner_hotel', 'Dinner + Hotel', 'Hôtel + dîner le soir', ['Hotel', 'Restaurant'], $stats, 'Réduit les trajets inutiles après le check-in.'),
            $this->makeBundle('transport_stay', 'Transport + Stay', 'Arrivée fluide + hébergement', ['Transport', 'Hotel'], $stats, 'Pratique pour les arrivées tardives ou les destinations éloignées.'),
            $this->makeBundle('relaxation_pack', 'Relaxation Pack', 'Hôtel + spa', ['Hotel', 'Spa'], $stats, 'Très cohérent pour un week-end détente.'),
            $this->makeBundle('discovery_pack', 'Tourist Discovery Pack', 'Lieu touristique + activité + repas', ['Voyage', 'Activite', 'Restaurant'], $stats, 'Le pack découverte crée une journée complète.'),
            $this->makeBundle('event_stay', 'Event + Stay', 'Sortie / activité + hôtel', ['Activite', 'Hotel'], $stats, 'Utile quand l’activité finit tard.'),
            $this->makeBundle('family_pack', 'Family Pack', 'Hôtel + activité famille + restauration', ['Hotel', 'Activite', 'Restaurant'], $stats, 'Pertinent dès qu’il y a plusieurs voyageurs.'),
            $this->makeBundle('weekend_getaway', 'Weekend Getaway', '2 nuits + activité + dîner', ['Hotel', 'Activite', 'Restaurant'], $stats, 'Très bon panier moyen pour un city-break.'),
            $this->makeBundle('business_pack', 'Business Pack', 'Hôtel + café / restaurant + transport', ['Hotel', 'Cafe', 'Transport'], $stats, 'Pratique pour les courts séjours professionnels.'),
            $this->makeBundle('luxury_experience', 'Luxury Experience', 'Hôtel premium + fine dining + expérience', ['Hotel', 'Restaurant', 'Activite'], $stats, 'Convient aux paniers haut de gamme et occasions spéciales.'),
        ];

        foreach ($bundles as &$bundle) {
            if ($bundle['code'] === 'family_pack') {
                $bundle['priority'] += $stats['people'] >= 3 ? 20 : -10;
            }
            if ($bundle['code'] === 'weekend_getaway') {
                $bundle['priority'] += $stats['nights'] >= 2 ? 18 : 0;
            }
            if ($bundle['code'] === 'luxury_experience') {
                $bundle['priority'] += $stats['luxury'] ? 16 : -8;
            }
        }
        unset($bundle);

        usort($bundles, static fn (array $a, array $b): int => $b['priority'] <=> $a['priority']);

        return $bundles;
    }

    /**
     * @param Panier[] $paniers
     * @return array<string, mixed>
     */
    private function buildStats(array $paniers): array
    {
        $types = [];
        $cities = [];
        $maxPeople = 0;
        $maxNights = 0;
        $luxury = false;

        foreach ($paniers as $panier) {
            if (!$panier instanceof Panier) {
                continue;
            }

            $types[$panier->getTypeService()] = true;
            $city = $panier->getDisplayCity() ?: $this->catalogService->resolveCity($panier);
            if ($city) {
                $cities[mb_strtolower($city)] = $city;
            }
            $maxPeople = max($maxPeople, (int) $panier->getNbPersonnes());
            $maxNights = max($maxNights, (int) $panier->getNbJours());
            $etablissement = $this->catalogService->resolveEtablissement($panier);
            if ($etablissement && str_contains(mb_strtolower((string) $etablissement->getGammePrix()), 'luxe')) {
                $luxury = true;
            }
        }

        return [
            'types' => array_keys($types),
            'cities' => array_values($cities),
            'people' => $maxPeople,
            'nights' => $maxNights,
            'luxury' => $luxury,
        ];
    }

    /**
     * @param array<string, mixed> $stats
     * @param string[] $requiredTypes
     * @return array<string, mixed>
     */
    private function makeBundle(string $code, string $title, string $subtitle, array $requiredTypes, array $stats, string $description): array
    {
        $currentTypes = $stats['types'];
        $missing = [];
        foreach ($requiredTypes as $requiredType) {
            if (!in_array($requiredType, $currentTypes, true)) {
                $missing[] = $requiredType;
            }
        }

        $completed = count($requiredTypes) - count($missing);
        $readiness = count($requiredTypes) > 0 ? (int) round(($completed / count($requiredTypes)) * 100) : 0;
        $ready = $missing === [];

        return [
            'code' => $code,
            'title' => $title,
            'subtitle' => $subtitle,
            'description' => $description,
            'required_types' => $requiredTypes,
            'missing' => $missing,
            'readiness' => $readiness,
            'status' => $ready ? 'ready' : ($completed > 0 ? 'partial' : 'opportunity'),
            'priority' => $ready ? 100 : ($completed * 20 - count($missing) * 5),
            'city_scope' => $stats['cities'][0] ?? 'Tunisie',
        ];
    }
}
