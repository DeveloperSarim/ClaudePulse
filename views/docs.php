<?php
$pageTitle = 'Connect & Tutorials';
$currentRoute = 'docs';
use ClaudePulse\Database;

$db = Database::getConnection();
$gatewayKeys = $db->query("SELECT id, name, api_key_prefix FROM gateway_keys WHERE status = 'active' ORDER BY name ASC")->fetchAll();
$currentHost = baseUrl();
?>

<!-- Header -->
<div class="mb-8">
    <h1 class="text-xl font-bold tracking-tight text-slate-900 dark:text-white">Connect & Integration Guide</h1>
    <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">Plug Claude Code CLI, Cursor, and custom applications directly into ClaudePulse to optimize costs and leverage promotional credits.</p>
</div>

<!-- Dynamic Host & Key Selector -->
<div class="card-clean p-5 mb-8">
    <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
        <div>
            <label class="block text-xs font-semibold text-slate-800 dark:text-slate-200 mb-1.5 flex items-center space-x-1.5">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                <span>Gateway Base URL</span>
            </label>
            <input type="text" id="live-base-url" value="<?= htmlspecialchars($currentHost) ?>" 
                   class="form-input font-mono text-emerald-700 dark:text-emerald-400">
            <p class="text-[11px] text-slate-500 mt-1">Clean routing with zero <code class="font-mono">.php</code> in endpoints.</p>
        </div>

        <div>
            <label class="block text-xs font-semibold text-slate-800 dark:text-slate-200 mb-1.5 flex items-center space-x-1.5">
                <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                <span>Select Active Gateway Key</span>
            </label>
            <select id="select-active-key" class="form-input font-mono">
                <?php if (empty($gatewayKeys)): ?>
                    <option value="sk-cp-your-gateway-key">No active keys (sk-cp-your-gateway-key)</option>
                <?php else: ?>
                    <?php foreach ($gatewayKeys as $k): ?>
                        <option value="<?= $k['api_key_prefix'] ?>"><?= htmlspecialchars($k['name']) ?> (<?= $k['api_key_prefix'] ?>)</option>
                    <?php endforeach; ?>
                <?php endif; ?>
            </select>
            <p class="text-[11px] text-slate-500 mt-1">Pick a key to dynamically populate the tutorial snippets below.</p>
        </div>
    </div>
</div>

<!-- Tab Navigation -->
<div class="border-b border-slate-200 dark:border-slate-800 mb-6">
    <nav class="flex space-x-6 text-sm">
        <button class="doc-tab-btn pb-3 border-b-2 border-emerald-600 text-emerald-700 dark:border-emerald-400 dark:text-emerald-400 font-bold" data-target="tab-claude-code">
            1. Claude Code CLI & IDE
        </button>
        <button class="doc-tab-btn pb-3 border-b-2 border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white font-medium" data-target="tab-sdks">
            2. Python, TypeScript & cURL
        </button>
        <button class="doc-tab-btn pb-3 border-b-2 border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white font-medium" data-target="tab-status-monitor">
            3. Uptime & Status Monitoring
        </button>
    </nav>
</div>

<!-- TAB 1: Claude Code CLI & IDE -->
<div id="tab-claude-code" class="doc-tab-content space-y-6">
    
    <!-- Explainer Banner -->
    <div class="p-5 rounded-xl bg-amber-50/60 border border-amber-200/80 dark:bg-amber-500/10 dark:border-amber-500/20">
        <div class="flex items-start space-x-3">
            <span class="p-2 rounded-lg bg-amber-100 text-amber-800 flex-shrink-0 dark:bg-amber-500/20 dark:text-amber-400">
                <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
            </span>
            <div>
                <h3 class="text-xs font-bold text-amber-900 dark:text-amber-300">Why Route Claude Code Through ClaudePulse?</h3>
                <p class="text-xs text-amber-800/90 dark:text-amber-200/90 mt-1 leading-relaxed">
                    By default, Claude Code expects an interactive Claude Pro/Team subscription. By setting <code class="text-amber-950 dark:text-amber-300 font-mono font-semibold">ANTHROPIC_BASE_URL</code> to your ClaudePulse gateway, Claude Code routes all traffic to Anthropic's official <strong>Messages API (<code class="font-mono">/v1/messages</code>)</strong>. This allows <strong>promotional API credits, prepaid balances, and developer keys</strong> to work out of the box with zero subscription required, while our Auto-Prompt Caching saves you up to 90% on repeated system inputs!
                </p>
            </div>
        </div>
    </div>

    <!-- macOS & Linux Terminal -->
    <div class="card-clean p-5">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-xs font-bold text-slate-900 dark:text-white">macOS & Linux Terminal (Bash / Zsh)</h4>
            <button onclick="copySnippet('cmd-mac-session', this)" class="text-xs text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 font-semibold">Copy Command</button>
        </div>
        <div class="code-box p-3 font-mono text-xs text-emerald-400 overflow-x-auto" id="cmd-mac-session">
export ANTHROPIC_BASE_URL="<span class="var-base-url"><?= $currentHost ?></span>"
export ANTHROPIC_API_KEY="<span class="var-api-key">sk-cp-your-gateway-key</span>"
# Optional: Set model to Claude 5 Sonnet, Fable 5.2, or 3.7 Sonnet
export ANTHROPIC_MODEL="claude-sonnet-5"
claude
        </div>
    </div>

    <!-- Persistent Shell Configuration -->
    <div class="card-clean p-5">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white">Make Permanent in ~/.zshrc or ~/.bashrc</h4>
                <p class="text-[11px] text-slate-500 mt-0.5">Persists across terminal sessions.</p>
            </div>
            <button onclick="copySnippet('cmd-mac-persist', this)" class="text-xs text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 font-semibold">Copy Snippet</button>
        </div>
        <div class="code-box p-3 font-mono text-xs text-slate-300 overflow-x-auto" id="cmd-mac-persist">
echo 'export ANTHROPIC_BASE_URL="<span class="var-base-url"><?= $currentHost ?></span>"' >> ~/.zshrc
echo 'export ANTHROPIC_API_KEY="<span class="var-api-key">sk-cp-your-gateway-key</span>"' >> ~/.zshrc
source ~/.zshrc
        </div>
    </div>

    <!-- Windows PowerShell -->
    <div class="card-clean p-5">
        <div class="flex items-center justify-between mb-3">
            <h4 class="text-xs font-bold text-slate-900 dark:text-white">Windows PowerShell</h4>
            <button onclick="copySnippet('cmd-win-ps', this)" class="text-xs text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 font-semibold">Copy PowerShell</button>
        </div>
        <div class="code-box p-3 font-mono text-xs text-cyan-300 overflow-x-auto" id="cmd-win-ps">
$env:ANTHROPIC_BASE_URL = "<span class="var-base-url"><?= $currentHost ?></span>"
$env:ANTHROPIC_API_KEY = "<span class="var-api-key">sk-cp-your-gateway-key</span>"
claude
        </div>
    </div>

    <!-- IDE Settings: ~/.claude/settings.json -->
    <div class="card-clean p-5">
        <div class="flex items-center justify-between mb-3">
            <div>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white">Claude Code Config (~/.claude/settings.json)</h4>
                <p class="text-[11px] text-slate-500 mt-0.5">For VS Code extension, Antigravity IDE, or global Claude Code daemon.</p>
            </div>
            <button onclick="copySnippet('code-claude-json', this)" class="text-xs text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 font-semibold">Copy JSON</button>
        </div>
        <pre class="code-box p-3 font-mono text-xs text-slate-200 overflow-x-auto" id="code-claude-json">{
  "apiKey": "<span class="var-api-key">sk-cp-your-gateway-key</span>",
  "baseURL": "<span class="var-base-url"><?= $currentHost ?></span>"
}</pre>
    </div>

</div>

<!-- TAB 2: Python, TypeScript & Custom Apps -->
<div id="tab-sdks" class="doc-tab-content hidden space-y-6">
    
    <!-- Python SDK -->
    <div class="card-clean p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center space-x-2">
                <span class="badge badge-indigo">Python</span>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white">Official Anthropic Python SDK</h4>
            </div>
            <button onclick="copySnippet('code-py', this)" class="text-xs text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 font-semibold">Copy Code</button>
        </div>
        <pre class="code-box p-3 font-mono text-xs text-slate-200 overflow-x-auto" id="code-py">import os
from anthropic import Anthropic

client = Anthropic(
    base_url="<span class="var-base-url"><?= $currentHost ?></span>",
    api_key="<span class="var-api-key">sk-cp-your-gateway-key</span>"
)

# Real-time SSE streaming supported
with client.messages.stream(
    model="claude-3-7-sonnet-20250219",
    max_tokens=1024,
    messages=[{"role": "user", "content": "Explain prompt caching in one sentence."}]
) as stream:
    for text in stream.text_stream:
        print(text, end="", flush=True)</pre>
    </div>

    <!-- Node.js / TypeScript SDK -->
    <div class="card-clean p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center space-x-2">
                <span class="badge badge-amber">TypeScript</span>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white">@anthropic-ai/sdk (Node.js & Next.js)</h4>
            </div>
            <button onclick="copySnippet('code-ts', this)" class="text-xs text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 font-semibold">Copy Code</button>
        </div>
        <pre class="code-box p-3 font-mono text-xs text-slate-200 overflow-x-auto" id="code-ts">import Anthropic from '@anthropic-ai/sdk';

const anthropic = new Anthropic({
  baseURL: '<span class="var-base-url"><?= $currentHost ?></span>',
  apiKey: '<span class="var-api-key">sk-cp-your-gateway-key</span>',
});

const stream = await anthropic.messages.create({
  model: 'claude-3-7-sonnet-20250219',
  max_tokens: 1024,
  messages: [{ role: 'user', content: 'Say hello from ClaudePulse!' }],
  stream: true,
});

for await (const messageChunk of stream) {
  if (messageChunk.type === 'content_block_delta') {
    process.stdout.write((messageChunk.delta as any).text || '');
  }
}</pre>
    </div>

    <!-- Raw cURL with Streaming & Tool Calling -->
    <div class="card-clean p-5">
        <div class="flex items-center justify-between mb-3">
            <div class="flex items-center space-x-2">
                <span class="badge badge-emerald">cURL</span>
                <h4 class="text-xs font-bold text-slate-900 dark:text-white">Raw Streaming Terminal cURL</h4>
            </div>
            <button onclick="copySnippet('code-curl', this)" class="text-xs text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 dark:hover:text-emerald-300 font-semibold">Copy cURL</button>
        </div>
        <pre class="code-box p-3 font-mono text-xs text-emerald-400 overflow-x-auto" id="code-curl">curl -N -X POST "<span class="var-base-url"><?= $currentHost ?></span>/v1/messages" \
  -H "x-api-key: <span class="var-api-key">sk-cp-your-gateway-key</span>" \
  -H "anthropic-version: 2023-06-01" \
  -H "content-type: application/json" \
  -d '{
    "model": "claude-3-7-sonnet-20250219",
    "max_tokens": 1024,
    "stream": true,
    "messages": [
      {"role": "user", "content": "Tell me a 2-line joke about caching."}
    ]
  }'</pre>
    </div>

</div>

<!-- TAB 3: Uptime & Status Monitoring -->
<div id="tab-status-monitor" class="doc-tab-content hidden space-y-6">
    
    <div class="card-clean p-5">
        <h4 class="text-xs font-bold text-slate-900 dark:text-white mb-2">Connecting External Uptime Monitors</h4>
        <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed mb-4">
            ClaudePulse exposes an unauthenticated health probe at <code class="text-emerald-700 dark:text-emerald-400 font-mono">/status</code>. When requested by a browser, it renders a visual status page. When requested by automated uptime monitors or with an <code class="font-mono">Accept: application/json</code> header, it returns clean JSON with component health indicators.
        </p>

        <div class="space-y-3">
            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900 dark:border-slate-800">
                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">UptimeRobot / BetterStack Probe URL:</div>
                <div class="flex items-center justify-between">
                    <span class="font-mono text-xs text-emerald-700 dark:text-emerald-400 font-semibold"><?= $currentHost ?>/status</span>
                    <button onclick="ClaudePulse.copyText('<?= $currentHost ?>/status', this)" class="text-xs text-emerald-700 hover:text-emerald-800 dark:text-emerald-400 font-semibold">Copy URL</button>
                </div>
            </div>

            <div class="p-3.5 rounded-xl bg-slate-50 border border-slate-200 dark:bg-slate-900 dark:border-slate-800">
                <div class="text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1">Expected HTTP Response:</div>
                <span class="badge badge-emerald">200 OK</span>
            </div>
        </div>
    </div>

    <div class="card-clean p-5">
        <h4 class="text-xs font-bold text-slate-900 dark:text-white mb-2">JSON Probe Output Format</h4>
        <pre class="code-box p-3 font-mono text-xs text-slate-300 overflow-x-auto">{
  "status": "operational",
  "gateway": "ClaudePulse",
  "version": "1.0.0",
  "timestamp": "2026-11-17T12:00:00Z",
  "uptime_percentage": 99.98,
  "avg_latency_ms": 145,
  "components": {
    "gateway_engine": "operational",
    "database": "operational",
    "upstream_anthropic": "operational"
  }
}</pre>
    </div>

</div>

<script>
document.addEventListener('DOMContentLoaded', () => {
    const liveBaseUrlInput = document.getElementById('live-base-url');
    const selectKey = document.getElementById('select-active-key');

    function updateVariables() {
        const url = liveBaseUrlInput.value.trim().replace(/\/+$/, '');
        const key = selectKey.value;

        document.querySelectorAll('.var-base-url').forEach(el => el.innerText = url);
        document.querySelectorAll('.var-api-key').forEach(el => el.innerText = key);
    }

    liveBaseUrlInput.addEventListener('input', updateVariables);
    selectKey.addEventListener('change', updateVariables);

    const tabButtons = document.querySelectorAll('.doc-tab-btn');
    const tabContents = document.querySelectorAll('.doc-tab-content');

    tabButtons.forEach(btn => {
        btn.addEventListener('click', () => {
            tabButtons.forEach(b => {
                b.classList.remove('border-emerald-600', 'text-emerald-700', 'dark:border-emerald-400', 'dark:text-emerald-400', 'font-bold');
                b.classList.add('border-transparent', 'text-slate-500', 'dark:text-slate-400', 'font-medium');
            });
            btn.classList.add('border-emerald-600', 'text-emerald-700', 'dark:border-emerald-400', 'dark:text-emerald-400', 'font-bold');
            btn.classList.remove('border-transparent', 'text-slate-500', 'dark:text-slate-400', 'font-medium');

            const targetId = btn.getAttribute('data-target');
            tabContents.forEach(tc => {
                if (tc.id === targetId) {
                    tc.classList.remove('hidden');
                } else {
                    tc.classList.add('hidden');
                }
            });
        });
    });

    window.copySnippet = function(id, btn) {
        const el = document.getElementById(id);
        if (!el) return;
        ClaudePulse.copyText(el.innerText, btn);
    };

    updateVariables();
});
</script>
