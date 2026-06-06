<?php
/**
 * POST /api/verify-holder.php
 * Body: { "wallet": "..." }
 *
 * Checks on-chain balance of $PHZL for given wallet.
 * Returns: { ok, is_holder, balance, threshold }
 */

require_once __DIR__ . '/../config.php';

handle_preflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['ok' => false, 'error' => 'method not allowed'], 405);
}

$input = json_decode(file_get_contents('php://input'), true);
$wallet = sanitize_solana_address($input['wallet'] ?? '');

if (!$wallet) {
    send_json(['ok' => false, 'error' => 'invalid wallet'], 400);
}

// If token not launched yet - everyone is a "pre-holder" if they signed up
if (empty(PHZL_MINT_ADDRESS)) {
    send_json([
        'ok' => true,
        'is_holder' => false,
        'balance' => 0,
        'threshold' => MIN_HOLDER_BALANCE,
        'status' => 'token_not_launched',
        'message' => '$PHZL not launched yet. Wallet noted on waitlist.',
    ]);
}

// Call Solana RPC - getTokenAccountsByOwner filtered by mint
$rpcBody = [
    'jsonrpc' => '2.0',
    'id' => 1,
    'method' => 'getTokenAccountsByOwner',
    'params' => [
        $wallet,
        ['mint' => PHZL_MINT_ADDRESS],
        ['encoding' => 'jsonParsed'],
    ],
];

$ch = curl_init(SOLANA_RPC);
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($rpcBody),
    CURLOPT_HTTPHEADER => ['Content-Type: application/json'],
    CURLOPT_TIMEOUT => 15,
]);
$response = curl_exec($ch);
$err = curl_error($ch);
curl_close($ch);

if ($err || !$response) {
    send_json(['ok' => false, 'error' => 'rpc unreachable'], 502);
}

$data = json_decode($response, true);
$accounts = $data['result']['value'] ?? [];

$balance = 0;
foreach ($accounts as $acc) {
    $amt = $acc['account']['data']['parsed']['info']['tokenAmount']['uiAmount'] ?? 0;
    $balance += floatval($amt);
}

$isHolder = $balance >= MIN_HOLDER_BALANCE;

send_json([
    'ok' => true,
    'is_holder' => $isHolder,
    'balance' => $balance,
    'threshold' => MIN_HOLDER_BALANCE,
    'wallet' => $wallet,
]);
