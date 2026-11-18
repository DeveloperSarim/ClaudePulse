<?php
declare(strict_types=1);

/**
 * ClaudePulse High-Performance Streaming API Gateway Proxy Engine
 * Supports Claude Code, SSE Streaming, Prompt Caching, Hard Caps & Token Telemetry
 */

require_once __DIR__ . '/../config.php';

use ClaudePulse\Database;
use ClaudePulse\Crypto;
use ClaudePulse\Pricing;
use ClaudePulse\Auth;

// Prevent PHP execution timeout on long streaming interactions
@set_time_limit(600);
@ini_set('max_execution_time', '600');

// Disable output buffering for zero-latency SSE delivery
if (function_exists('apache_setenv')) {
    @apache_setenv('no-gzip', '1');
}
@ini_set('zlib.output_compression', '0');
@ini_set('output_buffering', 'Off');
@ini_set('implicit_flush', '1');
while (ob_get_level() > 0) {
    @ob_end_flush();
}
ob_implicit_flush(true);

$startTime = microtime(true);
$db = Database::getConnection();

// -------------------------------------------------------------
// 1. Resolve Target Endpoint
// -------------------------------------------------------------
$requestUri = $_SERVER['REQUEST_URI'] ?? '/v1/messages';
$parsedPath = parse_url($requestUri, PHP_URL_PATH) ?: '/v1/messages';

// Strip potential subdirectory installation path
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_ends_with($scriptName, '.php') ? dirname($scriptName) : '';
$basePath = rtrim(str_replace('\\', '/', $scriptDir), '/');
if ($basePath !== '' && $basePath !== '.' && $basePath !== '/') {
    if (str_starts_with($parsedPath, $basePath)) {
        $parsedPath = substr($parsedPath, strlen($basePath));
    }
}
$endpoint = '/' . ltrim($parsedPath, '/');

// -------------------------------------------------------------
// 2. Extract and Validate Gateway API Key (sk-cp-...)
// -------------------------------------------------------------
$rawGatewayKey = '';
$allHeaders = function_exists('getallheaders') ? getallheaders() : [];

// Check headers (case-insensitive)
foreach ($allHeaders as $hKey => $hVal) {
    $hKeyLower = strtolower($hKey);
    if ($hKeyLower === 'x-api-key') {
        $rawGatewayKey = trim($hVal);
        break;
    }
    if ($hKeyLower === 'authorization' && preg_match('/Bearer\s+(sk-cp-[a-zA-Z0-9_\-]+)/i', $hVal, $matches)) {
        $rawGatewayKey = trim($matches[1]);
        break;
    }
}

// Fallback to server env vars on shared hosting
if (empty($rawGatewayKey)) {
    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        $rawGatewayKey = trim($_SERVER['HTTP_X_API_KEY']);
    } elseif (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+(sk-cp-[a-zA-Z0-9_\-]+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $rawGatewayKey = trim($matches[1]);
    }
}

// Special check: Public or authenticated /v1/models endpoint
$isModelsEndpoint = ($endpoint === '/v1/models' || str_starts_with($endpoint, '/v1/models/'));

if ($isModelsEndpoint && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // Check if we can fetch live models from Anthropic upstream
    $stmtLiveUpstream = $db->query("SELECT api_key_encrypted FROM upstream_keys WHERE is_active = 1 ORDER BY priority ASC LIMIT 1");
    $liveUpstreamRow = $stmtLiveUpstream->fetch();

    if ($liveUpstreamRow) {
        try {
            $liveUpstreamKey = Crypto::decrypt($liveUpstreamRow['api_key_encrypted']);
            $chModels = curl_init('https://api.anthropic.com/v1/models');
            curl_setopt($chModels, CURLOPT_HTTPHEADER, [
                'x-api-key: ' . $liveUpstreamKey,
                'anthropic-version: 2023-06-01'
            ]);
            curl_setopt($chModels, CURLOPT_RETURNTRANSFER, true);
            curl_setopt($chModels, CURLOPT_CONNECTTIMEOUT, 5);
            curl_setopt($chModels, CURLOPT_TIMEOUT, 8);
            $liveResp = curl_exec($chModels);
            $liveCode = curl_getinfo($chModels, CURLINFO_HTTP_CODE);
            curl_close($chModels);

            if ($liveCode === 200 && $liveResp) {
                $liveData = json_decode($liveResp, true);
                if (!empty($liveData['data']) && is_array($liveData['data'])) {
                    jsonResponse($liveData);
                }
            }
        } catch (\Exception $e) {
            // Fall back to local catalog
        }
    }

    jsonResponse(['data' => Pricing::getKnownModels(), 'has_more' => false]);
}

$keyHash = hash('sha256', $rawGatewayKey);
$stmtKey = $db->prepare("SELECT * FROM gateway_keys WHERE api_key_hash = :hash LIMIT 1");
$stmtKey->execute([':hash' => $keyHash]);
$gatewayKey = $stmtKey->fetch();

if (!$gatewayKey) {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'type' => 'error',
        'error' => [
            'type' => 'authentication_error',
            'message' => 'ClaudePulse: Invalid gateway API key.'
        ]
    ]);
    exit;
}

if ($gatewayKey['status'] !== 'active') {
    http_response_code(401);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'type' => 'error',
        'error' => [
            'type' => 'authentication_error',
            'message' => 'ClaudePulse: This gateway API key has been revoked.'
        ]
    ]);
    exit;
}

// Expiration check
if (!empty($gatewayKey['expires_at'])) {
    if (strtotime($gatewayKey['expires_at']) < time()) {
        http_response_code(401);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'type' => 'error',
            'error' => [
                'type' => 'authentication_error',
                'message' => 'ClaudePulse: This gateway API key has expired.'
            ]
        ]);
        exit;
    }
}

// Hard Spend Cap Check
$capUsd = (float)($gatewayKey['cap_usd'] ?? 0.0);
$spentUsd = (float)($gatewayKey['spent_usd'] ?? 0.0);
if ($capUsd > 0.0 && $spentUsd >= $capUsd) {
    http_response_code(429);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'type' => 'error',
        'error' => [
            'type' => 'rate_limit_error',
            'message' => 'ClaudePulse: Gateway spend cap of $' . number_format($capUsd, 2) . ' USD reached for key "' . htmlspecialchars($gatewayKey['name']) . '".'
        ]
    ]);
    exit;
}

// Hard Token Quota Limit Check
$capTokens = (int)($gatewayKey['cap_tokens'] ?? 0);
$usedTokens = (int)($gatewayKey['used_tokens'] ?? 0);
if ($capTokens > 0 && $usedTokens >= $capTokens) {
    http_response_code(429);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'type' => 'error',
        'error' => [
            'type' => 'rate_limit_error',
            'message' => 'ClaudePulse: Gateway token limit of ' . number_format($capTokens) . ' tokens reached for key "' . htmlspecialchars($gatewayKey['name']) . '".'
        ]
    ]);
    exit;
}

// Rate limit (RPM) check if set
$rpmLimit = (int)($gatewayKey['rate_limit_rpm'] ?? 0);
if ($rpmLimit > 0) {
    $stmtRpm = $db->prepare("
        SELECT COUNT(*) as req_count 
        FROM request_logs 
        WHERE gateway_key_id = :gid 
          AND created_at >= datetime('now', '-1 minute')
    ");
    $stmtRpm->execute([':gid' => $gatewayKey['id']]);
    $rpmCount = (int)$stmtRpm->fetchColumn();

    if ($rpmCount >= $rpmLimit) {
        http_response_code(429);
        header('Content-Type: application/json; charset=utf-8');
        echo json_encode([
            'type' => 'error',
            'error' => [
                'type' => 'rate_limit_error',
                'message' => "ClaudePulse: Rate limit of {$rpmLimit} requests/min exceeded. Please slow down."
            ]
        ]);
        exit;
    }
}

// -------------------------------------------------------------
// 3. Upstream Anthropic Key Retrieval
// -------------------------------------------------------------
$stmtUpstream = $db->query("
    SELECT * FROM upstream_keys 
    WHERE is_active = 1 
    ORDER BY priority ASC, id ASC
");
$upstreamKeys = $stmtUpstream->fetchAll();

if (empty($upstreamKeys)) {
    http_response_code(502);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'type' => 'error',
        'error' => [
            'type' => 'api_error',
            'message' => 'ClaudePulse: No active upstream official Anthropic API keys found in vault. Please add one in ClaudePulse Settings.'
        ]
    ]);
    exit;
}

// -------------------------------------------------------------
// 4. Request Payload & Prompt Caching Injection
// -------------------------------------------------------------
$rawInput = file_get_contents('php://input');
$body = [];
if (!empty($rawInput)) {
    $body = json_decode($rawInput, true) ?: [];
}

$modelRequested = $body['model'] ?? null;

// Check allowed models restriction
if (!empty($gatewayKey['allowed_models']) && !empty($modelRequested)) {
    $allowed = array_filter(array_map('trim', explode(',', $gatewayKey['allowed_models'])));
    if (!empty($allowed)) {
        $modelMatched = false;
        $reqLower = strtolower(trim((string)$modelRequested));
        foreach ($allowed as $allowedModel) {
            $allowedLower = strtolower(trim((string)$allowedModel));
            if (empty($allowedLower)) continue;
            if (
                $reqLower === $allowedLower ||
                str_starts_with($reqLower, $allowedLower) ||
                str_starts_with($allowedLower, $reqLower) ||
                ($allowedLower === 'sonnet' && str_contains($reqLower, 'sonnet')) ||
                ($allowedLower === 'opus' && str_contains($reqLower, 'opus')) ||
                ($allowedLower === 'haiku' && str_contains($reqLower, 'haiku')) ||
                ($allowedLower === 'fable' && str_contains($reqLower, 'fable'))
            ) {
                $modelMatched = true;
                break;
            }
        }
        if (!$modelMatched) {
            http_response_code(400);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'type' => 'error',
                'error' => [
                    'type' => 'invalid_request_error',
                    'message' => "ClaudePulse: Model '{$modelRequested}' is not permitted for this gateway key."
                ]
            ]);
            exit;
        }
    }
}

// Client Tagging (Auto-detect Claude Code CLI / IDE)
$userAgent = $_SERVER['HTTP_USER_AGENT'] ?? '';
$clientTag = 'API Client';
if (stripos($userAgent, 'claude-code') !== false || stripos($userAgent, 'claude_code') !== false || stripos($userAgent, 'Anthropic/ClaudeCode') !== false) {
    $clientTag = 'Claude Code';
} elseif (stripos($userAgent, 'vscode') !== false || stripos($userAgent, 'antigravity') !== false || stripos($userAgent, 'cursor') !== false) {
    $clientTag = 'Claude IDE';
} elseif (stripos($userAgent, 'python') !== false) {
    $clientTag = 'Python SDK';
} elseif (stripos($userAgent, 'node') !== false) {
    $clientTag = 'Node.js SDK';
}

// Cost Optimizer: Prompt Caching Injector
$autoPromptCaching = Database::getSetting('auto_prompt_caching', '1') === '1';
$promptCachingInjected = false;
$existingBeta = '';

foreach ($allHeaders as $hKey => $hVal) {
    if (strtolower($hKey) === 'anthropic-beta') {
        $existingBeta = trim($hVal);
        break;
    }
}

if ($autoPromptCaching && $endpoint === '/v1/messages' && !empty($body)) {
    // 1. Inject cache_control on system prompt (if present)
    if (!empty($body['system'])) {
        if (is_string($body['system'])) {
            $body['system'] = [
                [
                    'type' => 'text',
                    'text' => $body['system'],
                    'cache_control' => ['type' => 'ephemeral']
                ]
            ];
            $promptCachingInjected = true;
        } elseif (is_array($body['system']) && count($body['system']) > 0) {
            $lastIdx = count($body['system']) - 1;
            if (is_array($body['system'][$lastIdx]) && !isset($body['system'][$lastIdx]['cache_control'])) {
                $body['system'][$lastIdx]['cache_control'] = ['type' => 'ephemeral'];
                $promptCachingInjected = true;
            }
        }
    }

    // 2. Inject cache_control on the last tool definition (if present)
    if (!empty($body['tools']) && is_array($body['tools']) && count($body['tools']) > 0) {
        $lastToolIdx = count($body['tools']) - 1;
        if (is_array($body['tools'][$lastToolIdx]) && !isset($body['tools'][$lastToolIdx]['cache_control'])) {
            $body['tools'][$lastToolIdx]['cache_control'] = ['type' => 'ephemeral'];
            $promptCachingInjected = true;
        }
    }

    // 3. Ensure prompt-caching beta header is attached
    if ($promptCachingInjected) {
        if (empty($existingBeta)) {
            $existingBeta = 'prompt-caching-2024-07-31';
        } elseif (!str_contains($existingBeta, 'prompt-caching')) {
            $existingBeta .= ',prompt-caching-2024-07-31';
        }
        $rawInput = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    }
}

$isStreaming = !empty($body['stream']) || (isset($_SERVER['HTTP_ACCEPT']) && str_contains($_SERVER['HTTP_ACCEPT'], 'text/event-stream'));

// -------------------------------------------------------------
// 5. Proxy Execution with Upstream Failover
// -------------------------------------------------------------
$targetUrl = 'https://api.anthropic.com' . $endpoint;
if (!empty($_SERVER['QUERY_STRING'])) {
    $targetUrl .= '?' . $_SERVER['QUERY_STRING'];
}

$autoFallback = Database::getSetting('auto_fallback', '1') === '1';
$chosenUpstreamKey = null;
$finalStatusCode = 502;
$finalErrorMsg = null;

// Telemetry Accumulators
$inputTokens = 0;
$outputTokens = 0;
$cacheCreationTokens = 0;
$cacheReadTokens = 0;
$resolvedModel = $modelRequested;
$ttftMs = 0;
$firstChunkTime = 0.0;
$rawSseBuffer = '';

// Attempt proxying with upstream key (with fallback if enabled)
foreach ($upstreamKeys as $index => $upstreamRow) {
    try {
        $decryptedApiKey = Crypto::decrypt($upstreamRow['api_key_encrypted']);
    } catch (\Exception $e) {
        continue;
    }

    $chosenUpstreamKey = $upstreamRow;

    // Build headers to send to Anthropic
    $anthropicVersion = '2023-06-01';
    foreach ($allHeaders as $hKey => $hVal) {
        if (strtolower($hKey) === 'anthropic-version') {
            $anthropicVersion = trim($hVal);
            break;
        }
    }

    $headersToSend = [
        'x-api-key: ' . $decryptedApiKey,
        'anthropic-version: ' . $anthropicVersion,
        'Content-Type: application/json',
    ];

    if (!empty($existingBeta)) {
        $headersToSend[] = 'anthropic-beta: ' . $existingBeta;
    }

    $ch = curl_init($targetUrl);
    curl_setopt($ch, CURLOPT_HTTPHEADER, $headersToSend);
    curl_setopt($ch, CURLOPT_CUSTOMREQUEST, $_SERVER['REQUEST_METHOD']);
    curl_setopt($ch, CURLOPT_SSL_VERIFYPEER, true);
    curl_setopt($ch, CURLOPT_SSL_VERIFYHOST, 2);
    curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 15);
    curl_setopt($ch, CURLOPT_TIMEOUT, 600);

    if ($_SERVER['REQUEST_METHOD'] === 'POST' || $_SERVER['REQUEST_METHOD'] === 'PUT') {
        curl_setopt($ch, CURLOPT_POSTFIELDS, $rawInput);
    }

    if ($isStreaming) {
        // Setup SSE streaming
        $headersSent = false;

        curl_setopt($ch, CURLOPT_WRITEFUNCTION, function ($ch, $chunk) use (
            &$headersSent,
            &$firstChunkTime,
            &$ttftMs,
            $startTime,
            &$rawSseBuffer,
            &$inputTokens,
            &$outputTokens,
            &$cacheCreationTokens,
            &$cacheReadTokens,
            &$resolvedModel,
            &$finalStatusCode
        ) {
            $chunkLen = strlen($chunk);
            if ($chunkLen === 0) {
                return 0;
            }

            // Send SSE headers on first chunk
            if (!$headersSent) {
                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $finalStatusCode = $httpCode;

                if ($firstChunkTime === 0.0) {
                    $firstChunkTime = microtime(true);
                    $ttftMs = (int)round(($firstChunkTime - $startTime) * 1000);
                }

                http_response_code($httpCode > 0 ? $httpCode : 200);
                header('Content-Type: text/event-stream; charset=utf-8');
                header('Cache-Control: no-cache, no-transform');
                header('Connection: keep-alive');
                header('X-Accel-Buffering: no');
                header('Access-Control-Allow-Origin: *');
                $headersSent = true;
            }

            // Stream chunk immediately to client terminal / UI
            echo $chunk;
            flush();

            // Accumulate buffer to parse usage events in the background
            $rawSseBuffer .= $chunk;
            if (str_contains($rawSseBuffer, "\n")) {
                $lines = explode("\n", $rawSseBuffer);
                $rawSseBuffer = array_pop($lines); // keep unfinished trailing line

                foreach ($lines as $line) {
                    $line = trim($line);
                    if (str_starts_with($line, 'data: ')) {
                        $jsonPayload = substr($line, 6);
                        if ($jsonPayload === '[DONE]') {
                            continue;
                        }
                        $eventData = json_decode($jsonPayload, true);
                        if (!is_array($eventData)) {
                            continue;
                        }

                        $type = $eventData['type'] ?? '';

                        // Capture input usage & model from message_start
                        if ($type === 'message_start' && isset($eventData['message'])) {
                            if (!empty($eventData['message']['model'])) {
                                $resolvedModel = $eventData['message']['model'];
                            }
                            if (isset($eventData['message']['usage'])) {
                                $u = $eventData['message']['usage'];
                                $inputTokens = (int)($u['input_tokens'] ?? 0);
                                $cacheCreationTokens = (int)($u['cache_creation_input_tokens'] ?? 0);
                                $cacheReadTokens = (int)($u['cache_read_input_tokens'] ?? 0);
                            }
                        }

                        // Capture output tokens from message_delta
                        if ($type === 'message_delta' && isset($eventData['usage'])) {
                            $outputTokens += (int)($eventData['usage']['output_tokens'] ?? 0);
                        }
                    }
                }
            }

            return $chunkLen;
        });

        curl_exec($ch);
        $finalStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $finalErrorMsg = $curlError;
        }

        // If streaming started, we already sent response to client, break out of fallback loop
        break;
    } else {
        // Non-streaming request
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        $responseBody = curl_exec($ch);
        $finalStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlError = curl_error($ch);
        curl_close($ch);

        if ($curlError) {
            $finalErrorMsg = $curlError;
        }

        // Fallback retry condition: 429 (Rate Limit) or 529 (Overloaded) or 401 (Invalid Key)
        if (($finalStatusCode === 429 || $finalStatusCode === 529 || $finalStatusCode === 401)
            && $autoFallback
            && ($index + 1 < count($upstreamKeys))) {
            // Try next key in loop
            continue;
        }

        // Parse non-streaming JSON response for token usage
        if ($responseBody !== false) {
            $jsonResp = json_decode($responseBody, true);
            if (is_array($jsonResp)) {
                if (!empty($jsonResp['model'])) {
                    $resolvedModel = $jsonResp['model'];
                }
                if (isset($jsonResp['usage'])) {
                    $u = $jsonResp['usage'];
                    $inputTokens = (int)($u['input_tokens'] ?? 0);
                    $outputTokens = (int)($u['output_tokens'] ?? 0);
                    $cacheCreationTokens = (int)($u['cache_creation_input_tokens'] ?? 0);
                    $cacheReadTokens = (int)($u['cache_read_input_tokens'] ?? 0);
                }
                if (isset($jsonResp['error']['message'])) {
                    $finalErrorMsg = $jsonResp['error']['message'];
                }
            }

            http_response_code($finalStatusCode > 0 ? $finalStatusCode : 200);
            header('Content-Type: application/json; charset=utf-8');
            header('Access-Control-Allow-Origin: *');
            echo $responseBody;
        }
        break;
    }
}

// -------------------------------------------------------------
// 6. Calculate Pricing, Telemetry & Hard Cap Accumulation
// -------------------------------------------------------------
$totalLatencyMs = (int)round((microtime(true) - $startTime) * 1000);
if ($ttftMs === 0) {
    $ttftMs = $totalLatencyMs;
}

$costUsd = Pricing::calculateCost(
    $resolvedModel,
    $inputTokens,
    $outputTokens,
    $cacheCreationTokens,
    $cacheReadTokens
);

$savedUsd = Pricing::calculateSavings($resolvedModel, $cacheReadTokens);
$clientIp = Auth::getClientIp();

// Persist request telemetry log
try {
    $stmtLog = $db->prepare("
        INSERT INTO request_logs (
            gateway_key_id, upstream_key_id, endpoint, model, status_code,
            is_streaming, input_tokens, output_tokens,
            cache_creation_input_tokens, cache_read_input_tokens,
            cost_usd, saved_usd, latency_ms, ttft_ms,
            client_ip, user_agent, client_tag, error_message, created_at
        ) VALUES (
            :gid, :uid, :endpoint, :model, :status,
            :stream, :in_tok, :out_tok,
            :c_write, :c_read,
            :cost, :saved, :latency, :ttft,
            :ip, :ua, :tag, :err, datetime('now')
        )
    ");

    $stmtLog->execute([
        ':gid'      => $gatewayKey['id'],
        ':uid'      => $chosenUpstreamKey['id'] ?? null,
        ':endpoint' => $endpoint,
        ':model'    => $resolvedModel,
        ':status'   => $finalStatusCode,
        ':stream'   => $isStreaming ? 1 : 0,
        ':in_tok'   => $inputTokens,
        ':out_tok'  => $outputTokens,
        ':c_write'  => $cacheCreationTokens,
        ':c_read'   => $cacheReadTokens,
        ':cost'     => $costUsd,
        ':saved'    => $savedUsd,
        ':latency'  => $totalLatencyMs,
        ':ttft'     => $ttftMs,
        ':ip'       => $clientIp,
        ':ua'       => substr($userAgent, 0, 250),
        ':tag'      => $clientTag,
        ':err'      => $finalErrorMsg ? substr($finalErrorMsg, 0, 250) : null
    ]);

    // Atomically increment spent_usd, used_tokens and total_requests for the Gateway Key
    $totalReqTokens = (int)($inputTokens + $outputTokens + $cacheCreationTokens + $cacheReadTokens);
    $stmtUpdateKey = $db->prepare("
        UPDATE gateway_keys
        SET spent_usd = spent_usd + :cost,
            used_tokens = used_tokens + :tokens,
            total_requests = total_requests + 1,
            last_used_at = datetime('now')
        WHERE id = :id
    ");
    $stmtUpdateKey->execute([
        ':cost'   => $costUsd,
        ':tokens' => $totalReqTokens,
        ':id'     => $gatewayKey['id']
    ]);
} catch (\Exception $e) {
    // Suppress DB write error from breaking response stream
}

exit;
