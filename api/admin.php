<?php
/**
 * GET /api/admin.php?key=ADMIN_KEY
 *
 * Returns full admin data: users, waitlist, sessions, stats.
 */

require_once __DIR__ . '/../config.php';
handle_preflight();
require_admin();

$users = read_json_file(USERS_FILE);
$waitlist = read_json_file(WAITLIST_FILE);
$sessions = read_json_file(SESSIONS_FILE);

// Clean expired sessions
$activeSessions = array_values(array_filter($sessions, fn($s) => $s['expires'] > time()));

// Stats
$emailUsers = array_filter($users, fn($u) => !empty($u['email']));
$walletUsers = array_filter($users, fn($u) => !empty($u['wallet']));
$emailWaitlist = array_filter($waitlist, fn($w) => $w['type'] === 'email');
$walletWaitlist = array_filter($waitlist, fn($w) => $w['type'] === 'wallet');

// Last 24h signups
$dayAgo = time() - 86400;
$recentUsers = array_filter($users, fn($u) => strtotime($u['created_at']) > $dayAgo);

send_json([
    'ok' => true,
    'stats' => [
        'total_users' => count($users),
        'email_users' => count($emailUsers),
        'wallet_users' => count($walletUsers),
        'active_sessions' => count($activeSessions),
        'waitlist_total' => count($waitlist),
        'waitlist_emails' => count($emailWaitlist),
        'waitlist_wallets' => count($walletWaitlist),
        'signups_24h' => count($recentUsers),
    ],
    'users' => array_map(fn($u) => [
        'email' => $u['email'],
        'wallet' => $u['wallet'] ? substr($u['wallet'], 0, 4) . '...' . substr($u['wallet'], -4) : null,
        'tier' => $u['tier'] ?? 'free',
        'login_count' => $u['login_count'] ?? 0,
        'created_at' => $u['created_at'],
        'last_login' => $u['last_login'] ?? null,
    ], $users),
    'recent_waitlist' => array_slice(array_reverse($waitlist), 0, 50),
]);
