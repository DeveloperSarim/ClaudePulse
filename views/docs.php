<?php
declare(strict_types=1);

/**
 * ClaudePulse Customer Integration & Documentation Hub
 * Public Route: /docs, /connect, /tutorial
 */

use ClaudePulse\Database;
use ClaudePulse\Pricing;
use ClaudePulse\Auth;

$db = Database::getConnection();
$isAdmin = Auth::check();

// If admin is logged in, optionally fetch active key options
$gatewayKeys = [];
if ($isAdmin) {
    try {
        $stmtKeys = $db->query("SELECT id, name, api_key_prefix FROM gateway_keys WHERE status = 'active' ORDER BY name ASC");
        $gatewayKeys = $stmtKeys ? $stmtKeys->fetchAll() : [];
    } catch (\Exception $e) {}
}

$currentHost = baseUrl();
$models = Pricing::getKnownModels();
?>
<!DOCTYPE html>
<html lang="en" class="light">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="color-scheme" content="light dark">
    <title>Connect & Integration Guide &mdash; ClaudePulse</title>
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
    <link rel="stylesheet" href="<?= asset('assets/css/style.css') ?>">
</head>
<body class="bg-slate-50 text-slate-900 min-h-screen py-8 px-4 sm:px-6 dark:bg-[#090d16] dark:text-slate-100 transition-colors duration-150">

    <div class="max-w-5xl mx-auto space-y-6">

        <!-- Header -->
        <header class="flex items-center justify-between pb-4 border-b border-slate-200 dark:border-slate-800">
            <a href="/" class="flex items-center space-x-2.5 group">
                <div class="w-8 h-8 rounded-lg bg-slate-900 text-white flex items-center justify-center shadow-sm group-hover:bg-emerald-600 transition dark:bg-emerald-500 dark:text-slate-950">
                    <svg class="w-4 h-4" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2.4" stroke-linecap="round" stroke-linejoin="round">
                        <polyline points="22 12 18 12 15 21 9 3 6 12 2 12"></polyline>
                    </svg>
                </div>
                <div>
                    <h1 class="font-bold text-base tracking-tight text-slate-900 dark:text-white">Claude<span class="text-emerald-600 dark:text-emerald-400">Pulse</span></h1>
                    <p class="text-[11px] text-slate-500 dark:text-slate-400">Customer Documentation & Tutorials</p>
                </div>
            </a>

            <div class="flex items-center space-x-3">
                <a href="/verify" class="inline-flex items-center space-x-1.5 px-3 py-1.5 rounded-lg text-xs font-semibold bg-white border border-slate-200 hover:bg-slate-50 text-slate-700 transition dark:bg-slate-800 dark:border-slate-700 dark:text-slate-200 dark:hover:bg-slate-700">
                    <svg class="w-3.5 h-3.5 text-emerald-500" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    <span>Check Key Balance</span>
                </a>
                <a href="/status" target="_blank" class="inline-flex items-center space-x-1.5 px-2.5 py-1.5 rounded-lg text-xs font-medium bg-emerald-50 text-emerald-700 border border-emerald-200 hover:bg-emerald-100 transition dark:bg-emerald-500/10 dark:text-emerald-400 dark:border-emerald-500/20">
                    <span class="w-1.5 h-1.5 rounded-full bg-emerald-500 pulse-live"></span>
                    <span class="hidden sm:inline">System Status</span>
                </a>
                <?php if ($isAdmin): ?>
                <a href="/dashboard" class="px-3 py-1.5 rounded-lg bg-slate-900 hover:bg-slate-800 text-white font-semibold text-xs transition dark:bg-emerald-600 dark:hover:bg-emerald-700">
                    Admin Dashboard
                </a>
                <?php endif; ?>
                <button id="theme-toggle-btn" type="button" class="p-2 rounded-lg text-slate-500 hover:text-slate-900 hover:bg-slate-100 dark:text-slate-400 dark:hover:text-white dark:hover:bg-slate-800 transition" title="Toggle Theme">
                    <svg class="w-4 h-4 hidden dark:block" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M12 3v1m0 16v1m9-9h-1M4 12H3m15.364 6.364l-.707-.707M6.343 6.343l-.707-.707m12.728 0l-.707.707M6.343 17.657l-.707.707M16 12a4 4 0 11-8 0 4 4 0 018 0z" />
                    </svg>
                    <svg class="w-4 h-4 block dark:hidden" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                        <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M20.354 15.354A9 9 0 018.646 3.646 9.003 9.003 0 0012 21a9.003 9.003 0 008.354-5.646z" />
                    </svg>
                </button>
            </div>
        </header>

        <!-- Dynamic Personalization Card -->
        <div class="card-clean p-6">
            <div class="mb-4">
                <h2 class="text-lg font-bold text-slate-900 dark:text-white">Personalize Your Connect Commands</h2>
                <p class="text-xs text-slate-500 dark:text-slate-400 mt-1">
                    Enter your assigned <code class="font-mono text-emerald-600 dark:text-emerald-400 font-bold">sk-cp-...</code> gateway key below. All terminal commands, code snippets, and SDK scripts will update dynamically.
                </p>
            </div>

            <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1 flex items-center space-x-1.5">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13.828 10.172a4 4 0 00-5.656 0l-4 4a4 4 0 105.656 5.656l1.102-1.101m-.758-4.899a4 4 0 005.656 0l4-4a4 4 0 00-5.656-5.656l-1.1 1.1"/></svg>
                        <span>ClaudePulse Base URL</span>
                    </label>
                    <input type="text" id="live-base-url" value="<?= htmlspecialchars($currentHost) ?>" 
                           class="form-input font-mono text-xs text-emerald-700 dark:text-emerald-400">
                    <p class="text-[10px] text-slate-500 mt-1">Direct endpoint with zero <code class="font-mono">.php</code> in routes.</p>
                </div>

                <div>
                    <label class="block text-xs font-semibold text-slate-700 dark:text-slate-300 mb-1 flex items-center space-x-1.5">
                        <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M15 7a2 2 0 012 2m4 0a6 6 0 01-7.743 5.743L11 17H9v2H7v2H4a1 1 0 01-1-1v-2.586a1 1 0 01.293-.707l5.964-5.964A6 6 0 1121 9z"/></svg>
                        <span>Your Assigned Gateway API Key</span>
                    </label>
                    <?php if ($isAdmin && !empty($gatewayKeys)): ?>
                    <div class="flex gap-2">
                        <input type="text" id="live-api-key" placeholder="sk-cp-your-gateway-key-here" 
                               value="sk-cp-your-gateway-key"
                               class="form-input font-mono text-xs flex-1">
                        <select id="select-active-key" class="form-input font-mono text-xs w-36">
                            <option value="">Select Key...</option>
                            <?php foreach ($gatewayKeys as $k): ?>
                                <option value="<?= htmlspecialchars($k['api_key_prefix']) ?>"><?= htmlspecialchars($k['name']) ?></option>
                            <?php endforeach; ?>
                        </select>
                    </div>
                    <?php else: ?>
                    <input type="text" id="live-api-key" placeholder="Paste sk-cp-... key here" 
                           value="<?= htmlspecialchars($_GET['key'] ?? 'sk-cp-your-gateway-key') ?>"
                           class="form-input font-mono text-xs">
                    <?php endif; ?>
                    <p class="text-[10px] text-slate-500 mt-1">Changes are live in memory. Your key is never sent anywhere.</p>
                </div>
            </div>
        </div>

        <!-- Tab Navigation -->
        <div class="border-b border-slate-200 dark:border-slate-800">
            <nav class="flex space-x-4 sm:space-x-8 text-xs sm:text-sm overflow-x-auto">
                <button class="doc-tab-btn pb-3 border-b-2 border-emerald-600 text-emerald-700 dark:border-emerald-400 dark:text-emerald-400 font-bold whitespace-nowrap" data-target="tab-claude-desktop">
                    1. Claude Desktop App
                </button>
                <button class="doc-tab-btn pb-3 border-b-2 border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white font-semibold whitespace-nowrap" data-target="tab-claude-code">
                    2. Claude Code CLI
                </button>
                <button class="doc-tab-btn pb-3 border-b-2 border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white font-semibold whitespace-nowrap" data-target="tab-cursor">
                    3. Cursor IDE
                </button>
                <button class="doc-tab-btn pb-3 border-b-2 border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white font-semibold whitespace-nowrap" data-target="tab-sdks">
                    4. Python & TypeScript
                </button>
                <button class="doc-tab-btn pb-3 border-b-2 border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white font-semibold whitespace-nowrap" data-target="tab-curl">
                    5. cURL & REST API
                </button>
                <button class="doc-tab-btn pb-3 border-b-2 border-transparent text-slate-500 hover:text-slate-900 dark:text-slate-400 dark:hover:text-white font-semibold whitespace-nowrap" data-target="tab-balance">
                    6. Balance & Limits (/verify)
                </button>
            </nav>
        </div>

        <!-- TAB: Claude Desktop App -->
        <div id="tab-claude-desktop" class="doc-tab-content space-y-6">
            <div class="p-4 rounded-xl bg-emerald-50/70 border border-emerald-200 dark:bg-emerald-500/10 dark:border-emerald-500/20 text-xs text-emerald-900 dark:text-emerald-200">
                <div class="font-bold flex items-center space-x-1.5 mb-1">
                    <svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9.75 17L9 20l-1 1h8l-1-1-.75-3M3 13h18M5 17h14a2 2 0 002-2V5a2 2 0 00-2-2H5a2 2 0 00-2 2v10a2 2 0 002 2z"/></svg>
                    <span>Claude Desktop App &mdash; 100% Automatic Model Discovery</span>
                </div>
                <p class="leading-relaxed">
                    Connect official Claude Desktop (Windows or Mac) directly to ClaudePulse. Models auto-populate in your chat dropdown based on your API key's permissions with zero manual model typing!
                </p>
            </div>

            <div class="card-clean p-6 space-y-5">
                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center space-x-2">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 inline-flex items-center justify-center text-xs font-bold">1</span>
                    <span>Open Claude Desktop Settings</span>
                </h3>
                <p class="text-xs text-slate-600 dark:text-slate-400">
                    In Claude Desktop, click your profile / initials in the bottom-left (or press <kbd class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono text-[11px] border border-slate-300 dark:border-slate-700">Ctrl + ,</kbd> / <kbd class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono text-[11px] border border-slate-300 dark:border-slate-700">Cmd + ,</kbd>), then select <b>Developer</b> or <b>Custom Models / Third-Party Provider</b>.
                </p>

                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center space-x-2 pt-2">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 inline-flex items-center justify-center text-xs font-bold">2</span>
                    <span>Enter Connection Credentials</span>
                </h3>
                <div class="grid grid-cols-1 md:grid-cols-2 gap-4">
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div class="text-[11px] font-bold text-slate-500 uppercase mb-1">Provider URL (Base URL)</div>
                        <div class="font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400 select-all var-base-url"><?= $currentHost ?></div>
                    </div>
                    <div class="p-3.5 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800">
                        <div class="text-[11px] font-bold text-slate-500 uppercase mb-1">API Key</div>
                        <div class="font-mono text-xs font-bold text-emerald-600 dark:text-emerald-400 select-all var-api-key">sk-cp-your-gateway-key</div>
                    </div>
                </div>

                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center space-x-2 pt-2">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 inline-flex items-center justify-center text-xs font-bold">3</span>
                    <span>Enable Automatic Model Discovery</span>
                </h3>
                <ul class="text-xs space-y-2 text-slate-700 dark:text-slate-300 list-disc list-inside">
                    <li>Toggle <b>"Model discovery"</b> to <b>ON</b>.</li>
                    <li><strong class="text-amber-600 dark:text-amber-400">Important:</strong> Leave the <b>"Model list"</b> text box <b>completely empty</b>. (If you type model names manually here, Claude Desktop overrides and hides auto-discovered models).</li>
                    <li>Click <b>"Test model discovery"</b> &mdash; it will return <span class="text-emerald-600 dark:text-emerald-400 font-bold">&check; Success</span> and automatically discover your permitted models!</li>
                </ul>

                <h3 class="text-sm font-bold text-slate-900 dark:text-white flex items-center space-x-2 pt-2">
                    <span class="w-6 h-6 rounded-full bg-emerald-100 dark:bg-emerald-500/20 text-emerald-700 dark:text-emerald-400 inline-flex items-center justify-center text-xs font-bold">4</span>
                    <span>Start Chatting</span>
                </h3>
                <p class="text-xs text-slate-600 dark:text-slate-400">
                    Close Settings. In the Claude Desktop chat bar, click the model selector dropdown in the bottom right corner &mdash; your allowed models (e.g. <b>Claude Sonnet 5.5</b>, <b>Claude Haiku 4.5</b>, <b>Claude 3.7 Sonnet</b>) are now automatically listed and ready to use!
                </p>
            </div>
        </div>

        <!-- TAB 2: Claude Code CLI -->
        <div id="tab-claude-code" class="doc-tab-content hidden space-y-6">
            
            <div class="p-4 rounded-xl bg-amber-50/70 border border-amber-200 dark:bg-amber-500/10 dark:border-amber-500/20 text-xs text-amber-900 dark:text-amber-200">
                <div class="font-bold flex items-center space-x-1.5 mb-1">
                    <svg class="w-4 h-4 text-amber-600 dark:text-amber-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M13 10V3L4 14h7v7l9-11h-7z"/></svg>
                    <span>Claude Code Terminal Setup</span>
                </div>
                <p class="leading-relaxed">
                    Setting <code class="font-mono font-bold">ANTHROPIC_BASE_URL</code> redirects Claude Code to your gateway. Auto-Prompt Caching saves up to 90% on repetitive tool and system tokens, while your Quota protects against runaway spend.
                </p>
            </div>

            <!-- macOS / Linux Terminal -->
            <div class="card-clean p-5">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">macOS & Linux Terminal (One-Time Session)</h4>
                    <button onclick="copySnippet('cmd-mac-session', this)" class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">Copy Command</button>
                </div>
                <div class="code-box p-3 font-mono text-xs text-emerald-400 overflow-x-auto" id="cmd-mac-session">
export ANTHROPIC_BASE_URL="<span class="var-base-url"><?= $currentHost ?></span>"
export ANTHROPIC_API_KEY="<span class="var-api-key">sk-cp-your-gateway-key</span>"
claude</div>
            </div>

            <!-- Permanent Configuration -->
            <div class="card-clean p-5">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">Permanent Setup (Append to ~/.zshrc or ~/.bashrc)</h4>
                    <button onclick="copySnippet('cmd-mac-perm', this)" class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">Copy Command</button>
                </div>
                <div class="code-box p-3 font-mono text-xs text-emerald-400 overflow-x-auto" id="cmd-mac-perm">
echo 'export ANTHROPIC_BASE_URL="<span class="var-base-url"><?= $currentHost ?></span>"' >> ~/.zshrc
echo 'export ANTHROPIC_API_KEY="<span class="var-api-key">sk-cp-your-gateway-key</span>"' >> ~/.zshrc
source ~/.zshrc</div>
            </div>

            <!-- Windows PowerShell -->
            <div class="card-clean p-5">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">Windows PowerShell</h4>
                    <button onclick="copySnippet('cmd-win-ps', this)" class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">Copy Command</button>
                </div>
                <div class="code-box p-3 font-mono text-xs text-emerald-400 overflow-x-auto" id="cmd-win-ps">
$env:ANTHROPIC_BASE_URL = "<span class="var-base-url"><?= $currentHost ?></span>"
$env:ANTHROPIC_API_KEY = "<span class="var-api-key">sk-cp-your-gateway-key</span>"
claude</div>
            </div>

        </div>

        <!-- TAB 2: Cursor IDE -->
        <div id="tab-cursor" class="doc-tab-content hidden space-y-6">
            <div class="card-clean p-6 space-y-4">
                <h3 class="text-base font-bold text-slate-900 dark:text-white">Connecting Cursor IDE to ClaudePulse</h3>
                <p class="text-xs text-slate-600 dark:text-slate-400 leading-relaxed">
                    Cursor IDE natively supports overriding the Anthropic Base URL to route requests through custom gateways.
                </p>

                <ol class="list-decimal list-inside space-y-3 text-xs text-slate-700 dark:text-slate-300">
                    <li>Open <b>Cursor Settings</b> (Gear icon at top right, or press <kbd class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono">Cmd + ,</kbd> / <kbd class="px-1.5 py-0.5 rounded bg-slate-100 dark:bg-slate-800 font-mono">Ctrl + ,</kbd>).</li>
                    <li>Navigate to <b>Models</b> &rarr; <b>Anthropic API Key</b>.</li>
                    <li>Enable <b>"Override Base URL"</b> and enter:
                        <div class="code-box p-2.5 font-mono text-emerald-400 mt-1 flex items-center justify-between">
                            <span class="var-base-url"><?= $currentHost ?></span>
                            <button onclick="copyRaw('<?= $currentHost ?>', this)" class="text-xs text-white bg-slate-800 px-2 py-1 rounded">Copy</button>
                        </div>
                    </li>
                    <li>In the <b>Anthropic API Key</b> input box, paste your key:
                        <div class="code-box p-2.5 font-mono text-emerald-400 mt-1 flex items-center justify-between">
                            <span class="var-api-key">sk-cp-your-gateway-key</span>
                            <button onclick="copySnippet('cmd-copy-key-val', this)" class="text-xs text-white bg-slate-800 px-2 py-1 rounded">Copy</button>
                        </div>
                        <span id="cmd-copy-key-val" class="hidden var-api-key">sk-cp-your-gateway-key</span>
                    </li>
                    <li>Click <b>Verify</b> in Cursor. You are connected!</li>
                </ol>
            </div>
        </div>

        <!-- TAB 3: Python & TypeScript -->
        <div id="tab-sdks" class="doc-tab-content hidden space-y-6">
            
            <!-- Python -->
            <div class="card-clean p-5">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">Python (Official anthropic SDK)</h4>
                    <button onclick="copySnippet('code-python', this)" class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">Copy Python</button>
                </div>
                <div class="code-box p-3 font-mono text-xs text-emerald-400 overflow-x-auto" id="code-python">
import anthropic

client = anthropic.Anthropic(
    base_url="<span class="var-base-url"><?= $currentHost ?></span>",
    api_key="<span class="var-api-key">sk-cp-your-gateway-key</span>",
)

message = client.messages.create(
    model="claude-3-7-sonnet",
    max_tokens=1000,
    messages=[
        {"role": "user", "content": "Salam! Write a hello world script in Python."}
    ]
)

print(message.content[0].text)</div>
            </div>

            <!-- TypeScript / Node.js -->
            <div class="card-clean p-5">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">TypeScript / Node.js (@anthropic-ai/sdk)</h4>
                    <button onclick="copySnippet('code-ts', this)" class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">Copy TypeScript</button>
                </div>
                <div class="code-box p-3 font-mono text-xs text-emerald-400 overflow-x-auto" id="code-ts">
import Anthropic from '@anthropic-ai/sdk';

const anthropic = new Anthropic({
    baseURL: '<span class="var-base-url"><?= $currentHost ?></span>',
    apiKey: '<span class="var-api-key">sk-cp-your-gateway-key</span>',
});

async function main() {
    const message = await anthropic.messages.create({
        model: 'claude-3-7-sonnet',
        max_tokens: 1000,
        messages: [{ role: 'user', content: 'Salam! Test message.' }],
    });
    console.log(message.content[0].text);
}

main();</div>
            </div>

        </div>

        <!-- TAB 4: cURL & REST API -->
        <div id="tab-curl" class="doc-tab-content hidden space-y-6">
            <div class="card-clean p-5">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">Standard Messages API (/v1/messages)</h4>
                    <button onclick="copySnippet('code-curl', this)" class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">Copy cURL</button>
                </div>
                <div class="code-box p-3 font-mono text-xs text-emerald-400 overflow-x-auto" id="code-curl">
curl -X POST "<span class="var-base-url"><?= $currentHost ?></span>/v1/messages" \
  -H "x-api-key: <span class="var-api-key">sk-cp-your-gateway-key</span>" \
  -H "anthropic-version: 2023-06-01" \
  -H "content-type: application/json" \
  -d '{
    "model": "claude-haiku-4-5-20251001",
    "max_tokens": 150,
    "messages": [
      {"role": "user", "content": "Salam! Test message via ClaudePulse."}
    ]
  }'</div>
            </div>

            <!-- Streaming SSE -->
            <div class="card-clean p-5">
                <div class="flex items-center justify-between mb-2">
                    <h4 class="text-xs font-bold text-slate-900 dark:text-white">Streaming Server-Sent Events (stream: true)</h4>
                    <button onclick="copySnippet('code-curl-stream', this)" class="text-xs text-emerald-600 dark:text-emerald-400 font-semibold hover:underline">Copy cURL</button>
                </div>
                <div class="code-box p-3 font-mono text-xs text-emerald-400 overflow-x-auto" id="code-curl-stream">
curl -N -X POST "<span class="var-base-url"><?= $currentHost ?></span>/v1/messages" \
  -H "x-api-key: <span class="var-api-key">sk-cp-your-gateway-key</span>" \
  -H "anthropic-version: 2023-06-01" \
  -H "content-type: application/json" \
  -d '{
    "model": "claude-sonnet-5",
    "max_tokens": 200,
    "stream": true,
    "messages": [
      {"role": "user", "content": "Explain prompt caching in one sentence."}
    ]
  }'</div>
            </div>
        </div>

        <!-- TAB 5: Balance & Limits (/verify) -->
        <div id="tab-balance" class="doc-tab-content hidden space-y-6">
            <div class="card-clean p-6 space-y-4">
                <div class="flex items-center space-x-3">
                    <div class="w-10 h-10 rounded-xl bg-emerald-50 dark:bg-emerald-500/10 text-emerald-600 dark:text-emerald-400 flex items-center justify-center font-bold">
                        <svg class="w-5 h-5" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M9 12l2 2 4-4m6 2a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>
                    </div>
                    <div>
                        <h3 class="text-base font-bold text-slate-900 dark:text-white">Check Your Remaining Tokens & Spend Anytime</h3>
                        <p class="text-xs text-slate-500 dark:text-slate-400">Public self-service inspector requires zero login.</p>
                    </div>
                </div>

                <p class="text-xs text-slate-700 dark:text-slate-300 leading-relaxed">
                    Whenever you want to see how many tokens you have left, how much USD you have spent, or check the latency and cost of your last 20 requests, simply visit the public <b>/verify</b> page:
                </p>

                <div class="p-4 rounded-xl bg-slate-50 dark:bg-slate-900 border border-slate-200 dark:border-slate-800 flex flex-col sm:flex-row items-center justify-between gap-3">
                    <div class="font-mono text-xs text-emerald-600 dark:text-emerald-400 font-bold truncate">
                        <span class="var-base-url"><?= $currentHost ?></span>/verify?key=<span class="var-api-key">sk-cp-your-gateway-key</span>
                    </div>
                    <a id="btn-open-verify-link" href="/verify" target="_blank" class="px-4 py-2 rounded-xl bg-emerald-600 hover:bg-emerald-700 text-white font-semibold text-xs whitespace-nowrap transition">
                        Open Balance Inspector &rarr;
                    </a>
                </div>

                <div class="space-y-2 pt-2">
                    <h4 class="text-xs font-bold text-slate-800 dark:text-slate-200">Terminal Check via cURL:</h4>
                    <div class="code-box p-3 font-mono text-xs text-emerald-400 overflow-x-auto">
curl "<span class="var-base-url"><?= $currentHost ?></span>/verify?key=<span class="var-api-key">sk-cp-your-gateway-key</span>&format=json"</div>
                </div>
            </div>
        </div>

        <!-- Available Models Overview -->
        <div class="card-clean p-5">
            <h3 class="text-xs uppercase tracking-wider font-bold text-slate-400 mb-3">Supported Frontier Models</h3>
            <div class="grid grid-cols-2 sm:grid-cols-4 gap-2 text-xs font-mono">
                <?php foreach (array_slice($models, 0, 8) as $m): ?>
                <div class="p-2.5 rounded-lg bg-slate-50 dark:bg-slate-900/60 border border-slate-200 dark:border-slate-800">
                    <div class="font-bold text-slate-800 dark:text-slate-200 truncate"><?= htmlspecialchars($m['id']) ?></div>
                    <div class="text-[10px] text-slate-400 mt-0.5">$<?= $m['pricing']['input_per_m'] ?> / $<?= $m['pricing']['output_per_m'] ?></div>
                </div>
                <?php endforeach; ?>
            </div>
        </div>

        <!-- Footer -->
        <footer class="text-center text-xs text-slate-400 py-4 border-t border-slate-200 dark:border-slate-800">
            ClaudePulse Self-Hosted API Gateway &bull; Designed by <a href="https://github.com/DeveloperSarim" target="_blank" class="underline hover:text-emerald-500 font-semibold">DeveloperSarim</a>
        </footer>

    </div>

    <script>
        document.addEventListener('DOMContentLoaded', () => {
            const baseUrlInput = document.getElementById('live-base-url');
            const apiKeyInput = document.getElementById('live-api-key');
            const selectKey = document.getElementById('select-active-key');
            const verifyBtn = document.getElementById('btn-open-verify-link');

            function updateVars() {
                const url = (baseUrlInput?.value || window.location.origin).trim().replace(/\/+$/, '');
                const key = (apiKeyInput?.value || 'sk-cp-your-gateway-key').trim();

                document.querySelectorAll('.var-base-url').forEach(el => el.innerText = url);
                document.querySelectorAll('.var-api-key').forEach(el => el.innerText = key);

                if (verifyBtn) {
                    verifyBtn.href = `${url}/verify?key=${encodeURIComponent(key)}`;
                }
            }

            if (baseUrlInput) baseUrlInput.addEventListener('input', updateVars);
            if (apiKeyInput) apiKeyInput.addEventListener('input', updateVars);

            if (selectKey) {
                selectKey.addEventListener('change', () => {
                    if (selectKey.value && apiKeyInput) {
                        apiKeyInput.value = selectKey.value;
                        updateVars();
                    }
                });
            }

            // Tab Navigation
            const tabButtons = document.querySelectorAll('.doc-tab-btn');
            const tabContents = document.querySelectorAll('.doc-tab-content');

            tabButtons.forEach(btn => {
                btn.addEventListener('click', () => {
                    tabButtons.forEach(b => {
                        b.classList.remove('border-emerald-600', 'text-emerald-700', 'dark:border-emerald-400', 'dark:text-emerald-400', 'font-bold');
                        b.classList.add('border-transparent', 'text-slate-500', 'dark:text-slate-400', 'font-semibold');
                    });
                    btn.classList.add('border-emerald-600', 'text-emerald-700', 'dark:border-emerald-400', 'dark:text-emerald-400', 'font-bold');
                    btn.classList.remove('border-transparent', 'text-slate-500', 'dark:text-slate-400', 'font-semibold');

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
                const text = el.innerText;
                navigator.clipboard.writeText(text).then(() => {
                    const orig = btn.innerText;
                    btn.innerText = 'Copied!';
                    setTimeout(() => btn.innerText = orig, 1500);
                });
            };

            window.copyRaw = function(text, btn) {
                navigator.clipboard.writeText(text).then(() => {
                    const orig = btn.innerText;
                    btn.innerText = 'Copied!';
                    setTimeout(() => btn.innerText = orig, 1500);
                });
            };

            // Theme toggle
            const themeBtn = document.getElementById('theme-toggle-btn');
            if (themeBtn) {
                themeBtn.addEventListener('click', () => {
                    const isDark = document.documentElement.classList.toggle('dark');
                    localStorage.setItem('claudepulse_theme', isDark ? 'dark' : 'light');
                });
                const savedTheme = localStorage.getItem('claudepulse_theme');
                if (savedTheme === 'dark' || (!savedTheme && window.matchMedia('(prefers-color-scheme: dark)').matches)) {
                    document.documentElement.classList.add('dark');
                }
            }

            updateVars();
        });
    </script>
</body>
</html>
