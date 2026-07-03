/**
 * Tee Shirt Ali — Frontend JS
 * tsa-main.js  |  Version 2.0.0
 */
(function ($) {
    'use strict';

    /* ── Smooth scroll for in-page anchor links ──
       Scoped so it never hijacks clicks inside the React configurator (whose
       tiles/buttons use "#" hrefs — that was scrolling the page on select).
       Also ignores bare "#" and guards against invalid selectors. */
    $(document).on('click', 'a[href^="#"]', function (e) {
        if ($(this).closest('.tsa-cfg-embed, .apparel-configurator, [data-ac-app]').length) return;
        var href = this.getAttribute('href');
        if (!href || href === '#' || href.length < 2) return;
        var $target;
        try { $target = $(href); } catch (err) { return; }
        if ($target.length) {
            e.preventDefault();
            $('html, body').animate({ scrollTop: $target.offset().top - 80 }, 500);
        }
    });

    /* ── Hero mini-card stagger entrance ── */
    function initHeroCards() {
        var $cards = $('.tsa-mini-card');
        if (!$cards.length) return;
        $cards.css({ opacity: 0, transform: 'translateY(18px)' });
        $cards.each(function (i) {
            var $card = $(this);
            setTimeout(function () {
                $card.css({
                    transition: 'opacity .45s ease, transform .45s ease',
                    opacity: 1,
                    transform: 'translateY(0)'
                });
            }, 200 + (i * 120));
        });
    }

    /* ── Intersection observer for section reveal ── */
    function initReveal() {
        if (!('IntersectionObserver' in window)) return;

        var targets = document.querySelectorAll(
            '.tsa-service-card, .tsa-school-card, .tsa-pill, .tsa-mini-card, .tsa-feature-card, .tsa-brand-card, .tsa-design-card, .tsa-how-step, .tsa-style-card'
        );

        var observer = new IntersectionObserver(function (entries) {
            entries.forEach(function (entry) {
                if (entry.isIntersecting) {
                    entry.target.classList.add('tsa-revealed');
                    observer.unobserve(entry.target);
                }
            });
        }, { threshold: 0.12 });

        targets.forEach(function (el) {
            el.classList.add('tsa-will-reveal');
            observer.observe(el);
        });
    }

    /* ── Sticky header shadow on scroll ── */
    function initStickyNav() {
        var $header = $('#header, .tsa-nav');
        if (!$header.length) return;
        $(window).on('scroll.tsaNav', function () {
            $header.toggleClass('tsa-nav--scrolled', $(window).scrollTop() > 10);
        });
    }

    /* ── Catalog / design grid filter tabs ── */
    function initFilterTabs() {
        // Generic filter handler for both brand catalog and design library
        $(document).on('click', '.tsa-filter-tab', function () {
            var $btn   = $(this);
            var filter = $btn.data('filter');
            var $grid  = $btn.closest('section, .tsa-section, #' + ($btn.closest('[id]').attr('id') || '')).find('[data-category]');

            if (!$grid.length) {
                // Try to find grid by closest sibling
                $grid = $btn.closest('.tsa-filter-tabs').siblings('.tsa-brand-grid, .tsa-design-grid').find('[data-category]');
            }

            $btn.siblings('.tsa-filter-tab').removeClass('active');
            $btn.addClass('active');

            if (filter === 'all') {
                $grid.removeAttr('data-hidden');
            } else {
                $grid.each(function () {
                    var cats = ($(this).data('category') || '').split(' ');
                    if (cats.indexOf(filter) !== -1) {
                        $(this).removeAttr('data-hidden');
                    } else {
                        $(this).attr('data-hidden', '1');
                    }
                });
            }

            // Check no-results
            var $noResults = $('#tsa-no-results');
            if ($noResults.length) {
                var visible = $grid.filter(':not([data-hidden])').length;
                $noResults.toggle(visible === 0);
            }
        });
    }

    /* ── Design library search ── */
    function initDesignSearch() {
        var $input = $('#tsa-design-search');
        if (!$input.length) return;

        $input.on('input', function () {
            var query = $(this).val().toLowerCase().trim();
            var $cards = $('#tsa-design-grid .tsa-design-card');

            $cards.each(function () {
                var name = ($(this).data('name') || '').toLowerCase();
                if (!query || name.indexOf(query) !== -1) {
                    $(this).removeAttr('data-hidden');
                } else {
                    $(this).attr('data-hidden', '1');
                }
            });

            var $noResults = $('#tsa-no-results');
            if ($noResults.length) {
                var visible = $cards.filter(':not([data-hidden])').length;
                $noResults.toggle(visible === 0);
            }
        });
    }

    /* ── Quote project type selector ── */
    function initProjectTypes() {
        var $types = $('#tsa-project-types');
        if (!$types.length) return;

        $types.on('click', '.tsa-project-type', function () {
            var $el = $(this);
            $el.siblings().removeClass('selected');
            $el.addClass('selected');
            $('#tsa-project-type-val').val($el.data('value'));
        });
    }

    /* ── AI Chat (basic UI interaction — real AI handled by backend) ── */
    function initAiChat() {
        var $input = $('#tsa-chat-input');
        var $send  = $('#tsa-chat-send');
        var $msgs  = $('#tsa-chat-messages');
        if (!$input.length) return;

        function sendMessage() {
            var msg = $input.val().trim();
            if (!msg) return;
            $msgs.append(
                '<div style="text-align:right; margin:10px 0;">' +
                '<span style="display:inline-block; background:var(--tsa-dark); color:#fff; border-radius:12px 12px 4px 12px; padding:10px 14px; font-size:14px; max-width:80%;">' +
                $('<div>').text(msg).html() +
                '</span></div>'
            );
            $input.val('');
            $msgs.scrollTop($msgs[0].scrollHeight);

            // Stub response — replace with real API call via tsaData.ajaxUrl
            setTimeout(function () {
                $msgs.append(
                    '<div style="margin:10px 0;">' +
                    '<span style="display:inline-block; background:#fff; border:1px solid var(--tsa-line); border-radius:12px 12px 12px 4px; padding:10px 14px; font-size:14px; max-width:80%; color:var(--tsa-dark);">' +
                    'Thanks for your message! Our team will follow up shortly. For immediate help, <a href="/contact/" style="color:var(--tsa-dark); font-weight:700;">contact us here</a>.' +
                    '</span></div>'
                );
                $msgs.scrollTop($msgs[0].scrollHeight);
            }, 800);
        }

        $send.on('click', sendMessage);
        $input.on('keydown', function (e) { if (e.key === 'Enter') sendMessage(); });
    }

    /* ── Init ── */
    $(function () {
        initHeroCards();
        initReveal();
        initStickyNav();
        initFilterTabs();
        initDesignSearch();
        initProjectTypes();
        initAiChat();
    });

}(jQuery));
