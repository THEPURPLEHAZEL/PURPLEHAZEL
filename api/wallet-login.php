<?php
/**
 * POST /api/wallet-login.php
 * Body: { "wallet": "..." }
 *
 * Login via Phantom wallet. Creates session + user.
 */

require_once __DIR__ . '/../config.php';
handle_preflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['ok' => false, 'error' => 'method not allowed'], 405);
}

if (!rate_limit_check('wallet_login_' . client_ip(), 10)) {
    send_json(['ok' => false, 'error' => 'too many requests'], 429);
}

$input = json_decode(file_get_contents('php://input'), true);
$wallet = sanitize_solana_address($input['wallet'] ?? '');

if (!$wallet) {
    send_json(['ok' => false, 'error' => 'invalid wallet address'], 400);
}

// Create/update user
$users = read_json_file(USERS_FILE);
$found = false;
foreach ($users as &$u) {
    if (($u['wallet'] ?? '') === $wallet) {
        $u['last_login'] = date('c');
        $u['login_count'] = ($u['login_count'] ?? 0) + 1;
        $found = true;
        break;
    }
}

if (!$found) {
    $users[] = [
        'email' => null,
        'wallet' => $wallet,
        'tier' => 'free',
        'created_at' => date('c'),
        'last_login' => date('c'),
        'login_count' => 1,
        'ip' => client_ip(),
    ];
}
write_json_file(USERS_FILE, $users);

// Add to waitlist
$waitlist = read_json_file(WAITLIST_FILE);
$onWaitlist = false;
foreach ($waitlist as $w) {
    if ($w['type'] === 'wallet' && strtolower($w['value']) === strtolower($wallet)) {
        $onWaitlist = true;
        break;
    }
}
if (!$onWaitlist) {
    $waitlist[] = [
        'type' => 'wallet',
        'value' => $wallet,
        'ip' => client_ip(),
        'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
        'created_at' => date('c'),
        'verified' => true,
    ];
    write_json_file(WAITLIST_FILE, $waitlist);
}

// Create session
$token = generate_token();
$sessions = read_json_file(SESSIONS_FILE);
$sessions = array_values(array_filter($sessions, fn($s) => $s['expires'] > time()));
$sessions[] = [
    'token' => $token,
    'email' => null,
    'wallet' => $wallet,
    'expires' => time() + SESSION_TTL,
    'created_at' => date('c'),
    'ip' => client_ip(),
];
write_json_file(SESSIONS_FILE, $sessions);

send_json([
    'ok' => true,
    'token' => $token,
    'wallet' => $wallet,
    'message' => 'logged in via wallet',
]);
