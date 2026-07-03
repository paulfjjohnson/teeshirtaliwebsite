# Store System (Premium, Generic)

Every store — school, team, business, event — uses ONE set of premium templates,
branded and populated from its own store record + design inventory. This replaced
the hardcoded, product-based `schools/dutchtown/*` templates.

## Templates
| Template (Page Attributes → Template) | URL | Purpose |
|---|---|---|
| **TSA Store — Premium Home** | `/schools/<store>/` | Landing: hero, live collections, latest designs, CTA |
| **TSA Store — Programs Hub** | `/schools/<store>/programs/` | Category-grouped program cards → drill to designs |
| **TSA Store — Program** | `/schools/<store>/<program>/` | (Optional) one program's designs. Hub drill covers this too. |

**Page hierarchy matters:** program/hub pages must have the store landing page as
their **Parent**, or the URL flattens and store context is lost.

## How it works (inc/store-chrome.php, inc/store-designs.php)
- **Store resolution** — `tsa_resolve_store_slug($page_id)`: `_tsa_store_id` meta →
  the `/schools/<store>/` URL segment → page/parent slug. Robust; never dead-ends.
- **Context** — `tsa_store_context($slug)`: name, colors (`_tsa_school_primary/secondary`
  → school palette → default), logo (store featured image), tagline, programs, nav.
- **Programs** — the store record's curated program list if set; otherwise
  **derived from design inventory** (`tsa_store_programs_from_designs()`): every
  `tsa_design_category` that has designs for the store becomes a live program, with
  a count and an image (category term thumbnail → design-preview fallback).
- **Designs** — `tsa_store_designs_query($store, $cat)` / `tsa_render_store_design_grid()`
  scope `tsa_design` by `tsa_design_store` (+ category), cards → `/configurator/?design_id=X&store=<slug>`.
- **Drill-down** — one pattern everywhere: program card → `…/programs/?program=<catslug>`.
- **Branding** — `tsa_store_brand_style()` injects ~5 CSS vars; the brandable
  `schools/dutchtown/css/dutchtown-scoped.css` (scoped to `body.dths-active, .dths-page`)
  restyles the whole premium design in the store's colors. Chrome (header/footer)
  is the generic port of the Dutchtown chrome, branded from the store record.

## Configurator store context (plugin)
`[apparel_configurator]` honors `?store=<slug>` from the URL (v1.0.42+), so a design
link brands the configurator and loads that store's apparel. Cart routing + one-store
-per-cart follow the store slug.

## Future enhancements (not built)
1. **Parameterized dynamic pages** — replace per-school/per-program WP pages with
   rewrite rules + the 3 master templates reading store/program from the URL, so
   30+ schools need no manual page creation. (Drill-down is already param-based.)
2. **Configurator design placement/sizing** — fit the design to the print area based
   on the design image AND the chosen apparel image, so art is never over/undersized.
   Lives in the apparel-configurator React mockup layer (`ac-build/src/`).
