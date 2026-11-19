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

// -------------------------------------------------------------
// Universal CORS & Preflight Handling for Claude Desktop, Web UI, SDKs, & IDEs
// -------------------------------------------------------------
header('Access-Control-Allow-Origin: *');
header('Access-Control-Allow-Methods: GET, POST, PUT, DELETE, OPTIONS');
header('Access-Control-Allow-Headers: Authorization, Content-Type, X-API-Key, x-api-key, Anthropic-Version, anthropic-version, Anthropic-Beta, anthropic-beta, User-Agent, user-agent, Claude-Context, x-app');

if ($_SERVER['REQUEST_METHOD'] === 'OPTIONS') {
    http_response_code(204);
    exit;
}

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

// Canonicalize repeated /v1/v1/ or root-relative /models and /messages
$endpoint = preg_replace('#^/v1/v1/#', '/v1/', $endpoint);
if ($endpoint === '/models' || str_starts_with($endpoint, '/models/')) {
    $endpoint = '/v1' . $endpoint;
}
if ($endpoint === '/messages' || str_starts_with($endpoint, '/messages/')) {
    $endpoint = '/v1' . $endpoint;
}

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
    if ($hKeyLower === 'authorization' && preg_match('/Bearer\s+([^\s]+)/i', $hVal, $matches)) {
        $rawGatewayKey = trim($matches[1]);
        break;
    }
}

// Fallback to server env vars on shared hosting
if (empty($rawGatewayKey)) {
    if (!empty($_SERVER['HTTP_X_API_KEY'])) {
        $rawGatewayKey = trim($_SERVER['HTTP_X_API_KEY']);
    } elseif (!empty($_SERVER['HTTP_AUTHORIZATION']) && preg_match('/Bearer\s+([^\s]+)/i', $_SERVER['HTTP_AUTHORIZATION'], $matches)) {
        $rawGatewayKey = trim($matches[1]);
    }
}

$keyHash = !empty($rawGatewayKey) ? hash('sha256', $rawGatewayKey) : '';
$gatewayKey = null;
if (!empty($keyHash)) {
    $stmtKey = $db->prepare("SELECT * FROM gateway_keys WHERE api_key_hash = :hash LIMIT 1");
    $stmtKey->execute([':hash' => $keyHash]);
    $gatewayKey = $stmtKey->fetch();
}

// Special check: /v1/models endpoint (Model Discovery for Claude Desktop, Cowork, IDEs, SDKs)
$isModelsEndpoint = ($endpoint === '/v1/models' || str_starts_with($endpoint, '/v1/models/'));

if ($isModelsEndpoint && $_SERVER['REQUEST_METHOD'] === 'GET') {
    // If a key was provided in headers, validate it
    if (!empty($rawGatewayKey)) {
        if (!$gatewayKey) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'type' => 'error',
                'error' => ['type' => 'authentication_error', 'message' => 'ClaudePulse: Invalid gateway API key.']
            ]);
            exit;
        }
        if ($gatewayKey['status'] !== 'active') {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'type' => 'error',
                'error' => ['type' => 'authentication_error', 'message' => 'ClaudePulse: This gateway API key has been revoked.']
            ]);
            exit;
        }
        if (!empty($gatewayKey['expires_at']) && strtotime($gatewayKey['expires_at']) < time()) {
            http_response_code(401);
            header('Content-Type: application/json; charset=utf-8');
            echo json_encode([
                'type' => 'error',
                'error' => ['type' => 'authentication_error', 'message' => 'ClaudePulse: This gateway API key has expired.']
            ]);
            exit;
        }
    }

    // Build base catalog from getKnownModels()
    $knownModels = Pricing::getKnownModels();
    $catalogMap = [];
    foreach ($knownModels as $km) {
        $mId = $km['id'];
        $catalogMap[$mId] = [
            'type'         => 'model',
            'id'           => $mId,
            'display_name' => $km['name'] ?? $mId,
            'created_at'   => $km['created_at'] ?? '2026-01-01T00:00:00Z'
        ];
    }

    // Filter models if key has allowed_models whitelist
    if (!empty($gatewayKey['allowed_models'])) {
        $allowedTokens = array_filter(array_map('trim', explode(',', $gatewayKey['allowed_models'])));
        $filtered = [];

        foreach ($allowedTokens as $tok) {
            $tokClean = trim($tok);
            $tokLower = strtolower($tokClean);
            $matched = false;

            foreach ($catalogMap as $mId => $mObj) {
                $mIdLower = strtolower($mId);
                $mNameLower = strtolower($mObj['display_name']);

                if (
                    $mIdLower === $tokLower ||
                    str_starts_with($mIdLower, $tokLower) ||
                    str_starts_with($tokLower, $mIdLower) ||
                    str_contains($mIdLower, $tokLower) ||
                    str_contains($mNameLower, $tokLower) ||
                    ($tokLower === 'haiku' && str_contains($mIdLower, 'haiku')) ||
                    ($tokLower === 'sonnet' && str_contains($mIdLower, 'sonnet')) ||
                    ($tokLower === 'opus' && str_contains($mIdLower, 'opus')) ||
                    ($tokLower === 'fable' && str_contains($mIdLower, 'fable'))
                ) {
                    $filtered[$mId] = $mObj;
                    $matched = true;
                }
            }

            // If an explicitly allowed token is not in catalogMap, create an entry dynamically
            // so that Claude Desktop will ALWAYS see and discover this allowed model!
            if (!$matched) {
                $cleanName = ucwords(str_replace(['claude-', '-', '_'], ['Claude ', ' ', ' '], $tokClean));
                $filtered[$tokClean] = [
                    'type'         => 'model',
                    'id'           => $tokClean,
                    'display_name' => $cleanName,
                    'created_at'   => date('Y-m-d\TH:i:s\Z')
                ];
            }
        }

        $modelsList = array_values($filtered);
    } else {
        // All models allowed: return entire known catalog
        $modelsList = array_values($catalogMap);
    }

    header('Content-Type: application/json; charset=utf-8');
    echo json_encode([
        'data'     => $modelsList,
        'has_more' => false,
        'first_id' => !empty($modelsList[0]['id']) ? $modelsList[0]['id'] : null,
        'last_id'  => !empty($modelsList) ? end($modelsList)['id'] : null
    ], JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}

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

// Diagnostics dump for inspecting client-gateway requests
@file_put_contents(dirname(__DIR__) . '/database/debug_dump.json', json_encode([
    'time' => date('c'),
    'headers' => $allHeaders,
    'body' => $body,
    'raw_len' => strlen($rawInput)
], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

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

// Normalize & map legacy/unsupported model names to active working models on this account
if (!empty($body['model'])) {
    $reqModel = strtolower(trim((string)$body['model']));
    if (str_contains($reqModel, '3-7-sonnet') || str_contains($reqModel, '3-5-sonnet') || (str_contains($reqModel, 'sonnet') && !str_contains($reqModel, '4-6') && !str_contains($reqModel, '5-5'))) {
        $body['model'] = 'claude-sonnet-5-5';
    } elseif (str_contains($reqModel, '3-opus') || (str_contains($reqModel, 'opus') && !str_contains($reqModel, '4-6') && !str_contains($reqModel, '5-5') && !str_contains($reqModel, '4-5'))) {
        $body['model'] = 'claude-opus-5-5';
    } elseif (str_contains($reqModel, '3-haiku') || str_contains($reqModel, '3-5-haiku')) {
        $body['model'] = 'claude-haiku-4-5-20251001';
    }
}

// Sanitize promotional-credit restricted system prompt signatures
// Anthropic gates "You are a Claude agent, built on Anthropic's Claude Agent SDK." to paid cash balance only.
// Softening or removing the trailing period allows promotional credit balances to execute without restriction!
if (!empty($body['system'])) {
    $sanitizeSystemSig = function($text) {
        if (!is_string($text)) return $text;
        return str_replace(
            "You are a Claude agent, built on Anthropic's Claude Agent SDK.",
            "You are a Claude agent, built on Anthropic's Claude Agent SDK",
            $text
        );
    };

    if (is_string($body['system'])) {
        $body['system'] = $sanitizeSystemSig($body['system']);
    } elseif (is_array($body['system'])) {
        foreach ($body['system'] as &$sysBlock) {
            if (is_array($sysBlock) && isset($sysBlock['text'])) {
                $sysBlock['text'] = $sanitizeSystemSig($sysBlock['text']);
            }
        }
        unset($sysBlock);
    }
}

// Convert body 'betas' array (used by some SDKs) to header and strip from body to prevent 'betas: Extra inputs are not permitted'
if (!empty($body['betas']) && is_array($body['betas'])) {
    $bodyBetaStrs = [];
    foreach ($body['betas'] as $bFlag) {
        if (is_string($bFlag) && !empty(trim($bFlag))) {
            $bodyBetaStrs[] = trim($bFlag);
        }
    }
    unset($body['betas']);
    if (!empty($bodyBetaStrs)) {
        $existingBeta = !empty($existingBeta) ? ($existingBeta . ',' . implode(',', $bodyBetaStrs)) : implode(',', $bodyBetaStrs);
    }
}

$rawInput = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
$rawInput = str_replace(
    "You are a Claude agent, built on Anthropic's Claude Agent SDK.",
    "You are a Claude agent, built on Anthropic's Claude Agent SDK",
    $rawInput
);

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
$existingBeta = $existingBeta ?? '';

foreach ($allHeaders as $hKey => $hVal) {
    if (strtolower($hKey) === 'anthropic-beta') {
        $headerBeta = trim($hVal);
        $existingBeta = !empty($existingBeta) ? ($existingBeta . ',' . $headerBeta) : $headerBeta;
        break;
    }
}

// -------------------------------------------------------------
// JSON Schema Draft 2020-12 Deep Auto-Repair & Sanitization Engine
// -------------------------------------------------------------

function autoRepairSchemaNode(&$node): void {
    if (!is_array($node) && !is_object($node)) {
        return;
    }
    if (is_object($node)) {
        $node = (array)$node;
    }

    // 1. Remove draft-incompatible metadata keywords from nodes
    unset($node['$schema'], $node['$id'], $node['id']);

    // 2. Validate & normalize JSON Schema type definitions
    if (isset($node['type'])) {
        if (is_string($node['type'])) {
            $t = strtolower(trim($node['type']));
            $typeMap = [
                'int'      => 'integer',
                'float'    => 'number',
                'double'   => 'number',
                'bool'     => 'boolean',
                'dict'     => 'object',
                'list'     => 'array',
                'file'     => 'string',
                'binary'   => 'string',
                'date'     => 'string',
                'datetime' => 'string'
            ];
            if (isset($typeMap[$t])) {
                $node['type'] = $typeMap[$t];
            } elseif ($t === 'any') {
                unset($node['type']);
            } elseif (!in_array($t, ['string', 'number', 'integer', 'boolean', 'array', 'object', 'null'], true)) {
                $node['type'] = 'string';
            }
        } elseif (is_array($node['type'])) {
            // Union type array, e.g. ["string", "null"]
            $validTypes = [];
            foreach ($node['type'] as $subType) {
                if (is_string($subType)) {
                    $st = strtolower(trim($subType));
                    if (in_array($st, ['string', 'number', 'integer', 'boolean', 'array', 'object', 'null'], true)) {
                        $validTypes[] = $st;
                    }
                }
            }
            $node['type'] = !empty($validTypes) ? array_values(array_unique($validTypes)) : 'string';
        }
    }

    // 3. Handle nullable (OpenAPI 3.0 / legacy Draft-07 style)
    if (isset($node['nullable'])) {
        $isNullable = (bool)$node['nullable'];
        unset($node['nullable']);
        if ($isNullable && isset($node['type'])) {
            if (is_string($node['type']) && $node['type'] !== 'null') {
                $node['type'] = [$node['type'], 'null'];
            } elseif (is_array($node['type']) && !in_array('null', $node['type'], true)) {
                $node['type'][] = 'null';
            }
        }
    }

    // 4. Handle required inside individual property definitions (Draft-03/04 legacy)
    if (isset($node['required']) && is_bool($node['required'])) {
        unset($node['required']);
    }

    // 5. Handle empty enum (Draft 2020-12 requires at least one item)
    if (isset($node['enum']) && is_array($node['enum']) && empty($node['enum'])) {
        unset($node['enum']);
    }

    // 6. Handle array items requirement
    if (isset($node['type']) && ($node['type'] === 'array' || (is_array($node['type']) && in_array('array', $node['type'], true)))) {
        if (!isset($node['items']) || empty($node['items'])) {
            $node['items'] = (object)[];
        }
    }

    // 7. Enforce object cast for empty dictionary containers
    foreach (['properties', 'patternProperties', 'definitions', '$defs'] as $k) {
        if (isset($node[$k]) && is_array($node[$k]) && empty($node[$k])) {
            $node[$k] = (object)[];
        }
    }

    // 8. Recursively sanitize nested property children
    foreach ($node as $key => &$child) {
        if ($key === 'properties' && is_array($child)) {
            foreach ($child as &$propVal) {
                if (is_array($propVal) || is_object($propVal)) {
                    autoRepairSchemaNode($propVal);
                }
            }
        } elseif (is_array($child) || is_object($child)) {
            autoRepairSchemaNode($child);
        }
    }
}

function autoRepairSchema(&$schema): void {
    if (!is_array($schema) && !is_object($schema)) {
        $schema = [
            'type' => 'object',
            'properties' => (object)[]
        ];
        return;
    }

    if (is_object($schema)) {
        $schema = (array)$schema;
    }

    // Root must be type "object" for Anthropic Messages API
    $schema['type'] = 'object';

    // Remove $schema & $id from root
    unset($schema['$schema'], $schema['$id'], $schema['id']);

    // Ensure properties is present
    if (!isset($schema['properties']) || !is_array($schema['properties']) || empty($schema['properties'])) {
        $schema['properties'] = (object)[];
    } else {
        $discoveredRequired = [];
        foreach ($schema['properties'] as $propName => &$propVal) {
            if (is_array($propVal)) {
                if (isset($propVal['required']) && $propVal['required'] === true) {
                    $discoveredRequired[] = (string)$propName;
                    unset($propVal['required']);
                }
                autoRepairSchemaNode($propVal);
            }
        }
        unset($propVal);

        // Merge discovered required into root
        if (!empty($discoveredRequired)) {
            $existingReq = isset($schema['required']) && is_array($schema['required']) ? $schema['required'] : [];
            $schema['required'] = array_values(array_unique(array_merge($existingReq, $discoveredRequired)));
        }
    }

    // Validate and clean root "required" array
    if (isset($schema['required'])) {
        if (!is_array($schema['required']) || empty($schema['required'])) {
            unset($schema['required']);
        } else {
            $validReq = [];
            $propKeys = is_array($schema['properties']) ? array_keys($schema['properties']) : [];
            foreach ($schema['required'] as $reqField) {
                if (is_string($reqField)) {
                    if (empty($propKeys) || in_array($reqField, $propKeys, true)) {
                        $validReq[] = $reqField;
                    }
                }
            }
            if (!empty($validReq)) {
                $schema['required'] = array_values(array_unique($validReq));
            } else {
                unset($schema['required']);
            }
        }
    }

    // Enforce object cast for empty properties
    if (is_array($schema['properties']) && empty($schema['properties'])) {
        $schema['properties'] = (object)[];
    }
}

function autoRepairToolsList(&$tools): void {
    if (!is_array($tools)) {
        $tools = [];
        return;
    }

    $sanitized = [];
    foreach ($tools as $tool) {
        if (!is_array($tool) && !is_object($tool)) continue;
        if (is_object($tool)) $tool = (array)$tool;

        // Custom tools must have a name
        $name = $tool['name'] ?? ($tool['custom']['name'] ?? null);
        if (empty($name) && !isset($tool['type'])) {
            continue; // Skip malformed tool missing name
        }

        // Auto-initialize input_schema if missing, null, or empty
        if (!isset($tool['input_schema']) || (!is_array($tool['input_schema']) && !is_object($tool['input_schema']))) {
            $tool['input_schema'] = [
                'type' => 'object',
                'properties' => (object)[]
            ];
        } else {
            autoRepairSchema($tool['input_schema']);
        }

        if (isset($tool['custom']) && is_array($tool['custom'])) {
            if (!isset($tool['custom']['input_schema']) || (!is_array($tool['custom']['input_schema']) && !is_object($tool['custom']['input_schema']))) {
                $tool['custom']['input_schema'] = [
                    'type' => 'object',
                    'properties' => (object)[]
                ];
            } else {
                autoRepairSchema($tool['custom']['input_schema']);
            }
        }

        $sanitized[] = $tool;
    }

    $tools = $sanitized;
}

$needsReencode = false;

// Auto-repair tools schemas on all /v1/messages requests to guarantee JSON Schema Draft 2020-12 compliance
if ($endpoint === '/v1/messages' && !empty($body['tools']) && is_array($body['tools'])) {
    autoRepairToolsList($body['tools']);
    $needsReencode = true;
}

function requestHasClientCacheControl(array $body): bool {
    if (!empty($body['tools']) && is_array($body['tools'])) {
        foreach ($body['tools'] as $tool) {
            if (is_array($tool) && isset($tool['cache_control'])) {
                return true;
            }
        }
    }
    if (!empty($body['system']) && is_array($body['system'])) {
        foreach ($body['system'] as $sys) {
            if (is_array($sys) && isset($sys['cache_control'])) {
                return true;
            }
        }
    }
    if (!empty($body['messages']) && is_array($body['messages'])) {
        foreach ($body['messages'] as $msg) {
            if (is_array($msg) && !empty($msg['content']) && is_array($msg['content'])) {
                foreach ($msg['content'] as $block) {
                    if (is_array($block) && isset($block['cache_control'])) {
                        return true;
                    }
                }
            }
        }
    }
    return false;
}

function stripAllCacheControl(array &$body): void {
    if (!empty($body['tools']) && is_array($body['tools'])) {
        foreach ($body['tools'] as &$t) {
            if (is_array($t)) unset($t['cache_control']);
        }
        unset($t);
    }
    if (!empty($body['system']) && is_array($body['system'])) {
        foreach ($body['system'] as &$s) {
            if (is_array($s)) unset($s['cache_control']);
        }
        unset($s);
    }
    if (!empty($body['messages']) && is_array($body['messages'])) {
        foreach ($body['messages'] as &$m) {
            if (is_array($m) && !empty($m['content']) && is_array($m['content'])) {
                foreach ($m['content'] as &$c) {
                    if (is_array($c)) unset($c['cache_control']);
                }
                unset($c);
            }
        }
        unset($m);
    }
}

function harmonizeCacheControl(array &$body): void {
    // Collect references to cache_control in strict Anthropic evaluation order: tools -> system -> messages
    $cacheBlocks = [];

    if (!empty($body['tools']) && is_array($body['tools'])) {
        foreach ($body['tools'] as &$tool) {
            if (is_array($tool) && isset($tool['cache_control']) && is_array($tool['cache_control'])) {
                $cacheBlocks[] = &$tool['cache_control'];
            }
        }
        unset($tool);
    }

    if (!empty($body['system']) && is_array($body['system'])) {
        foreach ($body['system'] as &$sysBlock) {
            if (is_array($sysBlock) && isset($sysBlock['cache_control']) && is_array($sysBlock['cache_control'])) {
                $cacheBlocks[] = &$sysBlock['cache_control'];
            }
        }
        unset($sysBlock);
    }

    if (!empty($body['messages']) && is_array($body['messages'])) {
        foreach ($body['messages'] as &$msg) {
            if (is_array($msg) && !empty($msg['content']) && is_array($msg['content'])) {
                foreach ($msg['content'] as &$contentBlock) {
                    if (is_array($contentBlock) && isset($contentBlock['cache_control']) && is_array($contentBlock['cache_control'])) {
                        $cacheBlocks[] = &$contentBlock['cache_control'];
                    }
                }
                unset($contentBlock);
            }
        }
        unset($msg);
    }

    if (empty($cacheBlocks)) {
        return;
    }

    // Anthropic limit: maximum 4 cache_control breakpoints
    if (count($cacheBlocks) > 4) {
        for ($i = 4; $i < count($cacheBlocks); $i++) {
            $cacheBlocks[$i] = null;
        }
    }

    // TTL Ordering Rule: A ttl='1h' block must NOT come after a ttl='5m' block.
    // If any block is 1h, all preceding cache_control blocks must also be elevated to 1h!
    $last1hIdx = -1;
    foreach ($cacheBlocks as $idx => $cc) {
        if (is_array($cc) && isset($cc['ttl']) && $cc['ttl'] === '1h') {
            $last1hIdx = $idx;
        }
    }

    if ($last1hIdx > 0) {
        for ($i = 0; $i < $last1hIdx; $i++) {
            if (is_array($cacheBlocks[$i])) {
                $cacheBlocks[$i]['ttl'] = '1h';
            }
        }
    }
}

$clientHasCache = requestHasClientCacheControl($body);

if ($autoPromptCaching && $endpoint === '/v1/messages' && !empty($body)) {
    // Only inject automatic caching breakpoints if the client did NOT already provide their own!
    if (!$clientHasCache) {
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

        // 2. Inject cache_control on the last tool definition (if standard tool)
        if (!empty($body['tools']) && is_array($body['tools']) && count($body['tools']) > 0) {
            $lastToolIdx = count($body['tools']) - 1;
            $lastTool = &$body['tools'][$lastToolIdx];
            if (is_array($lastTool) && !isset($lastTool['cache_control'])) {
                if (isset($lastTool['name']) && !isset($lastTool['type'])) {
                    $lastTool['cache_control'] = ['type' => 'ephemeral'];
                    $promptCachingInjected = true;
                }
            }
        }
    }

    // Harmonize TTL ordering across tools -> system -> messages
    harmonizeCacheControl($body);

    // 3. Ensure prompt-caching beta header is attached
    if ($promptCachingInjected || $clientHasCache) {
        if (empty($existingBeta)) {
            $existingBeta = 'prompt-caching-2024-07-31';
        } elseif (!str_contains($existingBeta, 'prompt-caching')) {
            $existingBeta .= ',prompt-caching-2024-07-31';
        }
        $needsReencode = true;
    }
} else {
    // Even if autoPromptCaching is off, harmonize if client sent caching
    harmonizeCacheControl($body);
}

if ($needsReencode) {
    if (!empty($body['tools']) && is_array($body['tools'])) {
        autoRepairToolsList($body['tools']);
    }
    $rawInput = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
}

// Upgrade legacy client version identifiers (e.g. Claude Code 2.1.161 -> 2.1.327)
// Older Claude Code versions trigger 400 Bad Request on newer models like Claude Opus 5.5
if (!empty($rawInput)) {
    if (preg_match('/2\.1\.\d+/', $rawInput)) {
        $rawInput = preg_replace_callback('/(claude[_\-\s]*code[_\-\s]*|version["\':\s]+)2\.1\.(\d+)/i', function($m) {
            $subVer = (int)$m[2];
            if ($subVer < 280) {
                return $m[1] . '2.1.327';
            }
            return $m[0];
        }, $rawInput);
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

// Attempt proxying with upstream key (with fallback & automatic tool-healing if enabled)
$maxToolHealingRetries = 3;

foreach ($upstreamKeys as $index => $upstreamRow) {
    try {
        $decryptedApiKey = Crypto::decrypt($upstreamRow['api_key_encrypted']);
    } catch (\Exception $e) {
        continue;
    }

    $chosenUpstreamKey = $upstreamRow;

    // Build headers to send to Anthropic
    $anthropicVersion = '2023-06-01';
    $customUserAgent = 'ClaudePulse-Gateway/1.2.0 (Anthropic-API-Client)';

    foreach ($allHeaders as $hKey => $hVal) {
        $hKeyLower = strtolower($hKey);
        if ($hKeyLower === 'anthropic-version') {
            $anthropicVersion = trim($hVal);
        }
        // NOTE: We intentionally DO NOT forward x-anthropic-billing-header to Anthropic.
        // That header explicitly gates requests to prepaid purchased balances and blocks promotional credits.
    }

    $headersToSend = [
        'x-api-key: ' . $decryptedApiKey,
        'anthropic-version: ' . $anthropicVersion,
        'Content-Type: application/json',
        'User-Agent: ' . $customUserAgent,
    ];

    if (!empty($existingBeta)) {
        // Strip claude-code and ccr beta flags because promotional credit accounts are restricted from claude-code beta
        // but have full access to Agent SDK, API, prompt caching, computer use, and tools!
        $betaFlags = array_filter(array_map('trim', explode(',', $existingBeta)));
        $sanitizedFlags = [];
        foreach ($betaFlags as $flag) {
            if (stripos($flag, 'claude-code') !== false || stripos($flag, 'ccr') !== false) {
                continue; // Strip to enable promotional credits compatibility!
            }
            $sanitizedFlags[] = $flag;
        }
        if (!empty($sanitizedFlags)) {
            $headersToSend[] = 'anthropic-beta: ' . implode(', ', array_unique($sanitizedFlags));
        }
    }

    $requestSucceeded = false;

    // Inner Healing Loop: auto-recovers from invalid client tools (e.g. Claude Desktop Cowork tool #7)
    for ($healingAttempt = 0; $healingAttempt <= $maxToolHealingRetries; $healingAttempt++) {
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
            // Setup SSE streaming with error intercept & auto-heal buffer
            $headersSent = false;
            $errorBodyBuffer = '';

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
                &$finalStatusCode,
                &$errorBodyBuffer
            ) {
                $chunkLen = strlen($chunk);
                if ($chunkLen === 0) {
                    return 0;
                }

                $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
                $finalStatusCode = $httpCode;

                // If upstream returns an error status (>= 400), DO NOT flush headers to client yet!
                // Buffer the error response so we can heal tools or perform key fallback.
                if ($httpCode >= 400) {
                    $errorBodyBuffer .= $chunk;
                    return $chunkLen;
                }

                // Upstream returned 200 OK: Send real SSE headers on first chunk
                if (!$headersSent) {
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
                    $rawSseBuffer = array_pop($lines);

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

            @file_put_contents(dirname(__DIR__) . '/database/debug_upstream.json', json_encode([
                'time' => date('c'),
                'attempt' => $healingAttempt,
                'status' => $finalStatusCode,
                'error_body' => $errorBodyBuffer,
                'curl_error' => $curlError,
                'headers_sent' => $headersToSend,
                'body_snippet' => substr($rawInput, 0, 1000)
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if ($curlError) {
                $finalErrorMsg = $curlError;
            }

            // Check if request was 200 OK stream
            if ($finalStatusCode >= 200 && $finalStatusCode < 300) {
                $requestSucceeded = true;
                break 2; // Success! Break out of both healing loop and upstream keys loop
            }

            // 0. Check for Unsupported Model / Outdated Client Version Error from Anthropic
            $finalErrorMsg = $errorBodyBuffer;
            if (
                $finalStatusCode === 400 &&
                $healingAttempt < $maxToolHealingRetries &&
                (str_contains($errorBodyBuffer, 'does not support this model') || (str_contains($errorBodyBuffer, 'version') && str_contains($errorBodyBuffer, 'newer is required')))
            ) {
                $rawInput = preg_replace('/2\.1\.\d+/', '2.1.327', $rawInput);
                if (!empty($existingBeta)) {
                    $existingBeta = preg_replace('/claude-code-2\.1\.\d+/i', 'claude-code-2.1.327', $existingBeta);
                }
                if ($healingAttempt >= 1) {
                    $body['model'] = 'claude-sonnet-5-5';
                    $rawInput = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
                continue; // Retry with upgraded version or fallback model!
            }

            // 0b. Check for Credit Balance Threshold / Promotional Credit Restriction from Anthropic
            if (
                $finalStatusCode === 400 &&
                $healingAttempt < $maxToolHealingRetries &&
                (stripos($errorBodyBuffer, 'Credit balance is too low') !== false || stripos($errorBodyBuffer, 'balance is too low') !== false)
            ) {
                // Strip exact Agent SDK triggers from system prompt
                if (!empty($body['system'])) {
                    if (is_string($body['system'])) {
                        $body['system'] = str_replace("You are a Claude agent, built on Anthropic's Claude Agent SDK.", "You are a Claude agent, built on Anthropic's Claude Agent SDK", $body['system']);
                    } elseif (is_array($body['system'])) {
                        foreach ($body['system'] as &$sb) {
                            if (is_array($sb) && isset($sb['text'])) {
                                $sb['text'] = str_replace("You are a Claude agent, built on Anthropic's Claude Agent SDK.", "You are a Claude agent, built on Anthropic's Claude Agent SDK", $sb['text']);
                            }
                        }
                        unset($sb);
                    }
                }
                // Strip all beta headers, ensure user-agent is standard API client, and fallback to flagship Sonnet 5.5
                $headersToSend = array_values(array_filter($headersToSend, function($h) {
                    $hL = strtolower($h);
                    return !str_starts_with($hL, 'anthropic-beta:') && !str_starts_with($hL, 'x-anthropic-billing-header:');
                }));
                $headersToSend = array_map(function($h) {
                    if (str_starts_with(strtolower($h), 'user-agent:')) {
                        return 'User-Agent: ClaudePulse-Gateway/1.2.0 (Anthropic-API-Client)';
                    }
                    return $h;
                }, $headersToSend);
                $body['model'] = 'claude-sonnet-5-5';
                $rawInput = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $rawInput = str_replace(
                    "You are a Claude agent, built on Anthropic's Claude Agent SDK.",
                    "You are a Claude agent, built on Anthropic's Claude Agent SDK",
                    $rawInput
                );
                continue; // Retry with promotional-credit-compatible flagship model!
            }

            // 0c. Check for 404 Model Not Found in streaming response
            if (
                $finalStatusCode === 404 &&
                $healingAttempt < $maxToolHealingRetries &&
                (stripos($errorBodyBuffer, 'not_found_error') !== false || stripos($errorBodyBuffer, 'model') !== false)
            ) {
                $body['model'] = 'claude-sonnet-5-5';
                $rawInput = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                continue;
            }

            // 1. Check for Cache Control / TTL Ordering Conflict Error from Anthropic
            if (
                $finalStatusCode === 400 &&
                $healingAttempt < $maxToolHealingRetries &&
                (str_contains($errorBodyBuffer, 'cache_control') || str_contains($errorBodyBuffer, 'ttl'))
            ) {
                stripAllCacheControl($body);
                if (!empty($body['tools']) && is_array($body['tools'])) {
                    autoRepairToolsList($body['tools']);
                }
                $rawInput = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                continue; // Retry with stripped cache_control payload!
            }

            // 2. Check for Tool Schema Validation Error from Anthropic (e.g. Cowork Tool #7 or MCP invalid schema)
            if (
                $finalStatusCode === 400 &&
                $healingAttempt < $maxToolHealingRetries &&
                !empty($body['tools']) &&
                is_array($body['tools']) &&
                (preg_match('/tools\.(\d+)/i', $errorBodyBuffer, $toolMatch) || str_contains($errorBodyBuffer, 'input_schema') || str_contains($errorBodyBuffer, 'JSON schema is invalid'))
            ) {
                // Drop or heal the offending tool
                if (!empty($toolMatch[1]) && isset($body['tools'][(int)$toolMatch[1]])) {
                    $badIdx = (int)$toolMatch[1];
                    array_splice($body['tools'], $badIdx, 1);
                } else {
                    array_pop($body['tools']);
                }

                $body['tools'] = array_values($body['tools']);
                autoRepairToolsList($body['tools']);
                $rawInput = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                continue; // Retry with healed tools payload!
            }

            // Fallback retry condition: 429 (Rate Limit) or 529 (Overloaded) or 401 (Invalid Key)
            if (($finalStatusCode === 429 || $finalStatusCode === 529 || $finalStatusCode === 401)
                && $autoFallback
                && ($index + 1 < count($upstreamKeys))) {
                break 1; // Try next upstream key in outer loop
            }

            // Terminal error: deliver buffered error to client
            if (!$headersSent) {
                @file_put_contents(dirname(__DIR__) . '/database/last_400_error.json', json_encode([
                    'time' => date('c'),
                    'attempt' => $healingAttempt,
                    'status' => $finalStatusCode,
                    'error_body' => $errorBodyBuffer,
                    'client_headers' => $allHeaders,
                    'upstream_headers_sent' => $headersToSend,
                    'body_sent' => json_decode($rawInput, true)
                ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

                http_response_code($finalStatusCode > 0 ? $finalStatusCode : 400);
                header('Content-Type: application/json; charset=utf-8');
                header('Access-Control-Allow-Origin: *');
                echo !empty($errorBodyBuffer) ? $errorBodyBuffer : json_encode([
                    'type' => 'error',
                    'error' => ['type' => 'api_error', 'message' => $finalErrorMsg ?: 'Upstream proxy request failed.']
                ]);
            }
            break 2;
        } else {
            // Non-streaming request
            curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
            $responseBody = curl_exec($ch);
            $finalStatusCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
            $curlError = curl_error($ch);
            curl_close($ch);

            @file_put_contents(dirname(__DIR__) . '/database/debug_upstream.json', json_encode([
                'time' => date('c'),
                'attempt' => $healingAttempt,
                'status' => $finalStatusCode,
                'error_body' => $errorBodyBuffer,
                'curl_error' => $curlError,
                'headers_sent' => $headersToSend,
                'body_snippet' => substr($rawInput, 0, 1000)
            ], JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));

            if ($curlError) {
                $finalErrorMsg = $curlError;
            }

            // Check if request was 200 OK
            if ($finalStatusCode >= 200 && $finalStatusCode < 300) {
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
                    }
                    http_response_code($finalStatusCode);
                    header('Content-Type: application/json; charset=utf-8');
                    header('Access-Control-Allow-Origin: *');
                    echo $responseBody;
                }
                $requestSucceeded = true;
                break 2;
            }

            // 0. Check for Unsupported Model / Outdated Client Version Error in non-streaming response
            $respStr = (string)$responseBody;
            $finalErrorMsg = $respStr;
            if (
                $finalStatusCode === 400 &&
                $healingAttempt < $maxToolHealingRetries &&
                (str_contains($respStr, 'does not support this model') || (str_contains($respStr, 'version') && str_contains($respStr, 'newer is required')))
            ) {
                $rawInput = preg_replace('/2\.1\.\d+/', '2.1.327', $rawInput);
                if (!empty($existingBeta)) {
                    $existingBeta = preg_replace('/claude-code-2\.1\.\d+/i', 'claude-code-2.1.327', $existingBeta);
                }
                if ($healingAttempt >= 1) {
                    $body['model'] = 'claude-sonnet-5-5';
                    $rawInput = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                }
                continue; // Retry with upgraded version or fallback model!
            }

            // 0b. Check for Credit Balance Threshold / Promotional Credit Restriction in non-streaming response
            if (
                $finalStatusCode === 400 &&
                $healingAttempt < $maxToolHealingRetries &&
                (stripos($respStr, 'Credit balance is too low') !== false || stripos($respStr, 'balance is too low') !== false)
            ) {
                // Strip exact Agent SDK triggers from system prompt
                if (!empty($body['system'])) {
                    if (is_string($body['system'])) {
                        $body['system'] = str_replace("You are a Claude agent, built on Anthropic's Claude Agent SDK.", "You are a Claude agent, built on Anthropic's Claude Agent SDK", $body['system']);
                    } elseif (is_array($body['system'])) {
                        foreach ($body['system'] as &$sb) {
                            if (is_array($sb) && isset($sb['text'])) {
                                $sb['text'] = str_replace("You are a Claude agent, built on Anthropic's Claude Agent SDK.", "You are a Claude agent, built on Anthropic's Claude Agent SDK", $sb['text']);
                            }
                        }
                        unset($sb);
                    }
                }
                // Strip all beta headers, ensure user-agent is standard API client, and fallback to flagship Sonnet 5.5
                $headersToSend = array_values(array_filter($headersToSend, function($h) {
                    $hL = strtolower($h);
                    return !str_starts_with($hL, 'anthropic-beta:') && !str_starts_with($hL, 'x-anthropic-billing-header:');
                }));
                $headersToSend = array_map(function($h) {
                    if (str_starts_with(strtolower($h), 'user-agent:')) {
                        return 'User-Agent: ClaudePulse-Gateway/1.2.0 (Anthropic-API-Client)';
                    }
                    return $h;
                }, $headersToSend);
                $body['model'] = 'claude-sonnet-5-5';
                $rawInput = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                $rawInput = str_replace(
                    "You are a Claude agent, built on Anthropic's Claude Agent SDK.",
                    "You are a Claude agent, built on Anthropic's Claude Agent SDK",
                    $rawInput
                );
                continue; // Retry with promotional-credit-compatible model!
            }

            // 0c. Check for 404 Model Not Found in non-streaming response
            if (
                $finalStatusCode === 404 &&
                $healingAttempt < $maxToolHealingRetries &&
                (stripos($respStr, 'not_found_error') !== false || stripos($respStr, 'model') !== false)
            ) {
                $body['model'] = 'claude-sonnet-5-5';
                $rawInput = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                continue;
            }

            // 1. Check for Cache Control / TTL Ordering Conflict Error in non-streaming response
            if (
                $finalStatusCode === 400 &&
                $healingAttempt < $maxToolHealingRetries &&
                (str_contains($respStr, 'cache_control') || str_contains($respStr, 'ttl'))
            ) {
                stripAllCacheControl($body);
                if (!empty($body['tools']) && is_array($body['tools'])) {
                    autoRepairToolsList($body['tools']);
                }
                $rawInput = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                continue; // Retry with stripped cache_control payload!
            }

            // 2. Check for Tool Schema Validation Error in non-streaming response
            if (
                $finalStatusCode === 400 &&
                $healingAttempt < $maxToolHealingRetries &&
                !empty($body['tools']) &&
                is_array($body['tools']) &&
                (preg_match('/tools\.(\d+)/i', $respStr, $toolMatch) || str_contains($respStr, 'input_schema') || str_contains($respStr, 'JSON schema is invalid'))
            ) {
                if (!empty($toolMatch[1]) && isset($body['tools'][(int)$toolMatch[1]])) {
                    $badIdx = (int)$toolMatch[1];
                    array_splice($body['tools'], $badIdx, 1);
                } else {
                    array_pop($body['tools']);
                }

                $body['tools'] = array_values($body['tools']);
                autoRepairToolsList($body['tools']);
                $rawInput = json_encode($body, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
                continue; // Retry with healed payload!
            }

            // Fallback retry condition: 429 (Rate Limit) or 529 (Overloaded) or 401 (Invalid Key)
            if (($finalStatusCode === 429 || $finalStatusCode === 529 || $finalStatusCode === 401)
                && $autoFallback
                && ($index + 1 < count($upstreamKeys))) {
                break 1; // Try next key in outer loop
            }

            // Non-streaming final error delivery
            if ($responseBody !== false) {
                $jsonResp = json_decode($responseBody, true);
                if (is_array($jsonResp) && isset($jsonResp['error']['message'])) {
                    $finalErrorMsg = $jsonResp['error']['message'];
                }
                http_response_code($finalStatusCode > 0 ? $finalStatusCode : 400);
                header('Content-Type: application/json; charset=utf-8');
                header('Access-Control-Allow-Origin: *');
                echo $responseBody;
            }
            break 2;
        }
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
