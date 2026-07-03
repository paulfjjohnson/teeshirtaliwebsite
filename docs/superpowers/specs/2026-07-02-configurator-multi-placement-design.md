# Configurator Multi-Placement (Combo) — Design

**Goal:** let a customer put designs on multiple print locations of one garment (e.g. front + back), with a flat combo upcharge, and let the shop disable combos per garment.

## Requirements
- **Per-location picker:** after choosing the garment, the customer sees its print locations (from the garment's `allowed_zones`) and assigns a design to each. Each location's picker is filtered to designs whose `allowed_zones` include that location. ≥1 location required; others optional.
- **Combo pricing:** **+$2.00 per garment (per piece), flat, whenever 2+ locations have a design.** Not placement-specific; covers the extra work + print. Filterable (`ac_combo_upcharge`, default 2.00).
- **Per-garment control:** an **"Allow multiple placements"** toggle on the garment (default ON). When OFF → single placement only, no combo, no upcharge — regardless of how many zones the garment has. Hats/muscle tanks: set front-only zones and/or turn the toggle off.
- Mockup, Review, cart line, and production ticket all show each **location → design**.

## Data model (mostly exists)
- Cart/order item already stores `designs: { zone: design_id }` and `zones: []`. We populate a **different design per zone** instead of the same id everywhere. `zones` = locations with a design.
- Garment meta: `_ac_allow_multi_placement` ('1'|'0', default '1').
- Pricing call gains a `placements` count (number of locations with a design).

## Architecture

### Stage A — Server (PHP, no React rebuild)
1. **Garment editor** (`includes/class-cpt-garment.php`): add "Allow multiple placements" checkbox → save `_ac_allow_multi_placement`.
2. **Garments API** (`api/route-garments.php` `format()`): return `allow_multi` (bool) so the front-end can show/hide the combo option.
3. **Pricing** (`api/route-pricing.php` `calculate`): read `placements` (fallback: count of distinct design_ids). If `placements >= 2` AND garment allows multi → add `apply_filters('ac_combo_upcharge', 2.00)` to the per-unit price (so it scales per piece × qty, like other print costs). Existing per-design `_ac_print_cost` still sums as before (additive).
4. **Cart handler** (`includes/class-cart-handler.php`): compute the **authoritative** price the same way — add the combo upcharge server-side when the item has 2+ placements and the garment allows multi, so it can't be bypassed. Persist the per-zone `designs` map + `zones` on the cart/order item.

### Stage B — React (`ac-build/src/`, needs esbuild rebuild + new plugin zip)
1. **Placement step** (new, after Apparel): renders the garment's zones as slots; each slot opens the store-scoped design library filtered to that zone; supports assign/change/clear. Hidden/collapsed to a single slot when the garment's `allow_multi` is false.
2. **State** (`App.jsx`): replace the single `design` with a `placements: { zone: design }` map (seed the arriving design into its primary zone). `zones` = keys with a design. `handleAddToCart` sends the map + `placements` count.
3. **Pricing call** (`api.js`): send `design_ids` (the assigned designs) + `placements` count.
4. **Mockup** (`Mockup.jsx`): render each assigned design on its zone (front/back preview).
5. **Review** (`StepReview.jsx`): list each location → design + show the combo line when applicable.

## Edge cases
- Garment with only one zone → no combo possible, no upcharge.
- `allow_multi` OFF → single slot, no upcharge.
- Same design chosen on 2 zones still counts as 2 placements → upcharge applies (charge is for the work/print, not distinct art).
- Removing designs back down to 1 location removes the upcharge (recompute live).

## Non-goals (YAGNI)
- Per-location pricing differences (all locations priced the same; flat combo fee only).
- Per-location design scaling/positioning UI (uses existing zone rendering).
