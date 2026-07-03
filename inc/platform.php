<?php
/**
 * TSA / Parabellum Platform Spine (idea #3, sub-project 1).
 *
 * The foundation that turns this codebase into a tiered, multi-tenant product:
 *   - a FEATURE CATALOG (single source of truth for every capability),
 *   - per-tenant ENTITLEMENTS + a fail-open GATE (tsa_feature_active),
 *   - a thin TENANT-AGENT boundary (entitlements in, events out) so a real
 *     control plane / clones slot in later with no feature-code changes,
 *   - a versioned, idempotent MIGRATION runner,
 *   - a STORE-TYPE registry (school built; team/business = planned), where
 *     store types are themselves gated features (stamp_school/team/business).
 *
 * FAIL-OPEN BY DESIGN: with no entitlements configured (the live single-site
 * reference build), every *built* feature is active — so adding this file does
 * NOT change TSA's behavior. Gating only engages once a tenant's entitlements
 * are set (the multi-tenant / Parabellum case). 'planned' features are always
 * off until built.
 */
defined( 'ABSPATH' ) || exit;

/** Current platform (code) version — bump when registering a migration. */
function tsa_platform_version(): string { return apply_filters( 'tsa_platform_version', '1.0.0' ); }

/* ─────────────────────────────────────────────────────────────────
   FEATURE CATALOG — the source of truth.
   lifecycle: ga | beta | alpha | planned | deprecated
   store_type: '' = applies platform-wide; else scoped to that store type.
───────────────────────────────────────────────────────────────── */
function tsa_feature_catalog(): array {
    static $cat = null;
    if ( $cat !== null ) return $cat;

    $defs = [
        'configurator'        => [ 'name' => 'Apparel Configurator',        'group' => 'Core',        'lifecycle' => 'ga' ],
        'design_library'      => [ 'name' => 'Design Library',              'group' => 'Core',        'lifecycle' => 'ga' ],
        'blank_catalog'       => [ 'name' => 'Blank Apparel Catalog',       'group' => 'Core',        'lifecycle' => 'ga' ],

        'stamp_school'        => [ 'name' => 'School Stores',               'group' => 'Store types', 'store_type' => 'school',   'lifecycle' => 'ga' ],
        'stamp_team'          => [ 'name' => 'Team Stores',                 'group' => 'Store types', 'store_type' => 'team',     'lifecycle' => 'ga' ],
        'stamp_business'      => [ 'name' => 'Business Stores',             'group' => 'Store types', 'store_type' => 'business', 'lifecycle' => 'ga' ],

        'tee_parties'         => [ 'name' => 'Tee Parties (drops)',         'group' => 'Engage',      'lifecycle' => 'ga' ],
        'fundraising'         => [ 'name' => 'Fundraising',                 'group' => 'Monetize',    'requires' => [ 'design_library' ], 'lifecycle' => 'ga' ],
        'ambassadors'         => [ 'name' => 'Ambassadors + QR',            'group' => 'Engage',      'lifecycle' => 'ga' ],
        'reports'             => [ 'name' => 'Reports',                     'group' => 'Insight',     'lifecycle' => 'ga' ],

        'gang_sheet'              => [ 'name' => 'Gang Sheet Builder',         'group' => 'Tools', 'lifecycle' => 'ga' ],
        'gang_sheet_custom_sizes' => [ 'name' => 'Gang Sheet — Custom Sizes',  'group' => 'Tools', 'requires' => [ 'gang_sheet' ], 'lifecycle' => 'ga' ],

        'turnkey_fulfillment' => [ 'name' => 'Turnkey Fulfillment (Parabellum)', 'group' => 'Fulfillment', 'lifecycle' => 'beta' ],

        // Platform-tier upsell. 'planned' until the enforcement ships (custom-domain mapping +
        // hiding the Parabellum branding/footer credit) — same pattern as stamp_team/business.
        'white_label'         => [ 'name' => 'White-Label (custom domain + no Parabellum branding)', 'group' => 'Platform', 'lifecycle' => 'planned' ],
    ];

    $norm = [];
    foreach ( $defs as $id => $d ) {
        $norm[ $id ] = array_merge(
            [ 'id' => $id, 'name' => $id, 'group' => 'Misc', 'requires' => [], 'store_type' => '', 'lifecycle' => 'ga' ],
            $d
        );
    }
    $cat = apply_filters( 'tsa_feature_catalog', $norm );
    return $cat;
}

/* ─────────────────────────────────────────────────────────────────
   TENANT-AGENT BOUNDARY — entitlements in, events out.
   v1 reads/writes locally (per-site option = per-tenant on Multisite). The
   filters are where a control-plane sync (or a clone's license) hooks in later.
───────────────────────────────────────────────────────────────── */

/** Active feature ids for this tenant. [] (unset) = reference build → all built features on. */
function tsa_entitlements_get(): array {
    $ent = apply_filters( 'tsa_entitlements', get_option( 'tsa_entitlements', null ) );
    return is_array( $ent ) ? array_values( array_filter( array_map( 'sanitize_key', $ent ) ) ) : [];
}

/** Push an event (order, status, usage) toward the control plane. Local no-op until the hub exists. */
function tsa_report_event( string $type, array $data = [] ): void {
    do_action( 'tsa_platform_event', $type, $data );
}

/* ─────────────────────────────────────────────────────────────────
   THE GATE — wrap every feature entry point in this.
───────────────────────────────────────────────────────────────── */
function tsa_feature_active( string $id ): bool {
    $cat  = tsa_feature_catalog();
    $life = $cat[ $id ]['lifecycle'] ?? 'ga';
    if ( $life === 'planned' || $life === 'deprecated' ) return false; // not (or no longer) shippable

    $ent = tsa_entitlements_get();
    if ( empty( $ent ) ) return true; // reference build / unconfigured → everything built is on

    // Dependencies must also be entitled.
    if ( isset( $cat[ $id ] ) ) {
        foreach ( (array) $cat[ $id ]['requires'] as $dep ) {
            if ( ! in_array( $dep, $ent, true ) ) return false;
        }
    }
    return in_array( $id, $ent, true );
}

/** Convenience for REST/templates: bail if a feature is off. */
function tsa_require_feature( string $id ): bool {
    return tsa_feature_active( $id );
}

/* ─────────────────────────────────────────────────────────────────
   TIER REGISTRY — sellable plans = named bundles of catalog features.
   Bundles intentionally INCLUDE planned features (team/business stores,
   white-label): the gate keeps them off until they ship, then they
   activate automatically for already-entitled tenants (no re-stamp).
───────────────────────────────────────────────────────────────── */
function tsa_tiers(): array {
    $core = [ 'configurator', 'design_library', 'blank_catalog' ];
    $pro  = array_merge( $core, [
        'gang_sheet', 'gang_sheet_custom_sizes',
        'stamp_school', 'stamp_team', 'stamp_business',
        'tee_parties', 'ambassadors', 'fundraising',
    ] );
    $studio = array_merge( $pro, [ 'reports', 'turnkey_fulfillment', 'white_label' ] );

    // Prices = SaaS monthly (flat — what every tenant pays). Turnkey fulfillment is
    // billed SEPARATELY by order-volume bands (<=250 $99 / 251-1k $179 / 1k-3k $329 /
    // 3k+ $549). The bands apply ONLY to turnkey customers (Parabellum fulfills = the
    // enforceable chokepoint — every order is auto-counted, can't be routed around);
    // self-fulfill customers pay the flat SaaS tier ONLY (a per-sale fee on orders we
    // never touch is unenforceable). See tshirt_pricing_profit.xlsx. Starter has an
    // optional $79 founding price (acquisition lever, grandfathered) toggled there.
    $tiers = [
        'starter' => [ 'name' => 'Starter', 'price' => 99,  'features' => $core ],
        'pro'     => [ 'name' => 'Pro',     'price' => 199, 'features' => $pro ],
        'studio'  => [ 'name' => 'Studio',  'price' => 349, 'features' => $studio ],
    ];
    return apply_filters( 'tsa_tiers', $tiers );
}

/** Apply a named tier to this tenant — writes entitlements + remembers the tier. Returns features applied ([] on unknown slug). */
function tsa_apply_tier( string $slug ): array {
    $tiers = tsa_tiers();
    if ( ! isset( $tiers[ $slug ] ) ) return [];
    $features = array_values( array_unique( array_map( 'sanitize_key', $tiers[ $slug ]['features'] ) ) );
    update_option( 'tsa_entitlements', $features );
    update_option( 'tsa_tier', sanitize_key( $slug ) );
    return $features;
}

/** Current tier slug for this tenant; '' when unconfigured or hand-customized. */
function tsa_current_tier(): string {
    return (string) get_option( 'tsa_tier', '' );
}

/* ─────────────────────────────────────────────────────────────────
   STORE-TYPE REGISTRY — store types are gated features (stamp_*).
   Generalizes the school stamp engine to teams + business (planned).
───────────────────────────────────────────────────────────────── */
function tsa_store_types(): array {
    $types = [
        'school'   => [ 'label' => 'School',   'feature' => 'stamp_school' ],
        'team'     => [ 'label' => 'Team',     'feature' => 'stamp_team' ],
        'business' => [ 'label' => 'Business', 'feature' => 'stamp_business' ],
        'event'    => [ 'label' => 'Event',    'feature' => 'stamp_event' ],
    ];
    return apply_filters( 'tsa_store_types', $types );
}

/** Is a store type available to stamp on this tenant? (its stamp_* feature is active) */
function tsa_store_type_available( string $type ): bool {
    $types = tsa_store_types();
    if ( ! isset( $types[ $type ] ) ) return false;
    $f = $types[ $type ]['feature'] ?? '';
    return $f ? tsa_feature_active( $f ) : true;
}

/* ─────────────────────────────────────────────────────────────────
   MIGRATION RUNNER — versioned, idempotent, per-tenant.
   Register with tsa_register_migration('1.1.0', fn() => ...). Runs once each,
   in version order, the first admin load after the code version advances.
───────────────────────────────────────────────────────────────── */
function tsa_register_migration( string $version, callable $cb ): void {
    $GLOBALS['tsa_migrations'][] = [ 'version' => $version, 'cb' => $cb ];
}

add_action( 'admin_init', 'tsa_run_migrations' );
function tsa_run_migrations(): void {
    $code = tsa_platform_version();
    $have = get_option( 'tsa_platform_db_version', '0' );
    if ( version_compare( $have, $code, '>=' ) ) return;

    $migs = $GLOBALS['tsa_migrations'] ?? [];
    usort( $migs, function( $a, $b ) { return version_compare( $a['version'], $b['version'] ); } );
    foreach ( $migs as $m ) {
        if ( version_compare( $m['version'], $have, '>' ) && version_compare( $m['version'], $code, '<=' ) ) {
            try { call_user_func( $m['cb'] ); } catch ( \Throwable $e ) { /* keep going; log if WP_DEBUG */ if ( defined( 'WP_DEBUG' ) && WP_DEBUG ) error_log( 'tsa migration ' . $m['version'] . ': ' . $e->getMessage() ); }
        }
    }
    update_option( 'tsa_platform_db_version', $code );
}

/* ─────────────────────────────────────────────────────────────────
   ADMIN — Platform screen (catalog + entitlement toggles, for testing tiers)
───────────────────────────────────────────────────────────────── */
add_action( 'admin_menu', function () {
    add_menu_page( 'Platform', 'Platform', 'manage_options', 'tsa-platform', 'tsa_platform_page', 'dashicons-admin-generic', 30 );
} );

function tsa_platform_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    // Quick-apply a named tier (button value carries the slug).
    if ( ! empty( $_POST['tsa_apply_tier'] ) && check_admin_referer( 'tsa_platform', 'tsa_platform_nonce' ) ) {
        $slug    = sanitize_key( $_POST['tsa_apply_tier'] );
        $applied = tsa_apply_tier( $slug );
        if ( $applied ) {
            $all = tsa_tiers();
            echo '<div class="notice notice-success is-dismissible"><p>Applied the <strong>' . esc_html( $all[ $slug ]['name'] ) . '</strong> tier — ' . count( $applied ) . ' features entitled.</p></div>';
        }
    }

    if ( isset( $_POST['tsa_platform_save'] ) && check_admin_referer( 'tsa_platform', 'tsa_platform_nonce' ) ) {
        if ( ! empty( $_POST['tsa_platform_unconfigured'] ) ) {
            delete_option( 'tsa_entitlements' ); // back to fail-open "all on"
            delete_option( 'tsa_tier' );
        } else {
            $sel = array_map( 'sanitize_key', (array) ( $_POST['tsa_features'] ?? [] ) );
            update_option( 'tsa_entitlements', array_values( $sel ) );
            update_option( 'tsa_tier', '' ); // hand-customized → no longer a named tier
        }
        echo '<div class="notice notice-success is-dismissible"><p>Entitlements saved.</p></div>';
    }

    $cat = tsa_feature_catalog();
    $ent = get_option( 'tsa_entitlements', null );
    $unconfigured = ! is_array( $ent );
    $active_ids   = $unconfigured ? [] : array_map( 'sanitize_key', $ent );

    // group catalog
    $groups = [];
    foreach ( $cat as $f ) $groups[ $f['group'] ][] = $f;
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-admin-generic" style="font-size:28px;width:28px;height:28px;vertical-align:-4px"></span> Platform</h1>
        <p>The feature catalog for this tenant. <strong>Entitlements</strong> decide which features are active — leave it <em>unconfigured</em> (the default) and every built feature is on (the reference build). Configure it to simulate a tier. <code>planned</code> features stay off until built.</p>
        <p style="font-size:12px;color:#666">Platform version <code><?php echo esc_html( tsa_platform_version() ); ?></code> · DB version <code><?php echo esc_html( get_option( 'tsa_platform_db_version', '0' ) ); ?></code></p>

        <?php $tiers = tsa_tiers(); $cur_tier = tsa_current_tier(); ?>
        <h2 style="margin-top:1.2em">Quick-apply a tier</h2>
        <p style="max-width:760px">Stamp this tenant with a sellable plan — writes its entitlements from the tier bundle. <code>planned</code> features (Team/Business stores, White-Label) are intentionally included so they switch on automatically the moment they ship, with no re-stamp.</p>
        <form method="post" style="margin:0 0 2em">
            <?php wp_nonce_field( 'tsa_platform', 'tsa_platform_nonce' ); ?>
            <table class="widefat striped" style="max-width:760px">
                <thead><tr><th>Tier</th><th style="width:90px">Price/mo</th><th>Includes</th><th style="width:80px"></th></tr></thead>
                <tbody>
                <?php foreach ( $tiers as $slug => $t ) :
                    $is_cur = ( $slug === $cur_tier ); ?>
                    <tr<?php echo $is_cur ? ' style="background:#eef7ee"' : ''; ?>>
                        <td><strong><?php echo esc_html( $t['name'] ); ?></strong><?php echo $is_cur ? ' <span style="color:#1a7f37;font-size:12px">● current</span>' : ''; ?></td>
                        <td>$<?php echo esc_html( number_format( (float) $t['price'] ) ); ?></td>
                        <td style="font-size:12px;color:#555"><?php echo esc_html( implode( ', ', $t['features'] ) ); ?></td>
                        <td><button class="button button-secondary" name="tsa_apply_tier" value="<?php echo esc_attr( $slug ); ?>">Apply</button></td>
                    </tr>
                <?php endforeach; ?>
                </tbody>
            </table>
        </form>

        <h2>Entitlements (fine-grained)</h2>
        <form method="post">
            <?php wp_nonce_field( 'tsa_platform', 'tsa_platform_nonce' ); ?>
            <p><label><input type="checkbox" name="tsa_platform_unconfigured" value="1" <?php checked( $unconfigured ); ?> id="tsa-unconf"> <strong>Unconfigured</strong> — all built features active (reference build / single-site TSA)</label></p>

            <table class="widefat striped" style="max-width:760px" id="tsa-feature-table">
                <thead><tr><th style="width:90px">Active</th><th>Feature</th><th>Group</th><th>Lifecycle</th><th>Requires</th></tr></thead>
                <tbody>
                <?php foreach ( $groups as $gname => $feats ) : ?>
                    <tr><td colspan="5" style="background:#f6f7f7;font-weight:600"><?php echo esc_html( $gname ); ?></td></tr>
                    <?php foreach ( $feats as $f ) :
                        $planned = in_array( $f['lifecycle'], [ 'planned', 'deprecated' ], true );
                        $on      = $unconfigured ? ! $planned : in_array( $f['id'], $active_ids, true );
                    ?>
                    <tr>
                        <td><input type="checkbox" class="tsa-feat" name="tsa_features[]" value="<?php echo esc_attr( $f['id'] ); ?>" <?php checked( $on ); disabled( $planned ); ?>></td>
                        <td><strong><?php echo esc_html( $f['name'] ); ?></strong> <code style="font-size:11px"><?php echo esc_html( $f['id'] ); ?></code></td>
                        <td><?php echo esc_html( $f['group'] ); ?></td>
                        <td><?php echo esc_html( $f['lifecycle'] ); ?><?php echo $f['store_type'] ? ' · ' . esc_html( $f['store_type'] ) : ''; ?></td>
                        <td><?php echo $f['requires'] ? '<code>' . esc_html( implode( ', ', $f['requires'] ) ) . '</code>' : '—'; ?></td>
                    </tr>
                    <?php endforeach; ?>
                <?php endforeach; ?>
                </tbody>
            </table>
            <p class="submit"><button class="button button-primary" name="tsa_platform_save" value="1">Save entitlements</button></p>
        </form>
    </div>
    <script>
    (function(){
        var unconf=document.getElementById('tsa-unconf');
        function sync(){ document.querySelectorAll('.tsa-feat').forEach(function(c){ if(!c.disabled) c.closest('tr').style.opacity = unconf.checked ? .5 : 1; }); }
        if(unconf){ unconf.addEventListener('change',sync); sync(); }
    })();
    </script>
    <?php
}
