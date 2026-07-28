# Packaging a New Customer

How to stand up a brand-new print-shop tenant from this codebase. The system is
built to make this repeatable: **seed** the pages, **provision** the tier +
first store, and fill the **business profile** — most of it from two commands
and one wizard screen.

> **Model:** each customer runs as its own single-site install (own domain,
> own WooCommerce, own billing), cloned from a "golden" blueprint. The same
> steps also work inside a Multisite subsite — provisioning seeds it
> automatically — if you later consolidate onto a network.

---

## The short version

```
1. Stand up the site   (clone the blueprint, or fresh WP + WooCommerce + theme)
2. wp tsa seed          → core pages on the right templates, menus, front page
3. Provision Tenant     → tier + entitlements + first store + business profile
4. Post-launch checklist (domain, cache, email, branding)
```

## CLI quickstart (one command)

Once the site + theme are in place, `wp tsa setup` chains **seed → provision →
business profile** in a single call:

```bash
# If you cloned a blueprint, clear its demo store first:
wp tsa deprovision --slug=<demo-store-slug> --purge-store

# Stand the tenant up (seeds pages, applies tier, stamps first store, saves profile):
wp tsa setup \
  --name="New Co" --slug=newco --type=school --tier=pro \
  --primary="#123456" --secondary="#cccccc" \
  --email="owner@newco.com" --tagline="Custom apparel for New Co" \
  --phone="(555) 123-4567" --city="Austin" --state="Texas" \
  --service-area="Serving Central Texas & nationwide" --hours="Mon–Fri 9–5" \
  --instagram="https://instagram.com/newco"

# Add --skip-seed if the site was already seeded (re-running provision only).
```

Then finish in the admin: **Branding** (logo/colors), **SMTP**, **Form Emails**,
point the domain + SSL, and **purge cache**. Individual steps are detailed below.

---

## 1 · Stand up the site

**Option A — clone the blueprint (fastest).** Package the reference site with
Duplicator / All-in-One WP Migration, or a host-level staging clone, and deploy
it to the new site/host. You get WordPress, WooCommerce (with its pages),
plugins, the theme, and the base catalog/configurator in one shot.

If you cloned, strip the blueprint's demo store data first:

```
wp tsa deprovision --slug=<demo-store-slug> --purge-store
```

**Option B — fresh install.** Install WordPress + WooCommerce, activate this
theme (`tsa-child`), and install the supporting plugins (at minimum a caching
plugin — the reference uses LiteSpeed — and an SMTP plugin, see step 4).

---

## 2 · Seed the core pages

Creates the ~30 top-level pages (Home, Quote, Configurator, Services, policies,
Customer Portal, owner Command Center, …) each on its correct page-template,
sets the static front page, points WooCommerce **My Account → Customer Portal**,
builds the primary + footer menus, and enables pretty permalinks.

```
wp tsa seed --dry-run     # preview — makes no changes
wp tsa seed               # do it
```

No shell? Use **WP Admin → Platform → Seed Core Pages** (has a dry-run box).

Idempotent: safe to re-run. It matches pages by slug, never duplicates, never
overwrites your content — it only ensures each page is on the right template.
(This is what prevents the "page on the wrong template silently breaks its
feature" class of bug.)

---

## 3 · Provision the tenant

**WP Admin → Platform → Provision Tenant** — one screen:

- **Business name, slug** — the tenant identity.
- **Store type** — School / Team / Business.
- **Tier** — Starter / Pro / Studio (sets the feature entitlements).
- **Mascot, brand colors, tagline, status, spotlight** — first store branding.
- **Business profile** — legal name, phone, city/state, service area, hours,
  social links. These feed the Contact page, footer, and policy pages, so the
  site shows *this* customer's details instead of placeholders.

Click **Preview** to sanity-check, then **Provision**. It applies the tier,
records tenant identity, stamps the first store (its `/schools/{slug}/` pages,
design-store term, cart routing), and saves the business profile.

CLI equivalent (branding/profile via the wizard afterward):

```
wp tsa provision --name="New Co" --slug=newco --type=school --tier=pro \
  --primary=#123456 --secondary=#cccccc --email=owner@newco.com
```

Re-running the same slug **updates** rather than duplicates.

---

## 4 · Post-launch checklist

- [ ] **Domain** — point the customer's domain at the site; update WordPress
      Address / Site Address if needed.
- [ ] **Branding** (Platform → Branding) — set brand name, logo, and colors if
      not already provisioned. Upload the site logo (also used in the footer).
- [ ] **Business Profile** (Platform → Business Profile) — confirm/adjust the
      fields captured during provisioning; add any socials.
- [ ] **Email deliverability** — configure an SMTP/transactional provider so
      form notifications and bulk email land. See `90-email-deliverability.md`.
- [ ] **Form email routing** (Settings → TSA Form Emails) — set where quote /
      contact / store / party / fundraiser submissions go.
- [ ] **WooCommerce** — payment gateway, tax, shipping zones, store address.
- [ ] **Verify pages** — open Home, Quote (upload works), Configurator, Contact
      (shows the new name/location/phone), and the footer (name, socials,
      policies).
- [ ] **Purge cache** — LiteSpeed Cache → **Purge All**, then hard-refresh.
      Most "it didn't update" moments are just the page cache.

---

## Verify it worked

- **Command Center** (owner dashboard page) lists the tools, incl. Bulk Email.
- **Contact** page shows the tenant's name, location, and (if set) phone.
- **Footer** shows the tenant name, tagline, and only the social icons you set.
- **Policies** (Privacy, Terms, Shipping) name the tenant + its domain, and
  Terms cites the tenant's state as governing law.
- **Platform** shows the applied tier and entitled features.

---

## Troubleshooting

- **A page shows the wrong layout / a feature is dead** → its Page is on the
  wrong template. Re-run `wp tsa seed` (or the admin button) — it repairs the
  template assignment without touching content.
- **Changes don't show live** → LiteSpeed Cache → Purge All, then hard-refresh.
- **Contact/footer still show the old shop's details** → fill in Platform →
  Business Profile (or re-run the provisioning wizard) and purge cache.
- **A cloned site still lists demo customers/stores** → `wp tsa deprovision
  --slug=<demo> --purge-store` (records go to Trash, recoverable).

---

## What's still per-customer manual

Some bespoke marketing copy remains as editable default text (e.g. the About
page's founding story and impact stats). Identity tokens (name, location,
domain, state) across Contact, footer, and policy pages are already
config-driven via the Business Profile — but a customer with a very different
story will still want to edit the About narrative.
