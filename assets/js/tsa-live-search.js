/**
 * TSA Live Search — whole-site instant dropdown.
 * Replaces Flatsome's product-only live search. Previews products, pages,
 * posts, and Design Library designs as you type (matches the results page).
 */
(function () {
    'use strict';
    var cfg = window.tsaLiveSearch || { ajaxUrl: '/wp-admin/admin-ajax.php' };

    function esc(s) { var d = document.createElement('div'); d.textContent = s == null ? '' : s; return d.innerHTML; }

    function wire(form) {
        var input = form.querySelector('input.search-field, input[type="search"]');
        var box   = form.querySelector('.live-search-results');
        if (!input || !box) { return; }

        var timer, lastQ = '';

        function close() { box.classList.remove('is-open'); }
        function open() { box.classList.add('is-open'); }

        function render(items) {
            if (!items || !items.length) {
                box.innerHTML = '<div class="tsa-ls-empty">No matches — press Enter to search everything.</div>';
                open();
                return;
            }
            var html = '';
            items.forEach(function (it) {
                html += '<a class="tsa-ls-item" href="' + encodeURI(it.url) + '">'
                     + (it.thumb
                          ? '<span class="tsa-ls-thumb"><img src="' + encodeURI(it.thumb) + '" alt="" loading="lazy"></span>'
                          : '<span class="tsa-ls-thumb tsa-ls-thumb--ph">🔍</span>')
                     + '<span class="tsa-ls-text"><span class="tsa-ls-title">' + esc(it.title) + '</span>'
                     + '<span class="tsa-ls-type">' + esc(it.type) + '</span></span></a>';
            });
            box.innerHTML = html;
            open();
        }

        function run() {
            var q = input.value.trim();
            if (q.length < 2) { box.innerHTML = ''; close(); lastQ = ''; return; }
            if (q === lastQ) { open(); return; }
            lastQ = q;
            var url = cfg.ajaxUrl + (cfg.ajaxUrl.indexOf('?') > -1 ? '&' : '?')
                    + 'action=tsa_live_search&q=' + encodeURIComponent(q);
            fetch(url, { credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(render)
                .catch(function () {});
        }

        input.addEventListener('input', function () { clearTimeout(timer); timer = setTimeout(run, 250); });
        input.addEventListener('focus', function () { if (box.innerHTML.trim()) { open(); } });
        document.addEventListener('click', function (e) { if (!form.contains(e.target)) { close(); } });
        input.addEventListener('keydown', function (e) { if (e.key === 'Escape') { close(); } });
    }

    function init() {
        var forms = document.querySelectorAll('form.searchform');
        for (var i = 0; i < forms.length; i++) { wire(forms[i]); }
    }

    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
