# Store Shipping Repair Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** Make configured TSA store pickup available independently of WooCommerce zone rates while retaining eligible WooCommerce delivery rates.

**Architecture:** Keep WooCommerce package calculation intact and apply a deterministic TSA rate policy in `inc/store-cart.php`. Cover the policy through a standalone PHP regression harness with minimal WordPress/WooCommerce stubs.

**Tech Stack:** PHP, WordPress hooks, WooCommerce shipping packages and `WC_Shipping_Rate`.

## Global Constraints

- WooCommerce remains the source of zone-based shipping methods.
- Store pickup must not depend on a matching WooCommerce zone method.
- Customer-facing output must not expose diagnostics.
- No unrelated refactoring.

---

### Task 1: Shipping-rate regression coverage

**Files:**
- Create: `tests/store-cart-shipping-test.php`
- Test: `tests/store-cart-shipping-test.php`

**Interfaces:**
- Consumes: `tsa_per_store_shipping_rates(array $rates, array $package): array`
- Produces: executable regression coverage for pickup, shipping, pickup-only, fallback, and general-cart behavior.

- [ ] **Step 1: Write the failing test harness**

Create lightweight WordPress hook/option stubs, a `WC_Shipping_Rate` test double, load `inc/store-cart.php`, and assert that a package carrying `tsa_store => dutchtown` receives its configured pickup even when the incoming rate array is empty.

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/store-cart-shipping-test.php`

Expected: failure identifying the missing deterministic policy/diagnostic behavior.

- [ ] **Step 3: Add remaining policy assertions**

Assert that shipping-enabled stores preserve non-pickup WooCommerce rates, pickup-only stores remove them, empty configurations retain defaults, and an unconfigured/general package is unchanged.

### Task 2: Deterministic store shipping policy

**Files:**
- Modify: `inc/store-cart.php:186-235`
- Test: `tests/store-cart-shipping-test.php`

**Interfaces:**
- Consumes: package `tsa_store`, `tsa_store_delivery_cfg()`, incoming WooCommerce rates.
- Produces: store-filtered `WC_Shipping_Rate[]` and admin-safe diagnostic metadata.

- [ ] **Step 1: Implement the minimal rate policy**

Normalize pickup labels, construct free `WC_Shipping_Rate` objects, preserve non-pickup WooCommerce rates only when store shipping is enabled, and preserve defaults for an empty configuration.

- [ ] **Step 2: Add an admin-only checkout diagnostic**

Render the resolved store key and available rate IDs only for users with `manage_woocommerce`; include no address data.

- [ ] **Step 3: Run the regression harness**

Run: `php tests/store-cart-shipping-test.php`

Expected: all assertions pass and process exits 0.

### Task 3: Store settings markup and final verification

**Files:**
- Modify: `inc/store-cart.php:145-151`

**Interfaces:**
- Consumes: recognized store slug array.
- Produces: correctly escaped individual `<code>` elements.

- [ ] **Step 1: Correct recognized-store rendering**

Escape each slug individually before joining the code elements, avoiding escaped literal markup.

- [ ] **Step 2: Verify PHP syntax**

Run: `php -l inc/store-cart.php` and `php -l tests/store-cart-shipping-test.php`.

Expected: `No syntax errors detected` for both files.

- [ ] **Step 3: Re-run all regression checks**

Run: `php tests/store-cart-shipping-test.php`.

Expected: all assertions pass with exit code 0.

## Self-review

The plan covers all approved design behaviors. It introduces no new production dependencies, changes only the shipping/settings module, and includes no unresolved placeholders.

