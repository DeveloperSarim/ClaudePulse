<?php
$pageTitle = 'Overview Dashboard';
$currentRoute = 'dashboard';
use ClaudePulse\Database;

$db = Database::getConnection();
$gatewayKeys = $db->query("SELECT id, name, api_key_prefix FROM gateway_keys ORDER BY name ASC")->fetchAll();
?>

<!-- Header & Timeframe Filter Bar -->
<div class="flex flex-col sm:flex-row items-start sm:items-center justify-between gap-4 mb-8">
    <div>
        <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Analytics Overview</h1>
        <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Real-time spend, token telemetry, and prompt cache optimization metrics.</p>
    </div>

    <div class="flex flex-wrap items-center gap-2.5">
        <!-- Gateway Key Filter -->
        <select id="filter-gateway-key" class="px-3 py-1.5 rounded-lg bg-white border border-slate-300 text-xs font-medium text-slate-700 focus:outline-none focus:border-emerald-600 dark:bg-slate-900 dark:border-slate-700 dark:text-slate-200">
            <option value="">All Gateway Keys</option>
            <?php foreach ($gatewayKeys as $k): ?>
                <option value="<?= $k['id'] ?>"><?= htmlspecialchars($k['name']) ?> (<?= $k['api_key_prefix'] ?>)</option>
            <?php endforeach; ?>
        </select>

        <!-- Timeframe Segmented Control -->
        <div class="inline-flex p-0.5 rounded-lg bg-slate-200/80 border border-slate-200 dark:bg-slate-900 dark:border-slate-800" id="timeframe-group">
            <button data-timeframe="24h" class="timeframe-btn px-3 py-1 rounded-md text-xs font-semibold bg-white text-slate-900 shadow-sm transition dark:bg-slate-800 dark:text-white">24h</button>
            <button data-timeframe="7d" class="timeframe-btn px-3 py-1 rounded-md text-xs font-medium text-slate-600 hover:text-slate-900 transition dark:text-slate-400 dark:hover:text-white">7d</button>
            <button data-timeframe="30d" class="timeframe-btn px-3 py-1 rounded-md text-xs font-medium text-slate-600 hover:text-slate-900 transition dark:text-slate-400 dark:hover:text-white">30d</button>
            <button data-timeframe="all" class="timeframe-btn px-3 py-1 rounded-md text-xs font-medium text-slate-600 hover:text-slate-900 transition dark:text-slate-400 dark:hover:text-white">All</button>
        </div>
    </div>
</div>

<!-- 5 Top KPI Cards -->
<div class="grid grid-cols-1 sm:grid-cols-2 lg:grid-cols-5 gap-4 mb-8">
    
    <!-- KPI 1: Total Spend -->
    <div class="card-clean p-5 relative overflow-hidden">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Spend</span>
            <span class="p-1 rounded-md bg-emerald-50 text-emerald-600 dark:bg-emerald-500/10 dark:text-emerald-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8c-1.657 0-3 .895-3 2s1.343 2 3 2 3 .895 3 2-1.343 2-3 2m0-8c1.11 0 2.08.402 2.599 1M12 8V7m0 1v8m0 0v1m0-1c-1.11 0-2.08-.402-2.599-1M21 12a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
        </div>
        <div class="mt-3">
            <div id="kpi-spend" class="text-2xl font-bold tracking-tight text-slate-900 font-mono dark:text-white">$0.0000</div>
            <p class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">Direct upstream billing</p>
        </div>
    </div>

    <!-- KPI 2: Total Tokens -->
    <div class="card-clean p-5 relative overflow-hidden">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Tokens</span>
            <span class="p-1 rounded-md bg-indigo-50 text-indigo-600 dark:bg-indigo-500/10 dark:text-indigo-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M7 7h.01M7 3h5c.512 0 1.024.195 1.414.586l7 7a2 2 0 010 2.828l-7 7a2 2 0 01-2.828 0l-7-7A1.994 1.994 0 013 12V7a4 4 0 014-4z" />
                </svg>
            </span>
        </div>
        <div class="mt-3">
            <div id="kpi-tokens" class="text-2xl font-bold tracking-tight text-slate-900 font-mono dark:text-white">0</div>
            <p id="kpi-tokens-sub" class="text-[11px] text-slate-500 dark:text-slate-400 mt-1 truncate">In: 0 &bull; Out: 0</p>
        </div>
    </div>

    <!-- KPI 3: Prompt Cache Savings -->
    <div class="card-clean p-5 relative overflow-hidden">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Cache Savings</span>
            <span class="p-1 rounded-md bg-amber-50 text-amber-600 dark:bg-amber-500/10 dark:text-amber-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z" />
                </svg>
            </span>
        </div>
        <div class="mt-3">
            <div id="kpi-saved" class="text-2xl font-bold tracking-tight text-emerald-600 font-mono dark:text-emerald-400">$0.0000</div>
            <p id="kpi-saved-pct" class="text-[11px] text-emerald-700 dark:text-emerald-400 font-medium mt-1">0% cost reduction</p>
        </div>
    </div>

    <!-- KPI 4: Average Latency -->
    <div class="card-clean p-5 relative overflow-hidden">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Avg Latency</span>
            <span class="p-1 rounded-md bg-blue-50 text-blue-600 dark:bg-blue-500/10 dark:text-blue-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 8v4l3 3m6-3a9 9 0 11-18 0 9 9 0 0118 0z" />
                </svg>
            </span>
        </div>
        <div class="mt-3">
            <div id="kpi-latency" class="text-2xl font-bold tracking-tight text-slate-900 font-mono dark:text-white">0 ms</div>
            <p id="kpi-ttft" class="text-[11px] text-slate-500 dark:text-slate-400 mt-1">TTFT: 0 ms</p>
        </div>
    </div>

    <!-- KPI 5: Total Requests -->
    <div class="card-clean p-5 relative overflow-hidden">
        <div class="flex items-center justify-between">
            <span class="text-xs font-medium text-slate-500 dark:text-slate-400">Total Requests</span>
            <span class="p-1 rounded-md bg-purple-50 text-purple-600 dark:bg-purple-500/10 dark:text-purple-400">
                <svg class="w-4 h-4" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M8 9l3 3-3 3m5 0h3M5 20h14a2 2 0 002-2V6a2 2 0 00-2-2H5a2 2 0 00-2 2v12a2 2 0 002 2z" />
                </svg>
            </span>
        </div>
        <div class="mt-3">
            <div id="kpi-requests" class="text-2xl font-bold tracking-tight text-slate-900 font-mono dark:text-white">0</div>
            <p id="kpi-success-rate" class="text-[11px] text-emerald-600 dark:text-emerald-400 font-medium mt-1">100% success rate</p>
        </div>
    </div>

</div>

<!-- Charts Section -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6 mb-8">
    
    <!-- Timeline Line/Area Chart -->
    <div class="lg:col-span-2 card-clean p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Token Volume & Spend Activity</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Hourly & daily request traffic routed through ClaudePulse.</p>
            </div>
            <div class="flex items-center space-x-3 text-xs">
                <span class="flex items-center space-x-1.5"><span class="w-2.5 h-2.5 rounded-full bg-emerald-600 dark:bg-emerald-500"></span><span class="text-slate-600 dark:text-slate-400">Tokens</span></span>
                <span class="flex items-center space-x-1.5"><span class="w-2.5 h-2.5 rounded-full bg-indigo-600 dark:bg-cyan-400"></span><span class="text-slate-600 dark:text-slate-400">Spend ($)</span></span>
            </div>
        </div>
        <div class="h-72 w-full">
            <canvas id="chart-timeline"></canvas>
        </div>
    </div>

    <!-- Models Distribution Doughnut -->
    <div class="card-clean p-6 flex flex-col justify-between">
        <div>
            <h3 class="text-sm font-bold text-slate-900 dark:text-white">Model Distribution</h3>
            <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Spend share across Opus, Sonnet, and Haiku models.</p>
            <div class="h-56 relative flex items-center justify-center">
                <canvas id="chart-models"></canvas>
            </div>
        </div>
        <div id="models-legend" class="mt-4 space-y-1.5 text-xs text-slate-600 dark:text-slate-400">
            <!-- Dynamically injected via JS -->
        </div>
    </div>

</div>

<!-- Prompt Cache Efficiency & Recent Activity -->
<div class="grid grid-cols-1 lg:grid-cols-3 gap-6">

    <!-- Cache Ratio Doughnut -->
    <div class="card-clean p-6">
        <h3 class="text-sm font-bold text-slate-900 dark:text-white">Prompt Caching Efficiency</h3>
        <p class="text-xs text-slate-500 dark:text-slate-400 mb-4">Cached read tokens (90% off) vs. fresh input token creation.</p>
        
        <div class="h-48 relative flex items-center justify-center">
            <canvas id="chart-cache"></canvas>
        </div>

        <div class="mt-4 grid grid-cols-2 gap-2 text-center text-xs">
            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800">
                <div class="text-slate-500">Cache Reads</div>
                <div id="cache-read-val" class="text-sm font-bold text-emerald-600 dark:text-emerald-400 font-mono mt-0.5">0</div>
            </div>
            <div class="p-2.5 rounded-lg bg-slate-50 border border-slate-200 dark:bg-slate-900/60 dark:border-slate-800">
                <div class="text-slate-500">Fresh Input</div>
                <div id="fresh-input-val" class="text-sm font-bold text-slate-700 dark:text-slate-300 font-mono mt-0.5">0</div>
            </div>
        </div>
    </div>

    <!-- Recent Requests Snippet -->
    <div class="lg:col-span-2 card-clean p-6">
        <div class="flex items-center justify-between mb-4">
            <div>
                <h3 class="text-sm font-bold text-slate-900 dark:text-white">Recent Requests</h3>
                <p class="text-xs text-slate-500 dark:text-slate-400">Live stream of intercepted Claude Code and API traffic.</p>
            </div>
            <a href="/logs" class="text-xs text-emerald-600 hover:text-emerald-700 dark:text-emerald-400 dark:hover:text-emerald-300 font-semibold inline-flex items-center space-x-1">
                <span>View Full Telemetry</span>
                <span>&rarr;</span>
            </a>
        </div>

        <div class="overflow-x-auto">
            <table class="w-full text-left text-xs">
                <thead>
                    <tr class="border-b border-slate-200 text-slate-500 dark:border-slate-800 dark:text-slate-400">
                        <th class="pb-2.5 font-semibold">Status</th>
                        <th class="pb-2.5 font-semibold">Client</th>
                        <th class="pb-2.5 font-semibold">Model</th>
                        <th class="pb-2.5 font-semibold text-right">Tokens</th>
                        <th class="pb-2.5 font-semibold text-right">Cost</th>
                        <th class="pb-2.5 font-semibold text-right">Latency</th>
                    </tr>
                </thead>
                <tbody id="recent-requests-tbody" class="divide-y divide-slate-100 dark:divide-slate-800/60">
                    <tr>
                        <td colspan="6" class="py-6 text-center text-slate-400">Loading live telemetry...</td>
                    </tr>
                </tbody>
            </table>
        </div>
    </div>

</div>

<!-- Dashboard Controller Script -->
<script>
document.addEventListener('DOMContentLoaded', () => {
    let timelineChart = null;
    let modelsChart = null;
    let cacheChart = null;

    let activeTimeframe = '24h';
    let activeKeyId = '';

    const filterKeySelect = document.getElementById('filter-gateway-key');
    const timeframeButtons = document.querySelectorAll('.timeframe-btn');

    timeframeButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            timeframeButtons.forEach(b => {
                b.classList.remove('bg-white', 'text-slate-900', 'shadow-sm', 'dark:bg-slate-800', 'dark:text-white');
                b.classList.add('text-slate-600', 'dark:text-slate-400');
            });
            btn.classList.add('bg-white', 'text-slate-900', 'shadow-sm', 'dark:bg-slate-800', 'dark:text-white');
            btn.classList.remove('text-slate-600', 'dark:text-slate-400');

            activeTimeframe = btn.getAttribute('data-timeframe');
            loadDashboardData();
        });
    });

    if (filterKeySelect) {
        filterKeySelect.addEventListener('change', (e) => {
            activeKeyId = e.target.value;
            loadDashboardData();
        });
    }

    async function loadDashboardData() {
        const url = `/api/internal/stats?timeframe=${activeTimeframe}&gateway_key_id=${activeKeyId}`;
        const res = await ClaudePulse.apiRequest(url);

        if (!res.ok || !res.data) {
            return;
        }

        const data = res.data;
        const kpis = data.kpis;

        document.getElementById('kpi-spend').innerText = '$' + Number(kpis.total_spend_usd).toFixed(4);
        
        const totalTokens = (kpis.total_input_tokens + kpis.total_output_tokens + kpis.total_cache_read_tokens + kpis.total_cache_write_tokens);
        document.getElementById('kpi-tokens').innerText = totalTokens.toLocaleString();
        document.getElementById('kpi-tokens-sub').innerText = `In: ${kpis.total_input_tokens.toLocaleString()} • Out: ${kpis.total_output_tokens.toLocaleString()}`;

        document.getElementById('kpi-saved').innerText = '$' + Number(kpis.total_saved_usd).toFixed(4);
        document.getElementById('kpi-saved-pct').innerText = `${kpis.savings_percentage}% cost reduction`;

        document.getElementById('kpi-latency').innerText = `${kpis.avg_latency_ms} ms`;
        document.getElementById('kpi-ttft').innerText = `TTFT: ${kpis.avg_ttft_ms} ms`;

        document.getElementById('kpi-requests').innerText = kpis.total_requests.toLocaleString();
        document.getElementById('kpi-success-rate').innerText = `${kpis.success_rate}% success rate`;

        document.getElementById('cache-read-val').innerText = kpis.total_cache_read_tokens.toLocaleString();
        document.getElementById('fresh-input-val').innerText = kpis.total_input_tokens.toLocaleString();

        renderTimelineChart(data.timeline || []);
        renderModelsChart(data.models || []);
        renderCacheChart(kpis.total_cache_read_tokens, kpis.total_input_tokens, kpis.total_cache_write_tokens);
        renderRecentTable(data.recent || []);
    }

    function renderTimelineChart(timeline) {
        const ctx = document.getElementById('chart-timeline');
        if (!ctx) return;

        const labels = timeline.map(t => t.time_bucket.split(' ').pop());
        const tokens = timeline.map(t => t.total_tokens);
        const spend = timeline.map(t => t.spend);

        if (timelineChart) timelineChart.destroy();

        const isDark = document.documentElement.classList.contains('dark');
        const gridColor = isDark ? 'rgba(255, 255, 255, 0.05)' : 'rgba(0, 0, 0, 0.05)';
        const textColor = isDark ? '#94a3b8' : '#64748b';

        timelineChart = new Chart(ctx, {
            type: 'line',
            data: {
                labels: labels.length ? labels : ['No Traffic'],
                datasets: [
                    {
                        label: 'Tokens',
                        data: tokens.length ? tokens : [0],
                        borderColor: isDark ? '#10b981' : '#059669',
                        backgroundColor: isDark ? 'rgba(16, 185, 129, 0.08)' : 'rgba(5, 150, 105, 0.08)',
                        fill: true,
                        tension: 0.3,
                        yAxisID: 'y'
                    },
                    {
                        label: 'Spend ($)',
                        data: spend.length ? spend : [0],
                        borderColor: isDark ? '#22d3ee' : '#4f46e5',
                        backgroundColor: 'transparent',
                        borderDash: [4, 4],
                        tension: 0.3,
                        yAxisID: 'y1'
                    }
                ]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                interaction: { mode: 'index', intersect: false },
                plugins: {
                    legend: { display: false },
                    tooltip: {
                        backgroundColor: isDark ? '#0f172a' : '#ffffff',
                        titleColor: isDark ? '#f8fafc' : '#0f172a',
                        bodyColor: isDark ? '#cbd5e1' : '#334155',
                        borderColor: isDark ? '#334155' : '#e2e8f0',
                        borderWidth: 1
                    }
                },
                scales: {
                    x: { grid: { color: gridColor }, ticks: { color: textColor, font: { size: 10 } } },
                    y: { position: 'left', grid: { color: gridColor }, ticks: { color: textColor, font: { size: 10 } } },
                    y1: { position: 'right', grid: { drawOnChartArea: false }, ticks: { color: isDark ? '#22d3ee' : '#4f46e5', font: { size: 10 }, callback: v => '$' + v } }
                }
            }
        });
    }

    function renderModelsChart(models) {
        const ctx = document.getElementById('chart-models');
        if (!ctx) return;

        if (modelsChart) modelsChart.destroy();

        const labels = models.length ? models.map(m => m.model_name) : ['No Models'];
        const values = models.length ? models.map(m => m.spend > 0 ? m.spend : 1) : [1];
        const colors = ['#059669', '#4f46e5', '#d97706', '#db2777', '#0891b2', '#7c3aed'];

        modelsChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: labels,
                datasets: [{
                    data: values,
                    backgroundColor: colors.slice(0, labels.length),
                    borderWidth: 0,
                    hoverOffset: 4
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                cutout: '72%',
                plugins: { legend: { display: false } }
            }
        });

        const legDiv = document.getElementById('models-legend');
        if (legDiv) {
            legDiv.innerHTML = models.map((m, i) => `
                <div class="flex items-center justify-between">
                    <span class="flex items-center space-x-2 truncate">
                        <span class="w-2 h-2 rounded-full" style="background-color: ${colors[i % colors.length]}"></span>
                        <span class="truncate font-mono text-[11px]">${m.model_name}</span>
                    </span>
                    <span class="font-mono text-[11px] font-semibold text-slate-700 dark:text-slate-300">$${Number(m.spend).toFixed(4)}</span>
                </div>
            `).join('') || '<div class="text-center text-slate-400">No model usage yet</div>';
        }
    }

    function renderCacheChart(reads, fresh, writes) {
        const ctx = document.getElementById('chart-cache');
        if (!ctx) return;

        if (cacheChart) cacheChart.destroy();

        const readVal = reads || 0;
        const freshVal = (fresh + writes) || 1;

        cacheChart = new Chart(ctx, {
            type: 'doughnut',
            data: {
                labels: ['Cached Reads (90% Off)', 'Fresh Input Tokens'],
                datasets: [{
                    data: [readVal, freshVal],
                    backgroundColor: ['#059669', '#cbd5e1'],
                    borderWidth: 0,
                    cutout: '75%'
                }]
            },
            options: {
                responsive: true,
                maintainAspectRatio: false,
                plugins: { legend: { display: false } }
            }
        });
    }

    function renderRecentTable(requests) {
        const tbody = document.getElementById('recent-requests-tbody');
        if (!tbody) return;

        if (!requests.length) {
            tbody.innerHTML = `<tr><td colspan="6" class="py-8 text-center text-slate-400">No API requests recorded yet. Connect Claude Code in the "Connect & Docs" tab.</td></tr>`;
            return;
        }

        tbody.innerHTML = requests.map(r => {
            const statusClass = r.status_code === 200 ? 'badge-emerald'
                              : r.status_code === 429 ? 'badge-amber'
                              : 'badge-rose';

            const clientTagClass = r.client_tag === 'Claude Code' ? 'badge-amber font-semibold' : 'badge-indigo';
            const totalTok = (r.input_tokens + r.output_tokens);

            return `
                <tr class="hover:bg-slate-50 dark:hover:bg-slate-800/40 transition">
                    <td class="py-2.5">
                        <span class="badge ${statusClass}">
                            ${r.status_code}
                        </span>
                    </td>
                    <td class="py-2.5">
                        <span class="badge ${clientTagClass}">
                            ${r.client_tag}
                        </span>
                    </td>
                    <td class="py-2.5 font-mono text-[11px] text-slate-800 dark:text-slate-200 truncate max-w-[130px]" title="${r.model || ''}">
                        ${r.model || 'unknown'}
                    </td>
                    <td class="py-2.5 font-mono text-[11px] text-right text-slate-700 dark:text-slate-300">
                        ${totalTok.toLocaleString()}
                    </td>
                    <td class="py-2.5 font-mono text-[11px] text-right font-semibold text-emerald-600 dark:text-emerald-400">
                        $${Number(r.cost_usd).toFixed(4)}
                    </td>
                    <td class="py-2.5 font-mono text-[11px] text-right text-slate-500">
                        ${r.latency_ms}ms
                    </td>
                </tr>
            `;
        }).join('');
    }

    loadDashboardData();
});
</script>
