<?php
declare(strict_types=1);

/**
 * ClaudePulse Internal AJAX API
 * Provides secure JSON endpoints for dashboard actions, telemetry analytics, keys vault, and settings.
 */

require_once __DIR__ . '/../config.php';

use ClaudePulse\Database;
use ClaudePulse\Crypto;
use ClaudePulse\Pricing;
use ClaudePulse\Auth;

// Require active admin authentication for all internal operations
Auth::requireAuth(true);

$method = $_SERVER['REQUEST_METHOD'] ?? 'GET';
$uri = $_SERVER['REQUEST_URI'] ?? '';
$action = $_GET['action'] ?? '';

// If action is empty, parse from path (e.g. /api/internal/stats)
if (empty($action)) {
    $path = parse_url($uri, PHP_URL_PATH) ?: '';
    if (preg_match('#/(?:api/internal|internal)/([a-zA-Z0-9_\-/]+)#', $path, $matches)) {
        $action = trim($matches[1], '/');
    }
}

$db = Database::getConnection();

// Read JSON input if sent
$rawBody = file_get_contents('php://input');
$body = !empty($rawBody) ? (json_decode($rawBody, true) ?: []) : $_POST;

// CSRF check for state-mutating requests
if ($method === 'POST') {
    $csrf = $body['csrf_token'] ?? $_SERVER['HTTP_X_CSRF_TOKEN'] ?? '';
    if (!Auth::validateCsrf($csrf)) {
        jsonResponse(['error' => 'Invalid or expired CSRF security token.'], 403);
    }
}

// -------------------------------------------------------------
// Route Dispatcher
// -------------------------------------------------------------
switch ($action) {
    // ---------------------------------------------------------
    // 1. Dashboard Analytics & KPIs
    // ---------------------------------------------------------
    case 'stats':
        $timeframe = $_GET['timeframe'] ?? '24h';
        $gatewayKeyId = !empty($_GET['gateway_key_id']) ? (int)$_GET['gateway_key_id'] : null;

        $timeCondition = match ($timeframe) {
            '24h'    => "created_at >= datetime('now', '-24 hours')",
            '7d'     => "created_at >= datetime('now', '-7 days')",
            '30d'    => "created_at >= datetime('now', '-30 days')",
            'all'    => "1=1",
            default  => "created_at >= datetime('now', '-24 hours')"
        };

        $params = [];
        $keyCondition = "1=1";
        if ($gatewayKeyId !== null && $gatewayKeyId > 0) {
            $keyCondition = "gateway_key_id = :gid";
            $params[':gid'] = $gatewayKeyId;
        }

        // Aggregate Overview KPIs
        $sqlKpi = "
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status_code = 200 THEN 1 ELSE 0 END) as successful_requests,
                COALESCE(SUM(cost_usd), 0.0) as total_spend_usd,
                COALESCE(SUM(saved_usd), 0.0) as total_saved_usd,
                COALESCE(SUM(input_tokens), 0) as total_input_tokens,
                COALESCE(SUM(output_tokens), 0) as total_output_tokens,
                COALESCE(SUM(cache_creation_input_tokens), 0) as total_cache_creation_tokens,
                COALESCE(SUM(cache_read_input_tokens), 0) as total_cache_read_tokens,
                COALESCE(AVG(latency_ms), 0) as avg_latency_ms,
                COALESCE(AVG(ttft_ms), 0) as avg_ttft_ms
            FROM request_logs
            WHERE {$timeCondition} AND {$keyCondition}
        ";
        $stmtKpi = $db->prepare($sqlKpi);
        $stmtKpi->execute($params);
        $kpis = $stmtKpi->fetch() ?: [];

        $totalSpend = (float)($kpis['total_spend_usd'] ?? 0.0);
        $totalSaved = (float)($kpis['total_saved_usd'] ?? 0.0);
        $unoptimizedCost = $totalSpend + $totalSaved;
        $savingsPct = $unoptimizedCost > 0 ? round(($totalSaved / $unoptimizedCost) * 100, 1) : 0.0;

        $totalReqs = (int)($kpis['total_requests'] ?? 0);
        $successReqs = (int)($kpis['successful_requests'] ?? 0);
        $successRate = $totalReqs > 0 ? round(($successReqs / $totalReqs) * 100, 1) : 100.0;

        // Timeline chart points (group by hour for 24h, group by day for 7d/30d/all)
        $groupBy = ($timeframe === '24h') 
            ? "strftime('%Y-%m-%d %H:00', created_at)" 
            : "strftime('%Y-%m-%d', created_at)";

        $sqlTimeline = "
            SELECT 
                {$groupBy} as time_bucket,
                COUNT(*) as requests,
                COALESCE(SUM(cost_usd), 0.0) as spend,
                COALESCE(SUM(saved_usd), 0.0) as saved,
                COALESCE(SUM(input_tokens + output_tokens + cache_creation_input_tokens + cache_read_input_tokens), 0) as total_tokens
            FROM request_logs
            WHERE {$timeCondition} AND {$keyCondition}
            GROUP BY time_bucket
            ORDER BY time_bucket ASC
        ";
        $stmtTimeline = $db->prepare($sqlTimeline);
        $stmtTimeline->execute($params);
        $timelineRows = $stmtTimeline->fetchAll();

        // Model distribution chart
        $sqlModels = "
            SELECT 
                COALESCE(model, 'unknown') as model_name,
                COUNT(*) as requests,
                COALESCE(SUM(cost_usd), 0.0) as spend,
                COALESCE(SUM(input_tokens + output_tokens), 0) as tokens
            FROM request_logs
            WHERE {$timeCondition} AND {$keyCondition}
            GROUP BY model_name
            ORDER BY spend DESC
        ";
        $stmtModels = $db->prepare($sqlModels);
        $stmtModels->execute($params);
        $modelRows = $stmtModels->fetchAll();

        // Recent Requests snippet
        $sqlRecent = "
            SELECT 
                id, endpoint, model, status_code, is_streaming,
                input_tokens, output_tokens, cache_read_input_tokens,
                cost_usd, latency_ms, client_tag, created_at
            FROM request_logs
            WHERE {$timeCondition} AND {$keyCondition}
            ORDER BY id DESC
            LIMIT 8
        ";
        $stmtRecent = $db->prepare($sqlRecent);
        $stmtRecent->execute($params);
        $recentRows = $stmtRecent->fetchAll();

        jsonResponse([
            'kpis' => [
                'total_spend_usd'          => round($totalSpend, 4),
                'total_saved_usd'          => round($totalSaved, 4),
                'savings_percentage'       => $savingsPct,
                'total_requests'           => $totalReqs,
                'success_rate'             => $successRate,
                'total_input_tokens'       => (int)($kpis['total_input_tokens'] ?? 0),
                'total_output_tokens'      => (int)($kpis['total_output_tokens'] ?? 0),
                'total_cache_read_tokens'  => (int)($kpis['total_cache_read_tokens'] ?? 0),
                'total_cache_write_tokens' => (int)($kpis['total_cache_creation_tokens'] ?? 0),
                'avg_latency_ms'           => (int)round((float)($kpis['avg_latency_ms'] ?? 0)),
                'avg_ttft_ms'              => (int)round((float)($kpis['avg_ttft_ms'] ?? 0))
            ],
            'timeline' => $timelineRows,
            'models'   => $modelRows,
            'recent'   => $recentRows
        ]);
        break;

    // ---------------------------------------------------------
    // 2. Gateway API Keys Management
    // ---------------------------------------------------------
    case 'keys':
        $stmt = $db->query("
            SELECT id, name, api_key_prefix, cap_usd, spent_usd, cap_tokens, used_tokens,
                   rate_limit_rpm, allowed_models, status, expires_at, 
                   total_requests, last_used_at, created_at
            FROM gateway_keys
            ORDER BY id DESC
        ");
        $keys = $stmt->fetchAll();
        jsonResponse(['keys' => $keys]);
        break;

    case 'keys/create':
        $name = trim($body['name'] ?? 'Claude Code Client');
        $capUsd = !empty($body['cap_usd']) ? (float)$body['cap_usd'] : 0.0;
        $capTokens = !empty($body['cap_tokens']) ? (int)$body['cap_tokens'] : 0;
        $rpm = !empty($body['rate_limit_rpm']) ? (int)$body['rate_limit_rpm'] : 0;
        $allowedModels = !empty($body['allowed_models']) ? trim($body['allowed_models']) : null;
        $expiresAt = !empty($body['expires_at']) ? trim($body['expires_at']) : null;

        $newKeyData = Crypto::generateGatewayKey();

        $stmt = $db->prepare("
            INSERT INTO gateway_keys (
                name, api_key_hash, api_key_prefix, cap_usd, spent_usd, cap_tokens, used_tokens,
                rate_limit_rpm, allowed_models, status, expires_at, created_at
            ) VALUES (
                :name, :hash, :prefix, :cap, 0.0, :cap_tok, 0,
                :rpm, :models, 'active', :expires, datetime('now')
            )
        ");
        $stmt->execute([
            ':name'     => $name,
            ':hash'     => $newKeyData['hash'],
            ':prefix'   => $newKeyData['prefix'],
            ':cap'      => $capUsd,
            ':cap_tok'  => $capTokens,
            ':rpm'      => $rpm,
            ':models'   => $allowedModels,
            ':expires'  => $expiresAt
        ]);

        $newId = (int)$db->lastInsertId();

        jsonResponse([
            'success' => true,
            'key'     => [
                'id'             => $newId,
                'name'           => $name,
                'full_key'       => $newKeyData['key'],
                'api_key_prefix' => $newKeyData['prefix'],
                'cap_usd'        => $capUsd,
                'spent_usd'      => 0.0,
                'cap_tokens'     => $capTokens,
                'used_tokens'    => 0,
                'status'         => 'active'
            ]
        ]);
        break;

    case 'keys/update':
        $id = (int)($body['id'] ?? 0);
        $name = trim($body['name'] ?? '');
        $capUsd = isset($body['cap_usd']) ? (float)$body['cap_usd'] : 0.0;
        $capTokens = isset($body['cap_tokens']) ? (int)$body['cap_tokens'] : 0;
        $rpm = isset($body['rate_limit_rpm']) ? (int)$body['rate_limit_rpm'] : 0;
        $status = in_array($body['status'] ?? '', ['active', 'revoked'], true) ? $body['status'] : 'active';
        $allowedModels = !empty($body['allowed_models']) ? trim($body['allowed_models']) : null;
        $expiresAt = !empty($body['expires_at']) ? trim($body['expires_at']) : null;

        if ($id <= 0) {
            jsonResponse(['error' => 'Invalid key ID.'], 400);
        }

        $stmt = $db->prepare("
            UPDATE gateway_keys 
            SET name = :name,
                cap_usd = :cap,
                cap_tokens = :cap_tok,
                rate_limit_rpm = :rpm,
                status = :status,
                allowed_models = :models,
                expires_at = :expires
            WHERE id = :id
        ");
        $stmt->execute([
            ':name'     => $name,
            ':cap'      => $capUsd,
            ':cap_tok'  => $capTokens,
            ':rpm'      => $rpm,
            ':status'   => $status,
            ':models'   => $allowedModels,
            ':expires'  => $expiresAt,
            ':id'       => $id
        ]);

        jsonResponse(['success' => true]);
        break;

    case 'keys/reset_usage':
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['error' => 'Invalid key ID.'], 400);
        }

        $stmt = $db->prepare("UPDATE gateway_keys SET spent_usd = 0.0, used_tokens = 0, total_requests = 0 WHERE id = :id");
        $stmt->execute([':id' => $id]);

        jsonResponse(['success' => true]);
        break;

    case 'keys/delete':
        $id = (int)($body['id'] ?? 0);
        if ($id <= 0) {
            jsonResponse(['error' => 'Invalid key ID.'], 400);
        }

        $stmt = $db->prepare("DELETE FROM gateway_keys WHERE id = :id");
        $stmt->execute([':id' => $id]);

        jsonResponse(['success' => true]);
        break;

    // ---------------------------------------------------------
    // 3. Upstream Anthropic Key Vault
    // ---------------------------------------------------------
    case 'upstream':
        $stmt = $db->query("
            SELECT id, label, key_hint, is_active, priority, last_checked_at, status_message, created_at
            FROM upstream_keys
            ORDER BY priority ASC, id ASC
        ");
        $upstreams = $stmt->fetchAll();
        jsonResponse(['upstream_keys' => $upstreams]);
        break;

    case 'upstream/add':
        $label = trim($body['label'] ?? 'Anthropic Official Key');
        $rawKey = trim($body['api_key'] ?? '');

        if (!str_starts_with($rawKey, 'sk-ant-')) {
            jsonResponse(['error' => 'Invalid Anthropic API Key. Must start with "sk-ant-"'], 400);
        }

        $encrypted = Crypto::encrypt($rawKey);
        $hint = Crypto::maskKey($rawKey);

        $stmt = $db->prepare("
            INSERT INTO upstream_keys (label, api_key_encrypted, key_hint, is_active, priority, status_message, created_at)
            VALUES (:label, :enc, :hint, 1, 1, 'Key added, testing recommended', datetime('now'))
        ");
        $stmt->execute([
            ':label' => $label,
            ':enc'   => $encrypted,
            ':hint'  => $hint
        ]);

        jsonResponse(['success' => true, 'id' => (int)$db->lastInsertId()]);
        break;

    case 'upstream/test':
        $id = (int)($body['id'] ?? 0);
        $rawKeyToTest = '';

        if ($id > 0) {
            $stmt = $db->prepare("SELECT * FROM upstream_keys WHERE id = :id LIMIT 1");
            $stmt->execute([':id' => $id]);
            $row = $stmt->fetch();
            if ($row) {
                try {
                    $rawKeyToTest = Crypto::decrypt($row['api_key_encrypted']);
                } catch (\Exception $e) {
                    jsonResponse(['error' => 'Decryption failed for key.'], 500);
                }
            }
        } elseif (!empty($body['api_key'])) {
            $rawKeyToTest = trim($body['api_key']);
        }

        if (empty($rawKeyToTest)) {
            jsonResponse(['error' => 'No key provided for testing.'], 400);
        }

        // Real ping to Anthropic API /v1/models endpoint
        $pingStart = microtime(true);
        $ch = curl_init('https://api.anthropic.com/v1/models');
        curl_setopt($ch, CURLOPT_HTTPHEADER, [
            'x-api-key: ' . $rawKeyToTest,
            'anthropic-version: 2023-06-01'
        ]);
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 10);
        curl_setopt($ch, CURLOPT_TIMEOUT, 15);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        $curlErr = curl_error($ch);
        curl_close($ch);

        $latency = (int)round((microtime(true) - $pingStart) * 1000);

        if ($curlErr) {
            $statusMsg = "Connection error: {$curlErr}";
            if ($id > 0) {
                $db->prepare("UPDATE upstream_keys SET status_message = :msg, last_checked_at = datetime('now') WHERE id = :id")
                   ->execute([':msg' => $statusMsg, ':id' => $id]);
            }
            jsonResponse(['success' => false, 'error' => $statusMsg, 'latency_ms' => $latency]);
        }

        if ($httpCode === 200) {
            $data = json_decode($resp, true);
            $modelCount = isset($data['data']) && is_array($data['data']) ? count($data['data']) : 0;
            $statusMsg = "Active & Verified ({$modelCount} models, {$latency}ms)";

            if ($id > 0) {
                $db->prepare("UPDATE upstream_keys SET status_message = :msg, last_checked_at = datetime('now') WHERE id = :id")
                   ->execute([':msg' => $statusMsg, ':id' => $id]);
            }

            jsonResponse([
                'success'    => true,
                'latency_ms' => $latency,
                'message'    => "Key is valid and active!",
                'status'     => $statusMsg
            ]);
        } else {
            $errData = json_decode($resp, true);
            $errText = $errData['error']['message'] ?? "HTTP {$httpCode}";
            $statusMsg = "Invalid: {$errText}";

            if ($id > 0) {
                $db->prepare("UPDATE upstream_keys SET status_message = :msg, last_checked_at = datetime('now') WHERE id = :id")
                   ->execute([':msg' => $statusMsg, ':id' => $id]);
            }

            jsonResponse([
                'success'    => false,
                'latency_ms' => $latency,
                'error'      => "Anthropic API rejected key: {$errText}",
                'status'     => $statusMsg
            ]);
        }
        break;

    case 'upstream/toggle':
        $id = (int)($body['id'] ?? 0);
        $isActive = !empty($body['is_active']) ? 1 : 0;

        $stmt = $db->prepare("UPDATE upstream_keys SET is_active = :act WHERE id = :id");
        $stmt->execute([':act' => $isActive, ':id' => $id]);

        jsonResponse(['success' => true]);
        break;

    case 'upstream/delete':
        $id = (int)($body['id'] ?? 0);
        $stmt = $db->prepare("DELETE FROM upstream_keys WHERE id = :id");
        $stmt->execute([':id' => $id]);

        jsonResponse(['success' => true]);
        break;

    // ---------------------------------------------------------
    // 4. Request Logs & Inspector
    // ---------------------------------------------------------
    case 'logs':
        $page = max(1, (int)($_GET['page'] ?? 1));
        $perPage = min(100, max(10, (int)($_GET['per_page'] ?? 25)));
        $offset = ($page - 1) * $perPage;

        $conditions = ["1=1"];
        $params = [];

        if (!empty($_GET['gateway_key_id'])) {
            $conditions[] = "gateway_key_id = :gid";
            $params[':gid'] = (int)$_GET['gateway_key_id'];
        }

        if (!empty($_GET['status_code'])) {
            $conditions[] = "status_code = :status";
            $params[':status'] = (int)$_GET['status_code'];
        }

        if (!empty($_GET['model'])) {
            $conditions[] = "model LIKE :model";
            $params[':model'] = '%' . trim($_GET['model']) . '%';
        }

        if (!empty($_GET['client_tag'])) {
            $conditions[] = "client_tag = :tag";
            $params[':tag'] = trim($_GET['client_tag']);
        }

        if (!empty($_GET['search'])) {
            $conditions[] = "(model LIKE :q OR endpoint LIKE :q OR client_ip LIKE :q OR user_agent LIKE :q)";
            $params[':q'] = '%' . trim($_GET['search']) . '%';
        }

        $whereClause = implode(' AND ', $conditions);

        // Count total matching
        $stmtCount = $db->prepare("SELECT COUNT(*) FROM request_logs WHERE {$whereClause}");
        $stmtCount->execute($params);
        $totalLogs = (int)$stmtCount->fetchColumn();

        // Fetch paginated rows with key name
        $stmtLogs = $db->prepare("
            SELECT r.*, k.name as gateway_key_name, k.api_key_prefix
            FROM request_logs r
            LEFT JOIN gateway_keys k ON r.gateway_key_id = k.id
            WHERE {$whereClause}
            ORDER BY r.id DESC
            LIMIT {$perPage} OFFSET {$offset}
        ");
        $stmtLogs->execute($params);
        $logs = $stmtLogs->fetchAll();

        jsonResponse([
            'logs'        => $logs,
            'total'       => $totalLogs,
            'page'        => $page,
            'per_page'    => $perPage,
            'total_pages' => (int)ceil($totalLogs / $perPage)
        ]);
        break;

    case 'logs/export':
        $format = strtolower($_GET['format'] ?? 'csv');

        $stmt = $db->query("
            SELECT r.id, r.created_at, k.name as key_label, r.client_tag, r.model, 
                   r.status_code, r.input_tokens, r.output_tokens, 
                   r.cache_creation_input_tokens, r.cache_read_input_tokens, 
                   r.cost_usd, r.saved_usd, r.latency_ms, r.ttft_ms, r.client_ip
            FROM request_logs r
            LEFT JOIN gateway_keys k ON r.gateway_key_id = k.id
            ORDER BY r.id DESC
            LIMIT 5000
        ");
        $rows = $stmt->fetchAll();

        if ($format === 'json') {
            header('Content-Type: application/json');
            header('Content-Disposition: attachment; filename="claudepulse_logs_' . date('Ymd_His') . '.json"');
            echo json_encode($rows, JSON_PRETTY_PRINT);
            exit;
        }

        // Default CSV
        header('Content-Type: text/csv; charset=utf-8');
        header('Content-Disposition: attachment; filename="claudepulse_logs_' . date('Ymd_His') . '.csv"');
        $output = fopen('php://output', 'w');
        fputcsv($output, [
            'ID', 'Date (UTC)', 'Gateway Key', 'Client Tag', 'Model',
            'Status', 'Input Tokens', 'Output Tokens', 'Cache Write', 'Cache Read',
            'Cost ($ USD)', 'Saved ($ USD)', 'Total Latency (ms)', 'TTFT (ms)', 'Client IP'
        ]);

        foreach ($rows as $r) {
            fputcsv($output, [
                $r['id'],
                $r['created_at'],
                $r['key_label'] ?? 'Unknown Key',
                $r['client_tag'],
                $r['model'],
                $r['status_code'],
                $r['input_tokens'],
                $r['output_tokens'],
                $r['cache_creation_input_tokens'],
                $r['cache_read_input_tokens'],
                $r['cost_usd'],
                $r['saved_usd'],
                $r['latency_ms'],
                $r['ttft_ms'],
                $r['client_ip']
            ]);
        }
        fclose($output);
        exit;

    // ---------------------------------------------------------
    // 5. System Settings & Gateway Toggles
    // ---------------------------------------------------------
    case 'settings/update':
        $settingsToUpdate = [
            'auto_prompt_caching' => !empty($body['auto_prompt_caching']) ? '1' : '0',
            'public_status_page'  => !empty($body['public_status_page']) ? '1' : '0',
            'auto_fallback'       => !empty($body['auto_fallback']) ? '1' : '0',
            'log_payloads'        => !empty($body['log_payloads']) ? '1' : '0',
        ];

        foreach ($settingsToUpdate as $key => $val) {
            Database::setSetting($key, $val);
        }

        jsonResponse(['success' => true]);
        break;

    case 'settings/account':
        $username = trim($body['username'] ?? '');
        $email = trim($body['email'] ?? '');
        $currentPassword = $body['current_password'] ?? '';
        $newPassword = $body['new_password'] ?? '';

        $adminId = $_SESSION['claudepulse_admin_id'];
        $stmt = $db->prepare("SELECT * FROM admin_users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $adminId]);
        $admin = $stmt->fetch();

        if (!$admin || !Crypto::verifyPassword($currentPassword, $admin['password_hash'])) {
            jsonResponse(['error' => 'Current password is incorrect.'], 400);
        }

        if (strlen($username) < 3) {
            jsonResponse(['error' => 'Username must be at least 3 characters.'], 400);
        }

        if (!empty($newPassword)) {
            if (strlen($newPassword) < 8) {
                jsonResponse(['error' => 'New password must be at least 8 characters.'], 400);
            }
            $newHash = Crypto::hashPassword($newPassword);
            $stmtUpd = $db->prepare("UPDATE admin_users SET username = :u, email = :e, password_hash = :p WHERE id = :id");
            $stmtUpd->execute([':u' => $username, ':e' => $email ?: null, ':p' => $newHash, ':id' => $adminId]);
        } else {
            $stmtUpd = $db->prepare("UPDATE admin_users SET username = :u, email = :e WHERE id = :id");
            $stmtUpd->execute([':u' => $username, ':e' => $email ?: null, ':id' => $adminId]);
        }

        $_SESSION['claudepulse_username'] = $username;
        jsonResponse(['success' => true]);
        break;

    case 'models/sync':
    case 'sync_models':
        $models = Pricing::syncFromAnthropic();
        jsonResponse([
            'success'   => true,
            'models'    => $models,
            'count'     => count($models),
            'synced_at' => Database::getSetting('models_last_synced_at') ?: date('Y-m-d H:i:s')
        ]);
        break;

    case 'models':
        $models = Pricing::getKnownModels();
        jsonResponse([
            'success'   => true,
            'models'    => $models,
            'count'     => count($models),
            'synced_at' => Database::getSetting('models_last_synced_at') ?: 'Not synced'
        ]);
        break;

    case 'maintenance/vacuum':
        Database::vacuum();
        $size = Database::getDatabaseSize();
        jsonResponse([
            'success'   => true,
            'db_size'   => $size,
            'db_size_formatted' => round($size / 1024, 1) . ' KB'
        ]);
        break;

    // ---------------------------------------------------------
    // 6. In-Dashboard 1-Click Auto-Updater (GitHub Sync)
    // ---------------------------------------------------------
    case 'updater/check':
        $repo = defined('CLAUDEPULSE_GITHUB_REPO') ? CLAUDEPULSE_GITHUB_REPO : 'DeveloperSarim/ClaudePulse';
        $currentVersion = defined('CLAUDEPULSE_VERSION') ? CLAUDEPULSE_VERSION : '1.2.0';

        $ch = curl_init("https://api.github.com/repos/{$repo}/commits/main");
        curl_setopt($ch, CURLOPT_RETURNTRANSFER, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ClaudePulse-Updater/1.2.0');
        curl_setopt($ch, CURLOPT_CONNECTTIMEOUT, 6);
        curl_setopt($ch, CURLOPT_TIMEOUT, 10);
        $resp = curl_exec($ch);
        $httpCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);

        $latestCommit = null;
        $commitMessage = null;
        $commitDate = null;

        if ($httpCode === 200 && $resp) {
            $data = json_decode($resp, true);
            if (!empty($data['sha'])) {
                $latestCommit = substr($data['sha'], 0, 7);
                $commitMessage = $data['commit']['message'] ?? 'Latest updates';
                $commitDate = $data['commit']['committer']['date'] ?? null;
            }
        }

        $lastInstalledCommit = Database::getSetting('installed_commit_sha', '');

        jsonResponse([
            'success'               => true,
            'current_version'       => $currentVersion,
            'repo'                  => $repo,
            'latest_commit'         => $latestCommit ?: 'main',
            'latest_commit_message' => $commitMessage,
            'latest_commit_date'    => $commitDate,
            'installed_commit'      => $lastInstalledCommit,
            'update_available'      => ($latestCommit !== null && $latestCommit !== $lastInstalledCommit)
        ]);
        break;

    case 'updater/install':
        $repo = defined('CLAUDEPULSE_GITHUB_REPO') ? CLAUDEPULSE_GITHUB_REPO : 'DeveloperSarim/ClaudePulse';
        $rootDir = realpath(__DIR__ . '/..');
        $backupDir = $rootDir . '/database/backups';

        if (!class_exists('ZipArchive')) {
            jsonResponse(['error' => 'PHP ZipArchive extension is required for auto-updates.'], 500);
        }

        if (!is_dir($backupDir)) {
            @mkdir($backupDir, 0755, true);
        }

        // 1. Create Safety Backup
        $backupName = 'backup_' . date('Ymd_His') . '.zip';
        $backupPath = $backupDir . '/' . $backupName;
        $backupZip = new ZipArchive();
        if ($backupZip->open($backupPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
            $dbFile = $rootDir . '/database/claudepulse.sqlite';
            if (file_exists($dbFile)) {
                $backupZip->addFile($dbFile, 'database/claudepulse.sqlite');
            }
            $cfgFile = $rootDir . '/config.php';
            if (file_exists($cfgFile)) {
                $backupZip->addFile($cfgFile, 'config.php');
            }
            $keyFile = $rootDir . '/database/secret.key';
            if (file_exists($keyFile)) {
                $backupZip->addFile($keyFile, 'database/secret.key');
            }
            $backupZip->close();
        }

        // 2. Download latest zipball from GitHub
        $zipUrl = "https://codeload.github.com/{$repo}/zip/refs/heads/main";
        $tempZip = $rootDir . '/database/temp_update_' . time() . '.zip';

        $ch = curl_init($zipUrl);
        $fp = fopen($tempZip, 'w');
        curl_setopt($ch, CURLOPT_FILE, $fp);
        curl_setopt($ch, CURLOPT_FOLLOWLOCATION, true);
        curl_setopt($ch, CURLOPT_USERAGENT, 'ClaudePulse-AutoUpdater/1.2.0');
        curl_setopt($ch, CURLOPT_TIMEOUT, 60);
        curl_exec($ch);
        $dlCode = curl_getinfo($ch, CURLINFO_HTTP_CODE);
        curl_close($ch);
        fclose($fp);

        if ($dlCode !== 200 || !file_exists($tempZip) || filesize($tempZip) < 1000) {
            @unlink($tempZip);
            jsonResponse(['error' => "Failed to download update from GitHub (HTTP {$dlCode}). Ensure repository is public."], 500);
        }

        // 3. Extract while protecting live database & keys
        $zip = new ZipArchive();
        if ($zip->open($tempZip) !== true) {
            @unlink($tempZip);
            jsonResponse(['error' => 'Failed to open downloaded update package.'], 500);
        }

        // GitHub zipballs have a root directory like "ClaudePulse-main/..."
        $rootPrefix = '';
        if ($zip->numFiles > 0) {
            $first = $zip->getNameIndex(0);
            if (str_contains($first, '/')) {
                $rootPrefix = explode('/', $first)[0] . '/';
            }
        }

        $filesExtracted = 0;
        for ($i = 0; $i < $zip->numFiles; $i++) {
            $rawName = $zip->getNameIndex($i);
            $cleanName = $rawName;
            if ($rootPrefix !== '' && str_starts_with($cleanName, $rootPrefix)) {
                $cleanName = substr($cleanName, strlen($rootPrefix));
            }
            $cleanName = ltrim(str_replace('\\', '/', $cleanName), '/');

            if ($cleanName === '' || str_ends_with($cleanName, '/')) {
                continue;
            }

            // Safety safeguard: Never overwrite user database or keys!
            if ($cleanName === 'database/claudepulse.sqlite' || $cleanName === 'database/secret.key') {
                continue;
            }

            $target = $rootDir . '/' . $cleanName;
            $parent = dirname($target);
            if (!is_dir($parent)) {
                @mkdir($parent, 0755, true);
            }

            $src = $zip->getStream($rawName);
            if ($src) {
                $dst = @fopen($target, 'w');
                if ($dst) {
                    while (!feof($src)) {
                        fwrite($dst, fread($src, 8192));
                    }
                    fclose($dst);
                    $filesExtracted++;
                }
                fclose($src);
            }
        }
        $zip->close();
        @unlink($tempZip);

        // 4. Run Auto-Migrations
        Database::getConnection();

        // 5. Save latest commit as installed
        Database::setSetting('installed_commit_at', date('Y-m-d H:i:s'));

        jsonResponse([
            'success'   => true,
            'message'   => "Successfully installed update ({$filesExtracted} files updated). Database & keys preserved.",
            'backup'    => "database/backups/{$backupName}"
        ]);
        break;

    default:
        jsonResponse(['error' => "Action '{$action}' not recognized."], 404);
}

