/**
 * TSA Cart UI — off-canvas mini-cart drawer.
 * Intercepts the Flatsome header cart, opens a themed slide-in drawer,
 * handles qty steppers + remove (AJAX), and opens on add-to-cart.
 */
(function () {
    'use strict';
    var cfg = window.tsaCart || { ajaxUrl: '/wp-admin/admin-ajax.php', nonce: '' };

    function tsaSetCookie(n, v) { try { document.cookie = n + '=' + encodeURIComponent(v) + ';path=/;max-age=86400;samesite=lax'; } catch (e) {} }
    function tsaGetCookie(n) { var m = document.cookie.match(new RegExp('(?:^|;\\s*)' + n + '=([^;]+)')); return m ? decodeURIComponent(m[1]) : ''; }

    function init() {
        var drawer  = document.getElementById('tsa-cart-drawer');
        var overlay = document.getElementById('tsa-cart-overlay');
        if (!drawer || !overlay) return;

        /* ── Open / close ── */
        function openDrawer() {
            drawer.classList.add('is-open');
            overlay.hidden = false;
            requestAnimationFrame(function () { overlay.classList.add('is-open'); });
            drawer.setAttribute('aria-hidden', 'false');
            document.body.classList.add('tsa-cart-open');
        }
        function closeDrawer() {
            drawer.classList.remove('is-open');
            overlay.classList.remove('is-open');
            drawer.setAttribute('aria-hidden', 'true');
            document.body.classList.remove('tsa-cart-open');
            setTimeout(function () { if (!overlay.classList.contains('is-open')) overlay.hidden = true; }, 280);
        }
        window.tsaCartOpen = openDrawer; // so the configurator / gang-sheet flows can open it

        overlay.addEventListener('click', closeDrawer);
        var closeBtn = document.getElementById('tsa-cart-close');
        if (closeBtn) closeBtn.addEventListener('click', closeDrawer);
        document.addEventListener('keydown', function (e) { if (e.key === 'Escape' && drawer.classList.contains('is-open')) closeDrawer(); });

        /* ── Intercept the header cart (capture phase beats Flatsome's handler) ──
           Match by known classes OR — markup-agnostic — by any link whose URL
           points at the cart page. Links inside the drawer (View cart / Checkout)
           are excluded so they still navigate. */
        var cartPath = '';
        try { cartPath = new URL(cfg.cartUrl || '/cart/', location.origin).pathname.replace(/\/+$/, ''); } catch (e) { cartPath = ''; }

        document.addEventListener('click', function (e) {
            var link = e.target.closest('a, .header-cart-link, .cart-icon, [data-tsa-cart]');
            if (!link) return;
            if (drawer.contains(link)) return; // drawer's own links must navigate

            var isCart = !!(link.matches && link.matches('.header-cart-link, a.icon-cart, li.cart-item > a, .cart-icon, [data-tsa-cart]'));
            if (!isCart && cartPath && link.tagName === 'A' && link.getAttribute('href')) {
                try {
                    var p = new URL(link.href, location.origin).pathname.replace(/\/+$/, '');
                    if (p === cartPath) isCart = true;
                } catch (err) {}
            }
            if (!isCart) return;

            e.preventDefault();
            e.stopPropagation();
            openDrawer();
        }, true);

        /* ── Apply returned WC fragments (qty/remove responses) ── */
        function applyFragments(fragments) {
            if (!fragments) return;
            Object.keys(fragments).forEach(function (sel) {
                document.querySelectorAll(sel).forEach(function (el) {
                    var tmp = document.createElement('div');
                    tmp.innerHTML = fragments[sel];
                    var nw = tmp.firstElementChild;
                    if (nw) el.replaceWith(nw);
                });
            });
        }

        function setQty(key, qty) {
            if (!key) return;
            drawer.classList.add('is-loading');
            var fd = new FormData();
            fd.append('action', 'tsa_cart_set_qty');
            fd.append('nonce', cfg.nonce);
            fd.append('key', key);
            fd.append('qty', qty);
            fetch(cfg.ajaxUrl, { method: 'POST', body: fd, credentials: 'same-origin' })
                .then(function (r) { return r.json(); })
                .then(function (res) {
                    if (res && res.success && res.data && res.data.fragments) {
                        applyFragments(res.data.fragments);
                        if (window.jQuery) window.jQuery(document.body).trigger('wc_fragments_refreshed');
                    }
                })
                .catch(function () {})
                .finally(function () { drawer.classList.remove('is-loading'); });
        }

        /* ── Delegated qty + remove (survives fragment re-render) ── */
        drawer.addEventListener('click', function (e) {
            var line = e.target.closest('.tsa-cart-line');
            var key  = line ? line.getAttribute('data-key') : '';

            var step = e.target.closest('.tsa-cart-qty__btn');
            if (step && line) {
                var d = parseInt(step.getAttribute('data-d'), 10) || 0;
                var cur = parseInt(line.querySelector('.tsa-cart-qty__v').textContent, 10) || 1;
                setQty(key, Math.max(0, cur + d));
                return;
            }
            var rm = e.target.closest('.tsa-cart-line__remove');
            if (rm && line) { setQty(key, 0); return; }
        });

        /* ── Open on add-to-cart (native WC ajax adds fire this on document.body) ── */
        if (window.jQuery) {
            window.jQuery(document.body).on('added_to_cart', function () { openDrawer(); });
        }

        /* ── "Continue shopping" → the last place the user was browsing ──
           Save the current URL on ANY front-end page that isn't part of the
           cart/checkout/account flow — so the configurator, store landings,
           and archives are all remembered (not just WooCommerce archives).
           Product pages still prefer their category link. Cache-proof cookie. */
        var bc = document.body.classList;
        var onCartFlow = bc.contains('woocommerce-cart') || bc.contains('woocommerce-checkout')
            || bc.contains('woocommerce-account') || bc.contains('tsa-cart-page')
            || bc.contains('tsa-account-page');
        if (!onCartFlow) {
            if (bc.contains('single-product')) {
                var catLink = document.querySelector('.product_meta .posted_in a, .posted_in a, .woocommerce-breadcrumb a:last-of-type');
                tsaSetCookie('tsa_continue_url', (catLink && catLink.href) ? catLink.href : location.href);
            } else {
                tsaSetCookie('tsa_continue_url', location.href);
            }
        }
        var contBtns = document.querySelectorAll('.button-continue-shopping, a.continue-shopping, .return-to-shop a, .wc-backward');
        if (contBtns.length) {
            var cfg = window.tsaCart || {};
            // Store cart → back to that store; else the last page browsed; else the
            // Design Library. Never the default WooCommerce shop page.
            var target = cfg.continueUrl || tsaGetCookie('tsa_continue_url') || cfg.shopUrl || '';
            if (target) { contBtns.forEach(function (a) { a.href = target; }); }
        }
    }

    // The drawer markup is printed late in the footer (after this script), so
    // wait for the DOM to be parsed before wiring it up.
    if (document.readyState === 'loading') {
        document.addEventListener('DOMContentLoaded', init);
    } else {
        init();
    }
}());
