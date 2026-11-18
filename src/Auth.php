<?php
declare(strict_types=1);

namespace ClaudePulse;

class Auth
{
    private const MAX_ATTEMPTS = 5;
    private const LOCKOUT_MINUTES = 15;

    /**
     * Start resilient PHP session with custom storage path and secure settings
     */
    public static function startSession(): void
    {
        if (session_status() === PHP_SESSION_NONE) {
            // Use local database/sessions directory to avoid shared hosting /tmp purge issues
            $sessionDir = __DIR__ . '/../database/sessions';
            if (!is_dir($sessionDir)) {
                @mkdir($sessionDir, 0700, true);
            }
            if (is_dir($sessionDir) && is_writable($sessionDir)) {
                @session_save_path($sessionDir);
            }

            if (!headers_sent()) {
                $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                         || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
                         || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

                session_set_cookie_params([
                    'lifetime' => 86400 * 7, // 7 days
                    'path'     => '/',
                    'domain'   => '',
                    'secure'   => $isSecure,
                    'httponly' => true,
                    'samesite' => 'Lax' // Lax ensures session persists across 302 redirects & top-level navigation
                ]);

                @session_start();
            } else {
                @session_start();
            }
        }
    }

    /**
     * Check if current visitor is authenticated admin (Dual Session + Encrypted Auth Token)
     */
    public static function check(): bool
    {
        self::startSession();

        // 1. Check PHP Session
        if (!empty($_SESSION['claudepulse_admin_id'])) {
            return true;
        }

        // 2. Resilient Fallback: Encrypted Authentication Cookie (cp_auth)
        if (!empty($_COOKIE['cp_auth'])) {
            try {
                $decrypted = Crypto::decrypt($_COOKIE['cp_auth']);
                $payload = json_decode($decrypted, true);

                if (!empty($payload['id']) && !empty($payload['hash']) && !empty($payload['time'])) {
                    // Check expiry (7 days)
                    if ((time() - (int)$payload['time']) < (86400 * 7)) {
                        $db = Database::getConnection();
                        $stmt = $db->prepare("SELECT id, username, password_hash FROM admin_users WHERE id = :id LIMIT 1");
                        $stmt->execute([':id' => (int)$payload['id']]);
                        $user = $stmt->fetch();

                        if ($user && hash_equals(hash('sha256', $user['password_hash']), $payload['hash'])) {
                            // Restore session
                            $_SESSION['claudepulse_admin_id'] = $user['id'];
                            $_SESSION['claudepulse_username'] = $user['username'];
                            return true;
                        }
                    }
                }
            } catch (\Exception $e) {
                // Ignore invalid or tampered cookies
            }
        }

        return false;
    }

    /**
     * Get authenticated admin user
     */
    public static function user(): ?array
    {
        if (!self::check()) {
            return null;
        }

        $db = Database::getConnection();
        $adminId = $_SESSION['claudepulse_admin_id'] ?? null;
        if (!$adminId) {
            return null;
        }

        $stmt = $db->prepare("SELECT id, username, email, created_at, last_login_at FROM admin_users WHERE id = :id LIMIT 1");
        $stmt->execute([':id' => $adminId]);
        $user = $stmt->fetch();

        return $user ?: null;
    }

    /**
     * Require authentication or redirect / return 401
     */
    public static function requireAuth(bool $isJson = false): void
    {
        if (!Database::hasAdmin()) {
            if ($isJson) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'No administrator account configured. Please complete initial setup at /setup.']);
                exit;
            }
            header('Location: /setup');
            exit;
        }

        if (!self::check()) {
            if ($isJson) {
                http_response_code(401);
                header('Content-Type: application/json');
                echo json_encode(['error' => 'Unauthorized. Please log in.']);
                exit;
            }

            header('Location: /login');
            exit;
        }
    }

    /**
     * Attempt login with brute-force protection
     */
    public static function login(string $username, string $password): array
    {
        self::startSession();
        $ip = self::getClientIp();
        $db = Database::getConnection();

        // 1. Check brute force lockout
        if (self::isIpLocked($ip)) {
            return [
                'success' => false,
                'error'   => 'Too many failed login attempts. Please wait 15 minutes before trying again.'
            ];
        }

        // 2. Fetch user
        $stmt = $db->prepare("SELECT id, username, password_hash FROM admin_users WHERE username = :u LIMIT 1");
        $stmt->execute([':u' => trim($username)]);
        $user = $stmt->fetch();

        if ($user && Crypto::verifyPassword($password, $user['password_hash'])) {
            // Success: Reset rate limiter
            self::clearIpAttempts($ip);

            // Regenerate session ID to prevent fixation
            if (session_status() === PHP_SESSION_ACTIVE && !headers_sent()) {
                session_regenerate_id(true);
            }
            $_SESSION['claudepulse_admin_id'] = $user['id'];
            $_SESSION['claudepulse_username'] = $user['username'];

            // Set persistent encrypted authentication cookie (7 days)
            self::setAuthCookie((int)$user['id'], $user['password_hash']);

            // Update last login
            $update = $db->prepare("UPDATE admin_users SET last_login_at = datetime('now') WHERE id = :id");
            $update->execute([':id' => $user['id']]);

            // Flush session to disk before header redirect
            session_write_close();

            return ['success' => true];
        }

        // Failed attempt
        self::recordFailedAttempt($ip);
        return [
            'success' => false,
            'error'   => 'Invalid username or password.'
        ];
    }

    /**
     * First-run initial admin setup
     */
    public static function setupInitialAdmin(string $username, string $email, string $password, ?string $anthropicKey = null): array
    {
        if (Database::hasAdmin()) {
            return ['success' => false, 'error' => 'Admin account already exists. Please log in.'];
        }

        $username = trim($username);
        $email = trim($email);

        if (strlen($username) < 3) {
            return ['success' => false, 'error' => 'Username must be at least 3 characters.'];
        }
        if (strlen($password) < 8) {
            return ['success' => false, 'error' => 'Password must be at least 8 characters.'];
        }

        $db = Database::getConnection();
        $passwordHash = Crypto::hashPassword($password);

        $stmt = $db->prepare("INSERT INTO admin_users (username, email, password_hash) VALUES (:u, :e, :p)");
        $stmt->execute([
            ':u' => $username,
            ':e' => $email ?: null,
            ':p' => $passwordHash
        ]);

        $adminId = (int)$db->lastInsertId();

        // If an initial Anthropic key is supplied, save it encrypted
        if (!empty($anthropicKey) && str_starts_with(trim($anthropicKey), 'sk-ant-')) {
            $keyTrimmed = trim($anthropicKey);
            $encryptedKey = Crypto::encrypt($keyTrimmed);
            $keyHint = Crypto::maskKey($keyTrimmed);

            $stmtKey = $db->prepare("
                INSERT INTO upstream_keys (label, api_key_encrypted, key_hint, is_active, priority, status_message)
                VALUES (:label, :enc, :hint, 1, 1, 'Initial key saved')
            ");
            $stmtKey->execute([
                ':label' => 'Primary Anthropic Account',
                ':enc'   => $encryptedKey,
                ':hint'  => $keyHint,
            ]);
        }

        // Also generate an initial Gateway Key so the user can immediately connect Claude Code
        $defaultKeyData = Crypto::generateGatewayKey();
        $stmtGw = $db->prepare("
            INSERT INTO gateway_keys (name, api_key_hash, api_key_prefix, cap_usd, spent_usd, status)
            VALUES (:name, :hash, :prefix, 0.0, 0.0, 'active')
        ");
        $stmtGw->execute([
            ':name'   => 'Default Claude Code Key',
            ':hash'   => $defaultKeyData['hash'],
            ':prefix' => $defaultKeyData['prefix']
        ]);

        // Auto login
        self::startSession();
        $_SESSION['claudepulse_admin_id'] = $adminId;
        $_SESSION['claudepulse_username'] = $username;
        $_SESSION['initial_new_key'] = $defaultKeyData['key'];

        // Set persistent encrypted auth cookie
        self::setAuthCookie($adminId, $passwordHash);

        session_write_close();

        return [
            'success' => true,
            'initial_gateway_key' => $defaultKeyData['key']
        ];
    }

    /**
     * Terminate admin session
     */
    public static function logout(): void
    {
        self::startSession();
        $_SESSION = [];

        if (ini_get("session.use_cookies") && !headers_sent()) {
            $params = session_get_cookie_params();
            setcookie(session_name(), '', time() - 42000,
                $params["path"], $params["domain"],
                $params["secure"], $params["httponly"]
            );
        }

        @session_destroy();

        // Clear cp_auth cookie
        if (!headers_sent()) {
            setcookie('cp_auth', '', [
                'expires'  => time() - 3600,
                'path'     => '/',
                'domain'   => '',
                'secure'   => false,
                'httponly' => true,
                'samesite' => 'Lax'
            ]);
        }
    }

    /**
     * Set encrypted authentication cookie
     */
    private static function setAuthCookie(int $adminId, string $passwordHash): void
    {
        if (headers_sent()) {
            return;
        }

        $isSecure = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
                 || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
                 || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https');

        $authPayload = json_encode([
            'id'   => $adminId,
            'hash' => hash('sha256', $passwordHash),
            'time' => time()
        ]);
        $authCipher = Crypto::encrypt($authPayload);

        setcookie('cp_auth', $authCipher, [
            'expires'  => time() + (86400 * 7),
            'path'     => '/',
            'domain'   => '',
            'secure'   => $isSecure,
            'httponly' => true,
            'samesite' => 'Lax'
        ]);
    }

    /**
     * Dual-Layer CSRF Token: Session + Cryptographic HMAC
     * Resistant to shared-hosting session drops and multi-server rotation
     */
    public static function getCsrfToken(): string
    {
        self::startSession();
        if (empty($_SESSION['csrf_token'])) {
            $_SESSION['csrf_token'] = Crypto::randomToken(32);
        }

        $time = time();
        $tokenBase = $_SESSION['csrf_token'];
        $signature = hash_hmac('sha256', "{$tokenBase}:{$time}", Crypto::getMasterKey());

        return "{$tokenBase}.{$time}.{$signature}";
    }

    /**
     * Validate CSRF Token
     */
    public static function validateCsrf(?string $token): bool
    {
        if (empty($token)) {
            return false;
        }

        self::startSession();

        // 1. Direct session match
        if (!empty($_SESSION['csrf_token']) && hash_equals($_SESSION['csrf_token'], $token)) {
            return true;
        }

        // 2. Cryptographic HMAC validation (valid for 4 hours)
        if (str_contains($token, '.')) {
            $parts = explode('.', $token);
            if (count($parts) === 3) {
                [$tokenBase, $timeStr, $signature] = $parts;
                $time = (int)$timeStr;

                // Validate timestamp (4-hour window, allow 60s clock drift)
                if ((time() - $time) <= 14400 && (time() - $time) >= -60) {
                    $expected = hash_hmac('sha256', "{$tokenBase}:{$time}", Crypto::getMasterKey());
                    if (hash_equals($expected, $signature)) {
                        return true;
                    }
                }
            }
        }

        return false;
    }

    /**
     * Rate Limiting & Brute Force Checks
     */
    private static function isIpLocked(string $ip): bool
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT attempts, locked_until FROM login_attempts WHERE ip_address = :ip LIMIT 1");
        $stmt->execute([':ip' => $ip]);
        $row = $stmt->fetch();

        if ($row && !empty($row['locked_until'])) {
            $lockedTime = strtotime($row['locked_until']);
            if ($lockedTime > time()) {
                return true;
            }
        }
        return false;
    }

    private static function recordFailedAttempt(string $ip): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("SELECT attempts FROM login_attempts WHERE ip_address = :ip LIMIT 1");
        $stmt->execute([':ip' => $ip]);
        $row = $stmt->fetch();

        if ($row) {
            $attempts = (int)$row['attempts'] + 1;
            $lockedUntil = null;
            if ($attempts >= self::MAX_ATTEMPTS) {
                $lockedUntil = date('Y-m-d H:i:s', time() + (self::LOCKOUT_MINUTES * 60));
            }

            $upd = $db->prepare("
                UPDATE login_attempts
                SET attempts = :att, last_attempt = datetime('now'), locked_until = :lock
                WHERE ip_address = :ip
            ");
            $upd->execute([':att' => $attempts, ':lock' => $lockedUntil, ':ip' => $ip]);
        } else {
            $ins = $db->prepare("
                INSERT INTO login_attempts (ip_address, attempts, last_attempt, locked_until)
                VALUES (:ip, 1, datetime('now'), NULL)
            ");
            $ins->execute([':ip' => $ip]);
        }
    }

    private static function clearIpAttempts(string $ip): void
    {
        $db = Database::getConnection();
        $stmt = $db->prepare("DELETE FROM login_attempts WHERE ip_address = :ip");
        $stmt->execute([':ip' => $ip]);
    }

    /**
     * Helper to get real client IP
     */
    public static function getClientIp(): string
    {
        $headers = [
            'HTTP_CF_CONNECTING_IP',
            'HTTP_X_REAL_IP',
            'HTTP_X_FORWARDED_FOR',
            'REMOTE_ADDR'
        ];

        foreach ($headers as $header) {
            if (!empty($_SERVER[$header])) {
                $ips = explode(',', $_SERVER[$header]);
                $ip = trim($ips[0]);
                if (filter_var($ip, FILTER_VALIDATE_IP)) {
                    return $ip;
                }
            }
        }

        return '127.0.0.1';
    }
}
