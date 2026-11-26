<?php
/**
 * OverpassFetcher.php
 * Queries OpenStreetMap via the Overpass API and upserts
 * food vendor data into the CraveFood vendor table.
 *
 * Usage:
 *   require_once __DIR__ . '/OverpassFetcher.php';
 *   $result = OverpassFetcher::fetchByBbox($pdo, 1.48, 103.55, 1.57, 103.78, 'UTM Skudai');
 *
 * Data © OpenStreetMap contributors, ODbL — https://www.openstreetmap.org/copyright
 */

class OverpassFetcher {

    // Public Overpass endpoints (fallback list)
    private static array $endpoints = [
        'https://overpass-api.de/api/interpreter',
        'https://overpass.kumi.systems/api/interpreter',
        'https://overpass.openstreetmap.ru/api/interpreter',
    ];

    // Amenity types to import
    private static string $amenityFilter = 'restaurant|fast_food|cafe|food_court';

    // Human-readable amenity labels
    private static array $amenityLabels = [
        'restaurant'  => 'Restaurant',
        'fast_food'   => 'Fast Food',
        'cafe'        => 'Café',
        'food_court'  => 'Food Court',
    ];

    // ----------------------------------------------------------------
    // Public: fetch by bounding box (south, west, north, east)
    // ----------------------------------------------------------------
    public static function fetchByBbox(
        PDO    $pdo,
        float  $south,
        float  $west,
        float  $north,
        float  $east,
        string $region = 'Malaysia'
    ): array {
        $bbox  = "{$south},{$west},{$north},{$east}";
        $query = self::buildBboxQuery($bbox);
        return self::runFetch($pdo, $query, $region);
    }

    // ----------------------------------------------------------------
    // Public: fetch by admin area name (e.g. "Johor", admin_level 4)
    // ----------------------------------------------------------------
    public static function fetchByAdminArea(
        PDO    $pdo,
        string $areaName,
        int    $adminLevel = 4,
        string $region = ''
    ): array {
        $region = $region ?: $areaName;
        $query  = self::buildAreaQuery($areaName, $adminLevel);
        return self::runFetch($pdo, $query, $region);
    }

    // ----------------------------------------------------------------
    // Preconfigured Malaysian regions
    // ----------------------------------------------------------------
    public static function getMalaysiaRegions(): array {
        return [
            'UTM Skudai / JB'  => ['south' => 1.480, 'west' => 103.550, 'north' => 1.570, 'east' => 103.780],
            'Johor Bahru City' => ['south' => 1.430, 'west' => 103.620, 'north' => 1.550, 'east' => 103.820],
            'Kuala Lumpur'     => ['south' => 3.050, 'west' => 101.600, 'north' => 3.220, 'east' => 101.780],
            'Klang Valley'     => ['south' => 2.900, 'west' => 101.400, 'north' => 3.400, 'east' => 101.900],
            'Penang (Georgetown)' => ['south' => 5.300, 'west' => 100.180, 'north' => 5.500, 'east' => 100.480],
            'Ipoh'             => ['south' => 4.520, 'west' => 101.040, 'north' => 4.660, 'east' => 101.180],
            'Kota Kinabalu'    => ['south' => 5.930, 'west' => 116.020, 'north' => 6.050, 'east' => 116.200],
            'Kuching'          => ['south' => 1.520, 'west' => 110.290, 'north' => 1.620, 'east' => 110.440],
        ];
    }

    // ================================================================
    // PRIVATE INTERNALS
    // ================================================================

    private static function buildBboxQuery(string $bbox): string {
        $filter = self::$amenityFilter;
        return <<<EOQ
[out:json][timeout:30];
(
  node["amenity"~"{$filter}"]({$bbox});
  way["amenity"~"{$filter}"]({$bbox});
);
out center tags;
EOQ;
    }

    private static function buildAreaQuery(string $areaName, int $adminLevel): string {
        $filter = self::$amenityFilter;
        return <<<EOQ
[out:json][timeout:60];
area["name"="{$areaName}"]["admin_level"="{$adminLevel}"]->.searchArea;
(
  node["amenity"~"{$filter}"](area.searchArea);
  way["amenity"~"{$filter}"](area.searchArea);
);
out center tags;
EOQ;
    }

    private static function runFetch(PDO $pdo, string $query, string $region): array {
        $elements = self::callOverpass($query);
        if ($elements === null) {
            return ['error' => 'Overpass API request failed.', 'imported' => 0, 'updated' => 0, 'skipped' => 0];
        }

        $counts = ['imported' => 0, 'updated' => 0, 'skipped' => 0, 'total_elements' => count($elements)];

        foreach ($elements as $el) {
            $normalized = self::normalizeElement($el);
            if ($normalized === null) {
                $counts['skipped']++;
                continue;
            }
            $normalized['Region'] = $region;

            // Cache raw tags
            self::cacheRawElement($pdo, $el, $region);

            // Upsert vendor
            $result = self::upsertVendor($pdo, $normalized);
            $counts[$result]++;
        }

        return $counts;
    }

    // ----------------------------------------------------------------
    // HTTP call to Overpass API (tries each endpoint in order)
    // ----------------------------------------------------------------
    private static function callOverpass(string $query): ?array {
        foreach (self::$endpoints as $endpoint) {
            $ch = curl_init($endpoint);
            curl_setopt_array($ch, [
                CURLOPT_RETURNTRANSFER => true,
                CURLOPT_POST           => true,
                CURLOPT_POSTFIELDS     => http_build_query(['data' => $query]),
                CURLOPT_USERAGENT      => 'CraveFood/1.0 (food ordering app; Malaysia)',
                CURLOPT_TIMEOUT        => 45,
                CURLOPT_CONNECTTIMEOUT => 10,
                CURLOPT_SSL_VERIFYPEER => true,
            ]);

            $response = curl_exec($ch);
            $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlErr  = curl_error($ch);
            curl_close($ch);

            if ($curlErr || $httpCode !== 200) {
                continue; // Try next endpoint
            }

            $data = json_decode($response, true);
            if (isset($data['elements'])) {
                return $data['elements'];
            }
        }
        return null;
    }

    // ----------------------------------------------------------------
    // Normalize a single OSM element into CraveFood vendor fields
    // Returns null if the element should be skipped (no name)
    // ----------------------------------------------------------------
    private static function normalizeElement(array $el): ?array {
        $tags = $el['tags'] ?? [];

        // Skip if no name
        $name = trim($tags['name'] ?? $tags['name:en'] ?? $tags['name:ms'] ?? '');
        if ($name === '') return null;

        // Get coordinates
        [$lat, $lon] = self::getCenter($el);
        if ($lat === null) return null;

        // Amenity → FoodType label
        $amenity  = $tags['amenity'] ?? 'restaurant';
        $foodType = self::$amenityLabels[$amenity] ?? 'Restaurant';

        // Cuisine → Categories
        $cuisineRaw = $tags['cuisine'] ?? '';
        $categories = '';
        if ($cuisineRaw !== '') {
            $parts = array_map(fn($c) => ucfirst(trim(str_replace('_', ' ', $c))), explode(';', $cuisineRaw));
            $categories = implode(', ', array_filter($parts));
        }

        // Halal detection
        $dietaryParts = [];
        $isHalal = ($tags['diet:halal'] ?? $tags['halal'] ?? '') === 'yes';
        if ($isHalal) $dietaryParts[] = 'Halal';
        // Vegetarian
        if (($tags['diet:vegetarian'] ?? '') === 'yes') $dietaryParts[] = 'Vegetarian';
        $dietaryInfo = implode(', ', $dietaryParts);

        // Address
        $addrParts = array_filter([
            $tags['addr:housenumber'] ?? '',
            $tags['addr:street']      ?? '',
            $tags['addr:suburb']      ?? $tags['addr:neighbourhood'] ?? '',
            $tags['addr:city']        ?? '',
            $tags['addr:postcode']    ?? '',
        ]);
        $location = implode(', ', $addrParts) ?: ($tags['addr:city'] ?? '');

        // Phone
        $phone = $tags['phone'] ?? $tags['contact:phone'] ?? $tags['contact:mobile'] ?? '';
        $phone = preg_replace('/\s+/', '', $phone); // remove spaces

        // Website
        $website = $tags['website'] ?? $tags['contact:website'] ?? '';

        // Opening hours
        $openHours = $tags['opening_hours'] ?? '';

        // OSM URL
        $osmType = $el['type'];  // 'node' or 'way'
        $osmId   = $el['id'];
        $externalUrl = "https://www.openstreetmap.org/{$osmType}/{$osmId}";

        return [
            'ExternalID'     => (string)$osmId,
            'ExternalSource' => 'osm',
            'ExternalURL'    => $externalUrl,
            'OsmType'        => $osmType,
            'ShopName'       => $name,
            'FoodType'       => $foodType,
            'Amenity'        => $amenity,
            'Categories'     => $categories,
            'DietaryInfo'    => $dietaryInfo,
            'Location'       => $location,
            'Latitude'       => $lat,
            'Longitude'      => $lon,
            'Phone'          => $phone,
            'Website'        => $website,
            'OpenHours'      => $openHours,
            'VendorType'     => 'imported',
            'IsVerified'     => false,
        ];
    }

    // ----------------------------------------------------------------
    // Extract lat/lon: direct for nodes, center{} for ways
    // ----------------------------------------------------------------
    private static function getCenter(array $el): array {
        if ($el['type'] === 'node') {
            return [(float)$el['lat'], (float)$el['lon']];
        }
        // Ways: Overpass returns a 'center' key with 'out center tags'
        if (isset($el['center']['lat'])) {
            return [(float)$el['center']['lat'], (float)$el['center']['lon']];
        }
        return [null, null];
    }

    // ----------------------------------------------------------------
    // Upsert vendor row; return 'imported' | 'updated' | 'skipped'
    // ----------------------------------------------------------------
    private static function upsertVendor(PDO $pdo, array $v): string {
        // Check if already exists (by OSM ID + source)
        $existing = $pdo->prepare(
            'SELECT "VendorID", "VendorType" FROM vendor
             WHERE "ExternalSource" = ? AND "ExternalID" = ?
             LIMIT 1'
        );
        $existing->execute(['osm', $v['ExternalID']]);
        $row = $existing->fetch();

        if ($row) {
            // Don't overwrite a claimed/registered vendor's data
            if ($row['VendorType'] === 'registered') {
                // Just update LastSynced so we know it's still on OSM
                $pdo->prepare('UPDATE vendor SET "LastSynced" = NOW() WHERE "VendorID" = ?')
                    ->execute([$row['VendorID']]);
                return 'updated';
            }

            // Update imported vendor with fresh OSM data
            $stmt = $pdo->prepare('
                UPDATE vendor SET
                    "ShopName"       = ?,
                    "FoodType"       = ?,
                    "Amenity"        = ?,
                    "Categories"     = ?,
                    "DietaryInfo"    = ?,
                    "Location"       = CASE WHEN ? <> \'\' THEN ? ELSE "Location" END,
                    "Latitude"       = ?,
                    "Longitude"      = ?,
                    "Phone"          = CASE WHEN ? <> \'\' THEN ? ELSE "Phone" END,
                    "Website"        = CASE WHEN ? <> \'\' THEN ? ELSE "Website" END,
                    "OpenHours"      = CASE WHEN ? <> \'\' THEN ? ELSE "OpenHours" END,
                    "ExternalURL"    = ?,
                    "LastSynced"     = NOW()
                WHERE "VendorID" = ?
            ');
            $stmt->execute([
                $v['ShopName'], $v['FoodType'], $v['Amenity'], $v['Categories'], $v['DietaryInfo'],
                $v['Location'], $v['Location'],
                $v['Latitude'], $v['Longitude'],
                $v['Phone'], $v['Phone'],
                $v['Website'], $v['Website'],
                $v['OpenHours'], $v['OpenHours'],
                $v['ExternalURL'],
                $row['VendorID']
            ]);
            return 'updated';
        }

        // New vendor — insert
        $stmt = $pdo->prepare('
            INSERT INTO vendor (
                "ShopName", "VendorPassword", "FoodType", "Amenity", "Categories",
                "DietaryInfo", "Location", "Latitude", "Longitude",
                "Phone", "Website", "OpenHours", "Description",
                "ExternalID", "ExternalSource", "ExternalURL",
                "VendorType", "IsVerified", "LastSynced"
            ) VALUES (
                ?, \'\', ?, ?, ?,
                ?, ?, ?, ?,
                ?, ?, ?, \'Imported from OpenStreetMap\',
                ?, \'osm\', ?,
                \'imported\', FALSE, NOW()
            )
        ');
        $stmt->execute([
            $v['ShopName'], $v['FoodType'], $v['Amenity'], $v['Categories'],
            $v['DietaryInfo'], $v['Location'], $v['Latitude'], $v['Longitude'],
            $v['Phone'], $v['Website'], $v['OpenHours'],
            $v['ExternalID'], $v['ExternalURL'],
        ]);
        return 'imported';
    }

    // ----------------------------------------------------------------
    // Cache raw OSM element in vendor_osm_cache
    // ----------------------------------------------------------------
    private static function cacheRawElement(PDO $pdo, array $el, string $region): void {
        [$lat, $lon] = self::getCenter($el);
        $pdo->prepare('
            INSERT INTO vendor_osm_cache ("OsmID","OsmType","RawTags","CenterLat","CenterLon","Region","FetchedAt")
            VALUES (?,?,?::jsonb,?,?,?,NOW())
            ON CONFLICT ("OsmType","OsmID") DO UPDATE
              SET "RawTags"   = EXCLUDED."RawTags",
                  "FetchedAt" = NOW(),
                  "Region"    = EXCLUDED."Region"
        ')->execute([
            $el['id'],
            $el['type'],
            json_encode($el['tags'] ?? []),
            $lat,
            $lon,
            $region,
        ]);
    }
}

