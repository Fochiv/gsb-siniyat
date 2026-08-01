/**
 * Multilingual support — FR/EN
 * Loads translations dynamically without page reload
 */
(function() {
    'use strict';

    let translations = {};
    let currentLang = localStorage.getItem('gsb_lang') || window.APP_LANG || 'fr';

    async function loadTranslations(lang) {
        try {
            const res = await fetch('/lang/' + lang + '.json?v=1');
            if (!res.ok) throw new Error('Failed to load translations');
            translations = await res.json();
        } catch (e) {
            console.warn('Could not load translations for:', lang);
        }
    }

    function t(key) {
        const parts = key.split('.');
        let val = translations;
        for (const p of parts) {
            if (val && typeof val === 'object' && p in val) val = val[p];
            else return key;
        }
        return typeof val === 'string' ? val : key;
    }

    function applyTranslations() {
        document.querySelectorAll('[data-i18n]').forEach(el => {
            const key = el.getAttribute('data-i18n');
            const val = t(key);
            if (val !== key) {
                el.textContent = val;
            }
        });
        document.querySelectorAll('[data-i18n-placeholder]').forEach(el => {
            const key = el.getAttribute('data-i18n-placeholder');
            const val = t(key);
            if (val !== key) el.setAttribute('placeholder', val);
        });
    }

    async function switchLang(lang) {
        currentLang = lang;
        localStorage.setItem('gsb_lang', lang);
        // Persist to server
        try {
            await fetch('/api/lang.php', {
                method: 'POST',
                headers: { 'Content-Type': 'application/json', 'X-CSRF-Token': window.CSRF_TOKEN || '' },
                body: JSON.stringify({ lang })
            });
        } catch(e) {}
        await loadTranslations(lang);
        applyTranslations();
        document.documentElement.setAttribute('lang', lang);
        // Update active button states
        document.querySelectorAll('[onclick*="switchLang"]').forEach(btn => {
            const btnLang = btn.getAttribute('onclick').match(/'(\w+)'/)?.[1];
            if (btnLang) btn.classList.toggle('active', btnLang === lang);
        });
    }

    // Initialize on DOM ready
    async function init() {
        await loadTranslations(currentLang);
        applyTranslations();
        document.documentElement.setAttribute('lang', currentLang);
    }

    // Expose globally
    window.switchLang = switchLang;
    window.t = t;

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
})();
