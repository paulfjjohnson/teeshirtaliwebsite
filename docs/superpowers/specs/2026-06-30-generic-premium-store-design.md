# Generic Premium Store — Design Spec (2026-06-30)

## Problem
Released Tee Party designs never appear in a school store. Root cause: the Dutchtown
storefront is pinned to **hardcoded legacy templates** (`schools/dutchtown/page-school-*.php`)
that query **WooCommerce products** (`product_cat`), while the design/Tee-Party/configurator
funnel runs on the **`tsa_design` CPT + `tsa_design_store` taxonomy**. The two systems never
touch — so the store is empty even though designs exist.

A correct, generic, store-type-agnostic template already exists (`template-school-store.php`)
and its `[tsa_school_programs]` cards already link to `/design-library/?store=<slug>&cat=<prog>`
(the design system). Dutchtown simply isn't using it. It also lacks the premium "Dutchtown Roar"
visual treatment that lives only in the hardcoded files.

## Goal
ONE premium, store-record-driven storefront that works for **school / team / business / event**
stores, wired to the design system: store → program → design → store-branded configurator → cart.

## Design model (per docs/40-design-library.md)
- A design is scoped by **Store** (`tsa_design_store`, who owns it, e.g. `dutchtown`) and described
  by shared **Categories** (`tsa_design_category`, what it is, e.g. `band`).
- A store page shows designs filtered by **Store**, grouped by **Category**.
- Program page = Store + that program's Category.
- Every design card links to `/configurator/?design_id=<id>&store=<slug>` (store-branded funnel,
  now honored by apparel-configurator 1.0.42).

## Components
1. **`tsa_render_store_design_grid( $store_slug, $cat = '' )`** — reusable renderer (new
   `inc/store-designs.php`). Queries `tsa_design` where `tsa_design_store IN [slug]`
   (+ `tsa_design_category IN [cat]` when given), publish-only, active. Outputs design cards
   (preview, title, "Configure & Order →" → configurator with `&store=`). Single source of truth.
   Empty state handled gracefully.
2. **Brandable premium CSS** — generalize `schools/dutchtown/css/dutchtown-scoped.css` into a
   store-agnostic stylesheet using CSS variables (`--store-primary/--store-secondary/--store-text`)
   set inline from the store record. Same look, any store's colors.
3. **`template-store-program.php`** — per-program collection page; resolves store + program from the
   page hierarchy/record, renders the hero + `tsa_render_store_design_grid($slug,$prog)`.
4. **`template-store-premium.php`** — premium home; store record drives name/tagline/colors/banner +
   live/coming-soon programs + a store-level design grid (all the store's designs grouped by category).
5. **Generic chrome** — generalize header.php/footer.php (or fold into templates) so nothing is
   Dutchtown-bound; enqueue the brandable CSS on the new templates (functions.php), not keyed to Dutchtown.
6. **Migrate Dutchtown** — reassign its pages to the new templates + set `_tsa_store_id`; retire the
   `schools/dutchtown/` hardcoded files (keep as backup until verified).

## Staged delivery (verify after each)
- **Stage A (the fix, lowest risk):** Components 1 + 3. Register `template-store-program.php`; assign the
  Dutchtown `color-guard`/`band` pages to it. Designs appear + configurator funnel works.
  *Verify early:* do released designs carry a program category? If not, the store-home grid (all store
  designs) is the surface, and designs get tagged with program categories.
- **Stage B (premium home):** Components 2 + 4. Migrate the Dutchtown home onto `template-store-premium.php`.
- **Stage C (cleanup + parity):** Component 6. Retire hardcoded Dutchtown files; confirm a team/business
  store renders identically.

## Dependencies already shipped today
- `inc/design-library.php` REST crash fix; `style.css` overlay; `inc/tee-party.php` store-exclusive
  release; `inc/store-cart.php` `tsa_store_url()`; `template-tee-party.php` closed→store links;
  apparel-configurator 1.0.42 (`?store=` honored). These must be deployed for the funnel to work.

## Out of scope
- WooCommerce product storefronts (this platform sells via the configurator/design funnel).
- Per-school bespoke copy (value props etc.) become generic or store-record fields.
