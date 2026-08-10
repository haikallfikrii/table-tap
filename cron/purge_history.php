<?php
/**
 * Cron: purge expired order history by package retention.
 * Hostinger Cron Jobs example (daily):
 *   curl -s "https://tabletap.jomsite.com/cron/purge_history.php?key=YOUR_CRON_SECRET"
 */

declare(strict_types=1);

require_once dirname(__DIR__) . '/includes/helpers.php';

$c = getConfig();
$key = (string) ($_GET['key'] ?? '');
$expected = (string) ($c['cron_secret'] ?? '');

if ($expected === '' || $expected === 'CHANGE_ME_TO_RANDOM_STRING' || !hash_equals($expected, $key)) {
    http_response_code(403);
    header('Content-Type: text/plain');
    echo 'Forbidden';
    exit;
}

$deleted = purgeExpiredOrderHistory();
header('Content-Type: application/json');
echo json_encode(['ok' => true, 'deleted_orders' => $deleted]);
