<?php
declare(strict_types=1);

/**
 * ClaudePulse Front-Controller & Clean URL Router
 * Apache / LiteSpeed / Nginx / PHP CLI Compatible
 */

require_once __DIR__ . '/config.php';

use ClaudePulse\Database;
use ClaudePulse\Auth;
use ClaudePulse\Pricing;

// Parse request path
$requestUri = $_SERVER['REQUEST_URI'] ?? '/';
$parsedUrl = parse_url($requestUri);
$path = $parsedUrl['path'] ?? '/';

// Support running in subdirectories (e.g. /claudepulse/dashboard)
$scriptName = $_SERVER['SCRIPT_NAME'] ?? '';
$scriptDir = str_ends_with($scriptName, '.php') ? dirname($scriptName) : '';
$basePath = rtrim(str_replace('\\', '/', $scriptDir), '/');
if ($basePath !== '' && $basePath !== '.' && $basePath !== '/') {
    if (str_starts_with($path, $basePath)) {
        $path = substr($path, strlen($basePath));
    }
}
$path = '/' . trim($path, '/');

// Serve static assets when using PHP built-in server
if (php_sapi_name() === 'cli-server') {
    $filePath = __DIR__ . $path;
    if ($path !== '/' && file_exists($filePath) && !is_dir($filePath)) {
        $ext = strtolower(pathinfo($filePath, PATHINFO_EXTENSION));
        $mimes = [
            'css'  => 'text/css',
            'js'   => 'application/javascript',
            'svg'  => 'image/svg+xml',
            'png'  => 'image/png',
            'jpg'  => 'image/jpeg',
            'ico'  => 'image/x-icon',
            'json' => 'application/json'
        ];
        if (isset($mimes[$ext])) {
            header('Content-Type: ' . $mimes[$ext]);
            readfile($filePath);
            exit;
        }
    }
}

// -------------------------------------------------------------
// 1. API Reverse Proxy Routes (/v1/*)
// -------------------------------------------------------------
if ($path === '/v1' || str_starts_with($path, '/v1/')) {
    require __DIR__ . '/api/proxy.php';
    exit;
}

// -------------------------------------------------------------
// 2. Internal AJAX API Routes (/api/internal/*)
// -------------------------------------------------------------
if ($path === '/api/internal' || str_starts_with($path, '/api/internal/') || str_starts_with($path, '/api/')) {
    require __DIR__ . '/api/internal.php';
    exit;
}

// -------------------------------------------------------------
// 3. Public Status & Health Probe (/status & /v1/status)
// -------------------------------------------------------------
if ($path === '/status' || $path === '/v1/status') {
    $accept = $_SERVER['HTTP_ACCEPT'] ?? '';
    $format = $_GET['format'] ?? '';

    if ($format === 'json' || str_contains($accept, 'application/json')) {
        // Return JSON Probe
        $db = Database::getConnection();
        $stmt = $db->query("
            SELECT 
                COUNT(*) as total_requests,
                SUM(CASE WHEN status_code = 200 THEN 1 ELSE 0 END) as successful,
                AVG(latency_ms) as avg_latency
            FROM request_logs 
            WHERE created_at >= datetime('now', '-24 hours')
        ");
        $stats = $stmt->fetch();
        $totalReqs = (int)($stats['total_requests'] ?? 0);
        $successReqs = (int)($stats['successful'] ?? 0);
        $uptimePct = $totalReqs > 0 ? round(($successReqs / $totalReqs) * 100, 2) : 100.0;
        $avgLatency = (int)round((float)($stats['avg_latency'] ?? 145));

        jsonResponse([
            'status'            => 'operational',
            'gateway'           => CLAUDEPULSE_NAME,
            'version'           => CLAUDEPULSE_VERSION,
            'timestamp'         => gmdate('Y-m-d\TH:i:s\Z'),
            'uptime_percentage' => $uptimePct,
            'avg_latency_ms'    => $avgLatency,
            'components'        => [
                'gateway_engine'     => 'operational',
                'database'           => 'operational',
                'upstream_anthropic' => 'operational'
            ],
            'active_models'     => array_column(Pricing::getKnownModels(), 'id')
        ]);
    }

    require __DIR__ . '/views/status.php';
    exit;
}

// -------------------------------------------------------------
// 3b. Public Key Quota & Balance Inspector (/verify, /check, /balance, /api/verify)
// -------------------------------------------------------------
if ($path === '/verify' || $path === '/check' || $path === '/balance' || $path === '/api/verify') {
    require __DIR__ . '/views/verify.php';
    exit;
}

// -------------------------------------------------------------
// 3c. Public Documentation & Tutorials (/docs, /connect, /tutorial)
// -------------------------------------------------------------
if ($path === '/docs' || $path === '/connect' || $path === '/tutorial') {
    $currentRoute = 'docs';
    $pageTitle = 'Connect & Tutorials';
    if (Auth::check()) {
        ob_start();
        require __DIR__ . '/views/docs.php';
        $content = ob_get_clean();
        require __DIR__ . '/views/layout.php';
    } else {
        require __DIR__ . '/views/docs.php';
    }
    exit;
}

// -------------------------------------------------------------
// 4. Authentication & Setup Routes (/setup, /login, /logout)
// -------------------------------------------------------------
if ($path === '/setup' || $path === '/login') {
    $hasAdmin = Database::hasAdmin();

    // If no admin exists yet, direct to setup wizard
    if (!$hasAdmin && $path === '/login') {
        header('Location: /setup');
        exit;
    }

    // If admin already exists and user tries to access /setup, redirect to login
    if ($hasAdmin && $path === '/setup') {
        header('Location: /login');
        exit;
    }

    $errorMessage = null;

    if ($_SERVER['REQUEST_METHOD'] === 'POST') {
        $csrfToken = $_POST['csrf_token'] ?? '';
        if (!Auth::validateCsrf($csrfToken)) {
            $errorMessage = 'Invalid or expired CSRF security token. Please refresh.';
        } else {
            $action = $_POST['action'] ?? ($path === '/setup' ? 'setup' : 'login');

            if ($action === 'setup') {
                $username = $_POST['username'] ?? '';
                $email = $_POST['email'] ?? '';
                $password = $_POST['password'] ?? '';
                $anthropicKey = $_POST['anthropic_key'] ?? null;

                $res = Auth::setupInitialAdmin($username, $email, $password, $anthropicKey);
                if ($res['success']) {
                    header('Location: /dashboard');
                    exit;
                } else {
                    $errorMessage = $res['error'] ?? 'Setup failed.';
                }
            } else {
                $username = $_POST['username'] ?? '';
                $password = $_POST['password'] ?? '';

                $res = Auth::login($username, $password);
                if ($res['success']) {
                    header('Location: /dashboard');
                    exit;
                } else {
                    $errorMessage = $res['error'] ?? 'Invalid credentials.';
                }
            }
        }
    }

    if (Auth::check()) {
        header('Location: /dashboard');
        exit;
    }

    require __DIR__ . '/views/login.php';
    exit;
}

if ($path === '/logout') {
    Auth::logout();
    header('Location: /login');
    exit;
}

// -------------------------------------------------------------
// 5. Protected Dashboard Routes
// -------------------------------------------------------------
Auth::requireAuth();

if ($path === '/' || $path === '/dashboard') {
    $currentRoute = 'dashboard';
    $pageTitle = 'Dashboard';
    ob_start();
    require __DIR__ . '/views/dashboard.php';
    $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
    exit;
}

if ($path === '/keys') {
    $currentRoute = 'keys';
    $pageTitle = 'Gateway API Keys';
    ob_start();
    require __DIR__ . '/views/keys.php';
    $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
    exit;
}

if ($path === '/logs') {
    $currentRoute = 'logs';
    $pageTitle = 'Request Telemetry';
    ob_start();
    require __DIR__ . '/views/logs.php';
    $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
    exit;
}

if ($path === '/settings') {
    $currentRoute = 'settings';
    $pageTitle = 'Vault & Settings';
    ob_start();
    require __DIR__ . '/views/settings.php';
    $content = ob_get_clean();
    require __DIR__ . '/views/layout.php';
    exit;
}

// -------------------------------------------------------------
// 6. 404 Fallback
// -------------------------------------------------------------
http_response_code(404);
$currentRoute = '';
$pageTitle = 'Page Not Found';
ob_start();
?>
<div class="text-center py-20">
    <div class="inline-flex p-4 rounded-2xl bg-slate-900 border border-slate-800 text-emerald-400 mb-4 font-mono text-2xl font-bold">404</div>
    <h1 class="text-2xl font-bold text-white mb-2">Endpoint Not Found</h1>
    <p class="text-xs text-slate-400 max-w-sm mx-auto mb-6">The requested path does not exist on this ClaudePulse gateway.</p>
    <a href="/dashboard" class="px-4 py-2 rounded-xl bg-emerald-500 hover:bg-emerald-600 text-slate-950 font-semibold text-xs transition">Return to Dashboard &rarr;</a>
</div>
<?php
$content = ob_get_clean();
require __DIR__ . '/views/layout.php';
