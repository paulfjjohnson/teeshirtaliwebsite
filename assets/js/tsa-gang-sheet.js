/**
 * TSA Gang Sheet Builder
 * tsa-gang-sheet.js | Version 2.0.0
 *
 * Configurable roll widths (per-width price-per-inch) · customer-facing builder
 * - File upload (drag & drop + click), PNG only
 * - Per-item print dimensions + DPI check
 * - Shelf bin-packing, optional rotation, drag-to-reposition
 * - HTML5 Canvas render (auto-scaled to fit any width)
 * - WooCommerce AJAX add-to-cart with server-calculated price
 */
(function () {
    'use strict';

    var cfg = window.tsaGSB || {
        ajaxUrl: '/wp-admin/admin-ajax.php', nonce: '', productId: 0, pageId: 0,
        widths: [ { w: 13, rate: 0.50, max: 200 } ], currency: '$',
    };
    var WIDTHS = (cfg.widths && cfg.widths.length) ? cfg.widths.map(function (x) {
        return { w: parseFloat(x.w), rate: parseFloat(x.rate), max: parseInt(x.max, 10) || 200 };
    }) : [ { w: 13, rate: 0.50, max: 200 } ];

    var MAX_CANVAS_PX = 420; // keep the preview within the right column for any width

    var state = {
        items: [], packed: [], gap: 0.25, allowRotation: true, nextId: 1, dragging: null, minLengthNeeded: 0,
        sheetWidth:   WIDTHS[0].w,
        pricePerInch: WIDTHS[0].rate,
        maxLength:    WIDTHS[0].max,
        sheetLength:  10,
        pxPerIn:      30,
    };

    // Scale to the canvas container's real width (capped at 420px) so the sheet
    // fits any viewport — including narrow phones — instead of overflowing.
    function computePx()  {
        var avail = ($canvasOuter && $canvasOuter.clientWidth) ? $canvasOuter.clientWidth - 4 : MAX_CANVAS_PX;
        var cap   = Math.min(MAX_CANVAS_PX, Math.max(220, avail));
        state.pxPerIn = Math.max(8, Math.min(30, Math.floor(cap / state.sheetWidth)));
    }
    function canvasW()    { return Math.round(state.sheetWidth * state.pxPerIn); }
    function fmtW(w)      { return (Math.round(w * 100) / 100).toString().replace(/\.00$/, ''); }

    /* DOM */
    var $uploadZone   = document.getElementById('gsb-upload-zone');
    var $fileInput    = document.getElementById('gsb-file-input');
    var $browseLink   = document.getElementById('gsb-browse-link');
    var $itemList     = document.getElementById('gsb-item-list');
    var $emptyState   = document.getElementById('gsb-empty-state');
    var $canvas       = document.getElementById('gsb-canvas');
    var $canvasEmpty  = document.getElementById('gsb-canvas-empty');
    var $canvasOuter  = document.getElementById('gsb-canvas-outer');
    var $canvasLabel  = document.getElementById('gsb-canvas-label');
    var $utilDisplay  = document.getElementById('gsb-util-display');
    var $canvasRuler  = document.getElementById('gsb-canvas-ruler');
    var $widthSelect  = document.getElementById('gsb-width-select');
    var $lengthSelect = document.getElementById('gsb-length-select');
    var $gapSelect    = document.getElementById('gsb-gap-select');
    var $rotToggle    = document.getElementById('gsb-rotation-toggle');
    var $autoPackBtn  = document.getElementById('gsb-auto-pack');
    var $clearBtn     = document.getElementById('gsb-clear-all');
    var $addToCartBtn = document.getElementById('gsb-add-to-cart');
    var $priceDisplay = document.getElementById('gsb-price');
    var $priceNote    = document.querySelector('.tsa-gsb-price-note');
    var $cartMsg      = document.getElementById('gsb-cart-msg');
    var $statsBox     = document.getElementById('gsb-stats');
    var $statItems    = document.getElementById('gsb-stat-items');
    var $statUtil     = document.getElementById('gsb-stat-util');
    var $statMin      = document.getElementById('gsb-stat-min');
    var $minHint      = document.getElementById('gsb-min-length-hint');
    var $dpiWarn      = document.getElementById('gsb-dpi-warn');
    var $topbarNote   = document.getElementById('gsb-topbar-note');
    var $pricingGrid  = document.getElementById('gsb-pricing-grid');

    if (!$canvas) return;
    var ctx = $canvas.getContext('2d');

    /* ── Canvas size ── */
    function setCanvasSize(lengthIn) {
        var w = canvasW(), h = Math.round(lengthIn * state.pxPerIn);
        $canvas.width = w; $canvas.height = h;
        $canvas.style.width = w + 'px'; $canvas.style.height = h + 'px';
        if ($canvasLabel) $canvasLabel.textContent = 'Sheet preview — ' + fmtW(state.sheetWidth) + '″ × ' + lengthIn + '″';
    }

    /* ── Upload ── */
    function handleFiles(files) {
        Array.from(files).forEach(function (file) {
            if (file.type !== 'image/png') return;
            var reader = new FileReader();
            reader.onload = function (e) {
                var img = new Image();
                img.onload = function () {
                    var defW = Math.min(4.0, state.sheetWidth);
                    var item = {
                        id: state.nextId++, file: file, imgEl: img,
                        pxW: img.naturalWidth, pxH: img.naturalHeight,
                        inW: defW, inH: 0, qty: 1, rot: 0, name: file.name.replace(/\.png$/i, ''),
                        token: null, url: null, uploading: false, uploadErr: false,
                    };
                    item.inH = parseFloat((item.inW * (img.naturalHeight / img.naturalWidth)).toFixed(2));
                    state.items.push(item);
                    renderItemList();
                    autoPack();
                    uploadItem(item); // ship the source PNG to the server so the order carries the art
                };
                img.src = e.target.result;
            };
            reader.readAsDataURL(file);
        });
    }
    $uploadZone.addEventListener('dragover', function (e) { e.preventDefault(); $uploadZone.classList.add('dragover'); });
    $uploadZone.addEventListener('dragleave', function () { $uploadZone.classList.remove('dragover'); });
    $uploadZone.addEventListener('drop', function (e) { e.preventDefault(); $uploadZone.classList.remove('dragover'); handleFiles(e.dataTransfer.files); });
    $uploadZone.addEventListener('click', function (e) { if (e.target !== $browseLink && !$uploadZone.contains(e.target)) return; $fileInput.click(); });
    $browseLink.addEventListener('click', function (e) { e.stopPropagation(); $fileInput.click(); });
    $fileInput.addEventListener('change', function () { handleFiles($fileInput.files); $fileInput.value = ''; });

    /* ── Item list ── */
    // Rotation-aware helpers: when an item is manually rotated, its printed
    // width maps to the image's pixel HEIGHT (and the H/W aspect inverts).
    function srcWpx(item)  { return item.rot ? item.pxH : item.pxW; }
    function aspectH(item) { return item.rot ? (item.pxW / item.pxH) : (item.pxH / item.pxW); }

    // Common DTF print presets (width in inches).
    var SIZE_PRESETS = [ ['', 'Preset…'], ['3.5', 'Sleeve 3.5″'], ['4', 'Left chest 4″'], ['8', 'Youth 8″'], ['11', 'Adult 11″'], ['12', 'Full 12″'] ];

    function dpiInfo(item) {
        if (!item.inW || item.inW <= 0) return { dpi: null, cls: 'na', text: 'Set size' };
        var dpi = Math.round(srcWpx(item) / item.inW);
        if (dpi >= 300) return { dpi: dpi, cls: 'ok', text: dpi + ' DPI ✓' };
        return { dpi: dpi, cls: 'warn', text: dpi + ' DPI ⚠' };
    }
    function renderItemList() {
        var hasItems = state.items.length > 0;
        $emptyState.style.display = hasItems ? 'none' : '';
        $itemList.querySelectorAll('.tsa-gsb-item').forEach(function (el) { el.remove(); });
        var hasDpiWarn = false;

        state.items.forEach(function (item, idx) {
            var di = dpiInfo(item); if (di.cls === 'warn') hasDpiWarn = true;
            var card = document.createElement('div'); card.className = 'tsa-gsb-item'; card.dataset.id = item.id;

            var thumb = document.createElement('div'); thumb.className = 'tsa-gsb-item__thumb';
            var thumbImg = document.createElement('img'); thumbImg.src = item.imgEl.src; thumbImg.alt = item.name; thumb.appendChild(thumbImg);

            var info = document.createElement('div'); info.className = 'tsa-gsb-item__info';
            var name = document.createElement('div'); name.className = 'tsa-gsb-item__name'; name.textContent = item.name; info.appendChild(name);

            var dims = document.createElement('div'); dims.className = 'tsa-gsb-item__dims';
            var wLabel = document.createElement('label'); wLabel.textContent = 'W';
            var wInput = document.createElement('input');
            wInput.type = 'number'; wInput.min = '0.5'; wInput.max = String(state.sheetWidth); wInput.step = '0.25';
            wInput.className = 'tsa-gsb-item__dim-input'; wInput.value = item.inW.toFixed(2);
            wInput.title = 'Print width in inches (max ' + fmtW(state.sheetWidth) + '″)';
            var sep = document.createElement('span'); sep.className = 'sep'; sep.textContent = '×';
            var hInput = document.createElement('input');
            hInput.type = 'number'; hInput.min = '0.5'; hInput.max = '400'; hInput.step = '0.25';
            hInput.className = 'tsa-gsb-item__dim-input'; hInput.value = item.inH.toFixed(2);
            hInput.title = 'Print height in inches';
            var unit = document.createElement('span'); unit.className = 'unit'; unit.textContent = '"';

            wInput.addEventListener('input', function () {
                var v = parseFloat(wInput.value); if (isNaN(v) || v <= 0) return;
                if (v > state.sheetWidth) { v = state.sheetWidth; wInput.value = v; }
                item.inW = v;
                item.inH = parseFloat((v * aspectH(item)).toFixed(2));
                hInput.value = item.inH.toFixed(2);
                updateDpiBadge(card, item); autoPack();
            });
            hInput.addEventListener('input', function () {
                var v = parseFloat(hInput.value); if (isNaN(v) || v <= 0) return;
                item.inH = v; updateDpiBadge(card, item); autoPack();
            });

            var presetSel = document.createElement('select');
            presetSel.className = 'tsa-gsb-item__preset';
            presetSel.title = 'Quick print size';
            presetSel.style.cssText = 'margin-left:6px;font-size:11px;border:1px solid rgba(0,0,0,.15);border-radius:6px;padding:2px 4px;background:#fff';
            SIZE_PRESETS.forEach(function (o) { var op = document.createElement('option'); op.value = o[0]; op.textContent = o[1]; presetSel.appendChild(op); });
            presetSel.addEventListener('change', function () {
                var v = parseFloat(presetSel.value); presetSel.value = '';
                if (isNaN(v) || v <= 0) return;
                if (v > state.sheetWidth) v = state.sheetWidth;
                item.inW = v; item.inH = parseFloat((v * aspectH(item)).toFixed(2));
                wInput.value = item.inW.toFixed(2); hInput.value = item.inH.toFixed(2);
                updateDpiBadge(card, item); autoPack();
            });

            dims.appendChild(wLabel); dims.appendChild(wInput); dims.appendChild(sep); dims.appendChild(hInput); dims.appendChild(unit); dims.appendChild(presetSel);
            info.appendChild(dims);

            var dpiBadge = document.createElement('span'); dpiBadge.className = 'tsa-gsb-item__dpi ' + di.cls; dpiBadge.textContent = di.text; info.appendChild(dpiBadge);

            var up = document.createElement('span'); up.className = 'tsa-gsb-item__upload'; up.style.fontSize = '11px'; up.style.marginLeft = '6px';
            if (item.uploading) { up.textContent = 'uploading…'; up.style.color = '#b26a00'; }
            else if (item.uploadErr) { up.textContent = 'upload failed — retry'; up.style.color = '#b3261e'; up.style.cursor = 'pointer'; up.title = 'Click to retry'; up.addEventListener('click', function () { uploadItem(item); }); }
            else if (item.token) { up.textContent = 'ready ✓'; up.style.color = '#1a7f37'; }
            info.appendChild(up);

            var qtyWrap = document.createElement('div'); qtyWrap.className = 'tsa-gsb-item__qty-wrap';
            var qtyBox = document.createElement('div'); qtyBox.className = 'tsa-gsb-item__qty';
            var btnMinus = document.createElement('button'); btnMinus.innerHTML = '−'; btnMinus.title = 'Decrease quantity';
            btnMinus.addEventListener('click', function () { if (item.qty > 1) { item.qty--; qtyVal.textContent = item.qty; autoPack(); } });
            var qtyVal = document.createElement('span'); qtyVal.className = 'tsa-gsb-item__qty-val'; qtyVal.textContent = item.qty;
            var btnPlus = document.createElement('button'); btnPlus.innerHTML = '+'; btnPlus.title = 'Increase quantity';
            btnPlus.addEventListener('click', function () { item.qty++; qtyVal.textContent = item.qty; autoPack(); });
            qtyBox.appendChild(btnMinus); qtyBox.appendChild(qtyVal); qtyBox.appendChild(btnPlus);

            var rotBtn = document.createElement('button'); rotBtn.className = 'tsa-gsb-item__rotate'; rotBtn.innerHTML = '⟳'; rotBtn.title = 'Rotate 90°';
            rotBtn.style.cssText = 'cursor:pointer;border:1px solid rgba(0,0,0,.15);border-radius:6px;background:#fff;width:26px;height:26px;font-size:14px;line-height:1';
            if (item.rot) rotBtn.style.background = 'var(--gsb-accent,#d8a85f)';
            rotBtn.addEventListener('click', function () {
                var t = item.inW; item.inW = item.inH; item.inH = t;   // swap footprint
                item.rot = item.rot ? 0 : 1;
                if (item.inW > state.sheetWidth) { item.inW = state.sheetWidth; item.inH = parseFloat((item.inW * aspectH(item)).toFixed(2)); }
                renderItemList(); autoPack();
            });

            var removeBtn = document.createElement('button'); removeBtn.className = 'tsa-gsb-item__remove'; removeBtn.innerHTML = '✕'; removeBtn.title = 'Remove design';
            removeBtn.addEventListener('click', function () { state.items.splice(idx, 1); renderItemList(); autoPack(); updateCartEnabled(); });
            qtyWrap.appendChild(qtyBox); qtyWrap.appendChild(rotBtn); qtyWrap.appendChild(removeBtn);

            card.appendChild(thumb); card.appendChild(info); card.appendChild(qtyWrap);
            $itemList.appendChild(card);
        });
        $dpiWarn.style.display = hasDpiWarn ? '' : 'none';
    }
    function updateDpiBadge(card, item) {
        var di = dpiInfo(item); var badge = card.querySelector('.tsa-gsb-item__dpi');
        if (badge) { badge.className = 'tsa-gsb-item__dpi ' + di.cls; badge.textContent = di.text; }
    }

    /* ── Bin-packing (shelf) ── */
    function buildRects() {
        var rects = [];
        state.items.forEach(function (item) { for (var q = 0; q < item.qty; q++) rects.push({ item: item, w: item.inW, h: item.inH, rotated: false }); });
        return rects;
    }
    function tryRotate(rect) {
        if (rect.item && rect.item.rot) return rect; // user fixed this design's orientation
        if (!state.allowRotation) return rect;
        if (rect.h > rect.w && rect.h <= state.sheetWidth) return { item: rect.item, w: rect.h, h: rect.w, rotated: true };
        return rect;
    }
    function packRects(rects, sheetW, maxLength) {
        var gap = state.gap, margin = gap;
        var sorted = rects.slice().sort(function (a, b) { return (b.w * b.h) - (a.w * a.h); });
        var shelves = [], placed = [], overflow = [];
        for (var i = 0; i < sorted.length; i++) {
            var r = tryRotate(sorted[i]);
            if (r.w + margin * 2 > sheetW) { overflow.push(r); continue; }
            var fit = false;
            for (var s = 0; s < shelves.length; s++) {
                var shelf = shelves[s], nextX = shelf.usedW;
                if (nextX + r.w + gap <= sheetW - margin) {
                    var y = shelf.y;
                    if (maxLength > 0 && y + r.h > maxLength) continue;
                    placed.push({ item: r.item, x: nextX, y: y, w: r.w, h: r.h, rotated: r.rotated });
                    shelf.usedW += r.w + gap; fit = true; break;
                }
            }
            if (!fit) {
                var newY = shelves.length > 0 ? shelves[shelves.length - 1].y + shelves[shelves.length - 1].h + gap : margin;
                if (maxLength > 0 && newY + r.h > maxLength) { overflow.push(r); continue; }
                shelves.push({ y: newY, h: r.h, usedW: margin + r.w + gap });
                placed.push({ item: r.item, x: margin, y: newY, w: r.w, h: r.h, rotated: r.rotated });
            }
        }
        return { placed: placed, overflow: overflow };
    }
    function calcMinLength(placed) {
        if (!placed.length) return 0;
        var max = 0;
        for (var i = 0; i < placed.length; i++) { var bottom = placed[i].y + placed[i].h + state.gap; if (bottom > max) max = bottom; }
        return max;
    }

    function autoPack() {
        if (!state.items.length) { state.packed = []; state.minLengthNeeded = 0; drawCanvas(); updateStats(); updatePrice(); updateMinHint(); return; }
        var result = packRects(buildRects(), state.sheetWidth, 0);
        state.packed = result.placed;
        state.minLengthNeeded = Math.ceil(calcMinLength(result.placed));
        var snapped = Math.ceil(state.minLengthNeeded / 10) * 10;
        if (snapped < 10) snapped = 10;
        if (snapped > state.maxLength) snapped = state.maxLength;
        if (snapped > state.sheetLength) { state.sheetLength = snapped; if ($lengthSelect) $lengthSelect.value = snapped; }
        drawCanvas(); updateStats(); updatePrice(); updateMinHint();
    }

    /* ── Canvas render ── */
    var COLORS = ['rgba(254,194,192,.3)', 'rgba(216,168,95,.25)', 'rgba(167,210,180,.3)', 'rgba(163,196,243,.3)', 'rgba(240,210,163,.3)', 'rgba(210,163,240,.3)'];
    function drawCanvas() {
        var len = state.sheetLength, px = state.pxPerIn, cw = canvasW();
        setCanvasSize(len);
        ctx.clearRect(0, 0, $canvas.width, $canvas.height);
        ctx.fillStyle = '#ffffff'; ctx.fillRect(0, 0, $canvas.width, $canvas.height);
        ctx.strokeStyle = 'rgba(0,0,0,.06)'; ctx.lineWidth = 1;
        for (var x = px; x < cw; x += px) { ctx.beginPath(); ctx.moveTo(x, 0); ctx.lineTo(x, $canvas.height); ctx.stroke(); }
        for (var y = px; y < $canvas.height; y += px) { ctx.beginPath(); ctx.moveTo(0, y); ctx.lineTo(cw, y); ctx.stroke(); }

        if (state.minLengthNeeded > len) {
            var oy = len * px;
            ctx.fillStyle = 'rgba(239,68,68,.08)'; ctx.fillRect(0, oy, cw, $canvas.height - oy);
            ctx.strokeStyle = 'rgba(239,68,68,.5)'; ctx.setLineDash([6, 4]); ctx.lineWidth = 1.5;
            ctx.beginPath(); ctx.moveTo(0, oy); ctx.lineTo(cw, oy); ctx.stroke(); ctx.setLineDash([]);
        }

        var colorIdx = {};
        state.packed.forEach(function (p) {
            var pxx = Math.round(p.x * px), pyy = Math.round(p.y * px), pw = Math.round(p.w * px), ph = Math.round(p.h * px);
            var ckey = p.item.id; if (colorIdx[ckey] === undefined) colorIdx[ckey] = Object.keys(colorIdx).length % COLORS.length;
            ctx.fillStyle = COLORS[colorIdx[ckey]]; ctx.fillRect(pxx, pyy, pw, ph);
            var turned = p.rotated || (p.item.rot ? true : false);
            ctx.save();
            if (turned) { ctx.translate(pxx + pw, pyy); ctx.rotate(Math.PI / 2); ctx.drawImage(p.item.imgEl, 0, 0, ph, pw); }
            else { ctx.drawImage(p.item.imgEl, pxx, pyy, pw, ph); }
            ctx.restore();
            ctx.strokeStyle = 'rgba(37,33,36,.25)'; ctx.lineWidth = 1; ctx.strokeRect(pxx + .5, pyy + .5, pw - 1, ph - 1);
            if (turned) { ctx.fillStyle = 'rgba(37,33,36,.6)'; ctx.fillRect(pxx, pyy, 16, 16); ctx.fillStyle = '#fff'; ctx.font = '10px sans-serif'; ctx.fillText('↻', pxx + 3, pyy + 11); }
            ctx.fillStyle = 'rgba(37,33,36,.7)'; ctx.fillRect(pxx, pyy + ph - 16, pw, 16);
            ctx.fillStyle = '#fff'; ctx.font = 'bold 9px sans-serif'; ctx.textAlign = 'center';
            ctx.fillText(truncate(p.item.name, Math.floor(pw / 6)), pxx + pw / 2, pyy + ph - 4); ctx.textAlign = 'left';
        });

        ctx.strokeStyle = 'rgba(37,33,36,.15)'; ctx.lineWidth = 2; ctx.strokeRect(1, 1, cw - 2, $canvas.height - 2);
        $canvasEmpty.style.display = state.packed.length ? 'none' : '';
        buildRuler(len);
    }
    function truncate(str, max) { if (str.length <= max) return str; return str.substring(0, Math.max(1, max - 1)) + '…'; }
    function buildRuler(lengthIn) {
        $canvasRuler.innerHTML = '';
        for (var i = 0; i <= lengthIn; i += 2) {
            var tick = document.createElement('div'); tick.className = 'tsa-gsb-ruler-tick'; tick.style.left = Math.round(i * state.pxPerIn) + 'px';
            if (i % 4 === 0) { var lbl = document.createElement('span'); lbl.textContent = i + '"'; tick.appendChild(lbl); tick.style.height = '12px'; tick.style.opacity = '0.7'; }
            $canvasRuler.appendChild(tick);
        }
    }

    /* ── Drag-to-reposition (Pointer Events = mouse + touch + pen) ── */
    function ptXY(e) {
        var rect = $canvas.getBoundingClientRect();
        return { x: (e.clientX - rect.left) / state.pxPerIn, y: (e.clientY - rect.top) / state.pxPerIn };
    }
    $canvas.addEventListener('pointerdown', function (e) {
        var m = ptXY(e);
        for (var i = state.packed.length - 1; i >= 0; i--) {
            var p = state.packed[i];
            if (m.x >= p.x && m.x <= p.x + p.w && m.y >= p.y && m.y <= p.y + p.h) {
                state.dragging = { idx: i, offX: m.x - p.x, offY: m.y - p.y };
                $canvas.classList.add('dragging');
                if ($canvas.setPointerCapture) { try { $canvas.setPointerCapture(e.pointerId); } catch (err) {} }
                e.preventDefault(); // stop touch-scroll while dragging
                break;
            }
        }
    });
    $canvas.addEventListener('pointermove', function (e) {
        if (!state.dragging) return;
        var m = ptXY(e);
        var p = state.packed[state.dragging.idx];
        p.x = Math.max(0, Math.min(state.sheetWidth - p.w, m.x - state.dragging.offX));
        p.y = Math.max(0, m.y - state.dragging.offY);
        drawCanvas(); updateStats();
        e.preventDefault();
    });
    function endDrag() { if (!state.dragging) return; state.dragging = null; $canvas.classList.remove('dragging'); updateStats(); }
    $canvas.addEventListener('pointerup', endDrag);
    $canvas.addEventListener('pointercancel', endDrag);

    /* ── Reflow the canvas scale when the viewport resizes ── */
    var rzTimer;
    window.addEventListener('resize', function () {
        clearTimeout(rzTimer);
        rzTimer = setTimeout(function () { computePx(); drawCanvas(); }, 150);
    });

    /* ── Stats + price ── */
    function updateStats() {
        var placed = state.packed.length, len = state.sheetLength, sheetArea = state.sheetWidth * len, usedArea = 0;
        state.packed.forEach(function (p) { usedArea += p.w * p.h; });
        var util = sheetArea > 0 ? Math.round((usedArea / sheetArea) * 100) : 0;
        $statsBox.style.display = placed > 0 ? '' : 'none';
        if ($statItems) $statItems.textContent = placed;
        if ($statUtil) $statUtil.textContent = util + '%';
        if ($statMin) $statMin.textContent = state.minLengthNeeded > 0 ? state.minLengthNeeded + '"' : '–';
        if ($utilDisplay) { $utilDisplay.textContent = placed > 0 ? util + '% used' : '–'; $utilDisplay.className = 'tsa-gsb-canvas-header__util' + (util >= 60 ? ' good' : ''); }
    }
    function updatePrice() {
        var price = state.sheetLength * state.pricePerInch;
        if ($priceDisplay) $priceDisplay.textContent = cfg.currency + price.toFixed(2);
        if ($priceNote) $priceNote.textContent = fmtW(state.sheetWidth) + '″ × ' + state.sheetLength + '″ gang sheet';
    }
    function updateMinHint() {
        if (!$minHint) return;
        var min = state.minLengthNeeded;
        if (min > 0) { var snapped = Math.ceil(min / 10) * 10; $minHint.textContent = 'Min needed: ' + min.toFixed(1) + '″ (ordering ' + snapped + '″)'; }
        else $minHint.textContent = '';
    }

    /* ── Length options + pricing table (per width) ── */
    function buildLengthOptions() {
        if (!$lengthSelect) return;
        var keep = state.sheetLength;
        $lengthSelect.innerHTML = '';
        for (var L = 10; L <= state.maxLength; L += 10) {
            var o = document.createElement('option'); o.value = L;
            o.textContent = L + '″ — ' + cfg.currency + (L * state.pricePerInch).toFixed(2);
            $lengthSelect.appendChild(o);
        }
        if (keep > state.maxLength) keep = state.maxLength;
        if (keep < 10) keep = 10;
        state.sheetLength = keep; $lengthSelect.value = keep;
    }
    function buildPricingGrid() {
        if (!$pricingGrid) return;
        var pts = [10, 20, 30, 40, 50, 60, 80, 100, 120, 150, 200].filter(function (L) { return L <= state.maxLength; });
        $pricingGrid.innerHTML = '';
        pts.forEach(function (L) {
            var price = L * state.pricePerInch, feat = (L === 30);
            var card = document.createElement('div'); card.className = 'tsa-gsb-price-card' + (feat ? ' tsa-gsb-price-card--featured' : '');
            card.innerHTML = (feat ? '<div class="tsa-gsb-price-card__badge">Most popular</div>' : '') +
                '<div class="tsa-gsb-price-card__len">' + L + '″</div>' +
                '<div class="tsa-gsb-price-card__dim">' + fmtW(state.sheetWidth) + '″ × ' + L + '″</div>' +
                '<div class="tsa-gsb-price-card__price">' + cfg.currency + price.toFixed(2) + '</div>' +
                '<a href="#builder" class="tsa-gsb-btn tsa-gsb-btn--outline tsa-gsb-btn--sm tsa-gsb-btn--full" data-len="' + L + '">Build this sheet</a>';
            $pricingGrid.appendChild(card);
        });
    }
    if ($pricingGrid) {
        $pricingGrid.addEventListener('click', function (e) {
            var a = e.target.closest('a[data-len]'); if (!a) return;
            var L = parseInt(a.getAttribute('data-len'), 10);
            if ($lengthSelect) { state.sheetLength = L; $lengthSelect.value = L; drawCanvas(); updateStats(); updatePrice(); }
        });
    }

    /* ── Width switch ── */
    function setWidth(w) {
        var cfgW = WIDTHS[0];
        for (var i = 0; i < WIDTHS.length; i++) { if (Math.abs(WIDTHS[i].w - w) < 0.01) { cfgW = WIDTHS[i]; break; } }
        state.sheetWidth = cfgW.w; state.pricePerInch = cfgW.rate; state.maxLength = cfgW.max;
        computePx();
        // Clamp any item wider than the new roll.
        state.items.forEach(function (item) {
            if (item.inW > state.sheetWidth) { item.inW = state.sheetWidth; item.inH = parseFloat((item.inW * aspectH(item)).toFixed(2)); }
        });
        if ($topbarNote) $topbarNote.textContent = fmtW(state.sheetWidth) + '″ roll · PNG · 300 DPI min';
        buildLengthOptions();
        buildPricingGrid();
        renderItemList();
        autoPack();
    }
    if ($widthSelect) $widthSelect.addEventListener('change', function () { setWidth(parseFloat(this.value)); });

    /* ── Controls ── */
    if ($lengthSelect) $lengthSelect.addEventListener('change', function () { state.sheetLength = parseInt(this.value, 10); drawCanvas(); updateStats(); updatePrice(); updateMinHint(); });
    $gapSelect.addEventListener('change', function () { state.gap = parseFloat(this.value); autoPack(); });
    $rotToggle.addEventListener('change', function () { state.allowRotation = this.checked; autoPack(); });
    $autoPackBtn.addEventListener('click', autoPack);
    $clearBtn.addEventListener('click', function () {
        if (!state.items.length) return;
        if (!confirm('Clear all designs from the sheet?')) return;
        state.items = []; state.packed = []; state.minLengthNeeded = 0;
        renderItemList(); drawCanvas(); updateStats(); updatePrice(); updateCartEnabled();
        if ($cartMsg) $cartMsg.style.display = 'none';
    });

    /* ── Progressive upload: send each PNG as it's added ── */
    function uploadItem(item) {
        item.uploading = true; item.uploadErr = false; item.token = null;
        updateCartEnabled(); renderItemList();
        var fd = new FormData();
        fd.append('action', 'tsa_gsb_upload');
        fd.append('nonce', cfg.nonce);
        fd.append('file', item.file, item.file.name);
        fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
            .then(function (r) { return r.json(); })
            .then(function (data) {
                if (data && data.success && data.data && data.data.token) {
                    item.token = data.data.token; item.url = data.data.url; item.uploadErr = false;
                } else { item.uploadErr = true; }
            })
            .catch(function () { item.uploadErr = true; })
            .finally(function () { item.uploading = false; renderItemList(); updateCartEnabled(); });
    }
    function anyUploading() { return state.items.some(function (i) { return i.uploading; }); }
    function allUploaded() { return state.items.length > 0 && state.items.every(function (i) { return i.token && !i.uploading && !i.uploadErr; }); }
    function updateCartEnabled() {
        if (!$addToCartBtn) return;
        $addToCartBtn.disabled = state.items.length > 0 && !allUploaded();
        $addToCartBtn.textContent = anyUploading() ? 'Uploading…' : 'Add to cart →';
    }

    /* ── Add to cart (server-calculated price) ── */
    function showCartMsg(type, text) {
        if (!$cartMsg) return;
        $cartMsg.className = 'tsa-gsb-cart-msg ' + type; $cartMsg.innerHTML = text; $cartMsg.style.display = '';
        setTimeout(function () { $cartMsg.style.display = 'none'; }, 6000);
    }
    if ($addToCartBtn) {
        $addToCartBtn.addEventListener('click', function () {
            if (!state.items.length) { showCartMsg('error', 'Add at least one design before adding to cart.'); return; }
            if (state.minLengthNeeded > state.sheetLength) { showCartMsg('error', 'Your designs need at least ' + Math.ceil(state.minLengthNeeded / 10) * 10 + '″ of sheet. Please increase the length.'); return; }
            if (!cfg.productId) { showCartMsg('error', 'Product not configured. Please contact us to order.'); return; }
            if (!allUploaded()) { showCartMsg('error', 'Please wait for all designs to finish uploading (retry any that failed).'); return; }

            $addToCartBtn.disabled = true; $addToCartBtn.textContent = 'Adding…';
            var fd = new FormData();
            fd.append('action', 'tsa_gsb_add_to_cart');
            fd.append('nonce', cfg.nonce);
            fd.append('product_id', cfg.productId);
            fd.append('page_id', cfg.pageId);
            fd.append('width', state.sheetWidth);
            fd.append('length', state.sheetLength);
            fd.append('quantity', 1);
            state.items.forEach(function (i) { if (i.token) fd.append('tokens[]', i.token); });
            fd.append('layout', JSON.stringify({
                items: state.items.map(function (i) { return { name: i.name, inW: i.inW, inH: i.inH, qty: i.qty, rot: i.rot ? 1 : 0, dpi: (i.inW > 0 ? Math.round(srcWpx(i) / i.inW) : 0) }; }),
                placements: state.packed.map(function (p) { return { name: p.item.name, x: p.x, y: p.y, w: p.w, h: p.h, rotated: !!p.rotated }; })
            }));
            fetch(cfg.ajaxUrl, { method: 'POST', body: fd })
                .then(function (r) { return r.json(); })
                .then(function (data) {
                    if (data.success) {
                        showCartMsg('success', '✓ Added to cart! ' + data.data.cart_link);
                        if (window.jQuery && window.jQuery.fn.wc_fragments) window.jQuery(document.body).trigger('wc_fragment_refresh');
                    } else { showCartMsg('error', (data.data && data.data.message) || data.data || 'Could not add to cart. Please try again.'); }
                })
                .catch(function () { showCartMsg('error', 'Connection error. Please try again.'); })
                .finally(function () { updateCartEnabled(); });
        });
    }

    /* ── Init ── */
    if ($widthSelect && $widthSelect.value) state.sheetWidth = parseFloat($widthSelect.value);
    setWidth(state.sheetWidth);
    setCanvasSize(state.sheetLength);
    drawCanvas(); updateStats(); updatePrice(); updateCartEnabled();

}());
