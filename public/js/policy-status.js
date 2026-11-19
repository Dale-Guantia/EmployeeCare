(function () {
    const notice = document.getElementById('policy-processing-status');
    if (!notice) return;
    let timer = null;
    let running = false;
    let version = null;

    function schedule() { timer = setTimeout(poll, 4000); }
    async function poll() {
        timer = null;
        if (document.hidden) { schedule(); return; }
        try {
            const response = await fetch(notice.dataset.statusUrl, { credentials: 'same-origin', headers: { Accept: 'application/json' }, signal: AbortSignal.timeout(10000) });
            if (!response.ok) throw new Error('Status unavailable');
            const state = await response.json();
            const busy = state.pending + state.processing > 0;
            if (!busy) running = false;
            if (version !== state.version || !busy) {
                version = state.version;
                window.crud?.table?.ajax.reload(null, false);
            }
            notice.textContent = busy
                ? state.pending + ' waiting, ' + state.processing + ' processing. Status refreshes automatically; waiting documents require the policy worker.'
                : 'Background processing finished. The list is updated; check each document’s status for the result.';
            if (busy) schedule();
        } catch (_) {
            running = false;
            notice.textContent = 'Automatic status refresh is unavailable. Refresh this page to check your documents.';
        }
    }
    function start() {
        if (running || timer !== null) return;
        running = true;
        notice.hidden = false;
        schedule();
    }
    if (notice.dataset.pending === '1') start();
    if (window.jQuery) {
        window.jQuery('#crudTable').on('draw.dt', function () {
            if (document.querySelector('[data-policy-status="pending"], [data-policy-status="processing"]')) start();
        });
    }
    window.addEventListener('pagehide', function () { clearTimeout(timer); running = false; });
})();
