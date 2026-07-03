<?php
/**
 * TSA Store Builder — school-store generator (Phase 1: Foundation).
 *
 * One admin form + a Build button that provisions everything a school store
 * needs from a single record. The configurator_store post is the school's
 * single source of truth; the directory, color map, landing page, design
 * scoping, and cart/delivery all derive from it.
 *
 * Re-running with the same slug UPDATES that school (idempotent), never
 * duplicates. Store type defaults to "school"; Teams/Business reuse this in a
 * later phase.
 */
defined( 'ABSPATH' ) || exit;

/* ─────────────────────────────────────────────────────────────────
   DATA LAYER — normalized school store records.
   Consumed by tsa_school_directory() + tsa_get_school_colors() (functions.php)
   so generated schools flow into the directory, colors, design scoping, and
   the request-a-store dropdown automatically.
───────────────────────────────────────────────────────────────── */

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

/**
 * All school store records (configurator_store, type=school), normalized for
 * the directory + color map. Excludes 'hidden' stores. Cached per request.
 */
function tsa_school_store_records(): array {
    static $cache = null;
    if ( $cache !== null ) return $cache;

    $cache = [];
    $posts = get_posts( [
        'post_type'   => 'configurator_store',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby'     => 'title',
        'order'       => 'ASC',
        'meta_query'  => [ [ 'key' => '_tsa_store_type', 'value' => 'school' ] ],
    ] );

    foreach ( $posts as $p ) {
        $status_raw = get_post_meta( $p->ID, '_tsa_homepage_status', true ) ?: 'coming-soon';
        if ( $status_raw === 'hidden' ) continue; // exists, but not shown in directory
        $slug = sanitize_title( get_post_meta( $p->ID, '_ac_store_slug', true ) ?: $p->post_name );
        if ( ! $slug ) continue;

        $cache[] = [
            'post_id' => $p->ID,
            'name'    => $p->post_title,
            'slug'    => $slug,
            'status'  => ( $status_raw === 'live' ) ? 'live' : 'coming-soon',
            'mascot'  => get_post_meta( $p->ID, '_tsa_school_mascot', true ),
            'level'   => get_post_meta( $p->ID, '_tsa_school_level', true ) ?: 'High Schools',
            'color'   => get_post_meta( $p->ID, '_tsa_school_primary', true ),
            'color2'  => get_post_meta( $p->ID, '_tsa_school_secondary', true ),
            'url'     => '/schools/' . $slug . '/',
        ];
    }
    return $cache;
}

/**
 * Every school store for the Design Library directory grid — INCLUDING hidden
 * (shown as "Not active") — with the RAW 3-way status + logo URL for the card UI.
 * tsa_school_store_records() drops hidden schools and collapses status to
 * live/coming-soon; this keeps each school and its true status so the Library can
 * label and route each one (live → store, coming-soon → static, hidden → request).
 */
function tsa_school_records_all(): array {
    static $cache = null;
    if ( $cache !== null ) return $cache;
    $cache = [];
    foreach ( get_posts( [
        'post_type'   => 'configurator_store',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby'     => 'title',
        'order'       => 'ASC',
        'meta_query'  => [ [ 'key' => '_tsa_store_type', 'value' => 'school' ] ],
    ] ) as $p ) {
        $slug = sanitize_title( get_post_meta( $p->ID, '_ac_store_slug', true ) ?: $p->post_name );
        if ( ! $slug ) continue;
        $status = get_post_meta( $p->ID, '_tsa_homepage_status', true ) ?: 'coming-soon';
        if ( ! in_array( $status, [ 'live', 'coming-soon', 'hidden' ], true ) ) $status = 'coming-soon';
        $cache[] = [
            'post_id' => $p->ID,
            'name'    => $p->post_title,
            'slug'    => $slug,
            'status'  => $status,                                                 // raw: live | coming-soon | hidden
            'mascot'  => get_post_meta( $p->ID, '_tsa_school_mascot', true ),
            'level'   => get_post_meta( $p->ID, '_tsa_school_level', true ) ?: 'High Schools',
            'color'   => get_post_meta( $p->ID, '_tsa_school_primary', true ),
            'color2'  => get_post_meta( $p->ID, '_tsa_school_secondary', true ),
            'logo'    => get_the_post_thumbnail_url( $p->ID, 'medium' ) ?: '',     // school logo = store featured image
            'url'     => '/schools/' . $slug . '/',
        ];
    }
    return $cache;
}

/* ─────────────────────────────────────────────────────────────────
   ADMIN MENU + PAGE
───────────────────────────────────────────────────────────────── */

add_action( 'admin_menu', function () {
    add_menu_page(
        'TSA Store Builder', 'TSA Store Builder', 'manage_options',
        'tsa-store-builder', 'tsa_store_builder_page', 'dashicons-store', 26
    );
} );

add_action( 'admin_enqueue_scripts', function ( $hook ) {
    if ( $hook === 'toplevel_page_tsa-store-builder' ) wp_enqueue_media();
} );

/** Known Ascension brand-guide colors for the prefill helper (name → [primary, secondary]). */
function tsa_sb_brand_guide_map(): array {
    return [
        'donaldsonville' => [ '#E1251B', '#020000' ],
        'dutchtown'      => [ '#592C82', '#C7C9C8' ],
        'east ascension' => [ '#263A80', '#FFCE06' ],
        'prairieville'   => [ '#0F2D52', '#61A744' ],
        'amant'          => [ '#FFCE06', '#020000' ],
    ];
}

function tsa_store_builder_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    $result = null;
    $form   = [
        'name' => '', 'slug' => '', 'type' => 'school', 'mascot' => '', 'level' => 'High Schools',
        'status' => 'coming-soon', 'spotlight' => 0,
        'primary' => '', 'secondary' => '', 'logo_id' => 0, 'tagline' => '', 'description' => '',
        'ticker' => '',
        'pickups' => '', 'shipping' => 0, 'contact_email' => '',
        'programs' => [],
    ];

    $editing_id = 0;
    if ( isset( $_POST['tsa_sb_submit'] ) && check_admin_referer( 'tsa_sb_build', 'tsa_sb_nonce' ) ) {
        $form = tsa_sb_read_form();
        if ( $_POST['tsa_sb_submit'] === 'build' ) {
            $result = tsa_sb_build_school( $form );
        }
    } elseif ( isset( $_GET['tsa_sb_edit'] ) ) {
        $editing_id = absint( $_GET['tsa_sb_edit'] );
        if ( $editing_id && get_post_type( $editing_id ) === 'configurator_store' ) {
            $form = tsa_sb_form_from_store( $editing_id );
        } else {
            $editing_id = 0;
        }
    }
    $levels    = tsa_school_levels();
    $brand_map = wp_json_encode( tsa_sb_brand_guide_map() );
    $logo_src  = $form['logo_id'] ? wp_get_attachment_image_url( $form['logo_id'], 'thumbnail' ) : '';
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-store" style="font-size:28px;width:28px;height:28px;vertical-align:-4px"></span> TSA Store Builder</h1>
        <?php if ( $editing_id ) : ?>
        <p>✏️ Editing <strong><?php echo esc_html( $form['name'] ); ?></strong> — click <strong>Build school store</strong> to save changes to this school (same slug, no duplicate).
            <a href="<?php echo esc_url( admin_url( 'admin.php?page=tsa-store-builder' ) ); ?>">Start a new school instead</a></p>
        <?php else : ?>
        <p>Answer a few questions and click <strong>Build school store</strong>. Re-running with the same slug updates that school — it never creates duplicates. Store type defaults to <em>School</em>; Teams &amp; Business reuse this builder later.</p>
        <?php endif; ?>

        <?php if ( function_exists( 'tsa_store_types' ) ) : ?>
        <div style="display:flex;gap:8px;margin:6px 0 2px;flex-wrap:wrap">
            <?php foreach ( tsa_store_types() as $tk => $tt ) :
                $avail = function_exists( 'tsa_store_type_available' ) ? tsa_store_type_available( $tk ) : ( $tk === 'school' );
                $on    = ( $tk === 'school' );
                $bg    = $on ? '#dff0d8' : '#f0f0f1'; $fg = $on ? '#1a7f37' : '#888';
            ?>
            <span style="font-size:12px;padding:5px 12px;border-radius:999px;background:<?php echo esc_attr( $bg ); ?>;color:<?php echo esc_attr( $fg ); ?>"><?php echo esc_html( $tt['label'] ); ?><?php echo $avail ? '' : ' · soon'; ?></span>
            <?php endforeach; ?>
        </div>
        <p class="description" style="margin:0 0 10px">Store types are gated platform features (see <strong>Platform</strong>). School ships now; Team &amp; Business use this same builder once their feature is built.</p>
        <?php endif; ?>

        <?php if ( $result ) : ?>
            <div class="notice notice-success" style="padding:14px 16px">
                <h2 style="margin-top:0">✅ <?php echo esc_html( $result['verb'] ); ?>: <?php echo esc_html( $form['name'] ); ?></h2>
                <ul style="margin:0 0 4px 18px;list-style:disc">
                    <?php foreach ( $result['steps'] as $step ) : ?>
                    <li><?php echo wp_kses_post( $step ); ?></li>
                    <?php endforeach; ?>
                </ul>
            </div>
        <?php endif; ?>

        <form method="post" style="max-width:920px;margin-top:14px">
            <?php wp_nonce_field( 'tsa_sb_build', 'tsa_sb_nonce' ); ?>

            <div style="display:grid;grid-template-columns:1.5fr 1fr;gap:20px;align-items:start">

                <div>
                    <h2 class="title" style="font-size:14px;text-transform:uppercase;letter-spacing:.5px;color:#555">Identity</h2>
                    <table class="form-table"><tbody>
                        <tr><th><label for="tsa_sb_type">Store type</label></th>
                            <td><select name="tsa_sb_type" id="tsa_sb_type">
                                <?php foreach ( tsa_sb_type_choices() as $tk => $tl ) : ?>
                                <option value="<?php echo esc_attr( $tk ); ?>" <?php selected( $form['type'], $tk ); ?>><?php echo esc_html( $tl ); ?></option>
                                <?php endforeach; ?>
                            </select></td></tr>
                        <tr><th><label for="tsa_sb_name">Name</label></th>
                            <td><input name="tsa_sb_name" id="tsa_sb_name" type="text" class="regular-text" value="<?php echo esc_attr( $form['name'] ); ?>" placeholder="Dutchtown High School" required></td></tr>
                        <tr><th><label for="tsa_sb_slug">Slug</label></th>
                            <td><input name="tsa_sb_slug" id="tsa_sb_slug" type="text" class="regular-text" value="<?php echo esc_attr( $form['slug'] ); ?>" placeholder="dutchtown">
                            <p class="description">Auto-filled from the name. Used for the store, design scope, and <code>/schools/&lt;slug&gt;/</code>.</p></td></tr>
                        <tr data-school-only="1"><th><label for="tsa_sb_mascot">Mascot</label></th>
                            <td><input name="tsa_sb_mascot" id="tsa_sb_mascot" type="text" class="regular-text" value="<?php echo esc_attr( $form['mascot'] ); ?>" placeholder="Griffins"></td></tr>
                        <tr data-school-only="1"><th><label for="tsa_sb_level">Level</label></th>
                            <td><select name="tsa_sb_level" id="tsa_sb_level">
                                <?php foreach ( $levels as $lv ) : ?>
                                <option value="<?php echo esc_attr( $lv ); ?>" <?php selected( $form['level'], $lv ); ?>><?php echo esc_html( $lv ); ?></option>
                                <?php endforeach; ?>
                            </select></td></tr>
                        <tr><th><label for="tsa_sb_status">Status</label></th>
                            <td><select name="tsa_sb_status" id="tsa_sb_status">
                                <option value="live"        <?php selected( $form['status'], 'live' ); ?>>Live (shoppable + shown)</option>
                                <option value="coming-soon" <?php selected( $form['status'], 'coming-soon' ); ?>>Coming soon (shown, not shoppable)</option>
                                <option value="hidden"      <?php selected( $form['status'], 'hidden' ); ?>>Hidden (built, not shown anywhere)</option>
                            </select>
                            <label style="margin-left:14px"><input type="checkbox" name="tsa_sb_spotlight" value="1" <?php checked( $form['spotlight'], 1 ); ?>> Spotlight on homepage</label></td></tr>
                    </tbody></table>

                    <h2 class="title" style="font-size:14px;text-transform:uppercase;letter-spacing:.5px;color:#555">Branding</h2>
                    <table class="form-table"><tbody>
                        <tr><th>Colors</th>
                            <td>
                                <input name="tsa_sb_primary" id="tsa_sb_primary" type="color" value="<?php echo esc_attr( $form['primary'] ?: '#592C82' ); ?>" style="width:54px;height:34px;vertical-align:middle">
                                <span style="margin:0 14px 0 4px">Primary</span>
                                <input name="tsa_sb_secondary" id="tsa_sb_secondary" type="color" value="<?php echo esc_attr( $form['secondary'] ?: '#C7C9C8' ); ?>" style="width:54px;height:34px;vertical-align:middle">
                                <span style="margin-left:4px">Secondary</span>
                                <p class="description" id="tsa_sb_brandnote" style="display:none">✨ Prefilled from the Ascension brand guide — adjust if needed.</p>
                            </td></tr>
                        <tr><th><label for="tsa_sb_tagline">Tagline</label></th>
                            <td><input name="tsa_sb_tagline" id="tsa_sb_tagline" type="text" class="regular-text" value="<?php echo esc_attr( $form['tagline'] ); ?>" placeholder="Home of the Griffins"></td></tr>
                        <tr><th><label for="tsa_sb_description">Description</label></th>
                            <td><textarea name="tsa_sb_description" id="tsa_sb_description" rows="3" class="large-text"><?php echo esc_textarea( $form['description'] ); ?></textarea>
                            <p class="description">Internal note about this store — was previously only on the native Stores screen.</p></td></tr>
                        <tr><th><label for="tsa_sb_ticker">Ticker items</label></th>
                            <td><textarea name="tsa_sb_ticker" id="tsa_sb_ticker" rows="4" class="large-text code" placeholder="Official Merch&#10;Drops on Schedule&#10;Performance First"><?php echo esc_textarea( $form['ticker'] ); ?></textarea>
                            <p class="description">One phrase per line — scrolls across the homepage hero banner. Leave blank to use the default TSA ticker.</p></td></tr>
                        <tr><th>Logo</th>
                            <td>
                                <input type="hidden" name="tsa_sb_logo_id" id="tsa_sb_logo_id" value="<?php echo esc_attr( $form['logo_id'] ); ?>">
                                <img id="tsa_sb_logo_preview" src="<?php echo esc_url( $logo_src ); ?>" style="max-height:54px;display:<?php echo $logo_src ? 'inline-block' : 'none'; ?>;vertical-align:middle;margin-right:10px;border:1px solid #ddd;border-radius:6px">
                                <button type="button" class="button" id="tsa_sb_logo_btn">Select logo</button>
                                <button type="button" class="button-link" id="tsa_sb_logo_clear" style="<?php echo $logo_src ? '' : 'display:none'; ?>;margin-left:8px;color:#b32d2e">Remove</button>
                            </td></tr>
                    </tbody></table>

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

                    <div data-school-only="1">
                    <h2 class="title" style="font-size:14px;text-transform:uppercase;letter-spacing:.5px;color:#555">Programs <span style="text-transform:none;font-weight:400;color:#888">(optional — Band, Football, Color Guard…)</span></h2>
                    <p class="description" style="margin:0 0 8px">Each program becomes a design category for this school and a card on the programs hub. Live programs link to the school's designs filtered to that program.</p>
                    <table class="widefat" id="tsa-sb-programs" style="max-width:560px;margin-bottom:8px"><tbody>
                        <?php
                        $prog_rows = ! empty( $form['programs'] ) ? $form['programs'] : [ [ 'name' => '', 'status' => 'coming-soon' ] ];
                        foreach ( $prog_rows as $pr ) : ?>
                        <tr class="tsa-sb-prow">
                            <td><input type="text" name="tsa_sb_prog_name[]" value="<?php echo esc_attr( $pr['name'] ?? '' ); ?>" placeholder="Marching Band" class="regular-text" style="width:100%"></td>
                            <td style="width:140px"><select name="tsa_sb_prog_status[]">
                                <option value="live"        <?php selected( ( $pr['status'] ?? '' ), 'live' ); ?>>Live</option>
                                <option value="coming-soon" <?php selected( ( $pr['status'] ?? 'coming-soon' ), 'coming-soon' ); ?>>Coming soon</option>
                            </select></td>
                            <td style="width:28px"><button type="button" class="button-link tsa-sb-prm" style="color:#b32d2e;font-size:18px;text-decoration:none" title="Remove">×</button></td>
                        </tr>
                        <?php endforeach; ?>
                    </tbody></table>
                    <button type="button" class="button button-small" id="tsa-sb-add-prog">+ Add program</button>
                    </div>

                    <p class="submit">
                        <button type="submit" name="tsa_sb_submit" value="build" class="button button-primary button-hero">🏫 Build school store</button>
                        <button type="submit" name="tsa_sb_submit" value="preview" class="button button-secondary" style="margin-left:8px">Preview</button>
                    </p>
                </div>

                <div>
                    <div style="position:sticky;top:40px">
                        <p style="font-size:12px;color:#777;margin:0 0 6px">Live preview</p>
                        <div id="tsa_sb_preview" style="border-radius:12px;overflow:hidden;border:1px solid #e2e2e2">
                            <div style="height:5px;background:#C7C9C8" id="tsa_sb_pv_accent"></div>
                            <div id="tsa_sb_pv_hero" style="background:#592C82;color:#fff;padding:22px 16px;text-align:center">
                                <div id="tsa_sb_pv_initial" style="width:46px;height:46px;border-radius:50%;background:#C7C9C8;color:#592C82;display:flex;align-items:center;justify-content:center;font-weight:700;margin:0 auto 8px">D</div>
                                <div id="tsa_sb_pv_name" style="font-weight:700;font-size:15px">Dutchtown High School</div>
                                <div id="tsa_sb_pv_tagline" style="font-size:12px;opacity:.85">Home of the Griffins</div>
                            </div>
                            <div style="background:#fff;padding:9px;text-align:center;font-size:12px;color:#1a7f37" id="tsa_sb_pv_url">● Coming soon · /schools/dutchtown/</div>
                        </div>
                        <div style="background:#fff;border:1px solid #e2e2e2;border-radius:12px;padding:14px;margin-top:14px;font-size:13px">
                            <p style="margin:0 0 8px;color:#777">Build will create / update</p>
                            <div style="display:flex;flex-direction:column;gap:6px">
                                <span>✓ Store record (configurator_store)</span>
                                <span>✓ Design Library scope (tsa_design_store)</span>
                                <span>✓ /schools/&lt;slug&gt;/ landing page</span>
                                <span>✓ Colors + directory entry</span>
                                <span>✓ Cart routing + pickup/shipping</span>
                            </div>
                        </div>
                    </div>
                </div>

            </div>
        </form>

        <?php tsa_sb_render_existing(); ?>
    </div>

    <script>
    (function(){
        var BRAND = <?php echo $brand_map; ?>;
        var name = document.getElementById('tsa_sb_name'),
            slug = document.getElementById('tsa_sb_slug'),
            mascot = document.getElementById('tsa_sb_mascot'),
            tagline = document.getElementById('tsa_sb_tagline'),
            status = document.getElementById('tsa_sb_status'),
            prim = document.getElementById('tsa_sb_primary'),
            sec  = document.getElementById('tsa_sb_secondary'),
            brandnote = document.getElementById('tsa_sb_brandnote');

        function slugify(s){ return s.toLowerCase().replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,''); }
        function shortSlug(s){ // drop generic words for a cleaner store slug
            return slugify(s.replace(/\b(high|middle|elementary|primary|school|of|and|the)\b/gi,' '));
        }
        var slugTouched = <?php echo $form['slug'] ? 'true' : 'false'; ?>;
        if (slug) slug.addEventListener('input', function(){ slugTouched = true; });

        function syncFromName(){
            var v = name.value.trim();
            if (!slugTouched) slug.value = shortSlug(v);
            // Brand-guide color prefill (substring match), only if user hasn't picked.
            var key = v.toLowerCase(), hit = null;
            Object.keys(BRAND).forEach(function(k){ if (key.indexOf(k) !== -1) hit = BRAND[k]; });
            if (hit) { prim.value = hit[0]; sec.value = hit[1]; brandnote.style.display = ''; }
            else brandnote.style.display = 'none';
            paint();
        }
        function paint(){
            var p = prim.value, s = sec.value;
            document.getElementById('tsa_sb_pv_hero').style.background = p;
            document.getElementById('tsa_sb_pv_accent').style.background = s;
            var ini = document.getElementById('tsa_sb_pv_initial');
            ini.style.background = s; ini.style.color = p;
            ini.textContent = (name.value.trim()[0] || 'S').toUpperCase();
            document.getElementById('tsa_sb_pv_name').textContent = name.value.trim() || 'School name';
            document.getElementById('tsa_sb_pv_tagline').textContent = tagline.value.trim() || (mascot.value.trim() ? 'Home of the ' + mascot.value.trim() : '');
            var st = status.value, lbl = st === 'live' ? '● Live' : (st === 'hidden' ? '○ Hidden' : '● Coming soon');
            document.getElementById('tsa_sb_pv_url').textContent = lbl + ' · /schools/' + (slug.value || 'slug') + '/';
        }
        [name].forEach(function(el){ el && el.addEventListener('input', syncFromName); });
        [mascot,tagline,status,slug,prim,sec].forEach(function(el){ el && el.addEventListener('input', paint); });
        paint();

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

        // Media logo picker
        var btn = document.getElementById('tsa_sb_logo_btn'),
            clr = document.getElementById('tsa_sb_logo_clear'),
            idf = document.getElementById('tsa_sb_logo_id'),
            img = document.getElementById('tsa_sb_logo_preview'), frame;
        if (btn) btn.addEventListener('click', function(e){
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = wp.media({ title:'Select school logo', button:{ text:'Use logo' }, multiple:false });
            frame.on('select', function(){
                var a = frame.state().get('selection').first().toJSON();
                idf.value = a.id;
                img.src = (a.sizes && a.sizes.thumbnail ? a.sizes.thumbnail.url : a.url);
                img.style.display = 'inline-block'; clr.style.display = '';
            });
            frame.open();
        });
        if (clr) clr.addEventListener('click', function(e){ e.preventDefault(); idf.value=''; img.style.display='none'; clr.style.display='none'; });

        // Programs — add / remove repeatable rows
        var progBody = document.querySelector('#tsa-sb-programs tbody');
        var addProg  = document.getElementById('tsa-sb-add-prog');
        function progRow(){
            var tr = document.createElement('tr');
            tr.className = 'tsa-sb-prow';
            tr.innerHTML = '<td><input type="text" name="tsa_sb_prog_name[]" placeholder="Color Guard" class="regular-text" style="width:100%"></td>'
                + '<td style="width:140px"><select name="tsa_sb_prog_status[]"><option value="live">Live</option><option value="coming-soon" selected>Coming soon</option></select></td>'
                + '<td style="width:28px"><button type="button" class="button-link tsa-sb-prm" style="color:#b32d2e;font-size:18px;text-decoration:none" title="Remove">×</button></td>';
            return tr;
        }
        if (addProg) addProg.addEventListener('click', function(){ progBody.appendChild(progRow()); });
        if (progBody) progBody.addEventListener('click', function(e){
            if (e.target.classList.contains('tsa-sb-prm')) {
                if (progBody.querySelectorAll('.tsa-sb-prow').length > 1) e.target.closest('tr').remove();
                else { var i = e.target.closest('tr').querySelector('input'); if (i) i.value=''; }
            }
        });
    })();
    </script>
    <?php
}

/* ─────────────────────────────────────────────────────────────────
   FORM READ + BUILD
───────────────────────────────────────────────────────────────── */

function tsa_sb_read_form(): array {
    $name = sanitize_text_field( wp_unslash( $_POST['tsa_sb_name'] ?? '' ) );
    $slug = sanitize_title( wp_unslash( $_POST['tsa_sb_slug'] ?? '' ) );
    if ( ! $slug && $name ) {
        // Drop generic words for a cleaner slug, mirroring the JS.
        $slug = sanitize_title( preg_replace( '/\b(high|middle|elementary|primary|school|of|and|the)\b/i', ' ', $name ) );
    }
    $hex = function ( $v, $d ) { $v = sanitize_text_field( wp_unslash( $v ) ); return preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ? $v : $d; };
    $status = sanitize_key( $_POST['tsa_sb_status'] ?? 'coming-soon' );
    $type   = sanitize_key( $_POST['tsa_sb_type'] ?? 'school' );
    return [
        'name'          => $name,
        'slug'          => $slug,
        'type'          => array_key_exists( $type, tsa_sb_type_choices() ) ? $type : 'school',
        'mascot'        => sanitize_text_field( wp_unslash( $_POST['tsa_sb_mascot'] ?? '' ) ),
        'level'         => sanitize_text_field( wp_unslash( $_POST['tsa_sb_level'] ?? 'High Schools' ) ),
        'status'        => in_array( $status, [ 'live', 'coming-soon', 'hidden' ], true ) ? $status : 'coming-soon',
        'spotlight'     => isset( $_POST['tsa_sb_spotlight'] ) ? 1 : 0,
        'primary'       => $hex( $_POST['tsa_sb_primary'] ?? '', '#592C82' ),
        'secondary'     => $hex( $_POST['tsa_sb_secondary'] ?? '', '#C7C9C8' ),
        'logo_id'       => absint( $_POST['tsa_sb_logo_id'] ?? 0 ),
        'tagline'       => sanitize_text_field( wp_unslash( $_POST['tsa_sb_tagline'] ?? '' ) ),
        'description'   => sanitize_textarea_field( wp_unslash( $_POST['tsa_sb_description'] ?? '' ) ),
        'ticker'        => sanitize_textarea_field( wp_unslash( $_POST['tsa_sb_ticker'] ?? '' ) ),
        'pickups'       => sanitize_textarea_field( wp_unslash( $_POST['tsa_sb_pickups'] ?? '' ) ),
        'shipping'      => isset( $_POST['tsa_sb_shipping'] ) ? 1 : 0,
        'contact_email' => sanitize_email( wp_unslash( $_POST['tsa_sb_email'] ?? '' ) ),
        'programs'      => tsa_sb_read_programs(),
    ];
}

/** Rebuild the builder form array from an existing store's saved meta, for the edit link. */
function tsa_sb_form_from_store( int $store_id ): array {
    return [
        'name'          => get_the_title( $store_id ),
        'slug'          => sanitize_title( get_post_meta( $store_id, '_ac_store_slug', true ) ?: get_post_field( 'post_name', $store_id ) ),
        'type'          => get_post_meta( $store_id, '_tsa_store_type', true ) ?: 'school',
        'mascot'        => get_post_meta( $store_id, '_tsa_school_mascot', true ),
        'level'         => get_post_meta( $store_id, '_tsa_school_level', true ) ?: 'High Schools',
        'status'        => get_post_meta( $store_id, '_tsa_homepage_status', true ) ?: 'coming-soon',
        'spotlight'     => get_post_meta( $store_id, '_tsa_is_spotlight', true ) ? 1 : 0,
        'primary'       => get_post_meta( $store_id, '_tsa_school_primary', true ) ?: '#592C82',
        'secondary'     => get_post_meta( $store_id, '_tsa_school_secondary', true ) ?: '#C7C9C8',
        'logo_id'       => (int) get_post_thumbnail_id( $store_id ),
        'tagline'       => get_post_meta( $store_id, '_tsa_store_tagline', true ),
        'description'   => get_post_meta( $store_id, '_ac_store_description', true ),
        'ticker'        => get_post_meta( $store_id, '_tsa_school_ticker', true ),
        'pickups'       => get_post_meta( $store_id, '_tsa_school_pickups', true ),
        'shipping'      => get_post_meta( $store_id, '_tsa_school_shipping', true ) ? 1 : 0,
        'contact_email' => get_post_meta( $store_id, '_tsa_school_contact_email', true ),
        'programs'      => function_exists( 'tsa_school_programs' ) ? tsa_school_programs( $store_id ) : [],
    ];
}

/** Parse the repeatable Programs rows into [ ['name','slug','status'], ... ]. */
function tsa_sb_read_programs(): array {
    $names    = (array) ( $_POST['tsa_sb_prog_name'] ?? [] );
    $statuses = (array) ( $_POST['tsa_sb_prog_status'] ?? [] );
    $out      = [];
    foreach ( $names as $i => $raw ) {
        $name = sanitize_text_field( wp_unslash( $raw ) );
        if ( $name === '' ) continue;
        $status = sanitize_key( $statuses[ $i ] ?? 'coming-soon' );
        $out[]  = [
            'name'   => $name,
            'slug'   => sanitize_title( $name ),
            'status' => in_array( $status, [ 'live', 'coming-soon' ], true ) ? $status : 'coming-soon',
        ];
    }
    return $out;
}

/**
 * Provision (or update) every piece a school store needs. Idempotent by slug.
 * Returns [ 'verb' => 'Created'|'Updated', 'steps' => string[] (HTML) ].
 */
function tsa_sb_build_school( array $f ): array {
    $steps = [];
    $slug  = $f['slug'];
    if ( ! $f['name'] || ! $slug ) {
        return [ 'verb' => 'Error', 'steps' => [ 'A school name and slug are required.' ] ];
    }

    // ── 1. Store record (configurator_store), found by slug ──
    $store_id = tsa_sb_find_store_by_slug( $slug );
    $verb     = $store_id ? 'Updated' : 'Created';
    if ( ! $store_id ) {
        $store_id = wp_insert_post( [
            'post_type' => 'configurator_store', 'post_status' => 'publish',
            'post_title' => $f['name'], 'post_name' => $slug,
        ] );
    } else {
        wp_update_post( [ 'ID' => $store_id, 'post_title' => $f['name'] ] );
    }
    if ( ! $store_id || is_wp_error( $store_id ) ) {
        return [ 'verb' => 'Error', 'steps' => [ 'Could not create the store record.' ] ];
    }

    update_post_meta( $store_id, '_ac_store_slug',        $slug );
    update_post_meta( $store_id, '_tsa_store_type',       $f['type'] );
    update_post_meta( $store_id, '_ac_store_type',        $f['type'] ); // keep both in sync
    update_post_meta( $store_id, '_ac_is_active',         tsa_sb_active_from_status( $f['status'] ) );
    update_post_meta( $store_id, '_tsa_homepage_status',  $f['status'] );
    update_post_meta( $store_id, '_tsa_is_spotlight',     $f['spotlight'] ? '1' : '0' );
    update_post_meta( $store_id, '_tsa_store_tagline',    $f['tagline'] );
    update_post_meta( $store_id, '_ac_store_description', $f['description'] );
    update_post_meta( $store_id, '_tsa_school_ticker',    $f['ticker'] );
    update_post_meta( $store_id, '_tsa_store_cta_text',   'Shop ' . $f['name'] );
    update_post_meta( $store_id, '_tsa_store_cta_url',    '/schools/' . $slug . '/' );
    update_post_meta( $store_id, '_tsa_school_primary',   $f['primary'] );
    update_post_meta( $store_id, '_tsa_school_secondary', $f['secondary'] );
    update_post_meta( $store_id, '_tsa_school_mascot',    $f['mascot'] );
    update_post_meta( $store_id, '_tsa_school_level',     $f['level'] );
    update_post_meta( $store_id, '_tsa_school_pickups',   $f['pickups'] );
    update_post_meta( $store_id, '_tsa_school_shipping',  $f['shipping'] ? '1' : '0' );
    update_post_meta( $store_id, '_tsa_school_contact_email', $f['contact_email'] );
    if ( $f['logo_id'] ) set_post_thumbnail( $store_id, $f['logo_id'] );

    $steps[] = sprintf( 'Store record <a href="%s">%s</a> (slug <code>%s</code>, %s).',
        esc_url( get_edit_post_link( $store_id ) ), esc_html( $f['name'] ), esc_html( $slug ), esc_html( $f['status'] ) );

    // ── 2. Design Library scope term ──
    if ( taxonomy_exists( 'tsa_design_store' ) ) {
        if ( ! term_exists( $slug, 'tsa_design_store' ) ) {
            wp_insert_term( $f['name'], 'tsa_design_store', [ 'slug' => $slug ] );
            $steps[] = sprintf( 'Design Library store term <code>%s</code> created.', esc_html( $slug ) );
        } else {
            $steps[] = sprintf( 'Design Library store term <code>%s</code> already present.', esc_html( $slug ) );
        }
    }

    // ── 3. /schools/{slug}/ landing page ──
    $parent_id = tsa_sb_ensure_schools_parent();
    $page_id   = tsa_sb_ensure_school_page( $slug, $f['name'], $parent_id, $store_id );
    if ( $page_id ) {
        $steps[] = sprintf( 'Landing page <a href="%s">/schools/%s/</a> ready (view <a href="%s" target="_blank">live ↗</a>).',
            esc_url( get_edit_post_link( $page_id ) ), esc_html( $slug ), esc_url( home_url( '/schools/' . $slug . '/' ) ) );
    }

    // ── 3b. Programs (design categories + programs hub page) ──
    $programs = is_array( $f['programs'] ) ? $f['programs'] : [];
    update_post_meta( $store_id, '_tsa_school_programs', wp_json_encode( $programs, JSON_UNESCAPED_SLASHES ) );
    if ( $programs ) {
        $made = 0;
        if ( taxonomy_exists( 'tsa_design_category' ) ) {
            foreach ( $programs as $pr ) {
                if ( empty( $pr['slug'] ) ) continue;
                if ( ! term_exists( $pr['slug'], 'tsa_design_category' ) ) {
                    wp_insert_term( $pr['name'], 'tsa_design_category', [ 'slug' => $pr['slug'] ] );
                    $made++;
                }
            }
        }
        $hub_id = $page_id ? tsa_sb_ensure_programs_page( $slug, $f['name'], $page_id ) : 0;
        $steps[] = sprintf(
            '%d program%s saved (%d new design categor%s)%s.',
            count( $programs ), count( $programs ) === 1 ? '' : 's',
            $made, $made === 1 ? 'y' : 'ies',
            $hub_id ? sprintf( ' · hub at <a href="%s" target="_blank">/schools/%s/programs/ ↗</a>', esc_url( home_url( '/schools/' . $slug . '/programs/' ) ), esc_html( $slug ) ) : ''
        );
    }

    // ── 3c. Drops hub page (parties link to it via the Tee Party editor) ──
    if ( $page_id ) {
        $drops_id = tsa_sb_ensure_drops_page( $slug, $f['name'], $page_id );
        if ( $drops_id ) {
            $steps[] = sprintf( 'Drops page <a href="%s" target="_blank">/schools/%s/drops/ ↗</a> ready — link Tee Parties to this school in the party editor.',
                esc_url( home_url( '/schools/' . $slug . '/drops/' ) ), esc_html( $slug ) );
        }
    }

    // ── 4. Cart routing + per-store delivery ──
    $delivery = get_option( 'tsa_store_delivery', [] );
    if ( ! is_array( $delivery ) ) $delivery = [];
    $delivery[ $slug ] = [
        'pickups'  => array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $f['pickups'] ) ) ) ),
        'shipping' => (bool) $f['shipping'],
    ];
    update_option( 'tsa_store_delivery', $delivery );
    $steps[] = 'Cart routing + per-store delivery registered (Settings → TSA Stores).';

    // ── 5. Confirm directory/colors flow-through ──
    $steps[] = sprintf( 'Appears in the <a href="%s" target="_blank">school directory</a>, color map, and request-a-store dropdown automatically.', esc_url( home_url( '/schools/' ) ) );

    return [ 'verb' => $verb, 'steps' => $steps ];
}

/** Find a configurator_store post id by its _ac_store_slug. */
function tsa_sb_find_store_by_slug( string $slug ): int {
    $q = get_posts( [
        'post_type' => 'configurator_store', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
        'meta_query' => [ [ 'key' => '_ac_store_slug', 'value' => $slug ] ],
    ] );
    return $q ? (int) $q[0] : 0;
}

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

/** Ensure the /schools/{slug}/ page exists, on the school-store template, linked to the store. */
function tsa_sb_ensure_school_page( string $slug, string $name, int $parent_id, int $store_id ): int {
    $existing = get_posts( [
        'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
        'name' => $slug, 'post_parent' => $parent_id,
    ] );
    $page_id = $existing ? (int) $existing[0] : 0;
    if ( ! $page_id ) {
        $page_id = wp_insert_post( [
            'post_type' => 'page', 'post_status' => 'publish',
            'post_title' => $name, 'post_name' => $slug, 'post_parent' => $parent_id,
        ] );
    }
    if ( $page_id && ! is_wp_error( $page_id ) ) {
        update_post_meta( $page_id, '_wp_page_template', 'template-store-premium.php' );
        update_post_meta( $page_id, '_tsa_store_id', $store_id );
        return (int) $page_id;
    }
    return 0;
}

/** Ensure the /schools/{slug}/programs/ hub page exists, on the premium programs template. */
function tsa_sb_ensure_programs_page( string $slug, string $name, int $parent_page_id ): int {
    $existing = get_posts( [
        'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
        'name' => 'programs', 'post_parent' => $parent_page_id,
    ] );
    $pid = $existing ? (int) $existing[0] : 0;
    if ( ! $pid ) {
        $pid = wp_insert_post( [
            'post_type' => 'page', 'post_status' => 'publish',
            'post_title' => $name . ' Programs', 'post_name' => 'programs', 'post_parent' => $parent_page_id,
            'post_content' => '[tsa_school_programs school="' . esc_attr( $slug ) . '"]',
        ] );
    }
    if ( $pid && ! is_wp_error( $pid ) ) {
        update_post_meta( $pid, '_wp_page_template', 'template-store-programs.php' );
        return (int) $pid;
    }
    return 0;
}

/** Read a school store's saved program list: [ ['name','slug','status'], ... ]. */
function tsa_school_programs( int $store_id ): array {
    $raw = $store_id ? get_post_meta( $store_id, '_tsa_school_programs', true ) : '';
    $arr = $raw ? json_decode( $raw, true ) : [];
    return is_array( $arr ) ? $arr : [];
}

/**
 * [tsa_school_programs school="slug"] — school-colored program cards. Live
 * programs link to the school's designs filtered to that program; coming-soon
 * programs render muted. Reuses the Design Library (store + category scope).
 */
add_shortcode( 'tsa_school_programs', 'tsa_shortcode_school_programs' );
function tsa_shortcode_school_programs( $atts ): string {
    $atts = shortcode_atts( [ 'school' => '' ], $atts, 'tsa_school_programs' );
    $slug = sanitize_title( $atts['school'] );
    if ( ! $slug ) return '';

    $store_id = function_exists( 'tsa_sb_find_store_by_slug' ) ? tsa_sb_find_store_by_slug( $slug ) : 0;
    if ( ! $store_id ) return '';
    $programs = tsa_school_programs( $store_id );
    if ( ! $programs ) return '';

    $name    = get_the_title( $store_id );
    $cols    = function_exists( 'tsa_get_school_colors' ) ? tsa_get_school_colors( $name ) : [ 'primary' => '#5D4777', 'secondary' => '#9991A4' ];
    $primary = get_post_meta( $store_id, '_tsa_school_primary', true ) ?: $cols['primary'];
    $second  = get_post_meta( $store_id, '_tsa_school_secondary', true ) ?: ( $cols['secondary'] ?? $primary );
    $txt     = function_exists( 'tsa_readable_text' ) ? tsa_readable_text( $primary ) : '#fff';

    ob_start();
    ?>
    <div class="tsa-programs-grid" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(200px,1fr));gap:16px">
        <?php foreach ( $programs as $pr ) :
            $pname = $pr['name'] ?? '';
            $pslug = $pr['slug'] ?? sanitize_title( $pname );
            if ( ! $pname ) continue;
            $live  = ( ( $pr['status'] ?? '' ) === 'live' );
            $href  = home_url( '/design-library/?store=' . rawurlencode( $slug ) . '&cat=' . rawurlencode( $pslug ) );
            $tag   = $live ? 'a' : 'div';
            $attrs = $live ? ' href="' . esc_url( $href ) . '"' : '';
        ?>
        <<?php echo $tag; ?><?php echo $attrs; // phpcs:ignore ?> class="tsa-program-card<?php echo $live ? ' is-live' : ' is-soon'; ?>"
            style="display:block;border-radius:14px;overflow:hidden;text-decoration:none;border:1px solid rgba(0,0,0,.08);background:#fff;<?php echo $live ? '' : 'opacity:.85'; ?>">
            <div style="background:<?php echo esc_attr( $primary ); ?>;color:<?php echo esc_attr( $txt ); ?>;padding:20px 16px">
                <div style="font-size:17px;font-weight:800;letter-spacing:-.3px"><?php echo esc_html( $pname ); ?></div>
                <div style="font-size:12px;opacity:.85;margin-top:3px"><?php echo $live ? 'Shop the collection →' : 'Coming soon'; ?></div>
            </div>
            <div style="height:5px;background:<?php echo esc_attr( $second ); ?>"></div>
        </<?php echo $tag; ?>>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/** Ensure the /schools/{slug}/drops/ hub page exists (holds the drops shortcode). */
function tsa_sb_ensure_drops_page( string $slug, string $name, int $parent_page_id ): int {
    $existing = get_posts( [
        'post_type' => 'page', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
        'name' => 'drops', 'post_parent' => $parent_page_id,
    ] );
    if ( $existing ) return (int) $existing[0];
    $pid = wp_insert_post( [
        'post_type' => 'page', 'post_status' => 'publish',
        'post_title' => $name . ' Drops', 'post_name' => 'drops', 'post_parent' => $parent_page_id,
        'post_content' => '[tsa_school_drops school="' . esc_attr( $slug ) . '"]',
    ] );
    return ( $pid && ! is_wp_error( $pid ) ) ? (int) $pid : 0;
}

/**
 * [tsa_school_drops school="slug" active_only="0"] — the school's Tee Parties
 * grouped Live / Upcoming / Past (uses tsa_party_status()). Parties are linked
 * to a school via the Tee Party editor's School field. Returns '' if none.
 */
add_shortcode( 'tsa_school_drops', 'tsa_shortcode_school_drops' );
function tsa_shortcode_school_drops( $atts ): string {
    $atts = shortcode_atts( [ 'school' => '', 'active_only' => '0' ], $atts, 'tsa_school_drops' );
    $slug = sanitize_title( $atts['school'] );
    if ( ! $slug || ! function_exists( 'tsa_party_status' ) ) return '';
    $active_only = in_array( $atts['active_only'], [ '1', 1, true, 'true' ], true );

    $parties = get_posts( [
        'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1,
        'meta_query' => [ [ 'key' => '_tsa_party_store_slug', 'value' => $slug ] ],
    ] );
    if ( ! $parties ) return '';

    $live = []; $upcoming = []; $past = [];
    foreach ( $parties as $p ) {
        $st = tsa_party_status( $p->ID );
        if ( in_array( $st['status'], [ 'live', 'grace' ], true ) ) $live[] = [ $p, $st ];
        elseif ( $st['status'] === 'upcoming' ) $upcoming[] = [ $p, $st ];
        elseif ( $st['status'] === 'closed' )   $past[] = [ $p, $st ];
    }
    if ( $active_only ) $past = [];
    if ( ! $live && ! $upcoming && ! $past ) return '';

    $store_id = tsa_sb_find_store_by_slug( $slug );
    $sname    = $store_id ? get_the_title( $store_id ) : '';
    $cols     = function_exists( 'tsa_get_school_colors' ) ? tsa_get_school_colors( $sname ) : [ 'primary' => '#5D4777', 'secondary' => '#9991A4' ];
    $primary  = ( $store_id ? get_post_meta( $store_id, '_tsa_school_primary', true ) : '' ) ?: $cols['primary'];
    $txt      = function_exists( 'tsa_readable_text' ) ? tsa_readable_text( $primary ) : '#fff';

    $render = function ( $label, $items, $kind ) use ( $primary, $txt ) {
        if ( ! $items ) return;
        echo '<div style="margin-bottom:22px"><h3 style="font-size:14px;text-transform:uppercase;letter-spacing:.6px;color:#888;margin:0 0 12px">' . esc_html( $label ) . '</h3>';
        echo '<div style="display:grid;grid-template-columns:repeat(auto-fill,minmax(240px,1fr));gap:14px">';
        foreach ( $items as $pair ) {
            list( $p, $st ) = $pair;
            $title = get_post_meta( $p->ID, '_tsa_party_theme', true ) ?: get_the_title( $p->ID );
            if ( $kind === 'live' )     { $badge = '● Live'; $when = (int) $st['revealed'] . ' of ' . (int) $st['total_designs'] . ' designs revealed'; }
            elseif ( $kind === 'soon' ) { $badge = 'Upcoming'; $when = ! empty( $st['start_ts'] ) ? 'Opens ' . date_i18n( 'M j · g:ia', $st['start_ts'] ) : 'Coming soon'; }
            else                        { $badge = 'Ended'; $when = ! empty( $st['end_ts'] ) ? 'Ended ' . date_i18n( 'M j', $st['end_ts'] ) . ' · now in the library' : 'In the library'; }
            echo '<a href="' . esc_url( get_permalink( $p->ID ) ) . '" style="display:block;border-radius:14px;overflow:hidden;text-decoration:none;border:1px solid rgba(0,0,0,.08);background:#fff"' . ( $kind === 'past' ? ' ' : '' ) . '>';
            echo '<div style="background:' . esc_attr( $primary ) . ';color:' . esc_attr( $txt ) . ';padding:16px">';
            echo '<div style="font-size:11px;font-weight:800;letter-spacing:.5px;text-transform:uppercase;opacity:.85;margin-bottom:5px">' . esc_html( $badge ) . '</div>';
            echo '<div style="font-size:16px;font-weight:800;letter-spacing:-.3px">' . esc_html( $title ) . '</div></div>';
            echo '<div style="padding:11px 14px;font-size:12px;color:#666">' . esc_html( $when ) . '</div></a>';
        }
        echo '</div></div>';
    };

    ob_start();
    echo '<div class="tsa-drops">';
    $render( 'Live now', $live, 'live' );
    $render( 'Upcoming', $upcoming, 'soon' );
    $render( 'Past drops', $past, 'past' );
    echo '</div>';
    return ob_get_clean();
}

/** Small list of already-built school stores under the form. */
function tsa_sb_render_existing(): void {
    $records = tsa_school_store_records();
    echo '<hr style="margin:28px 0"><h2>Existing school stores</h2>';
    if ( ! $records ) { echo '<p><em>None generated yet. The directory still shows the seeded list until you build schools here.</em></p>'; return; }
    echo '<table class="widefat striped" style="max-width:920px"><thead><tr><th>School</th><th>Slug</th><th>Status</th><th>Store</th><th>Page</th></tr></thead><tbody>';
    foreach ( $records as $r ) {
        $page = get_posts( [ 'post_type' => 'page', 'name' => $r['slug'], 'numberposts' => 1, 'post_status' => 'any' ] );
        $page_link = $page ? '<a href="' . esc_url( home_url( $r['url'] ) ) . '" target="_blank">view ↗</a>' : '—';
        $edit_link = admin_url( 'admin.php?page=tsa-store-builder&tsa_sb_edit=' . $r['post_id'] );
        printf(
            '<tr><td><strong>%s</strong>%s</td><td><code>%s</code></td><td>%s</td><td><a href="%s">edit</a></td><td>%s</td></tr>',
            esc_html( $r['name'] ),
            $r['mascot'] ? ' <span style="color:#888">· ' . esc_html( $r['mascot'] ) . '</span>' : '',
            esc_html( $r['slug'] ),
            esc_html( $r['status'] ),
            esc_url( $edit_link ),
            $page_link
        );
    }
    echo '</tbody></table>';
}
