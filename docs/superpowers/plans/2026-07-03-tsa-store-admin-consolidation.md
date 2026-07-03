# TSA Store Admin Consolidation + Team/Business Launch Implementation Plan

> **For agentic workers:** REQUIRED SUB-SKILL: Use superpowers:subagent-driven-development (recommended) or superpowers:executing-plans to implement this plan task-by-task. Steps use checkbox (`- [ ]`) syntax for tracking.

**Goal:** One admin screen (TSA Store Builder) for creating/editing any store of any type, and Team/Business stores flipped from "planned" to fully live.

**Architecture:** Extend the existing `inc/school-builder.php` admin page with a Store type selector and type-conditional fields, generalize its hardcoded `/schools/` URL logic to resolve per type, hide the now-redundant native "Stores" screen (theme-level only, doesn't touch the shared plugin), and flip two feature-lifecycle flags in `inc/platform.php`. No new storefront template is needed — `template-store-premium.php` is already store-type-agnostic (2026-06-30 spec).

**Tech Stack:** WordPress/PHP (no framework), plain-script tests (`tests/*.php`, pattern established by `tests/store-cart-shipping-test.php` — stub minimal WP functions, `require` the file under test, assert with a tiny `tsa_assert()` helper, run via `php tests/<name>.php`).

**Spec:** `docs/superpowers/specs/2026-07-03-tsa-store-admin-consolidation-design.md`

---

## File Structure

- **Modify `inc/school-builder.php`** — the whole admin consolidation lives here: three new pure helper functions (type choices, type→base/label/template mapping, active-from-status derivation), a Store type field, a Description field, type-conditional field visibility, generalized URL/parent-page logic, an all-types store listing, and the native-menu removal + link-out.
- **Create `tests/school-builder-type-test.php`** — unit tests for the three new pure functions, following the existing `tests/store-cart-shipping-test.php` pattern exactly.
- **Modify `inc/platform.php`** — flip `stamp_team` and `stamp_business` lifecycle to `'ga'`.
- **Modify `template-store-premium.php`** — type-aware hero subhead copy.

No files are deleted. The native "Stores" screen (plugin-owned `class-cpt-store.php`) is not modified — only hidden from the admin menu via a theme-level hook, and only reachable through a link the Builder now provides.

---

### Task 1: Pure helpers — type choices, type metadata, active-from-status

**Files:**
- Modify: `inc/school-builder.php` (add functions near the top, after `tsa_school_levels()` at line 26)
- Test: `tests/school-builder-type-test.php`

- [ ] **Step 1: Write the failing test**

Create `tests/school-builder-type-test.php`:

```php
<?php
declare(strict_types=1);

define( 'ABSPATH', __DIR__ . '/' );

function tsa_assert( bool $condition, string $message ): void {
    if ( ! $condition ) {
        fwrite( STDERR, "FAIL: {$message}\n" );
        exit( 1 );
    }
}

// Only the three pure functions under test are needed — extract them by
// requiring the full file (WP function stubs below cover what it touches
// at load time: add_action/add_shortcode registration calls).
function add_action( $hook, $callback, $priority = 10, $accepted_args = 1 ) {}
function add_shortcode( $tag, $callback ) {}

require dirname( __DIR__ ) . '/inc/school-builder.php';

// ── tsa_sb_type_choices() ──
$choices = tsa_sb_type_choices();
tsa_assert( $choices === [
    'school'   => 'School',
    'team'     => 'Team',
    'business' => 'Business',
    'event'    => 'Event',
    'main'     => 'TSA Main',
], 'Type choices must be the 5 types the plugin already allows, in a stable order.' );

// ── tsa_sb_type_meta() ──
$school = tsa_sb_type_meta( 'school' );
tsa_assert( $school['base'] === 'schools', 'School base segment must be "schools".' );
tsa_assert( $school['label'] === 'Schools', 'School label must be "Schools".' );
tsa_assert( $school['directory_template'] === 'template-school-directory.php', 'School directory template must match the existing file.' );

$team = tsa_sb_type_meta( 'team' );
tsa_assert( $team['base'] === 'teams', 'Team base segment must be "teams".' );
tsa_assert( $team['directory_template'] === 'template-team-directory.php', 'Team directory template must match the existing file.' );

$business = tsa_sb_type_meta( 'business' );
tsa_assert( $business['base'] === 'business', 'Business base segment must be "business".' );
tsa_assert( $business['directory_template'] === 'template-business-directory.php', 'Business directory template must match the existing file.' );

$event = tsa_sb_type_meta( 'event' );
tsa_assert( $event['base'] === 'events', 'Event base segment must be "events".' );
tsa_assert( $event['directory_template'] === '', 'Event has no directory template yet — must be empty, not a guess.' );

$main = tsa_sb_type_meta( 'main' );
tsa_assert( $main['base'] === 'schools', 'Main is a singleton with no section of its own — falls back to schools.' );

$unknown = tsa_sb_type_meta( 'nonsense' );
tsa_assert( $unknown['base'] === 'schools', 'Unknown type must fall back to the school config, not error.' );

// ── tsa_sb_active_from_status() ──
tsa_assert( tsa_sb_active_from_status( 'hidden' ) === '0', 'Hidden status must derive to inactive.' );
tsa_assert( tsa_sb_active_from_status( 'live' ) === '1', 'Live status must derive to active.' );
tsa_assert( tsa_sb_active_from_status( 'coming-soon' ) === '1', 'Coming-soon status must derive to active (shown, just not shoppable).' );
tsa_assert( tsa_sb_active_from_status( 'garbage' ) === '1', 'Unrecognized status must default to active, matching the Builder\'s own coming-soon default.' );

echo "PASS: school-builder type helpers\n";
```

- [ ] **Step 2: Run test to verify it fails**

Run: `php tests/school-builder-type-test.php`
Expected: PHP fatal error — `Call to undefined function tsa_sb_type_choices()` (the functions don't exist yet).

- [ ] **Step 3: Write the minimal implementation**

Modify `inc/school-builder.php` — insert immediately after `tsa_school_levels()` (currently lines 24–26):

```php
/** Directory level groups (match the directory template's group headings). */
function tsa_school_levels(): array {
    return [ 'High Schools', 'Middle & Elementary Schools', 'Primary Schools' ];
}

/**
 * The 5 store types the plugin's native "Store Details" box already allows
 * (class-cpt-store.php save_meta()). Single source of truth for the Builder's
 * type dropdown so the two screens can never drift out of sync on valid values.
 */
function tsa_sb_type_choices(): array {
    return [
        'school'   => 'School',
        'team'     => 'Team',
        'business' => 'Business',
        'event'    => 'Event',
        'main'     => 'TSA Main',
    ];
}

/**
 * Per-type provisioning metadata: the URL base segment (/<base>/<slug>/),
 * the section's display label, and its directory-listing page template
 * (empty when none exists yet — caller must not assign a template in that
 * case, not guess one). Unknown types fall back to school's config so
 * provisioning never errors on a bad value.
 */
function tsa_sb_type_meta( string $type ): array {
    $map = [
        'school'   => [ 'base' => 'schools',  'label' => 'Schools',  'directory_template' => 'template-school-directory.php' ],
        'team'     => [ 'base' => 'teams',    'label' => 'Teams',    'directory_template' => 'template-team-directory.php' ],
        'business' => [ 'base' => 'business', 'label' => 'Business', 'directory_template' => 'template-business-directory.php' ],
        'event'    => [ 'base' => 'events',   'label' => 'Events',   'directory_template' => '' ],
        'main'     => [ 'base' => 'schools',  'label' => 'Schools',  'directory_template' => 'template-school-directory.php' ],
    ];
    return $map[ $type ] ?? $map['school'];
}

/**
 * Derive the plugin's legacy "Active" flag (_ac_is_active) from the Builder's
 * richer 3-state Status field, so admins only ever set one status, not two.
 * Hidden = inactive (configurator should error if visited); live/coming-soon
 * (and anything unrecognized) = active.
 */
function tsa_sb_active_from_status( string $status ): string {
    return $status === 'hidden' ? '0' : '1';
}
```

- [ ] **Step 4: Run test to verify it passes**

Run: `php tests/school-builder-type-test.php`
Expected: `PASS: school-builder type helpers`

- [ ] **Step 5: Commit**

```bash
git add inc/school-builder.php tests/school-builder-type-test.php
git commit -m "Add store-type and active-status helper functions"
```

---

### Task 2: Store type field + folded-in Active status

**Files:**
- Modify: `inc/school-builder.php`

- [ ] **Step 1: Add `type` to the form defaults**

In `tsa_store_builder_page()`, the defaults array (lines 136–143):

```php
    $form   = [
        'name' => '', 'slug' => '', 'type' => 'school', 'mascot' => '', 'level' => 'High Schools',
        'status' => 'coming-soon', 'spotlight' => 0,
        'primary' => '', 'secondary' => '', 'logo_id' => 0, 'tagline' => '',
        'ticker' => '',
        'pickups' => '', 'shipping' => 0, 'contact_email' => '',
        'programs' => [],
    ];
```

- [ ] **Step 2: Add the Store type field to the form HTML**

In the "Identity" table (starts at line 204), insert a new first row before "School name" (line 205–206):

```php
                    <table class="form-table"><tbody>
                        <tr><th><label for="tsa_sb_type">Store type</label></th>
                            <td><select name="tsa_sb_type" id="tsa_sb_type">
                                <?php foreach ( tsa_sb_type_choices() as $tk => $tl ) : ?>
                                <option value="<?php echo esc_attr( $tk ); ?>" <?php selected( $form['type'], $tk ); ?>><?php echo esc_html( $tl ); ?></option>
                                <?php endforeach; ?>
                            </select></td></tr>
                        <tr><th><label for="tsa_sb_name">Name</label></th>
                            <td><input name="tsa_sb_name" id="tsa_sb_name" type="text" class="regular-text" value="<?php echo esc_attr( $form['name'] ); ?>" placeholder="Dutchtown High School" required></td></tr>
```

(Only the `<th>` label text changes on the existing "School name" row — from "School name" to "Name" — since the field now applies to every store type. The `id`/`name` attribute `tsa_sb_name` stays exactly as-is; nothing else references "School name" as a string.)

Also remove the now-inaccurate hardcoded `<input type="hidden" name="tsa_store_type" value="school">` at line 198 — it's dead weight now that the real type comes from the new `tsa_sb_type` select.

- [ ] **Step 3: Show/hide school-only fields by type with JS**

School-only rows are: Mascot, Level (Identity table), and the entire "Delivery & contact" table + "Programs" section. Tag each with `data-school-only="1"` on the `<tr>` (or wrapping element for the Programs section, which isn't a `<tr>`).

Mascot/Level rows (lines 210–217), add the attribute:

```php
                        <tr data-school-only="1"><th><label for="tsa_sb_mascot">Mascot</label></th>
                            <td><input name="tsa_sb_mascot" id="tsa_sb_mascot" type="text" class="regular-text" value="<?php echo esc_attr( $form['mascot'] ); ?>" placeholder="Griffins"></td></tr>
                        <tr data-school-only="1"><th><label for="tsa_sb_level">Level</label></th>
                            <td><select name="tsa_sb_level" id="tsa_sb_level">
                                <?php foreach ( $levels as $lv ) : ?>
                                <option value="<?php echo esc_attr( $lv ); ?>" <?php selected( $form['level'], $lv ); ?>><?php echo esc_html( $lv ); ?></option>
                                <?php endforeach; ?>
                            </select></td></tr>
```

"Delivery & contact" heading + table (lines 251–261) and "Programs" heading + everything through the add-program button (lines 263–279): wrap each block in `<div data-school-only="1">...</div>` rather than tagging individual rows, since these are whole `<h2>` + `<table>` / `<h2>` + free-form sections. Example for Delivery & contact:

```php
                    <div data-school-only="1">
                    <h2 class="title" style="font-size:14px;text-transform:uppercase;letter-spacing:.5px;color:#555">Delivery &amp; contact</h2>
                    <table class="form-table"><tbody>
                        <tr><th><label for="tsa_sb_pickups">Pickup location(s)</label></th>
                            <td><textarea name="tsa_sb_pickups" id="tsa_sb_pickups" rows="3" class="large-text code" placeholder="Dutchtown HS — 13165 Hwy 73, Geismar"><?php echo esc_textarea( $form['pickups'] ); ?></textarea>
                            <p class="description">One per line. Shown at checkout for this store's cart.</p></td></tr>
                        <tr><th>Shipping</th>
                            <td><label><input type="checkbox" name="tsa_sb_shipping" value="1" <?php checked( $form['shipping'], 1 ); ?>> Offer shipping for this store (else pickup only)</label></td></tr>
                        <tr><th><label for="tsa_sb_email">Contact email</label></th>
                            <td><input name="tsa_sb_email" id="tsa_sb_email" type="email" class="regular-text" value="<?php echo esc_attr( $form['contact_email'] ); ?>" placeholder="coach@school.org">
                            <p class="description">Optional. Used later for fundraiser/notification routing.</p></td></tr>
                    </tbody></table>
                    </div>
```

And for Programs (wrap the `<h2>` through the `+ Add program` button, lines 263–279, in the same `data-school-only="1"` div).

Then, in the `<script>` block (after the existing `paint()` definition, near line 361), add the toggle wiring:

```javascript
        // Store type — show/hide school-only sections.
        var typeSel = document.getElementById('tsa_sb_type');
        function syncType(){
            var isSchool = !typeSel || typeSel.value === 'school';
            document.querySelectorAll('[data-school-only]').forEach(function(el){
                el.style.display = isSchool ? '' : 'none';
            });
        }
        if (typeSel) typeSel.addEventListener('change', syncType);
        syncType();
```

- [ ] **Step 4: Read the new field on submit**

In `tsa_sb_read_form()` (lines 409–435), add type reading right after slug resolution:

```php
    $hex = function ( $v, $d ) { $v = sanitize_text_field( wp_unslash( $v ) ); return preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ? $v : $d; };
    $status = sanitize_key( $_POST['tsa_sb_status'] ?? 'coming-soon' );
    $type   = sanitize_key( $_POST['tsa_sb_type'] ?? 'school' );
    return [
        'name'          => $name,
        'slug'          => $slug,
        'type'          => array_key_exists( $type, tsa_sb_type_choices() ) ? $type : 'school',
        'mascot'        => sanitize_text_field( wp_unslash( $_POST['tsa_sb_mascot'] ?? '' ) ),
```

(Leave the rest of the returned array exactly as-is — only the `'type'` line is new, inserted after `'slug'`.)

- [ ] **Step 5: Save type + derived Active flag**

In `tsa_sb_build_school()` (lines 502–517), replace the two hardcoded `'school'` writes and add the Active derivation:

```php
    update_post_meta( $store_id, '_ac_store_slug',        $slug );
    update_post_meta( $store_id, '_tsa_store_type',       $f['type'] );
    update_post_meta( $store_id, '_ac_store_type',        $f['type'] ); // keep both in sync
    update_post_meta( $store_id, '_ac_is_active',         tsa_sb_active_from_status( $f['status'] ) );
    update_post_meta( $store_id, '_tsa_homepage_status',  $f['status'] );
```

(The remaining `update_post_meta` calls in that block — spotlight, tagline, ticker, cta_text, colors, mascot, level, pickups, shipping, contact_email — are unchanged here; cta_url is handled in Task 4.)

- [ ] **Step 6: Prefill type on edit**

In `tsa_sb_form_from_store()` (lines 438–456), add the type read right after `'slug'`:

```php
        'name'          => get_the_title( $store_id ),
        'slug'          => sanitize_title( get_post_meta( $store_id, '_ac_store_slug', true ) ?: get_post_field( 'post_name', $store_id ) ),
        'type'          => get_post_meta( $store_id, '_tsa_store_type', true ) ?: 'school',
        'mascot'        => get_post_meta( $store_id, '_tsa_school_mascot', true ),
```

- [ ] **Step 7: Manual verification**

1. Open **TSA Store Builder** in wp-admin.
2. Confirm a **Store type** dropdown appears at the top of Identity, defaulting to School.
3. Switch it to **Business** — confirm Mascot, Level, Delivery & contact, and Programs all disappear; switch back to School — confirm they reappear.
4. Build a test business store (name "Test Biz Store", type Business). Confirm no PHP errors/notices in the admin notice output.
5. In wp-admin → Custom Fields (or a quick DB check via phpMyAdmin/Query Monitor), confirm the new store's `_tsa_store_type` and `_ac_store_type` both read `business`, and `_ac_is_active` is `1` (status defaulted to coming-soon).
6. Click **edit** on that store from the Builder's table — confirm the type dropdown shows Business (not reset to School).

- [ ] **Step 8: Commit**

```bash
git add inc/school-builder.php
git commit -m "Add store type selector and fold Active status into the Builder"
```

---

### Task 3: Description field

**Files:**
- Modify: `inc/school-builder.php`

- [ ] **Step 1: Add to form defaults**

Add `'description' => '',` to the defaults array from Task 2, Step 1 (after `'tagline' => '',`):

```php
        'primary' => '', 'secondary' => '', 'logo_id' => 0, 'tagline' => '', 'description' => '',
```

- [ ] **Step 2: Add the field to the form HTML**

In the "Branding" table, after the Tagline row (lines 237–238):

```php
                        <tr><th><label for="tsa_sb_tagline">Tagline</label></th>
                            <td><input name="tsa_sb_tagline" id="tsa_sb_tagline" type="text" class="regular-text" value="<?php echo esc_attr( $form['tagline'] ); ?>" placeholder="Home of the Griffins"></td></tr>
                        <tr><th><label for="tsa_sb_description">Description</label></th>
                            <td><textarea name="tsa_sb_description" id="tsa_sb_description" rows="3" class="large-text"><?php echo esc_textarea( $form['description'] ); ?></textarea>
                            <p class="description">Internal note about this store — was previously only on the native Stores screen.</p></td></tr>
```

- [ ] **Step 3: Read on submit**

In `tsa_sb_read_form()`, add after the `'tagline'` line:

```php
        'tagline'       => sanitize_text_field( wp_unslash( $_POST['tsa_sb_tagline'] ?? '' ) ),
        'description'   => sanitize_textarea_field( wp_unslash( $_POST['tsa_sb_description'] ?? '' ) ),
```

- [ ] **Step 4: Save it**

In `tsa_sb_build_school()`, add next to the tagline write:

```php
    update_post_meta( $store_id, '_tsa_store_tagline',    $f['tagline'] );
    update_post_meta( $store_id, '_ac_store_description', $f['description'] );
```

- [ ] **Step 5: Prefill on edit**

In `tsa_sb_form_from_store()`, add next to the tagline read:

```php
        'tagline'       => get_post_meta( $store_id, '_tsa_store_tagline', true ),
        'description'   => get_post_meta( $store_id, '_ac_store_description', true ),
```

- [ ] **Step 6: Manual verification**

1. Open a store's edit link from the Builder's table (e.g. Dutchtown).
2. If it was ever set on the native Stores screen, its Description should now appear pre-filled in the Builder.
3. Type a new description, click Build, reload the edit link — confirm it persisted.

- [ ] **Step 7: Commit**

```bash
git add inc/school-builder.php
git commit -m "Add Description field to the Store Builder"
```

---

### Task 4: Type-aware base URL and section parent page

**Files:**
- Modify: `inc/school-builder.php`

- [ ] **Step 1: Replace `tsa_sb_ensure_schools_parent()` with a type-aware version**

Replace the function at lines 599–611:

```php
/** Ensure the /schools/ parent page (directory) exists; return its ID. */
function tsa_sb_ensure_schools_parent(): int {
    $parent = get_page_by_path( 'schools' );
    if ( $parent ) return (int) $parent->ID;
    $pid = wp_insert_post( [
        'post_type' => 'page', 'post_status' => 'publish',
        'post_title' => 'Schools', 'post_name' => 'schools',
    ] );
    if ( $pid && ! is_wp_error( $pid ) ) {
        update_post_meta( $pid, '_wp_page_template', 'template-school-directory.php' );
        return (int) $pid;
    }
    return 0;
}
```

with:

```php
/** Ensure the /<base>/ parent (directory) page for this store type exists; return its ID. */
function tsa_sb_ensure_section_parent( string $type ): int {
    $meta   = tsa_sb_type_meta( $type );
    $parent = get_page_by_path( $meta['base'] );
    if ( $parent ) return (int) $parent->ID;
    $pid = wp_insert_post( [
        'post_type' => 'page', 'post_status' => 'publish',
        'post_title' => $meta['label'], 'post_name' => $meta['base'],
    ] );
    if ( $pid && ! is_wp_error( $pid ) ) {
        if ( $meta['directory_template'] ) {
            update_post_meta( $pid, '_wp_page_template', $meta['directory_template'] );
        }
        return (int) $pid;
    }
    return 0;
}
```

- [ ] **Step 2: Update the call site and every hardcoded `/schools/` string in `tsa_sb_build_school()`**

Replace lines 509–510:

```php
    update_post_meta( $store_id, '_tsa_store_cta_text',   'Shop ' . $f['name'] );
    update_post_meta( $store_id, '_tsa_store_cta_url',    '/schools/' . $slug . '/' );
```

with:

```php
    $base = tsa_sb_type_meta( $f['type'] )['base'];
    update_post_meta( $store_id, '_tsa_store_cta_text',   'Shop ' . $f['name'] );
    update_post_meta( $store_id, '_tsa_store_cta_url',    '/' . $base . '/' . $slug . '/' );
```

Replace line 534 (`$parent_id = tsa_sb_ensure_schools_parent();`) with:

```php
    $parent_id = tsa_sb_ensure_section_parent( $f['type'] );
```

Replace line 538 (the landing-page confirmation message):

```php
        $steps[] = sprintf( 'Landing page <a href="%s">/schools/%s/</a> ready (view <a href="%s" target="_blank">live ↗</a>).',
            esc_url( get_edit_post_link( $page_id ) ), esc_html( $slug ), esc_url( home_url( '/schools/' . $slug . '/' ) ) );
```

with:

```php
        $steps[] = sprintf( 'Landing page <a href="%s">/%s/%s/</a> ready (view <a href="%s" target="_blank">live ↗</a>).',
            esc_url( get_edit_post_link( $page_id ) ), esc_html( $base ), esc_html( $slug ), esc_url( home_url( '/' . $base . '/' . $slug . '/' ) ) );
```

Replace line 560 (the programs-hub message, inside the `sprintf` for step 3b):

```php
            $hub_id ? sprintf( ' · hub at <a href="%s" target="_blank">/schools/%s/programs/ ↗</a>', esc_url( home_url( '/schools/' . $slug . '/programs/' ) ), esc_html( $slug ) ) : ''
```

with:

```php
            $hub_id ? sprintf( ' · hub at <a href="%s" target="_blank">/%s/%s/programs/ ↗</a>', esc_url( home_url( '/' . $base . '/' . $slug . '/programs/' ) ), esc_html( $base ), esc_html( $slug ) ) : ''
```

Replace lines 567–569 (the drops-page message):

```php
        if ( $drops_id ) {
            $steps[] = sprintf( 'Drops page <a href="%s" target="_blank">/schools/%s/drops/ ↗</a> ready — link Tee Parties to this school in the party editor.',
                esc_url( home_url( '/schools/' . $slug . '/drops/' ) ), esc_html( $slug ) );
        }
```

with:

```php
        if ( $drops_id ) {
            $steps[] = sprintf( 'Drops page <a href="%s" target="_blank">/%s/%s/drops/ ↗</a> ready — link Tee Parties to this store in the party editor.',
                esc_url( home_url( '/' . $base . '/' . $slug . '/drops/' ) ), esc_html( $base ), esc_html( $slug ) );
        }
```

Replace line 584 (the final directory-flow-through message):

```php
    $steps[] = sprintf( 'Appears in the <a href="%s" target="_blank">school directory</a>, color map, and request-a-store dropdown automatically.', esc_url( home_url( '/schools/' ) ) );
```

with:

```php
    $steps[] = sprintf( 'Appears in the <a href="%s" target="_blank">%s directory</a>, color map, and request-a-store dropdown automatically.', esc_url( home_url( '/' . $base . '/' ) ), esc_html( strtolower( tsa_sb_type_meta( $f['type'] )['label'] ) ) );
```

- [ ] **Step 3: Manual verification**

1. Build a School store — confirm the admin notice still reads `/schools/<slug>/` everywhere (no regression for the existing, most-used path).
2. Build a Team store — confirm the notice reads `/teams/<slug>/`, and visiting that URL renders the storefront (via `template-store-premium.php`, already generic).
3. Build a Business store — confirm `/business/<slug>/` and that a `Business` parent/directory page now exists at that path using `template-business-directory.php`.
4. Build an Event store — confirm `/events/<slug>/` works even though there's no directory template yet (the `/events/` parent page falls back to the theme's default page template — not broken, just not custom-styled).

- [ ] **Step 4: Commit**

```bash
git add inc/school-builder.php
git commit -m "Generalize Store Builder URL/parent-page logic to all store types"
```

---

### Task 5: All-stores listing with a Type column

**Files:**
- Modify: `inc/school-builder.php`

- [ ] **Step 1: Add `tsa_all_store_records()`**

Add after `tsa_school_store_records()` (after line 65), leaving `tsa_school_store_records()` itself untouched — it's still used by the school directory/color-map front end and must stay school-only:

```php
/**
 * Every store record (any type), normalized for the Builder's admin table.
 * Unlike tsa_school_store_records() this is NOT filtered to type=school and
 * is NOT used by any front-end directory — admin listing only.
 */
function tsa_all_store_records(): array {
    $records = [];
    $posts   = get_posts( [
        'post_type'   => 'configurator_store',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby'     => 'title',
        'order'       => 'ASC',
    ] );

    foreach ( $posts as $p ) {
        $slug = sanitize_title( get_post_meta( $p->ID, '_ac_store_slug', true ) ?: $p->post_name );
        if ( ! $slug ) continue;
        $type   = get_post_meta( $p->ID, '_tsa_store_type', true ) ?: 'school';
        $status = get_post_meta( $p->ID, '_tsa_homepage_status', true ) ?: 'coming-soon';
        $base   = tsa_sb_type_meta( $type )['base'];

        $records[] = [
            'post_id' => $p->ID,
            'name'    => $p->post_title,
            'slug'    => $slug,
            'type'    => $type,
            'status'  => in_array( $status, [ 'live', 'coming-soon', 'hidden' ], true ) ? $status : 'coming-soon',
            'mascot'  => get_post_meta( $p->ID, '_tsa_school_mascot', true ),
            'url'     => '/' . $base . '/' . $slug . '/',
        ];
    }
    return $records;
}
```

- [ ] **Step 2: Use it in the table, with a Type column**

Replace `tsa_sb_render_existing()` (lines 788–808):

```php
/** Full store listing (every type) under the form. */
function tsa_sb_render_existing(): void {
    $records = tsa_all_store_records();
    echo '<hr style="margin:28px 0"><h2>All stores</h2>';
    if ( ! $records ) { echo '<p><em>None generated yet. The directory still shows the seeded list until you build stores here.</em></p>'; return; }
    echo '<table class="widefat striped" style="max-width:920px"><thead><tr><th>Store</th><th>Type</th><th>Slug</th><th>Status</th><th>Store</th><th>Page</th></tr></thead><tbody>';
    foreach ( $records as $r ) {
        $page = get_posts( [ 'post_type' => 'page', 'name' => $r['slug'], 'numberposts' => 1, 'post_status' => 'any' ] );
        $page_link = $page ? '<a href="' . esc_url( home_url( $r['url'] ) ) . '" target="_blank">view ↗</a>' : '—';
        $edit_link = admin_url( 'admin.php?page=tsa-store-builder&tsa_sb_edit=' . $r['post_id'] );
        printf(
            '<tr><td><strong>%s</strong>%s</td><td>%s</td><td><code>%s</code></td><td>%s</td><td><a href="%s">edit</a></td><td>%s</td></tr>',
            esc_html( $r['name'] ),
            $r['mascot'] ? ' <span style="color:#888">· ' . esc_html( $r['mascot'] ) . '</span>' : '',
            esc_html( ucfirst( $r['type'] ) ),
            esc_html( $r['slug'] ),
            esc_html( $r['status'] ),
            esc_url( $edit_link ),
            $page_link
        );
    }
    echo '</tbody></table>';
}
```

(Note the added `<th>Type</th>` in the header and `<td><?php echo esc_html( ucfirst( $r['type'] ) ); ?></td>` — written inline above via `esc_html( ucfirst( $r['type'] ) )` — in the row.)

- [ ] **Step 3: Manual verification**

1. Reload the Store Builder page. Confirm the table is now titled "All stores" and shows every store you've built in this plan's testing (school, team, business, event), each with a correct Type column and a working "view" link to its type-correct URL.

- [ ] **Step 4: Commit**

```bash
git add inc/school-builder.php
git commit -m "List every store type in the Builder's admin table"
```

---

### Task 6: Stage A end-to-end verification

No code changes — verification only, closing out Stage A (Components 1–4 from the spec).

- [ ] **Step 1:** Build one store of each type (school, team, business, event, main) via the Builder.
- [ ] **Step 2:** Confirm each appears in the "All stores" table with the right type, and its "edit" link reopens the Builder pre-filled (not the native screen).
- [ ] **Step 3:** Confirm each store's storefront renders at its type-correct URL with no PHP warnings/notices (check the page source or `WP_DEBUG` log if available).
- [ ] **Step 4:** No commit — this task is verification of Task 1–5's already-committed work.

---

### Task 7: Hide the native "Stores" screen, add a link-out from the Builder

**Files:**
- Modify: `inc/school-builder.php`

- [ ] **Step 1: Remove the native submenu (theme-level only)**

Add near the top of the file, after the `admin_menu` block that registers the Builder's own menu page (after line 115, before `tsa_sb_brand_guide_map()`):

```php
add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook === 'toplevel_page_tsa-store-builder' ) wp_enqueue_media();
} );

/**
 * Hide the native "Stores" post-type screen from the admin menu — the Store
 * Builder is now the one entry point. This only removes the MENU ITEM (a
 * theme-level admin_menu hook); it does NOT touch the plugin's CPT
 * registration, so moneyoverbs.com (which runs the same plugin) is
 * unaffected. The screen itself still works and is still reachable — see
 * the "Manage apparel & drops" link in tsa_sb_render_existing().
 */
add_action( 'admin_menu', function () {
    remove_submenu_page( 'apparel-configurator', 'edit.php?post_type=configurator_store' );
}, 999 );
```

- [ ] **Step 2: Add the link-out to the Builder's table**

In `tsa_sb_render_existing()` (from Task 5), add a "Manage apparel & drops" column. Update the header:

```php
    echo '<table class="widefat striped" style="max-width:920px"><thead><tr><th>Store</th><th>Type</th><th>Slug</th><th>Status</th><th>Store</th><th>Page</th><th>Apparel &amp; drops</th></tr></thead><tbody>';
```

And the row `printf`:

```php
        printf(
            '<tr><td><strong>%s</strong>%s</td><td>%s</td><td><code>%s</code></td><td>%s</td><td><a href="%s">edit</a></td><td>%s</td><td><a href="%s">Manage →</a></td></tr>',
            esc_html( $r['name'] ),
            $r['mascot'] ? ' <span style="color:#888">· ' . esc_html( $r['mascot'] ) . '</span>' : '',
            esc_html( ucfirst( $r['type'] ) ),
            esc_html( $r['slug'] ),
            esc_html( $r['status'] ),
            esc_url( $edit_link ),
            $page_link,
            esc_url( get_edit_post_link( $r['post_id'] ) )
        );
```

- [ ] **Step 3: Manual verification**

1. Reload wp-admin. Confirm **Apparel Configurator → Stores** no longer appears in the sidebar.
2. On the Store Builder page, confirm each row now has a "Manage →" link under "Apparel & drops".
3. Click it for an existing store (e.g. Dutchtown) — confirm it opens the native post-edit screen and the **Store Apparel & Colors** and **Store Design Drops** boxes are present and functional exactly as before.
4. Confirm you can still save changes on that native screen (garment/color assignment still works).

- [ ] **Step 4: Commit**

```bash
git add inc/school-builder.php
git commit -m "Hide native Stores menu; link to it from the Store Builder instead"
```

---

### Task 8: Stage B verification

No code changes.

- [ ] **Step 1:** Confirm no other part of wp-admin links to `edit.php?post_type=configurator_store` in a way that's now broken (the URL itself still works — only the menu entry is gone — so this is a quick sanity check, not expected to find anything).
- [ ] **Step 2:** No commit.

---

### Task 9: Flip Team and Business to GA

**Files:**
- Modify: `inc/platform.php`

- [ ] **Step 1: Change the lifecycle values**

Replace lines 40–41:

```php
        'stamp_team'          => [ 'name' => 'Team Stores',                 'group' => 'Store types', 'store_type' => 'team',     'lifecycle' => 'planned' ],
        'stamp_business'      => [ 'name' => 'Business Stores',             'group' => 'Store types', 'store_type' => 'business', 'lifecycle' => 'planned' ],
```

with:

```php
        'stamp_team'          => [ 'name' => 'Team Stores',                 'group' => 'Store types', 'store_type' => 'team',     'lifecycle' => 'ga' ],
        'stamp_business'      => [ 'name' => 'Business Stores',             'group' => 'Store types', 'store_type' => 'business', 'lifecycle' => 'ga' ],
```

- [ ] **Step 2: Manual verification**

1. Open **Platform** in wp-admin. Confirm Team Stores and Business Stores now show lifecycle `ga` (not `planned`) and their checkboxes in the Entitlements table are no longer disabled.
2. Open the **TSA Store Builder**. Confirm the small availability-pill row at the top no longer shows "· soon" next to Team or Business.

- [ ] **Step 3: Commit**

```bash
git add inc/platform.php
git commit -m "Flip Team and Business store types from planned to GA"
```

---

### Task 10: Type-aware hero copy

**Files:**
- Modify: `template-store-premium.php`

- [ ] **Step 1: Replace the hardcoded hero subhead**

Replace line 53:

```php
			<p class="dths-hero-sub">Premium spirit gear powered by Tee Shirt Ali — configure your design on your apparel, color and size, with live pricing and no email back-and-forth.</p>
```

with:

```php
			<?php
			$hero_taglines = [
				'business' => 'Premium branded merchandise for ' . $ctx['name'] . ' — configure your design on your apparel, color and size, with live pricing and no email back-and-forth.',
				'event'    => 'Official event merchandise — configure your design on your apparel, color and size, with live pricing and no email back-and-forth.',
			];
			$hero_sub = $hero_taglines[ $ctx['type'] ] ?? 'Premium spirit gear powered by Tee Shirt Ali — configure your design on your apparel, color and size, with live pricing and no email back-and-forth.';
			?>
			<p class="dths-hero-sub"><?php echo esc_html( $hero_sub ); ?></p>
```

- [ ] **Step 2: Manual verification**

1. Visit a School or Team store's homepage — confirm the hero subhead is unchanged ("Premium spirit gear powered by Tee Shirt Ali...").
2. Visit the Business store built in Task 6 — confirm the subhead now reads the business-specific copy with its name substituted in.

- [ ] **Step 3: Commit**

```bash
git add template-store-premium.php
git commit -m "Type-aware hero copy on the premium store template"
```

---

### Task 11: Stage C end-to-end verification (final)

No code changes — closes out the whole plan.

- [ ] **Step 1:** Using the Store Builder, build one real Team store and one real Business store with actual branding (name, colors, logo, ticker).
- [ ] **Step 2:** Visit each storefront. Confirm hero, ticker, programs (if any design categories are tagged), and footer all render correctly with that store's branding.
- [ ] **Step 3:** Add a product to cart through the configurator on the Business store; confirm checkout reaches the cart page without errors (delivery/pickup config can be set from the Builder's Delivery & contact fields — but recall those are hidden for non-school types in this plan's design; if the business store needs pickup/shipping configured, note this as a follow-up — it's called out as a known gap, not silently broken).
- [ ] **Step 4:** No commit — verification only.

---

## Known follow-up (not in this plan)

Task 11 surfaces a real gap: Delivery & contact (pickups/shipping) is hidden for non-school types in Task 2's `data-school-only` treatment, but delivery is not conceptually school-specific — `tsa_store_delivery` is already keyed generically by slug. If Team/Business stores need real checkout delivery options, a follow-up should un-hide that section for all types (rename its `data-school-only` wrapper) rather than leaving Team/Business stores pickup/shipping-less. Flagging here rather than silently expanding this plan's scope.
