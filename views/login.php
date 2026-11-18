<?php
use ClaudePulse\Database;
use ClaudePulse\Auth;

$hasAdmin = Database::hasAdmin();
$csrfToken = Auth::getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?= $hasAdmin ? 'Sign In' : 'Initial Admin Setup' ?> &mdash; ClaudePulse</title>
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23059669' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='22 12 18 12 15 21 9 3 6 12 2 12'%3E%3C/polyline%3E%3C/svg%3E">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                        }
                    }
                }
            }
        }
    </script>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="min-h-screen flex items-center justify-center p-4 bg-slate-50 text-slate-900 dark:bg-[#090d16] dark:text-slate-100 transition-colors duration-150">

    <div class="max-w-lg w-full">
        
        <!-- Header Branding -->
        <div class="text-center mb-8">
            <div class="inline-flex items-center justify-center w-12 h-12 rounded-xl bg-slate-900 text-white mb-3 shadow-md dark:bg-emerald-500 dark:text-slate-950">
                <svg class="w-6 h-6" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
            </div>
            <h1 class="text-2xl font-bold tracking-tight text-slate-900 dark:text-white">Claude<span class="text-emerald-600 dark:text-emerald-400">Pulse</span></h1>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Anthropic Claude API Gateway, Cost Optimizer & Usage Telemetry</p>
        </div>

        <!-- Card Container -->
        <div class="bg-white border border-slate-200/90 rounded-2xl p-8 shadow-sm dark:bg-[#0f172a] dark:border-slate-800">
            
            <?php if (!empty($errorMessage)): ?>
            <div class="mb-5 p-3.5 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 text-xs font-medium flex items-center space-x-2 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300">
                <svg class="w-4 h-4 flex-shrink-0 text-rose-500" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span><?= htmlspecialchars($errorMessage) ?></span>
            </div>
            <?php endif; ?>

            <?php if (!$hasAdmin): ?>
            <!-- ============================================== -->
            <!-- INITIAL ADMINISTRATOR ONBOARDING WIZARD        -->
            <!-- ============================================== -->
            <div class="mb-6">
                <div class="inline-flex items-center space-x-1.5 px-2.5 py-0.5 rounded-full text-[11px] font-semibold bg-emerald-50 text-emerald-700 border border-emerald-200 mb-2.5 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 pulse-live"></span>
                    <span>First Run Setup</span>
                </div>
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Create Administrator Account</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Welcome to ClaudePulse. Initialize your administrator credentials and optionally connect your official Anthropic API key to start routing Claude Code.
                </p>
            </div>

            <form method="POST" action="/setup" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="setup">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Admin Username</label>
                    <input type="text" name="username" required placeholder="admin" autocomplete="username" autofocus
                           class="form-input">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Admin Email (Optional)</label>
                    <input type="email" name="email" placeholder="admin@yourdomain.com" autocomplete="email"
                           class="form-input">
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Master Password</label>
                    <input type="password" name="password" required minlength="8" placeholder="••••••••••••" autocomplete="new-password"
                           class="form-input">
                    <p class="text-[11px] text-slate-400 mt-1">Minimum 8 characters. Hashed using modern bcrypt.</p>
                </div>

                <div class="pt-3 border-t border-slate-200 dark:border-slate-800">
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-slate-800 dark:text-slate-200">Official Anthropic API Key (Optional)</label>
                        <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-medium">AES-256 Encrypted</span>
                    </div>
                    <input type="password" name="anthropic_key" placeholder="sk-ant-api03-..." autocomplete="off"
                           class="form-input font-mono text-xs">
                    <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                        If provided, it will be encrypted at rest in your database vault and a default <code class="text-emerald-600 dark:text-emerald-400 font-mono">sk-cp-...</code> gateway key will be generated automatically.
                    </p>
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition shadow-sm flex items-center justify-center space-x-2 dark:bg-emerald-600 dark:hover:bg-emerald-700">
                        <span>Initialize ClaudePulse &rarr;</span>
                    </button>
                </div>
            </form>

            <?php else: ?>
            <!-- ============================================== -->
            <!-- STANDARD LOGIN FORM                            -->
            <!-- ============================================== -->
            <div class="mb-6">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Sign In</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Enter your administrator credentials to access the console.</p>
            </div>

            <form method="POST" action="/login" class="space-y-4">
                <input type="hidden" name="csrf_token" value="<?= $csrfToken ?>">
                <input type="hidden" name="action" value="login">

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Username</label>
                    <input type="text" name="username" required placeholder="admin" autocomplete="username" autofocus
                           class="form-input">
                </div>

                <div>
                    <div class="flex items-center justify-between mb-1.5">
                        <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300">Password</label>
                    </div>
                    <input type="password" name="password" required placeholder="••••••••••••" autocomplete="current-password"
                           class="form-input">
                </div>

                <div class="pt-2">
                    <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition shadow-sm flex items-center justify-center space-x-2 dark:bg-emerald-600 dark:hover:bg-emerald-700">
                        <span>Sign In to Dashboard</span>
                    </button>
                </div>
            </form>
            <?php endif; ?>

        </div>

        <!-- Footer link -->
        <div class="mt-6 text-center">
            <a href="/status" target="_blank" class="text-xs text-slate-500 hover:text-slate-900 dark:hover:text-slate-300 transition inline-flex items-center space-x-1.5">
                <span class="w-1.5 h-1.5 rounded-full bg-emerald-500"></span>
                <span>Check Public Status Page &rarr;</span>
            </a>
        </div>

    </div>

    <script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
