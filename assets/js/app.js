/**
 * ClaudePulse Client-Side Application Core
 * Vanilla JS with Chart.js integration, modals, drawer, and async API requests.
 */

window.ClaudePulse = (function () {
    // Theme Management (Light mode default)
    function initTheme() {
        const savedTheme = localStorage.getItem('claudepulse_theme');
        
        if (savedTheme === 'dark') {
            document.documentElement.classList.add('dark');
            document.documentElement.classList.remove('light');
        } else {
            document.documentElement.classList.remove('dark');
            document.documentElement.classList.add('light');
        }
    }

    function toggleTheme() {
        if (document.documentElement.classList.contains('dark')) {
            document.documentElement.classList.remove('dark');
            document.documentElement.classList.add('light');
            localStorage.setItem('claudepulse_theme', 'light');
        } else {
            document.documentElement.classList.add('dark');
            document.documentElement.classList.remove('light');
            localStorage.setItem('claudepulse_theme', 'dark');
        }
    }

    // Toast Notifications
    function showToast(message, type = 'success') {
        const container = document.getElementById('toast-container');
        if (!container) return;

        const toast = document.createElement('div');
        const isSuccess = type === 'success';
        const isError = type === 'error';

        const borderClass = isSuccess ? 'border-emerald-300 dark:border-emerald-500/40' 
                          : isError ? 'border-rose-300 dark:border-rose-500/40' 
                          : 'border-slate-300 dark:border-slate-700';

        const iconSvg = isSuccess
            ? '<svg class="w-4 h-4 text-emerald-600 dark:text-emerald-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M5 13l4 4L19 7"/></svg>'
            : '<svg class="w-4 h-4 text-rose-600 dark:text-rose-400" fill="none" viewBox="0 0 24 24" stroke="currentColor"><path stroke-linecap="round" stroke-linejoin="round" stroke-width="2.5" d="M12 8v4m0 4h.01M21 12a9 9 0 11-18 0 9 9 0 0118 0z"/></svg>';

        toast.className = `flex items-center space-x-3 px-4 py-3 rounded-xl border shadow-lg bg-white text-slate-800 dark:bg-slate-900 dark:text-slate-100 transition-all duration-300 transform translate-y-2 opacity-0 text-xs font-semibold ${borderClass}`;
        toast.innerHTML = `
            <div class="flex-shrink-0">${iconSvg}</div>
            <div class="flex-1">${message}</div>
            <button onclick="this.parentElement.remove()" class="text-slate-400 hover:text-slate-700 dark:hover:text-white font-bold">&times;</button>
        `;

        container.appendChild(toast);

        // Animate in
        requestAnimationFrame(() => {
            toast.classList.remove('translate-y-2', 'opacity-0');
        });

        // Auto remove after 4.5s
        setTimeout(() => {
            toast.classList.add('opacity-0', 'translate-y-2');
            setTimeout(() => toast.remove(), 300);
        }, 4500);
    }

    // Clipboard Copy Helper
    function copyText(text, btnElement) {
        if (!navigator.clipboard) {
            const textarea = document.createElement('textarea');
            textarea.value = text;
            document.body.appendChild(textarea);
            textarea.select();
            document.execCommand('copy');
            document.body.removeChild(textarea);
        } else {
            navigator.clipboard.writeText(text);
        }

        showToast('Copied to clipboard!');

        if (btnElement) {
            const origHtml = btnElement.innerHTML;
            btnElement.innerHTML = `
                <svg class="w-4 h-4 text-emerald-400 inline" fill="none" viewBox="0 0 24 24" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" stroke-width="2" d="M5 13l4 4L19 7"/>
                </svg> Copied!
            `;
            setTimeout(() => {
                btnElement.innerHTML = origHtml;
            }, 2000);
        }
    }

    // Modal Manager
    function openModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.remove('hidden');
            modal.classList.add('flex');
        }
    }

    function closeModal(id) {
        const modal = document.getElementById(id);
        if (modal) {
            modal.classList.add('hidden');
            modal.classList.remove('flex');
        }
    }

    // CSRF Token Helper
    function getCsrfToken() {
        const meta = document.querySelector('meta[name="csrf-token"]');
        return meta ? meta.getAttribute('content') : '';
    }

    // Generic AJAX fetch with CSRF
    async function apiRequest(endpoint, method = 'GET', data = null) {
        const headers = {
            'X-CSRF-TOKEN': getCsrfToken(),
            'Accept': 'application/json'
        };

        const options = {
            method: method,
            headers: headers
        };

        if (data) {
            headers['Content-Type'] = 'application/json';
            options.body = JSON.stringify(data);
        }

        try {
            const response = await fetch(endpoint, options);
            const json = await response.json();
            return { ok: response.ok, status: response.status, data: json };
        } catch (error) {
            return { ok: false, status: 0, error: error.message };
        }
    }

    // Initialize on DOM Ready
    document.addEventListener('DOMContentLoaded', () => {
        initTheme();

        const themeToggleBtn = document.getElementById('theme-toggle-btn');
        if (themeToggleBtn) {
            themeToggleBtn.addEventListener('click', toggleTheme);
        }
    });

    return {
        initTheme,
        toggleTheme,
        showToast,
        copyText,
        openModal,
        closeModal,
        getCsrfToken,
        apiRequest
    };
})();

