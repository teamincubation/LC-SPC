<?php

declare(strict_types=1);

namespace App\Services;

/**
 * Server-Side Geofencing Verification Service
 * Evaluates participant proximity to event coordinates using the Haversine great-circle formula.
 * Client-provided calculations or flags are never trusted.
 */
class GeofenceService
{
    private const EARTH_RADIUS_METERS = 6371000.0;

    /**
     * Compute great-circle distance between two GPS coordinates in meters.
     */
    public function calculateDistanceMeters(float $lat1, float $lon1, float $lat2, float $lon2): float
    {
        $dLat = deg2rad($lat2 - $lat1);
        $dLon = deg2rad($lon2 - $lon1);

        $a = sin($dLat / 2.0) * sin($dLat / 2.0) +
             cos(deg2rad($lat1)) * cos(deg2rad($lat2)) *
             sin($dLon / 2.0) * sin($dLon / 2.0);

        $c = 2.0 * atan2(sqrt($a), sqrt(max(0.0, 1.0 - $a)));

        return round(self::EARTH_RADIUS_METERS * $c, 2);
    }

    /**
     * Authoritatively evaluate whether participant coordinates fall inside the configured event geofence.
     */
    public function verifyProximity(
        ?float $participantLat,
        ?float $participantLon,
        ?float $eventLat,
        ?float $eventLon,
        ?int $radiusMeters
    ): array {
        // Geofence is not configured if coordinates or radius are missing
        if ($eventLat === null || $eventLon === null || empty($radiusMeters) || $radiusMeters <= 0) {
            return [
                'configured'      => false,
                'inside'          => false,
                'distance_meters' => null,
                'allowed_radius'  => null,
                'message'         => 'Geofencing is not configured for this event.',
            ];
        }

        // Missing client coordinates
        if ($participantLat === null || $participantLon === null) {
            return [
                'configured'      => true,
                'inside'          => false,
                'distance_meters' => null,
                'allowed_radius'  => $radiusMeters,
                'message'         => 'Device GPS location was not provided or could not be determined.',
            ];
        }

        // Validate coordinate bounds
        if ($participantLat < -90.0 || $participantLat > 90.0 || $participantLon < -180.0 || $participantLon > 180.0) {
            return [
                'configured'      => true,
                'inside'          => false,
                'distance_meters' => null,
                'allowed_radius'  => $radiusMeters,
                'message'         => 'Invalid GPS coordinates provided.',
            ];
        }

        $distance = $this->calculateDistanceMeters($participantLat, $participantLon, $eventLat, $eventLon);
        $isInside = ($distance <= (float) $radiusMeters);

        return [
            'configured'      => true,
            'inside'          => $isInside,
            'distance_meters' => $distance,
            'allowed_radius'  => $radiusMeters,
            'message'         => $isInside 
                ? "Location verified: you are within the event perimeter ({$distance}m away)."
                : "Outside geofence: you appear to be approx. {$distance}m away (configured venue radius is {$radiusMeters}m).",
        ];
    }
}
