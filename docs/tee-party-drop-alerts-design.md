# Tee Party — Customer Drop Alerts (Email) — Design

**Date:** 2026-06-27
**Status:** Approved design, pending implementation plan
**Author:** Paul + Claude
**Theme:** tsa-child

---

## 1. Goal

Let a customer subscribe to a Tee Party and be emailed **when the party opens** and
**each time a batch of designs drops**. Replaces the current placeholder
"Get Notified" buttons (which link to `/contact/` and do nothing real).

Email-only for v1. SMS is a deliberate future layer (the existing owner-side
`tsa_send_sms()` infra can be reused later) and is **out of scope here**.

## 2. Scope

**In scope**
- Email capture form on the **Tee Party page** — upcoming/closed state (replaces the
  `/contact/` "Get Notified" button) and a compact inline form in the live state.
- Store subscribers per party.
- Send a "now live" email when the party opens.
- Send a "new designs dropped" email at each drop reveal (with batch thumbnails/names).
- One-click unsubscribe (CAN-SPAM).

**Out of scope (v1)**
- SMS / any non-email channel.
- Subscribe capture on the hub cards, school store, or school landing/drops pages.
- Double opt-in (single opt-in for v1).
- An admin UI for managing/exporting subscribers (can read meta directly if needed).

## 3. Data model

Subscribers are stored as **non-unique post-meta rows** on the party page (a `page`
using `template-tee-party.php`):

- `_tsa_party_subscriber` — one meta row **per subscriber**, value = lowercased email.
  Using non-unique rows (`add_post_meta` / `get_post_meta($pid, $key, false)`) avoids the
  read-modify-write race a single serialized array would have under concurrent submits.

Idempotency / state flags (single rows):
- `_tsa_party_notified_open` = `'1'` once the open email has been sent.
- `_tsa_party_notified_drops` = JSON array of reveal timestamps already emailed.

No new database table. (If a list ever outgrows post meta, migrate behind the same
helper functions — see §10.)

## 4. Components

### 4.1 Subscribe form (front end)
- Rendered in `template-tee-party.php`:
  - **Upcoming / closed state:** replaces the `tsa_btn('/contact/', 'Get Notified', …)`
    with an email input + "Notify me" button.
  - **Live state:** a compact "Email me each drop" inline form near the spotlight /
    next-drop area.
- Posts via AJAX to `admin-ajax.php?action=tsa_party_subscribe` with `party_id`, `email`,
  and a nonce. Progressive-enhancement: the form also works as a normal POST fallback.

### 4.2 Subscribe handler
- `wp_ajax_tsa_party_subscribe` + `wp_ajax_nopriv_tsa_party_subscribe`.
- Validates nonce + `is_email()`; confirms the post is a real party page.
- Adds `_tsa_party_subscriber` row if that email isn't already subscribed (dedupe).
- Returns JSON `{ success, message }` ("You're on the list — watch your inbox.").

### 4.3 Triggers
Both reuse the party engine's scheduling pattern and are **idempotent**.

- **Party opens** — `tsa_party_open_event` scheduled (one-time) at the party start time
  when the party is saved active. Handler emails all subscribers the "now live" email,
  then sets `_tsa_party_notified_open`. Lazy fallback: the REST endpoint
  (`tsa_rest_get_party`) calls the send-if-pending function when it first sees status flip
  to `live`/`grace`, covering a missed WP-Cron run.
- **Each drop** — `party-alerts.php` adds its own `add_action('tsa_party_drop_event', …)`
  (the event already exists and fires per reveal time). For each reveal timestamp that has
  passed and is **not** in `_tsa_party_notified_drops`, send the drop email for that
  batch's designs and append the timestamp to the notified list. The REST lazy path also
  calls this, so emails still go out if cron is late — the per-timestamp guard makes
  repeat calls safe (never double-send).

### 4.4 Unsubscribe
- Every email footer includes: `home_url('/?tsa_unsub=<token>&e=<email>&p=<party_id>')`.
- `token = hash_hmac('sha256', strtolower($email) . '|' . $party_id, wp_salt('auth'))`.
- An `init`/`template_redirect` handler validates the token (constant-time compare),
  deletes the matching `_tsa_party_subscriber` row, and shows a small confirmation notice.

### 4.5 Emails
- Sent with `wp_mail`, HTML, branded with the party name + per-party accent colors
  (same meta the party page uses). Plain-text alt acceptable.
- **Open email:** subject "{Party} is live — new designs are dropping", CTA → party page.
- **Drop email:** subject "{N} new designs just dropped in {Party}", body shows the
  batch's design names + thumbnails, CTA → party page.
- Both include the unsubscribe link.

## 5. Data flow

```
Visitor → party page form → AJAX tsa_party_subscribe → _tsa_party_subscriber meta row
Party start time reached → tsa_party_open_event (cron) ── or ── REST lazy fallback
        → send open email to all subscribers (guard _tsa_party_notified_open)
Reveal time reached → tsa_party_drop_event (cron) ── or ── REST lazy fallback
        → for each un-notified reveal ts: send drop email (guard _tsa_party_notified_drops)
Email footer → /?tsa_unsub=token… → validate HMAC → delete subscriber row
```

## 6. Idempotency & safety

- Open email gated by `_tsa_party_notified_open`.
- Drop emails gated per reveal timestamp via `_tsa_party_notified_drops`.
- Subscribe dedupes on email; unsubscribe is HMAC-verified.
- Sends run inside the cron event (and guarded lazy fallback); fine for the school-sized
  lists expected. Batching can be added later if a list grows large.

## 7. Files

- **NEW** `inc/party-alerts.php` — self-contained: subscribe AJAX, unsubscribe handler,
  send functions, `tsa_party_open_event` scheduling (on party save) + handler, and the
  `tsa_party_drop_event` listener.
- `functions.php` — include `inc/party-alerts.php`.
- `template-tee-party.php` — replace the placeholder "Get Notified" button (upcoming/
  closed) and add the compact live-state form.
- `inc/tee-party.php` — only if needed to expose a helper or add the lazy send call in
  `tsa_rest_get_party`; prefer hooking from `party-alerts.php` to keep it self-contained.

## 8. Compliance

- Single opt-in with explicit action (enter email + submit).
- Unsubscribe link in every email (CAN-SPAM).
- Email validated + nonce-protected on capture.

## 9. Testing / verification (manual, staging)

1. Subscribe with a test email on a party page → success message; meta row exists.
2. Re-submit same email → no duplicate row.
3. "Reveal Now" a drop + save → exactly one drop email; re-load/poll the party page →
   **no** second email (idempotency).
4. Cross a party start time → one "now live" email.
5. Click unsubscribe link → row removed, confirmation shown; further drops don't email it.

## 10. Future (not now)

- SMS channel via `tsa_send_sms()` + TCPA opt-in/consent.
- Capture on hub cards / school pages.
- Subscriber admin list + CSV export.
- Migrate storage to a `wp_tsa_party_subscribers` table behind the same helpers if lists
  grow large.
