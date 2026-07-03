/**
 * TSA Auto-Contrast
 * ------------------------------------------------------------------
 * Flips a design tile's background to a soft dark tone when the art is
 * white / very light, so it doesn't disappear on a white tile.
 *
 * Usage: add class "tsa-autocontrast" to the element whose background
 * should change (either the <img> itself or a wrapper containing one).
 * Static elements are handled on load; for dynamically-built cards call
 * window.tsaAutoContrastScan(rootEl) after inserting them.
 *
 * Same-origin only: getImageData is wrapped in try/catch, so a
 * cross-origin (tainted) image silently keeps the white background and
 * the visible image is never affected.
 */
(function () {
    var DARK  = '#544e48'; // softer warm charcoal — tweak to taste
    var LIGHT = '#ffffff';

    function pick(img, target) {
        try {
            var c = document.createElement('canvas');
            var w = c.width = 28, h = c.height = 28;
            var ctx = c.getContext('2d');
            ctx.drawImage(img, 0, 0, w, h);
            var d = ctx.getImageData(0, 0, w, h).data;
            var opaque = 0, nearWhite = 0, lumSum = 0;
            for (var p = 0; p < d.length; p += 4) {
                if (d[p + 3] < 128) continue;            // skip transparent
                opaque++;
                var r = d[p], g = d[p + 1], b = d[p + 2];
                if (r > 232 && g > 232 && b > 232) nearWhite++;
                lumSum += (0.2126 * r + 0.7152 * g + 0.0722 * b) / 255;
            }
            if (!opaque) return;                         // fully transparent
            target.style.background =
                (nearWhite / opaque > 0.05 || lumSum / opaque > 0.86) ? DARK : LIGHT;
        } catch (e) { /* cross-origin taint — keep white */ }
    }

    function process(el) {
        if (!el || el.dataset.acDone) return;
        var img = el.tagName === 'IMG' ? el : el.querySelector('img');
        if (!img) return;
        el.dataset.acDone = '1';
        var target = el.tagName === 'IMG' ? img : el;
        var run = function () { pick(img, target); };
        if (img.complete && img.naturalWidth) run();
        else img.addEventListener('load', run);
    }

    function scan(root) {
        (root || document).querySelectorAll('.tsa-autocontrast').forEach(process);
        // allow the root itself to carry the class
        if (root && root.classList && root.classList.contains('tsa-autocontrast')) process(root);
    }

    window.tsaAutoContrast     = pick;   // pick(img, targetEl)
    window.tsaAutoContrastScan = scan;   // scan(rootEl?)

    if (document.readyState !== 'loading') scan(document);
    else document.addEventListener('DOMContentLoaded', function () { scan(document); });
})();
