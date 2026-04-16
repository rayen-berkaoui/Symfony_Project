<?php

namespace App\Service;

use App\Entity\Panier;

class CartPricingService
{
    public function __construct(private readonly BookingCatalogService $catalogService)
    {
    }

    public function estimate(Panier $panier): string
    {
        $panier->syncPeopleCount();

        if ($panier->isLieuLikeService()) {
            $lieu = $this->catalogService->resolveLieu($panier);
            $unitPrice = (float) ($lieu?->getPrix() ?? 0);
            $amount = $unitPrice * max(1, $panier->getNbPersonnes()) * max(1, $panier->getNbJours());
            return number_format($amount, 2, '.', '');
        }

        $etablissement = $this->catalogService->resolveEtablissement($panier);
        $gamme = strtolower((string) $etablissement?->getGammePrix());
        $type = strtolower((string) $etablissement?->getType());

        $hotelNightly = match (true) {
            str_contains($gamme, 'luxe') => 320,
            str_contains($gamme, 'haut') => 240,
            str_contains($gamme, 'moyen') => 160,
            str_contains($gamme, 'eco') => 90,
            default => 150,
        };

        $foodPerAdult = match (true) {
            str_contains($gamme, 'haut') || str_contains($gamme, 'luxe') => 70,
            str_contains($gamme, 'moyen') => 45,
            default => 28,
        };
        $foodPerChild = round($foodPerAdult * 0.55, 2);

        $spaPerAdult = match (true) {
            str_contains($gamme, 'luxe') => 120,
            str_contains($gamme, 'haut') => 95,
            default => 75,
        };
        $spaPerChild = round($spaPerAdult * 0.5, 2);

        $amount = match ($type) {
            'hotel' => $hotelNightly * max(1, (int) $panier->getNbChambres()) * max(1, $panier->getNbJours()),
            'restaurant', 'cafe' => ($foodPerAdult * max(1, (int) $panier->getNbAdultes())) + ($foodPerChild * max(0, (int) $panier->getNbEnfants())),
            'spa' => ($spaPerAdult * max(1, (int) $panier->getNbAdultes())) + ($spaPerChild * max(0, (int) $panier->getNbEnfants())),
            default => 0,
        };

        return number_format($amount, 2, '.', '');
    }
}
