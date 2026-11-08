<?php
/**
 * delivery_fee_helper.php
 *
 * Computes a distance-based delivery fee using FREE, no-API-key services:
 *   - Nominatim (OpenStreetMap)  -> geocoding (turns an address into lat/lng)
 *   - OSRM demo server           -> driving distance between two points
 *
 * NOTE: Both are free public demo servers with light rate limits
 * (Nominatim: ~1 request/sec). Fine for a barangay-scale system.
 * If traffic grows a lot later, consider self-hosting Nominatim/OSRM
 * or switching to a paid provider (Mapbox, Google, etc.) - the
 * computeDeliveryFee() function below is the only place that would
 * need to change.
 */

// ── Barangay Hall origin (pickup/dispatch point) ────────────────────────────
// TODO: Replace with the EXACT Barangay Lipay Hall pin once available
// (Google Maps -> right-click the hall -> copy the lat,lng shown).
// Currently using the Barangay Lipay centroid from PhilAtlas as a close estimate.
define('BRGY_HALL_LAT', 8.3422);
define('BRGY_HALL_LNG', 123.1087);

// ── Fee formula ──────────────────────────────────────────────────────────
define('DELIVERY_BASE_FEE', 50);      // covers the first DELIVERY_FREE_KM
define('DELIVERY_FREE_KM', 2);        // km included in the base fee
define('DELIVERY_RATE_PER_KM', 15);   // ₱ per additional km (rounded up)
define('DELIVERY_MAX_FEE', 300);      // safety cap so a bad geocode can't overcharge

// ── Known barangay centroids ─────────────────────────────────────────────
// Used as the destination when no street details are given, and as a
// fallback if Nominatim can't find the exact street/purok.
const BARANGAY_FALLBACK_COORDS = [
    'Barangay Lipay'   => ['lat' => 8.3422, 'lng' => 123.1087],
    'Barangay Dinasan' => ['lat' => 8.3389, 'lng' => 123.0817],
];

/**
 * Geocode an address string via Nominatim (OpenStreetMap), restricted to PH.
 * Returns ['lat'=>float,'lng'=>float] or null on failure.
 */
function geocodeAddress(string $address): ?array
{
    if (trim($address) === '') return null;

    $url = 'https://nominatim.openstreetmap.org/search?' . http_build_query([
        'q'            => $address,
        'format'       => 'json',
        'limit'        => 1,
        'countrycodes' => 'ph',
    ]);

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
        // Nominatim's usage policy requires a descriptive User-Agent
        CURLOPT_HTTPHEADER     => ['User-Agent: BarangayLipayDocSystem/1.0'],
    ]);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error || !$response) return null;

    $data = json_decode($response, true);
    if (empty($data[0]['lat']) || empty($data[0]['lon'])) return null;

    return ['lat' => (float) $data[0]['lat'], 'lng' => (float) $data[0]['lon']];
}

/**
 * Driving distance in km between two coordinates using OSRM.
 * Returns float km, or null on failure.
 */
function getDrivingDistanceKm(float $lat1, float $lng1, float $lat2, float $lng2): ?float
{
    $url = "https://router.project-osrm.org/route/v1/driving/{$lng1},{$lat1};{$lng2},{$lat2}?overview=false";

    $ch = curl_init($url);
    curl_setopt_array($ch, [
        CURLOPT_RETURNTRANSFER => true,
        CURLOPT_TIMEOUT        => 6,
    ]);
    $response = curl_exec($ch);
    $error    = curl_error($ch);
    curl_close($ch);

    if ($error || !$response) return null;

    $data = json_decode($response, true);
    if (empty($data['routes'][0]['distance'])) return null;

    return $data['routes'][0]['distance'] / 1000; // meters -> km
}

/**
 * Straight-line (haversine) distance in km — fallback if OSRM is unreachable.
 */
function haversineKm(float $lat1, float $lng1, float $lat2, float $lng2): float
{
    $earthRadius = 6371;
    $dLat = deg2rad($lat2 - $lat1);
    $dLng = deg2rad($lng2 - $lng1);
    $a = sin($dLat / 2) ** 2 + cos(deg2rad($lat1)) * cos(deg2rad($lat2)) * sin($dLng / 2) ** 2;
    $c = 2 * atan2(sqrt($a), sqrt(1 - $a));
    return $earthRadius * $c;
}

/**
 * Main entry point. Computes the delivery fee for a barangay + optional
 * street details.
 *
 * @return array{fee:int, distance_km:?float, source:string}
 */
function computeDeliveryFee(string $barangay, string $addressDetails = ''): array
{
    $fallback = BARANGAY_FALLBACK_COORDS[$barangay] ?? null;
    if (!$fallback) {
        // Unsupported barangay — caller should reject the request; this is
        // just a safe (expensive) default in case it slips through.
        return ['fee' => DELIVERY_MAX_FEE, 'distance_km' => null, 'source' => 'unsupported'];
    }

    $destination = null;
    $usedGeocode = false;

    if (trim($addressDetails) !== '') {
        $fullQuery = trim($addressDetails) . ', ' . $barangay . ', Jose Dalman, Zamboanga del Norte, Philippines';
        $geo = geocodeAddress($fullQuery);
        if ($geo) {
            $destination = $geo;
            $usedGeocode = true;
        }
    }

    if (!$destination) {
        $destination = $fallback; // fall back to barangay centroid
    }

    $distanceKm = getDrivingDistanceKm(BRGY_HALL_LAT, BRGY_HALL_LNG, $destination['lat'], $destination['lng']);
    if ($distanceKm !== null) {
        $source = $usedGeocode ? 'osrm' : 'osrm_centroid';
    } else {
        // OSRM unreachable — fall back to straight-line distance
        $distanceKm = haversineKm(BRGY_HALL_LAT, BRGY_HALL_LNG, $destination['lat'], $destination['lng']);
        $source = 'haversine';
    }

    $extraKm = max(0, $distanceKm - DELIVERY_FREE_KM);
    $fee = DELIVERY_BASE_FEE + (ceil($extraKm) * DELIVERY_RATE_PER_KM);
    $fee = (int) min($fee, DELIVERY_MAX_FEE);

    return [
        'fee'         => $fee,
        'distance_km' => round($distanceKm, 2),
        'source'      => $source,
    ];
}