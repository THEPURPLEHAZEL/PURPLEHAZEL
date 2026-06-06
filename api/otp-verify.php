<?php
/**
 * POST /api/otp-verify.php
 * Body: { "email": "user@example.com", "code": "123456" }
 *
 * Verifies OTP. On success:
 * - Creates/updates user
 * - Returns session token
 * - Adds to waitlist if not already
 */

require_once __DIR__ . '/../config.php';
handle_preflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['ok' => false, 'error' => 'method not allowed'], 405);
}

// Rate limit: 10 verify attempts per hour per IP
if (!rate_limit_check('otp_verify_' . client_ip(), 10)) {
    send_json(['ok' => false, 'error' => 'too many attempts'], 429);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = sanitize_email($input['email'] ?? '');
$code = trim($input['code'] ?? '');

if (!$email || !$code) {
    send_json(['ok' => false, 'error' => 'email and code required'], 400);
}

// Find OTP
$otps = read_json_file(OTP_FILE);
$matched = false;
$newOtps = [];

foreach ($otps as $o) {
    if ($o['email'] === $email && $o['expires'] > time() && password_verify($code, $o['code'])) {
        $matched = true;
        // Don't keep this OTP (consumed)
    } else {
        $newOtps[] = $o;
    }
}

if (!$matched) {
    send_json(['ok' => false, 'error' => 'invalid or expired code'], 401);
}

// Clear used OTP
write_json_file(OTP_FILE, $newOtps);

// Create/update user
$users = read_json_file(USERS_FILE);
$found = false;
foreach ($users as &$u) {
    if ($u['email'] === $email) {
        $u['last_login'] = date('c');
        $u['login_count'] = ($u['login_count'] ?? 0) + 1;
        $found = true;
        break;
    }
}

if (!$found) {
    $users[] = [
        'email' => $email,
        'wallet' => null,
        'tier' => 'free',
        'created_at' => date('c'),
        'last_login' => date('c'),
        'login_count' => 1,
        'ip' => client_ip(),
    ];
}
write_json_file(USERS_FILE, $users);

// Add to waitlist if not present
$waitlist = read_json_file(WAITLIST_FILE);
$onWaitlist = false;
foreach ($waitlist as $w) {
    if ($w['type'] === 'email' && strtolower($w['value']) === $email) {
        $onWaitlist = true;
        break;
    }
}
if (!$onWaitlist) {
    $waitlist[] = [
        'type' => 'email',
        'value' => $email,
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
// Clean expired
$sessions = array_values(array_filter($sessions, fn($s) => $s['expires'] > time()));
$sessions[] = [
    'token' => $token,
    'email' => $email,
    'expires' => time() + SESSION_TTL,
    'created_at' => date('c'),
    'ip' => client_ip(),
];
write_json_file(SESSIONS_FILE, $sessions);

send_json([
    'ok' => true,
    'token' => $token,
    'email' => $email,
    'message' => 'logged in',
]);
