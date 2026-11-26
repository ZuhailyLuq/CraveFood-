<?php
/**
 * cron_vendor_sync.php
 * Called weekly via Vercel cron (or any external cron ping).
 * Syncs all configured Malaysian regions from OpenStreetMap.
 *
 * vercel.json cron entry:
 *   { "path": "/api/cron_vendor_sync.php", "schedule": "0 2 * * 1" }
 *
 * To protect from public access, requires a secret token.
 * Set CRON_SECRET in Vercel env vars.
 */
require_once __DIR__ . '/db.php';
require_once __DIR__ . '/db_helpers.php';
require_once __DIR__ . '/external/OverpassFetcher.php';

// ── Auth: allow Vercel cron (no auth header) or secret token ────────
$cronSecret = env('CRON_SECRET', '');
$authHeader = $_SERVER['HTTP_AUTHORIZATION'] ?? '';
$tokenParam = $_GET['token'] ?? '';

$isCronInvocation = isset($_SERVER['HTTP_X_VERCEL_CRON']); // Vercel sets this
$isSecretValid    = $cronSecret !== '' && $tokenParam === $cronSecret;

if (!$isCronInvocation && !$isSecretValid) {
    http_response_code(401);
    die(json_encode(['error' => 'Unauthorized']));
}

header('Content-Type: application/json');

$regions  = OverpassFetcher::getMalaysiaRegions();
$results  = [];
$startAll = microtime(true);

foreach ($regions as $name => $bbox) {
    $start = microtime(true);
    try {
        $r = OverpassFetcher::fetchByBbox(
            $pdo,
            $bbox['south'], $bbox['west'], $bbox['north'], $bbox['east'],
            $name
        );
        $r['region']       = $name;
        $r['elapsed_ms']   = round((microtime(true) - $start) * 1000);
        $r['status']       = 'ok';
    } catch (Exception $e) {
        $r = [
            'region'   => $name,
            'status'   => 'error',
            'message'  => $e->getMessage(),
        ];
    }
    $results[] = $r;

    // Brief pause between regions to be polite to Overpass servers
    sleep(2);
}

// Mark vendors not seen in 90 days as Hidden
$hiddenCount = db_execute($pdo,
    "UPDATE vendor SET \"Status\" = 'Hidden'
     WHERE \"VendorType\" = 'imported'
       AND \"Status\" = 'Available'
       AND (\"LastSynced\" IS NULL OR \"LastSynced\" < NOW() - INTERVAL '90 days')"
);

echo json_encode([
    'timestamp'    => date('c'),
    'regions_synced' => count($results),
    'total_ms'     => round((microtime(true) - $startAll) * 1000),
    'auto_hidden'  => $hiddenCount,
    'results'      => $results,
], JSON_PRETTY_PRINT);

