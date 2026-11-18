<?php
$pageTitle = 'Request Logs';
$currentRoute = 'logs';
use ClaudePulse\Database;

$db = Database::getConnection();
$gatewayKeys = $db->query("SELECT id, name, api_key_prefix FROM gateway_keys ORDER BY name ASC")->fetchAll();
?>

<!-- Header -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-6">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Request Telemetry & Inspector</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Audit log with token breakdowns, prompt cache read metrics, and latency details.</p>
    </div>

    <div class="flex items-center space-x-2">
        <a href="/api/internal/logs/export?format=csv" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-xs font-semibold text-slate-700 shadow-2xs dark:bg-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition">
            <svg class="w-3.5 h-3.5 text-slate-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M4 16v1a3 3 0 003 3h10a3 3 0 003-3v-1m-4-4l-4 4m0 0l-4-4m4 4V4"/></svg>
            <span>Export CSV</span>
        </a>
        <a href="/api/internal/logs/export?format=json" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg bg-white border border-slate-300 hover:bg-slate-50 text-xs font-semibold text-slate-700 shadow-2xs dark:bg-slate-900 dark:border-slate-700 dark:text-slate-300 dark:hover:bg-slate-800 transition">
            <span>Export JSON</span>
        </a>
    </div>
</div>

<!-- Filters Bar Card -->
<div class="card-clean p-4 mb-6">
    <div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-3">
        
        <div>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Search</label>
            <input type="text" id="filter-search" placeholder="Model, IP, endpoint..." 
                   class="form-input">
        </div>

        <div>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Status Code</label>
            <select id="filter-status" class="form-input">
                <option value="">All Statuses</option>
                <option value="200">200 OK</option>
                <option value="429">429 Rate Limit / Spend Cap</option>
                <option value="401">401 Unauthorized</option>
                <option value="500">500 Server Error</option>
            </select>
        </div>

        <div>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Client Tag</label>
            <select id="filter-client" class="form-input">
                <option value="">All Clients</option>
                <option value="Claude Code">Claude Code CLI</option>
                <option value="Claude IDE">Claude IDE</option>
                <option value="API Client">Generic API Client</option>
                <option value="Python SDK">Python SDK</option>
                <option value="Node.js SDK">Node.js SDK</option>
            </select>
        </div>

        <div>
            <label class="block text-[11px] font-semibold text-slate-600 dark:text-slate-400 mb-1">Gateway Key</label>
            <select id="filter-key" class="form-input">
                <option value="">All Keys</option>
                <?php foreach ($gatewayKeys as $k): ?>
                    <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['name']) ?></option>
                <?php endforeach; ?>
            </select>
        </div>

        <div class="flex items-end space-x-2">
            <button id="btn-apply-filters" class="flex-1 py-2 px-3 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition shadow-2xs dark:bg-emerald-600 dark:hover:bg-emerald-700">
                Filter
            </button>
            <button id="btn-reset-filters" class="py-2 px-3 rounded-lg bg-slate-100 hover:bg-slate-200 text-slate-700 font-semibold text-xs transition dark:bg-slate-800 dark:text-slate-300">
                Reset
            </button>
        </div>

    </div>
</div>

<!-- Logs Table Card -->
<div class="card-clean overflow-hidden mb-4">
    <div class="overflow-x-auto">
        <table class="w-full text-left text-xs">
            <thead>
                <tr class="border-b border-slate-200 bg-slate-50 text-slate-600 dark:border-slate-800 dark:bg-slate-900/50 dark:text-slate-400">
                    <th class="py-3 px-4 font-semibold">Status</th>
                    <th class="py-3 px-4 font-semibold">Time (UTC)</th>
                    <th class="py-3 px-4 font-semibold">Client</th>
                    <th class="py-3 px-4 font-semibold">Model</th>
                    <th class="py-3 px-4 font-semibold">Tokens (In / Read / Out)</th>
                    <th class="py-3 px-4 font-semibold text-right">Cost</th>
                    <th class="py-3 px-4 font-semibold text-right">Latency</th>
                    <th class="py-3 px-4 font-semibold text-right">Inspect</th>
                </tr>
            </thead>
            <tbody id="logs-tbody" class="divide-y divide-slate-100 dark:divide-slate-800/60">
                <tr>
                    <td colspan="8" class="py-12 text-center text-slate-400">Loading request logs...</td>
                </tr>
            </tbody>
        </table>
    </div>
</div>

<!-- Pagination Controls -->
<div class="flex items-center justify-between text-xs text-slate-500">
    <div>
        Showing <span id="pag-showing-start" class="font-semibold text-slate-800 dark:text-slate-200">0</span> to <span id="pag-showing-end" class="font-semibold text-slate-800 dark:text-slate-200">0</span> of <span id="pag-total" class="font-semibold text-slate-800 dark:text-slate-200">0</span> requests
    </div>
    <div class="flex items-center space-x-2">
        <button id="pag-prev" class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300 font-medium">
            &larr; Prev
        </button>
        <span id="pag-page-info" class="font-mono text-slate-600 dark:text-slate-400 px-1">Page 1</span>
        <button id="pag-next" class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 text-slate-700 hover:bg-slate-50 disabled:opacity-40 disabled:cursor-not-allowed dark:bg-slate-900 dark:border-slate-800 dark:text-slate-300 font-medium">
            Next &rarr;
        </button>
    </div>
</div>

<!-- SLIDE-OVER DRAWER: Request Details -->
<div id="drawer-container" class="fixed inset-0 z-50 overflow-hidden drawer-closed pointer-events-none">
    <div id="drawer-backdrop" class="drawer-backdrop absolute inset-0 bg-slate-900/60 backdrop-blur-2xs"></div>

    <div class="absolute inset-y-0 right-0 max-w-full flex pl-10">
        <div class="drawer-panel w-screen max-w-md bg-white border-l border-slate-200 p-6 flex flex-col justify-between dark:bg-[#0f172a] dark:border-slate-800 shadow-2xl overflow-y-auto">
            
            <div>
                <div class="flex items-center justify-between border-b border-slate-200 pb-4 mb-5 dark:border-slate-800">
                    <div class="flex items-center space-x-2">
                        <span id="drawer-status-badge" class="badge"></span>
                        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Request Telemetry</h3>
                    </div>
                    <button id="drawer-close-btn" class="text-slate-400 hover:text-slate-600 dark:hover:text-white text-lg">&times;</button>
                </div>

                <div class="space-y-4 text-xs">
                    
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900 dark:border-slate-800">
                        <div class="text-slate-500 mb-1">Target Endpoint</div>
                        <div id="drawer-endpoint" class="font-mono text-emerald-700 dark:text-emerald-400 font-semibold"></div>
                    </div>

                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900 dark:border-slate-800">
                            <div class="text-slate-500 mb-1">Model</div>
                            <div id="drawer-model" class="font-mono font-semibold text-slate-900 dark:text-white truncate"></div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900 dark:border-slate-800">
                            <div class="text-slate-500 mb-1">Client Tag</div>
                            <div id="drawer-client-tag" class="font-semibold text-indigo-700 dark:text-indigo-400"></div>
                        </div>
                    </div>

                    <!-- Token Telemetry Box -->
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900 dark:border-slate-800 space-y-2">
                        <div class="font-bold text-slate-800 dark:text-slate-200 mb-2">Token Usage & Prompt Caching</div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Fresh Input Tokens:</span>
                            <span id="drawer-tok-in" class="font-mono text-slate-900 dark:text-white font-medium"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Cache Creation (Write):</span>
                            <span id="drawer-tok-write" class="font-mono text-amber-700 dark:text-amber-400 font-medium"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Cache Hits (Read 90% off):</span>
                            <span id="drawer-tok-read" class="font-mono text-emerald-700 dark:text-emerald-400 font-bold"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Output Tokens:</span>
                            <span id="drawer-tok-out" class="font-mono text-indigo-700 dark:text-cyan-400 font-medium"></span>
                        </div>
                        <div class="border-t border-slate-200 pt-2 flex justify-between font-bold dark:border-slate-800">
                            <span class="text-slate-700 dark:text-slate-300">Total Tokens:</span>
                            <span id="drawer-tok-total" class="font-mono text-slate-900 dark:text-white"></span>
                        </div>
                    </div>

                    <!-- Cost & Savings -->
                    <div class="p-4 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900 dark:border-slate-800 space-y-2">
                        <div class="font-bold text-slate-800 dark:text-slate-200 mb-2">Cost Accounting</div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Actual Cost ($ USD):</span>
                            <span id="drawer-cost" class="font-mono font-bold text-emerald-700 dark:text-emerald-400 text-sm"></span>
                        </div>
                        <div class="flex justify-between">
                            <span class="text-slate-500">Cache Savings ($ USD):</span>
                            <span id="drawer-savings" class="font-mono font-semibold text-emerald-600 dark:text-emerald-500"></span>
                        </div>
                    </div>

                    <!-- Latency Breakdown -->
                    <div class="grid grid-cols-2 gap-3">
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900 dark:border-slate-800">
                            <div class="text-slate-500 mb-1">Time to First Token</div>
                            <div id="drawer-ttft" class="font-mono text-slate-900 dark:text-white font-medium"></div>
                        </div>
                        <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900 dark:border-slate-800">
                            <div class="text-slate-500 mb-1">Total Request Time</div>
                            <div id="drawer-latency" class="font-mono text-slate-900 dark:text-white font-medium"></div>
                        </div>
                    </div>

                    <!-- Client Environment -->
                    <div class="p-3 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900 dark:border-slate-800 space-y-1.5">
                        <div class="text-slate-500">Client IP: <span id="drawer-ip" class="font-mono text-slate-900 dark:text-white"></span></div>
                        <div class="text-slate-500 truncate">Gateway Key: <span id="drawer-key-name" class="text-slate-900 dark:text-white font-medium"></span></div>
                        <div class="text-slate-500">Streaming: <span id="drawer-streaming" class="font-semibold text-emerald-700 dark:text-emerald-400"></span></div>
                        <div class="text-slate-500 text-[10px] break-all">User Agent: <span id="drawer-ua" class="font-mono text-slate-600 dark:text-slate-400"></span></div>
                    </div>

                    <!-- Error Trace (if any) -->
                    <div id="drawer-error-container" class="hidden p-3 rounded-xl bg-rose-50 border border-rose-200 text-rose-700 dark:bg-rose-500/10 dark:border-rose-500/30 dark:text-rose-300">
                        <div class="font-bold text-rose-800 dark:text-rose-400 mb-1">Error Trace</div>
                        <div id="drawer-error-text" class="font-mono text-[11px] break-words"></div>
                    </div>

                </div>
            </div>

            <div class="pt-4 border-t border-slate-200 dark:border-slate-800 text-center">
                <span id="drawer-timestamp" class="text-[11px] text-slate-400 font-mono"></span>
            </div>

        </div>
    </div>
</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    let currentPage = 1;
    let totalPages = 1;
    let currentLogs = [];

    const tbody = document.getElementById('logs-tbody');

    async function fetchLogs() {
        const search = document.getElementById('filter-search').value;
        const status = document.getElementById('filter-status').value;
        const client = document.getElementById('filter-client').value;
        const keyId = document.getElementById('filter-key').value;

        const params = new URLSearchParams({
            page: currentPage,
            per_page: 25,
            search: search,
            status_code: status,
            client_tag: client,
            gateway_key_id: keyId
        });

        const res = await ClaudePulse.apiRequest(`/api/internal/logs?${params.toString()}`);
        if (res.ok && res.data) {
            currentLogs = res.data.logs || [];
            totalPages = res.data.total_pages || 1;
            renderLogsTable(res.data);
        }
    }

    function renderLogsTable(data) {
        if (!currentLogs.length) {
            tbody.innerHTML = `<tr><td colspan="8" class="py-12 text-center text-slate-400">No requests found matching criteria.</td></tr>`;
            updatePagination(0, 0, 0);
            return;
        }

        tbody.innerHTML = currentLogs.map(r => {
            const statusClass = r.status_code === 200 ? 'badge-emerald'
                              : r.status_code === 429 ? 'badge-amber'
                              : 'badge-rose';

            const clientTagClass = r.client_tag === 'Claude Code' ? 'badge-amber font-semibold' : 'badge-indigo';
            const cacheReadBadge = r.cache_read_input_tokens > 0 
                ? `<span class="text-emerald-700 dark:text-emerald-400 font-bold" title="${r.cache_read_input_tokens} cache hits">${r.cache_read_input_tokens}</span>` 
                : `<span class="text-slate-400">0</span>`;

            return `
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition cursor-pointer" onclick="openDrawer(${r.id})">
                    <td class="py-3 px-4">
                        <span class="badge ${statusClass}">
                            ${r.status_code}
                        </span>
                    </td>
                    <td class="py-3 px-4 font-mono text-[11px] text-slate-500 whitespace-nowrap">
                        ${r.created_at}
                    </td>
                    <td class="py-3 px-4">
                        <span class="badge ${clientTagClass}">
                            ${r.client_tag}
                        </span>
                    </td>
                    <td class="py-3 px-4 font-mono text-[11px] text-slate-900 dark:text-white font-medium truncate max-w-[150px]" title="${r.model || ''}">
                        ${r.model || 'unknown'}
                    </td>
                    <td class="py-3 px-4 font-mono text-[11px] text-slate-700 dark:text-slate-300 whitespace-nowrap">
                        ${(r.input_tokens || 0).toLocaleString()} / ${cacheReadBadge} / ${(r.output_tokens || 0).toLocaleString()}
                    </td>
                    <td class="py-3 px-4 font-mono text-[11px] text-right font-semibold text-emerald-700 dark:text-emerald-400">
                        $${Number(r.cost_usd || 0).toFixed(4)}
                    </td>
                    <td class="py-3 px-4 font-mono text-[11px] text-right text-slate-500">
                        ${r.latency_ms || 0}ms
                    </td>
                    <td class="py-3 px-4 text-right">
                        <button onclick="event.stopPropagation(); openDrawer(${r.id})" class="text-slate-500 hover:text-slate-900 dark:hover:text-white text-xs font-semibold transition">
                            Details &rarr;
                        </button>
                    </td>
                </tr>
            `;
        }).join('');

        const start = ((data.page - 1) * data.per_page) + 1;
        const end = Math.min(data.total, data.page * data.per_page);
        updatePagination(start, end, data.total);
    }

    function updatePagination(start, end, total) {
        document.getElementById('pag-showing-start').innerText = start;
        document.getElementById('pag-showing-end').innerText = end;
        document.getElementById('pag-total').innerText = total;
        document.getElementById('pag-page-info').innerText = `Page ${currentPage} of ${totalPages || 1}`;

        document.getElementById('pag-prev').disabled = (currentPage <= 1);
        document.getElementById('pag-next').disabled = (currentPage >= totalPages);
    }

    document.getElementById('pag-prev').addEventListener('click', () => {
        if (currentPage > 1) {
            currentPage--;
            fetchLogs();
        }
    });

    document.getElementById('pag-next').addEventListener('click', () => {
        if (currentPage < totalPages) {
            currentPage++;
            fetchLogs();
        }
    });

    document.getElementById('btn-apply-filters').addEventListener('click', () => {
        currentPage = 1;
        fetchLogs();
    });

    document.getElementById('btn-reset-filters').addEventListener('click', () => {
        document.getElementById('filter-search').value = '';
        document.getElementById('filter-status').value = '';
        document.getElementById('filter-client').value = '';
        document.getElementById('filter-key').value = '';
        currentPage = 1;
        fetchLogs();
    });

    const drawerContainer = document.getElementById('drawer-container');
    const drawerBackdrop = document.getElementById('drawer-backdrop');
    const drawerCloseBtn = document.getElementById('drawer-close-btn');

    window.openDrawer = function(id) {
        const r = currentLogs.find(log => log.id === id);
        if (!r) return;

        const statusBadge = document.getElementById('drawer-status-badge');
        statusBadge.innerText = r.status_code;
        statusBadge.className = r.status_code === 200 ? 'badge badge-emerald' : 'badge badge-rose';

        document.getElementById('drawer-endpoint').innerText = r.endpoint;
        document.getElementById('drawer-model').innerText = r.model || 'Not specified';
        document.getElementById('drawer-client-tag').innerText = r.client_tag;

        document.getElementById('drawer-tok-in').innerText = (r.input_tokens || 0).toLocaleString();
        document.getElementById('drawer-tok-write').innerText = (r.cache_creation_input_tokens || 0).toLocaleString();
        document.getElementById('drawer-tok-read').innerText = (r.cache_read_input_tokens || 0).toLocaleString();
        document.getElementById('drawer-tok-out').innerText = (r.output_tokens || 0).toLocaleString();
        
        const totalTok = ((r.input_tokens || 0) + (r.output_tokens || 0) + (r.cache_creation_input_tokens || 0) + (r.cache_read_input_tokens || 0));
        document.getElementById('drawer-tok-total').innerText = totalTok.toLocaleString();

        document.getElementById('drawer-cost').innerText = '$' + Number(r.cost_usd || 0).toFixed(6);
        document.getElementById('drawer-savings').innerText = '$' + Number(r.saved_usd || 0).toFixed(6);

        document.getElementById('drawer-ttft').innerText = `${r.ttft_ms || 0} ms`;
        document.getElementById('drawer-latency').innerText = `${r.latency_ms || 0} ms`;

        document.getElementById('drawer-ip').innerText = r.client_ip || '127.0.0.1';
        document.getElementById('drawer-key-name').innerText = r.gateway_key_name ? `${r.gateway_key_name} (${r.api_key_prefix})` : 'Unknown Key';
        document.getElementById('drawer-streaming').innerText = r.is_streaming ? 'Yes (SSE)' : 'No (JSON)';
        document.getElementById('drawer-ua').innerText = r.user_agent || 'None';
        document.getElementById('drawer-timestamp').innerText = `Recorded at ${r.created_at} UTC`;

        const errContainer = document.getElementById('drawer-error-container');
        if (r.error_message) {
            errContainer.classList.remove('hidden');
            document.getElementById('drawer-error-text').innerText = r.error_message;
        } else {
            errContainer.classList.add('hidden');
        }

        drawerContainer.classList.remove('drawer-closed');
        drawerContainer.classList.remove('pointer-events-none');
    };

    function closeDrawer() {
        drawerContainer.classList.add('drawer-closed');
        drawerContainer.classList.add('pointer-events-none');
    }

    drawerBackdrop.addEventListener('click', closeDrawer);
    drawerCloseBtn.addEventListener('click', closeDrawer);

    fetchLogs();
});
</script>
