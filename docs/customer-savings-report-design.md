# TSA Reports — Customer Savings — Design

**Date:** 2026-06-29
**Status:** Approved design, implementing
**Theme:** tsa-child · `inc/reports.php`

## Goal
Show, on the TSA Reports page (per school + date window), how much customers saved
via discounts — split **by source**: discounts we provided (Tee Party / quantity-tier)
vs coupons, plus a combined total. Same dollars, framed as customer savings.

## Data
Extend `tsa_school_report()` (already loops the school's paid orders). Per order
counted for the school (one-store-per-cart → order-level totals attribute cleanly):
- **savings_provided** += sum of **negative fee** totals (the Tee Party buy-more-save-more
  tier discount is applied via `$cart->add_fee($label, -$amount)` →
  `tsa_apply_party_quantity_discounts`; on the order it's a fee line with a negative total).
- **savings_coupons** += `$order->get_total_discount()` (coupon discount; separate from fees).
- **savings_total** = provided + coupons.
Add these three (rounded) to the returned `$r`.

## Display
On TSA Reports, under the existing Revenue/Shirts/Orders/Programs cards, a "Customer
savings" row of three cards — **Discounts we provided**, **Coupons applied**, **Total
customer savings** (highlighted) — plus a one-line summary ("You've saved {school}
shoppers ${total} through discounts."). Scoped to the selected school + window.

## Out of scope (v1)
Store-level **bulk pricing** (`AC_Bulk_Pricing`) discounts are baked into the per-unit
line price (no separate line), so they're not separately measurable without
reconstructing the full price. Not included in v1; can add a computed estimate later.

## Verification
1. A school with a Tee Party order that hit a discount tier shows a non-zero
   "Discounts we provided" equal to the sum of that order's negative fee(s).
2. An order with a coupon shows the coupon amount under "Coupons applied".
3. Total = provided + coupons; summary line matches.
4. A school/window with no discounts shows $0.00 across the three cards.
