<?php

namespace App\Service;

class ItineraryPlanner
{
    /**
     * @var array<string, float>
     */
    private const TRANSPORT_SPEEDS_KMH = [
        'car' => 35.0,
        'walk' => 4.5,
    ];

    /**
     * @param array<int, array<string, mixed>> $stops
     * @param array<string, mixed>|null         $startPoint
     *
     * @return array<string, mixed>
     */
    public function plan(
        array $stops,
        ?array $startPoint,
        string $transportMode = 'car',
        ?float $maxBudget = null,
        ?float $maxDurationHours = null
    ): array {
        $transportMode = $this->normalizeTransportMode($transportMode);
        $speedKmh = self::TRANSPORT_SPEEDS_KMH[$transportMode];
        $maxDurationMinutes = $maxDurationHours !== null ? max(0.0, $maxDurationHours * 60.0) : null;

        $remaining = array_values($stops);
        $selectedStops = [];
        $droppedByConstraints = [];

        $totalDistanceKm = 0.0;
        $totalTravelMinutes = 0.0;
        $totalVisitMinutes = 0.0;
        $totalBudget = 0.0;

        $currentPoint = $startPoint;

        if ($remaining !== [] && $currentPoint === null) {
            $firstIndex = $this->findStartIndexFromCentroid($remaining);
            $firstStop = $remaining[$firstIndex];
            unset($remaining[$firstIndex]);
            $remaining = array_values($remaining);

            $visitMinutes = $this->visitMinutes($firstStop);
            $firstBudget = (float) ($firstStop['estimatedPrice'] ?? 0.0);

            if ($this->wouldExceedBudget($firstBudget, $totalBudget, $maxBudget)
                || $this->wouldExceedDuration($visitMinutes, 0.0, $totalTravelMinutes, $totalVisitMinutes, $maxDurationMinutes)
            ) {
                $droppedByConstraints[] = [
                    'name' => (string) ($firstStop['name'] ?? 'Stop'),
                    'estimatedPrice' => round($firstBudget, 2),
                    'reason' => $this->buildConstraintReason($firstBudget, $visitMinutes, 0.0, $totalBudget, $totalTravelMinutes, $totalVisitMinutes, $maxBudget, $maxDurationMinutes),
                ];
            } else {
                $totalVisitMinutes += $visitMinutes;
                $totalBudget += $firstBudget;

                $selectedStops[] = $this->enrichStop(
                    $firstStop,
                    count($selectedStops) + 1,
                    0.0,
                    0.0,
                    $visitMinutes,
                    $totalDistanceKm,
                    $totalTravelMinutes,
                    $totalVisitMinutes,
                    $totalBudget
                );

                $currentPoint = [
                    'latitude' => (float) $firstStop['latitude'],
                    'longitude' => (float) $firstStop['longitude'],
                ];
            }
        }

        while ($remaining !== [] && $currentPoint !== null) {
            usort($remaining, function (array $left, array $right) use ($currentPoint): int {
                $leftDistance = $this->distanceKm($currentPoint, $left);
                $rightDistance = $this->distanceKm($currentPoint, $right);

                return $leftDistance <=> $rightDistance;
            });

            $pickedIndex = null;
            $pickedDistanceKm = 0.0;
            $pickedTravelMinutes = 0.0;
            $pickedVisitMinutes = 0.0;

            foreach ($remaining as $index => $candidate) {
                $distanceKm = $this->distanceKm($currentPoint, $candidate);
                $travelMinutes = $this->travelMinutes($distanceKm, $speedKmh);
                $visitMinutes = $this->visitMinutes($candidate);
                $candidateBudget = (float) ($candidate['estimatedPrice'] ?? 0.0);

                if ($this->wouldExceedBudget($candidateBudget, $totalBudget, $maxBudget)) {
                    continue;
                }

                if ($this->wouldExceedDuration($visitMinutes, $travelMinutes, $totalTravelMinutes, $totalVisitMinutes, $maxDurationMinutes)) {
                    continue;
                }

                $pickedIndex = $index;
                $pickedDistanceKm = $distanceKm;
                $pickedTravelMinutes = $travelMinutes;
                $pickedVisitMinutes = $visitMinutes;
                break;
            }

            if ($pickedIndex === null) {
                foreach ($remaining as $candidate) {
                    $distanceKm = $this->distanceKm($currentPoint, $candidate);
                    $travelMinutes = $this->travelMinutes($distanceKm, $speedKmh);
                    $visitMinutes = $this->visitMinutes($candidate);
                    $candidateBudget = (float) ($candidate['estimatedPrice'] ?? 0.0);

                    $droppedByConstraints[] = [
                        'name' => (string) ($candidate['name'] ?? 'Stop'),
                        'estimatedPrice' => round($candidateBudget, 2),
                        'reason' => $this->buildConstraintReason(
                            $candidateBudget,
                            $visitMinutes,
                            $travelMinutes,
                            $totalBudget,
                            $totalTravelMinutes,
                            $totalVisitMinutes,
                            $maxBudget,
                            $maxDurationMinutes
                        ),
                    ];
                }

                break;
            }

            $pickedStop = $remaining[$pickedIndex];
            unset($remaining[$pickedIndex]);
            $remaining = array_values($remaining);

            $totalDistanceKm += $pickedDistanceKm;
            $totalTravelMinutes += $pickedTravelMinutes;
            $totalVisitMinutes += $pickedVisitMinutes;
            $totalBudget += (float) ($pickedStop['estimatedPrice'] ?? 0.0);

            $selectedStops[] = $this->enrichStop(
                $pickedStop,
                count($selectedStops) + 1,
                $pickedDistanceKm,
                $pickedTravelMinutes,
                $pickedVisitMinutes,
                $totalDistanceKm,
                $totalTravelMinutes,
                $totalVisitMinutes,
                $totalBudget
            );

            $currentPoint = [
                'latitude' => (float) $pickedStop['latitude'],
                'longitude' => (float) $pickedStop['longitude'],
            ];
        }

        $mapPoints = [];
        if ($startPoint !== null) {
            $mapPoints[] = [
                'latitude' => (float) ($startPoint['latitude'] ?? 0.0),
                'longitude' => (float) ($startPoint['longitude'] ?? 0.0),
                'label' => (string) ($startPoint['label'] ?? 'Depart'),
                'kind' => 'start',
            ];
        }

        foreach ($selectedStops as $stop) {
            $mapPoints[] = [
                'latitude' => (float) $stop['latitude'],
                'longitude' => (float) $stop['longitude'],
                'label' => sprintf('%d. %s', $stop['step'], (string) $stop['name']),
                'kind' => 'stop',
            ];
        }

        return [
            'selectedStops' => $selectedStops,
            'droppedByConstraints' => $droppedByConstraints,
            'totals' => [
                'distanceKm' => round($totalDistanceKm, 2),
                'travelMinutes' => (int) round($totalTravelMinutes),
                'visitMinutes' => (int) round($totalVisitMinutes),
                'durationMinutes' => (int) round($totalTravelMinutes + $totalVisitMinutes),
                'budget' => round($totalBudget, 2),
                'selectedCount' => count($selectedStops),
                'droppedCount' => count($droppedByConstraints),
            ],
            'transportMode' => $transportMode,
            'startPoint' => $startPoint,
            'map' => [
                'points' => $mapPoints,
                'googleMapsUrl' => $this->buildGoogleMapsUrl($selectedStops, $startPoint, $transportMode),
            ],
        ];
    }

    /**
     * @param array<string, mixed> $stop
     *
     * @return array<string, mixed>
     */
    private function enrichStop(
        array $stop,
        int $step,
        float $distanceFromPreviousKm,
        float $travelMinutesFromPrevious,
        float $visitMinutes,
        float $cumulativeDistanceKm,
        float $cumulativeTravelMinutes,
        float $cumulativeVisitMinutes,
        float $cumulativeBudget
    ): array {
        $stop['step'] = $step;
        $stop['distanceFromPreviousKm'] = round($distanceFromPreviousKm, 2);
        $stop['travelMinutesFromPrevious'] = (int) round($travelMinutesFromPrevious);
        $stop['visitMinutes'] = (int) round($visitMinutes);
        $stop['cumulativeDistanceKm'] = round($cumulativeDistanceKm, 2);
        $stop['cumulativeTravelMinutes'] = (int) round($cumulativeTravelMinutes);
        $stop['cumulativeVisitMinutes'] = (int) round($cumulativeVisitMinutes);
        $stop['cumulativeDurationMinutes'] = (int) round($cumulativeTravelMinutes + $cumulativeVisitMinutes);
        $stop['cumulativeBudget'] = round($cumulativeBudget, 2);

        return $stop;
    }

    private function normalizeTransportMode(string $transportMode): string
    {
        $normalized = strtolower(trim($transportMode));

        return array_key_exists($normalized, self::TRANSPORT_SPEEDS_KMH) ? $normalized : 'car';
    }

    private function wouldExceedBudget(float $candidateBudget, float $currentBudget, ?float $maxBudget): bool
    {
        if ($maxBudget === null) {
            return false;
        }

        return ($currentBudget + $candidateBudget) > ($maxBudget + 0.0001);
    }

    private function wouldExceedDuration(
        float $candidateVisitMinutes,
        float $candidateTravelMinutes,
        float $currentTravelMinutes,
        float $currentVisitMinutes,
        ?float $maxDurationMinutes
    ): bool {
        if ($maxDurationMinutes === null) {
            return false;
        }

        return ($currentTravelMinutes + $candidateTravelMinutes + $currentVisitMinutes + $candidateVisitMinutes) > ($maxDurationMinutes + 0.0001);
    }

    private function buildConstraintReason(
        float $candidateBudget,
        float $candidateVisitMinutes,
        float $candidateTravelMinutes,
        float $currentBudget,
        float $currentTravelMinutes,
        float $currentVisitMinutes,
        ?float $maxBudget,
        ?float $maxDurationMinutes
    ): string {
        $reasons = [];

        if ($this->wouldExceedBudget($candidateBudget, $currentBudget, $maxBudget)) {
            $reasons[] = 'budget';
        }

        if ($this->wouldExceedDuration($candidateVisitMinutes, $candidateTravelMinutes, $currentTravelMinutes, $currentVisitMinutes, $maxDurationMinutes)) {
            $reasons[] = 'duration';
        }

        if ($reasons === []) {
            return 'constraints';
        }

        return implode('+', $reasons);
    }

    /**
     * @param array<string, mixed> $stop
     */
    private function visitMinutes(array $stop): float
    {
        $hours = isset($stop['visitHours']) && is_numeric($stop['visitHours']) ? (float) $stop['visitHours'] : 1.5;
        $hours = max(0.5, min(8.0, $hours));

        return $hours * 60.0;
    }

    private function travelMinutes(float $distanceKm, float $speedKmh): float
    {
        if ($speedKmh <= 0.0) {
            return 0.0;
        }

        return ($distanceKm / $speedKmh) * 60.0;
    }

    /**
     * @param array<string, mixed> $from
     * @param array<string, mixed> $to
     */
    private function distanceKm(array $from, array $to): float
    {
        $fromLat = deg2rad((float) ($from['latitude'] ?? 0.0));
        $fromLng = deg2rad((float) ($from['longitude'] ?? 0.0));
        $toLat = deg2rad((float) ($to['latitude'] ?? 0.0));
        $toLng = deg2rad((float) ($to['longitude'] ?? 0.0));

        $deltaLat = $toLat - $fromLat;
        $deltaLng = $toLng - $fromLng;

        $a = sin($deltaLat / 2) ** 2 + cos($fromLat) * cos($toLat) * sin($deltaLng / 2) ** 2;
        $c = 2 * atan2(sqrt($a), sqrt(max(0.0, 1 - $a)));

        return 6371.0 * $c;
    }

    /**
     * @param array<int, array<string, mixed>> $stops
     */
    private function findStartIndexFromCentroid(array $stops): int
    {
        if (count($stops) <= 1) {
            return 0;
        }

        $sumLat = 0.0;
        $sumLng = 0.0;

        foreach ($stops as $stop) {
            $sumLat += (float) ($stop['latitude'] ?? 0.0);
            $sumLng += (float) ($stop['longitude'] ?? 0.0);
        }

        $centroid = [
            'latitude' => $sumLat / count($stops),
            'longitude' => $sumLng / count($stops),
        ];

        $bestIndex = 0;
        $bestDistance = INF;

        foreach ($stops as $index => $stop) {
            $distance = $this->distanceKm($centroid, $stop);
            if ($distance < $bestDistance) {
                $bestDistance = $distance;
                $bestIndex = $index;
            }
        }

        return $bestIndex;
    }

    /**
     * @param array<int, array<string, mixed>> $selectedStops
     * @param array<string, mixed>|null        $startPoint
     */
    private function buildGoogleMapsUrl(array $selectedStops, ?array $startPoint, string $transportMode): ?string
    {
        if ($selectedStops === []) {
            return null;
        }

        $travelMode = $transportMode === 'walk' ? 'walking' : 'driving';

        if ($startPoint !== null) {
            $origin = $this->formatCoordinates((float) $startPoint['latitude'], (float) $startPoint['longitude']);
            $destinationStop = $selectedStops[count($selectedStops) - 1];
            $destination = $this->formatCoordinates((float) $destinationStop['latitude'], (float) $destinationStop['longitude']);

            $waypoints = [];
            foreach (array_slice($selectedStops, 0, -1) as $stop) {
                $waypoints[] = $this->formatCoordinates((float) $stop['latitude'], (float) $stop['longitude']);
            }
        } else {
            $originStop = $selectedStops[0];
            $destinationStop = $selectedStops[count($selectedStops) - 1];
            $origin = $this->formatCoordinates((float) $originStop['latitude'], (float) $originStop['longitude']);
            $destination = $this->formatCoordinates((float) $destinationStop['latitude'], (float) $destinationStop['longitude']);

            $waypoints = [];
            if (count($selectedStops) > 2) {
                foreach (array_slice($selectedStops, 1, -1) as $stop) {
                    $waypoints[] = $this->formatCoordinates((float) $stop['latitude'], (float) $stop['longitude']);
                }
            }
        }

        $params = [
            'api' => '1',
            'origin' => $origin,
            'destination' => $destination,
            'travelmode' => $travelMode,
        ];

        if ($waypoints !== []) {
            $params['waypoints'] = implode('|', $waypoints);
        }

        return 'https://www.google.com/maps/dir/?' . http_build_query($params, '', '&', PHP_QUERY_RFC3986);
    }

    private function formatCoordinates(float $latitude, float $longitude): string
    {
        return number_format($latitude, 6, '.', '') . ',' . number_format($longitude, 6, '.', '');
    }
}
