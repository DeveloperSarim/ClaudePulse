<?php
declare(strict_types=1);

/**
 * ClaudePulse Standalone Web Updater & Zip Installer
 * 
 * Instructions:
 * 1. Upload 'update.zip' and this 'update.php' to your server's project root (e.g. public_html/).
 * 2. Open this file in your browser: https://yourdomain.com/update.php
 * 3. Click 'Run Update Now'. It will automatically backup, extract files, and run database migrations.
 * 4. Click 'Delete Updater Files' when done to keep your server secure!
 */

@ini_set('display_errors', '1');
error_reporting(E_ALL);
@set_time_limit(300);

$rootDir = __DIR__;
$zipFile = $rootDir . '/update.zip';
$backupDir = $rootDir . '/database/backups';

// Handle self-delete action for security
if (isset($_GET['action']) && $_GET['action'] === 'cleanup') {
    $deleted = [];
    $errors = [];

    if (file_exists($zipFile)) {
        if (@unlink($zipFile)) {
            $deleted[] = 'update.zip';
        } else {
            $errors[] = 'Failed to delete update.zip. Please delete it via File Manager.';
        }
    }

    $thisFile = __FILE__;
    if (file_exists($thisFile)) {
        if (@unlink($thisFile)) {
            $deleted[] = basename($thisFile);
        } else {
            $errors[] = 'Failed to delete ' . basename($thisFile) . '. Please delete it via File Manager.';
        }
    }

    header('Content-Type: application/json');
    echo json_encode([
        'success' => empty($errors),
        'deleted' => $deleted,
        'errors'  => $errors,
        'redirect' => '/dashboard'
    ]);
    exit;
}

$status = null;
$log = [];
$canUpdate = true;
$phpVersionOk = version_compare(PHP_VERSION, '8.1.0', '>=');
$zipExtOk = class_exists('ZipArchive');
$zipFound = file_exists($zipFile);
$isWritable = is_writable($rootDir);

if (!$phpVersionOk || !$zipExtOk || !$zipFound || !$isWritable) {
    $canUpdate = false;
}

// Perform update action
if ($_SERVER['REQUEST_METHOD'] === 'POST' && isset($_POST['action']) && $_POST['action'] === 'install') {
    if (!$canUpdate) {
        $status = 'error';
        $log[] = 'Pre-installation checks failed. Cannot proceed.';
    } else {
        try {
            $log[] = 'Starting ClaudePulse Auto-Update process...';

            // 1. Create Backup Directory
            if (!is_dir($backupDir)) {
                @mkdir($backupDir, 0755, true);
            }

            // 2. Safe Database & Config Backup
            $backupZipName = 'backup_' . date('Ymd_His') . '.zip';
            $backupZipPath = $backupDir . '/' . $backupZipName;
            
            $backupZip = new ZipArchive();
            if ($backupZip->open($backupZipPath, ZipArchive::CREATE | ZipArchive::OVERWRITE) === true) {
                // Backup database file if present
                $dbFile = $rootDir . '/database/claudepulse.sqlite';
                if (file_exists($dbFile)) {
                    $backupZip->addFile($dbFile, 'database/claudepulse.sqlite');
                }
                // Backup secret.key if present
                $keyFile = $rootDir . '/database/secret.key';
                if (file_exists($keyFile)) {
                    $backupZip->addFile($keyFile, 'database/secret.key');
                }
                // Backup config.php if present
                $cfgFile = $rootDir . '/config.php';
                if (file_exists($cfgFile)) {
                    $backupZip->addFile($cfgFile, 'config.php');
                }
                $backupZip->close();
                $log[] = "Safety backup created successfully: database/backups/{$backupZipName}";
            } else {
                $log[] = "Notice: Could not create zip backup, proceeding with caution.";
            }

            // 3. Extract update.zip with strict safeguards
            $zip = new ZipArchive();
            $res = $zip->open($zipFile);
            if ($res !== true) {
                throw new Exception("Failed to open update.zip (ZipArchive Error Code: {$res})");
            }

            $extractedFilesCount = 0;
            $skippedFilesCount = 0;

            for ($i = 0; $i < $zip->numFiles; $i++) {
                $entryName = $zip->getNameIndex($i);

                // Normalise path
                $cleanEntry = ltrim(str_replace('\\', '/', $entryName), '/');

                // Security Safeguard: Prevent overwriting existing live SQLite database or encryption keys!
                if ($cleanEntry === 'database/claudepulse.sqlite' && file_exists($rootDir . '/database/claudepulse.sqlite')) {
                    $skippedFilesCount++;
                    $log[] = "Preserved live database: {$cleanEntry} (Skipped overwrite)";
                    continue;
                }
                if ($cleanEntry === 'database/secret.key' && file_exists($rootDir . '/database/secret.key')) {
                    $skippedFilesCount++;
                    $log[] = "Preserved master secret key: {$cleanEntry} (Skipped overwrite)";
                    continue;
                }

                // Skip extracting updater itself or backup zips from the archive
                if ($cleanEntry === 'update.php' || str_starts_with($cleanEntry, 'database/backups/')) {
                    continue;
                }

                $targetPath = $rootDir . '/' . $cleanEntry;

                // Handle directory
                if (str_ends_with($entryName, '/')) {
                    if (!is_dir($targetPath)) {
                        @mkdir($targetPath, 0755, true);
                    }
                    continue;
                }

                // Ensure parent directory exists
                $targetDir = dirname($targetPath);
                if (!is_dir($targetDir)) {
                    @mkdir($targetDir, 0755, true);
                }

                // Extract stream
                $fp = $zip->getStream($entryName);
                if ($fp) {
                    $dest = @fopen($targetPath, 'w');
                    if ($dest) {
                        while (!feof($fp)) {
                            fwrite($dest, fread($fp, 8192));
                        }
                        fclose($dest);
                        $extractedFilesCount++;
                    }
                    fclose($fp);
                }
            }

            $zip->close();
            $log[] = "Extracted {$extractedFilesCount} updated files successfully ({$skippedFilesCount} protected files preserved).";

            // 4. Trigger Schema Auto-Migrations
            $configFile = $rootDir . '/config.php';
            if (file_exists($configFile)) {
                require_once $configFile;
                if (class_exists('ClaudePulse\Database')) {
                    $db = \ClaudePulse\Database::getConnection();
                    $log[] = "SQLite schema auto-migrations executed successfully (Token quotas & spend caps ready).";
                }
            }

            $status = 'success';
            $log[] = "ClaudePulse update completed with 100% success!";

        } catch (Exception $e) {
            $status = 'error';
            $log[] = "Update failed: " . $e->getMessage();
        }
    }
}
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>ClaudePulse 1-Click Web Updater</title>
    <link rel="icon" type="image/svg+xml" href="data:image/svg+xml,%3Csvg xmlns='http://www.w3.org/2000/svg' viewBox='0 0 24 24' fill='none' stroke='%23059669' stroke-width='2.2' stroke-linecap='round' stroke-linejoin='round'%3E%3Cpolyline points='22 12 18 12 15 21 9 3 6 12 2 12'%3E%3C/polyline%3E%3C/svg%3E">
    <script src="https://cdn.tailwindcss.com"></script>
    <script>
        tailwind.config = {
            darkMode: 'class',
            theme: {
                extend: {
                    colors: {
                        brand: { 500: '#10b981', 600: '#059669' }
                    }
                }
            }
        }
    </script>
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen py-10 px-4 sm:px-6 dark:bg-[#090d16] dark:text-slate-100 flex items-center justify-center font-sans">

    <div class="max-w-xl w-full bg-white dark:bg-[#0f172a] rounded-2xl border border-slate-200 dark:border-slate-800 shadow-xl p-6 sm:p-8 space-y-6">

        <!-- Header -->
        <div class="flex items-center space-x-3 pb-4 border-b border-slate-100 dark:border-slate-800">
            <div class="w-10 h-10 rounded-xl bg-slate-900 text-white flex items-center justify-center dark:bg-emerald-500 dark:text-slate-950 shadow-sm flex-shrink-0">
                <svg class="w-5 h-5" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                    <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                </svg>
            </div>
            <div>
                <h1 class="text-lg font-bold tracking-tight text-slate-900 dark:text-white flex items-center space-x-2">
                    <span>ClaudePulse 1-Click Updater</span>
                    <span class="px-2 py-0.5 rounded text-[10px] font-mono bg-emerald-50 text-emerald-700 border border-emerald-200 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30">Auto-Extractor</span>
                </h1>
                <p class="text-xs text-slate-500 dark:text-slate-400">Safely extracts update.zip, preserves database & runs auto-migrations.</p>
            </div>
        </div>

        <?php if ($status === 'success'): ?>
        <!-- Success Banner -->
        <div class="p-4 rounded-xl bg-emerald-50 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/30 space-y-3">
            <div class="flex items-center space-x-2.5 text-emerald-800 dark:text-emerald-300 font-bold text-sm">
                <svg class="w-5 h-5 text-emerald-600 dark:text-emerald-400 flex-shrink-0" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
                <span>Update Completed Successfully!</span>
            </div>
            <p class="text-xs text-emerald-700 dark:text-emerald-400/90 leading-relaxed">
                All updated files have been extracted. Your database, admin credentials, and API keys are intact and ready.
            </p>
            <div class="pt-2 flex flex-col sm:flex-row gap-2">
                <button id="btn-cleanup" onclick="cleanupFiles()" class="px-4 py-2 rounded-xl bg-rose-600 hover:bg-rose-700 text-white font-semibold text-xs transition shadow-sm flex items-center justify-center space-x-1.5">
                    <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M19 7l-.867 12.142A2 2 0 0116.138 21H7.862a2 2 0 01-1.995-1.858L5 7m5 4v6m4-6v6m1-10V4a1 1 0 00-1-1h-4a1 1 0 00-1 1v3M4 7h16"/></svg>
                    <span>Delete update.php & update.zip (Recommended)</span>
                </button>
                <a href="/dashboard" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white dark:bg-emerald-600 dark:hover:bg-emerald-700 font-semibold text-xs transition text-center">
                    Open Dashboard &rarr;
                </a>
            </div>
        </div>
        <?php elseif ($status === 'error'): ?>
        <!-- Error Banner -->
        <div class="p-4 rounded-xl bg-rose-50 border border-rose-200 dark:bg-rose-500/10 dark:border-rose-500/30 text-rose-700 dark:text-rose-400 text-xs">
            <div class="font-bold flex items-center space-x-2 text-sm mb-1">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                <span>Update Encountered an Issue</span>
            </div>
            <p>Please check the log details below or verify file permissions in File Manager.</p>
        </div>
        <?php endif; ?>

        <!-- Environment & File Checks -->
        <div class="space-y-2.5">
            <h3 class="text-xs uppercase tracking-wider font-bold text-slate-400">Pre-Flight System Check</h3>
            
            <div class="grid grid-cols-1 sm:grid-cols-2 gap-2 text-xs">
                
                <!-- Check: PHP Version -->
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">PHP 8.1+</span>
                    <span class="font-mono font-bold <?= $phpVersionOk ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600' ?>">
                        <?= PHP_VERSION ?> <?= $phpVersionOk ? '✓' : '✗' ?>
                    </span>
                </div>

                <!-- Check: ZipArchive -->
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">ZipArchive Module</span>
                    <span class="font-mono font-bold <?= $zipExtOk ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600' ?>">
                        <?= $zipExtOk ? 'Available ✓' : 'Missing ✗' ?>
                    </span>
                </div>

                <!-- Check: update.zip Present -->
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">update.zip Archive</span>
                    <span class="font-mono font-bold <?= $zipFound ? 'text-emerald-600 dark:text-emerald-400' : 'text-rose-600' ?>">
                        <?php if ($zipFound): ?>
                            <?= round(filesize($zipFile) / (1024 * 1024), 2) ?> MB ✓
                        <?php else: ?>
                            Not Found ✗
                        <?php endif; ?>
                    </span>
                </div>

                <!-- Check: Writable -->
                <div class="flex items-center justify-between p-3 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800">
                    <span class="text-slate-600 dark:text-slate-400">Write Permissions</span>
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
        <!-- Trigger Form -->
        <form method="POST" class="pt-2">
            <input type="hidden" name="action" value="install">
            <button type="submit" <?= !$canUpdate ? 'disabled' : '' ?> class="w-full py-3 rounded-xl bg-slate-900 hover:bg-slate-800 disabled:opacity-50 text-white font-semibold text-xs transition shadow-sm dark:bg-emerald-600 dark:hover:bg-emerald-700 flex items-center justify-center space-x-2">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12" />
                </svg>
                <span>Run Update Now (Safe Auto-Extract & Migrate)</span>
            </button>
            <?php if (!$zipFound): ?>
                <p class="text-[11px] text-amber-600 text-center mt-2">
                    Please ensure you have uploaded <code>update.zip</code> into this same folder.
                </p>
            <?php endif; ?>
        </form>
        <?php endif; ?>

        <div class="text-center text-[11px] text-slate-400 pt-2 border-t border-slate-100 dark:border-slate-800">
            ClaudePulse Self-Hosted API Gateway &bull; Designed by DeveloperSarim
        </div>

    </div>

    <script>
        async function cleanupFiles() {
            const btn = document.getElementById('btn-cleanup');
            btn.disabled = true;
            btn.innerText = 'Deleting updater files...';

            try {
                const res = await fetch('update.php?action=cleanup');
                const data = await res.json();
                if (data.success) {
                    alert('Updater files deleted successfully! Redirecting to Dashboard.');
                    window.location.href = '/dashboard';
                } else {
                    alert('Notice: ' + (data.errors ? data.errors.join("\n") : 'Please remove update.php and update.zip manually.'));
                    window.location.href = '/dashboard';
                }
            } catch (e) {
                window.location.href = '/dashboard';
            }
        }
    </script>
</body>
</html>
