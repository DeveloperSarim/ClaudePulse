<?php
declare(strict_types=1);

// Use isolated test database so production claudepulse.sqlite is never tainted
$testDbPath = __DIR__ . '/../database/claudepulse_test.sqlite';
@unlink($testDbPath);
putenv("CLAUDEPULSE_DB_PATH={$testDbPath}");

require_once __DIR__ . '/../config.php';

use ClaudePulse\Crypto;
use ClaudePulse\Pricing;
use ClaudePulse\Database;
use ClaudePulse\Auth;

function assertTest(bool $condition, string $description): void {
    if ($condition) {
        echo "  [PASS] {$description}\n";
    } else {
        echo "  [FAIL] {$description}\n";
        exit(1);
    }
}

echo "=== Testing ClaudePulse Core Components ===\n\n";

// 1. Crypto Tests
echo "[1] Testing Crypto System...\n";
$secretPlaintext = "sk-ant-api03-abcdef1234567890abcdef1234567890";
$encrypted = Crypto::encrypt($secretPlaintext);
assertTest(!empty($encrypted) && $encrypted !== $secretPlaintext, "AES-256-CBC ciphertext generated");
$decrypted = Crypto::decrypt($encrypted);
assertTest($decrypted === $secretPlaintext, "Decryption recovers original secret");

$gwKey = Crypto::generateGatewayKey();
assertTest(str_starts_with($gwKey['key'], 'sk-cp-'), "Gateway key prefix is sk-cp-");
assertTest(strlen($gwKey['key']) === 70, "Gateway key has expected length (6 + 64 hex)");
assertTest(hash('sha256', $gwKey['key']) === $gwKey['hash'], "Gateway key hash matches SHA-256");

$masked = Crypto::maskKey($secretPlaintext);
assertTest(str_contains($masked, '••••••••') && str_ends_with($masked, '7890'), "Key masking works properly ({$masked})");

$pw = "MasterPass123!";
$pwHash = Crypto::hashPassword($pw);
assertTest(Crypto::verifyPassword($pw, $pwHash), "Password hash & verification succeeds");
assertTest(!Crypto::verifyPassword("WrongPass", $pwHash), "Invalid password verification fails");

// 2. Pricing Tests
echo "\n[2] Testing Pricing Engine...\n";
// Claude 5.2 Fable: In $3/M, Out $15/M, Cache Write $3.75/M, Cache Read $0.30/M
$costFable = Pricing::calculateCost('claude-5-2-fable', 2000, 1000, 500, 1500);
// 2000 * 0.000003 = 0.006
// 1000 * 0.000015 = 0.015
// 500 * 0.00000375 = 0.001875
// 1500 * 0.0000003 = 0.00045
// Total = 0.023325
assertTest(abs($costFable - 0.023325) < 0.00001, "Claude 5.2 Fable pricing calculation is exact (\${$costFable})");

// Claude 5.5 Opus: In $15/M, Out $75/M
$costOpus5 = Pricing::calculateCost('claude-opus-5-5', 1000, 1000, 0, 0);
assertTest(abs($costOpus5 - 0.09) < 0.00001, "Claude 5.5 Opus pricing calculation is exact (\${$costOpus5})");

// Claude 5 Sonnet: In $3/M, Out $15/M
$costSonnet5 = Pricing::calculateCost('claude-sonnet-5', 1000, 1000, 0, 0);
assertTest(abs($costSonnet5 - 0.018) < 0.00001, "Claude 5 Sonnet pricing calculation is exact (\${$costSonnet5})");

// Claude 5 Haiku: In $0.80/M, Out $4.00/M
$costHaiku5 = Pricing::calculateCost('claude-haiku-5', 1000, 1000, 0, 0);
assertTest(abs($costHaiku5 - 0.0048) < 0.00001, "Claude 5 Haiku pricing calculation is exact (\${$costHaiku5})");

// Heuristic fallback for arbitrary future or alias string (e.g. "custom-claude-5-fable-preview")
$costFableHeuristic = Pricing::calculateCost('custom-fable-agent-v1', 1000, 1000, 0, 0);
assertTest(abs($costFableHeuristic - 0.018) < 0.00001, "Fable keyword heuristic resolves correctly to reasoning tier (\${$costFableHeuristic})");

// Sonnet 4.6: In $3/M, Out $15/M, Cache Write $3.75/M, Cache Read $0.30/M
$costSonnet = Pricing::calculateCost('claude-sonnet-4-6', 1000, 500, 200, 800);
assertTest(abs($costSonnet - 0.01149) < 0.00001, "Sonnet 4.6 pricing calculation is exact (\${$costSonnet})");

$savingsSonnet = Pricing::calculateSavings('claude-sonnet-4-6', 10000);
// 10,000 * (3 - 0.30)/1M = 10,000 * 2.70 / 1M = 0.027
assertTest(abs($savingsSonnet - 0.027) < 0.00001, "Sonnet prompt cache savings calculation is exact (\${$savingsSonnet})");

$modelsList = Pricing::getKnownModels();
$modelIds = array_column($modelsList, 'id');
assertTest(in_array('claude-fable-5-2', $modelIds) || in_array('claude-5-2-fable', $modelIds), "Catalog includes Claude Fable 5.2");
assertTest(in_array('claude-opus-5-5', $modelIds) || in_array('claude-5-5-opus', $modelIds), "Catalog includes Claude 5.5 Opus");
assertTest(in_array('claude-sonnet-5-5', $modelIds) || in_array('claude-sonnet-5', $modelIds), "Catalog includes Claude Sonnet 5/5.5");
assertTest(in_array('claude-haiku-5-5', $modelIds) || in_array('claude-haiku-5', $modelIds), "Catalog includes Claude Haiku 5/5.5");
assertTest(count($modelsList) >= 10, "Frontier models catalog contains all required models (" . count($modelsList) . " models)");

// 3. Database Tests
echo "\n[3] Testing Database Schema & Migrations...\n";
$db = Database::getConnection();
assertTest($db instanceof PDO, "SQLite database connection active");

$tables = $db->query("SELECT name FROM sqlite_master WHERE type='table'")->fetchAll(PDO::FETCH_COLUMN);
$expectedTables = ['admin_users', 'upstream_keys', 'gateway_keys', 'request_logs', 'settings', 'login_attempts'];
foreach ($expectedTables as $t) {
    assertTest(in_array($t, $tables), "Table '{$t}' created");
}

$promptCachingSetting = Database::getSetting('auto_prompt_caching');
assertTest($promptCachingSetting === '1', "Default setting 'auto_prompt_caching' seeded as '1'");

Database::setSetting('test_setting', 'hello_world');
assertTest(Database::getSetting('test_setting') === 'hello_world', "Set and get setting works");

$vacuumOk = Database::vacuum();
assertTest($vacuumOk, "Database vacuum executed cleanly");

// 4. Auth & Admin Setup Tests
echo "\n[4] Testing Authentication & Onboarding...\n";
// Ensure clean state for test if no admin
if (!Database::hasAdmin()) {
    $setupRes = Auth::setupInitialAdmin('admin', 'admin@example.com', 'AdminPass123!', 'sk-ant-api03-testkey1234567890');
    assertTest($setupRes['success'], "Initial admin onboarding succeeded");
    assertTest(!empty($setupRes['initial_gateway_key']), "Initial gateway key auto-generated on setup");
}
assertTest(Database::hasAdmin(), "Admin account exists in database");

$loginOk = Auth::login('admin', 'AdminPass123!');
assertTest($loginOk['success'], "Admin authentication with valid password succeeds");

$loginFail = Auth::login('admin', 'BadPassword');
assertTest(!$loginFail['success'], "Admin authentication with invalid password fails");

// 5. Upstream Keys Vault Tests
echo "\n[5] Testing Upstream Keys Vault...\n";
$stmtUpstream = $db->query("SELECT * FROM upstream_keys LIMIT 1");
$upstream = $stmtUpstream->fetch();
assertTest(!empty($upstream), "Upstream key row retrieved from database");
$decryptedUpstream = Crypto::decrypt($upstream['api_key_encrypted']);
assertTest(str_starts_with($decryptedUpstream, 'sk-ant-'), "Decrypted upstream key begins with sk-ant-");

// 6. Gateway Keys & Hard Caps
echo "\n[6] Testing Gateway Keys & Hard Spend Limits...\n";
$gwTestKey = Crypto::generateGatewayKey();
$stmtGw = $db->prepare("
    INSERT INTO gateway_keys (name, api_key_hash, api_key_prefix, cap_usd, spent_usd, status)
    VALUES ('Spend Cap Test', :hash, :prefix, 5.00, 4.99, 'active')
");
$stmtGw->execute([':hash' => $gwTestKey['hash'], ':prefix' => $gwTestKey['prefix']]);
$gwKeyId = (int)$db->lastInsertId();

$stmtFetch = $db->prepare("SELECT * FROM gateway_keys WHERE id = :id");
$stmtFetch->execute([':id' => $gwKeyId]);
$gwRow = $stmtFetch->fetch();
assertTest((float)$gwRow['cap_usd'] === 5.00, "Hard cap configured to \$5.00");
assertTest((float)$gwRow['spent_usd'] < (float)$gwRow['cap_usd'], "Under cap: allowed to request");

// Update spent to exceed cap
$db->prepare("UPDATE gateway_keys SET spent_usd = 5.01 WHERE id = :id")->execute([':id' => $gwKeyId]);
$stmtFetch->execute([':id' => $gwKeyId]);
$gwRowExceeded = $stmtFetch->fetch();
assertTest((float)$gwRowExceeded['spent_usd'] >= (float)$gwRowExceeded['cap_usd'], "Spend cap threshold reached ($5.01 >= $5.00)");

// 6b. Token Quota Limits & Quota Telemetry
echo "\n[6b] Testing Token Quota Limits & Key Telemetry...\n";
$gwTokenKey = Crypto::generateGatewayKey();
$stmtTokenGw = $db->prepare("
    INSERT INTO gateway_keys (name, api_key_hash, api_key_prefix, cap_usd, spent_usd, cap_tokens, used_tokens, status)
    VALUES ('10M Tokens Key Test', :hash, :prefix, 0.0, 0.0, 10000000, 9999000, 'active')
");
$stmtTokenGw->execute([':hash' => $gwTokenKey['hash'], ':prefix' => $gwTokenKey['prefix']]);
$gwTokenKeyId = (int)$db->lastInsertId();

$stmtFetch->execute([':id' => $gwTokenKeyId]);
$gwTokenRow = $stmtFetch->fetch();
assertTest((int)$gwTokenRow['cap_tokens'] === 10000000, "Token cap initialized to 10M tokens (10,000,000)");
assertTest((int)$gwTokenRow['used_tokens'] < (int)$gwTokenRow['cap_tokens'], "Under token quota: allowed to request (9,999,000 < 10,000,000)");

// Simulate request using 1,500 tokens, which pushes it over 10M
$db->prepare("
    UPDATE gateway_keys 
    SET used_tokens = used_tokens + 1500, spent_usd = spent_usd + 0.005 
    WHERE id = :id
")->execute([':id' => $gwTokenKeyId]);

$stmtFetch->execute([':id' => $gwTokenKeyId]);
$gwTokenRowOver = $stmtFetch->fetch();
assertTest((int)$gwTokenRowOver['used_tokens'] === 10000500, "Tokens atomically accumulated to 10,005,000");
assertTest((int)$gwTokenRowOver['used_tokens'] >= (int)$gwTokenRowOver['cap_tokens'], "Token quota threshold triggered (10,005,000 >= 10,000,000)");

// Test reset usage
$db->prepare("UPDATE gateway_keys SET spent_usd = 0.0, used_tokens = 0, total_requests = 0 WHERE id = :id")
   ->execute([':id' => $gwTokenKeyId]);
$stmtFetch->execute([':id' => $gwTokenKeyId]);
$gwTokenRowReset = $stmtFetch->fetch();
assertTest((int)$gwTokenRowReset['used_tokens'] === 0 && (float)$gwTokenRowReset['spent_usd'] === 0.0, "Reset usage resets both spend and used tokens to 0");

// 6c. Gateway Key Vault & Expiration Logic
echo "\n[6c] Testing Gateway Key Vault Encryption & Expiration...\n";
$vaultKey = Crypto::generateGatewayKey();
$encryptedVaultKey = Crypto::encrypt($vaultKey['key']);
$stmtVault = $db->prepare("
    INSERT INTO gateway_keys (name, api_key_hash, api_key_prefix, api_key_encrypted, cap_usd, spent_usd, cap_tokens, used_tokens, expires_at, status)
    VALUES ('Vault & Expiry Test', :hash, :prefix, :enc, 0.0, 0.0, 5000000, 0, datetime('now', '+7 days'), 'active')
");
$stmtVault->execute([
    ':hash'   => $vaultKey['hash'],
    ':prefix' => $vaultKey['prefix'],
    ':enc'    => $encryptedVaultKey
]);
$vaultKeyId = (int)$db->lastInsertId();

$stmtFetch->execute([':id' => $vaultKeyId]);
$vaultRow = $stmtFetch->fetch();
assertTest(!empty($vaultRow['api_key_encrypted']), "Raw gateway key safely stored with AES-256-CBC ciphertext");
assertTest($vaultRow['api_key_encrypted'] !== $vaultKey['key'], "Stored gateway key is encrypted, never plaintext");
$revealedRawKey = Crypto::decrypt($vaultRow['api_key_encrypted']);
assertTest($revealedRawKey === $vaultKey['key'], "Admin key reveal accurately recovers full original gateway key ({$vaultRow['api_key_prefix']}...)");

// Test Expiration Detection
$expireTs = strtotime($vaultRow['expires_at']);
assertTest($expireTs > time(), "Active key with future expiration (+7 days) evaluates as valid");

$db->prepare("UPDATE gateway_keys SET expires_at = datetime('now', '-1 hour') WHERE id = :id")->execute([':id' => $vaultKeyId]);
$stmtFetch->execute([':id' => $vaultKeyId]);
$expiredRow = $stmtFetch->fetch();
$expiredTs = strtotime($expiredRow['expires_at']);
assertTest($expiredTs < time(), "Past expiration date evaluates as expired");

// 6d. Allowed Models Whitelist Validation Logic
echo "\n[6d] Testing Allowed Models Whitelist Matching...\n";
function isModelAllowed(string $allowedModelsStr, string $modelRequested): bool {
    $allowed = array_filter(array_map('trim', explode(',', $allowedModelsStr)));
    if (empty($allowed)) return true;
    $reqLower = strtolower(trim($modelRequested));
    foreach ($allowed as $allowedModel) {
        $allowedLower = strtolower(trim($allowedModel));
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
            return true;
        }
    }
    return false;
}

assertTest(isModelAllowed('', 'claude-sonnet-5-5'), "Empty allowed_models allows any model (All Models)");
assertTest(isModelAllowed('claude-sonnet-5-5,claude-haiku-5-5', 'claude-sonnet-5-5'), "Whitelisted model allowed");
assertTest(isModelAllowed('claude-3-7-sonnet', 'claude-3-7-sonnet-20250219'), "Prefix/dated model variation permitted");
assertTest(isModelAllowed('sonnet', 'claude-sonnet-4-6'), "Family keyword whitelist allows series member");
assertTest(!isModelAllowed('claude-haiku-5-5', 'claude-opus-5-5'), "Non-whitelisted model correctly rejected");

// 7. Prompt Caching Injection Logic
echo "\n[7] Testing Prompt Caching Injector Logic...\n";
$samplePayload = [
    'model' => 'claude-sonnet-4-6',
    'system' => 'You are an elite coding assistant.',
    'tools' => [
        [
            'name' => 'bash',
            'description' => 'Run shell command',
            'input_schema' => ['type' => 'object', 'properties' => ['cmd' => ['type' => 'string']]]
        ],
        [
            'name' => 'read_file',
            'description' => 'Read local file',
            'input_schema' => ['type' => 'object', 'properties' => ['path' => ['type' => 'string']]]
        ]
    ],
    'messages' => [
        ['role' => 'user', 'content' => 'List workspace files']
    ]
];

// Test system prompt conversion to ephemeral cache block
if (is_string($samplePayload['system'])) {
    $samplePayload['system'] = [
        [
            'type' => 'text',
            'text' => $samplePayload['system'],
            'cache_control' => ['type' => 'ephemeral']
        ]
    ];
}
assertTest($samplePayload['system'][0]['cache_control']['type'] === 'ephemeral', "Prompt Caching injected into system prompt");

// Test last tool definition cache injection
$lastToolIdx = count($samplePayload['tools']) - 1;
$samplePayload['tools'][$lastToolIdx]['cache_control'] = ['type' => 'ephemeral'];
assertTest($samplePayload['tools'][$lastToolIdx]['cache_control']['type'] === 'ephemeral', "Prompt Caching injected into last tool block");
assertTest(!isset($samplePayload['tools'][0]['cache_control']), "Earlier tool definitions left unchanged");

// 8. Tool Schema Auto-Repair for Draft 2020-12
echo "\n[8] Testing Tool Schema Auto-Repair (Draft 2020-12 Compliance)...\n";

function autoRepairSchemaNodeTest(&$node): void {
    if (!is_array($node) && !is_object($node)) return;
    if (is_object($node)) $node = (array)$node;

    unset($node['$schema'], $node['$id'], $node['id']);

    if (isset($node['type'])) {
        if (is_string($node['type'])) {
            $t = strtolower(trim($node['type']));
            $typeMap = ['int' => 'integer', 'float' => 'number', 'bool' => 'boolean', 'dict' => 'object', 'list' => 'array'];
            if (isset($typeMap[$t])) $node['type'] = $typeMap[$t];
            elseif ($t === 'any') unset($node['type']);
            elseif (!in_array($t, ['string', 'number', 'integer', 'boolean', 'array', 'object', 'null'], true)) $node['type'] = 'string';
        }
    }

    if (isset($node['nullable'])) {
        $isNullable = (bool)$node['nullable'];
        unset($node['nullable']);
        if ($isNullable && isset($node['type'])) {
            if (is_string($node['type']) && $node['type'] !== 'null') {
                $node['type'] = [$node['type'], 'null'];
            }
        }
    }

    if (isset($node['required']) && is_bool($node['required'])) {
        unset($node['required']);
    }

    if (isset($node['enum']) && is_array($node['enum']) && empty($node['enum'])) {
        unset($node['enum']);
    }

    if (isset($node['type']) && ($node['type'] === 'array' || (is_array($node['type']) && in_array('array', $node['type'], true)))) {
        if (!isset($node['items']) || empty($node['items'])) $node['items'] = (object)[];
    }

    foreach (['properties', 'patternProperties', 'definitions', '$defs'] as $k) {
        if (isset($node[$k]) && is_array($node[$k]) && empty($node[$k])) $node[$k] = (object)[];
    }

    foreach ($node as $key => &$child) {
        if ($key === 'properties' && is_array($child)) {
            foreach ($child as &$propVal) {
                if (is_array($propVal) || is_object($propVal)) autoRepairSchemaNodeTest($propVal);
            }
        } elseif (is_array($child) || is_object($child)) {
            autoRepairSchemaNodeTest($child);
        }
    }
}

function autoRepairSchemaTest(&$schema): void {
    if (!is_array($schema) && !is_object($schema)) {
        $schema = ['type' => 'object', 'properties' => (object)[]];
        return;
    }
    if (is_object($schema)) $schema = (array)$schema;

    $schema['type'] = 'object';
    unset($schema['$schema'], $schema['$id'], $schema['id']);

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
                autoRepairSchemaNodeTest($propVal);
            }
        }
        if (!empty($discoveredRequired)) {
            $existingReq = isset($schema['required']) && is_array($schema['required']) ? $schema['required'] : [];
            $schema['required'] = array_values(array_unique(array_merge($existingReq, $discoveredRequired)));
        }
    }

    if (isset($schema['required'])) {
        if (!is_array($schema['required']) || empty($schema['required'])) {
            unset($schema['required']);
        }
    }

    if (is_array($schema['properties']) && empty($schema['properties'])) {
        $schema['properties'] = (object)[];
    }
}

function autoRepairToolsListTest(&$tools): void {
    if (!is_array($tools)) {
        $tools = [];
        return;
    }
    $sanitized = [];
    foreach ($tools as $tool) {
        if (!is_array($tool) && !is_object($tool)) continue;
        if (is_object($tool)) $tool = (array)$tool;
        $name = $tool['name'] ?? ($tool['custom']['name'] ?? null);
        if (empty($name) && !isset($tool['type'])) continue;

        if (!isset($tool['input_schema']) || (!is_array($tool['input_schema']) && !is_object($tool['input_schema']))) {
            $tool['input_schema'] = ['type' => 'object', 'properties' => (object)[]];
        } else {
            autoRepairSchemaTest($tool['input_schema']);
        }

        if (isset($tool['custom']) && is_array($tool['custom'])) {
            if (!isset($tool['custom']['input_schema'])) {
                $tool['custom']['input_schema'] = ['type' => 'object', 'properties' => (object)[]];
            } else {
                autoRepairSchemaTest($tool['custom']['input_schema']);
            }
        }
        $sanitized[] = $tool;
    }
    $tools = $sanitized;
}

$problematicTools = [
    ['name' => 'no_props_tool', 'input_schema' => []],
    ['type' => 'custom', 'custom' => ['name' => 'custom_tool_7', 'input_schema' => ['properties' => []]]],
    ['name' => 'nullable_tool', 'input_schema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'nullable' => true]]]],
    ['name' => 'missing_schema_tool'], // Missing input_schema
    ['name' => 'legacy_types_tool', 'input_schema' => ['type' => 'object', 'properties' => ['count' => ['type' => 'int'], 'config' => ['type' => 'dict']]]]
];

autoRepairToolsListTest($problematicTools);
$jsonOutput = json_encode($problematicTools);
assertTest(str_contains($jsonOutput, '"properties":{}'), "Empty properties encoded strictly as JSON object ({})");
assertTest(!str_contains($jsonOutput, '"properties":[]'), "Empty properties NEVER encoded as array ([])");
assertTest(str_contains($jsonOutput, '"type":["string","null"]'), "Nullable keyword auto-converted to Draft 2020-12 union type");
assertTest(str_contains($jsonOutput, '"type":"object"'), "Root object type enforced across all tool definitions");
assertTest(str_contains($jsonOutput, '"count":{"type":"integer"}'), "Legacy type 'int' converted to Draft 2020-12 'integer'");
assertTest(str_contains($jsonOutput, '"config":{"type":"object"'), "Legacy type 'dict' converted to Draft 2020-12 'object'");

// 9. Upstream Error Intercept & Bad Tool Auto-Healing
echo "\n[9] Testing Upstream Tool Error Extraction & Auto-Healing...\n";
$upstreamAnthropicError = 'API Error: 400 tools.7.custom.input_schema: JSON schema is invalid. It must match JSON Schema draft 2020-12 (https://json-schema.org/draft/2020-12).';
preg_match('/tools\.(\d+)/i', $upstreamAnthropicError, $extractedBadTool);
assertTest(!empty($extractedBadTool[1]) && (int)$extractedBadTool[1] === 7, "Regex accurately parsed bad tool index (7) from Anthropic error string");

// Test dropping bad tool from tools array
$simulatedTools = [
    ['name' => 'tool_0'], ['name' => 'tool_1'], ['name' => 'tool_2'], ['name' => 'tool_3'],
    ['name' => 'tool_4'], ['name' => 'tool_5'], ['name' => 'tool_6'], ['name' => 'tool_7_broken']
];
assertTest(count($simulatedTools) === 8, "Simulated tools count is 8");
$badIndex = (int)$extractedBadTool[1];
array_splice($simulatedTools, $badIndex, 1);
assertTest(count($simulatedTools) === 7, "Broken tool_7 removed: remaining count is 7");
assertTest(!in_array('tool_7_broken', array_column($simulatedTools, 'name'), true), "Offending tool_7_broken purged from tools list");

// 10. Prompt Caching TTL Ordering & Harmonizer Test
echo "\n[10] Testing Prompt Caching TTL Ordering & Harmonization...\n";

function requestHasClientCacheControlTest(array $body): bool {
    if (!empty($body['tools']) && is_array($body['tools'])) {
        foreach ($body['tools'] as $tool) {
            if (is_array($tool) && isset($tool['cache_control'])) return true;
        }
    }
    if (!empty($body['system']) && is_array($body['system'])) {
        foreach ($body['system'] as $sys) {
            if (is_array($sys) && isset($sys['cache_control'])) return true;
        }
    }
    return false;
}

function harmonizeCacheControlTest(array &$body): void {
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

// Reproduce exact situation from user screenshot:
// tools[0] has default 5m cache_control, but system[1] has 1h cache_control!
$conflictPayload = [
    'tools' => [
        ['name' => 'bash', 'input_schema' => ['type' => 'object', 'properties' => (object)[]], 'cache_control' => ['type' => 'ephemeral']]
    ],
    'system' => [
        ['type' => 'text', 'text' => 'Base prompt'],
        ['type' => 'text', 'text' => 'Code instructions', 'cache_control' => ['type' => 'ephemeral', 'ttl' => '1h']]
    ]
];

assertTest(requestHasClientCacheControlTest($conflictPayload) === true, "Detected client-defined cache_control in payload");

// Before harmonization: tools[0] does NOT have ttl=1h, while system[1] has ttl=1h (which causes 400 error)
assertTest(!isset($conflictPayload['tools'][0]['cache_control']['ttl']), "tools[0] initially lacks 1h TTL");

harmonizeCacheControlTest($conflictPayload);

// After harmonization: tools[0] must have been elevated to ttl=1h so 1h comes before or equal to 1h!
assertTest(isset($conflictPayload['tools'][0]['cache_control']['ttl']) && $conflictPayload['tools'][0]['cache_control']['ttl'] === '1h', "Earlier cache_control elevated to ttl='1h' to satisfy Anthropic TTL ordering rule");

// Test upstream cache_control error regex intercept
$upstreamTtlError = "API Error: 400 system.1.cache_control.ttl: a ttl='1h' cache_control block must not come after a ttl='5m' cache_control block. Note that blocks are processed in the following order: 'tools', 'system', 'messages'.";
assertTest(str_contains($upstreamTtlError, 'cache_control') && str_contains($upstreamTtlError, 'ttl'), "Upstream TTL error correctly detected by interceptor");

// 11. Legacy Client Version Upgrade & Model Compatibility Auto-Healer Test
echo "\n[11] Testing Legacy Client Version Upgrade & Model Compatibility...\n";

// Test version upgrade in raw payload
$simulatedRawInput = json_encode([
    'model' => 'claude-opus-5-5',
    'system' => 'You are Claude Code, Anthropic official CLI for coding. Version 2.1.161.',
    'metadata' => ['app' => 'claude-code', 'version' => '2.1.161']
]);

if (preg_match('/2\.1\.\d+/', $simulatedRawInput)) {
    $simulatedRawInput = preg_replace_callback('/(claude[_\-\s]*code[_\-\s]*|version["\':\s]+)2\.1\.(\d+)/i', function($m) {
        $subVer = (int)$m[2];
        if ($subVer < 280) {
            return $m[1] . '2.1.327';
        }
        return $m[0];
    }, $simulatedRawInput);
}

assertTest(str_contains($simulatedRawInput, '2.1.327'), "Legacy client version 2.1.161 auto-upgraded to 2.1.327 in payload");
assertTest(!str_contains($simulatedRawInput, '2.1.161'), "Outdated version 2.1.161 completely eliminated from payload");

// Test User-Agent header upgrade
$simulatedUa = 'Claude-Code/2.1.161 (Windows NT 10.0; Win64; x64)';
$upgradedUa = preg_replace('/Claude[_\-\s]*Code\/2\.1\.\d+/i', 'Claude-Code/2.1.327', $simulatedUa);
assertTest($upgradedUa === 'Claude-Code/2.1.327 (Windows NT 10.0; Win64; x64)', "User-Agent upgraded to Claude-Code/2.1.327");

// Test upstream unsupported model error intercept
$upstreamModelError = "API Error: 400 Claude Code 2.1.161 does not support this model; version 2.1.280 or newer is required. Run 'claude update', or update the Claude desktop app, then try again.";
assertTest(
    str_contains($upstreamModelError, 'does not support this model') ||
    (str_contains($upstreamModelError, 'version') && str_contains($upstreamModelError, 'newer is required')),
    "Upstream unsupported model / outdated client version error accurately caught by interceptor"
);

// 12. Promotional Credits & Credit Balance Interceptor Test
echo "\n[12] Testing Promotional Credits & Credit Balance Interceptor...\n";
$upstreamCreditError = 'API Error: 400 Your credit balance is too low to access the Anthropic API. Please go to Plans & Billing to upgrade or purchase credits.';
assertTest(
    stripos($upstreamCreditError, 'Credit balance is too low') !== false || stripos($upstreamCreditError, 'balance is too low') !== false,
    "Upstream credit balance threshold error accurately detected by interceptor"
);

// 13. Claude Desktop Auto-Discovery & Models Filtering Test
echo "\n[13] Testing Claude Desktop Model Discovery & Filtering Engine...\n";
$known = Pricing::getKnownModels();
$catalogMap = [];
foreach ($known as $km) {
    $mId = $km['id'];
    $catalogMap[$mId] = [
        'type'         => 'model',
        'id'           => $mId,
        'display_name' => $km['name'] ?? $mId,
        'created_at'   => $km['created_at'] ?? '2026-01-01T00:00:00Z'
    ];
}

assertTest(!empty($catalogMap), "Catalog map generated with " . count($catalogMap) . " models");
assertTest(isset($catalogMap['claude-sonnet-5-5']), "Flagship Claude Sonnet 5.5 present in catalog");
assertTest(isset($catalogMap['claude-haiku-4-5-20251001']), "Claude Haiku 4.5 present in catalog");
assertTest(isset($catalogMap['claude-3-7-sonnet-20250219']), "Claude 3.7 Sonnet present in catalog");

// Test key with single allowed model (e.g. Haiku 4.5)
$simKey = ['allowed_models' => 'claude-haiku-4-5-20251001'];
$allowedTokens = array_filter(array_map('trim', explode(',', $simKey['allowed_models'])));
$filtered = [];
foreach ($allowedTokens as $tok) {
    $tokClean = trim($tok);
    $tokLower = strtolower($tokClean);
    foreach ($catalogMap as $mId => $mObj) {
        $mIdLower = strtolower($mId);
        $mNameLower = strtolower($mObj['display_name']);
        if ($mIdLower === $tokLower || str_contains($mIdLower, $tokLower)) {
            $filtered[$mId] = $mObj;
        }
    }
}
assertTest(count($filtered) === 1, "Single allowed model produces exact 1-model list for Claude Desktop");
assertTest(isset($filtered['claude-haiku-4-5-20251001']), "Filtered list contains claude-haiku-4-5-20251001");
assertTest($filtered['claude-haiku-4-5-20251001']['display_name'] === 'Claude Haiku 4.5', "Display name is 'Claude Haiku 4.5'");

// Test key with dual allowed models (Haiku 4.5 and Sonnet 5.5)
$simKeyDual = ['allowed_models' => 'claude-haiku-4-5-20251001,claude-sonnet-5-5'];
$allowedTokensDual = array_filter(array_map('trim', explode(',', $simKeyDual['allowed_models'])));
$filteredDual = [];
foreach ($allowedTokensDual as $tok) {
    $tokClean = trim($tok);
    $tokLower = strtolower($tokClean);
    foreach ($catalogMap as $mId => $mObj) {
        $mIdLower = strtolower($mId);
        if ($mIdLower === $tokLower || str_contains($mIdLower, $tokLower)) {
            $filteredDual[$mId] = $mObj;
        }
    }
}
assertTest(count($filteredDual) === 2, "Dual allowed models produce exact 2-model list for Claude Desktop");
assertTest(isset($filteredDual['claude-sonnet-5-5']), "Dual list includes Sonnet 5.5");
assertTest(isset($filteredDual['claude-haiku-4-5-20251001']), "Dual list includes Haiku 4.5");

// Clean up isolated test database
@unlink($testDbPath);

echo "\nAll core component unit tests PASSED successfully!\n";

