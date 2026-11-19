<?php
declare(strict_types=1);

/**
 * ClaudePulse Public Self-Service Gateway Key Quota & Balance Inspector
 * Route: /verify or /check or /balance
 */

use ClaudePulse\Database;
use ClaudePulse\Crypto;

$db = Database::getConnection();

if (!empty($_GET['debug'])) {
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'last_400' => @json_decode(@file_get_contents(dirname(__DIR__) . '/database/last_400_error.json') ?: 'null'),
        'dump' => @json_decode(@file_get_contents(dirname(__DIR__) . '/database/debug_dump.json') ?: 'null'),
        'upstream' => @json_decode(@file_get_contents(dirname(__DIR__) . '/database/debug_upstream.json') ?: 'null')
    ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES);
    exit;
}

// Check if request is JSON API query
$isJson = (
    ($_GET['format'] ?? '') === 'json' ||
    str_contains($_SERVER['HTTP_ACCEPT'] ?? '', 'application/json') ||
    str_starts_with($_SERVER['REQUEST_URI'] ?? '', '/api/verify')
);

// Extract requested key from query param, POST body, or header
$inputKey = '';
if (!empty($_GET['key'])) {
    $inputKey = trim((string)$_GET['key']);
} elseif (!empty($_POST['key'])) {
    $inputKey = trim((string)$_POST['key']);
} else {
    $rawBody = @file_get_contents('php://input');
    if ($rawBody) {
        $json = @json_decode($rawBody, true);
        if (!empty($json['key'])) {
            $inputKey = trim((string)$json['key']);
        }
    }
}

if (empty($inputKey)) {
    // Check Authorization / X-API-Key headers
    $allHeaders = function_exists('getallheaders') ? getallheaders() : [];
    foreach ($allHeaders as $hKey => $hVal) {
        if (strtolower($hKey) === 'x-api-key') {
            $inputKey = trim($hVal);
            break;
        }
    }
}

// Function to resolve key telemetry data
function inspectGatewayKey(\PDO $db, string $keyInput): ?array {
    $keyInput = trim($keyInput);
    if (empty($keyInput)) {
        return null;
    }

    $keyHash = hash('sha256', $keyInput);
    $stmt = $db->prepare("
        SELECT id, name, api_key_prefix, cap_usd, spent_usd, cap_tokens, used_tokens,
               rate_limit_rpm, allowed_models, status, expires_at, total_requests,
               last_used_at, created_at
        FROM gateway_keys
        WHERE api_key_hash = :hash OR api_key_prefix = :prefix
        LIMIT 1
    ");
    $stmt->execute([':hash' => $keyHash, ':prefix' => $keyInput]);
    $k = $stmt->fetch();

    if (!$k) {
        return null;
    }

    $capUsd = (float)($k['cap_usd'] ?? 0.0);
    $spentUsd = (float)($k['spent_usd'] ?? 0.0);
    $remainingUsd = $capUsd > 0.0 ? max(0.0, $capUsd - $spentUsd) : null;
    $pctSpend = $capUsd > 0.0 ? min(100.0, round(($spentUsd / $capUsd) * 100, 1)) : 0.0;

    $capTok = (int)($k['cap_tokens'] ?? 0);
    $usedTok = (int)($k['used_tokens'] ?? 0);
    $remainingTok = $capTok > 0 ? max(0, $capTok - $usedTok) : null;
    $pctTok = $capTok > 0 ? min(100.0, round(($usedTok / $capTok) * 100, 1)) : 0.0;

    // Determine quota mode
    $quotaMode = 'unlimited';
    if ($capTok > 0 && $capUsd == 0.0) {
        $quotaMode = 'token';
    } elseif ($capUsd > 0.0 && $capTok == 0) {
        $quotaMode = 'usd';
    } elseif ($capTok > 0 && $capUsd > 0.0) {
        $quotaMode = 'dual';
    }

    // Expiration details
    $expiresAt = $k['expires_at'] ?? null;
    $expiresFormatted = 'Lifetime (No Expiry)';
    $isExpired = false;
    $expiresInHuman = null;
    if (!empty($expiresAt)) {
        $expireTs = strtotime($expiresAt);
        $diffSec = $expireTs - time();
        if ($diffSec <= 0) {
            $isExpired = true;
            $expiresFormatted = 'Expired (' . date('M j, Y H:i', $expireTs) . ')';
            $expiresInHuman = 'Expired';
        } else {
            $days = ceil($diffSec / 86400);
            if ($days <= 1) {
                $hours = max(1, (int)ceil($diffSec / 3600));
                $expiresInHuman = "{$hours}h left";
                $expiresFormatted = "Expires in {$hours}h (" . date('M j, Y H:i', $expireTs) . ')';
            } else {
                $expiresInHuman = "{$days}d left";
                $expiresFormatted = "Expires in {$days} days (" . date('M j, Y', $expireTs) . ')';
            }
        }
    }

    // Determine current live operational status
    $status = $k['status'];
    $statusMessage = 'Active';
    $statusType = 'active';

    if ($status !== 'active') {
        $statusMessage = 'Revoked';
        $statusType = 'revoked';
    } elseif ($isExpired) {
        $statusMessage = 'Expired';
        $statusType = 'expired';
    } elseif ($capTok > 0 && $usedTok >= $capTok) {
        $statusMessage = 'Token Quota Exceeded';
        $statusType = 'exceeded';
    } elseif ($capUsd > 0.0 && $spentUsd >= $capUsd) {
        $statusMessage = 'Spend Cap Exceeded';
        $statusType = 'exceeded';
    }

    // Token & Savings Breakdown from request_logs
    $stmtBreakdown = $db->prepare("
        SELECT 
            COALESCE(SUM(input_tokens), 0) as input_tokens,
            COALESCE(SUM(output_tokens), 0) as output_tokens,
            COALESCE(SUM(cache_read_input_tokens), 0) as cache_read_tokens,
            COALESCE(SUM(cache_creation_input_tokens), 0) as cache_creation_tokens,
            COALESCE(SUM(saved_usd), 0.0) as total_saved_usd
        FROM request_logs
        WHERE gateway_key_id = :gid
    ");
    $stmtBreakdown->execute([':gid' => $k['id']]);
    $breakdown = $stmtBreakdown->fetch() ?: [];

    // Recent 20 Request Logs
    $stmtLogs = $db->prepare("
        SELECT id, endpoint, model, status_code, is_streaming,
               input_tokens, output_tokens, cache_read_input_tokens,
               cost_usd, latency_ms, error_message, created_at
        FROM request_logs
        WHERE gateway_key_id = :gid
        ORDER BY id DESC
        LIMIT 20
    ");
    $stmtLogs->execute([':gid' => $k['id']]);
    $recentLogs = $stmtLogs->fetchAll() ?: [];

    // Masked Key display
    $maskedKey = $k['api_key_prefix'] . '••••••••';

    return [
        'id'            => (int)$k['id'],
        'name'          => $k['name'],
        'masked_key'    => $maskedKey,
        'prefix'        => $k['api_key_prefix'],
        'status'        => $statusMessage,
        'status_type'   => $statusType,
        'quota_mode'    => $quotaMode,
        'expiration'    => [
            'expires_at' => $expiresAt,
            'is_expired' => $isExpired,
            'formatted'  => $expiresFormatted,
            'human'      => $expiresInHuman,
        ],
        'tokens'        => [
            'cap'           => $capTok,
            'used'          => $usedTok,
            'remaining'     => $remainingTok,
            'percent_used'  => $pctTok,
            'is_unlimited'  => ($capTok === 0)
        ],
        'spend_usd'     => [
            'cap'           => round($capUsd, 2),
            'spent'         => round($spentUsd, 4),
            'remaining'     => $remainingUsd !== null ? round($remainingUsd, 4) : null,
            'percent_used'  => $pctSpend,
            'is_unlimited'  => ($capUsd === 0.0)
        ],
        'breakdown'     => [
            'input_tokens'          => (int)($breakdown['input_tokens'] ?? 0),
            'output_tokens'         => (int)($breakdown['output_tokens'] ?? 0),
            'cache_read_tokens'     => (int)($breakdown['cache_read_tokens'] ?? 0),
            'cache_creation_tokens' => (int)($breakdown['cache_creation_tokens'] ?? 0),
            'total_saved_usd'       => round((float)($breakdown['total_saved_usd'] ?? 0.0), 4),
        ],
        'limits'        => [
            'rate_limit_rpm' => (int)$k['rate_limit_rpm'],
            'allowed_models' => $k['allowed_models'] ?: 'All Models',
            'expires_at'     => $k['expires_at'],
        ],
        'activity'      => [
            'total_requests' => (int)$k['total_requests'],
            'last_used_at'   => $k['last_used_at'] ?: 'Never',
            'created_at'     => $k['created_at'],
        ],
        'recent_logs'   => $recentLogs
    ];
}

$inspectionResult = null;
$errorMessage = null;

if (!empty($inputKey)) {
    $inspectionResult = inspectGatewayKey($db, $inputKey);
    if (!$inspectionResult) {
        $errorMessage = 'Invalid or unrecognized Gateway API key. Please check the key prefix or full token.';
    }
}

// Return JSON if requested
if ($isJson) {
    header('Content-Type: application/json; charset=utf-8');
    header('Access-Control-Allow-Origin: *');
    if ($errorMessage) {
        http_response_code(404);
        echo json_encode(['success' => false, 'error' => $errorMessage]);
    } elseif ($inspectionResult) {
        echo json_encode(['success' => true, 'data' => $inspectionResult]);
    } else {
        http_response_code(400);
        echo json_encode(['success' => false, 'error' => 'No key parameter provided. Pass ?key=sk-cp-...']);
    }
    exit;
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Gateway Key Balance & Usage Inspector &mdash; ClaudePulse</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23059669' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='22 12 18 12 15 21 9 3 6 12 2 12'%3E%3C/polyline%3E%3C/svg%3E">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 500: '#10b981', 600: '#059669' }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen py-8 px-4 sm:px-6 dark:bg-[#090d16] dark:text-slate-100 transition-colors duration-150">

    <div class="max-w-4xl mx-auto space-y-6">

        <!-- Top Navigation Bar -->
        <header class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
            <a href="/" class="flex items-center space-x-2.5 group">
                <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center shadow-sm group-hover:bg-emerald-600 transition dark:bg-emerald-500 dark:text-slate-950">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
                <div>
                    <h1 class="font-bold text-base tracking-tight text-slate-900 dark:text-white">Claude<span class="text-emerald-600 dark:text-emerald-400">Pulse</span></h1>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Key Quota & Balance Inspector</p>
                </div>
            </a>

            <div class="flex items-center space-x-3">
                <a href="/docs" class="inline-flex items-center space-x-1.5 px-3 py-1 rounded-full text-xs font-medium bg-slate-100 text-slate-700 hover:bg-slate-200 transition dark:bg-slate-800 dark:text-slate-300 dark:hover:bg-slate-700">
                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 6.253v13m0-13C10.832 5.477 9.246 5 7.5 5S4.168 5.477 3 6.253v13C4.168 18.477 5.754 18 7.5 18s3.332.477 4.5 1.253m0-13C13.168 5.477 14.754 5 16.5 5c1.747 0 3.332.477 4.5 1.253v13C19.832 18.477 18.247 18 16.5 18c-1.746 0-3.332.477-4.5 1.253" />
                    </svg>
                    <span>Integration Docs</span>
                </a>
                <a href="/status" target="_blank" class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 pulse-live"></span>
                    <span class="hidden sm:inline">System Status</span>
                </a>
                <button id="theme-toggle-btn" type="button" class="p-2 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800 transition" title="Toggle Theme">
                    <svg class="w-4 h-4 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <svg class="w-4 h-4 block dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>
            </div>
        </header>

        <!-- Search / Input Card -->
        <div class="card-clean p-6">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Self-Service Balance & Quota Check</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Paste your ClaudePulse Gateway Key (<code class="font-mono text-emerald-600 dark:text-emerald-400">sk-cp-...</code>) or prefix below to inspect your remaining token quota, USD balance, and live telemetry logs.
                </p>
            </div>

            <form method="GET" action="/verify" class="flex flex-col sm:flex-row gap-2.5">
                <div class="relative flex-1">
                    <div class="absolute inset-y-0 left-0 pl-3.5 flex items-center pointer-events-none text-slate-400">
                        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                        </svg>
                    </div>
                    <input type="text" name="key" value="<?= htmlspecialchars($inputKey) ?>" 
                           placeholder="Enter sk-cp-... key" required
                           class="form-input pl-10 font-mono text-xs w-full">
                </div>
                <button type="submit" class="px-5 py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition shadow-sm dark:bg-emerald-600 dark:hover:bg-emerald-700 flex items-center justify-center space-x-1.5 flex-shrink-0">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z" />
                    </svg>
                    <span>Inspect Balance</span>
                </button>
            </form>

            <?php if ($errorMessage): ?>
            <div class="mt-4 p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-400 flex items-center space-x-2">
                <svg class="w-4 h-4 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 9v2m0 4h.01m-6.938 4h13.856c1.54 0 2.502-1.667 1.732-3L13.732 4c-.77-1.333-2.694-1.333-3.464 0L3.34 16c-.77 1.333.192 3 1.732 3z" />
                </svg>
                <span><?= htmlspecialchars($errorMessage) ?></span>
            </div>
            <?php endif; ?>
        </div>

        <?php if ($inspectionResult): ?>
        <!-- Key Metadata Header -->
        <div class="card-clean p-6">
            <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4">
                <div>
                    <div class="flex flex-wrap items-center gap-2">
                        <h2 class="text-xl font-bold text-slate-900 dark:text-white"><?= htmlspecialchars($inspectionResult['name']) ?></h2>
                        <?php
                            $badgeClass = match ($inspectionResult['status_type']) {
                                'active'   => 'badge-emerald',
                                'exceeded' => 'badge-rose',
                                'expired'  => 'badge-amber',
                                default    => 'badge-rose'
                            };
                        ?>
                        <span class="badge <?= $badgeClass ?> font-semibold"><?= htmlspecialchars($inspectionResult['status']) ?></span>

                        <?php if ($inspectionResult['quota_mode'] === 'token'): ?>
                            <span class="badge badge-emerald text-[11px] font-medium">🪙 Token Quota Key</span>
                        <?php elseif ($inspectionResult['quota_mode'] === 'usd'): ?>
                            <span class="badge badge-emerald text-[11px] font-medium">💵 USD Spend Cap Key</span>
                        <?php elseif ($inspectionResult['quota_mode'] === 'dual'): ?>
                            <span class="badge badge-indigo text-[11px] font-medium">⚙️ Dual Cap Key</span>
                        <?php else: ?>
                            <span class="badge badge-slate text-[11px] font-medium">♾️ Unlimited Key</span>
                        <?php endif; ?>

                        <?php if ($inspectionResult['expiration']['expires_at']): ?>
                            <span class="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded text-[11px] font-mono <?= $inspectionResult['expiration']['is_expired'] ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400' : 'bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300' ?>">
                                <span>⏳</span>
                                <span><?= htmlspecialchars($inspectionResult['expiration']['formatted']) ?></span>
                            </span>
                        <?php else: ?>
                            <span class="inline-flex items-center space-x-1 px-2.5 py-0.5 rounded text-[11px] font-mono bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                                <span>♾️</span>
                                <span>Lifetime (No Expiry)</span>
                            </span>
                        <?php endif; ?>
                    </div>
                    <p class="text-xs text-slate-500 dark:text-slate-400 mt-2 font-mono">
                        Key Prefix: <span class="text-slate-800 dark:text-slate-200 font-bold"><?= htmlspecialchars($inspectionResult['prefix']) ?></span> &bull; 
                        Created: <?= htmlspecialchars(substr($inspectionResult['activity']['created_at'], 0, 10)) ?> &bull; 
                        Last Used: <?= htmlspecialchars($inspectionResult['activity']['last_used_at']) ?>
                    </p>
                </div>

                <div class="text-left sm:text-right">
                    <div class="text-[11px] text-slate-400 uppercase tracking-wider font-semibold">Total Requests</div>
                    <div class="text-2xl font-bold font-mono text-slate-900 dark:text-white">
                        <?= number_format($inspectionResult['activity']['total_requests']) ?>
                    </div>
                </div>
            </div>
        </div>

        <!-- Primary Quota & Balance Cards -->
        <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
            
            <!-- Card 1: Token Quota Limit -->
            <div class="card-clean p-5 relative overflow-hidden <?= $inspectionResult['quota_mode'] === 'token' ? 'ring-2 ring-emerald-500/30' : '' ?>">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 flex items-center space-x-1.5">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                        </svg>
                        <span>Token Quota Balance</span>
                    </span>
                    <?php if ($inspectionResult['quota_mode'] === 'usd'): ?>
                        <span class="text-[11px] font-mono font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            Tracking Only (USD Capped)
                        </span>
                    <?php elseif ($inspectionResult['tokens']['is_unlimited']): ?>
                        <span class="text-[11px] font-mono font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            Unlimited Tokens
                        </span>
                    <?php else: ?>
                        <span class="text-[11px] font-mono font-semibold px-2 py-0.5 rounded <?= $inspectionResult['tokens']['percent_used'] >= 100 ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' ?>">
                            <?= $inspectionResult['tokens']['percent_used'] ?>% Used
                        </span>
                    <?php endif; ?>
                </div>

                <div class="mt-2 mb-3">
                    <div class="flex items-baseline space-x-2">
                        <span class="text-2xl font-bold font-mono text-slate-900 dark:text-white">
                            <?= number_format($inspectionResult['tokens']['used']) ?>
                        </span>
                        <span class="text-xs text-slate-400 font-mono">
                            / <?= $inspectionResult['tokens']['is_unlimited'] ? '&infin;' : number_format($inspectionResult['tokens']['cap']) ?> Tokens
                        </span>
                    </div>
                    <?php if (!$inspectionResult['tokens']['is_unlimited']): ?>
                    <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-1 font-mono">
                        &bull; <?= number_format($inspectionResult['tokens']['remaining']) ?> tokens remaining
                    </p>
                    <?php elseif ($inspectionResult['quota_mode'] === 'usd'): ?>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                        &bull; Key is capped by dollar spend ($<?= number_format($inspectionResult['spend_usd']['cap'], 2) ?> USD), tokens are unlimited.
                    </p>
                    <?php else: ?>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                        &bull; Unlimited tokens allowance.
                    </p>
                    <?php endif; ?>
                </div>

                <?php if (!$inspectionResult['tokens']['is_unlimited']): 
                    $tPct = $inspectionResult['tokens']['percent_used'];
                    $tColor = $tPct >= 100 ? 'bg-rose-500' : ($tPct >= 80 ? 'bg-amber-500' : 'bg-emerald-500');
                ?>
                <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden dark:bg-slate-800 mt-2">
                    <div class="<?= $tColor ?> h-2 rounded-full transition-all duration-500" style="width: <?= $tPct ?>%"></div>
                </div>
                <?php endif; ?>
            </div>

            <!-- Card 2: USD Spend Cap -->
            <div class="card-clean p-5 relative overflow-hidden <?= $inspectionResult['quota_mode'] === 'usd' ? 'ring-2 ring-emerald-500/30' : '' ?>">
                <div class="flex items-center justify-between mb-2">
                    <span class="text-xs font-semibold text-slate-500 dark:text-slate-400 flex items-center space-x-1.5">
                        <svg class="w-4 h-4 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                        </svg>
                        <span>Spend Cap ($ USD)</span>
                    </span>
                    <?php if ($inspectionResult['quota_mode'] === 'token'): ?>
                        <span class="text-[11px] font-mono font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            Tracking Only (Token Capped)
                        </span>
                    <?php elseif ($inspectionResult['spend_usd']['is_unlimited']): ?>
                        <span class="text-[11px] font-mono font-semibold px-2 py-0.5 rounded bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-300">
                            Unlimited Spend
                        </span>
                    <?php else: ?>
                        <span class="text-[11px] font-mono font-semibold px-2 py-0.5 rounded <?= $inspectionResult['spend_usd']['percent_used'] >= 100 ? 'bg-rose-50 text-rose-600 dark:bg-rose-500/10 dark:text-rose-400' : 'bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400' ?>">
                            <?= $inspectionResult['spend_usd']['percent_used'] ?>% Spent
                        </span>
                    <?php endif; ?>
                </div>

                <div class="mt-2 mb-3">
                    <div class="flex items-baseline space-x-2">
                        <span class="text-2xl font-bold font-mono text-slate-900 dark:text-white">
                            $<?= number_format($inspectionResult['spend_usd']['spent'], 4) ?>
                        </span>
                        <span class="text-xs text-slate-400 font-mono">
                            / <?= $inspectionResult['spend_usd']['is_unlimited'] ? '&infin;' : '$' . number_format($inspectionResult['spend_usd']['cap'], 2) ?> USD
                        </span>
                    </div>
                    <?php if (!$inspectionResult['spend_usd']['is_unlimited']): ?>
                    <p class="text-[11px] text-emerald-600 dark:text-emerald-400 mt-1 font-mono">
                        &bull; $<?= number_format($inspectionResult['spend_usd']['remaining'], 4) ?> USD remaining
                    </p>
                    <?php elseif ($inspectionResult['quota_mode'] === 'token'): ?>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                        &bull; Key is capped by token quota (<?= number_format($inspectionResult['tokens']['cap']) ?> tokens), USD spend is tracking only.
                    </p>
                    <?php else: ?>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">
                        &bull; Unlimited USD spend allowance.
                    </p>
                    <?php endif; ?>
                </div>

                <?php if (!$inspectionResult['spend_usd']['is_unlimited']): 
                    $sPct = $inspectionResult['spend_usd']['percent_used'];
                    $sColor = $sPct >= 100 ? 'bg-rose-500' : ($sPct >= 80 ? 'bg-amber-500' : 'bg-emerald-500');
                ?>
                <div class="w-full bg-slate-100 rounded-full h-2 overflow-hidden dark:bg-slate-800 mt-2">
                    <div class="<?= $sColor ?> h-2 rounded-full transition-all duration-500" style="width: <?= $sPct ?>%"></div>
                </div>
                <?php endif; ?>
            </div>

        </div>

        <!-- Token Breakdown Details & Optimization -->
        <div class="grid grid-cols-2 sm:grid-cols-4 gap-3">
            <div class="card-clean p-4">
                <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Input Tokens</div>
                <div class="text-lg font-bold font-mono text-slate-900 dark:text-white mt-1">
                    <?= number_format($inspectionResult['breakdown']['input_tokens']) ?>
                </div>
            </div>
            <div class="card-clean p-4">
                <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Output Tokens</div>
                <div class="text-lg font-bold font-mono text-slate-900 dark:text-white mt-1">
                    <?= number_format($inspectionResult['breakdown']['output_tokens']) ?>
                </div>
            </div>
            <div class="card-clean p-4">
                <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium flex items-center justify-between">
                    <span>Cache Reads</span>
                    <span class="text-[9px] px-1 rounded bg-emerald-100 text-emerald-800 dark:bg-emerald-900/50 dark:text-emerald-300 font-bold">-90%</span>
                </div>
                <div class="text-lg font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-1">
                    <?= number_format($inspectionResult['breakdown']['cache_read_tokens']) ?>
                </div>
            </div>
            <div class="card-clean p-4">
                <div class="text-[11px] text-slate-500 dark:text-slate-400 font-medium">Cache Savings</div>
                <div class="text-lg font-bold font-mono text-emerald-600 dark:text-emerald-400 mt-1">
                    +$<?= number_format($inspectionResult['breakdown']['total_saved_usd'], 4) ?>
                </div>
            </div>
        </div>

        <!-- Allowed Models & Policy -->
        <div class="card-clean p-4 flex flex-col sm:flex-row items-start sm:items-center justify-between gap-3 text-xs">
            <div>
                <span class="text-slate-500 dark:text-slate-400">Rate Limit:</span>
                <span class="font-mono font-bold text-slate-800 dark:text-slate-200 ml-1">
                    <?= $inspectionResult['limits']['rate_limit_rpm'] > 0 ? $inspectionResult['limits']['rate_limit_rpm'] . ' req/min' : 'Unlimited' ?>
                </span>
            </div>
            <div>
                <span class="text-slate-500 dark:text-slate-400">Allowed Models:</span>
                <span class="font-mono text-emerald-700 dark:text-emerald-400 font-semibold ml-1">
                    <?= htmlspecialchars($inspectionResult['limits']['allowed_models']) ?>
                </span>
            </div>
        </div>

        <!-- Recent 20 Request Logs Table -->
        <div class="card-clean overflow-hidden">
            <div class="p-4 border-b border-slate-200 dark:border-slate-800 flex items-center justify-between">
                <div>
                    <h3 class="text-sm font-bold text-slate-900 dark:text-white">Recent Request Logs</h3>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Last 20 API requests executed with this key.</p>
                </div>
                <span class="text-xs font-mono text-slate-400"><?= count($inspectionResult['recent_logs']) ?> events</span>
            </div>

            <div class="overflow-x-auto">
                <table class="w-full text-left text-xs">
                    <thead>
                        <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400">
                            <th class="py-2.5 px-3 font-semibold">Timestamp</th>
                            <th class="py-2.5 px-3 font-semibold">Status</th>
                            <th class="py-2.5 px-3 font-semibold">Model</th>
                            <th class="py-2.5 px-3 font-semibold text-right">In Tokens</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Out Tokens</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Cost</th>
                            <th class="py-2.5 px-3 font-semibold text-right">Latency</th>
                        </tr>
                    </thead>
                    <tbody class="divide-y divide-slate-100 dark:divide-slate-800/60 font-mono text-[11px]">
                        <?php if (empty($inspectionResult['recent_logs'])): ?>
                        <tr>
                            <td colspan="7" class="py-8 text-center text-slate-400 font-sans text-xs">
                                No requests recorded yet for this gateway key.
                            </td>
                        </tr>
                        <?php else: ?>
                        <?php foreach ($inspectionResult['recent_logs'] as $log): 
                            $isSuccess = ((int)$log['status_code'] === 200);
                            $statusBadge = $isSuccess ? 'badge-emerald' : 'badge-rose';
                        ?>
                        <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/30 transition">
                            <td class="py-2.5 px-3 text-slate-500 whitespace-nowrap">
                                <?= htmlspecialchars($log['created_at']) ?>
                            </td>
                            <td class="py-2.5 px-3">
                                <span class="badge <?= $statusBadge ?> text-[10px]">
                                    <?= htmlspecialchars((string)$log['status_code']) ?>
                                </span>
                            </td>
                            <td class="py-2.5 px-3 font-semibold text-slate-800 dark:text-slate-200">
                                <?= htmlspecialchars($log['model'] ?: 'claude') ?>
                            </td>
                            <td class="py-2.5 px-3 text-right text-slate-600 dark:text-slate-400">
                                <?= number_format((int)$log['input_tokens']) ?>
                            </td>
                            <td class="py-2.5 px-3 text-right text-slate-600 dark:text-slate-400">
                                <?= number_format((int)$log['output_tokens']) ?>
                            </td>
                            <td class="py-2.5 px-3 text-right text-emerald-600 dark:text-emerald-400 font-semibold">
                                $<?= number_format((float)$log['cost_usd'], 5) ?>
                            </td>
                            <td class="py-2.5 px-3 text-right text-slate-500">
                                <?= (int)$log['latency_ms'] ?>ms
                            </td>
                        </tr>
                        <?php endforeach; ?>
                        <?php endif; ?>
                    </tbody>
                </table>
            </div>
        </div>
        <?php endif; ?>

        <!-- Footer -->
        <footer class="text-center text-xs text-slate-400 py-4">
            ClaudePulse Self-Hosted API Gateway &bull; <a href="/docs" class="hover:text-emerald-500 transition underline">Documentation</a> &bull; <a href="/status" class="hover:text-emerald-500 transition underline">Status</a>
        </footer>

    </div>

    <script>
        // Theme Toggle Support
        const themeBtn = document.getElementById('theme-toggle-btn');
        if (themeBtn) {
            themeBtn.addEventListener('click', () => {
                const isDark = document.documentElement.classList.toggle('dark');
                localStorage.setItem('claudepulse_theme', isDark ? 'dark' : 'light');
            });
            const savedTheme = localStorage.getItem('claudepulse_theme');
            if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                document.documentElement.classList.add('dark');
            } else {
                document.documentElement.classList.remove('dark');
            }
        }
    </script>
</body>
</html>

