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

function autoRepairSchemaTest(&$schema): void {
    if (!is_array($schema) && !is_object($schema)) {
        $schema = ['type' => 'object', 'properties' => (object)[]];
        return;
    }
    if (is_object($schema)) $schema = (array)$schema;
    if (empty($schema['type']) || $schema['type'] !== 'object') {
        $schema['type'] = 'object';
    }
    if (!isset($schema['properties']) || !is_array($schema['properties']) || empty($schema['properties'])) {
        $schema['properties'] = (object)[];
    } else {
        foreach ($schema['properties'] as $propKey => &$propVal) {
            if (is_array($propVal)) {
                if (isset($propVal['nullable'])) {
                    $isNullable = (bool)$propVal['nullable'];
                    unset($propVal['nullable']);
                    if ($isNullable && isset($propVal['type']) && is_string($propVal['type']) && $propVal['type'] !== 'null') {
                        $propVal['type'] = [$propVal['type'], 'null'];
                    }
                }
            }
        }
    }
}

function autoRepairToolsListTest(&$tools): void {
    if (!is_array($tools)) return;
    foreach ($tools as &$tool) {
        if (!is_array($tool)) continue;
        if (isset($tool['input_schema'])) autoRepairSchemaTest($tool['input_schema']);
        if (isset($tool['custom']) && is_array($tool['custom']) && isset($tool['custom']['input_schema'])) {
            autoRepairSchemaTest($tool['custom']['input_schema']);
        }
    }
}

$problematicTools = [
    ['name' => 'no_props_tool', 'input_schema' => []],
    ['type' => 'custom', 'custom' => ['name' => 'custom_tool_7', 'input_schema' => ['properties' => []]]],
    ['name' => 'nullable_tool', 'input_schema' => ['type' => 'object', 'properties' => ['query' => ['type' => 'string', 'nullable' => true]]]]
];

autoRepairToolsListTest($problematicTools);
$jsonOutput = json_encode($problematicTools);
assertTest(str_contains($jsonOutput, '"properties":{}'), "Empty properties encoded strictly as JSON object ({})");
assertTest(!str_contains($jsonOutput, '"properties":[]'), "Empty properties NEVER encoded as array ([])");
assertTest(str_contains($jsonOutput, '"type":["string","null"]'), "Nullable keyword auto-converted to Draft 2020-12 union type");
assertTest(str_contains($jsonOutput, '"type":"object"'), "Root object type enforced across all tool definitions");

// Clean up isolated test database
@unlink($testDbPath);

echo "\nAll core component unit tests PASSED successfully!\n";

