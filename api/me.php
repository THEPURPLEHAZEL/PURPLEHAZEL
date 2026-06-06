<?php
/**
 * GET /api/me.php
 * Header: Authorization: Bearer <token>
 *
 * Returns current user info if logged in.
 */

require_once __DIR__ . '/../config.php';
handle_preflight();

$session = authenticate();

if (!$session) {
    send_json(['ok' => false, 'logged_in' => false], 401);
}

send_json([
    'ok' => true,
    'logged_in' => true,
    'email' => $session['email'] ?? null,
    'wallet' => $session['wallet'] ?? null,
    'expires' => $session['expires'],
]);
