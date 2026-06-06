<?php
/**
 * POST /api/otp-send.php
 * Body: { "email": "user@example.com" }
 *
 * Generates OTP, stores it, sends via SMTP.
 * For waitlist AND login.
 */

require_once __DIR__ . '/../config.php';
handle_preflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['ok' => false, 'error' => 'method not allowed'], 405);
}

// Rate limit: 5 OTP sends per hour per IP
if (!rate_limit_check('otp_' . client_ip(), 5)) {
    send_json(['ok' => false, 'error' => 'too many requests - try again later'], 429);
}

$input = json_decode(file_get_contents('php://input'), true);
$email = sanitize_email($input['email'] ?? '');

if (!$email) {
    send_json(['ok' => false, 'error' => 'invalid email address'], 400);
}

// Block disposable email domains
$disposable = ['tempmail.com','guerrillamail.com','throwaway.email','mailinator.com',
    'yopmail.com','trashmail.com','fakeinbox.com','sharklasers.com','guerrillamailblock.com',
    'grr.la','dispostable.com','maildrop.cc','10minutemail.com'];
$domain = substr($email, strrpos($email, '@') + 1);
if (in_array($domain, $disposable)) {
    send_json(['ok' => false, 'error' => 'disposable email not allowed'], 400);
}

$otp = generate_otp();
$expires = time() + OTP_EXPIRY;

// Store OTP
$otps = read_json_file(OTP_FILE);
// Remove expired + existing for this email
$otps = array_values(array_filter($otps, fn($o) => $o['expires'] > time() && $o['email'] !== $email));
$otps[] = [
    'email' => $email,
    'code' => password_hash($otp, PASSWORD_BCRYPT),
    'expires' => $expires,
    'ip' => client_ip(),
    'created_at' => date('c'),
];
write_json_file(OTP_FILE, $otps);

// Send email via SMTP using cURL (no PHPMailer needed)
$sent = send_otp_email($email, $otp);

if ($sent) {
    send_json(['ok' => true, 'message' => 'OTP sent to your email', 'expires_in' => OTP_EXPIRY]);
} else {
    send_json(['ok' => false, 'error' => 'failed to send email - try again'], 500);
}

function send_otp_email($to, $otp) {
    $subject = "PURPLEHAZEL - Your verification code";
    $body = "
    <div style='font-family:monospace;max-width:480px;margin:0 auto;padding:32px;background:#0a0a12;color:#f8fafc;border:1px solid #222;border-radius:8px;'>
        <div style='text-align:center;margin-bottom:24px;'>
            <h1 style='color:#c425e3;font-size:24px;margin:0;'>PURPLEHAZEL</h1>
        </div>
        <p style='color:#8b949e;font-size:14px;line-height:1.6;'>Your verification code is:</p>
        <div style='text-align:center;margin:24px 0;'>
            <span style='font-size:36px;font-weight:700;letter-spacing:8px;color:#c425e3;background:#1a1426;padding:16px 32px;border-radius:8px;border:1px solid #333;display:inline-block;'>$otp</span>
        </div>
        <p style='color:#8b949e;font-size:12px;line-height:1.6;'>This code expires in 5 minutes. If you didn't request this, ignore this email.</p>
        <div style='margin-top:24px;padding-top:16px;border-top:1px solid #222;text-align:center;'>
            <span style='color:#555;font-size:11px;'>PURPLEHAZEL - Autonomous Agent Infrastructure</span>
        </div>
    </div>";

    // Prefer authenticated SMTP (reliable on Hostinger). Falls back to mail().
    if (defined('SMTP_PASS') && SMTP_PASS && SMTP_PASS !== 'YOUR_EMAIL_PASSWORD') {
        if (smtp_send_mail($to, $subject, $body)) return true;
    }

    $headers  = "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "From: " . SMTP_FROM_NAME . " <" . SMTP_USER . ">\r\n";
    return @mail($to, $subject, $body, $headers, "-f" . SMTP_USER);
}

/**
 * Minimal authenticated SMTP client (no PHPMailer needed).
 * Works with Hostinger: smtp.hostinger.com, port 465 (SSL) or 587 (STARTTLS).
 */
function smtp_send_mail($to, $subject, $html) {
    $host = SMTP_HOST;
    $port = (int) SMTP_PORT;
    $user = SMTP_USER;
    $pass = SMTP_PASS;
    $fromName = SMTP_FROM_NAME;

    $transport = ($port === 465) ? "ssl://{$host}" : $host;
    $errno = 0; $errstr = '';
    $fp = @fsockopen($transport, $port, $errno, $errstr, 20);
    if (!$fp) { error_log("SMTP connect failed: $errstr ($errno)"); return false; }
    stream_set_timeout($fp, 20);

    $read = function () use ($fp) {
        $data = '';
        while (($line = fgets($fp, 515)) !== false) {
            $data .= $line;
            if (isset($line[3]) && $line[3] === ' ') break;
        }
        return $data;
    };
    $put = function ($cmd) use ($fp) { fwrite($fp, $cmd . "\r\n"); };
    $code = function ($resp) { return substr($resp, 0, 3); };

    if ($code($read()) !== '220') { fclose($fp); return false; }

    $put("EHLO purplehazel.xyz");
    if ($code($read()) !== '250') {
        $put("HELO purplehazel.xyz");
        if ($code($read()) !== '250') { fclose($fp); return false; }
    }

    if ($port === 587) { // STARTTLS upgrade
        $put("STARTTLS");
        if ($code($read()) !== '220') { fclose($fp); return false; }
        if (!stream_socket_enable_crypto($fp, true, STREAM_CRYPTO_METHOD_TLS_CLIENT)) { fclose($fp); return false; }
        $put("EHLO purplehazel.xyz"); $read();
    }

    $put("AUTH LOGIN");
    if ($code($read()) !== '334') { fclose($fp); return false; }
    $put(base64_encode($user));
    if ($code($read()) !== '334') { fclose($fp); return false; }
    $put(base64_encode($pass));
    if ($code($read()) !== '235') { error_log("SMTP auth failed"); fclose($fp); return false; }

    $put("MAIL FROM:<{$user}>");
    if ($code($read()) !== '250') { fclose($fp); return false; }
    $put("RCPT TO:<{$to}>");
    $rc = $code($read());
    if ($rc !== '250' && $rc !== '251') { fclose($fp); return false; }
    $put("DATA");
    if ($code($read()) !== '354') { fclose($fp); return false; }

    $headers  = "From: {$fromName} <{$user}>\r\n";
    $headers .= "To: <{$to}>\r\n";
    $headers .= "Subject: {$subject}\r\n";
    $headers .= "Reply-To: {$user}\r\n";
    $headers .= "MIME-Version: 1.0\r\n";
    $headers .= "Content-Type: text/html; charset=UTF-8\r\n";
    $headers .= "Date: " . date('r') . "\r\n";

    $data = preg_replace('/^\./m', '..', $html); // dot-stuffing
    $put($headers . "\r\n" . $data . "\r\n.");
    if ($code($read()) !== '250') { fclose($fp); return false; }

    $put("QUIT");
    fclose($fp);
    return true;
}
