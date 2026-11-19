<?php
declare(strict_types=1);

namespace ClaudePulse;

class Database
{
    private static ?\PDO $pdo = null;

    /**
     * Get singleton PDO connection and ensure schema is initialized
     */
    public static function getConnection(): \PDO
    {
        if (self::$pdo !== null) {
            return self::$pdo;
        }

        $connectionType = getenv('DB_CONNECTION') ?: 'sqlite';

        if ($connectionType === 'mysql') {
            $host = getenv('DB_HOST') ?: '127.0.0.1';
            $port = getenv('DB_PORT') ?: '3306';
            $database = getenv('DB_DATABASE') ?: 'claudepulse';
            $username = getenv('DB_USERNAME') ?: 'root';
            $password = getenv('DB_PASSWORD') ?: '';

            $dsn = "mysql:host={$host};port={$port};dbname={$database};charset=utf8mb4";
            self::$pdo = new \PDO($dsn, $username, $password, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_EMULATE_PREPARES => false,
            ]);
        } else {
            // Default: Zero-configuration SQLite for Shared Hosting
            $dbDir = __DIR__ . '/../database';
            if (!is_dir($dbDir)) {
                @mkdir($dbDir, 0755, true);
            }
            $dbPath = getenv('CLAUDEPULSE_DB_PATH') ?: ($dbDir . '/claudepulse.sqlite');

            $isNew = !file_exists($dbPath);

            self::$pdo = new \PDO("sqlite:" . $dbPath, null, null, [
                \PDO::ATTR_ERRMODE => \PDO::ERRMODE_EXCEPTION,
                \PDO::ATTR_DEFAULT_FETCH_MODE => \PDO::FETCH_ASSOC,
                \PDO::ATTR_TIMEOUT => 30,
            ]);

            // SQLite performance optimizations for concurrent gateway traffic
            self::$pdo->exec('PRAGMA journal_mode = WAL;');
            self::$pdo->exec('PRAGMA synchronous = NORMAL;');
            self::$pdo->exec('PRAGMA foreign_keys = ON;');
            self::$pdo->exec('PRAGMA busy_timeout = 5000;');

            if ($isNew) {
                @chmod($dbPath, 0660);
            }
        }

        self::runAutoMigrations();

        return self::$pdo;
    }

    /**
     * Public alias to trigger database auto-migrations
     */
    public static function runMigrations(): void
    {
        self::getConnection();
    }

    /**
     * Execute auto-schema migration on startup
     */
    private static function runAutoMigrations(): void
    {
        $driver = self::$pdo->getAttribute(\PDO::ATTR_DRIVER_NAME);
        $isSqlite = ($driver === 'sqlite');

        $autoIncrement = $isSqlite ? 'INTEGER PRIMARY KEY AUTOINCREMENT' : 'INT AUTO_INCREMENT PRIMARY KEY';
        $currentTimestamp = $isSqlite ? "DATETIME DEFAULT (datetime('now'))" : 'DATETIME DEFAULT CURRENT_TIMESTAMP';

        // 1. admin_users
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS admin_users (
                id {$autoIncrement},
                username VARCHAR(64) NOT NULL UNIQUE,
                email VARCHAR(191) NULL,
                password_hash VARCHAR(255) NOT NULL,
                created_at {$currentTimestamp},
                last_login_at DATETIME NULL
            );
        ");

        // 2. upstream_keys (Official Anthropic Keys Vault)
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS upstream_keys (
                id {$autoIncrement},
                label VARCHAR(100) NOT NULL,
                api_key_encrypted TEXT NOT NULL,
                key_hint VARCHAR(30) NOT NULL,
                is_active INTEGER DEFAULT 1,
                priority INTEGER DEFAULT 1,
                last_checked_at DATETIME NULL,
                status_message VARCHAR(255) DEFAULT 'Not tested',
                created_at {$currentTimestamp}
            );
        ");

        // 3. gateway_keys (Client sk-cp-... Keys)
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS gateway_keys (
                id {$autoIncrement},
                name VARCHAR(100) NOT NULL,
                api_key_hash VARCHAR(64) NOT NULL UNIQUE,
                api_key_prefix VARCHAR(30) NOT NULL,
                api_key_encrypted TEXT NULL,
                cap_usd DOUBLE DEFAULT 0.0,
                spent_usd DOUBLE DEFAULT 0.0,
                cap_tokens INTEGER DEFAULT 0,
                used_tokens INTEGER DEFAULT 0,
                rate_limit_rpm INTEGER DEFAULT 0,
                allowed_models TEXT NULL,
                status VARCHAR(20) DEFAULT 'active',
                expires_at DATETIME NULL,
                total_requests INTEGER DEFAULT 0,
                last_used_at DATETIME NULL,
                created_at {$currentTimestamp}
            );
        ");

        // Safe auto-migrations for existing installations
        try {
            self::$pdo->exec("ALTER TABLE gateway_keys ADD COLUMN cap_tokens INTEGER DEFAULT 0;");
        } catch (\Exception $e) {}
        try {
            self::$pdo->exec("ALTER TABLE gateway_keys ADD COLUMN used_tokens INTEGER DEFAULT 0;");
        } catch (\Exception $e) {}
        try {
            self::$pdo->exec("ALTER TABLE gateway_keys ADD COLUMN api_key_encrypted TEXT NULL;");
        } catch (\Exception $e) {}

        // 4. request_logs (Telemetry & Audit)
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS request_logs (
                id {$autoIncrement},
                gateway_key_id INTEGER NULL,
                upstream_key_id INTEGER NULL,
                endpoint VARCHAR(100) NOT NULL,
                model VARCHAR(100) NULL,
                status_code INTEGER DEFAULT 200,
                is_streaming INTEGER DEFAULT 0,
                input_tokens INTEGER DEFAULT 0,
                output_tokens INTEGER DEFAULT 0,
                cache_creation_input_tokens INTEGER DEFAULT 0,
                cache_read_input_tokens INTEGER DEFAULT 0,
                cost_usd DOUBLE DEFAULT 0.0,
                saved_usd DOUBLE DEFAULT 0.0,
                latency_ms INTEGER DEFAULT 0,
                ttft_ms INTEGER DEFAULT 0,
                client_ip VARCHAR(45) NULL,
                user_agent VARCHAR(255) NULL,
                client_tag VARCHAR(50) DEFAULT 'API Client',
                error_message TEXT NULL,
                created_at {$currentTimestamp}
            );
        ");

        // Indexes for lightning fast analytics
        try {
            self::$pdo->exec("CREATE INDEX IF NOT EXISTS idx_logs_created_at ON request_logs(created_at);");
            self::$pdo->exec("CREATE INDEX IF NOT EXISTS idx_logs_gateway_key ON request_logs(gateway_key_id);");
            self::$pdo->exec("CREATE INDEX IF NOT EXISTS idx_logs_model ON request_logs(model);");
            self::$pdo->exec("CREATE INDEX IF NOT EXISTS idx_logs_status_code ON request_logs(status_code);");
            self::$pdo->exec("CREATE INDEX IF NOT EXISTS idx_gateway_hash ON gateway_keys(api_key_hash);");
        } catch (\Exception $e) {
            // Index might already exist on MySQL or SQLite
        }

        // 5. settings
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS settings (
                key_name VARCHAR(64) PRIMARY KEY,
                value TEXT NULL,
                updated_at {$currentTimestamp}
            );
        ");

        // 6. login_attempts (Brute-force protection)
        self::$pdo->exec("
            CREATE TABLE IF NOT EXISTS login_attempts (
                ip_address VARCHAR(45) PRIMARY KEY,
                attempts INTEGER DEFAULT 1,
                last_attempt {$currentTimestamp},
                locked_until DATETIME NULL
            );
        ");

        // Seed default settings if empty
        self::seedDefaultSettings();
    }

    /**
     * Populate default settings
     */
    private static function seedDefaultSettings(): void
    {
        $defaults = [
            'auto_prompt_caching' => '1',
            'public_status_page'  => '1',
            'auto_fallback'       => '1',
            'log_payloads'        => '0',
            'app_name'            => 'ClaudePulse',
        ];

        $stmt = self::$pdo->prepare("INSERT OR IGNORE INTO settings (key_name, value) VALUES (:key, :val)");
        foreach ($defaults as $k => $v) {
            $stmt->execute([':key' => $k, ':val' => $v]);
        }
    }

    /**
     * Check if an admin account has been created
     */
    public static function hasAdmin(): bool
    {
        $db = self::getConnection();
        $stmt = $db->query("SELECT COUNT(*) as count FROM admin_users");
        $row = $stmt->fetch();
        return !empty($row['count']) && (int)$row['count'] > 0;
    }

    /**
     * Get a setting by key
     */
    public static function getSetting(string $key, ?string $default = null): ?string
    {
        $db = self::getConnection();
        $stmt = $db->prepare("SELECT value FROM settings WHERE key_name = :key LIMIT 1");
        $stmt->execute([':key' => $key]);
        $row = $stmt->fetch();
        return $row ? (string)$row['value'] : $default;
    }

    /**
     * Set or update a setting
     */
    public static function setSetting(string $key, string $value): bool
    {
        $db = self::getConnection();
        $driver = $db->getAttribute(\PDO::ATTR_DRIVER_NAME);

        if ($driver === 'sqlite') {
            $stmt = $db->prepare("
                INSERT INTO settings (key_name, value, updated_at)
                VALUES (:key, :val, datetime('now'))
                ON CONFLICT(key_name) DO UPDATE SET value = :val2, updated_at = datetime('now')
            ");
            return $stmt->execute([':key' => $key, ':val' => $value, ':val2' => $value]);
        } else {
            $stmt = $db->prepare("
                INSERT INTO settings (key_name, value, updated_at)
                VALUES (:key, :val, NOW())
                ON DUPLICATE KEY UPDATE value = :val2, updated_at = NOW()
            ");
            return $stmt->execute([':key' => $key, ':val' => $value, ':val2' => $value]);
        }
    }

    /**
     * Vacuum / Optimize SQLite database
     */
    public static function vacuum(): bool
    {
        $db = self::getConnection();
        if ($db->getAttribute(\PDO::ATTR_DRIVER_NAME) === 'sqlite') {
            return (bool)$db->exec('VACUUM;');
        }
        return true;
    }

    /**
     * Get Database file size in bytes (for SQLite)
     */
    public static function getDatabaseSize(): int
    {
        $dbPath = __DIR__ . '/../database/claudepulse.sqlite';
        if (file_exists($dbPath)) {
            return (int)@filesize($dbPath);
        }
        return 0;
    }
}

