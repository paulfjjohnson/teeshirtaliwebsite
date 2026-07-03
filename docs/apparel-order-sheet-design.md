# TSA Reports — Apparel Order Sheet (printable PO) — Design

**Date:** 2026-06-27
**Status:** Approved design, implementing
**Theme:** tsa-child · `inc/reports.php`

## Goal
From the **TSA Reports** admin page, export a clean, printable document that lists
the apparel to order from a wholesaler — grouped so it can be ordered as-is.
Print page → "Save as PDF". No CSV, no server-side PDF library.

## Why a new aggregation
The existing `tsa_school_report()` flattens quantities into *separate* by-garment /
by-size / by-color totals — not orderable (no garment×color×size combos). The raw
order loop, however, has each line as garment + color + {size:qty}. The order sheet
re-aggregates the same paid orders into a **garment → color → size** cross-tab.

## Components (all in `inc/reports.php`)

### 1. `tsa_school_order_sheet( $school, $start, $end ): array`
- Same order query as `tsa_school_report()` (paid statuses, date window, `_ac_configurator`
  line items whose `store_slug` matches `$school`).
- Builds `garments[ garment_id ] = [ 'label'=>…, 'colors'=>[ color => [ size => qty ] ], 'total'=>int ]`.
- `label` = brand + style number when available, else brand + garment title, else title:
  - brand: `_ac_brand`; style number: `_ac_ss_style_id` (S&S import) — fall back gracefully.
- Returns `[ 'garments'=>…, 'sizes'=>[ordered union], 'units'=>int ]`.
- Size ordering: fixed `XS,S,M,L,XL,2XL,3XL,4XL,5XL`; any other sizes appended in first-seen order.
- Sorted: garments by total desc; colors by total desc within a garment.

### 2. `admin_post_tsa_order_sheet` handler → `tsa_render_order_sheet()`
- `current_user_can('manage_options')` + `check_admin_referer('tsa_order_sheet')`.
- Reads `school`, `start`, `end` from the query.
- Echoes a **standalone HTML document** (own `<!doctype html>…`) — no WP admin chrome —
  with print CSS, the header (school + window + generated date + total units), one table
  per garment (color rows × size columns + Total col + style-total row), grand total, and a
  "Print / Save as PDF" button (`@media print { .no-print{display:none} }`). `exit` after.

### 3. Button on the TSA Reports page
- An "Export order sheet" link/button near the Production breakdown, pointing at
  `admin-post.php?action=tsa_order_sheet&school=…&start=…&end=…` with `wp_nonce_url`,
  `target="_blank"`. Only shown when a school is selected and there are units.

## Scope / out of scope
- In: configurator apparel line items only (they carry garment/color/size), per selected
  school + window.
- Out: non-configurator products; pricing/costs (a pure pick/order sheet — quantities only);
  CSV; server-side PDF.

## Security
- Admin-only (`manage_options`), nonce-checked. All output escaped.

## Verification (manual)
1. Reports → pick a school with sales → Export order sheet opens a clean print page.
2. Quantities reconcile: each garment's grand total equals its by-garment total in the report;
   sheet total equals report "units".
3. Garment with an S&S style number shows brand + style; a manual garment shows brand + name.
4. Print → Save as PDF produces a tidy one/two-page document.
