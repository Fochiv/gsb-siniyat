/**
 * GSB SINIYAT — Main application JS
 */
(function() {
    'use strict';

    // ---- Toast notification ----
    let toastContainer = null;

    function showToast(message, type = 'success') {
        if (!toastContainer) {
            toastContainer = document.createElement('div');
            toastContainer.className = 'toast-container';
            document.body.appendChild(toastContainer);
        }
        const icons = { success: 'check-circle-fill', danger: 'exclamation-triangle-fill',
                        warning: 'exclamation-circle-fill', info: 'info-circle-fill' };
        const toast = document.createElement('div');
        toast.className = `toast align-items-center text-white bg-${type} border-0 show`;
        toast.setAttribute('role', 'alert');
        toast.innerHTML = `
            <div class="d-flex">
                <div class="toast-body"><i class="bi bi-${icons[type]||'info-circle-fill'} me-2"></i>${message}</div>
                <button type="button" class="btn-close btn-close-white me-2 m-auto" onclick="this.closest('.toast').remove()"></button>
            </div>`;
        toastContainer.appendChild(toast);
        setTimeout(() => toast.remove(), 5000);
    }
    window.showToast = showToast;

    // ---- Confirm dialog ----
    function confirm(message) {
        return window.confirm(message || window.t?.('common.confirm_delete') || 'Êtes-vous sûr ?');
    }
    window.confirmAction = confirm;

    // ---- API helper ----
    async function apiPost(url, data) {
        const res = await fetch(url, {
            method: 'POST',
            headers: {
                'Content-Type': 'application/json',
                'X-CSRF-Token': window.CSRF_TOKEN || '',
                'Accept': 'application/json'
            },
            body: JSON.stringify(data)
        });
        return res.json();
    }
    window.apiPost = apiPost;

    async function apiGet(url) {
        const res = await fetch(url, {
            headers: { 'Accept': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' }
        });
        return res.json();
    }
    window.apiGet = apiGet;

    // ---- Search / filter table ----
    function initTableSearch(inputId, tableId) {
        const input = document.getElementById(inputId);
        const table = document.getElementById(tableId);
        if (!input || !table) return;
        input.addEventListener('input', () => {
            const q = input.value.toLowerCase();
            table.querySelectorAll('tbody tr').forEach(row => {
                row.style.display = row.textContent.toLowerCase().includes(q) ? '' : 'none';
            });
        });
    }
    window.initTableSearch = initTableSearch;

    // ---- Format currency ----
    function formatFCFA(amount) {
        return new Intl.NumberFormat('fr-FR').format(amount) + ' FCFA';
    }
    window.formatFCFA = formatFCFA;

    // ---- Payment mode toggle ----
    function initPaymentModeToggle() {
        const modeSelect = document.getElementById('mode_paiement');
        const bankFields = document.getElementById('bank-fields');
        if (!modeSelect || !bankFields) return;
        function toggle() {
            const isBank = modeSelect.value === 'virement';
            bankFields.style.display = isBank ? '' : 'none';
            bankFields.querySelectorAll('input').forEach(i => i.required = isBank);
        }
        modeSelect.addEventListener('change', toggle);
        toggle();
    }

    // ---- Documents "select all" ----
    function initSelectAllDocs() {
        const selectAll = document.getElementById('docs_select_all');
        if (!selectAll) return;
        selectAll.addEventListener('change', () => {
            document.querySelectorAll('.doc-checkbox').forEach(cb => cb.checked = selectAll.checked);
        });
    }

    // ---- Fade in on load ----
    document.addEventListener('DOMContentLoaded', () => {
        document.querySelectorAll('.fade-in-load').forEach(el => el.classList.add('fade-in'));
        initPaymentModeToggle();
        initSelectAllDocs();
    });

    // ---- Service worker registration ----
    if ('serviceWorker' in navigator) {
        window.addEventListener('load', () => {
            navigator.serviceWorker.register('/sw.js')
                .then(reg => console.log('SW registered:', reg.scope))
                .catch(err => console.warn('SW registration failed:', err));
        });
    }

})();
