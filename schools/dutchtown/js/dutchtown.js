/**
 * Dutchtown Griffins — dutchtown.js
 * Header · Mobile Nav · Countdown · Filter Pills · Cart Toast
 */
(function($) {
    'use strict';

    /* ===== BODY CLASS — triggers TSA header/footer hide via CSS ===== */
    document.body.classList.add('dths-active');

    /* ===== STICKY HEADER SCROLL CLASS ===== */
    var header = document.getElementById('dths-header');
    if (header) {
        window.addEventListener('scroll', function() {
            header.classList.toggle('scrolled', window.scrollY > 60);
        }, { passive: true });
    }

    /* ===== MOBILE NAV ===== */
    var hamburger = document.getElementById('dths-hamburger');
    var mobileNav = document.getElementById('dths-mobile-nav');
    var mobileClose = document.getElementById('dths-mobile-close');
    var backdrop = document.getElementById('dths-mobile-backdrop');

    function openMobileNav() {
        if (!mobileNav) return;
        mobileNav.classList.add('is-open');
        mobileNav.setAttribute('aria-hidden', 'false');
        backdrop && backdrop.classList.add('is-visible');
        hamburger && hamburger.classList.add('is-open');
        hamburger && hamburger.setAttribute('aria-expanded', 'true');
        document.body.style.overflow = 'hidden';
    }

    function closeMobileNav() {
        if (!mobileNav) return;
        mobileNav.classList.remove('is-open');
        mobileNav.setAttribute('aria-hidden', 'true');
        backdrop && backdrop.classList.remove('is-visible');
        hamburger && hamburger.classList.remove('is-open');
        hamburger && hamburger.setAttribute('aria-expanded', 'false');
        document.body.style.overflow = '';
    }

    hamburger  && hamburger.addEventListener('click', openMobileNav);
    mobileClose && mobileClose.addEventListener('click', closeMobileNav);
    backdrop   && backdrop.addEventListener('click', closeMobileNav);

    document.addEventListener('keydown', function(e) {
        if (e.key === 'Escape') closeMobileNav();
    });

    /* ===== COUNTDOWN ENGINE ===== */
    function pad(n) { return String(n).padStart(2, '0'); }

    function initCountdowns() {
        document.querySelectorAll('.dths-countdown[data-target]').forEach(function(el) {
            var target = new Date(el.dataset.target).getTime();
            (function tick() {
                var diff = Math.max(0, target - Date.now());
                var d = Math.floor(diff / 864e5);  diff %= 864e5;
                var h = Math.floor(diff / 36e5);   diff %= 36e5;
                var m = Math.floor(diff / 6e4);    diff %= 6e4;
                var s = Math.floor(diff / 1e3);
                var map = { days: d, hours: h, minutes: m, seconds: s };
                Object.keys(map).forEach(function(u) {
                    el.querySelectorAll('[data-unit="'+u+'"]').forEach(function(n) {
                        n.textContent = pad(map[u]);
                    });
                });
                if (diff > 0) setTimeout(tick, 1000);
            })();
        });
    }

    /* ===== FILTER PILLS ===== */
    function initPills() {
        var wrap = document.getElementById('dths-filter-pills');
        if (!wrap) return;
        wrap.addEventListener('click', function(e) {
            var pill = e.target.closest('.dths-pill');
            if (!pill) return;
            wrap.querySelectorAll('.dths-pill').forEach(function(p) {
                p.classList.remove('dths-pill-active');
                p.classList.add('dths-pill-inactive');
            });
            pill.classList.add('dths-pill-active');
            pill.classList.remove('dths-pill-inactive');
            var filter = pill.dataset.filter;
            document.querySelectorAll('.products li.product').forEach(function(p) {
                p.style.display = (filter === 'all' || p.className.includes('product_tag-' + filter)) ? '' : 'none';
            });
        });
    }

    /* ===== CART TOAST ===== */
    function showToast(msg) {
        var t = document.createElement('div');
        t.className = 'dths-toast';
        t.textContent = msg;
        document.body.appendChild(t);
        requestAnimationFrame(function() { t.classList.add('visible'); });
        setTimeout(function() {
            t.classList.remove('visible');
            setTimeout(function() { t.remove(); }, 350);
        }, 2400);
    }

    /* ===== CART COUNT UPDATE ===== */
    $(document.body).on('added_to_cart', function(e, fragments, hash, $btn) {
        // Update cart count badge
        if (fragments) {
            Object.keys(fragments).forEach(function(sel) {
                try { $(sel).replaceWith(fragments[sel]); } catch(e) {}
            });
        }
        // Also update our custom badge
        if (fragments && fragments['.dths-header-cart-count']) {
            var newCount = parseInt($(fragments['.dths-header-cart-count']).text()) || 0;
            var badge = document.getElementById('dths-cart-count');
            if (badge) {
                badge.textContent = newCount;
                badge.classList.toggle('has-items', newCount > 0);
            }
        }
        var name = $btn.closest('li.product, .dths-product-page')
                       .find('.woocommerce-loop-product__title, .dths-product-title')
                       .first().text() || 'Item';
        showToast('✓ ' + name.trim() + ' added to bag');
    });

    /* ===== INIT ===== */
    $(function() {
        initCountdowns();
        initPills();
    });

    $(document.body).on('updated_wc_div updated_cart_totals', initCountdowns);

})(jQuery);
