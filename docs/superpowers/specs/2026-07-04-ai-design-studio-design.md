# TSA AI Design Studio — Design Spec

**Date:** 2026-07-04 (AC-plugin verification folded in 2026-07-05)
**Status:** Verified against AC plugin — ready for implementation plan after owner review
**Site:** teeshirtali.com (tsa-child theme + apparel-configurator plugin)

---

## 1. Goal

Add a **public, AI-powered design generator** to teeshirtali.com. A non-designer
(PTA parent, coach, small-business owner) describes what they want through a
guided form; the tool generates print-ready, transparent-background artwork
suitable for DTF, and hands it off to the existing Apparel Configurator or the
Quote form so it can be ordered.

This is the top trending 2026 feature for custom-apparel / print-on-demand
platforms (an estimated 40–65% of POD sellers now use AI design generation) and
it uniquely leverages infrastructure TSA already owns: the `tsa_design`
CPT/meta shape, the `?design_id=` configurator handoff, the artwork-review
proof pipeline, the customer artwork library, and the Requests/leads + SMS
pipeline.

## 2. Scope

**In scope**
- Public homepage-class tool at `/design-studio/`, email-gated + rate-limited.
- Guided inputs → Claude-assisted prompt + moderation → image model → 2–3
  transparent, print-ready variations.
- Customer-owned storage of generated designs, private to the owner, with
  provenance/source awareness.
- Handoff into the Apparel Configurator and Quote form.
- Mandatory human proof before print, via the plugin's existing artwork-review.

**Out of scope (this spec)**
- Roster-based group ordering (separate feature).
- Changes to curated `tsa_design` library behavior for admin/coordinator art.
- Vector/true-color-separation output (raster DTF only).
- Full custom account system (we ride the existing WooCommerce account + guest
  email + the plugin's `_ac_saved_artwork_ids` customer library).

## 3. User journey

1. **Guided inputs** (no blank prompt box):
   - *What's it for?* — School / Team / Event / Business / Just for fun
   - *Text to include* — e.g. "Warhawks • Est. 2024" (length-capped)
   - *Style* — preset chips: Retro/Vintage, Varsity, Mascot, Bold Typography,
     Minimalist Line-Art, Cottagecore, Y2K (2026 trending aesthetics)
   - *Colors* — 1–3 swatches + "for light or dark shirts?" toggle (contrast)
2. **Email gate** — before the first generation, capture email. Feeds
   `tsa_record_request` (leads + SMS hook) tagged `ai-studio`.
3. **Generate** — spinner (~10–30s) → 2–3 variations on a transparent
   checkerboard (shows it's cutout-ready).
4. **Iterate** — Regenerate / tweak inputs (each counts against the rate limit).
5. **Convert** — on the chosen design:
   - "Put it on apparel" → `/configurator/?design_id=X&t=TOKEN`
   - "Get a quote" → `/request-a-quote/?...&design_id=X&t=TOKEN`

The visitor never sees the raw prompt, the moderation step, or the provider.

## 4. Architecture

**New theme files** (all in existing, writable locations):
- `inc/ai-design-studio.php` — backend: AJAX endpoints, rate limiter, provider
  calls, design-save, REST owner-token guard. Included from `functions.php`.
- `template-ai-design-studio.php` — the `/design-studio/` page.
- `assets/js/ai-design-studio.js` — front-end.
- `assets/css/ai-design-studio.css` — styles (pink/gold, mobile-first).

**Plugin changes:** near-zero (see §5/§8). The read/hydrate/pricing/cart path
already supports `tsa_design`; we reuse it. The only enforcement we add
(owner-token) is done theme-side via a REST filter, so the plugin source and its
version/rebuild cycle are ideally left untouched.

**Two AJAX endpoints** (nonce-protected; API keys in `wp-config.php` constants,
never exposed to the browser):

### `tsa_ai_generate` (the metered endpoint)
1. Rate-limit check (email + IP) → reject early if over.
2. **Claude** — *moderation gate* (reject real trademarks / inappropriate
   content) **and** expand guided inputs into an optimized DTF image prompt.
3. **Image model** (`gpt-image-1`) — 2–3 variations, `background: transparent`,
   print-capable resolution.
4. Return temp preview URLs. Nothing persisted to the library yet.

### `tsa_ai_save` (on pick + CTA)
1. Sideload chosen PNG into the media library.
2. Create a **`tsa_design`** post (see §5) — `post_status = private`,
   `_source = customer_ai`, owner meta, and the bridge meta the configurator
   needs, set programmatically:
   - `_ac_configurator_active = '1'`
   - `_ac_allowed_zones = ['front_full']`
   - `_ac_print_cost = <DTF default>`
   - `_ac_fulfillment_partner = 'inhouse'`
   - `_ac_file_attachment_id = <sideloaded media id>`
   - `_design_preview_url` / `_design_print_file_url` (Library norm)
   - `_design_method = DTF`
3. Return `config_url` + `quote_url` (both carry the owner token).

**Cost optimization:** variations generated at standard quality for selection;
only the *saved* design needs the hi-res print asset — caps per-visitor spend.

## 5. Data model — provenance & ownership (VERIFIED APPROACH)

Verification confirmed the configurator reads the `tsa_design` CPT directly and
gates behavior by post type. Rather than a new CPT (which would require editing
the plugin's type allow-lists + rebuild), **AI designs are `tsa_design` posts
distinguished by meta and post status.** This reuses the fully-working
read/pricing/cart path with essentially no plugin surgery.

| | Curated Library | Customer AI |
|---|---|---|
| Post type | `tsa_design` | `tsa_design` |
| Post status | `publish` | **`private`** |
| `_source` | (unset) | `customer_ai` |
| Owner | store term | `_owner_user_id` / `_owner_email` + `_owner_token` |
| Public galleries / Featured / New | Yes | **No** (all those queries are `publish`-only) |
| Loadable by `?design_id=X` | Yes | Yes, **but only with matching `&t=TOKEN`** |
| Print-verified | Yes | No → routed through artwork-review |

**Why `private` is the right mechanism:** `route-designs.php` `get_designs()`
(galleries, categories, featured, new, store scoping) queries
`post_status = publish`, so a `private` design is automatically invisible
everywhere the public browses. But `get_design($id)` (the single-design hydrate
used by the `?design_id=` handoff) fetches by ID via `get_post()` and only
checks post type — so the direct token'd link still loads it. Private status
gives us "hidden from browse, reachable by direct owned link" for free.

**Meta added to the AI `tsa_design` post:**
- `_source = customer_ai`
- `_owner_user_id` (if logged in) and/or `_owner_email`
- `_owner_token` (random, unguessable — gates the handoff)
- `_ai_prompt` (stored for admin/proofing; never shown publicly)
- Standard bridge + preview meta from §4 so it "just works" in the configurator.

**Admin hygiene (theme-side, easy):**
- Exclude `_source = customer_ai` posts from the curated Designs list-table
  (`pre_get_posts` on the admin `tsa_design` query).
- Suppress the "new design uploaded" admin email
  (`tsa_notify_admin_new_design`) for `customer_ai` inserts.
- Optional: a separate admin submenu "AI Designs" for visibility.

**"Stays with their account":** ownership binds to email now. If the email maps
to a WooCommerce account (now or created later), designs associate and can
surface via the plugin's existing customer artwork library
(`_ac_saved_artwork_ids`, `class-my-account.php`). Guests can generate; nothing
is orphaned. Un-ordered AI designs auto-expire after N days.

## 6. Provider integration

- **Claude (text)** — moderation + prompt engineering. Server-side proxy pattern
  mirrors the Parabellum Anthropic proxy. Input length-capped and sanitized
  before send (prompt-injection guard).
- **Image model** — `gpt-image-1`, chosen for native transparent-background
  output and acceptable text rendering (team names/mascots). Alternative
  (Ideogram + background-removal) documented as a fallback if text fidelity
  proves insufficient.
- Keys as `wp-config.php` constants: `TSA_ANTHROPIC_KEY`, `TSA_IMAGE_API_KEY`.

## 7. Feature gating (customer-AI lane) — REUSES EXISTING PLUGIN SYSTEMS

The `customer_ai` source is limited to a safe subset. Most of this falls out of
the storage model; the proof gate reuses a system that already exists.

- **Owner-only load** — `?design_id=X` for a `customer_ai` design requires a
  matching `_owner_token`; enforced theme-side via a `rest_request_before_callbacks`
  (or equivalent) filter on `/ac/v1/designs/{id}`. Without a valid token the
  request 404s, preventing ID enumeration across customers. (Needed because
  `get_design()` does not itself check post status/ownership.)
- **Not featurable / not shareable / never in galleries or New/Featured** —
  automatic, because those are `publish`-only queries and AI designs are
  `private` with no store term.
- **Mandatory human proof before production — REUSE `artwork-review`.**
  `class-artwork-review.php` already implements an `artwork-review` WC order
  status with admin approve/reject, customer emails (submitted / approved /
  rejected / reminder), and saving approved art to the customer library. AI
  orders route into this exact flow: nothing prints until a human approves.
  Implementation: ensure any cart line carrying a `customer_ai` design forces
  the order into `artwork-review` (confirm/extend the existing trigger during
  the plan).
- **DTF-only** — `_ac_allowed_zones = ['front_full']`, method DTF, set at save.
- Auto-flag low-DPI on save (attach a note for the reviewer).

## 8. Configurator handoff contract — VERIFIED

- Front end: `src/index.jsx` reads `params.get('design_id')` → `preselectedDesignId`
  and hydrates via `fetchDesign(id)` → `/ac/v1/designs/{id}` (`api.js:29`).
  Confirmed working entry point.
- Theme passes `?design_id=<id>&t=<owner_token>` (plus `store` where relevant).
- Owner-token verified by the theme-side REST filter on `/ac/v1/designs/{id}`
  for `customer_ai` posts (§7). Curated designs are unaffected.

**RESOLVED (was the open blocker):** the plugin resolves `design_id` against the
`tsa_design` CPT (legacy `configurator_design` fallback), a design needs
`_ac_configurator_active` + bridge meta to function, and `get_design()` gates by
post type. Conclusion → store AI art as a `tsa_design` (private + `_source`
flag) with bridge meta set programmatically; add owner-token enforcement via a
theme REST filter. No plugin rebuild required for the read path.

## 9. Security

- Nonces on both AJAX endpoints; sanitize inputs, escape outputs.
- API keys server-side only (wp-config constants).
- Rate-limit by **both** email and IP (prevents trivial bypass).
- Owner-token (not just `private` status) gates every customer-AI handoff,
  because `get_design()` returns a post by ID without a status/cap check.
- Prompt length cap + injection stripping before Claude.
- Validate/normalize email; never trust client-supplied image URLs on save —
  only sideload from the provider response we generated.

## 10. Cost & abuse controls

- Email-gate before first generation.
- Per-email + per-IP daily cap (e.g. 5–10 generations; tunable via option).
- **Global daily circuit breaker** — hard ceiling on total generations/day to
  bound spend; over-limit shows "try later / request a custom design" CTA.
- Failed provider calls and moderation rejections do **not** consume the hi-res
  save budget; rate-limit accounting for rejections tunable.

## 11. Error handling

- Provider/API failure → friendly message; do not persist; soft rate-limit.
- Moderation rejection → explains no copyrighted/trademarked or inappropriate
  content; routes to "request a custom design from our team."
- Rate-limit hit → message + CTA to the existing design-request/quote flow.
- Generation timeout → graceful spinner timeout + retry option.
- Transparency/low-DPI failure on save → flag design for proof (never
  auto-approve).

## 12. Testing

- **Unit:** prompt builder, rate limiter, moderation gate, owner-token check
  (mocked providers).
- **Integration:** both AJAX endpoints with mocked provider responses; `tsa_design`
  save + bridge meta; REST owner-token filter returns 404 without token.
- **Manual:** end-to-end generate → save → configurator handoff → order forced
  into artwork-review; exceed rate limit; attempt a trademark; attempt to load
  another owner's `design_id` (must 404).

## 13. Config / keys

`wp-config.php`:
```php
define( 'TSA_ANTHROPIC_KEY', '...' );
define( 'TSA_IMAGE_API_KEY', '...' );
```
Tunable options: daily per-user cap, global daily ceiling, design auto-expiry
days, default DTF print cost.

## 14. Open questions / assumptions

- **[RESOLVED]** AC plugin `design_id` resolution + storage model (see §5/§8).
- Confirm during the plan whether a cart line's design triggers `artwork-review`
  automatically today, or whether we add a small trigger for `customer_ai` lines.
- Image provider final choice pending a quick output test (gpt-image-1 vs
  Ideogram + bg-removal) for text fidelity on team/school wordmarks.
- Exact daily caps (per-user + global) to be set with the owner based on
  expected traffic and acceptable API spend.
- Deployment: theme files edited on the network copy; owner uploads to the live
  server manually. If any plugin edit proves unavoidable, it also requires an
  AC_VERSION bump + rebuild + zip. Exact changed-file list provided at delivery.
