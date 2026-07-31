# Gang Sheet Builder — Roadmap

Where the DTF gang-sheet builder stands and what's next on the way to a
full-featured tool.

## Done

- **Front-end builder** (`template-gang-sheet-builder.php` + `assets/js/tsa-gang-sheet.js`):
  PNG drag-drop upload, per-design size with live 300-DPI check, quantity,
  auto-pack (shelf nesting) with rotation + gap, canvas preview with ruler /
  utilization / min-length, drag-to-reposition, configurable roll widths
  (per-inch pricing), live price, WooCommerce add-to-cart, feature-gated by tier.
- **Artwork → order pipeline** (`inc/gang-sheet.php`) — *the tool is now
  production-usable:*
  - Each PNG uploads as it's added (`tsa_gsb_upload`) into
    `/uploads/gang-sheets/YYYY/MM/`, returning an HMAC-signed token.
  - Add-to-cart sends the tokens + a layout JSON (per-design size/qty/DPI +
    placements); the server verifies tokens and stashes files + layout on the
    cart line. Price stays server-computed (length × per-inch).
  - Files + layout + a readable summary persist to the order line item.
  - The **admin order screen shows thumbnails + download links + a layout
    summary** so production has the art and the arrangement.

## Next (full-featured — prioritized)

**High value**
- [ ] **Save / reorder a sheet** (account-linked) — repeat DTF buyers reorder constantly.
- [ ] **Size presets** per design (pocket 4″, left-chest 3.5″, full-back 11″, …).
- [ ] **Manual per-design rotate** button (only auto-rotation exists today).
- [ ] **Multi-sheet auto-split** when designs exceed the roll's max length.
- [ ] **Server-side print-ready composite** (Imagick) — optional single flattened
      PNG/PDF at 300 DPI in addition to the source files, straight to the RIP.

**Medium**
- [ ] **Text / name-&-number tool** (big for team/spirit orders).
- [ ] **PDF proof** for customer approval before production.
- [ ] **Tiered / bulk pricing** (length or area price breaks) — today it's flat per-inch.
- [ ] Accept **SVG / PDF** in addition to PNG; transparency/background validation.
- [ ] **Undo / redo**; duplicate + nudge + align tools.

**Housekeeping**
- [ ] **Orphan cleanup** — a cron to delete `/uploads/gang-sheets/` files older
      than N days that are not referenced by any order (uploaded-but-abandoned).
      *Deferred: current build never deletes, so nothing referenced by an order
      is ever at risk; storage just grows until this lands.*
- [ ] Progressive-upload retry/back-pressure polish; large-file guidance.

## Later — extract to a plugin

The builder is a self-contained tool (its own template, assets, WooCommerce
hooks, and feature gate). **Once we want to distribute or version it
independently — sell it standalone, run it on non-TSA sites, or ship builder
updates separately from the theme — extract it into a dedicated plugin.** Not
urgent while every tenant is a full clone of this theme (the builder already
travels with the theme), but it's the right long-term boundary and matches how
the market ships this (Antigro, Kixxl, DTFBuild are all plugins/apps).

Extraction points to untangle when we do:
- `template-gang-sheet-builder.php` (page template → plugin-registered template
  or shortcode/block)
- `assets/js/tsa-gang-sheet.js`, `assets/css/tsa-gang-sheet.css`, and the
  conditional enqueue in `functions.php`
- `inc/gang-sheet.php` server pipeline + `tsa_gsb_widths()` (still in `functions.php`)
- the `tsa_feature_active('gang_sheet' / 'gang_sheet_custom_sizes')` gates and
  tenant theming (`tsa_gsb_accent`, `tsa_readable_text`)
- per-page settings UI in `inc/page-store-link.php`
