<?php
/**
 * ClaudePulse Modern Application Shell
 */
use ClaudePulse\Auth;

$currentUser = Auth::user();
$currentRoute = $currentRoute ?? 'dashboard';
$csrfToken = Auth::getCsrfToken();
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title><?= htmlspecialchars($pageTitle ?? 'ClaudePulse') ?> &mdash; Anthropic Claude Gateway & Cost Optimizer</title>
    <meta name="csrf-token" content="<?= $csrfToken ?>">
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23059669' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='22 12 18 12 15 21 9 3 6 12 2 12'%3E%3C/polyline%3E%3C/svg%3E">
    <!-- Tailwind CSS CDN -->
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: {
                            50: '#f0fdf4',
                            100: '#dcfce7',
                            500: '#10b981',
                            600: '#059669',
                            700: '#047857',
                            900: '#064e3b',
                        },
                        surface: {
                            page: 'var(--bg-page)',
                            card: 'var(--bg-surface)',
                            subtle: 'var(--bg-surface-subtle)',
                        }
                    }
                }
            }
        }
    </script>
    <!-- Chart.js -->
    <script src="https://cdn.jsdelivr.net/npm/chart.js@4.4.1/dist/chart.umd.min.js"></script>
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="min-h-screen flex flex-col bg-slate-50 text-slate-900 dark:bg-[#090d16] dark:text-slate-100 transition-colors duration-150">

    <!-- Top Navigation Bar -->
    <header class="sticky top-0 z-40 w-full border-b border-slate-200/90 bg-white/95 backdrop-blur-md dark:border-slate-800/80 dark:bg-[#0f172a]/95">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8">
            <div class="flex items-center justify-between h-16">
                
                <!-- Logo & Main Nav -->
                <div class="flex items-center space-x-8">
                    <a href="/dashboard" class="flex items-center space-x-2.5 group">
                        <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center shadow-sm group-hover:bg-emerald-600 transition dark:bg-emerald-500 dark:text-slate-950">
                            <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                                <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                            </svg>
                        </div>
                        <div class="flex items-center space-x-1.5">
                            <span class="font-bold text-base tracking-tight text-slate-900 dark:text-white">Claude<span class="text-emerald-600 dark:text-emerald-400">Pulse</span></span>
                            <span class="px-1.5 py-0.5 rounded text-[10px] font-semibold bg-slate-100 text-slate-600 border border-slate-200 dark:bg-slate-800 dark:text-slate-300 dark:border-slate-700 font-mono">v<?= CLAUDEPULSE_VERSION ?></span>
                        </div>
                    </a>

                    <!-- Navigation Links -->
                    <nav class="hidden md:flex items-center space-x-1">
                        <a href="/dashboard" class="px-3 py-1.5 rounded-md text-xs font-semibold transition <?= $currentRoute === 'dashboard' ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-800/50' ?>">
                            Overview
                        </a>
                        <a href="/keys" class="px-3 py-1.5 rounded-md text-xs font-semibold transition <?= $currentRoute === 'keys' ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-800/50' ?>">
                            Gateway Keys
                        </a>
                        <a href="/logs" class="px-3 py-1.5 rounded-md text-xs font-semibold transition <?= $currentRoute === 'logs' ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-800/50' ?>">
                            Requests
                        </a>
                        <a href="/settings" class="px-3 py-1.5 rounded-md text-xs font-semibold transition <?= $currentRoute === 'settings' ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-800/50' ?>">
                            Upstream & Vault
                        </a>
                        <a href="/docs" class="px-3 py-1.5 rounded-md text-xs font-semibold transition <?= $currentRoute === 'docs' ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 hover:text-slate-900 hover:bg-slate-50 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-800/50' ?>">
                            Connect & Docs
                        </a>
                        <a href="/verify" target="_blank" class="px-3 py-1.5 rounded-md text-xs font-semibold transition text-slate-600 hover:text-slate-900 hover:bg-slate-50 dark:text-slate-400 dark:hover:text-slate-100 dark:hover:bg-slate-800/50 flex items-center space-x-1">
                            <span>Balance Check</span>
                            <svg class="w-3 h-3 text-slate-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M10 6H6a2 2 0 00-2 2v10a2 2 0 002 2h10a2 2 0 002-2v-4M14 4h6m0 0v6m0-6L10 14" />
                            </svg>
                        </a>
                    </nav>
                </div>

                <!-- Right Utilities -->
                <div class="flex items-center space-x-3">
                    <!-- Status Beacon -->
                    <a href="/status" target="_blank" class="inline-flex items-center space-x-1.5 px-2.5 py-1 rounded-full text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
                        <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 pulse-live"></span>
                        <span class="hidden sm:inline">Gateway Status</span>
                    </a>

                    <!-- Theme Toggle -->
                    <button id="theme-toggle-btn" type="button" class="p-2 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800 transition" title="Toggle Theme" aria-label="Toggle Theme">
                        <svg class="w-4 h-4 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                        </svg>
                        <svg class="w-4 h-4 block dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                        </svg>
                    </button>

                    <!-- User Menu & Sign Out -->
                    <?php if ($currentUser): ?>
                    <div class="flex items-center space-x-2 pl-2 border-l border-slate-200 dark:border-slate-800">
                        <div class="w-7 h-7 rounded-full bg-slate-200 dark:bg-slate-700 text-slate-700 dark:text-slate-300 font-bold text-xs flex items-center justify-center uppercase">
                            <?= substr($currentUser['username'], 0, 1) ?>
                        </div>
                        <span class="text-xs font-semibold text-slate-700 dark:text-slate-300 hidden sm:inline"><?= htmlspecialchars($currentUser['username']) ?></span>
                        <a href="/logout" class="p-1.5 rounded-md text-slate-400 hover:text-rose-600 hover:bg-rose-50 dark:hover:bg-rose-500/10 transition" title="Sign Out">
                            <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M17 16l4-4m0 0l-4-4m4 4H7m6 4v1a3 3 0 01-3 3H6a3 3 0 01-3-3V7a3 3 0 013-3h4a3 3 0 013 3v1" />
                            </svg>
                        </a>
                    </div>
                    <?php endif; ?>
                </div>

            </div>
        </div>

        <!-- Mobile Navigation Menu -->
        <div class="md:hidden border-t border-slate-200 dark:border-slate-800 px-4 py-2 flex space-x-2 overflow-x-auto">
            <a href="/dashboard" class="px-2.5 py-1 rounded text-xs font-semibold <?= $currentRoute === 'dashboard' ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 dark:text-slate-400' ?>">Overview</a>
            <a href="/keys" class="px-2.5 py-1 rounded text-xs font-semibold <?= $currentRoute === 'keys' ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 dark:text-slate-400' ?>">Keys</a>
            <a href="/logs" class="px-2.5 py-1 rounded text-xs font-semibold <?= $currentRoute === 'logs' ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 dark:text-slate-400' ?>">Requests</a>
            <a href="/settings" class="px-2.5 py-1 rounded text-xs font-semibold <?= $currentRoute === 'settings' ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 dark:text-slate-400' ?>">Settings</a>
            <a href="/docs" class="px-2.5 py-1 rounded text-xs font-semibold <?= $currentRoute === 'docs' ? 'bg-slate-100 text-slate-900 dark:bg-slate-800 dark:text-white' : 'text-slate-600 dark:text-slate-400' ?>">Docs</a>
            <a href="/verify" target="_blank" class="px-2.5 py-1 rounded text-xs font-semibold text-slate-600 dark:text-slate-400">Balance</a>
        </div>
    </header>

    <!-- Main Content Canvas -->
    <main class="flex-1 max-w-7xl w-full mx-auto px-4 sm:px-6 lg:px-8 py-8">
        <?= $content ?? '' ?>
    </main>

    <!-- Refined Minimal Footer -->
    <footer class="border-t border-slate-200/80 dark:border-slate-800/60 py-6 text-xs text-slate-500 bg-white dark:bg-[#0f172a]">
        <div class="max-w-7xl mx-auto px-4 sm:px-6 lg:px-8 flex flex-col sm:flex-row items-center justify-between gap-3">
            <div>
                <span class="font-bold text-slate-700 dark:text-slate-300">ClaudePulse</span> &bull; Self-Hosted Anthropic Claude API Gateway & Cost Optimizer
            </div>
            <div class="flex items-center space-x-4">
                <a href="/docs" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium">Quickstart Guide</a>
                <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                <a href="/status" class="hover:text-emerald-600 dark:hover:text-emerald-400 transition font-medium">System Status</a>
                <span class="text-slate-300 dark:text-slate-700">&bull;</span>
                <span class="font-mono text-[11px] text-slate-500">Promotional API Ready</span>
            </div>
        </div>
    </footer>

    <!-- Toast Notification Container -->
    <div id="toast-container" class="fixed bottom-5 right-5 z-50 flex flex-col space-y-2 pointer-events-none sm:max-w-sm w-full px-4"></div>

    <script src="<?= asset('assets/js/app.js') ?>"></script>
</body>
</html>
