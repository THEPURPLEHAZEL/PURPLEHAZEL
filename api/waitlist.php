<?php
/**
 * POST /api/waitlist.php
 * Body: { "type": "email" | "wallet", "value": "..." }
 */

require_once __DIR__ . '/../config.php';

handle_preflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['ok' => false, 'error' => 'method not allowed'], 405);
}

// Rate limit: 10 waitlist signups per hour per IP
if (!rate_limit_check('waitlist_' . client_ip(), 10)) {
    send_json(['ok' => false, 'error' => 'too many requests'], 429);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) {
    send_json(['ok' => false, 'error' => 'invalid body'], 400);
}

$type = $input['type'] ?? '';
$value = $input['value'] ?? '';

if ($type === 'email') {
    $email = sanitize_email($value);
    if (!$email) {
        send_json(['ok' => false, 'error' => 'invalid email format'], 400);
    }

    // Extract domain
    $domain = strtolower(substr($email, strrpos($email, '@') + 1));

    // Block disposable/temp email domains
    $disposable = [
        'tempmail.com','tempmail.net','temp-mail.org','temp-mail.io',
        'guerrillamail.com','guerrillamailblock.com','sharklasers.com','grr.la',
        'throwaway.email','throwawaymail.com',
        'mailinator.com','mailinator.net','mailinator2.com',
        'yopmail.com','yopmail.net','yopmail.fr',
        'trashmail.com','trashmail.net','trashmail.de','trashmail.io',
        'fakeinbox.com','fakemail.net','fakemailgenerator.com',
        'dispostable.com','mintemail.com','mohmal.com',
        'maildrop.cc','mailnesia.com','mailcatch.com',
        '10minutemail.com','10minutemail.net','10minutemail.org',
        '20minutemail.com','30minutemail.com',
        'getnada.com','nada.email','getairmail.com',
        'spamgourmet.com','spam4.me','spambox.us','spambog.com',
        'emailondeck.com','emailfake.com','emailtemporario.com.br',
        'burnermail.io','burner-mail.com',
        'mytemp.email','tempinbox.com','tempail.com',
        'anonbox.net','armyspy.com','cuvox.de','dayrep.com',
        'einrot.com','fleckens.hu','gustr.com','jourrapide.com',
        'rhyta.com','superrito.com','teleworm.us',
        'discard.email','discardmail.com','discardmail.de',
        'inbox.si','moakt.com','mohmal.in',
        'wegwerfmail.de','wegwerfmail.net','wegwerfmail.org',
        'zetmail.com','incognitomail.com','deadaddress.com',
        'example.com','example.org','example.net','test.com','test.net',
    ];

    if (in_array($domain, $disposable)) {
        send_json(['ok' => false, 'error' => 'fake or disposable email not allowed. please use a real email address.'], 400);
    }

    // Verify domain has valid MX record (the domain can actually receive email)
    // This catches made-up domains like "asdfghjkl.com"
    if (function_exists('checkdnsrr')) {
        $has_mx = checkdnsrr($domain, 'MX');
        $has_a  = checkdnsrr($domain, 'A');
        if (!$has_mx && !$has_a) {
            send_json(['ok' => false, 'error' => 'email domain does not exist. please use a real email address.'], 400);
        }
    }

    $entry = [
        'type' => 'email',
        'value' => $email,
        'ip' => client_ip(),
        'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
        'created_at' => date('c'),
    ];
} elseif ($type === 'wallet') {
    $wallet = sanitize_solana_address($value);
    if (!$wallet) send_json(['ok' => false, 'error' => 'invalid wallet address'], 400);
    $entry = [
        'type' => 'wallet',
        'value' => $wallet,
        'ip' => client_ip(),
        'ua' => substr($_SERVER['HTTP_USER_AGENT'] ?? '', 0, 200),
        'created_at' => date('c'),
    ];
} else {
    send_json(['ok' => false, 'error' => 'invalid type'], 400);
}

// Load existing, check duplicates
$list = read_json_file(WAITLIST_FILE);

foreach ($list as $existing) {
    if ($existing['type'] === $entry['type'] && strtolower($existing['value']) === strtolower($entry['value'])) {
        send_json(['ok' => true, 'message' => 'already on waitlist', 'duplicate' => true]);
    }
}

$list[] = $entry;
write_json_file(WAITLIST_FILE, $list);

send_json([
    'ok' => true,
    'message' => 'reserved',
    'position' => count($list),
]);
