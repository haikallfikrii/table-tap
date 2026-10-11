<?php
/**
 * Beacon: customer browser reports a failed order submit (status, message, device).
 * Catches failures that never reach PHP (WAF/CDN blocks, network drops).
 */

declare(strict_types=1);

require_once dirname(__DIR__, 2) . '/includes/helpers.php';

if (($_SERVER['REQUEST_METHOD'] ?? 'GET') !== 'POST') {
    http_response_code(405);
    exit;
}

$raw = (string) file_get_contents('php://input', false, null, 0, 4000);
$data = json_decode($raw, true);
if (!is_array($data)) {
    http_response_code(204);
    exit;
}

orderFailLog([
    'src' => 'client',
    'status' => (int) ($data['status'] ?? 0),
    'msg' => mb_substr((string) ($data['msg'] ?? ''), 0, 300),
    'body' => mb_substr((string) ($data['body'] ?? ''), 0, 300),
    'meja' => mb_substr((string) ($data['meja'] ?? ''), 0, 20),
    'staff' => !empty($data['staff']),
    'online' => isset($data['online']) ? (bool) $data['online'] : null,
    'ua' => mb_substr((string) ($_SERVER['HTTP_USER_AGENT'] ?? ''), 0, 160),
]);

http_response_code(204);
