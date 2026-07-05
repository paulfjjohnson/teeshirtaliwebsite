# TSA AI Design Studio — Design Spec

**Date:** 2026-07-04
**Status:** Draft for review
**Site:** teeshirtali.com (tsa-child theme)

---

## 1. Goal

Add a **public, AI-powered design generator** to teeshirtali.com. A non-designer
(PTA parent, coach, small-business owner) describes what they want through a
guided form; the tool generates print-ready, transparent-background artwork
suitable for DTF, and hands it off to the existing Apparel Configurator or the
Quote form so it can be ordered.

This is the top trending 2026 feature for custom-apparel / print-on-demand
platforms (an estimated 40–65% of POD sellers now use AI design generation) and
it uniquely leverages infrastructure TSA already owns: the design CPT/meta
shape, the `?design_id=` configurator handoff, the Requests/leads + SMS
pipeline, and the customer portal.

## 2. Scope

**In scope**
- Public homepage-class tool at `/design-studio/`, email-gated + rate-limited.
- Guided inputs → Claude-assisted prompt + moderation → image model → 2–3
  transparent, print-ready variations.
- Customer-owned storage of generated designs (new `tsa_ai_design` CPT), private
  to the owner, with provenance/source awareness.
- Handoff into the Apparel Configurator and Quote form.
- Mandatory human proof gate before any customer-AI design prints.

**Out of scope (this spec)**
- Roster-based group ordering (separate feature).
- Changes to the curated `tsa_design` library behavior.
- Vector/true-color-separation output (raster DTF only).
- Full account system (we ride the existing WooCommerce account + guest email).

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

The visitor never sees the raw prompt, the moderation step, or the provider —
it presents as a branded TSA tool.

## 4. Architecture

**New files** (all in existing, writable locations):
- `inc/ai-design-studio.php` — backend: AJAX endpoints, rate limiter, provider
  calls, design-save, CPT registration. Included from `functions.php`.
- `template-ai-design-studio.php` — the `/design-studio/` page.
- `assets/js/ai-design-studio.js` — front-end.
- `assets/css/ai-design-studio.css` — styles (pink/gold, mobile-first).

**Two AJAX endpoints** (nonce-protected; API keys in `wp-config.php` constants,
never exposed to the browser):

### `tsa_ai_generate` (the metered endpoint)
1. Rate-limit check (email + IP) → reject early if over.
2. **Claude** call — *moderation gate* (reject real trademarks / inappropriate
   content) **and** expand guided inputs into an optimized DTF image prompt.
3. **Image model** (`gpt-image-1`) — 2–3 variations, `background: transparent`,
   print-capable resolution.
4. Return temp preview URLs. Nothing persisted to the library yet.

### `tsa_ai_save` (on pick + CTA)
1. Sideload chosen PNG into the media library.
2. Create a `tsa_ai_design` record (print file + preview + colors + owner + token).
3. Return `config_url` + `quote_url` (both carry the owner token).

**Cost optimization:** variations generated at standard quality for selection;
only the *saved* design needs the hi-res print asset — caps per-visitor spend.

## 5. Data model — provenance & ownership

Design provenance becomes a first-class attribute. The configurator already
distinguishes `configurator_design` vs `tsa_design`; we add a third, restricted
lane.

| | Curated Library (`tsa_design`) | Customer AI (`tsa_ai_design`) |
|---|---|---|
| Created by | Admin / coordinators | Customers via the Studio |
| Owner | The store | The customer (account or email token) |
| Visibility | Public/store galleries, Featured/New | Private to that one owner |
| Reusable across stores | Yes | No |
| Print-verified | Yes | No → **human proof required** |
| Configurator features | Full | Limited (§7) |

**`tsa_ai_design` CPT** — same meta shape as `tsa_design` so it reuses the
print-file/preview fields:
- `_design_print_file_url` / `_design_print_file_id`
- `_design_preview_url` / `_design_preview_id`
- `_design_colors`, `_design_method` (DTF)
- `_source = customer_ai`
- `_owner_user_id` (if logged in) and/or `_owner_email`
- `_owner_token` (random, unguessable — gates the handoff)
- `_ai_prompt` (stored for admin/proofing; never shown publicly)
- Post status `private`; auto-expire after N days if never ordered.

**"Stays with their account":** ownership binds to email now. If the email maps
to a WooCommerce account (now or created later), designs link and surface in
`template-customer-portal.php`. Guests can generate; nothing is orphaned.

## 6. Provider integration

- **Claude (text)** — moderation + prompt engineering. Server-side proxy pattern
  mirrors the Parabellum Anthropic proxy. Input length-capped and sanitized
  before send (prompt-injection guard).
- **Image model** — `gpt-image-1`, chosen for native transparent-background
  output and acceptable text rendering (team names/mascots). Alternative
  (Ideogram + background-removal) documented as a fallback if text fidelity
  proves insufficient.
- Keys as `wp-config.php` constants: `TSA_ANTHROPIC_KEY`, `TSA_IMAGE_API_KEY`.

## 7. Feature gating (customer-AI lane)

The configurator must recognize `_source = customer_ai` and enforce:
- **Owner-only load** — `design_id` requires matching `_owner_token`; otherwise
  refused (prevents ID enumeration across customers).
- **Not featurable, not store-shareable, never in New/collections/galleries.**
- **Mandatory human proof before production** (hard gate — approved).
- **DTF-only** decoration method.
- Auto-flag low-DPI on save.

## 8. Configurator handoff contract

The theme passes: `?design_id=<id>&t=<owner_token>` (and existing `store` where
relevant). The configurator resolves the design, verifies the token for
`customer_ai` sources, applies the §7 limits, and blocks production until proof
approval.

**⚠️ OPEN INTEGRATION SEAM (must verify before the implementation plan):**
The Design Library builds `config_url` as `/configurator/?design_id=POSTID`, but
the AC plugin has its own `configurator_design` CPT (seen in the library's
migration code). Before finalizing the plan, verify against the AC plugin source
(`C:\Users\paulf\Downloads\ac-build\`):
1. How the plugin resolves `design_id` — arbitrary post ID, print-file URL, or
   specifically a `configurator_design`.
2. What source/capability hooks already exist that we can extend for the new
   `customer_ai` source and the owner-token check.

This determines whether `tsa_ai_save` writes a `tsa_ai_design`, a
`configurator_design`, or passes an image URL. **Highest-risk item.**

## 9. Security

- Nonces on both AJAX endpoints; sanitize inputs, escape outputs.
- API keys server-side only (wp-config constants).
- Rate-limit by **both** email and IP (prevents trivial bypass).
- Owner-token (not just `private` status) gates every customer-AI handoff.
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
- **Integration:** both AJAX endpoints with mocked provider responses; CPT
  save + meta.
- **Manual:** end-to-end generate → save → configurator handoff; exceed rate
  limit; attempt a trademark; attempt to load another owner's `design_id`.

## 13. Config / keys

`wp-config.php`:
```php
define( 'TSA_ANTHROPIC_KEY', '...' );
define( 'TSA_IMAGE_API_KEY', '...' );
```
Tunable options: daily per-user cap, global daily ceiling, design auto-expiry
days, "New" is not applicable (customer-AI never surfaces publicly).

## 14. Open questions / assumptions

- **[BLOCKER for plan]** AC plugin `design_id` resolution + source hooks (§8) —
  verify against `ac-build` source before writing the implementation plan.
- Image provider final choice pending a quick output test (gpt-image-1 vs
  Ideogram+bg-removal) for text fidelity on team/school wordmarks.
- Exact daily caps (per-user + global) to be set with the owner based on
  expected traffic and acceptable API spend.
- Deployment: files are edited on the network theme copy; owner uploads to the
  live server manually (per standing workflow). Exact changed-file list provided
  at delivery.
