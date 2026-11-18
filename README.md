# ClaudePulse ⚡️
### Self-Hosted Anthropic Claude API Gateway, Token Quota Controller, Prompt Caching Optimizer & Telemetry Platform

<p align="center">
  <img src="https://img.shields.io/badge/version-1.2.0-10b981.svg?style=for-the-badge&logo=semver" alt="Version 1.2.0" />
  <img src="https://img.shields.io/badge/PHP-8.1%20%7C%208.2%20%7C%208.3%20%7C%208.4%20%7C%208.5-4F5D95.svg?style=for-the-badge&logo=php" alt="PHP 8.1+" />
  <img src="https://img.shields.io/badge/Claude%20Code-100%25%20Compatible-D97706.svg?style=for-the-badge&logo=anthropic" alt="Claude Code Ready" />
  <img src="https://img.shields.io/badge/Cursor%20IDE-Ready-0ea5e9.svg?style=for-the-badge&logo=visualstudiocode" alt="Cursor Ready" />
  <img src="https://img.shields.io/badge/Prompt%20Caching-Up%20to%2090%25%20Off-emerald.svg?style=for-the-badge" alt="Prompt Caching" />
  <img src="https://img.shields.io/badge/License-MIT-blue.svg?style=for-the-badge" alt="MIT License" />
  <img src="https://img.shields.io/badge/Author-DeveloperSarim-black.svg?style=for-the-badge&logo=github" alt="DeveloperSarim" />
</p>

---

## 🌟 Overview

**ClaudePulse** is a lightweight, zero-configuration, production-grade reverse proxy gateway, token quota governor, cost optimizer, and real-time observability telemetry platform for the **Anthropic Claude API**.

Built specifically for **Shared Hosting** (Hostinger, cPanel, LiteSpeed, Apache, Nginx, DirectAdmin, Plesk, VPS), ClaudePulse empowers developers, teams, and SaaS builders to issue client-specific `sk-cp-...` API keys with **Token Quotas** (e.g. 10M tokens) and **USD Spend Caps**, stream responses with zero buffering, and dynamically sync official Anthropic frontier models with **automatic prompt caching savings of up to 90%**.

---

## 🚀 Key Features

- **🎯 Dual-Layer Quota Governance:**
  - **Token Quota Limits:** Allocate strict token limits (e.g. 1M, 5M, 10M, 50M, 100M tokens or custom). API access automatically halts with HTTP `429 rate_limit_error` once exhausted.
  - **Hard USD Spend Caps:** Enforce hard dollar limits (e.g. `$10.00`). Total spend is atomically calculated per request using real-time Anthropic pricing heuristics.
- **🔍 Public Self-Service Key Inspector (`/verify`):**
  - Instant self-service URL for clients and developers to check remaining tokens, dollar balance, prompt cache savings, and inspect their last 20 API request logs.
  - Also outputs JSON via `curl "https://yourdomain.com/verify?key=sk-cp-...&format=json"`.
- **⚡ Prompt Caching Optimizer (Up to 90% Savings):**
  - Automatically identifies system prompts and repetitive tool definitions across multi-turn sessions (e.g., Claude Code CLI / Cursor). Injects ephemeral `cache_control` breakpoints, slashing input token costs from **\$3.00/M to \$0.30/M**.
- **🔄 Dynamic Upstream Model Sync:**
  - Automatically queries official Anthropic APIs (`/v1/models`) with smart 4-hour caching. Instantly supports current and newly released frontier models (Claude 3.5 Sonnet, Claude 3.7 Sonnet, Claude Opus 5.5, Claude Sonnet 5, Claude Haiku 5, Claude Fable 5.2, and thinking models) with zero manual reconfiguration.
- **🛡️ Upstream Key Vault & Multi-Key Failover:**
  - Encrypts official Anthropic API keys with authenticated **AES-256-CBC** at rest.
  - If a primary key hits a 429 rate limit or 529 overload, traffic automatically fails over to secondary keys without breaking active client streams.
- **🌊 True Zero-Latency SSE Streaming:**
  - Tuned with Apache/LiteSpeed `noabort:1`, `noconntimeout:1`, and immediate chunk flushing. Terminal tools like Claude Code stream tokens character-by-character without buffering or timeouts.
- **🔄 1-Click Auto-Updater (Two Methods):**
  - **Option 1: Web Installer (`update.php`):** Standalone web extractor that backs up your database, unzips updates, triggers migrations, and deletes itself cleanly.
  - **Option 2: Dashboard 1-Click Update:** Direct GitHub sync from `DeveloperSarim/ClaudePulse` inside your Settings console with automated pre-update zip backups.
- **📦 Zero-Maintenance Storage:**
  - Default SQLite in WAL mode with `.htaccess` directory protection. Works instantly on shared hosting without creating a MySQL database (MySQL optional via environment variables).

---

## 🛠️ System Architecture

```
Claude Code / Cursor / API Clients
         │ (x-api-key: sk-cp-...)
         ▼
 ┌──────────────────────────────────────────────────────────┐
 │ ClaudePulse Gateway (Hostinger / cPanel / LiteSpeed / VPS) │
 ├──────────────────────────────────────────────────────────┤
 │  1. Key Hash Validation & Expiration Checks              │
 │  2. Token Quota Check (used_tokens < cap_tokens)         │
 │  3. Hard Spend Check (spent_usd < cap_usd)               │
 │  4. Rate Limiter (RPM)                                   │
 │  5. Automatic Prompt Cache Injection (Ephemerals)        │
 └─────────────────────────┬────────────────────────────────┘
                           │ (x-api-key: sk-ant-..., AES-256 decrypted)
                           ▼
              Official Anthropic API
              (https://api.anthropic.com/v1/messages)
                           │
       ◄───────────────────┴──────────────────── (SSE Stream)
```

---

## 📁 Repository Structure

```
├── .htaccess                     # LiteSpeed / Apache rewrite engine & SSE stream optimizations
├── .gitignore                    # Protects live database, keys, and session data
├── config.php                    # Application configuration, URL resolution & error handling
├── index.php                     # Clean front-controller router (zero .php in URLs)
├── update.php                    # Standalone 1-Click Web Extractor & Migrator
├── api/
│   ├── proxy.php                 # Core streaming reverse proxy, cache injector & telemetry engine
│   └── internal.php              # Secure AJAX JSON API for the dashboard & GitHub updater
├── src/
│   ├── Auth.php                  # Multi-layer HMAC-SHA256 CSRF protection & session manager
│   ├── Crypto.php                # Authenticated AES-256-CBC encryption & key generator
│   ├── Database.php              # SQLite WAL singleton connection & auto-schema migrations
│   └── Pricing.php               # Dynamic Anthropic API sync & token pricing calculator
├── views/
│   ├── layout.php                # Modern application shell with dark/light mode toggle
│   ├── dashboard.php             # KPIs, timeline charts, model distribution, live requests
│   ├── keys.php                  # Gateway key generator (USD caps & token quotas)
│   ├── logs.php                  # Filterable request inspector & drawer telemetry
│   ├── settings.php              # Key vault, failover toggles, SQLite vacuum & GitHub updater
│   ├── verify.php                # Public self-service balance & quota inspector
│   ├── status.php                # Public system uptime & component health monitor
│   ├── docs.php                  # Interactive connect tutorials & terminal snippets
│   └── login.php                 # First-run admin onboarding & brute-force guarded login
└── assets/
    ├── css/style.css             # Tailwind UI styling, smooth transitions, and drawer rules
    └── js/app.js                 # Chart.js telemetry integrations, toasts, and modals
```

---

## 🚀 Quickstart & Installation

### Option 1: Quick Install via Hostinger / cPanel (Shared Hosting)
1. Download or clone this repository:
   ```bash
   git clone https://github.com/DeveloperSarim/ClaudePulse.git
   ```
2. Upload the files to your domain or subdomain's root (e.g. `public_html/` or `api.yourdomain.com`).
3. Ensure the `database/` folder has `0755` permissions (it is secured with `.htaccess` `Require all denied`).
4. In your browser, navigate to:
   ```
   https://yourdomain.com/login
   ```
5. Follow the **First-Run Setup Wizard** to configure your administrator credentials and optionally add your Anthropic API Key (`sk-ant-...`).

### Option 2: 1-Click Web Updater (`update.php`)
If updating an existing installation:
1. Upload `update.zip` and `update.php` into your server's root folder.
2. Visit `https://yourdomain.com/update.php` in your browser.
3. Click **"Run Update Now"**. Existing database data and keys are **100% preserved**.
4. Click **"Delete Updater Files"** when finished.

### Option 3: Local Development (PHP CLI)
```bash
# Clone the repository
git clone https://github.com/DeveloperSarim/ClaudePulse.git
cd ClaudePulse

# Start local server
php -S 0.0.0.0:8080 index.php
```
Open [http://localhost:8080/setup](http://localhost:8080/setup) to begin.

---

## 💻 Connecting Developer Tools

### 1. Claude Code CLI
Generate a Gateway Key (`sk-cp-...`) from the dashboard, then configure your terminal:

#### macOS / Linux (`~/.zshrc` or `~/.bashrc`)
```bash
export ANTHROPIC_BASE_URL="https://yourdomain.com"
export ANTHROPIC_API_KEY="sk-cp-your-gateway-key-here"

# Launch Claude Code
claude
```

#### Windows PowerShell
```powershell
$env:ANTHROPIC_BASE_URL = "https://yourdomain.com"
$env:ANTHROPIC_API_KEY = "sk-cp-your-gateway-key-here"
claude
```

### 2. Cursor IDE
In Cursor **Settings &rarr; Models &rarr; Anthropic API Key**:
1. Check **Override Base URL** and enter:
   ```
   https://yourdomain.com
   ```
2. In the Anthropic API Key field, enter your `sk-cp-...` key.

### 3. Direct cURL Testing
```bash
curl -X POST "https://yourdomain.com/v1/messages" \
  -H "x-api-key: sk-cp-your-gateway-key-here" \
  -H "anthropic-version: 2023-06-01" \
  -H "content-type: application/json" \
  -d '{
    "model": "claude-haiku-4-5-20251001",
    "max_tokens": 100,
    "messages": [
      {"role": "user", "content": "Hello from ClaudePulse!"}
    ]
  }'
```

---

## 🔍 Public Self-Service Key Inspector (`/verify`)

End-users and clients can check their balance at any time without admin credentials:

- **Web Interface:** Visit `https://yourdomain.com/verify` and paste any `sk-cp-...` key.
- **Direct Link:** Visit `https://yourdomain.com/verify?key=sk-cp-f1ff830...` to auto-load.
- **Terminal JSON:**
  ```bash
  curl "https://yourdomain.com/verify?key=sk-cp-...&format=json"
  ```
  **Sample JSON Response:**
  ```json
  {
    "success": true,
    "data": {
      "name": "Team Claude Code Key",
      "status": "Active",
      "tokens": {
        "cap": 10000000,
        "used": 450210,
        "remaining": 9549790,
        "percent_used": 4.5,
        "is_unlimited": false
      },
      "spend_usd": {
        "cap": 10.00,
        "spent": 0.0415,
        "remaining": 9.9585,
        "percent_used": 0.4
      },
      "breakdown": {
        "input_tokens": 300000,
        "output_tokens": 100210,
        "cache_read_tokens": 50000,
        "total_saved_usd": 0.1350
      },
      "recent_logs": [...]
    }
  }
  ```

---

## 🔒 Security Architecture

- **Encrypted Secrets:** All upstream Anthropic keys are stored with authenticated AES-256-CBC encryption using a cryptographically random 256-bit server master key stored outside the web root.
- **Hashed Gateway Keys:** Gateway keys are hashed with SHA-256 upon creation; raw key strings are never stored in the database.
- **Double CSRF Defense:** Dual-layer HMAC-SHA256 signature tokens signed by the server master key, eliminating session dropouts on Hostinger CDN/LiteSpeed workers.
- **Brute-Force Guard:** Strict IP-based exponential backoff lockouts against unauthorized login attempts.

---

## 🤝 Contributing

Contributions, issues, and feature requests are welcome! Feel free to check the [issues page](https://github.com/DeveloperSarim/ClaudePulse/issues).

---

## 📄 License

This project is licensed under the MIT License — see the [LICENSE](LICENSE) file for details.

---

<p align="center">
  <b>Designed & Developed with ❤️ by <a href="https://github.com/DeveloperSarim">DeveloperSarim</a></b><br>
  <sub>Empowering developers to build faster and smarter with Anthropic Claude.</sub>
</p>
