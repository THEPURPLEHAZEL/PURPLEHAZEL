<?php
/**
 * POST /api/chat.php
 * Body: {
 *   "agent": "market_scanner" | "code_runner" | "signal_hunter" | ... ,
 *   "messages": [ {role, content}, ... ],
 *   "wallet": "...optional"
 * }
 *
 * Returns: { ok, reply, agent, usage }
 */

require_once __DIR__ . '/../config.php';

handle_preflight();

if ($_SERVER['REQUEST_METHOD'] !== 'POST') {
    send_json(['ok' => false, 'error' => 'method not allowed'], 405);
}

if (!defined('CLAUDE_API_KEY') || CLAUDE_API_KEY === 'sk-ant-YOUR-KEY-HERE' || empty(CLAUDE_API_KEY)) {
    send_json(['ok' => false, 'error' => 'CLAUDE_API_KEY not configured in config.php'], 500);
}

// Rate limit
if (!rate_limit_check('chat_' . client_ip(), RATE_LIMIT_PER_HOUR)) {
    send_json(['ok' => false, 'error' => 'rate limit exceeded - try again in an hour'], 429);
}

$input = json_decode(file_get_contents('php://input'), true);
if (!is_array($input)) send_json(['ok' => false, 'error' => 'invalid body'], 400);

$agent = $input['agent'] ?? 'orchestrator';
$messages = $input['messages'] ?? [];

if (!is_array($messages) || count($messages) === 0) {
    send_json(['ok' => false, 'error' => 'messages required'], 400);
}

// Length guard
foreach ($messages as $msg) {
    $content = is_string($msg['content']) ? $msg['content'] : '';
    if (strlen($content) > MAX_MESSAGE_LENGTH) {
        send_json(['ok' => false, 'error' => 'message too long'], 400);
    }
}

/* ============================================================
   AGENT PERSONAS - each agent has its own system prompt.
   These define the PURPLEHAZEL agents' behaviors.
   ============================================================ */
$AGENTS = [
    'market_scanner' => [
        'name' => 'Market Scanner',
        'system' => 'You are Market Scanner, one of the seven PURPLEHAZEL agents. Your job is to analyze crypto markets, token metrics, and provide concise signal reports. Always include: sentiment (bullish/bearish/neutral), key metrics, risk level (low/med/high). Be sharp and direct - no fluff. Format responses in short sections with clear headers.',
    ],
    'code_runner' => [
        'name' => 'Code Runner',
        'system' => 'You are Code Runner, one of the seven PURPLEHAZEL agents. You write, review, and debug code - especially Solana/Web3 smart contracts, TypeScript agents, and PHP backends. Always include working code examples. Prefer concise solutions over verbose explanations.',
    ],
    'signal_hunter' => [
        'name' => 'Signal Hunter',
        'system' => 'You are Signal Hunter, one of the seven PURPLEHAZEL agents. You find alpha in noise - looking for emerging narratives, unusual on-chain activity, and overlooked opportunities. Present findings as: signal, evidence, confidence level, recommended action.',
    ],
    'social_monitor' => [
        'name' => 'Social Monitor',
        'system' => 'You are Social Monitor, one of the seven PURPLEHAZEL agents. You analyze social sentiment across Twitter/X, Telegram, and Discord. Identify emerging narratives, influential voices, and shifts in community mood. Be specific and quote indicative phrases where helpful.',
    ],
    'research_agent' => [
        'name' => 'Research Agent',
        'system' => 'You are Research Agent, one of the seven PURPLEHAZEL agents. You conduct deep research on topics - technical, market, competitive. Structure responses with: summary, key findings, sources/evidence, open questions. Intellectually honest about uncertainty.',
    ],
    'action_executor' => [
        'name' => 'Action Executor',
        'system' => 'You are Action Executor, one of the seven PURPLEHAZEL agents. You turn plans into concrete step-by-step action playbooks. Every response ends with a numbered action list. No philosophy - just what to do, in what order, with what tools.',
    ],
    'orchestrator' => [
        'name' => 'Orchestrator',
        'system' => 'You are the Orchestrator, the coordinator of the seven PURPLEHAZEL agents. You route tasks to the right specialist agent, synthesize multi-agent outputs, and maintain the overall plan. Be strategic and high-level. When the user asks a question, either answer directly or suggest which specialist agent(s) to consult.',
    ],
];

$agentKey = array_key_exists($agent, $AGENTS) ? $agent : 'orchestrator';
$agentConfig = $AGENTS[$agentKey];

// Build Anthropic API payload
$payload = [
    'model' => CLAUDE_MODEL,
    'max_tokens' => 1500,
    'system' => $agentConfig['system'],
    'messages' => array_values(array_filter($messages, function($m) {
        return isset($m['role']) && isset($m['content']) && in_array($m['role'], ['user', 'assistant']);
    })),
];

// Call Anthropic API
$ch = curl_init('https://api.anthropic.com/v1/messages');
curl_setopt_array($ch, [
    CURLOPT_RETURNTRANSFER => true,
    CURLOPT_POST => true,
    CURLOPT_POSTFIELDS => json_encode($payload),
    CURLOPT_HTTPHEADER => [
        'Content-Type: application/json',
        'x-api-key: ' . CLAUDE_API_KEY,
        'anthropic-version: 2023-06-01',
    ],
    CURLOPT_TIMEOUT => 60,
]);

$response = curl_exec($ch);
$httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
$curlError = curl_error($ch);
curl_close($ch);

if ($curlError) {
    send_json(['ok' => false, 'error' => 'upstream connection failed: ' . $curlError], 502);
}

$data = json_decode($response, true);

if ($httpCode !== 200) {
    $err = $data['error']['message'] ?? $response;
    send_json(['ok' => false, 'error' => 'claude api error: ' . $err, 'code' => $httpCode], 502);
}

// Extract text from response
$reply = '';
if (isset($data['content']) && is_array($data['content'])) {
    foreach ($data['content'] as $block) {
        if (($block['type'] ?? '') === 'text') {
            $reply .= $block['text'];
        }
    }
}

send_json([
    'ok' => true,
    'reply' => $reply,
    'agent' => $agentKey,
    'agent_name' => $agentConfig['name'],
    'usage' => $data['usage'] ?? null,
]);
