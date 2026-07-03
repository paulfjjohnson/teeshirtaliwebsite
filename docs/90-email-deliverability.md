# Email Deliverability & SMTP

If form notifications (Contact, Quote, Store, Tee Party, Fundraiser, Design "Customize It") aren't arriving, the problem is almost always **deliverability**, not the form. This page explains why and how to fix it for good.

## Why WordPress email gets lost

By default WordPress sends mail with PHP's built-in `mail()` function. On shared hosting that mail:

- comes from an **unauthenticated** address (e.g. `wordpress@teeshirtali.com`), and
- has no **SPF/DKIM** signature proving it's really from your domain.

Gmail, Outlook, and most providers respond by sending it to **spam** — or silently **dropping** it. Nothing is wrong with the form; the message just never lands in the inbox.

> Quick test: do your **Contact** and **Quote** emails arrive? If *none* of them do, it's site-wide deliverability (this page). If only one type is missing, check that recipient's address in **Settings → TSA Form Emails**.

## The fix: send through SMTP

Route all WordPress email through an authenticated **SMTP** sender instead of PHP `mail()`. One-time setup:

1. Install the free **WP Mail SMTP** plugin (Plugins → Add New → search "WP Mail SMTP").
2. Pick a sender:
   - **Hostinger email** — create a mailbox like `no-reply@teeshirtali.com` in hPanel, then use its SMTP host/port/credentials. Simplest if you're on Hostinger.
   - **Transactional service** — Brevo, SendGrid, or Mailgun. Best deliverability and free tiers; recommended once volume grows.
3. Set the **From Email** to a real address on your domain (e.g. `no-reply@teeshirtali.com`) and a **From Name** like `Tee Shirt Ali`.
4. Add **SPF** and **DKIM** DNS records for your domain (the SMTP provider gives you these — usually a TXT record or two).
5. Use the plugin's **Email Test** tab to send yourself a test.

After this, **every** site email — order receipts, form notifications, password resets — uses the authenticated sender and lands in the inbox.

## Recommended From address

Use a dedicated, real mailbox you don't mind being public:

| Field | Value |
|-------|-------|
| From Email | `no-reply@teeshirtali.com` |
| From Name | `Tee Shirt Ali` |
| Reply-To | left as-is — the system sets it to the customer's address so you can reply directly |

Do **not** use a Gmail/Yahoo address as the From — sending "as" Gmail from your server fails their checks and gets blocked.

## Where the recipients are set

Who *receives* each form is separate from *how* it's sent. Recipients live in **Settings → TSA Form Emails** (one address — or several, comma-separated — per form). SMTP only controls delivery; it doesn't change who gets the message.

## Two backups that don't depend on email

Even with email working, the platform keeps a copy so nothing is ever lost:

- **Requests inbox** — Contact, Quote, Store, Tee Party, Fundraiser, and Design "Customize It" submissions are saved under **wp-admin → Requests** and shown on the dashboard, regardless of whether the email arrives.
- **SMS alerts** — turn on **Settings → TSA Alerts** to get a text when a request or order comes in.

So if an email is ever delayed or filtered, you'll still see the request in the dashboard (and optionally by text) right away.
