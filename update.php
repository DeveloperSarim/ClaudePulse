<?php
declare(strict_types=1);

/**
 * ClaudePulse 1-Click GitHub & Zip Web Auto-Updater
 * 
 * Automatically checks the official GitHub repository for the latest version,
 * downloads and safely extracts updates, preserves your database & keys,
 * and runs database auto-migrations with zero manual zip downloads required.
 * 
 * Repository: https://github.com/DeveloperSarim/ClaudePulse
 */

@ini_set('display_errors', '1');
error_reporting(E_ALL);
@set_time_limit(300);

$rootDir = __DIR__;
$versionFile = $rootDir . '/.version';
$localZipFile = $rootDir . '/update.zip';
$backupDir = $rootDir . '/database/backups';
$githubRepo = 'DeveloperSarim/ClaudePulse';
$githubBranch = 'main';

// Read local version / commit
$localInfo = [
    'version'    => '1.2.0',
    'commit'     => 'Initial',
    'updated_at' => null
];
if (file_exists($versionFile)) {
    $parsed = @json_decode((string)file_get_contents($versionFile), true);
    if (is_array($parsed)) {
        $localInfo = array_merge($localInfo, $parsed);
    }
}

// Check latest GitHub commit via GitHub API
$latestCommit = null;
$commitMessage = null;
$commitDate = null;
$githubOnline = false;

$ctx = stream_context_create([
    'http' => [
        'method'  => 'GET',
        'header'  => "User-Agent: ClaudePulse-AutoUpdater/1.2.0\r\nAccept: application/vnd.github.v3+json\r\n",
        'timeout' => 8,
        'ignore_errors' => true
    ],
    'ssl' => [
        'verify_peer' => true,
        'verify_peer_name' => true
    ]
]);

$ghApiUrl = "https://api.github.com/repos/{$githubRepo}/commits/{$githubBranch}";
$ghResponse = @file_get_contents($ghApiUrl, false, $ctx);

if ($ghResponse !== false) {
    $ghData = @json_decode($ghResponse, true);
    if (is_array($ghData) && !empty($ghData['sha'])) {
        $latestCommit = substr((string)$ghData['sha'], 0, 7);
        $commitMessage = $ghData['commit']['message'] ?? '';
        $commitDate = $ghData['commit']['committer']['date'] ?? '';
        $githubOnline = true;
    }
}

$hasUpdate = ($githubOnline && $latestCommit && $latestCommit !== $localInfo['commit']);

// Handle self-delete action for security
if (isset($_GET['action']) && $_GET['action'] === 'cleanup') {
    $deleted = [];
    $errors = [];

    if (file_exists($localZipFile)) {
        if (@unlink($localZipFile)) {
            $deleted[] = 'update.zip';
        }
    }

    $thisFile = __FILE__;
    if (file_exists($thisFile)) {
        if (@unlink($thisFile)) {
            $deleted[] = basename($thisFile);
        } else {
            $errors[] = 'Failed to delete ' . basename($thisFile) . '. Please remove via File Manager.';
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success'  => empty($errors),
        'deleted'  => $deleted,
        'errors'   => $errors,
        'redirect' => '/dashboard'
    ]);
    exit;
}

$status = null;
$log = [];
$phpVersionOk = version_compare(PHP_VERSION, '8.1.0', '>=');
$zipExtOk = class_exists('ZipArchive');
$isWritable = is_writable($rootDir);
$localZipFound = file_exists($localZipFile);

// Process Update Request
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && in_array($_POST['action'], ['github_update', 'install_local'], true)) {
    $action = $_POST['action'];

    if (!$phpVersionOk || !$zipExtOk || !$isWritable) {
        $status = 'error';
        $log[] = 'System pre-flight checks failed. Ensure PHP 8.1+, ZipArchive, and write permissions are active.';
    } else {
        try {
            $log[] = 'Starting ClaudePulse Auto-Update process...';

            // 1. Safety Backup
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0755, true);
            }

            $backupZipName = 'backup_' . date('Ymd_His') . '.zip';
            $backupZipPath = $backupDir . '/' . $backupZipName;
            $backupZip = new ZipArchive();
            if ($backupZip->open($backupZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                if (file_exists($rootDir . '/database/claudepulse.sqlite')) {
                    $backupZip->addFile($rootDir . '/database/claudepulse.sqlite', 'database/claudepulse.sqlite');
                }
                if (file_exists($rootDir . '/database/secret.key')) {
                    $backupZip->addFile($rootDir . '/database/secret.key', 'database/secret.key');
                }
                if (file_exists($rootDir . '/config.php')) {
                    $backupZip->addFile($rootDir . '/config.php', 'config.php');
                }
                $backupZip->close();
                $log[] = "✓ Pre-update safety backup saved: database/backups/{$backupZipName}";
            }

            // 2. Obtain Zip Archive
            $targetZip = null;
            $isGithubArchive = false;

            if ($action === 'github_update') {
                $log[] = "Connecting to GitHub repository ({$githubRepo}@{$githubBranch})...";
                $ghZipUrl = "https://github.com/{$githubRepo}/archive/refs/heads/{$githubBranch}.zip";
                
                $downloadCtx = stream_context_create([
                    'http' => [
                        'method'  => 'GET',
                        'header'  => "User-Agent: ClaudePulse-AutoUpdater/1.2.0\r\n",
                        'timeout' => 60
                    ]
                ]);

                $targetZip = $rootDir . '/temp_github_update.zip';
                $zipData = @file_get_contents($ghZipUrl, false, $downloadCtx);

                if ($zipData === false || strlen($zipData) < 1000) {
                    throw new Exception("Failed to download update archive from GitHub. Please check server internet connectivity.");
                }

                file_put_contents($targetZip, $zipData);
                $log[] = "✓ Downloaded latest release package from GitHub (" . round(strlen($zipData) / 1024, 1) . " KB)";
                $isGithubArchive = true;
            } else {
                if (!$localZipFound) {
                    throw new Exception("Local update.zip archive not found in root directory.");
                }
                $targetZip = $localZipFile;
                $log[] = "Using local archive update.zip (" . round(filesize($targetZip) / 1024, 1) . " KB)...";
            }

            // 3. Extract Files safely
            $zip = new ZipArchive();
            $res = $zip->open($targetZip);
            if ($res !== true) {
                throw new Exception("Failed to open update archive (ZipArchive code: {$res})");
            }

            $extractedFilesCount = 0;
            $skippedFilesCount = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);

                // Strip top-level directory if downloaded from GitHub (e.g. 'ClaudePulse-main/')
                $cleanEntry = str_replace('\\', '/', $entryName);
                if ($isGithubArchive) {
                    $cleanEntry = preg_replace('/^ClaudePulse-[^\/]+\//', '', $cleanEntry);
                }
                $cleanEntry = ltrim($cleanEntry, '/');

                if (empty($cleanEntry)) {
                    continue;
                }

                // Security Safeguards: NEVER overwrite live SQLite database or existing encryption key!
                if ($cleanEntry === 'database/claudepulse.sqlite' && file_exists($rootDir . '/database/claudepulse.sqlite')) {
                    $skippedFilesCount++;
                    continue;
                }
                if ($cleanEntry === 'database/secret.key' && file_exists($rootDir . '/database/secret.key')) {
                    $skippedFilesCount++;
                    continue;
                }
                if ($cleanEntry === 'config.php' && file_exists($rootDir . '/config.php')) {
                    $skippedFilesCount++;
                    continue;
                }
                // Skip git / test / temp files
                if (str_starts_with($cleanEntry, '.git') || str_contains($cleanEntry, 'claudepulse_test') || str_ends_with($cleanEntry, '.DS_Store')) {
                    continue;
                }

                // Ensure target directory exists
                $destPath = $rootDir . '/' . $cleanEntry;
                $destDir = dirname($destPath);
                if (!is_dir($destDir)) {
                    @mkdir($destDir, 0755, true);
                }

                // If directory entry
                if (str_ends_with($entryName, '/')) {
                    if (!is_dir($destPath)) {
                        @mkdir($destPath, 0755, true);
                    }
                    continue;
                }

                // Extract file content
                $content = $zip->getFromIndex($i);
                if ($content !== false) {
                    file_put_contents($destPath, $content);
                    $extractedFilesCount++;
                }
            }

            $zip->close();
            $log[] = "✓ Extracted {$extractedFilesCount} system files successfully ({$skippedFilesCount} protected user config/db files preserved)";

            // 4. Update .version metadata
            $newCommit = $latestCommit ?: 'latest';
            $versionPayload = [
                'version'    => '1.2.0',
                'commit'     => $newCommit,
                'message'    => $commitMessage,
                'updated_at' => date('Y-m-d H:i:s T')
            ];
            file_put_contents($versionFile, json_encode($versionPayload, JSON_PRETTY_PRINT | JSON_UNESCAPED_SLASHES));
            $localInfo = $versionPayload;
            $hasUpdate = false;
            $log[] = "✓ Version signature updated to commit {$newCommit}";

            // 5. Cleanup temporary zip
            if ($isGithubArchive && file_exists($targetZip)) {
                @unlink($targetZip);
            }

            // 6. Run Database Migrations
            $dbPath = $rootDir . '/src/Database.php';
            if (file_exists($dbPath)) {
                require_once $dbPath;
                if (class_exists('ClaudePulse\\Database')) {
                    \ClaudePulse\Database::getConnection();
                    $log[] = '✓ Executed database auto-migrations & schema checks';
                }
            }

            $status = 'success';
            $log[] = '★ Update completed successfully! ClaudePulse is now running the latest version.';

        } catch (\Throwable $e) {
            $status = 'error';
            $log[] = 'Error during update: ' . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="dark">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClaudePulse 1-Click Auto-Updater</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23059669' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='22 12 18 12 15 21 9 3 6 12 2 12'%3E%3C/polyline%3E%3C/svg%3E">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        emerald: { 500: '#10b981', 600: '#059669', 700: '#047857' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-100 dark:bg-slate-950 text-slate-800 dark:text-slate-100 min-h-screen flex items-center justify-center p-4 antialiased font-sans">

    <div class="max-w-xl w-full bg-white dark:bg-slate-900 border border-slate-200 dark:border-slate-800 rounded-2xl shadow-xl p-6 sm:p-8 space-y-6">

        <!-- Header -->
        <div class="flex items-center space-x-3.5 border-b border-slate-100 dark:border-slate-800 pb-5">
            <div class="w-10 h-10 rounded-xl bg-gradient-to-tr from-emerald-600 to-teal-500 flex items-center justify-center text-white shadow-md shadow-emerald-500/20">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
            </div>
            <div>
                <h1 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white flex items-center space-x-2">
                    <span>ClaudePulse 1-Click Updater</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30">GitHub Sync</span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Directly syncs and updates from GitHub without manual zip downloads.</p>
            </div>
        </div>

        <?php if ($status === 'success'): ?>
        <!-- Success Alert -->
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 text-emerald-800 dark:bg-emerald-950/40 dark:border-emerald-800/60 dark:text-emerald-200 flex items-start space-x-3">
            <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div class="space-y-1">
                <h4 class="font-bold text-sm">Update Successfully Installed!</h4>
                <p class="text-xs opacity-90">All core gateway files have been updated to the latest GitHub release. Your live database and keys were preserved safely.</p>
                <div class="pt-3 flex flex-wrap gap-2">
                    <a href="/dashboard" class="inline-flex items-center px-4 py-2 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold shadow transition">
                        Open Dashboard &rarr;
                    </a>
                    <button type="button" onclick="cleanupFiles()" id="btn-cleanup" class="inline-flex items-center px-4 py-2 rounded-lg bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-200 text-xs font-medium transition">
                        Delete Updater File (Security Cleanup)
                    </button>
                </div>
            </div>
        </div>
        <?php elseif ($status === 'error'): ?>
        <!-- Error Alert -->
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 text-rose-800 dark:bg-rose-950/40 dark:border-rose-800/60 dark:text-rose-200 flex items-start space-x-3">
            <svg class="w-5 h-5 text-rose-600 dark:text-rose-400 mt-0.5 shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
            </svg>
            <div>
                <h4 class="font-bold text-sm">Update Failed</h4>
                <p class="text-xs opacity-90 mt-0.5">Please check the activity log below for troubleshooting details.</p>
            </div>
        </div>
        <?php endif; ?>

        <!-- Version Comparison Card -->
        <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800 space-y-3">
            <div class="flex items-center justify-between text-xs">
                <span class="text-slate-500 font-medium">Repository:</span>
                <a href="https://github.com/<?= htmlspecialchars($githubRepo) ?>" target="_blank" class="font-mono text-emerald-600 hover:underline flex items-center space-x-1">
                    <span><?= htmlspecialchars($githubRepo) ?></span>
                    <svg class="w-3.5 h-3.5" fill="currentColor" viewBox="0 0 24 24"><path fill-rule="evenodd" clip-rule="evenodd" d="M12 2C6.477 2 2 6.484 2 12.017c0 4.425 2.865 8.18 6.839 9.504.5.092.682-.217.682-.483 0-.237-.008-.868-.013-1.703-2.782.605-3.369-1.343-3.369-1.343-.454-1.158-1.11-1.466-1.11-1.466-.908-.62.069-.608.069-.608 1.003.07 1.53 1.032 1.53 1.032.892 1.53 2.341 1.088 2.91.832.092-.647.35-1.088.636-1.338-2.22-.253-4.555-1.113-4.555-4.951 0-1.093.39-1.988 1.029-2.688-.103-.253-.446-1.272.098-2.65 0 0 .84-.27 2.75 1.026A9.564 9.564 0 0112 6.844c.85.004 1.705.115 2.504.337 1.909-1.296 2.747-1.027 2.747-1.027.546 1.379.202 2.398.1 2.651.64.7 1.028 1.595 1.028 2.688 0 3.848-2.339 4.695-4.566 4.943.359.309.678.92.678 1.855 0 1.338-.012 2.419-.012 2.747 0 .268.18.58.688.482A10.019 10.019 0 0022 12.017C22 6.484 17.522 2 12 2z"/></svg>
                </a>
            </div>
            <div class="grid grid-cols-2 gap-3 pt-2 border-t border-slate-200 dark:border-slate-800 text-xs">
                <div>
                    <span class="text-slate-400 block text-[11px] uppercase tracking-wider font-bold">Installed Version</span>
                    <span class="font-mono font-bold text-slate-800 dark:text-slate-200 flex items-center space-x-1.5 mt-0.5">
                        <span>v<?= htmlspecialchars($localInfo['version']) ?></span>
                        <span class="px-1.5 py-0.2 rounded bg-slate-200 dark:bg-slate-800 text-[10px]"><?= htmlspecialchars($localInfo['commit']) ?></span>
                    </span>
                </div>
                <div>
                    <span class="text-slate-400 block text-[11px] uppercase tracking-wider font-bold">Latest on GitHub</span>
                    <span class="font-mono font-bold <?= $hasUpdate ? 'text-emerald-600 dark:text-emerald-400' : 'text-slate-800 dark:text-slate-200' ?> flex items-center space-x-1.5 mt-0.5">
                        <span><?= $latestCommit ? "Commit {$latestCommit}" : 'Checking...' ?></span>
                        <?php if ($hasUpdate): ?>
                            <span class="px-1.5 py-0.2 rounded bg-emerald-500/20 text-emerald-500 text-[10px]">New!</span>
                        <?php elseif ($githubOnline): ?>
                            <span class="text-emerald-500 text-xs">✓ Up to date</span>
                        <?php endif; ?>
                    </span>
                </div>
            </div>
            <?php if (!empty($commitMessage)): ?>
            <div class="text-[11px] text-slate-500 dark:text-slate-400 bg-white dark:bg-slate-950 p-2.5 rounded-lg border border-slate-200 dark:border-slate-800">
                <span class="font-semibold text-slate-600 dark:text-slate-300">Latest update:</span>
                <?= htmlspecialchars(substr($commitMessage, 0, 120)) ?><?= strlen($commitMessage) > 120 ? '...' : '' ?>
            </div>
            <?php endif; ?>
        </div>

        <!-- Pre-flight System Checks -->
        <div class="space-y-2">
            <h3 class="text-xs uppercase tracking-wider font-bold text-slate-400">Pre-Flight System Check</h3>
            <div class="grid grid-cols-2 gap-2 text-xs">
                
                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">PHP 8.1+</span>
                    <span class="font-mono font-bold text-emerald-600 dark:text-emerald-400">
                        <?= PHP_VERSION ?> ✓
                    </span>
                </div>

                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">ZipArchive</span>
                    <span class="font-mono font-bold <?= $zipExtOk ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600' ?>">
                        <?= $zipExtOk ? 'Available ✓' : 'Missing ✗' ?>
                    </span>
                </div>

                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">GitHub Connect</span>
                    <span class="font-mono font-bold <?= $githubOnline ? 'text-emerald-600 dark:text-emerald-400' : 'text-amber-500' ?>">
                        <?= $githubOnline ? 'Connected ✓' : 'Offline' ?>
                    </span>
                </div>

                <div class="flex items-center justify-between p-2.5 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">Write Access</span>
                    <span class="font-mono font-bold <?= $isWritable ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600' ?>">
                        <?= $isWritable ? 'Writable ✓' : 'Read-only ✗' ?>
                    </span>
                </div>

            </div>
        </div>

        <?php if (!empty($log)): ?>
        <!-- Execution Log -->
        <div class="space-y-1.5">
            <h3 class="text-xs uppercase tracking-wider font-bold text-slate-400">Activity Log</h3>
            <div class="p-3.5 rounded-xl bg-slate-900 text-slate-300 font-mono text-[11px] space-y-1 max-h-48 overflow-y-auto border border-slate-800">
                <?php foreach ($log as $l): ?>
                    <div class="flex items-start space-x-1.5">
                        <span class="text-emerald-400">&rsaquo;</span>
                        <span><?= htmlspecialchars($l) ?></span>
                    </div>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endif; ?>

        <?php if ($status !== 'success'): ?>
        <!-- Update Action Buttons -->
        <div class="space-y-2.5 pt-2">
            <!-- 1-Click Auto Update from GitHub (PRIMARY) -->
            <form method="POST">
                <input type="hidden" name="action" value="github_update">
                <button type="submit" <?= (!$phpVersionOk || !$zipExtOk || !$isWritable || !$githubOnline) ? 'disabled' : '' ?> class="w-full py-3.5 px-4 rounded-xl bg-emerald-600 hover:bg-emerald-500 disabled:opacity-50 text-white font-bold text-xs transition shadow-lg shadow-emerald-600/20 flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                    </svg>
                    <span><?= $hasUpdate ? '1-Click Auto-Update from GitHub (Recommended)' : 'Re-install / Sync Latest from GitHub' ?></span>
                </button>
            </form>

            <?php if ($localZipFound): ?>
            <!-- Fallback: Local update.zip -->
            <form method="POST">
                <input type="hidden" name="action" value="install_local">
                <button type="submit" class="w-full py-2.5 px-4 rounded-xl bg-slate-200 hover:bg-slate-300 dark:bg-slate-800 dark:hover:bg-slate-700 text-slate-700 dark:text-slate-300 font-medium text-xs transition flex items-center justify-center space-x-2">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12h6m-6 4h6m2 5H7a2 2 0 01-2-2V5a2 2 0 012-2h5.586a1 1 0 01.707.293l5.414 5.414a1 1 0 01.293.707V19a2 2 0 01-2 2z" />
                    </svg>
                    <span>Extract Local update.zip</span>
                </button>
            </form>
            <?php endif; ?>
        </div>
        <?php endif; ?>

        <div class="text-center text-[11px] text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800">
            ClaudePulse Self-Hosted API Gateway &bull; Designed by DeveloperSarim
        </div>

    </div>

    <script>
        async function cleanupFiles() {
            const btn = document.getElementById('btn-cleanup');
            btn.disabled = true;
            btn.innerText = 'Deleting updater...';

            try {
                const res = await fetch('update.php?action=cleanup');
                const data = await res.json();
                if (data.success) {
                    alert('Updater file deleted successfully! Redirecting to Dashboard.');
                    window.location.href = '/dashboard';
                } else {
                    alert('Notice: ' + (data.errors ? data.errors.join("\n") : 'Please remove update.php manually.'));
                    window.location.href = '/dashboard';
                }
            } catch (e) {
                window.location.href = '/dashboard';
            }
        }
    </script>
</body>
</html>
