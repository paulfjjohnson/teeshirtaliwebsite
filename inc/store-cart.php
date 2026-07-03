<?php
/**
 * TSA Store Cart Rules
 * ------------------------------------------------------------------
 * One store per cart. A shopper can only check out one store/school/team
 * (or the general TSA shop) at a time, because each store fulfils
 * separately and has its own pickup/delivery options (see Phase 2).
 *
 * "Store" identity per cart item:
 *   - Configurator items → ac_configurator['store_slug']
 *   - Regular products   → the product's product_cat that is a registered store
 *
 * Registered store slugs come from: configurator_store posts (_ac_store_slug),
 * the Settings > TSA Stores list, and the 'tsa_store_slugs' filter.
 */
defined( 'ABSPATH' ) || exit;

/** All slugs that identify a store (these double as store product_cat slugs). */
function tsa_store_slugs(): array {
    $slugs = [];
    foreach ( get_posts( [ 'post_type' => 'configurator_store', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids' ] ) as $sid ) {
        $s = get_post_meta( $sid, '_ac_store_slug', true );
        if ( $s ) $slugs[] = sanitize_title( $s );
    }
    $extra = preg_split( '/[\s,]+/', (string) get_option( 'tsa_store_cats', '' ) );
    foreach ( (array) $extra as $e ) {
        $e = sanitize_title( $e );
        if ( $e ) $slugs[] = $e;
    }
    return array_values( array_unique( array_filter( apply_filters( 'tsa_store_slugs', $slugs ) ) ) );
}

/** Store key for a product (its first product_cat that is a registered store). '' = general. */
function tsa_product_store_key( int $product_id ): string {
    if ( ! $product_id ) return '';
    $stores = tsa_store_slugs();
    if ( ! $stores ) return '';
    $cats = wp_get_post_terms( $product_id, 'product_cat', [ 'fields' => 'slugs' ] );
    if ( is_wp_error( $cats ) ) return '';
    foreach ( (array) $cats as $slug ) {
        if ( in_array( $slug, $stores, true ) ) return $slug;
    }
    return '';
}

/** Store key for a cart item (configurator OR regular product). '' = general. */
function tsa_cart_item_store_key( array $item ): string {
    if ( ! empty( $item['ac_configurator']['store_slug'] ) ) {
        return sanitize_title( $item['ac_configurator']['store_slug'] );
    }
    return tsa_product_store_key( (int) ( $item['product_id'] ?? 0 ) );
}

/** The store currently represented in the cart (first non-general item), or ''. */
function tsa_cart_store_key(): string {
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) return '';
    foreach ( WC()->cart->get_cart() as $item ) {
        $k = tsa_cart_item_store_key( $item );
        if ( $k !== '' ) return $k;
    }
    return '';
}

/** Human-readable store name from a slug. */
function tsa_store_name( string $slug ): string {
    if ( $slug === '' ) return 'your current order';
    foreach ( get_posts( [ 'post_type' => 'configurator_store', 'post_status' => 'publish', 'numberposts' => -1 ] ) as $p ) {
        if ( sanitize_title( get_post_meta( $p->ID, '_ac_store_slug', true ) ) === $slug ) return $p->post_title;
    }
    $term = get_term_by( 'slug', $slug, 'product_cat' );
    return $term ? $term->name : ucwords( str_replace( '-', ' ', $slug ) );
}

/**
 * Public landing ("Shop") URL for a store slug — type-agnostic (school / team /
 * business). Prefers the store record's saved CTA/landing URL (_tsa_store_cta_url);
 * falls back to the /schools/{slug}/ page convention. Returns '' for the Main TSA
 * slug or an empty slug (no dedicated storefront).
 */
function tsa_store_url( string $slug ): string {
    $slug = sanitize_title( $slug );
    if ( $slug === '' || $slug === 'tsa' ) return '';
    foreach ( get_posts( [ 'post_type' => 'configurator_store', 'post_status' => 'publish', 'numberposts' => -1 ] ) as $p ) {
        if ( sanitize_title( get_post_meta( $p->ID, '_ac_store_slug', true ) ) === $slug ) {
            $cta = trim( (string) get_post_meta( $p->ID, '_tsa_store_cta_url', true ) );
            if ( $cta !== '' ) return ( strpos( $cta, 'http' ) === 0 ) ? $cta : home_url( $cta );
            break;
        }
    }
    return home_url( '/schools/' . $slug . '/' );
}

/**
 * Block adding a product from a different store than the one already in the cart.
 * (Covers regular product-page adds; the configurator REST add enforces the same
 * rule server-side in the plugin.)
 */
add_filter( 'woocommerce_add_to_cart_validation', 'tsa_block_cross_store_add', 10, 3 );
function tsa_block_cross_store_add( $passed, $product_id, $qty ) {
    $current = tsa_cart_store_key();
    if ( $current === '' ) return $passed;                 // empty / general-only cart
    $incoming = tsa_product_store_key( (int) $product_id );
    if ( $incoming === '' || $incoming === $current ) return $passed; // general item or same store
    wc_add_notice(
        sprintf(
            'Your cart has items from <strong>%1$s</strong>. Please check out or empty that order before adding items from <strong>%2$s</strong> — each store ships and is picked up separately. <a href="%3$s">View cart</a>',
            esc_html( tsa_store_name( $current ) ),
            esc_html( tsa_store_name( $incoming ) ),
            esc_url( wc_get_cart_url() )
        ),
        'error'
    );
    return false;
}

/* ── Settings > TSA Stores : list extra store product-category slugs ── */
add_action( 'admin_menu', function () {
    add_options_page( 'TSA Stores', 'TSA Stores', 'manage_options', 'tsa-stores', 'tsa_stores_settings_page' );
} );
add_action( 'admin_init', function () {
    register_setting( 'tsa_stores', 'tsa_store_cats', [ 'sanitize_callback' => 'sanitize_textarea_field' ] );
    register_setting( 'tsa_stores', 'tsa_store_delivery', [ 'sanitize_callback' => 'tsa_sanitize_store_delivery' ] );
} );

/** Sanitize per-store delivery config: [ slug => ['pickups'=>[...], 'shipping'=>bool] ]. */
function tsa_sanitize_store_delivery( $input ): array {
    $out = [];
    if ( is_array( $input ) ) {
        foreach ( $input as $slug => $cfg ) {
            $slug = sanitize_title( $slug );
            if ( $slug === '' ) continue;
            $pickups = array_values( array_filter( array_map(
                'sanitize_text_field',
                preg_split( '/\r\n|\r|\n/', (string) ( $cfg['pickups'] ?? '' ) )
            ) ) );
            $out[ $slug ] = [ 'pickups' => $pickups, 'shipping' => ! empty( $cfg['shipping'] ) ];
        }
    }
    return $out;
}

/** Delivery config for a store slug, or null if none set. */
function tsa_store_delivery_cfg( string $slug ): ?array {
    if ( $slug === '' ) return null;
    $all = get_option( 'tsa_store_delivery', [] );
    return ( is_array( $all ) && isset( $all[ $slug ] ) ) ? $all[ $slug ] : null;
}
function tsa_stores_settings_page(): void {
    $detected = tsa_store_slugs();
    ?>
    <div class="wrap">
        <h1>TSA Stores</h1>
        <p>The cart is limited to <strong>one store at a time</strong>. List the WooCommerce <em>product category slugs</em> that represent a store (school / team / collection) — one per line or comma-separated. Configurator store slugs are detected automatically; add any regular-product store categories here.</p>
        <form method="post" action="options.php">
            <?php settings_fields( 'tsa_stores' ); ?>
            <table class="form-table">
                <tr>
                    <th><label for="tsa_store_cats">Store category slugs</label></th>
                    <td>
                        <textarea id="tsa_store_cats" name="tsa_store_cats" rows="6" class="large-text code"><?php echo esc_textarea( get_option( 'tsa_store_cats', '' ) ); ?></textarea>
                        <p class="description">e.g. <code>dutchtown-color-guard</code>, <code>dutchtown-band</code></p>
                    </td>
                </tr>
            </table>

            <h2>Currently recognized as stores</h2>
            <p><?php echo $detected ? '<code>' . implode( '</code>, <code>', array_map( 'esc_html', $detected ) ) . '</code>' : '<em>None detected yet — add slugs above and save, then reload to configure delivery.</em>'; ?></p>

            <hr>
            <h2>Per-store delivery</h2>
            <p>For each store, list its pickup location(s) — one per line (e.g. <code>Blakely's House — 123 Main St</code>) — and tick whether shipping is offered. At checkout, a single-store cart shows only that store's options. Leave a store blank to keep the site-wide defaults.</p>
            <?php if ( empty( $detected ) ) : ?>
                <p><em>Add store slugs above and save first, then reload to configure delivery.</em></p>
            <?php else :
                $delivery = get_option( 'tsa_store_delivery', [] );
                foreach ( $detected as $slug ) :
                    $cfg     = is_array( $delivery ) && isset( $delivery[ $slug ] ) ? $delivery[ $slug ] : [ 'pickups' => [], 'shipping' => false ];
                    $pickups = implode( "\n", (array) ( $cfg['pickups'] ?? [] ) );
                    ?>
                    <table class="form-table" style="border-top:1px solid #ddd">
                        <tr>
                            <th style="width:220px"><strong><?php echo esc_html( tsa_store_name( $slug ) ); ?></strong><br><code style="font-size:11px"><?php echo esc_html( $slug ); ?></code></th>
                            <td>
                                <p>
                                    <label><strong>Pickup locations</strong> (one per line)</label><br>
                                    <textarea name="tsa_store_delivery[<?php echo esc_attr( $slug ); ?>][pickups]" rows="3" class="large-text code"><?php echo esc_textarea( $pickups ); ?></textarea>
                                </p>
                                <p>
                                    <label>
                                        <input type="checkbox" name="tsa_store_delivery[<?php echo esc_attr( $slug ); ?>][shipping]" value="1" <?php checked( ! empty( $cfg['shipping'] ) ); ?>>
                                        Offer shipping for this store (else pickup only)
                                    </label>
                                </p>
                            </td>
                        </tr>
                    </table>
                <?php endforeach;
            endif; ?>
            <?php submit_button( 'Save delivery options' ); ?>
        </form>
    </div>
    <?php
}

/* ── Per-store delivery at checkout ──────────────────────────────────
   Single-store cart → show only that store's pickup location(s), plus
   shipping only if the store allows it. Stores with no config keep the
   site-wide defaults. ─────────────────────────────────────────────── */

// Include the store in the shipping package so WooCommerce recalculates
// rates when the cart's store changes (busts the rate cache).
add_filter( 'woocommerce_cart_shipping_packages', function ( $packages ) {
    $store = function_exists( 'tsa_cart_store_key' ) ? tsa_cart_store_key() : '';
    foreach ( $packages as $k => $p ) {
        $packages[ $k ]['tsa_store'] = $store;
    }
    return $packages;
} );

add_filter( 'woocommerce_package_rates', 'tsa_per_store_shipping_rates', 100, 2 );
function tsa_per_store_shipping_rates( $rates, $package ) {
    $store = $package['tsa_store'] ?? ( function_exists( 'tsa_cart_store_key' ) ? tsa_cart_store_key() : '' );
    $cfg   = $store !== '' ? tsa_store_delivery_cfg( $store ) : null;
    if ( ! $cfg ) return $rates; // no per-store config → leave defaults

    return tsa_build_store_shipping_rates( $rates, $store, $cfg );
}

/** Apply one store's delivery policy to WooCommerce package rates. */
function tsa_build_store_shipping_rates( array $rates, string $store, array $cfg ): array {
    if ( $store === '' ) return $rates;

    $new = [];

    // Store-specific pickup location(s) — free. Flatten defensively: older or
    // mis-synced config can store a nested array (which casts to the literal
    // "Array"), so walk recursively and keep only clean, non-empty strings.
    $pickup_labels = [];
    $pickup_raw    = (array) ( $cfg['pickups'] ?? [] ); // var (not a cast expr) — array_walk_recursive takes it by reference
    array_walk_recursive( $pickup_raw, function ( $v ) use ( &$pickup_labels ) {
        $v = trim( (string) $v );
        if ( $v !== '' && strcasecmp( $v, 'Array' ) !== 0 ) $pickup_labels[] = $v;
    } );
    foreach ( $pickup_labels as $i => $label ) {
        $id          = 'tsa_pickup_' . $store . '_' . $i;
        $new[ $id ]  = new WC_Shipping_Rate( $id, 'Local pickup — ' . $label, 0, [], 'tsa_pickup' );
    }

    // Shipping rates kept only if the store offers shipping.
    if ( ! empty( $cfg['shipping'] ) ) {
        foreach ( $rates as $rid => $rate ) {
            $mid = method_exists( $rate, 'get_method_id' ) ? $rate->get_method_id() : '';
            if ( $mid === 'local_pickup' || $mid === 'tsa_pickup' ) continue; // replace pickups with ours
            $new[ $rid ] = $rate;
        }
    }

    // Never return an empty set (would block checkout) — fall back to defaults.
    return $new ? $new : $rates;
}

/* ── Cart display polish: hide empty / "false" add-on rows ───────────
   Checkbox-style product add-ons can render their raw "false" value in
   the cart (e.g. "Add Player Number…: false"). Drop empties + falses. */
/** Admin-only checkout proof of the store identity used for delivery rules. */
add_action( 'woocommerce_checkout_before_order_review_heading', 'tsa_checkout_shipping_diagnostic', 5 );
function tsa_checkout_shipping_diagnostic(): void {
    if ( ! current_user_can( 'manage_woocommerce' ) ) return;

    $store    = function_exists( 'tsa_cart_store_key' ) ? tsa_cart_store_key() : '';
    $rate_ids = [];
    if ( function_exists( 'WC' ) && WC()->shipping() ) {
        foreach ( (array) WC()->shipping()->get_packages() as $package ) {
            $rate_ids = array_merge( $rate_ids, array_keys( (array) ( $package['rates'] ?? [] ) ) );
        }
    }

    echo '<div class="woocommerce-info" style="font-size:12px">'
       . '<strong>Admin shipping diagnostic:</strong> store <code>' . esc_html( $store !== '' ? $store : '(general / none)' ) . '</code>; rates <code>'
       . esc_html( $rate_ids ? implode( ', ', $rate_ids ) : '(none)' )
       . '</code></div>';
}

add_filter( 'woocommerce_get_item_data', 'tsa_hide_empty_cart_item_meta', 99, 2 );
function tsa_hide_empty_cart_item_meta( $data, $cart_item ) {
    foreach ( $data as $i => $row ) {
        $val = isset( $row['value'] ) ? trim( wp_strip_all_tags( (string) $row['value'] ) ) : '';
        if ( $val === '' || in_array( strtolower( $val ), [ 'false', 'no value', '—', '-' ], true ) ) {
            unset( $data[ $i ] );
        }
    }
    return array_values( $data );
}

/* ── Cart thumbnail for Configurator orders ──────────────────────────
   Configurator items ride a generic "vehicle" product with no image. Rebuild
   the configurator's live preview as a small HTML composite — the garment's
   color mockup with the chosen design overlaid in its print zone (same %-boxes
   as the React Mockup). No canvas/CORS issues. Falls back to design preview →
   garment image → default. */

/** CSS box (mirrors the configurator's ZONE_POS) for a print zone. */
function tsa_cart_zone_box( $zone ) {
    switch ( $zone ) {
        case 'front_pocket': return 'top:23%;left:54%;width:18%;height:14%';
        case 'back_full':
        case 'front_full':
        default:             return 'top:22%;left:50%;transform:translateX(-50%);width:46%;height:42%';
    }
}

add_filter( 'woocommerce_cart_item_thumbnail', 'tsa_configurator_cart_thumbnail', 10, 3 );
function tsa_configurator_cart_thumbnail( $thumbnail, $cart_item, $cart_item_key ) {
    return tsa_configurator_cart_image( $cart_item, $thumbnail );
}

/** Build the configurator mockup composite (garment color mockup + design
    overlaid in its print zone) for a cart item, or return $fallback if it's not
    a configurator item / has no usable images. Shared by the cart page filter
    above and the mini-cart drawer. */
function tsa_configurator_cart_image( $cart_item, $fallback = '' ) {
    if ( empty( $cart_item['ac_configurator'] ) ) return $fallback;
    $ac = $cart_item['ac_configurator'];

    // Chosen design preview + its zone (design array is keyed by zone).
    $design_url = ''; $zone = 'front_full';
    if ( ! empty( $ac['designs'] ) && is_array( $ac['designs'] ) ) {
        foreach ( $ac['designs'] as $z => $d ) {
            $did = absint( $d );
            if ( ! $did ) continue;
            $design_url = get_post_meta( $did, '_design_preview_url', true ) ?: ( get_the_post_thumbnail_url( $did, 'medium' ) ?: '' );
            if ( is_string( $z ) && $z !== '' ) $zone = $z;
            if ( $design_url ) break;
        }
    }

    // Garment mockup for the chosen color (back image if it's a back design).
    $garment_url = '';
    if ( ! empty( $ac['garment_id'] ) ) {
        $gid     = absint( $ac['garment_id'] );
        $color   = (string) ( $ac['color'] ?? '' );
        $mockups = get_post_meta( $gid, '_ac_mockup_images', true );
        if ( is_array( $mockups ) && $color !== '' && ! empty( $mockups[ $color ] ) ) {
            $set = $mockups[ $color ];
            $garment_url = ( $zone === 'back_full' && ! empty( $set['back'] ) ) ? $set['back'] : ( $set['front'] ?? '' );
        }
        if ( ! $garment_url ) $garment_url = get_the_post_thumbnail_url( $gid, 'woocommerce_thumbnail' ) ?: '';
    }

    // Composite: design on the garment, mirroring the configurator preview.
    if ( $garment_url && $design_url ) {
        return '<div class="tsa-cart-mockup" style="position:relative;width:100%;line-height:0">'
             . '<img src="' . esc_url( $garment_url ) . '" alt="" style="width:100%;height:auto;display:block;border-radius:10px" />'
             . '<span style="position:absolute;' . tsa_cart_zone_box( $zone ) . ';display:flex;align-items:flex-start;justify-content:center">'
             . '<img src="' . esc_url( $design_url ) . '" alt="" style="max-width:100%;max-height:100%;object-fit:contain" />'
             . '</span></div>';
    }

    // Fallbacks.
    $url = $design_url ?: $garment_url;
    if ( ! $url ) return $fallback;
    return '<img src="' . esc_url( $url ) . '" alt="" style="width:100%;height:auto;object-fit:contain" />';
}
