<?php
declare(strict_types=1);

/**
 * ClaudePulse Configuration & Bootstrap
 */

define('CLAUDEPULSE_VERSION', '1.2.0');
define('CLAUDEPULSE_NAME', 'ClaudePulse');
define('CLAUDEPULSE_GITHUB_REPO', 'DeveloperSarim/ClaudePulse');

// Require core classes
require_once __DIR__ . '/src/Crypto.php';
require_once __DIR__ . '/src/Pricing.php';
require_once __DIR__ . '/src/Database.php';
require_once __DIR__ . '/src/Auth.php';

// Safe error handling across PHP 8.2, 8.3, 8.4, 8.5+
error_reporting(E_ALL & ~E_DEPRECATED);
ini_set('display_errors', '0');

/**
 * Helper to build absolute URL for current deployment
 */
function baseUrl(string $path = ''): string
{
    $protocol = (!empty($_SERVER['HTTPS']) && $_SERVER['HTTPS'] !== 'off')
             || (!empty($_SERVER['SERVER_PORT']) && $_SERVER['SERVER_PORT'] == 443)
             || (!empty($_SERVER['HTTP_X_FORWARDED_PROTO']) && $_SERVER['HTTP_X_FORWARDED_PROTO'] === 'https')
             ? 'https://' : 'http://';

    $host = $_SERVER['HTTP_HOST'] ?? 'localhost';
    
    // Auto-detect base path if installed in a subdirectory
    $scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
    $scriptDir = str_ends_with($scriptName, '.php') ? dirname($scriptName) : '';
    $basePath = rtrim(str_replace('\\', '/', $scriptDir), '/');
    if ($basePath === '.' || $basePath === '') {
        $basePath = '';
    }

    $cleanPath = '/' . ltrim($path, '/');
    if ($path === '') {
        $cleanPath = '';
    }

    return $protocol . $host . $basePath . $cleanPath;
}

/**
 * Asset URL helper with cache busting
 */
function asset(string $path): string
{
    $filePath = __DIR__ . '/' . ltrim($path, '/');
    $version = file_exists($filePath) ? filemtime($filePath) : time();
    return baseUrl($path) . '?v=' . $version;
}

/**
 * Format USD currency
 */
function formatCurrency(float $amount, int $decimals = 4): string
{
    if ($amount < 0.0001 && $amount > 0) {
        return '$' . number_format($amount, 6);
    }
    if ($amount >= 1.0) {
        return '$' . number_format($amount, 2);
    }
    return '$' . number_format($amount, $decimals);
}

/**
 * Format token count with human-readable suffixes (1.2k, 4.5M)
 */
function formatTokens(int $tokens): string
{
    if ($tokens >= 1_000_000) {
        return number_format($tokens / 1_000_000, 2) . 'M';
    }
    if ($tokens >= 1_000) {
        return number_format($tokens / 1_000, 1) . 'k';
    }
    return number_format($tokens);
}

/**
 * JSON response helper
 */
function jsonResponse(array $data, int $statusCode = 200): void
{
    http_response_code($statusCode);
    header('Content-Type: application/json; charset=utf-8');
    echo json_encode($data, JSON_UNESCAPED_SLASHES | JSON_UNESCAPED_UNICODE);
    exit;
}
