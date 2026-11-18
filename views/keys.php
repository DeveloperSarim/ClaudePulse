<?php
$pageTitle = 'Gateway API Keys';
$currentRoute = 'keys';
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Gateway API Keys</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Generate dedicated <code class="text-emerald-700 dark:text-emerald-400 font-mono">sk-cp-...</code> keys with hard spend caps for your Claude Code CLI, Cursor, and developer devices.</p>
    </div>

    <button onclick="ClaudePulse.openModal('modal-create-key')" class="inline-flex items-center space-x-2 px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition shadow-sm dark:bg-emerald-600 dark:hover:bg-emerald-700">
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
                    <th class="py-3 px-4 font-semibold">Key Name & Label</th>
                    <th class="py-3 px-4 font-semibold">Key Prefix</th>
                    <th class="py-3 px-4 font-semibold min-w-[170px]">Spend Cap ($ USD)</th>
                    <th class="py-3 px-4 font-semibold min-w-[170px]">Token Quota</th>
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
    <div class="bg-white border border-slate-200 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative dark:bg-[#0f172a] dark:border-slate-800">
        <div class="flex items-center justify-between mb-5">
            <div>
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Generate Gateway Key</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Issued for Claude Code CLI, IDE extensions, or custom chatbots.</p>
            </div>
            <button onclick="ClaudePulse.closeModal('modal-create-key')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg">&times;</button>
        </div>

        <form id="form-create-key" class="space-y-4">
            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Key Label / Name</label>
                <input type="text" name="name" required placeholder="e.g. MacBook Pro Claude Code" 
                       class="form-input">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Hard Spend Cap ($ USD)</label>
                    <input type="number" step="0.50" min="0" name="cap_usd" value="10.00" placeholder="0.00 = Unlimited"
                           class="form-input font-mono">
                    <p class="text-[10px] text-slate-500 mt-1">Stops API requests once reached.</p>
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Rate Limit (RPM)</label>
                    <input type="number" min="0" name="rate_limit_rpm" value="60" placeholder="0 = Unlimited"
                           class="form-input font-mono">
                    <p class="text-[10px] text-slate-500 mt-1">Max requests per minute.</p>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Token Quota Limit (Total Tokens)</label>
                <input type="number" step="100000" min="0" name="cap_tokens" id="create-cap-tokens" value="0" placeholder="0 = Unlimited Tokens"
                       class="form-input font-mono">
                <div class="mt-1.5 flex flex-wrap gap-1 items-center">
                    <span class="text-[10px] text-slate-400 mr-1">Quick Select:</span>
                    <button type="button" onclick="setTokenVal('create-cap-tokens', 0)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">Unlimited</button>
                    <button type="button" onclick="setTokenVal('create-cap-tokens', 1000000)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">1M</button>
                    <button type="button" onclick="setTokenVal('create-cap-tokens', 5000000)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">5M</button>
                    <button type="button" onclick="setTokenVal('create-cap-tokens', 10000000)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition font-bold text-emerald-600 dark:text-emerald-400">10M</button>
                    <button type="button" onclick="setTokenVal('create-cap-tokens', 25000000)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">25M</button>
                    <button type="button" onclick="setTokenVal('create-cap-tokens', 50000000)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">50M</button>
                    <button type="button" onclick="setTokenVal('create-cap-tokens', 100000000)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">100M</button>
                </div>
                <p class="text-[10px] text-slate-500 mt-1">Stops API requests once total tokens (input + output + cache) hit this cap.</p>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Allowed Models (Optional)</label>
                <input type="text" id="create-allowed-models" name="allowed_models" placeholder="Leave empty for all models, or click below to add"
                       class="form-input font-mono">
                <div class="mt-1.5 flex flex-wrap gap-1 items-center">
                    <span class="text-[10px] text-slate-400 mr-1">Quick Add:</span>
                    <button type="button" onclick="addModelChip('create-allowed-models', 'claude-5-2-fable')" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">+ claude-5-2-fable</button>
                    <button type="button" onclick="addModelChip('create-allowed-models', 'claude-opus-5-5')" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">+ claude-opus-5-5</button>
                    <button type="button" onclick="addModelChip('create-allowed-models', 'claude-sonnet-5')" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">+ claude-sonnet-5</button>
                    <button type="button" onclick="addModelChip('create-allowed-models', 'claude-haiku-5')" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">+ claude-haiku-5</button>
                    <button type="button" onclick="addModelChip('create-allowed-models', 'claude-sonnet-4-6')" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">+ claude-sonnet-4-6</button>
                    <button type="button" onclick="addModelChip('create-allowed-models', 'claude-3-7-sonnet')" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">+ claude-3-7-sonnet</button>
                </div>
            </div>

            <div class="pt-2 flex justify-end space-x-2">
                <button type="button" onclick="ClaudePulse.closeModal('modal-create-key')" class="px-4 py-2 rounded-xl bg-slate-100 hover:bg-slate-200 text-xs font-semibold text-slate-700 transition dark:bg-slate-800 dark:text-slate-300">Cancel</button>
                <button type="submit" class="px-4 py-2 rounded-xl bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition shadow-sm dark:bg-emerald-600 dark:hover:bg-emerald-700">Generate Key &rarr;</button>
            </div>
        </form>
    </div>
</div>

<!-- MODAL: Reveal Generated Key -->
<div id="modal-reveal-key" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/70 backdrop-blur-xs">
    <div class="bg-white border border-emerald-300 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative dark:bg-[#0f172a] dark:border-emerald-500/40">
        <div class="text-center mb-4">
            <div class="inline-flex p-3 rounded-2xl bg-emerald-50 text-emerald-600 border border-emerald-200 mb-3 dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/30">
                <svg class="w-6 h-6" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </div>
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Gateway Key Generated!</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Copy this key now. It will never be displayed in full again for security.</p>
        </div>

        <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 mb-4 dark:bg-slate-900 dark:border-slate-800">
            <div class="text-[10px] uppercase font-bold text-slate-500 mb-1">Your Full Gateway API Key</div>
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
            I Have Saved This Key
        </button>
    </div>
</div>

<!-- MODAL: Edit Gateway Key -->
<div id="modal-edit-key" class="fixed inset-0 z-50 hidden items-center justify-center p-4 bg-slate-900/60 backdrop-blur-xs">
    <div class="bg-white border border-slate-200 rounded-2xl p-6 max-w-lg w-full shadow-2xl relative dark:bg-[#0f172a] dark:border-slate-800">
        <div class="flex items-center justify-between mb-5">
            <h3 class="text-base font-bold text-slate-900 dark:text-white">Edit Gateway Key</h3>
            <button onclick="ClaudePulse.closeModal('modal-edit-key')" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg">&times;</button>
        </div>

        <form id="form-edit-key" class="space-y-4">
            <input type="hidden" name="id" id="edit-key-id">

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Key Name</label>
                <input type="text" name="name" id="edit-key-name" required
                       class="form-input">
            </div>

            <div class="grid grid-cols-2 gap-3">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Spend Cap ($ USD)</label>
                    <input type="number" step="0.50" min="0" name="cap_usd" id="edit-key-cap"
                           class="form-input font-mono">
                </div>
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Rate Limit (RPM)</label>
                    <input type="number" min="0" name="rate_limit_rpm" id="edit-key-rpm"
                           class="form-input font-mono">
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Token Quota Limit (Total Tokens)</label>
                <input type="number" step="100000" min="0" name="cap_tokens" id="edit-key-cap-tokens" value="0" placeholder="0 = Unlimited Tokens"
                       class="form-input font-mono">
                <div class="mt-1.5 flex flex-wrap gap-1 items-center">
                    <span class="text-[10px] text-slate-400 mr-1">Quick Select:</span>
                    <button type="button" onclick="setTokenVal('edit-key-cap-tokens', 0)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">Unlimited</button>
                    <button type="button" onclick="setTokenVal('edit-key-cap-tokens', 1000000)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">1M</button>
                    <button type="button" onclick="setTokenVal('edit-key-cap-tokens', 5000000)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">5M</button>
                    <button type="button" onclick="setTokenVal('edit-key-cap-tokens', 10000000)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition font-bold text-emerald-600 dark:text-emerald-400">10M</button>
                    <button type="button" onclick="setTokenVal('edit-key-cap-tokens', 25000000)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">25M</button>
                    <button type="button" onclick="setTokenVal('edit-key-cap-tokens', 50000000)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">50M</button>
                    <button type="button" onclick="setTokenVal('edit-key-cap-tokens', 100000000)" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">100M</button>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Allowed Models</label>
                <input type="text" name="allowed_models" id="edit-key-models" placeholder="Leave empty for all models, or click below to add"
                       class="form-input font-mono">
                <div class="mt-1.5 flex flex-wrap gap-1 items-center">
                    <span class="text-[10px] text-slate-400 mr-1">Quick Add:</span>
                    <button type="button" onclick="addModelChip('edit-key-models', 'claude-5-2-fable')" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">+ claude-5-2-fable</button>
                    <button type="button" onclick="addModelChip('edit-key-models', 'claude-opus-5-5')" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">+ claude-opus-5-5</button>
                    <button type="button" onclick="addModelChip('edit-key-models', 'claude-sonnet-5')" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">+ claude-sonnet-5</button>
                    <button type="button" onclick="addModelChip('edit-key-models', 'claude-haiku-5')" class="text-[10px] font-mono px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 text-slate-600 dark:text-slate-300 hover:bg-emerald-50 dark:hover:bg-emerald-500/10 hover:text-emerald-600 border border-slate-200 dark:border-slate-700 transition">+ claude-haiku-5</button>
                </div>
            </div>

            <div>
                <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Status</label>
                <select name="status" id="edit-key-status" class="form-input">
                    <option value="active">Active</option>
                    <option value="revoked">Revoked</option>
                </select>
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
            tbody.innerHTML = `<tr><td colspan="8" class="py-12 text-center text-slate-400">No gateway keys found. Click "+ Generate Gateway Key" above.</td></tr>`;
            return;
        }

        tbody.innerHTML = keysData.map(k => {
            const spent = Number(k.spent_usd || 0);
            const cap = Number(k.cap_usd || 0);
            const pct = cap > 0 ? Math.min(100, Math.round((spent / cap) * 100)) : 0;
            const barColor = pct >= 100 ? 'bg-rose-500' : pct >= 80 ? 'bg-amber-500' : 'bg-emerald-500';
            const capText = cap > 0 ? `$${spent.toFixed(2)} / $${cap.toFixed(2)} (${pct}%)` : `$${spent.toFixed(4)} (Unlimited)`;

            // Token Quota calculations
            const usedTok = Number(k.used_tokens || 0);
            const capTok = Number(k.cap_tokens || 0);
            const tokPct = capTok > 0 ? Math.min(100, Math.round((usedTok / capTok) * 100)) : 0;
            const tokBarColor = tokPct >= 100 ? 'bg-rose-500' : tokPct >= 80 ? 'bg-amber-500' : 'bg-emerald-500';
            const tokText = capTok > 0 
                ? `${formatTokens(usedTok)} / ${formatTokens(capTok)} (${tokPct}%)` 
                : `${formatTokens(usedTok)} (Unlimited)`;

            const statusClass = k.status === 'active' ? 'badge-emerald' : 'badge-rose';

            return `
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                    <td class="py-3 px-4 font-semibold text-slate-900 dark:text-white">
                        ${k.name}
                    </td>
                    <td class="py-3 px-4">
                        <span class="inline-flex items-center space-x-1 font-mono text-[11px] text-slate-700 bg-slate-100 px-2 py-0.5 rounded border border-slate-200 dark:bg-slate-800 dark:border-slate-700 dark:text-slate-300">
                            <span>${k.api_key_prefix}</span>
                        </span>
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex items-center justify-between text-[11px] mb-1">
                            <span class="font-mono text-slate-700 dark:text-slate-300">${capText}</span>
                        </div>
                        ${cap > 0 ? `
                            <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden dark:bg-slate-700">
                                <div class="${barColor} h-1.5 rounded-full transition-all duration-500" style="width: ${pct}%"></div>
                            </div>
                        ` : ''}
                    </td>
                    <td class="py-3 px-4">
                        <div class="flex items-center justify-between text-[11px] mb-1">
                            <span class="font-mono text-slate-700 dark:text-slate-300">${tokText}</span>
                        </div>
                        ${capTok > 0 ? `
                            <div class="w-full bg-slate-200 rounded-full h-1.5 overflow-hidden dark:bg-slate-700">
                                <div class="${tokBarColor} h-1.5 rounded-full transition-all duration-500" style="width: ${tokPct}%"></div>
                            </div>
                        ` : ''}
                    </td>
                    <td class="py-3 px-4">
                        <span class="badge ${statusClass}">
                            ${k.status}
                        </span>
                    </td>
                    <td class="py-3 px-4 text-right font-mono text-slate-700 dark:text-slate-300">
                        ${(k.total_requests || 0).toLocaleString()}
                    </td>
                    <td class="py-3 px-4 text-[11px] text-slate-500">
                        ${k.last_used_at ? k.last_used_at.split(' ')[0] : 'Never'}
                    </td>
                    <td class="py-3 px-4 text-right space-x-2 whitespace-nowrap">
                        <a href="/verify?key=${k.api_key_prefix}" target="_blank" class="text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 text-xs font-medium">Verify</a>
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
        document.getElementById('edit-key-cap').value = key.cap_usd;
        document.getElementById('edit-key-cap-tokens').value = key.cap_tokens || 0;
        document.getElementById('edit-key-rpm').value = key.rate_limit_rpm;
        document.getElementById('edit-key-models').value = key.allowed_models || '';
        document.getElementById('edit-key-status').value = key.status;

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

    window.addModelChip = function(inputId, modelName) {
        const input = document.getElementById(inputId);
        if (!input) return;
        const current = input.value.trim();
        if (!current) {
            input.value = modelName;
        } else {
            const parts = current.split(',').map(s => s.trim()).filter(Boolean);
            if (!parts.includes(modelName)) {
                parts.push(modelName);
                input.value = parts.join(',');
            }
        }
        input.focus();
    };

    loadKeys();
});
</script>
