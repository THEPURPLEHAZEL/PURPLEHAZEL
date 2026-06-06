<?php
/**
 * GET /api/admin-waitlist.php?key=YOUR_ADMIN_KEY
 *
 * Returns full waitlist. Change ADMIN_KEY before deploying.
 */

require_once __DIR__ . '/../config.php';

// CHANGE THIS:
define('ADMIN_KEY_WAITLIST', 'change-me-to-something-random-xyz123');

$key = $_GET['key'] ?? '';
if ($key !== ADMIN_KEY_WAITLIST) {
    send_json(['ok' => false, 'error' => 'unauthorized'], 401);
}

$list = read_json_file(WAITLIST_FILE);

// Summary
$emails = array_filter($list, fn($e) => $e['type'] === 'email');
$wallets = array_filter($list, fn($e) => $e['type'] === 'wallet');

send_json([
    'ok' => true,
    'total' => count($list),
    'emails' => count($emails),
    'wallets' => count($wallets),
    'entries' => $list,
]);
