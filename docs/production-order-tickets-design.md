# Production Order Tickets — printable job slips — Design

**Date:** 2026-06-27
**Status:** Approved design, implementing
**Theme:** tsa-child · NEW `inc/order-tickets.php`

## Goal
Print a per-order **production ticket** from the WooCommerce admin to attach to the
physical order on the shop floor. One ticket per order, listing everything needed
to make + route it.

## Triggers (both)
- **Bulk action** on the Orders list ("Print production tickets") — classic
  (`edit-shop_order`) and HPOS (`woocommerce_page_wc-orders`) screens.
- **Per-order button** ("Print production ticket") on the single order edit screen
  (`woocommerce_admin_order_data_after_order_details`, fires on classic + HPOS).

Both link to a standalone, admin-only, nonce-protected print page that renders the
selected order(s) — **one ticket per order, page-break between them** — with a Print
button + "Back to orders" link.

## Ticket content (one per order)
- **Header:** "PRODUCTION TICKET", big **Order #**, date, a Store/School · Party badge,
  item + unit count.
- **Customer:** name, phone, email.
- **Fulfillment:** Local pickup + location, OR shipping address (derived from the
  order's shipping method + address).
- **Items to produce** (each line item): design **preview image**, garment (brand +
  style), color, sizes × qty, and each **placement → design name**.
- **Footer:** total units.

## Architecture (all in `inc/order-tickets.php`, included from `functions.php`)
- `tsa_order_ticket_data( WC_Order $order ): array` — assembles one ticket's data from
  the order's configurator line items (`_ac_configurator`: garment_id, color,
  quantities, zones, designs, party_id), `_ac_store_slug`, customer + fulfillment.
  Design label = brand + garment title; image = primary design `_design_preview_url`
  → garment image.
- `admin_post_tsa_order_tickets` → `tsa_render_order_tickets()` — caps
  (`manage_woocommerce`) + nonce; reads `orders` (comma-separated IDs); echoes the
  standalone print HTML and `exit`.
- Bulk: `bulk_actions-{screen}` (add) + `handle_bulk_actions-{screen}` (returns the
  nonce'd print URL as the redirect) for both screens.
- Button: `woocommerce_admin_order_data_after_order_details` prints a link button
  (target=_blank) to the print URL for that order.

## Security
Admin-only (`manage_woocommerce`), nonce-checked, all output escaped.

## Out of scope (v1)
Barcodes, sign-off checkboxes, label-printer sizing, auto-print on load. (Easy adds later.)

## Verification (manual)
1. Orders → select a few Processing → Bulk actions → Print production tickets → one
   ticket per order, page-breaks, all fields populated.
2. Single order → "Print production ticket" button → that order's ticket.
3. Pickup order shows the pickup location; shipping order shows the address.
4. Each item shows its design image + garment/color/sizes + placement→design.
