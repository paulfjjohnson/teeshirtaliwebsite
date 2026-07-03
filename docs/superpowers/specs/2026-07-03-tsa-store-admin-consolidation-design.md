# TSA Store Admin Consolidation + Team/Business Launch — Design Spec (2026-07-03)

## Problem
Store admin is split across two disconnected screens: the native "Stores" post-type screen
(Apparel Configurator plugin, `configurator_store` CPT — slug/type/description/active, plus
theme-added Apparel & Colors / Design Drops boxes) and the theme's "TSA Store Builder"
(name/mascot/level/status/colors/logo/tagline/ticker/pickups/shipping/contact/programs). Both
write to the same store record but neither shows the full picture, and it's unclear which one
to use to create or edit a store.

Separately, Team and Business store types are marked `lifecycle: 'planned'` in the platform
feature catalog (`inc/platform.php`), implying they're unbuilt. Investigation shows this is
mostly bookkeeping: `template-store-premium.php` + `tsa_store_context()` (2026-06-30 spec) are
already fully store-type-agnostic, and `tsa_store_type_available()` — the only place that reads
the "planned" flag — is referenced in exactly one spot in the whole theme (a cosmetic "· soon"
badge in the Store Builder). Nothing on the front end actually blocks a team or business store
from rendering today.

## Goal
One obvious admin entry point (the TSA Store Builder) for creating/editing any store regardless
of type — and Team/Business stores flipped to fully live, sellable store types, matching School.

## Components
1. **Type selector in the Store Builder** — new "Store type" field (school/team/business/event/
   main), defaulting to school. Type-conditional sections: School-only fields (mascot, level,
   pickups, shipping, contact email, programs) render only when type=school; universal fields
   (name, slug, colors, logo, tagline, ticker, description, status) apply to every type.
2. **Description + Active folded in** — new "Description" field (maps to the existing
   `_ac_store_description` meta, currently only editable on the native screen). "Active"
   (`_ac_is_active`) stops being a separate checkbox; the Builder derives it from Status on save
   (`hidden` → inactive, `live`/`coming-soon` → active), removing the duplicate-status confusion
   between the plugin's Active flag and the theme's homepage-status field.
3. **Type-aware base URL** — `tsa_sb_ensure_school_page()` / `tsa_sb_ensure_schools_parent()`
   currently hardcode `/schools/<slug>/` for every store. Generalize to resolve the base path
   segment per type, consistent with the `tsa_store_section_bases` filter already used by
   `tsa_resolve_store_slug()` in `inc/store-chrome.php`. Mapping: `school`→`schools`,
   `team`→`teams`, `business`→`business`, `event`→`events`, `main`→`schools` (fallback; `main`
   is a singleton without its own section and isn't expected to go through this flow).
4. **"All stores" table** — the Builder's existing-stores list currently filters to
   `_tsa_store_type = school` (`tsa_school_store_records()`). Add a type-inclusive variant so
   every store, any type, shows up, with its type as a visible column.
5. **Hide native "Stores" screen** — theme-level `admin_menu` hook removes the "Stores" submenu
   from the Apparel Configurator plugin's menu. Does NOT touch the shared plugin file, so
   moneyoverbs.com (which also runs this plugin) is unaffected. The Builder's table gains a
   "Manage apparel & drops →" link per store pointing at the native edit-post screen, since
   garment/color assignment and drop scheduling (`inc/store-apparel.php` meta boxes) stay there
   rather than being rebuilt inside the Builder.
6. **Flip Team/Business to GA** — `inc/platform.php`: change `stamp_team` and `stamp_business`
   lifecycle from `'planned'` to `'ga'`. Removes the "· soon" badge and makes them intentionally
   supported types (they're already functionally unblocked today).
7. **Copy pass** — spot-check `template-store-premium.php` hero copy ("Premium spirit gear...
   configure your design on your apparel...") for business/event-appropriate wording; adjust if
   it reads oddly for a non-spirit-wear store.

## Staged delivery (verify after each)
- **Stage A — Admin consolidation:** Components 1–4. Store Builder handles all 5 types; the
  edit-link parity already in place (this session) covers every store automatically.
- **Stage B — Hide the duplicate screen:** Component 5. Confirm apparel/drops management is
  still reachable and functional via the link-out before removing the menu item.
- **Stage C — Go live:** Components 6–7. Flip the feature flags; spot check one real team store
  and one real business store end-to-end (create via Builder → verify storefront renders →
  verify cart/checkout).

## Dependencies already shipped today
- `template-store-premium.php` + `tsa_store_context()` (2026-06-30 spec) — already
  store-type-agnostic, so no new storefront template is needed for Team/Business.
- `tsa_store_section_bases` filter in `inc/store-chrome.php` — already anticipates multiple base
  URL segments per store type.
- Edit-link parity in the Store Builder (this session, `tsa_sb_form_from_store()`) — the merged
  admin screen inherits it automatically.

## Out of scope
- Rebuilding the Apparel & Colors / Design Drops meta boxes inside the Builder — stays on the
  native edit screen, reachable via a link.
- Dedicated field sections for Event and Main store types beyond the universal set (same
  treatment as Business — no evidence yet they need anything more).
- Pricing/tier changes in `tsa_tiers()` — flipping lifecycle to `ga` doesn't change what's
  bundled in Starter/Pro/Studio; Team/Business are already included in the Pro/Studio feature
  lists today (gated off until `ga`).
