/**
 * TSA Owner Command Center
 * Timeframe toggle (AJAX refresh of KPIs + performance) and click-through
 * slide-over detail panels for every card.
 */
(function () {
    'use strict';
    var cfg = window.tsaDash || { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: '' };
    var tf = '7d';

    var kpisWrap = document.getElementById('tsa-dash-kpis-wrap');
    var perfWrap = document.getElementById('tsa-dash-perf-wrap');
    var tfBar    = document.getElementById('tsa-dash-tf');
    var overlay  = document.getElementById('tsa-dash-overlay');
    var detail   = document.getElementById('tsa-dash-detail');
    var inner    = document.getElementById('tsa-dash-detail-inner');
    if (!kpisWrap) return;

    function post(action, extra) {
        var fd = new FormData();
        fd.append('action', action);
        fd.append('nonce', cfg.nonce);
        fd.append('tf', tf);
        Object.keys(extra || {}).forEach(function (k) { fd.append(k, extra[k]); });
        return fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' }).then(function (r) { return r.json(); });
    }

    /* ── Timeframe toggle ── */
    if (tfBar) tfBar.addEventListener('click', function (e) {
        var b = e.target.closest('button[data-tf]'); if (!b) return;
        tf = b.getAttribute('data-tf');
        tfBar.querySelectorAll('button').forEach(function (x) { x.classList.toggle('is-active', x === b); });
        kpisWrap.style.opacity = perfWrap.style.opacity = '.5';
        post('tsa_dash_refresh', {}).then(function (res) {
            if (res && res.success) { kpisWrap.innerHTML = res.data.kpis; perfWrap.innerHTML = res.data.performance; }
        }).finally(function () { kpisWrap.style.opacity = perfWrap.style.opacity = ''; });
    });

    /* ── Slide-over detail panel ── */
    function openPanel() {
        detail.classList.add('is-open'); overlay.hidden = false;
        requestAnimationFrame(function () { overlay.classList.add('is-open'); });
        detail.setAttribute('aria-hidden', 'false'); document.body.classList.add('tsa-dash-panel-open');
    }
    function closePanel() {
        detail.classList.remove('is-open'); overlay.classList.remove('is-open');
        detail.setAttribute('aria-hidden', 'true'); document.body.classList.remove('tsa-dash-panel-open');
        setTimeout(function () { if (!overlay.classList.contains('is-open')) overlay.hidden = true; }, 280);
    }
    if (overlay) overlay.addEventListener('click', closePanel);
    document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && detail.classList.contains('is-open')) closePanel(); });
    if (inner) inner.addEventListener('click', function (e) { if (e.target.closest('.tsa-dp-close')) closePanel(); });

    /* Delegated: any [data-card] (buttons) opens its detail panel */
    document.addEventListener('click', function (e) {
        var card = e.target.closest('[data-card]');
        if (!card || (detail && detail.contains(card))) return;
        e.preventDefault();
        inner.innerHTML = '<div class="tsa-dp-head"><span class="tsa-dp-title">Loading…</span><button class="tsa-dp-close" aria-label="Close">&times;</button></div>';
        openPanel();
        post('tsa_dash_panel', { card: card.getAttribute('data-card') }).then(function (res) {
            if (res && res.success) inner.innerHTML = res.data.html;
            else inner.innerHTML = '<div class="tsa-dp-head"><span class="tsa-dp-title">Error</span><button class="tsa-dp-close">&times;</button></div><div class="tsa-dp-body"><p class="muted">Could not load details.</p></div>';
        }).catch(function () {
            inner.innerHTML = '<div class="tsa-dp-head"><span class="tsa-dp-title">Error</span><button class="tsa-dp-close">&times;</button></div><div class="tsa-dp-body"><p class="muted">Connection error.</p></div>';
        });
    });
}());
