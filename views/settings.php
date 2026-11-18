<?php
$pageTitle = 'Settings & Vault';
$currentRoute = 'settings';
use ClaudePulse\Database;
use ClaudePulse\Auth;

$currentUser = Auth::user();

// Fetch settings
$autoPromptCaching = Database::getSetting('auto_prompt_caching', '1') === '1';
$publicStatusPage = Database::getSetting('public_status_page', '1') === '1';
$autoFallback = Database::getSetting('auto_fallback', '1') === '1';
$logPayloads = Database::getSetting('log_payloads', '0') === '1';
$dbSize = Database::getDatabaseSize();
?>

<!-- Header -->
<div class="mb-8">
    <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Upstream Key Vault & System Settings</h1>
    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Configure your official Anthropic API keys (encrypted at rest), toggle cost optimization features, and manage maintenance.</p>
</div>

<!-- Section 1: Upstream Anthropic Key Vault -->
<div class="card-clean p-6 mb-8">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                <span>Official Anthropic API Key Vault</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Encrypted with AES-256-CBC at rest. Traffic forwarded to Anthropic will authenticate using these keys.</p>
        </div>

        <div class="flex items-center space-x-2">
            <button onclick="syncAnthropicModels(this)" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition border border-slate-300 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700" title="Fetch live models directly from Anthropic API">
                <svg class="w-3.5 h-3.5 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Sync Models from Anthropic</span>
            </button>
            <button onclick="ClaudePulse.openModal('modal-add-upstream')" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition shadow-2xs dark:bg-emerald-600 dark:hover:bg-emerald-700">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 4v16m8-8H4"/></svg>
                <span>Add Upstream Key</span>
            </button>
        </div>
    </div>

    <!-- Upstream Keys Table -->
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-slate-200 text-slate-500 dark:border-slate-800 dark:text-slate-400">
                    <th class="pb-2.5 font-semibold">Account Label</th>
                    <th class="pb-2.5 font-semibold">Masked Key</th>
                    <th class="pb-2.5 font-semibold">Live Verification & Status</th>
                    <th class="pb-2.5 font-semibold">Active</th>
                    <th class="pb-2.5 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="upstream-tbody" class="divide-y divide-slate-100 dark:divide-slate-800/60">
                <tr>
                    <td colspan="5" class="py-8 text-center text-slate-400">Loading upstream keys vault...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Section 2: Cost Optimizer & Gateway Policies -->
<div class="card-clean p-6 mb-8">
    <div class="mb-5">
        <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
            <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            <span>Cost Optimizer & Gateway Policies</span>
        </h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">Control proxy engine behavior, prompt caching injection, and public monitoring.</p>
    </div>

    <form id="form-settings" class="space-y-4">
        
        <!-- Toggle 1: Auto Prompt Caching -->
        <div class="flex items-start justify-between gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800">
            <div>
                <div class="text-xs font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                    <span>Auto Prompt Caching Optimizer</span>
                    <span class="badge badge-emerald">Up to 90% savings</span>
                </div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                    Automatically injects ephemeral <code class="text-emerald-600 dark:text-emerald-400 font-mono">cache_control</code> breakpoints onto repeated system instructions and tool definitions. Anthropic caches these for 5 minutes, reducing Claude Code input token rates from $3.00/M down to $0.30/M.
                </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-0.5">
                <input type="checkbox" name="auto_prompt_caching" class="sr-only peer" <?= $autoPromptCaching ? 'checked' : '' ?>>
                <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
            </label>
        </div>

        <!-- Toggle 2: Public Status Page -->
        <div class="flex items-start justify-between gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800">
            <div>
                <div class="text-xs font-bold text-slate-900 dark:text-white">Public Status Page (<code class="text-emerald-600 dark:text-emerald-400 font-mono">/status</code>)</div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                    Enables the public operational status page and JSON health check endpoint. Useful for hooking into external monitors like UptimeRobot, BetterStack, or Pingdom without requiring authentication.
                </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-0.5">
                <input type="checkbox" name="public_status_page" class="sr-only peer" <?= $publicStatusPage ? 'checked' : '' ?>>
                <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
            </label>
        </div>

        <!-- Toggle 3: Auto Upstream Fallback -->
        <div class="flex items-start justify-between gap-4 p-4 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800">
            <div>
                <div class="text-xs font-bold text-slate-900 dark:text-white">Automatic Upstream Failover</div>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1 leading-relaxed">
                    If your primary Anthropic API key hits a 429 rate limit or 529 overloaded error, immediately retries the request with the next active upstream key in the vault to prevent client disruptions.
                </p>
            </div>
            <label class="relative inline-flex items-center cursor-pointer flex-shrink-0 mt-0.5">
                <input type="checkbox" name="auto_fallback" class="sr-only peer" <?= $autoFallback ? 'checked' : '' ?>>
                <div class="w-11 h-6 bg-slate-300 peer-focus:outline-none rounded-full peer dark:bg-slate-700 peer-checked:after:translate-x-full peer-checked:after:border-white after:content-[''] after:absolute after:top-[2px] after:left-[2px] after:bg-white after:border-slate-300 after:border after:rounded-full after:h-5 after:w-5 after:transition-all peer-checked:bg-emerald-600"></div>
            </label>
        </div>

        <div class="pt-2 flex justify-end">
            <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition shadow-2xs dark:bg-emerald-600 dark:hover:bg-emerald-700">
                Save Policy Settings
            </button>
        </div>

    </form>
</div>

<!-- Section 3 & 4: Admin Profile & Maintenance -->
<div class="grid grid-cols-1 lg:grid-cols-2 gap-6">

    <!-- Admin Account Card -->
    <div class="card-clean p-6">
        <h2 class="text-base font-bold text-slate-900 dark:text-white mb-1">Administrator Profile</h2>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Update login credentials and master access.</p>

        <form id="form-account" class="space-y-3">
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Username</label>
                <input type="text" name="username" value="<?= htmlspecialchars($currentUser['username'] ?? '') ?>" required
                       class="form-input">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Email Address</label>
                <input type="email" name="email" value="<?= htmlspecialchars($currentUser['email'] ?? '') ?>"
                       class="form-input">
            </div>

            <div class="pt-2 border-t border-slate-200 dark:border-slate-800">
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Current Password (Required)</label>
                <input type="password" name="current_password" required placeholder="••••••••••••"
                       class="form-input">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">New Password (Leave blank to keep current)</label>
                <input type="password" name="new_password" minlength="8" placeholder="••••••••••••"
                       class="form-input">
            </div>

            <div class="pt-2 flex justify-end">
                <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition dark:bg-slate-800 dark:hover:bg-slate-700">
                    Update Account
                </button>
            </div>
        </form>
    </div>

    <!-- SQLite Maintenance Card -->
    <div class="card-clean p-6 flex flex-col justify-between">
        <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white mb-1">Database & Maintenance</h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Zero-maintenance SQLite shared hosting database status.</p>

            <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 space-y-2 mb-4 dark:bg-slate-900/60 dark:border-slate-800 text-xs">
                <div class="flex justify-between">
                    <span class="text-slate-500">Database Engine:</span>
                    <span class="font-mono text-emerald-700 dark:text-emerald-400 font-bold">SQLite 3 (WAL Mode)</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Database Size:</span>
                    <span id="db-size-display" class="font-mono text-slate-800 dark:text-white font-bold"><?= round($dbSize / 1024, 1) ?> KB</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Storage Location:</span>
                    <span class="font-mono text-[10px] text-slate-600 dark:text-slate-400 truncate max-w-[200px]">database/claudepulse.sqlite</span>
                </div>
                <div class="flex justify-between">
                    <span class="text-slate-500">Access Security:</span>
                    <span class="text-emerald-700 dark:text-emerald-400 font-semibold">Protected via .htaccess</span>
                </div>
            </div>
        </div>

        <div class="space-y-2">
            <button id="btn-vacuum-db" class="w-full py-2.5 px-4 rounded-xl bg-white border border-slate-300 hover:bg-slate-50 text-slate-800 text-xs font-semibold flex items-center justify-center space-x-2 transition shadow-2xs dark:bg-slate-900 dark:border-slate-800 dark:text-slate-200 dark:hover:bg-slate-800">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Vacuum & Optimize Database</span>
            </button>
        </div>
    </div>

</div>

<!-- Section 5: GitHub Sync & 1-Click Auto-Updater -->
<div class="card-clean p-6 mt-8">
    <div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-5">
        <div>
            <h2 class="text-base font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15" />
                </svg>
                <span>GitHub Sync & 1-Click Auto-Updater</span>
            </h2>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-0.5">
                Automatically backup and install updates directly from the official <a href="https://github.com/<?= CLAUDEPULSE_GITHUB_REPO ?>" target="_blank" class="underline text-emerald-600 dark:text-emerald-400 font-mono"><?= CLAUDEPULSE_GITHUB_REPO ?></a> repository.
            </p>
        </div>

        <div class="flex items-center space-x-2">
            <button id="btn-check-update" onclick="checkSystemUpdate(this)" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition border border-slate-300 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-700">
                <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M21 21l-6-6m2-5a7 7 0 11-14 0 7 7 0 0114 0z"/></svg>
                <span>Check for Updates</span>
            </button>
            <button id="btn-run-update" onclick="installSystemUpdate(this)" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition shadow-2xs dark:bg-emerald-600 dark:hover:bg-emerald-700">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-8l-4-4m0 0L8 8m4-4v12"/></svg>
                <span>1-Click Auto-Update</span>
            </button>
        </div>
    </div>

    <!-- Update Status Details Grid -->
    <div class="grid grid-cols-1 sm:grid-cols-3 gap-3 p-4 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800 text-xs">
        <div>
            <div class="text-[10px] uppercase font-bold text-slate-400">Current Installed Version</div>
            <div class="font-mono text-sm font-bold text-slate-800 dark:text-white mt-0.5 flex items-center space-x-1.5">
                <span>v<?= CLAUDEPULSE_VERSION ?></span>
                <span class="badge badge-emerald text-[10px]">Active</span>
            </div>
        </div>
        <div>
            <div class="text-[10px] uppercase font-bold text-slate-400">Upstream GitHub Repository</div>
            <div class="font-mono text-xs font-semibold text-emerald-600 dark:text-emerald-400 mt-1 truncate">
                <?= CLAUDEPULSE_GITHUB_REPO ?>
            </div>
        </div>
        <div>
            <div class="text-[10px] uppercase font-bold text-slate-400">Auto-Backup Protection</div>
            <div class="text-xs text-slate-700 dark:text-slate-300 font-semibold mt-1 flex items-center space-x-1">
                <span class="w-2 h-2 rounded-full bg-emerald-500"></span>
                <span>Safety zip backup enabled</span>
            </div>
        </div>
    </div>

    <div id="updater-status-box" class="hidden mt-4 p-3.5 rounded-xl border text-xs"></div>
</div>

<!-- MODAL: Add Upstream Anthropic Key -->
<div id="modal-add-upstream" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-2xs">
    <div class="bg-white border border-slate-200 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative dark:bg-[#0f172a] dark:border-slate-800">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Add Official Anthropic API Key</h3>
            <button onclick="ClaudePulse.closeModal('modal-add-upstream')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg">&times;</button>
        </div>

        <form id="form-add-upstream" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Account Label</label>
                <input type="text" name="label" required placeholder="e.g. Anthropic Production (Tier 4)"
                       class="form-input">
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Official Anthropic API Key</label>
                <input type="password" name="api_key" required placeholder="sk-ant-api03-..." autocomplete="off"
                       class="form-input font-mono">
                <p class="text-[11px] text-slate-500 mt-1">Must start with <code class="text-emerald-700 dark:text-emerald-400 font-mono">sk-ant-</code>. Encrypted at rest using AES-256-CBC.</p>
            </div>

            <div class="pt-2 flex justify-end space-x-2">
                <button type="button" onclick="ClaudePulse.closeModal('modal-add-upstream')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-semibold text-slate-700 transition dark:bg-slate-800 dark:text-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition shadow-2xs dark:bg-emerald-600 dark:hover:bg-emerald-700">Save to Vault &rarr;</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let upstreamData = [];

    async function loadUpstreamKeys() {
        const res = await ClaudePulse.apiRequest('/api/internal/upstream');
        if (res.ok && res.data && res.data.upstream_keys) {
            upstreamData = res.data.upstream_keys;
            renderUpstreamTable();
        }
    }

    function renderUpstreamTable() {
        const tbody = document.getElementById('upstream-tbody');
        if (!tbody) return;

        if (!upstreamData.length) {
            tbody.innerHTML = `<tr><td colspan="5" class="py-8 text-center text-slate-400">No upstream Anthropic API keys configured. Please add one above.</td></tr>`;
            return;
        }

        tbody.innerHTML = upstreamData.map(k => {
            const isActive = k.is_active == 1;
            const statusClass = k.status_message && k.status_message.includes('Active & Verified') 
                ? 'text-emerald-700 dark:text-emerald-400 font-medium' 
                : k.status_message && k.status_message.includes('Invalid') 
                ? 'text-rose-700 dark:text-rose-400 font-medium' 
                : 'text-slate-500';

            return `
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                    <td class="py-3 font-semibold text-slate-900 dark:text-white">
                        ${k.label}
                    </td>
                    <td class="py-3">
                        <span class="inline-flex font-mono text-[11px] text-slate-700 bg-slate-100 px-2 py-0.5 rounded border border-slate-200 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300">
                            ${k.key_hint}
                        </span>
                    </td>
                    <td class="py-3 text-[11px] ${statusClass}">
                        ${k.status_message || 'Not tested yet'}
                    </td>
                    <td class="py-3">
                        <button onclick="toggleUpstreamActive(${k.id}, ${isActive ? 0 : 1})" class="badge ${isActive ? 'badge-emerald' : 'badge-slate'}">
                            ${isActive ? 'Active' : 'Disabled'}
                        </button>
                    </td>
                    <td class="py-3 text-right space-x-2">
                        <button onclick="testUpstreamKey(${k.id}, this)" class="text-xs text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 font-semibold">
                            Test Connection
                        </button>
                        <button onclick="deleteUpstreamKey(${k.id})" class="text-xs text-slate-400 hover:text-rose-600 font-medium">
                            Delete
                        </button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    const addUpstreamForm = document.getElementById('form-add-upstream');
    if (addUpstreamForm) {
        addUpstreamForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(addUpstreamForm);
            const payload = Object.fromEntries(formData.entries());

            const res = await ClaudePulse.apiRequest('/api/internal/upstream/add', 'POST', payload);
            if (res.ok && res.data && res.data.success) {
                ClaudePulse.closeModal('modal-add-upstream');
                addUpstreamForm.reset();
                ClaudePulse.showToast('Upstream key encrypted and saved!');
                loadUpstreamKeys();
            } else {
                ClaudePulse.showToast(res.data?.error || 'Failed to add key', 'error');
            }
        });
    }

    window.testUpstreamKey = async function(id, btn) {
        const origText = btn.innerHTML;
        btn.innerHTML = `<span class="inline-block animate-spin mr-1">↻</span> Testing...`;
        btn.disabled = true;

        const res = await ClaudePulse.apiRequest('/api/internal/upstream/test', 'POST', { id: id });
        btn.innerHTML = origText;
        btn.disabled = false;

        if (res.ok && res.data && res.data.success) {
            ClaudePulse.showToast(`Anthropic connection verified! Latency: ${res.data.latency_ms}ms`);
            loadUpstreamKeys();
        } else {
            ClaudePulse.showToast(res.data?.error || 'Connection failed', 'error');
            loadUpstreamKeys();
        }
    };

    window.toggleUpstreamActive = async function(id, newState) {
        const res = await ClaudePulse.apiRequest('/api/internal/upstream/toggle', 'POST', { id: id, is_active: newState });
        if (res.ok) {
            ClaudePulse.showToast(newState ? 'Key activated' : 'Key disabled');
            loadUpstreamKeys();
        }
    };

    window.deleteUpstreamKey = async function(id) {
        if (!confirm('Permanently remove this Anthropic API key from the vault?')) return;
        const res = await ClaudePulse.apiRequest('/api/internal/upstream/delete', 'POST', { id: id });
        if (res.ok) {
            ClaudePulse.showToast('Key deleted from vault');
            loadUpstreamKeys();
        }
    };

    const settingsForm = document.getElementById('form-settings');
    if (settingsForm) {
        settingsForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(settingsForm);
            const payload = {
                auto_prompt_caching: formData.get('auto_prompt_caching') ? 1 : 0,
                public_status_page: formData.get('public_status_page') ? 1 : 0,
                auto_fallback: formData.get('auto_fallback') ? 1 : 0,
            };

            const res = await ClaudePulse.apiRequest('/api/internal/settings/update', 'POST', payload);
            if (res.ok) {
                ClaudePulse.showToast('Gateway policies saved successfully');
            } else {
                ClaudePulse.showToast('Failed to save settings', 'error');
            }
        });
    }

    const accountForm = document.getElementById('form-account');
    if (accountForm) {
        accountForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(accountForm);
            const payload = Object.fromEntries(formData.entries());

            const res = await ClaudePulse.apiRequest('/api/internal/settings/account', 'POST', payload);
            if (res.ok && res.data && res.data.success) {
                ClaudePulse.showToast('Profile updated');
                accountForm.querySelector('[name="current_password"]').value = '';
                accountForm.querySelector('[name="new_password"]').value = '';
            } else {
                ClaudePulse.showToast(res.data?.error || 'Failed to update account', 'error');
            }
        });
    }

    const btnVacuum = document.getElementById('btn-vacuum-db');
    if (btnVacuum) {
        btnVacuum.addEventListener('click', async () => {
            btnVacuum.disabled = true;
            btnVacuum.innerHTML = `<span>Optimizing database...</span>`;

            const res = await ClaudePulse.apiRequest('/api/internal/maintenance/vacuum', 'POST');
            btnVacuum.disabled = false;
            btnVacuum.innerHTML = `
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 4v5h.582m15.356 2A8.001 8.001 0 004.582 9m0 0H9m11 11v-5h-.581m0 0a8.003 8.003 0 01-15.357-2m15.357 2H15"/></svg>
                <span>Vacuum & Optimize Database</span>
            `;

            if (res.ok && res.data) {
                ClaudePulse.showToast('Database optimized and pages reorganized!');
                document.getElementById('db-size-display').innerText = res.data.db_size_formatted;
            }
        });
    }

    window.syncAnthropicModels = async function(btn) {
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<svg class="w-3.5 h-3.5 animate-spin inline mr-1 text-emerald-600" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> <span>Syncing...</span>`;

        const res = await ClaudePulse.apiRequest('/api/internal/models/sync', 'POST');
        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (res.ok && res.data && res.data.success) {
            ClaudePulse.showToast(`Successfully synced ${res.data.count} models from Anthropic API!`);
        } else {
            ClaudePulse.showToast(res.data?.error || 'Failed to sync models from Anthropic', 'error');
        }
    };

    window.checkSystemUpdate = async function(btn) {
        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<svg class="w-3.5 h-3.5 animate-spin inline mr-1 text-slate-500" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> <span>Checking GitHub...</span>`;

        const box = document.getElementById('updater-status-box');
        const res = await ClaudePulse.apiRequest('/api/internal/updater/check', 'GET');
        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (res.ok && res.data) {
            const d = res.data;
            box.classList.remove('hidden', 'bg-rose-50', 'border-rose-200', 'text-rose-700', 'bg-emerald-50', 'border-emerald-200', 'text-emerald-800', 'bg-slate-100', 'border-slate-200', 'text-slate-800');
            box.classList.add('bg-emerald-50', 'border-emerald-200', 'text-emerald-800', 'dark:bg-emerald-500/10', 'dark:border-emerald-500/30', 'dark:text-emerald-300');
            
            box.innerHTML = `
                <div class="flex items-center justify-between">
                    <span class="font-bold flex items-center space-x-1.5">
                        <svg class="w-4 h-4 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/></svg>
                        <span>Latest Release Checked</span>
                    </span>
                    <span class="font-mono text-[11px] font-bold">Commit: ${d.latest_commit}</span>
                </div>
                <div class="mt-1 text-xs opacity-90">
                    Latest commit message: "${d.latest_commit_message || 'Up to date'}"
                </div>
            `;
            ClaudePulse.showToast('Checked GitHub successfully!');
        } else {
            ClaudePulse.showToast(res.data?.error || 'Failed to check GitHub', 'error');
        }
    };

    window.installSystemUpdate = async function(btn) {
        if (!confirm('Run 1-Click Auto-Update now? A full pre-update backup of your database will be generated automatically.')) {
            return;
        }

        const origHtml = btn.innerHTML;
        btn.disabled = true;
        btn.innerHTML = `<svg class="w-4 h-4 animate-spin inline mr-1 text-white" fill="none" viewBox="0 0 24 24"><circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"></circle><path class="opacity-75" fill="currentColor" d="M4 12a8 8 0 018-8v8H4z"></path></svg> <span>Updating & Backing up...</span>`;

        const box = document.getElementById('updater-status-box');
        const res = await ClaudePulse.apiRequest('/api/internal/updater/install', 'POST');
        btn.disabled = false;
        btn.innerHTML = origHtml;

        if (res.ok && res.data && res.data.success) {
            box.classList.remove('hidden');
            box.className = 'mt-4 p-4 rounded-xl border bg-emerald-50 border-emerald-300 text-emerald-900 dark:bg-emerald-500/10 dark:border-emerald-500/40 dark:text-emerald-300 text-xs space-y-1.5';
            box.innerHTML = `
                <div class="font-bold text-sm flex items-center space-x-1.5">
                    <svg class="w-5 h-5 text-emerald-600" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>${res.data.message}</span>
                </div>
                <div class="font-mono text-[11px] text-emerald-800 dark:text-emerald-400">
                    Backup file saved: <b>${res.data.backup}</b>
                </div>
            `;
            ClaudePulse.showToast('System updated successfully! Refreshing...');
            setTimeout(() => window.location.reload(), 1500);
        } else {
            ClaudePulse.showToast(res.data?.error || 'Failed to install update', 'error');
        }
    };

    loadUpstreamKeys();
});
</script>
