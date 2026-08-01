/**
 * Session idle timer — warns at 8min, logs out at 10min
 * SERVER_TIMEOUT = 600s (10min)
 */
(function() {
    'use strict';

    const TIMEOUT = (window.SESSION_TIMEOUT || 600) * 1000;
    const WARN_BEFORE = 120 * 1000; // warn 2 minutes before

    let lastActivity = Date.now();
    let warnTimer, logoutTimer;
    const warningEl = document.getElementById('timeout-warning');

    function resetTimers() {
        clearTimeout(warnTimer);
        clearTimeout(logoutTimer);
        lastActivity = Date.now();

        if (warningEl) warningEl.classList.add('d-none');

        warnTimer = setTimeout(showWarning, TIMEOUT - WARN_BEFORE);
        logoutTimer = setTimeout(doLogout, TIMEOUT);
    }

    function showWarning() {
        if (warningEl) warningEl.classList.remove('d-none');
    }

    async function extendSession() {
        try {
            await fetch('/api/ping.php', { method: 'POST',
                headers: { 'X-CSRF-Token': window.CSRF_TOKEN || '' } });
        } catch(e) {}
        resetTimers();
    }

    function doLogout() {
        // Submit logout form
        const form = document.createElement('form');
        form.method = 'POST';
        form.action = '/logout.php';
        const csrf = document.createElement('input');
        csrf.type = 'hidden'; csrf.name = 'csrf_token';
        csrf.value = window.CSRF_TOKEN || '';
        form.appendChild(csrf);
        document.body.appendChild(form);
        form.submit();
    }

    // Track user activity
    ['mousemove','keydown','click','scroll','touchstart'].forEach(evt => {
        document.addEventListener(evt, resetTimers, { passive: true });
    });

    // Expose for manual call from navbar button
    window.extendSession = extendSession;

    // Start
    if (window.USER_ROLE) resetTimers(); // only if logged in
})();
