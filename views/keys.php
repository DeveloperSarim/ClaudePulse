<?php
$pageTitle = 'Gateway API Keys';
$currentRoute = 'keys';
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Gateway API Keys</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Issue dedicated <code class="text-emerald-700 dark:text-emerald-400 font-mono">sk-cp-...</code> keys with hard Token Quotas or USD Spend Caps for Claude Code, Cursor, and team devices.</p>
    </div>

    <button onclick="openCreateModal()" class="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition shadow-sm dark:bg-emerald-600 dark:hover:bg-emerald-700">
        <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
            <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M12 4v16m8-8H4" />
        </svg>
        <span>Generate Gateway Key</span>
    </button>
</div>

<!-- Keys Table Card -->
<div class="card-clean overflow-hidden">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400">
                    <th class="py-3 px-4 font-semibold">Key Name & Prefix</th>
                    <th class="py-3 px-4 font-semibold min-w-[220px]">Enforced Quota (Tokens / Spend)</th>
                    <th class="py-3 px-4 font-semibold">Validity / Expiry</th>
                    <th class="py-3 px-4 font-semibold">Status</th>
                    <th class="py-3 px-4 font-semibold text-right">Requests</th>
                    <th class="py-3 px-4 font-semibold">Last Used</th>
                    <th class="py-3 px-4 font-semibold text-right">Actions</th>
                </tr>
            </thead>
            <tbody id="keys-tbody" class="divide-y divide-slate-100 dark:divide-slate-800/60">
                <tr>
                    <td colspan="7" class="py-12 text-center text-slate-400">Loading gateway keys...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- MODAL: Create New Gateway Key -->
<div id="modal-create-key" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
    <div class="bg-white border border-slate-200 rounded-2xl p-6 max-w-xl w-full shadow-2xl relative dark:bg-[#0f172a] dark:border-slate-800 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Generate Gateway Key</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Issue a dedicated key with a Token Quota or USD Spend Cap.</p>
            </div>
            <button onclick="ClaudePulse.closeModal('modal-create-key')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg">&times;</button>
        </div>

        <form id="form-create-key" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Key Label / Name</label>
                <input type="text" name="name" required placeholder="e.g. MacBook Pro Claude Code / Client 1" 
                       class="form-input">
            </div>

            <!-- Quota Limit Mode Selector -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Quota Limit Mode</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 p-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-xs">
                    <button type="button" id="create-mode-tokens" onclick="switchQuotaMode('create', 'tokens')" class="mode-btn py-1.5 px-2 rounded-lg font-semibold transition text-center bg-white text-slate-900 dark:bg-emerald-600 dark:text-white shadow-2xs">
                        🪙 Token Quota
                    </button>
                    <button type="button" id="create-mode-usd" onclick="switchQuotaMode('create', 'usd')" class="mode-btn py-1.5 px-2 rounded-lg font-semibold transition text-center text-slate-600 dark:text-slate-300 hover:text-slate-900">
                        💵 USD Spend Cap
                    </button>
                    <button type="button" id="create-mode-unlimited" onclick="switchQuotaMode('create', 'unlimited')" class="mode-btn py-1.5 px-2 rounded-lg font-semibold transition text-center text-slate-600 dark:text-slate-300 hover:text-slate-900">
                        ♾️ Unlimited
                    </button>
                    <button type="button" id="create-mode-dual" onclick="switchQuotaMode('create', 'dual')" class="mode-btn py-1.5 px-2 rounded-lg font-semibold transition text-center text-slate-600 dark:text-slate-300 hover:text-slate-900">
                        ⚙️ Custom Dual
                    </button>
                </div>
            </div>

            <!-- Token Limit Container -->
            <div id="create-box-tokens" class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800">
                <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1 flex items-center justify-between">
                    <span>Token Quota Limit (Total Tokens)</span>
                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-normal">Halts on reaching token count</span>
                </label>
                <input type="number" step="100000" min="0" name="cap_tokens" id="create-cap-tokens" value="10000000" placeholder="0 = Unlimited Tokens"
                       class="form-input font-mono">
                <div class="mt-2 flex flex-wrap gap-1 items-center">
                    <span class="text-[10px] text-slate-400 mr-1">Quick Select:</span>
                    <button type="button" onclick="setTokenVal('create-cap-tokens', 1000000)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">1M</button>
                    <button type="button" onclick="setTokenVal('create-cap-tokens', 5000000)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">5M</button>
                    <button type="button" onclick="setTokenVal('create-cap-tokens', 10000000)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-emerald-50 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-300 border border-emerald-300 dark:border-emerald-500/40 font-bold transition">10M</button>
                    <button type="button" onclick="setTokenVal('create-cap-tokens', 25000000)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">25M</button>
                    <button type="button" onclick="setTokenVal('create-cap-tokens', 50000000)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">50M</button>
                    <button type="button" onclick="setTokenVal('create-cap-tokens', 100000000)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">100M</button>
                </div>
            </div>

            <!-- USD Spend Cap Container -->
            <div id="create-box-usd" class="hidden p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800">
                <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1 flex items-center justify-between">
                    <span>Hard Spend Cap ($ USD)</span>
                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-normal">Halts on reaching dollar spend</span>
                </label>
                <input type="number" step="0.50" min="0" name="cap_usd" id="create-cap-usd" value="0.00" placeholder="0.00 = Unlimited USD"
                       class="form-input font-mono">
                <div class="mt-2 flex flex-wrap gap-1 items-center">
                    <span class="text-[10px] text-slate-400 mr-1">Quick Select:</span>
                    <button type="button" onclick="setUsdVal('create-cap-usd', 5.00)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">$5</button>
                    <button type="button" onclick="setUsdVal('create-cap-usd', 10.00)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">$10</button>
                    <button type="button" onclick="setUsdVal('create-cap-usd', 25.00)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">$25</button>
                    <button type="button" onclick="setUsdVal('create-cap-usd', 50.00)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">$50</button>
                    <button type="button" onclick="setUsdVal('create-cap-usd', 100.00)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">$100</button>
                </div>
            </div>

            <!-- Key Validity / Expiration Date -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Key Validity / Expiration</label>
                <div class="flex flex-wrap gap-1 items-center mb-2">
                    <button type="button" onclick="setExpiryChip('create', 0)" class="text-[10px] px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 font-semibold transition">No Expiry (Never)</button>
                    <button type="button" onclick="setExpiryChip('create', 1)" class="text-[10px] px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">24 Hours (1 Day)</button>
                    <button type="button" onclick="setExpiryChip('create', 7)" class="text-[10px] px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">7 Days (1 Week)</button>
                    <button type="button" onclick="setExpiryChip('create', 14)" class="text-[10px] px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition font-bold text-emerald-600 dark:text-emerald-400">14 Days (2 Weeks)</button>
                    <button type="button" onclick="setExpiryChip('create', 30)" class="text-[10px] px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">30 Days (1 Month)</button>
                    <button type="button" onclick="setExpiryChip('create', 90)" class="text-[10px] px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">90 Days</button>
                    <button type="button" onclick="setExpiryChip('create', 365)" class="text-[10px] px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">1 Year</button>
                </div>
                <input type="datetime-local" name="expires_at" id="create-expires-at" class="form-input font-mono text-xs">
                <p class="text-[10px] text-slate-500 mt-1">Leave empty or select 'No Expiry' for perpetual lifetime keys.</p>
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Rate Limit (RPM)</label>
                    <input type="number" min="0" name="rate_limit_rpm" value="60" placeholder="0 = Unlimited"
                           class="form-input font-mono">
                    <p class="text-[10px] text-slate-500 mt-0.5">Max requests/min.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Allowed Models (Optional)</label>
                    <input type="text" id="create-allowed-models" name="allowed_models" placeholder="Leave empty for all models"
                           class="form-input font-mono">
                    <p class="text-[10px] text-slate-500 mt-0.5">Comma-separated whitelist.</p>
                </div>
            </div>

            <div class="pt-2 flex justify-end space-x-2">
                <button type="button" onclick="ClaudePulse.closeModal('modal-create-key')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-semibold text-slate-700 transition dark:bg-slate-800 dark:text-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition shadow-sm dark:bg-emerald-600 dark:hover:bg-emerald-700">Generate Key &rarr;</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Reveal or View Key -->
<div id="modal-reveal-key" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/70 backdrop-blur-xs">
    <div class="bg-white border border-emerald-300 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative dark:bg-[#0f172a] dark:border-emerald-500/40">
        <div class="text-center mb-4">
            <div class="inline-flex p-3 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 mb-3 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z" />
                </svg>
            </div>
            <h3 id="reveal-title" class="text-base font-bold text-slate-900 dark:text-white">Gateway API Key Vault</h3>
            <p id="reveal-subtitle" class="text-xs text-slate-500 dark:text-slate-400 mt-1">Full key securely decrypted from authenticated AES-256-CBC storage.</p>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 mb-4 dark:bg-slate-900 dark:border-slate-800">
            <div class="flex items-center justify-between text-[10px] uppercase font-bold text-slate-500 mb-1">
                <span>Full Gateway API Key</span>
                <span id="reveal-key-name" class="font-semibold text-emerald-600 dark:text-emerald-400"></span>
            </div>
            <div class="flex items-center justify-between gap-2">
                <span id="revealed-key-value" class="font-mono text-xs font-bold text-emerald-700 dark:text-emerald-400 break-all select-all"></span>
                <button id="btn-copy-revealed-key" class="px-3 py-1.5 rounded-lg bg-emerald-600 hover:bg-emerald-700 text-white text-xs font-semibold flex-shrink-0 transition">
                    Copy Key
                </button>
            </div>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 text-xs text-slate-700 dark:bg-slate-900/60 dark:border-slate-800 dark:text-slate-300 mb-5">
            <div class="font-bold text-slate-900 dark:text-white mb-1 flex items-center space-x-1.5">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z"/></svg>
                <span>Quick Connect for Claude Code CLI</span>
            </div>
            <p class="text-[11px] text-slate-500 mb-2">Run these in your terminal to start using this key immediately:</p>
            <div class="code-box p-3 font-mono text-[11px] text-emerald-400 space-y-1 select-all" id="revealed-terminal-cmd">
                <!-- Injected via JS -->
            </div>
        </div>

        <button onclick="ClaudePulse.closeModal('modal-reveal-key')" class="w-full py-2.5 rounded-xl bg-slate-900 hover:bg-slate-800 text-xs text-white font-semibold transition dark:bg-slate-800 dark:hover:bg-slate-700">
            Close Vault
        </button>
    </div>
</div>

<!-- MODAL: Edit Gateway Key -->
<div id="modal-edit-key" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
    <div class="bg-white border border-slate-200 rounded-2xl p-6 max-w-xl w-full shadow-2xl relative dark:bg-[#0f172a] dark:border-slate-800 max-h-[90vh] overflow-y-auto">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Edit Gateway Key</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Update quota limits, expiry duration, or revoke access.</p>
            </div>
            <button onclick="ClaudePulse.closeModal('modal-edit-key')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg">&times;</button>
        </div>

        <form id="form-edit-key" class="space-y-4">
            <input type="hidden" name="id" id="edit-key-id">

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Key Name</label>
                <input type="text" name="name" id="edit-key-name" required
                       class="form-input">
            </div>

            <!-- Quota Limit Mode Selector -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1.5">Quota Limit Mode</label>
                <div class="grid grid-cols-2 sm:grid-cols-4 gap-1.5 p-1 rounded-xl bg-slate-100 dark:bg-slate-800/80 border border-slate-200 dark:border-slate-700 text-xs">
                    <button type="button" id="edit-mode-tokens" onclick="switchQuotaMode('edit', 'tokens')" class="mode-btn py-1.5 px-2 rounded-lg font-semibold transition text-center bg-white text-slate-900 dark:bg-emerald-600 dark:text-white shadow-2xs">
                        🪙 Token Quota
                    </button>
                    <button type="button" id="edit-mode-usd" onclick="switchQuotaMode('edit', 'usd')" class="mode-btn py-1.5 px-2 rounded-lg font-semibold transition text-center text-slate-600 dark:text-slate-300 hover:text-slate-900">
                        💵 USD Spend Cap
                    </button>
                    <button type="button" id="edit-mode-unlimited" onclick="switchQuotaMode('edit', 'unlimited')" class="mode-btn py-1.5 px-2 rounded-lg font-semibold transition text-center text-slate-600 dark:text-slate-300 hover:text-slate-900">
                        ♾️ Unlimited
                    </button>
                    <button type="button" id="edit-mode-dual" onclick="switchQuotaMode('edit', 'dual')" class="mode-btn py-1.5 px-2 rounded-lg font-semibold transition text-center text-slate-600 dark:text-slate-300 hover:text-slate-900">
                        ⚙️ Custom Dual
                    </button>
                </div>
            </div>

            <!-- Token Limit Container -->
            <div id="edit-box-tokens" class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800">
                <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1 flex items-center justify-between">
                    <span>Token Quota Limit (Total Tokens)</span>
                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-normal">Halts on reaching token count</span>
                </label>
                <input type="number" step="100000" min="0" name="cap_tokens" id="edit-key-cap-tokens" placeholder="0 = Unlimited Tokens"
                       class="form-input font-mono">
                <div class="mt-2 flex flex-wrap gap-1 items-center">
                    <span class="text-[10px] text-slate-400 mr-1">Quick Select:</span>
                    <button type="button" onclick="setTokenVal('edit-key-cap-tokens', 1000000)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">1M</button>
                    <button type="button" onclick="setTokenVal('edit-key-cap-tokens', 5000000)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">5M</button>
                    <button type="button" onclick="setTokenVal('edit-key-cap-tokens', 10000000)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">10M</button>
                    <button type="button" onclick="setTokenVal('edit-key-cap-tokens', 25000000)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">25M</button>
                    <button type="button" onclick="setTokenVal('edit-key-cap-tokens', 50000000)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">50M</button>
                </div>
            </div>

            <!-- USD Spend Cap Container -->
            <div id="edit-box-usd" class="hidden p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800">
                <label class="block text-xs font-bold text-slate-800 dark:text-slate-200 mb-1 flex items-center justify-between">
                    <span>Hard Spend Cap ($ USD)</span>
                    <span class="text-[10px] text-emerald-600 dark:text-emerald-400 font-normal">Halts on reaching dollar spend</span>
                </label>
                <input type="number" step="0.50" min="0" name="cap_usd" id="edit-key-cap" placeholder="0.00 = Unlimited USD"
                       class="form-input font-mono">
                <div class="mt-2 flex flex-wrap gap-1 items-center">
                    <span class="text-[10px] text-slate-400 mr-1">Quick Select:</span>
                    <button type="button" onclick="setUsdVal('edit-key-cap', 5.00)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">$5</button>
                    <button type="button" onclick="setUsdVal('edit-key-cap', 10.00)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">$10</button>
                    <button type="button" onclick="setUsdVal('edit-key-cap', 25.00)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">$25</button>
                    <button type="button" onclick="setUsdVal('edit-key-cap', 50.00)" class="text-[10px] font-mono px-2 py-0.5 rounded bg-white dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:border-emerald-500 border border-slate-200 dark:border-slate-700 transition">$50</button>
                </div>
            </div>

            <!-- Key Validity / Expiration Date -->
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Key Validity / Expiration</label>
                <div class="flex flex-wrap gap-1 items-center mb-2">
                    <button type="button" onclick="setExpiryChip('edit', 0)" class="text-[10px] px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 font-semibold transition">No Expiry (Never)</button>
                    <button type="button" onclick="setExpiryChip('edit', 1)" class="text-[10px] px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">24 Hours</button>
                    <button type="button" onclick="setExpiryChip('edit', 7)" class="text-[10px] px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">7 Days</button>
                    <button type="button" onclick="setExpiryChip('edit', 14)" class="text-[10px] px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">14 Days</button>
                    <button type="button" onclick="setExpiryChip('edit', 30)" class="text-[10px] px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">30 Days</button>
                    <button type="button" onclick="setExpiryChip('edit', 365)" class="text-[10px] px-2 py-1 rounded bg-slate-100 dark:bg-slate-800 text-slate-700 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">1 Year</button>
                </div>
                <input type="datetime-local" name="expires_at" id="edit-key-expires" class="form-input font-mono text-xs">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Rate Limit (RPM)</label>
                    <input type="number" min="0" name="rate_limit_rpm" id="edit-key-rpm"
                           class="form-input font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Status</label>
                    <select name="status" id="edit-key-status" class="form-input">
                        <option value="active">Active</option>
                        <option value="revoked">Revoked</option>
                    </select>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Allowed Models (Optional)</label>
                <input type="text" name="allowed_models" id="edit-key-models" placeholder="Leave empty for all models"
                       class="form-input font-mono">
            </div>

            <div class="pt-2 flex justify-end space-x-2">
                <button type="button" onclick="ClaudePulse.closeModal('modal-edit-key')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-semibold text-slate-700 transition dark:bg-slate-800 dark:text-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition dark:bg-emerald-600 dark:hover:bg-emerald-700">Save Changes</button>
            </div>
        </form>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let keysData = [];

    async function loadKeys() {
        const res = await ClaudePulse.apiRequest('/api/internal/keys');
        if (res.ok && res.data && res.data.keys) {
            keysData = res.data.keys;
            renderKeysTable();
        }
    }

    function renderKeysTable() {
        const tbody = document.getElementById('keys-tbody');
        if (!tbody) return;

        if (!keysData.length) {
            tbody.innerHTML = `<tr><td colspan="7" class="py-12 text-center text-slate-400">No gateway keys found. Click "+ Generate Gateway Key" above.</td></tr>`;
            return;
        }

        tbody.innerHTML = keysData.map(k => {
            const spent = Number(k.spent_usd || 0);
            const cap = Number(k.cap_usd || 0);
            const usedTok = Number(k.used_tokens || 0);
            const capTok = Number(k.cap_tokens || 0);

            // Determine Quota Mode display
            let quotaHtml = '';
            if (capTok > 0 && cap === 0) {
                // Token Quota Mode
                const tokPct = Math.min(100, Math.round((usedTok / capTok) * 100));
                const barColor = tokPct >= 100 ? 'bg-rose-500' : (tokPct >= 80 ? 'bg-amber-500' : 'bg-emerald-500');
                quotaHtml = `
                    <div>
                        <div class="flex items-center justify-between text-[11px] mb-1">
                            <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">🪙 ${formatTokens(usedTok)} / ${formatTokens(capTok)} (${tokPct}%)</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden dark:bg-slate-700">
                            <div class="${barColor} h-1.5 rounded-full transition-all duration-500" style="width: ${tokPct}%"></div>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1">Spend: $${spent.toFixed(4)} (Tracking only)</div>
                    </div>
                `;
            } else if (cap > 0 && capTok === 0) {
                // USD Spend Cap Mode
                const spendPct = Math.min(100, Math.round((spent / cap) * 100));
                const barColor = spendPct >= 100 ? 'bg-rose-500' : (spendPct >= 80 ? 'bg-amber-500' : 'bg-emerald-500');
                quotaHtml = `
                    <div>
                        <div class="flex items-center justify-between text-[11px] mb-1">
                            <span class="font-bold text-slate-800 dark:text-slate-200 font-mono">💵 $${spent.toFixed(2)} / $${cap.toFixed(2)} (${spendPct}%)</span>
                        </div>
                        <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden dark:bg-slate-700">
                            <div class="${barColor} h-1.5 rounded-full transition-all duration-500" style="width: ${spendPct}%"></div>
                        </div>
                        <div class="text-[10px] text-slate-400 mt-1">Tokens: ${formatTokens(usedTok)} (Tracking only)</div>
                    </div>
                `;
            } else if (capTok > 0 && cap > 0) {
                // Dual Cap
                const tokPct = Math.min(100, Math.round((usedTok / capTok) * 100));
                const spendPct = Math.min(100, Math.round((spent / cap) * 100));
                quotaHtml = `
                    <div class="space-y-1 text-[11px]">
                        <div class="font-mono">🪙 ${formatTokens(usedTok)} / ${formatTokens(capTok)} (${tokPct}%)</div>
                        <div class="font-mono">💵 $${spent.toFixed(2)} / $${cap.toFixed(2)} (${spendPct}%)</div>
                    </div>
                `;
            } else {
                // Unlimited
                quotaHtml = `
                    <div>
                        <span class="inline-flex items-center space-x-1 text-slate-600 dark:text-slate-400 font-mono text-[11px]">
                            <span>♾️ Unlimited</span>
                            <span>(${formatTokens(usedTok)} | $${spent.toFixed(3)})</span>
                        </span>
                    </div>
                `;
            }

            // Expiry Badge
            let expiryHtml = '';
            if (!k.expires_at) {
                expiryHtml = `<span class="badge bg-slate-100 text-slate-600 dark:bg-slate-800 dark:text-slate-400">Lifetime</span>`;
            } else {
                const expDate = new Date(k.expires_at.replace(' ', 'T'));
                const now = new Date();
                const diffMs = expDate - now;
                const diffDays = Math.ceil(diffMs / (1000 * 60 * 60 * 24));

                if (diffMs <= 0) {
                    expiryHtml = `<span class="badge badge-rose">Expired</span>`;
                } else if (diffDays <= 1) {
                    expiryHtml = `<span class="badge badge-amber font-bold">Expires Today</span>`;
                } else if (diffDays <= 7) {
                    expiryHtml = `<span class="badge badge-amber">${diffDays} days left</span>`;
                } else {
                    expiryHtml = `<span class="badge badge-emerald">${diffDays} days left</span>`;
                }
            }

            const statusClass = k.status === 'active' ? 'badge-emerald' : 'badge-rose';

            return `
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                    <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                        <div class="font-bold">${k.name}</div>
                        <span class="inline-flex items-center space-x-1 font-mono text-[11px] text-slate-500 bg-slate-100 px-1.5 py-0.5 rounded border border-slate-200 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-400 mt-1">
                            <span>${k.api_key_prefix}••••</span>
                        </span>
                    </td>
                    <td class="py-3 px-4">
                        ${quotaHtml}
                    </td>
                    <td class="py-3 px-4 whitespace-nowrap">
                        ${expiryHtml}
                    </td>
                    <td class="py-3 px-4">
                        <span class="badge ${statusClass}">
                            ${k.status}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right font-mono text-slate-700 dark:text-slate-300">
                        ${(k.total_requests || 0).toLocaleString()}
                    </td>
                    <td class="py-3 px-4 text-[11px] text-slate-500 whitespace-nowrap">
                        ${k.last_used_at ? k.last_used_at.split(' ')[0] : 'Never'}
                    </td>
                    <td class="py-3 px-4 text-right space-x-2 whitespace-nowrap">
                        <button onclick="viewFullKey(${k.id})" class="text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 font-bold text-xs" title="Reveal full API key">View Key</button>
                        <a href="/verify?key=${k.api_key_prefix}" target="_blank" class="text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white text-xs font-medium">Verify</a>
                        <button onclick="editKey(${k.id})" class="text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white text-xs font-medium">Edit</button>
                        <button onclick="resetUsage(${k.id})" class="text-slate-600 hover:text-amber-600 dark:text-slate-400 dark:hover:text-amber-400 text-xs font-medium">Reset</button>
                        <button onclick="toggleKeyStatus(${k.id}, '${k.status}')" class="text-slate-600 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white text-xs font-medium">
                            ${k.status === 'active' ? 'Revoke' : 'Activate'}
                        </button>
                        <button onclick="deleteKey(${k.id})" class="text-slate-400 hover:text-rose-600 text-xs font-medium">Delete</button>
                    </td>
                </tr>
            `;
        }).join('');
    }

    // Quota Mode Switching Function
    window.switchQuotaMode = function(formPrefix, mode) {
        const boxTokens = document.getElementById(`${formPrefix}-box-tokens`);
        const boxUsd = document.getElementById(`${formPrefix}-box-usd`);
        const inputTokens = document.getElementById(formPrefix === 'create' ? 'create-cap-tokens' : 'edit-key-cap-tokens');
        const inputUsd = document.getElementById(formPrefix === 'create' ? 'create-cap-usd' : 'edit-key-cap');

        // Update button states
        ['tokens', 'usd', 'unlimited', 'dual'].forEach(m => {
            const btn = document.getElementById(`${formPrefix}-mode-${m}`);
            if (btn) {
                if (m === mode) {
                    btn.className = 'mode-btn py-1.5 px-2 rounded-lg font-bold transition text-center bg-white text-slate-900 dark:bg-emerald-600 dark:text-white shadow-2xs';
                } else {
                    btn.className = 'mode-btn py-1.5 px-2 rounded-lg font-semibold transition text-center text-slate-600 dark:text-slate-300 hover:text-slate-900';
                }
            }
        });

        if (mode === 'tokens') {
            boxTokens.classList.remove('hidden');
            boxUsd.classList.add('hidden');
            if (Number(inputTokens.value) === 0) inputTokens.value = 10000000;
            inputUsd.value = 0;
        } else if (mode === 'usd') {
            boxTokens.classList.add('hidden');
            boxUsd.classList.remove('hidden');
            inputTokens.value = 0;
            if (Number(inputUsd.value) === 0) inputUsd.value = 10.00;
        } else if (mode === 'unlimited') {
            boxTokens.classList.add('hidden');
            boxUsd.classList.add('hidden');
            inputTokens.value = 0;
            inputUsd.value = 0;
        } else if (mode === 'dual') {
            boxTokens.classList.remove('hidden');
            boxUsd.classList.remove('hidden');
            if (Number(inputTokens.value) === 0) inputTokens.value = 10000000;
            if (Number(inputUsd.value) === 0) inputUsd.value = 10.00;
        }
    };

    window.openCreateModal = function() {
        switchQuotaMode('create', 'tokens');
        ClaudePulse.openModal('modal-create-key');
    };

    window.setExpiryChip = function(formPrefix, days) {
        const input = document.getElementById(formPrefix === 'create' ? 'create-expires-at' : 'edit-key-expires');
        if (!input) return;

        if (days === 0) {
            input.value = '';
            return;
        }

        const target = new Date();
        target.setDate(target.getDate() + days);
        
        // Format to YYYY-MM-DDTHH:MM
        const pad = (n) => String(n).padStart(2, '0');
        const formatted = target.getFullYear() + '-' + pad(target.getMonth() + 1) + '-' + pad(target.getDate()) + 'T' + pad(target.getHours()) + ':' + pad(target.getMinutes());
        input.value = formatted;
    };

    window.viewFullKey = async function(id) {
        const res = await ClaudePulse.apiRequest('/api/internal/keys/reveal', 'POST', { id: id });
        if (res.ok && res.data && res.data.success) {
            const fullKey = res.data.full_key;
            document.getElementById('reveal-title').innerText = 'Gateway API Key';
            document.getElementById('reveal-subtitle').innerText = 'Decrypted from AES-256-CBC encrypted vault.';
            document.getElementById('reveal-key-name').innerText = res.data.name;
            document.getElementById('revealed-key-value').innerText = fullKey;

            const hostUrl = window.location.origin;
            document.getElementById('revealed-terminal-cmd').innerHTML = `
                <div>export ANTHROPIC_BASE_URL="${hostUrl}"</div>
                <div>export ANTHROPIC_API_KEY="${fullKey}"</div>
            `;

            document.getElementById('btn-copy-revealed-key').onclick = function() {
                ClaudePulse.copyText(fullKey, this);
            };

            ClaudePulse.openModal('modal-reveal-key');
        } else {
            alert(res.data?.error || 'Full key is not stored for this older key.');
        }
    };

    const createForm = document.getElementById('form-create-key');
    if (createForm) {
        createForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(createForm);
            const payload = Object.fromEntries(formData.entries());

            const res = await ClaudePulse.apiRequest('/api/internal/keys/create', 'POST', payload);
            if (res.ok && res.data && res.data.success) {
                ClaudePulse.closeModal('modal-create-key');
                createForm.reset();
                loadKeys();

                const keyObj = res.data.key;
                document.getElementById('reveal-title').innerText = 'Gateway Key Generated!';
                document.getElementById('reveal-subtitle').innerText = 'Key is safely stored encrypted in your vault and can be viewed anytime.';
                document.getElementById('reveal-key-name').innerText = keyObj.name;
                document.getElementById('revealed-key-value').innerText = keyObj.full_key;
                
                const hostUrl = window.location.origin;
                document.getElementById('revealed-terminal-cmd').innerHTML = `
                    <div>export ANTHROPIC_BASE_URL="${hostUrl}"</div>
                    <div>export ANTHROPIC_API_KEY="${keyObj.full_key}"</div>
                `;

                document.getElementById('btn-copy-revealed-key').onclick = function() {
                    ClaudePulse.copyText(keyObj.full_key, this);
                };

                ClaudePulse.openModal('modal-reveal-key');
            } else {
                ClaudePulse.showToast(res.data?.error || 'Failed to create key', 'error');
            }
        });
    }

    window.editKey = function(id) {
        const key = keysData.find(k => k.id === id);
        if (!key) return;

        document.getElementById('edit-key-id').value = key.id;
        document.getElementById('edit-key-name').value = key.name;
        document.getElementById('edit-key-cap').value = key.cap_usd || 0;
        document.getElementById('edit-key-cap-tokens').value = key.cap_tokens || 0;
        document.getElementById('edit-key-rpm').value = key.rate_limit_rpm;
        document.getElementById('edit-key-models').value = key.allowed_models || '';
        document.getElementById('edit-key-status').value = key.status;

        // Auto-detect mode
        const cap = Number(key.cap_usd || 0);
        const tok = Number(key.cap_tokens || 0);
        if (tok > 0 && cap === 0) {
            switchQuotaMode('edit', 'tokens');
        } else if (cap > 0 && tok === 0) {
            switchQuotaMode('edit', 'usd');
        } else if (cap === 0 && tok === 0) {
            switchQuotaMode('edit', 'unlimited');
        } else {
            switchQuotaMode('edit', 'dual');
        }

        // Format expires_at for datetime-local (YYYY-MM-DDTHH:MM)
        const expInput = document.getElementById('edit-key-expires');
        if (key.expires_at) {
            expInput.value = key.expires_at.replace(' ', 'T').substring(0, 16);
        } else {
            expInput.value = '';
        }

        ClaudePulse.openModal('modal-edit-key');
    };

    const editForm = document.getElementById('form-edit-key');
    if (editForm) {
        editForm.addEventListener('submit', async (e) => {
            e.preventDefault();
            const formData = new FormData(editForm);
            const payload = Object.fromEntries(formData.entries());

            const res = await ClaudePulse.apiRequest('/api/internal/keys/update', 'POST', payload);
            if (res.ok && res.data && res.data.success) {
                ClaudePulse.closeModal('modal-edit-key');
                ClaudePulse.showToast('Key settings updated');
                loadKeys();
            } else {
                ClaudePulse.showToast(res.data?.error || 'Failed to update key', 'error');
            }
        });
    }

    window.resetUsage = async function(id) {
        if (!confirm('Reset recorded spend ($0.00) and token counter (0) for this key?')) return;
        const res = await ClaudePulse.apiRequest('/api/internal/keys/reset_usage', 'POST', { id: id });
        if (res.ok) {
            ClaudePulse.showToast('Spend & Token counters reset');
            loadKeys();
        }
    };

    window.toggleKeyStatus = async function(id, currentStatus) {
        const newStatus = currentStatus === 'active' ? 'revoked' : 'active';
        const key = keysData.find(k => k.id === id);
        if (!key) return;

        const res = await ClaudePulse.apiRequest('/api/internal/keys/update', 'POST', {
            id: id,
            name: key.name,
            cap_usd: key.cap_usd,
            cap_tokens: key.cap_tokens || 0,
            rate_limit_rpm: key.rate_limit_rpm,
            allowed_models: key.allowed_models,
            status: newStatus
        });

        if (res.ok) {
            ClaudePulse.showToast(`Key ${newStatus === 'active' ? 'activated' : 'revoked'}`);
            loadKeys();
        }
    };

    window.deleteKey = async function(id) {
        if (!confirm('Permanently delete this gateway key? Any client using it will immediately get 401.')) return;
        const res = await ClaudePulse.apiRequest('/api/internal/keys/delete', 'POST', { id: id });
        if (res.ok) {
            ClaudePulse.showToast('Key deleted');
            loadKeys();
        }
    };

    window.formatTokens = function(num) {
        if (!num || isNaN(num)) return '0';
        if (num >= 1000000000) return (num / 1000000000).toFixed(2) + 'B';
        if (num >= 1000000) return (num / 1000000).toFixed(2) + 'M';
        if (num >= 1000) return (num / 1000).toFixed(1) + 'k';
        return Number(num).toLocaleString();
    };

    window.setTokenVal = function(inputId, val) {
        const input = document.getElementById(inputId);
        if (input) {
            input.value = val;
            input.focus();
        }
    };

    window.setUsdVal = function(inputId, val) {
        const input = document.getElementById(inputId);
        if (input) {
            input.value = Number(val).toFixed(2);
            input.focus();
        }
    };

    loadKeys();
});
</script>
