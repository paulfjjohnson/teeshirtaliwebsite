<?php
/**
 * Tee Shirt Ali Child Theme — functions.php
 * Requires: Flatsome parent theme + WooCommerce
 * Version: 2.0.0
 */

defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════════════════
   1. ENQUEUE PARENT + CHILD STYLES / SCRIPTS
═══════════════════════════════════════════════════════ */
add_action( 'wp_enqueue_scripts', 'tsa_enqueue_styles' );
function tsa_enqueue_styles() {
    $v = wp_get_theme()->get( 'Version' );

    // Parent Flatsome stylesheet
    wp_enqueue_style(
        'flatsome-parent',
        get_template_directory_uri() . '/style.css',
        [],
        wp_get_theme( 'flatsome' )->get( 'Version' )
    );

    // TSA child stylesheet — filemtime() so edits bust the cache (LiteSpeed + browser)
    $tsa_child_css = get_stylesheet_directory() . '/style.css';
    wp_enqueue_style( 'tsa-child', get_stylesheet_uri(), [ 'flatsome-parent' ], file_exists( $tsa_child_css ) ? filemtime( $tsa_child_css ) : $v );

    // TSA frontend JS
    $tsa_main_js = get_stylesheet_directory() . '/assets/js/tsa-main.js';
    wp_enqueue_script(
        'tsa-main',
        get_stylesheet_directory_uri() . '/assets/js/tsa-main.js',
        [ 'jquery' ], file_exists( $tsa_main_js ) ? filemtime( $tsa_main_js ) : $v, true
    );

    // Tee Party JS — only on party pages
    if ( is_page_template( 'template-tee-party.php' ) ) {
        wp_enqueue_script(
            'tsa-tee-party',
            get_stylesheet_directory_uri() . '/assets/js/tsa-tee-party.js',
            [ 'tsa-main' ], $v, true
        );
    }

    // Gang Sheet Builder — CSS + JS only on builder page, and only if the feature is active
    if ( is_page_template( 'template-gang-sheet-builder.php' )
        && ( ! function_exists( 'tsa_feature_active' ) || tsa_feature_active( 'gang_sheet' ) ) ) {
        $gsb_css = get_stylesheet_directory() . '/assets/css/tsa-gang-sheet.css';
        $gsb_js  = get_stylesheet_directory() . '/assets/js/tsa-gang-sheet.js';
        wp_enqueue_style(
            'tsa-gang-sheet',
            get_stylesheet_directory_uri() . '/assets/css/tsa-gang-sheet.css',
            [ 'tsa-child' ], file_exists( $gsb_css ) ? filemtime( $gsb_css ) : $v
        );
        wp_enqueue_script(
            'tsa-gang-sheet',
            get_stylesheet_directory_uri() . '/assets/js/tsa-gang-sheet.js',
            [], file_exists( $gsb_js ) ? filemtime( $gsb_js ) : $v, true
        );
        // tsaGSB data is localized from template-gang-sheet-builder.php after the product query
    }

    // Shop styles — WooCommerce pages
    if ( is_woocommerce() || is_cart() || is_checkout() ) {
        $tsa_shop_css = get_stylesheet_directory() . '/assets/css/tsa-shop.css';
        wp_enqueue_style(
            'tsa-shop',
            get_stylesheet_directory_uri() . '/assets/css/tsa-shop.css',
            [ 'tsa-child' ],
            file_exists( $tsa_shop_css ) ? filemtime( $tsa_shop_css ) : $v
        );
    }

    wp_localize_script( 'tsa-main', 'tsaData', [
        'ajaxUrl' => admin_url( 'admin-ajax.php' ),
        'nonce'   => wp_create_nonce( 'tsa_nonce' ),
        'siteUrl' => get_site_url(),
    ] );
}

/* ═══════════════════════════════════════════════════════
   1b. DESIGN SYSTEM — TOKENS + COMPONENT CLASSES (Layer 1)
   Loaded at priority 25 (after tsa-child @10 and tsa-templates @20)
   so the design-system definitions win over older ad-hoc .tsa-* rules.
   Tokens define all --tsa-* / --radius-* / --shadow-* / --wash-* vars;
   components define .tsa-btn/.tsa-card/.tsa-pill/.tsa-kicker/etc.
   filemtime() versioning auto-busts cache on every edit.
═══════════════════════════════════════════════════════ */
add_action( 'wp_enqueue_scripts', 'tsa_enqueue_design_system', 25 );
function tsa_enqueue_design_system() {
    $css_dir = get_stylesheet_directory() . '/assets/css/';
    $css_uri = get_stylesheet_directory_uri() . '/assets/css/';

    // 1) Tokens first — every --tsa-* custom property + base reset.
    $tokens_path = $css_dir . 'tsa-tokens.css';
    wp_enqueue_style(
        'tsa-tokens',
        $css_uri . 'tsa-tokens.css',
        [],
        file_exists( $tokens_path ) ? filemtime( $tokens_path ) : '1.0.0'
    );

    // 2) Component classes — depend on tokens.
    $components_path = $css_dir . 'tsa-components.css';
    wp_enqueue_style(
        'tsa-components',
        $css_uri . 'tsa-components.css',
        [ 'tsa-tokens' ],
        file_exists( $components_path ) ? filemtime( $components_path ) : '1.0.0'
    );

    // 2b) Info / utility / legal / form page styles (.tsa-ip-*). Loaded
    //     sitewide on purpose — the rules are .tsa-ip-* scoped so they can't
    //     bleed, and this guarantees Contact/FAQ/Fundraiser-form/etc. are
    //     always styled without per-template enqueue maintenance.
    $info_path = $css_dir . 'tsa-info-pages.css';
    if ( file_exists( $info_path ) ) {
        wp_enqueue_style(
            'tsa-info-pages',
            $css_uri . 'tsa-info-pages.css',
            [ 'tsa-tokens' ],
            filemtime( $info_path )
        );
    }

    // 2c) Auto-contrast helper — flips a design tile to a dark background when
    //     the artwork is white/very light so it stays visible. No-ops on pages
    //     without any .tsa-autocontrast elements.
    $ac_path = get_stylesheet_directory() . '/assets/js/tsa-auto-contrast.js';
    if ( file_exists( $ac_path ) ) {
        wp_enqueue_script(
            'tsa-auto-contrast',
            get_stylesheet_directory_uri() . '/assets/js/tsa-auto-contrast.js',
            [],
            filemtime( $ac_path ),
            true
        );
    }

    // 3) Configurator brand restyle — only where the configurator renders
    //    (product pages or any page containing the [apparel_configurator] shortcode).
    //    Depends on the plugin's 'ac-configurator' handle so it loads AFTER it.
    global $post;
    $has_configurator = is_singular( 'product' )
        || ( is_a( $post, 'WP_Post' ) && has_shortcode( $post->post_content, 'apparel_configurator' ) );
    if ( $has_configurator ) {
        $cfg_path = $css_dir . 'tsa-configurator.css';
        wp_enqueue_style(
            'tsa-configurator-skin',
            $css_uri . 'tsa-configurator.css',
            [ 'tsa-tokens', 'ac-configurator' ],
            file_exists( $cfg_path ) ? filemtime( $cfg_path ) : '1.0.0'
        );
    }
}

/* ═══════════════════════════════════════════════════════
   2. NAVIGATION MENUS
═══════════════════════════════════════════════════════ */
add_action( 'after_setup_theme', 'tsa_register_menus' );
function tsa_register_menus() {
    register_nav_menus( [
        'tsa-primary'    => __( 'TSA Primary Navigation', 'tsa-child' ),
        'tsa-school-nav' => __( 'TSA School/Store Nav', 'tsa-child' ),
        'tsa-footer-nav' => __( 'TSA Footer Navigation', 'tsa-child' ),
        'tsa-footer-2'   => __( 'TSA Footer Column 2', 'tsa-child' ),
    ] );
}

/* ═══════════════════════════════════════════════════════
   3. PAGE TEMPLATES REGISTRATION
═══════════════════════════════════════════════════════ */
add_filter( 'theme_page_templates', 'tsa_add_page_templates' );
function tsa_add_page_templates( $templates ) {
    $templates['template-homepage.php']      = __( 'TSA Homepage', 'tsa-child' );
    $templates['template-school-store.php']  = __( 'TSA School Store', 'tsa-child' );
    $templates['template-team-store.php']    = __( 'TSA Team Store', 'tsa-child' );
    $templates['template-service.php']       = __( 'TSA Service Page', 'tsa-child' );
    $templates['template-tee-party.php']     = __( 'TSA Tee Party', 'tsa-child' );
    $templates['template-tee-party-hub.php'] = __( 'TSA Tee Party Hub', 'tsa-child' );
    $templates['template-blank-apparel.php'] = __( 'TSA Blank Apparel Catalog', 'tsa-child' );
    $templates['template-brand-catalog.php'] = __( 'TSA Brand Catalog', 'tsa-child' );
    $templates['template-design-library.php']= __( 'TSA Design Library', 'tsa-child' );
    $templates['template-store-premium.php'] = __( 'TSA Store — Premium Home', 'tsa-child' );
    $templates['template-store-programs.php']= __( 'TSA Store — Programs Hub', 'tsa-child' );
    $templates['template-store-program.php'] = __( 'TSA Store — Program', 'tsa-child' );
    $templates['template-quote.php']         = __( 'TSA Request a Quote', 'tsa-child' );
    $templates['template-fundraiser.php']    = __( 'TSA Fundraiser', 'tsa-child' );
    $templates['template-customer-portal.php']    = __( 'TSA Customer Portal', 'tsa-child' );
    $templates['template-gang-sheet-builder.php']  = __( 'TSA Gang Sheet Builder', 'tsa-child' );
    $templates['template-school-directory.php']    = __( 'TSA School Directory', 'tsa-child' );
    $templates['template-team-directory.php']      = __( 'TSA Team Directory', 'tsa-child' );
    $templates['template-services-hub.php']        = __( 'TSA Services Hub', 'tsa-child' );
    $templates['template-configurator.php']        = __( 'TSA Exclusive Configurator', 'tsa-child' );
    $templates['template-spirit-wear.php']         = __( 'TSA Spirit Wear', 'tsa-child' );
    $templates['template-promo-products.php']      = __( 'TSA Promotional Products', 'tsa-child' );
    $templates['template-event-merch.php']         = __( 'TSA Event Merchandise', 'tsa-child' );
    $templates['template-graphic-design.php']      = __( 'TSA Graphic Design', 'tsa-child' );
    $templates['template-custom-apparel.php']      = __( 'TSA Custom Apparel', 'tsa-child' );
    $templates['template-request-store.php']       = __( 'TSA Request a Store', 'tsa-child' );
    // School subsites
    $templates['schools/dutchtown/page-school-dutchtown.php']  = __( 'School — Dutchtown Home', 'tsa-child' );
    $templates['schools/dutchtown/page-school-collection.php'] = __( 'School — Collection Store', 'tsa-child' );
    $templates['schools/dutchtown/page-school-drops.php']      = __( 'School — Drops Calendar', 'tsa-child' );
    $templates['schools/dutchtown/page-school-programs.php']   = __( 'School — Programs Hub', 'tsa-child' );
    return $templates;
}

/* ═══════════════════════════════════════════════════════
   GANG SHEET BUILDER — WOOCOMMERCE AJAX ADD TO CART
═══════════════════════════════════════════════════════ */
/** Parse a gang-sheet page's roll-width config → [ ['w'=>float,'rate'=>float,'max'=>int], ... ]. */
function tsa_gsb_widths( int $page_id ): array {
    $raw = (string) get_post_meta( $page_id, '_tsa_gsb_widths', true );
    if ( $raw === '' ) {
        $ppi = (float) ( get_post_meta( $page_id, '_tsa_gsb_price_per_inch', true ) ?: 0.50 );
        $raw = "13:{$ppi}:200\n22:0.80:200\n24:0.90:200";
    }
    $out = [];
    foreach ( preg_split( '/\r\n|\r|\n/', $raw ) as $line ) {
        $line = trim( $line ); if ( $line === '' ) continue;
        $p    = array_map( 'trim', explode( ':', $line ) );
        $w    = (float) ( $p[0] ?? 0 ); if ( $w <= 0 ) continue;
        $rate = (float) ( $p[1] ?? 0 ); if ( $rate <= 0 ) $rate = 0.50;
        $max  = ( isset( $p[2] ) && (int) $p[2] > 0 ) ? (int) $p[2] : 200;
        $out[] = [ 'w' => $w, 'rate' => $rate, 'max' => $max ];
    }
    return $out;
}

add_action( 'wp_ajax_tsa_gsb_add_to_cart',        'tsa_gsb_add_to_cart' );
add_action( 'wp_ajax_nopriv_tsa_gsb_add_to_cart', 'tsa_gsb_add_to_cart' );
function tsa_gsb_add_to_cart() {
    if ( ! check_ajax_referer( 'tsa_gsb_nonce', 'nonce', false ) ) wp_send_json_error( 'Invalid nonce.' );
    if ( function_exists( 'tsa_feature_active' ) && ! tsa_feature_active( 'gang_sheet' ) ) wp_send_json_error( 'Gang sheet builder is not available.' );

    $product_id = (int) ( $_POST['product_id'] ?? 0 );
    $page_id    = (int) ( $_POST['page_id']    ?? 0 );
    $width      = (float) ( $_POST['width']    ?? 0 );
    $length     = (int) ( $_POST['length']     ?? 0 );
    $quantity   = max( 1, (int) ( $_POST['quantity'] ?? 1 ) );

    if ( ! $product_id )               wp_send_json_error( 'Product not configured.' );
    if ( $width <= 0 || $length <= 0 ) wp_send_json_error( 'Invalid sheet size.' );

    // Price is computed SERVER-SIDE from the page's width config — never trust the client.
    $rate = 0;
    foreach ( tsa_gsb_widths( $page_id ) as $cfg ) {
        if ( abs( $cfg['w'] - $width ) < 0.01 ) { $rate = $cfg['rate']; break; }
    }
    if ( $rate <= 0 ) wp_send_json_error( 'That sheet width is not available.' );
    $price = round( $length * $rate, 2 );

    $cart_item_data = [
        'tsa_gang_sheet_width'  => $width,
        'tsa_gang_sheet_length' => $length,
        'tsa_gsb_price'         => $price,
        'tsa_gsb_unique'        => md5( $width . '|' . $length . '|' . microtime( true ) ), // keep each build a distinct line
    ];

    $added = WC()->cart->add_to_cart( $product_id, $quantity, 0, [], $cart_item_data );
    if ( $added ) {
        wp_send_json_success( [
            'message'   => 'Gang sheet added to cart.',
            'cart_link' => '<a href="' . esc_url( wc_get_cart_url() ) . '">View cart →</a>',
        ] );
    }
    wp_send_json_error( 'Could not add to cart. Please try again.' );
}

/* Apply the calculated gang-sheet price to its cart line. */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
    if ( is_admin() && ! wp_doing_ajax() ) return;
    if ( ! ( $cart instanceof WC_Cart ) ) return;
    foreach ( $cart->get_cart() as $item ) {
        if ( ! empty( $item['tsa_gsb_price'] ) && ! empty( $item['data'] ) ) {
            $item['data']->set_price( (float) $item['tsa_gsb_price'] );
        }
    }
}, 20 );

/* Show the sheet size in cart + checkout. */
add_filter( 'woocommerce_get_item_data', function ( $data, $item ) {
    if ( ! empty( $item['tsa_gang_sheet_width'] ) && ! empty( $item['tsa_gang_sheet_length'] ) ) {
        $data[] = [ 'name' => 'Gang sheet', 'value' => $item['tsa_gang_sheet_width'] . '″ × ' . $item['tsa_gang_sheet_length'] . '″' ];
    }
    return $data;
}, 10, 2 );

/* Persist the sheet size onto the order line for production. */
add_action( 'woocommerce_checkout_create_order_line_item', function ( $line, $key, $values ) {
    if ( ! empty( $values['tsa_gang_sheet_width'] ) ) {
        $line->add_meta_data( 'Gang Sheet Size', $values['tsa_gang_sheet_width'] . '″ × ' . ( $values['tsa_gang_sheet_length'] ?? '' ) . '″', true );
    }
}, 10, 3 );

/* ═══════════════════════════════════════════════════════
   4. BODY CLASSES
═══════════════════════════════════════════════════════ */
add_filter( 'body_class', 'tsa_body_classes' );
function tsa_body_classes( $classes ) {
    $template_map = [
        'template-homepage.php'       => 'tsa-homepage',
        'template-school-store.php'   => 'tsa-school-store',
        'template-team-store.php'     => 'tsa-team-store',
        'template-service.php'        => 'tsa-service-page',
        'template-tee-party.php'      => 'tsa-party-page',
        'template-tee-party-hub.php'  => 'tsa-party-hub-page',
        'template-blank-apparel.php'  => 'tsa-catalog-page',
        'template-brand-catalog.php'  => 'tsa-brand-page',
        'template-design-library.php' => 'tsa-library-page',
        'template-quote.php'          => 'tsa-quote-page',
        'template-fundraiser.php'     => 'tsa-fundraiser-page',
        'template-customer-portal.php'=> 'tsa-portal-page',
    ];
    foreach ( $template_map as $template => $class ) {
        if ( is_page_template( $template ) ) {
            $classes[] = $class;
        }
    }
    // Premium store templates: add dths-active server-side so the brandable dths
    // CSS takes over (and Flatsome chrome is suppressed) before JS runs — no flash.
    foreach ( [ 'template-store-premium.php', 'template-store-programs.php', 'template-store-program.php' ] as $store_tpl ) {
        if ( is_page_template( $store_tpl ) ) { $classes[] = 'dths-active'; break; }
    }
    if ( is_woocommerce() || is_shop() ) {
        $classes[] = 'tsa-shop-page';
    }
    return $classes;
}

/* ═══════════════════════════════════════════════════════
   5. CUSTOM IMAGE SIZES
═══════════════════════════════════════════════════════ */
add_action( 'after_setup_theme', 'tsa_image_sizes' );
function tsa_image_sizes() {
    add_image_size( 'tsa-hero',          1400, 720,  true );
    add_image_size( 'tsa-store-banner',  1200, 500,  true );
    add_image_size( 'tsa-school-card',   600,  400,  true );
    add_image_size( 'tsa-garment',       800,  800,  true );
    add_image_size( 'tsa-design-thumb',  400,  400,  true );
    add_image_size( 'tsa-service-hero',  1400, 500,  true );
    add_image_size( 'tsa-team-banner',   1400, 600,  true );
}

/* ═══════════════════════════════════════════════════════
   6. SCHOOL STORE META — configurator_store CPT
═══════════════════════════════════════════════════════ */
add_action( 'add_meta_boxes', 'tsa_school_meta_boxes' );
function tsa_school_meta_boxes() {
    add_meta_box(
        'tsa_school_display',
        __( 'TSA Homepage Display Settings', 'tsa-child' ),
        'tsa_school_display_meta_box',
        'configurator_store',
        'normal',
        'default'
    );
}

function tsa_school_display_meta_box( WP_Post $post ) {
    wp_nonce_field( 'tsa_school_display', 'tsa_school_display_nonce' );
    $status     = get_post_meta( $post->ID, '_tsa_homepage_status', true ) ?: 'coming-soon';
    $tagline    = get_post_meta( $post->ID, '_tsa_store_tagline', true );
    $cta_text   = get_post_meta( $post->ID, '_tsa_store_cta_text', true ) ?: 'Enter Store';
    $cta_url    = get_post_meta( $post->ID, '_tsa_store_cta_url', true );
    $sort_order = get_post_meta( $post->ID, '_tsa_homepage_order', true ) ?: '0';
    $spotlight  = get_post_meta( $post->ID, '_tsa_is_spotlight', true );
    $store_type = get_post_meta( $post->ID, '_tsa_store_type', true ) ?: 'school';
    ?>
    <table class="form-table">
        <tr>
            <th><label><?php esc_html_e( 'Status', 'tsa-child' ); ?></label></th>
            <td>
                <select name="tsa_homepage_status">
                    <option value="live"        <?php selected( $status, 'live' ); ?>>Live</option>
                    <option value="coming-soon" <?php selected( $status, 'coming-soon' ); ?>>Coming Soon</option>
                    <option value="hidden"      <?php selected( $status, 'hidden' ); ?>>Hidden</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label><?php esc_html_e( 'Store Type', 'tsa-child' ); ?></label></th>
            <td>
                <select name="tsa_store_type">
                    <option value="main"     <?php selected( $store_type, 'main' ); ?>>Main Store</option>
                    <option value="school"   <?php selected( $store_type, 'school' ); ?>>School</option>
                    <option value="team"     <?php selected( $store_type, 'team' ); ?>>Sports Team</option>
                    <option value="business" <?php selected( $store_type, 'business' ); ?>>Business</option>
                    <option value="event"    <?php selected( $store_type, 'event' ); ?>>Event</option>
                </select>
            </td>
        </tr>
        <tr>
            <th><label><?php esc_html_e( 'Spotlight Store', 'tsa-child' ); ?></label></th>
            <td>
                <input type="checkbox" name="tsa_is_spotlight" value="1" <?php checked( $spotlight, '1' ); ?> />
                <p class="description">Show in the hero spotlight card on the homepage.</p>
            </td>
        </tr>
        <tr>
            <th><label><?php esc_html_e( 'Tagline', 'tsa-child' ); ?></label></th>
            <td><input type="text" name="tsa_store_tagline" value="<?php echo esc_attr( $tagline ); ?>" class="large-text" /></td>
        </tr>
        <tr>
            <th><label><?php esc_html_e( 'CTA Button Text', 'tsa-child' ); ?></label></th>
            <td><input type="text" name="tsa_store_cta_text" value="<?php echo esc_attr( $cta_text ); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label><?php esc_html_e( 'CTA Button URL', 'tsa-child' ); ?></label></th>
            <td><input type="text" name="tsa_store_cta_url" value="<?php echo esc_attr( $cta_url ); ?>" class="large-text" placeholder="/schools/dutchtown/" /></td>
        </tr>
        <tr>
            <th><label><?php esc_html_e( 'Sort Order', 'tsa-child' ); ?></label></th>
            <td><input type="number" name="tsa_homepage_order" value="<?php echo esc_attr( $sort_order ); ?>" class="small-text" /></td>
        </tr>
    </table>
    <?php
}

add_action( 'save_post_configurator_store', 'tsa_save_school_display_meta' );
function tsa_save_school_display_meta( $post_id ) {
    if ( ! isset( $_POST['tsa_school_display_nonce'] ) ||
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_school_display_nonce'] ) ), 'tsa_school_display' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $allowed_statuses = [ 'live', 'coming-soon', 'hidden' ];
    $allowed_types    = [ 'main', 'school', 'team', 'business', 'event' ];

    $status = sanitize_key( $_POST['tsa_homepage_status'] ?? 'coming-soon' );
    update_post_meta( $post_id, '_tsa_homepage_status', in_array( $status, $allowed_statuses, true ) ? $status : 'coming-soon' );

    $type = sanitize_key( $_POST['tsa_store_type'] ?? 'school' );
    update_post_meta( $post_id, '_tsa_store_type', in_array( $type, $allowed_types, true ) ? $type : 'school' );

    update_post_meta( $post_id, '_tsa_is_spotlight', isset( $_POST['tsa_is_spotlight'] ) ? '1' : '0' );
    update_post_meta( $post_id, '_tsa_store_tagline', sanitize_text_field( wp_unslash( $_POST['tsa_store_tagline'] ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_store_cta_text', sanitize_text_field( wp_unslash( $_POST['tsa_store_cta_text'] ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_store_cta_url', esc_url_raw( wp_unslash( $_POST['tsa_store_cta_url'] ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_homepage_order', absint( $_POST['tsa_homepage_order'] ?? 0 ) );
}

/* Admin menu for the configurator_store CPT (the plugin registers it with
   show_in_menu = false, so school/team/business stores are otherwise hard to
   find). Adds a top-level "Stores" item linking to the store list. */
add_action( 'admin_menu', function () {
    if ( ! post_type_exists( 'configurator_store' ) ) return;
    if ( ! current_user_can( 'manage_woocommerce' ) ) return;
    add_menu_page(
        __( 'Stores', 'tsa-child' ),
        __( 'Stores', 'tsa-child' ),
        'manage_woocommerce',
        'edit.php?post_type=configurator_store',
        '',
        'dashicons-store',
        27
    );
}, 11 );

/* Alphabetize the wp-admin sidebar (Dashboard pinned at top), submenu items
   included. Runs late so every plugin/theme menu is already registered; the
   core post-processing in includes/menu.php ksorts by our new keys afterward.
   Default separators are dropped for a clean A–Z list. */
add_action( 'admin_menu', 'tsa_alphabetize_admin_menu', 999 );
function tsa_alphabetize_admin_menu() {
    global $menu, $submenu;
    if ( ! is_array( $menu ) ) return;

    $clean = function ( $title ) {
        $title = preg_replace( '/<span[^>]*>.*?<\/span>/is', '', (string) $title ); // drop count bubbles
        return trim( wp_strip_all_tags( $title ) );
    };
    $cmp = function ( $a, $b ) use ( $clean ) {
        return strcasecmp( $clean( $a[0] ?? '' ), $clean( $b[0] ?? '' ) );
    };

    // Top level — pin Dashboard, drop separators, sort the rest A–Z.
    $dashboard = null; $items = [];
    foreach ( $menu as $item ) {
        if ( empty( $item[2] ) ) continue;
        if ( ! empty( $item[4] ) && strpos( $item[4], 'wp-menu-separator' ) !== false ) continue;
        if ( $item[2] === 'index.php' ) { $dashboard = $item; continue; }
        $items[] = $item;
    }
    usort( $items, $cmp );
    $new = []; $pos = 3;
    if ( $dashboard ) $new[2] = $dashboard;
    foreach ( $items as $it ) { $new[ $pos++ ] = $it; }
    $menu = $new;

    // Submenu items, per parent.
    if ( is_array( $submenu ) ) {
        foreach ( $submenu as $parent => $subitems ) {
            usort( $subitems, $cmp );
            $submenu[ $parent ] = $subitems;
        }
    }
}

/* ═══════════════════════════════════════════════════════
   6b. STORE CO-BRAND BAR
   A slim "← Tee Shirt Ali" bar above the header on every store/template
   page (school stores, team stores, the exclusive configurator, and the
   school subsites), so a store never feels like a dead end. Inline-styled
   so it renders correctly regardless of CSS caching.
═══════════════════════════════════════════════════════ */
/** Store/template display name if the current page is a store page, else ''. */
function tsa_store_cobrand_name() {
    if ( is_admin() || ! is_page() ) return '';
    $tpl = (string) get_page_template_slug();
    if ( $tpl === '' ) return '';
    $store_tpls = [ 'template-school-store.php', 'template-team-store.php', 'template-configurator.php' ];
    $is_store   = in_array( $tpl, $store_tpls, true ) || strpos( $tpl, 'schools/' ) === 0;
    if ( ! $is_store ) return '';
    return get_the_title() ?: get_bloginfo( 'name' );
}

add_action( 'wp_body_open', function () {
    $name = tsa_store_cobrand_name();
    if ( $name === '' ) return;
    ?>
    <div id="tsa-cobrand" class="tsa-cobrand-bar" style="display:flex;align-items:center;justify-content:space-between;gap:10px 16px;flex-wrap:wrap;background:#252124;color:#fff;font-size:13px;line-height:1.35;padding:8px 6%;position:relative;top:0;z-index:1001;">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="display:inline-flex;align-items:center;gap:6px;color:#fec2c0;font-weight:700;text-decoration:none;white-space:nowrap;">
            <span aria-hidden="true">&larr;</span> Tee Shirt Ali
        </a>
        <span style="display:inline-flex;align-items:center;gap:10px;color:#cfcac9;">
            You&rsquo;re shopping the <strong style="color:#fff;font-weight:700;"><?php echo esc_html( $name ); ?></strong> store
            <button type="button" class="tsa-cobrand-pin" aria-pressed="false" aria-label="Pin this bar to the top" title="Pin to top" style="background:none;border:0;padding:2px;margin:0;cursor:pointer;color:#9a9694;display:inline-flex;align-items:center;">
                <svg width="15" height="15" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true"><path d="M9 4h6"/><path d="M10 4l-1 7-3 2v2h12v-2l-3-2-1-7"/><path d="M12 17v4"/></svg>
            </button>
        </span>
    </div>
    <script>
    (function(){
        var bar=document.getElementById('tsa-cobrand'); if(!bar) return;
        var pin=bar.querySelector('.tsa-cobrand-pin'), KEY='tsa_cobrand_pinned';
        function topOff(){ return document.getElementById('wpadminbar') ? 32 : 0; }
        function apply(on){
            bar.style.position = on ? 'sticky' : 'relative';
            bar.style.top = on ? topOff()+'px' : '0';
            if(pin){ pin.setAttribute('aria-pressed', on?'true':'false'); pin.style.color = on ? '#fec2c0' : '#9a9694'; }
        }
        var saved=false; try{ saved=localStorage.getItem(KEY)==='1'; }catch(e){}
        apply(saved);
        if(pin){ pin.addEventListener('click',function(){
            var on = bar.style.position!=='sticky'; apply(on);
            try{ localStorage.setItem(KEY, on?'1':'0'); }catch(e){}
        }); }
    }());
    </script>
    <?php
}, 5 );

/* ═══════════════════════════════════════════════════════
   7. TEE PARTY META BOXES
   ─ Moved to inc/tee-party.php (full design-drop schedule system).
     Old meta box removed here to prevent a duplicate "Tee Party
     Settings" box appearing on the page editor.
═══════════════════════════════════════════════════════ */

/* ═══════════════════════════════════════════════════════
   8. SERVICE PAGE META BOXES
═══════════════════════════════════════════════════════ */
add_action( 'add_meta_boxes', 'tsa_service_meta_box' );
function tsa_service_meta_box() {
    add_meta_box(
        'tsa_service',
        __( 'TSA Service Page Settings', 'tsa-child' ),
        'tsa_render_service_meta_box',
        'page',
        'normal',
        'high'
    );
}

function tsa_render_service_meta_box( WP_Post $post ) {
    if ( get_post_meta( $post->ID, '_wp_page_template', true ) !== 'template-service.php' ) {
        echo '<p style="color:#888;font-size:13px">These settings apply only to pages using the <strong>TSA Service Page</strong> template.</p>';
        return;
    }
    wp_nonce_field( 'tsa_service', 'tsa_service_nonce' );
    $tagline  = get_post_meta( $post->ID, '_tsa_service_tagline', true );
    $gradient = get_post_meta( $post->ID, '_tsa_service_gradient', true );
    $cta_url  = get_post_meta( $post->ID, '_tsa_service_cta_url', true ) ?: '/request-a-quote/';
    $cta_text = get_post_meta( $post->ID, '_tsa_service_cta_text', true ) ?: 'Get a Quote';
    ?>
    <table class="form-table">
        <tr>
            <th><label>Hero Tagline</label></th>
            <td><input type="text" name="tsa_service_tagline" value="<?php echo esc_attr( $tagline ); ?>" class="large-text" /></td>
        </tr>
        <tr>
            <th><label>Hero Gradient CSS</label></th>
            <td>
                <input type="text" name="tsa_service_gradient" value="<?php echo esc_attr( $gradient ); ?>" class="large-text" placeholder="linear-gradient(135deg, #0d1b2a, #1e3a5f)" />
                <p class="description">CSS gradient for the service hero background. Leave blank for default.</p>
            </td>
        </tr>
        <tr>
            <th><label>CTA Button URL</label></th>
            <td><input type="text" name="tsa_service_cta_url" value="<?php echo esc_attr( $cta_url ); ?>" class="regular-text" /></td>
        </tr>
        <tr>
            <th><label>CTA Button Text</label></th>
            <td><input type="text" name="tsa_service_cta_text" value="<?php echo esc_attr( $cta_text ); ?>" class="regular-text" /></td>
        </tr>
    </table>
    <?php
}

add_action( 'save_post_page', 'tsa_save_service_meta' );
function tsa_save_service_meta( $post_id ) {
    if ( ! isset( $_POST['tsa_service_nonce'] ) ||
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_service_nonce'] ) ), 'tsa_service' ) ) {
        return;
    }
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    update_post_meta( $post_id, '_tsa_service_tagline', sanitize_text_field( wp_unslash( $_POST['tsa_service_tagline'] ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_service_gradient', sanitize_text_field( wp_unslash( $_POST['tsa_service_gradient'] ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_service_cta_url', esc_url_raw( wp_unslash( $_POST['tsa_service_cta_url'] ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_service_cta_text', sanitize_text_field( wp_unslash( $_POST['tsa_service_cta_text'] ?? '' ) ) );
}

/* ═══════════════════════════════════════════════════════
   9. TEMPLATE HELPER FUNCTIONS
═══════════════════════════════════════════════════════ */

/** Get spotlight store (hero section homepage). */
function tsa_get_spotlight_store() {
    $posts = get_posts( [
        'post_type'      => 'configurator_store',
        'post_status'    => 'publish',
        'posts_per_page' => 1,
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => '_tsa_is_spotlight', 'value' => '1' ],
            [ 'key' => '_tsa_homepage_status', 'value' => 'live' ],
        ],
        'meta_key'  => '_tsa_homepage_order',
        'orderby'   => 'meta_value_num',
        'order'     => 'ASC',
    ] );
    return $posts[0] ?? null;
}

/** Get stores for the homepage School Stores grid.
 *  Only school/team type stores belong here — excludes the main configurator
 *  store, business, and event stores, plus anything explicitly hidden.
 */
function tsa_get_homepage_stores( $limit = 8 ) {
    // Hard lock-out: the main store must NEVER appear in the School Stores grid,
    // even if its _tsa_store_type meta is mis-set to school/team (the meta box
    // defaults to "school"). Exclude it by the main store slug, by post id.
    $main_slug = apply_filters( 'tsa_design_main_store_slug', 'tsa' );
    $exclude   = get_posts( [
        'post_type'   => 'configurator_store',
        'post_status' => 'any',
        'numberposts' => -1,
        'fields'      => 'ids',
        'meta_query'  => [
            'relation' => 'OR',
            [ 'key' => '_ac_store_slug',  'value' => $main_slug ],
            [ 'key' => '_tsa_store_type', 'value' => [ 'main', 'business', 'event' ], 'compare' => 'IN' ],
        ],
    ] );

    return get_posts( [
        'post_type'      => 'configurator_store',
        'post_status'    => 'publish',
        'posts_per_page' => $limit,
        'orderby'        => 'title',
        'order'          => 'ASC',
        'post__not_in'   => $exclude ?: [ 0 ],
        'meta_query'     => [
            'relation' => 'AND',
            // Only school/team stores belong in this section (excludes main/business/event).
            // Use _tsa_store_type — the key the store meta box + builder actually save.
            [ 'key' => '_tsa_store_type', 'value' => [ 'school', 'team' ], 'compare' => 'IN' ],
            // Hidden only if explicitly set to hidden (stores without the key still show).
            [
                'relation' => 'OR',
                [ 'key' => '_tsa_homepage_status', 'compare' => 'NOT EXISTS' ],
                [ 'key' => '_tsa_homepage_status', 'value' => 'hidden', 'compare' => '!=' ],
            ],
        ],
    ] );
}

/** Render a kicker pill. */
function tsa_kicker( $text, $modifier = '' ) {
    $class = 'tsa-kicker' . ( $modifier ? ' tsa-kicker--' . esc_attr( $modifier ) : '' );
    echo '<div class="' . esc_attr( $class ) . '">' . esc_html( $text ) . '</div>';
}

/** Render a TSA button link. */
function tsa_btn( $url, $label, $variant = 'primary', $extra_class = '' ) {
    printf(
        '<a href="%s" class="tsa-btn tsa-btn-%s%s">%s</a>',
        esc_url( $url ),
        esc_attr( $variant ),
        $extra_class ? ' ' . esc_attr( $extra_class ) : '',
        esc_html( $label )
    );
}

/** Get service-specific config by page slug. */
function tsa_get_service_config( $slug ) {
    $defaults = [
        'kicker'   => 'Our Services',
        'gradient' => 'linear-gradient(135deg, #252124, #3a3037)',
        'tagline'  => '',
        'features' => [],
        'steps'    => [],
    ];

    $configs = [
        'dtf-printing-services' => [
            'kicker'   => 'Industry-Grade Printing',
            'gradient' => 'linear-gradient(135deg, #0d1b2a, #1e3a5f)',
            'tagline'  => 'Vibrant, full-color DTF transfers that bond permanently to virtually any fabric.',
            'features' => [
                [ 'icon' => '🎨', 'title' => 'Full-Color Transfers', 'desc' => 'No color limits — photographic detail with zero screens or separations.' ],
                [ 'icon' => '⚡', 'title' => 'Fast Turnaround', 'desc' => 'Most orders ship within 3-5 business days. Rush options available.' ],
                [ 'icon' => '👕', 'title' => 'Any Fabric', 'desc' => 'Cotton, polyester, nylon, blends — DTF works on virtually all garment types.' ],
                [ 'icon' => '📏', 'title' => 'Small to Large Run', 'desc' => 'No minimum order quantity. Perfect from single items to bulk production.' ],
                [ 'icon' => '💎', 'title' => 'Wash-Fast', 'desc' => 'Heat-bonded transfers that resist cracking, peeling, and fading.' ],
                [ 'icon' => '📐', 'title' => 'Any Size Print', 'desc' => 'From small chest logos to oversized full-back graphics.' ],
            ],
            'steps'    => [
                [ 'title' => 'Submit Artwork', 'desc' => 'Send us your design file or let our team create one for you.' ],
                [ 'title' => 'We Print', 'desc' => 'Your artwork is printed on transfer film with precision equipment.' ],
                [ 'title' => 'Press & Bond', 'desc' => 'Transfers are heat-pressed onto your chosen garments.' ],
                [ 'title' => 'Ship to You', 'desc' => 'Finished apparel is quality-checked and shipped to your door.' ],
            ],
        ],
        'graphic-design-services' => [
            'kicker'   => 'Creative Design',
            'gradient' => 'linear-gradient(135deg, #2d1b4e, #7c3aed)',
            'tagline'  => 'Professional artwork from concept to print-ready file — built for apparel.',
            'features' => [
                [ 'icon' => '✏️', 'title' => 'Custom Logos', 'desc' => 'Brand-new logo creation for schools, teams, businesses, and events.' ],
                [ 'icon' => '🖼️', 'title' => 'Apparel Graphics', 'desc' => 'T-shirt artwork, mascots, spirit wear designs, and promotional graphics.' ],
                [ 'icon' => '🎯', 'title' => 'Print-Ready Files', 'desc' => 'All deliverables are export-ready for screen print, DTF, embroidery, and more.' ],
                [ 'icon' => '🔄', 'title' => 'Unlimited Revisions', 'desc' => 'We refine until you love it. Your satisfaction is the goal.' ],
                [ 'icon' => '⚡', 'title' => 'Fast Delivery', 'desc' => 'Initial concepts delivered within 48-72 hours.' ],
                [ 'icon' => '📁', 'title' => 'Full File Package', 'desc' => 'Receive AI, PNG, SVG, and PDF versions of every design.' ],
            ],
            'steps'    => [
                [ 'title' => 'Share Your Vision', 'desc' => 'Tell us about your brand, colors, style, and what you need.' ],
                [ 'title' => 'Concept Delivery', 'desc' => 'We deliver initial concepts within 48-72 hours.' ],
                [ 'title' => 'Refine Together', 'desc' => 'We revise based on your feedback until it is perfect.' ],
                [ 'title' => 'Receive Files', 'desc' => 'Get your final print-ready files in all formats.' ],
            ],
        ],
        'promotional-products' => [
            'kicker'   => 'Branded Merchandise',
            'gradient' => 'linear-gradient(135deg, #1a2d3a, #2d6b8c)',
            'tagline'  => 'Drinkware, bags, accessories, and branded items that keep your name in front of customers.',
        ],
        'business-branding-services' => [
            'kicker'   => 'Business Branding',
            'gradient' => 'linear-gradient(135deg, #1a1a2e, #3d2e7c)',
            'tagline'  => 'Consistent branded apparel and merchandise systems for local and regional businesses.',
        ],
        'business-merch-solutions' => [
            'kicker'   => 'Merch Solutions',
            'gradient' => 'linear-gradient(135deg, #252124, #4a3a46)',
            'tagline'  => 'Online merch stores, employee uniforms, branded gear programs, and fulfillment solutions.',
        ],
        'printing-capabilities' => [
            'kicker'   => 'What We Can Print',
            'gradient' => 'linear-gradient(135deg, #0a1628, #1e3d5a)',
            'tagline'  => 'A full breakdown of our decoration methods, substrates, and production capabilities.',
        ],
        'turnaround-times' => [
            'kicker'   => 'Production Timeline',
            'gradient' => 'linear-gradient(135deg, #1a2d1a, #2e5a2e)',
            'tagline'  => 'Realistic production and delivery timelines for every order type and quantity.',
        ],
        'services-overview' => [
            'kicker'   => 'Everything We Offer',
            'gradient' => 'linear-gradient(135deg, var(--tsa-purple), var(--tsa-purple-mid))',
            'tagline'  => 'From DTF transfers to school stores — one platform for all your apparel and merch needs.',
        ],
        'fundraiser-solutions' => [
            'kicker'   => 'Fundraiser Programs',
            'gradient' => 'linear-gradient(135deg, #1a3a1a, #2e6b2e)',
            'tagline'  => 'Zero-risk fundraiser programs with online ordering, no minimums, and automatic payout.',
        ],
    ];

    // Map standardized (short) page slugs to the original config keys so the
    // service template works after the footer slug standardization. Old slugs
    // still resolve directly via $configs, so this is backward compatible.
    $aliases = [
        'dtf-printing'   => 'dtf-printing-services',
        'graphic-design' => 'graphic-design-services',
        'fundraisers'    => 'fundraiser-solutions',
        'businesses'     => 'business-merch-solutions',
        'services'       => 'services-overview',
    ];
    if ( ! isset( $configs[ $slug ] ) && isset( $aliases[ $slug ] ) ) {
        $slug = $aliases[ $slug ];
    }

    return array_merge( $defaults, $configs[ $slug ] ?? [] );
}

/** Get brand catalog config by slug. */
function tsa_get_brand_config( $slug ) {
    // Prefer an admin-managed brand (Blank Apparel CPT) when one matches the slug.
    if ( function_exists( 'tsa_get_catalog_brand_by_slug' ) ) {
        $cpt = tsa_get_catalog_brand_by_slug( $slug );
        if ( $cpt ) return $cpt;
    }
    $brands = [
        'bella-canvas'     => [ 'name' => 'Bella + Canvas', 'type' => 'Premium Apparel', 'desc' => 'The go-to for soft, retail-quality fashion blanks. Bella + Canvas offers the widest color range in the industry with unisex, women\'s, and youth cuts.', 'colors' => 100, 'styles' => 18 ],
        'gildan'           => [ 'name' => 'Gildan', 'type' => 'Value Apparel', 'desc' => 'The most versatile value brand in the business. Gildan Heavy Cotton and SoftStyle are staples for school spirit wear and budget-friendly bulk programs.', 'colors' => 64, 'styles' => 12 ],
        'next-level'       => [ 'name' => 'Next Level', 'type' => 'Tri-blend & Comfort', 'desc' => 'Known for their buttery-soft tri-blends and street-ready fit. Perfect for fashion-forward apparel with a premium feel at a mid-range price.', 'colors' => 48, 'styles' => 10 ],
        'comfort-colors'   => [ 'name' => 'Comfort Colors', 'type' => 'Garment-Dyed', 'desc' => 'Pigment-dyed for a lived-in vintage look. Comfort Colors are the favorite for school spirit wear, church groups, and lifestyle brands.', 'colors' => 60, 'styles' => 8 ],
        'tultex'           => [ 'name' => 'Tultex', 'type' => 'Value Fleece', 'desc' => 'Affordable fleece options for hoodies, crewnecks, and joggers. Tultex delivers consistent quality at a price point perfect for bulk orders.', 'colors' => 30, 'styles' => 6 ],
        'jerzees'          => [ 'name' => 'Jerzees', 'type' => 'Fleece & Cotton', 'desc' => 'A trusted American brand for everyday hoodies, sweatshirts, and tees. Consistent sizing and durability make Jerzees a reliable choice for school programs.', 'colors' => 36, 'styles' => 8 ],
        'badger'           => [ 'name' => 'Badger Sport', 'type' => 'Performance Wear', 'desc' => 'High-performance athletic wear with moisture-wicking technology. Badger is the go-to for sports teams, PE programs, and athletic spirit wear.', 'colors' => 25, 'styles' => 14 ],
        'c2-sport'         => [ 'name' => 'C2 Sport', 'type' => 'Performance', 'desc' => 'Value-priced performance apparel for athletic programs. C2 Sport delivers moisture-management technology at a budget-friendly price point.', 'colors' => 20, 'styles' => 6 ],
        'independent-trading-co' => [ 'name' => 'Independent Trading Co.', 'type' => 'Premium Fleece', 'desc' => 'The premium fleece brand of choice for streetwear and lifestyle brands. ITC\'s hoodies and crewnecks feature heavyweight construction with a fashion-forward fit.', 'colors' => 40, 'styles' => 10 ],
        'richardson'       => [ 'name' => 'Richardson', 'type' => 'Headwear', 'desc' => 'The headwear standard for custom hats. Richardson\'s 112 trucker cap is the most embroidered and DTF-printed hat in the country.', 'colors' => 80, 'styles' => 12 ],
        'yp-classics'      => [ 'name' => 'YP Classics', 'type' => 'Value Headwear', 'desc' => 'Budget-friendly caps and hats for high-quantity orders. Great for spirit wear giveaways, event hats, and affordable team headwear.', 'colors' => 30, 'styles' => 6 ],
    ];
    return $brands[ $slug ] ?? [ 'name' => get_the_title(), 'type' => 'Apparel', 'desc' => '', 'colors' => 0, 'styles' => 0 ];
}

/* ═══════════════════════════════════════════════════════
   9b. SCHOOL BRAND COLORS (Ascension Public Schools Brand Guide 2025)
   Canonical source for per-school colors used by school cards sitewide.
   Match is tolerant — works for "Dutchtown", "Dutchtown High School", etc.
   [ primary, secondary ] = HEX/HTML from the official guide (pp.12–17).
═══════════════════════════════════════════════════════ */
function tsa_get_school_colors( $name ) {
    // Generated school store records win (Store Builder) — tolerant name match.
    if ( function_exists( 'tsa_school_store_records' ) ) {
        $key = strtolower( trim( (string) $name ) );
        if ( $key !== '' ) {
            foreach ( tsa_school_store_records() as $r ) {
                if ( empty( $r['color'] ) ) continue;
                $rn = strtolower( $r['name'] );
                if ( $rn === $key || strpos( $rn, $key ) !== false || strpos( $key, $rn ) !== false ) {
                    return [ 'primary' => $r['color'], 'secondary' => $r['color2'] ?: $r['color'] ];
                }
            }
        }
    }
    $map = [
        'donaldsonville' => [ '#E1251B', '#020000' ], // Tigers — red / rich black
        'dutchtown'      => [ '#592C82', '#C7C9C8' ], // Griffins — purple / cool gray
        'east ascension' => [ '#263A80', '#FFCE06' ], // Spartans — navy / gold
        'prairieville'   => [ '#0F2D52', '#61A744' ], // Hurricanes — navy / green
        'amant'          => [ '#FFCE06', '#020000' ], // St. Amant Gators — gold / rich black
    ];
    $key = strtolower( trim( (string) $name ) );
    foreach ( $map as $needle => $colors ) {
        if ( strpos( $key, $needle ) !== false ) {
            return [ 'primary' => $colors[0], 'secondary' => $colors[1] ];
        }
    }
    // Fallback = Ascension Public Schools DISTRICT colors (Brand Guide p.7),
    // used for schools the guide doesn't brand individually (middle/primary).
    // District: purple #5D4777 + gray #9991A4 (main red #BA0C2F also available).
    return [ 'primary' => '#5D4777', 'secondary' => '#9991A4' ];
}

/** Pick a readable text color (#fff or near-black) for a given background hex. */
function tsa_readable_text( $hex ) {
    $hex = ltrim( (string) $hex, '#' );
    if ( strlen( $hex ) !== 6 ) return '#ffffff';
    $r = hexdec( substr( $hex, 0, 2 ) );
    $g = hexdec( substr( $hex, 2, 2 ) );
    $b = hexdec( substr( $hex, 4, 2 ) );
    $lum = ( 0.299 * $r + 0.587 * $g + 0.114 * $b ) / 255;
    return $lum > 0.6 ? '#1a1018' : '#ffffff';
}

/**
 * Canonical Ascension school directory (single source for the School Directory
 * page + the Design Library "Schools" collection). Grouped; each school has a
 * name + status ('live'|'coming-soon'); live schools add a 'url' (and may add
 * mascot/colors). Filterable via 'tsa_school_directory'.
 */
function tsa_school_directory() {
    $groups = [
        'High Schools' => [
            [ 'name' => 'Donaldsonville High School', 'status' => 'coming-soon', 'mascot' => 'Tigers',     'color' => '#E1251B', 'color2' => '#020000' ],
            [ 'name' => 'Dutchtown High School',       'status' => 'live',       'mascot' => 'Griffins',   'color' => '#592C82', 'color2' => '#C7C9C8', 'url' => '/schools/dutchtown/' ],
            [ 'name' => 'East Ascension High School',  'status' => 'coming-soon','mascot' => 'Spartans',   'color' => '#263A80', 'color2' => '#FFCE06' ],
            [ 'name' => 'Prairieville High School',    'status' => 'coming-soon','mascot' => 'Hurricanes', 'color' => '#0F2D52', 'color2' => '#61A744' ],
            [ 'name' => 'St. Amant High School',       'status' => 'coming-soon','mascot' => 'Gators',     'color' => '#FFCE06', 'color2' => '#020000' ],
        ],
        'Middle & Elementary Schools' => [
            [ 'name' => 'Bluff Middle School',         'status' => 'coming-soon' ],
            [ 'name' => 'Central Middle School',       'status' => 'coming-soon' ],
            [ 'name' => 'Dutchtown Middle School',     'status' => 'coming-soon' ],
            [ 'name' => 'Galvez Middle School',        'status' => 'coming-soon' ],
            [ 'name' => 'Gonzales Middle School',      'status' => 'coming-soon' ],
            [ 'name' => 'Lake Elementary School',      'status' => 'coming-soon' ],
            [ 'name' => 'Lowery Middle School',        'status' => 'coming-soon' ],
            [ 'name' => 'Prairieville Middle School',  'status' => 'coming-soon' ],
            [ 'name' => 'St. Amant Middle School',     'status' => 'coming-soon' ],
        ],
        'Primary Schools' => [
            [ 'name' => 'Bluff Ridge Primary School',  'status' => 'coming-soon' ],
            [ 'name' => 'Bullion Primary School',       'status' => 'coming-soon' ],
            [ 'name' => 'Central Primary School',       'status' => 'coming-soon' ],
            [ 'name' => 'Donaldsonville Primary School','status' => 'coming-soon' ],
            [ 'name' => 'Duplessis Primary School',     'status' => 'coming-soon' ],
            [ 'name' => 'Dutchtown Primary School',     'status' => 'coming-soon' ],
            [ 'name' => 'G.W. Carver Primary School',   'status' => 'coming-soon' ],
            [ 'name' => 'Galvez Primary School',        'status' => 'coming-soon' ],
            [ 'name' => 'Gonzales Primary School',      'status' => 'coming-soon' ],
            [ 'name' => 'Lakeside Primary School',      'status' => 'coming-soon' ],
            [ 'name' => 'Lowery Elementary School',     'status' => 'coming-soon' ],
            [ 'name' => 'Oak Grove Primary School',     'status' => 'coming-soon' ],
            [ 'name' => 'Pecan Grove Primary School',   'status' => 'coming-soon' ],
            [ 'name' => 'Prairieville Primary School',  'status' => 'coming-soon' ],
            [ 'name' => 'Sorrento Primary School',      'status' => 'coming-soon' ],
            [ 'name' => 'Spanish Lake Primary School',  'status' => 'coming-soon' ],
            [ 'name' => 'St. Amant Primary School',     'status' => 'coming-soon' ],
            [ 'name' => 'Sugar Mill Primary School',    'status' => 'coming-soon' ],
        ],
    ];
    // Merge in generated school store records (TSA Store Builder). Records win
    // over the hardcoded seed by name/slug; the seed remains the fallback for
    // schools not yet generated. Records also extend the seed with new schools.
    if ( function_exists( 'tsa_school_store_records' ) ) {
        foreach ( tsa_school_store_records() as $r ) {
            // Drop any seed entry with the same name or store URL, in any group.
            foreach ( $groups as $gname => $glist ) {
                $groups[ $gname ] = array_values( array_filter( $glist, function ( $s ) use ( $r ) {
                    $same_name = sanitize_title( $s['name'] ) === sanitize_title( $r['name'] );
                    $same_url  = ! empty( $s['url'] ) && trim( $s['url'], '/' ) === trim( $r['url'], '/' );
                    return ! $same_name && ! $same_url;
                } ) );
            }
            $entry = [ 'name' => $r['name'], 'status' => $r['status'] ];
            if ( $r['mascot'] ) $entry['mascot'] = $r['mascot'];
            if ( $r['color']  ) $entry['color']  = $r['color'];
            if ( $r['color2'] ) $entry['color2'] = $r['color2'];
            if ( $r['status'] === 'live' ) $entry['url'] = $r['url'];
            $group = $r['level'] ?: 'High Schools';
            if ( ! isset( $groups[ $group ] ) ) $groups[ $group ] = [];
            $groups[ $group ][] = $entry;
        }
    }

    return apply_filters( 'tsa_school_directory', $groups );
}

/**
 * Is a configurator store a school store? Tolerant match of the store slug/name
 * against the canonical school directory (handles "Dutchtown" vs "Dutchtown High
 * School" vs the /schools/dutchtown/ store URL).
 */
function tsa_store_is_school( $slug, $name ) {
    $slug = sanitize_title( (string) $slug );
    $name = strtolower( trim( (string) $name ) );
    if ( ! $slug && ! $name ) return false;
    // The main TSA store is never a school — otherwise the configurator banner
    // runs the school-color path, finds no match, and falls back to district purple.
    if ( $slug && $slug === sanitize_title( apply_filters( 'tsa_design_main_store_slug', 'tsa' ) ) ) {
        return false;
    }
    foreach ( tsa_school_directory() as $group ) {
        foreach ( $group as $s ) {
            $sname = strtolower( $s['name'] );
            if ( $name && ( $name === $sname || strpos( $sname, $name ) !== false || strpos( $name, $sname ) !== false ) ) return true;
            if ( $slug ) {
                if ( ! empty( $s['url'] ) && strpos( $s['url'], $slug ) !== false ) return true;
                if ( strpos( sanitize_title( $s['name'] ), $slug ) === 0 ) return true;
            }
        }
    }
    return false;
}

/**
 * Store-colored configurator banner (Apparel Configurator `ac_store_color` filter).
 * School stores → official school colors; everything else → TSA brand. Text color
 * is auto-chosen for contrast. Returns [ primary, secondary, text ].
 */
add_filter( 'ac_store_color', function ( $default, $slug, $name ) {
    if ( function_exists( 'tsa_store_is_school' ) && tsa_store_is_school( $slug, $name ) ) {
        $c = tsa_get_school_colors( $name );
        return [
            'primary'   => $c['primary'],
            'secondary' => $c['secondary'] ?? $c['primary'],
            'text'      => tsa_readable_text( $c['primary'] ),
        ];
    }
    // TSA brand: gold → pink, dark readable text.
    return [
        'primary'   => '#d8a85f',
        'secondary' => '#fec2c0',
        'text'      => tsa_readable_text( '#d8a85f' ),
    ];
}, 10, 3 );

/* ═══════════════════════════════════════════════════════
   10. SHORTCODES
═══════════════════════════════════════════════════════ */

/** [tsa_school_grid] — school stores grid. */
add_shortcode( 'tsa_school_grid', 'tsa_shortcode_school_grid' );
function tsa_shortcode_school_grid( $atts ) {
    $stores = tsa_get_homepage_stores();
    if ( empty( $stores ) ) return '';
    ob_start();
    ?>
    <div class="tsa-school-grid">
        <?php foreach ( $stores as $store ) :
            $status  = get_post_meta( $store->ID, '_tsa_homepage_status', true );
            $cta_url = get_post_meta( $store->ID, '_tsa_store_cta_url', true );
            $is_live = ( $status === 'live' );
            $tag     = $is_live && $cta_url ? 'a' : 'div';
            $href    = $is_live && $cta_url ? ' href="' . esc_url( $cta_url ) . '"' : '';
            $title   = get_the_title( $store->ID );

            // Per-school brand colors (from the Ascension brand guide).
            $c   = tsa_get_school_colors( $title );
            $txt = tsa_readable_text( $c['primary'] );
            if ( $is_live ) {
                // Live: school primary background + readable text + secondary top accent.
                $card_style = 'background:' . $c['primary'] . ';color:' . $txt . ';box-shadow:inset 0 5px 0 0 ' . $c['secondary'] . ';';
                $name_style = ' style="color:' . esc_attr( $txt ) . '"';
            } else {
                // Coming soon: light card with the school's primary as a top accent strip.
                $card_style = 'box-shadow:inset 0 5px 0 0 ' . $c['primary'] . ';';
                $name_style = '';
            }
        ?>
            <<?php echo esc_html( $tag ); ?><?php echo $href; // phpcs:ignore
                ?> class="tsa-school-card<?php echo $is_live ? ' live' : ''; ?>" style="<?php echo esc_attr( $card_style ); ?>">
                <h3<?php echo $name_style; // phpcs:ignore ?>><?php echo esc_html( $title ); ?></h3>
                <p><?php echo $is_live ? 'Now live' : 'Coming soon'; ?></p>
            </<?php echo esc_html( $tag ); ?>>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/** [tsa_store_spotlight] — purple spotlight card. */
add_shortcode( 'tsa_store_spotlight', 'tsa_shortcode_store_spotlight' );
function tsa_shortcode_store_spotlight( $atts ) {
    $store = tsa_get_spotlight_store();
    if ( ! $store ) return '';
    $tagline  = get_post_meta( $store->ID, '_tsa_store_tagline', true ) ?: 'Shop the latest collections.';
    $cta_text = get_post_meta( $store->ID, '_tsa_store_cta_text', true ) ?: 'Enter Store';
    $cta_url  = get_post_meta( $store->ID, '_tsa_store_cta_url', true ) ?: '#';
    $img_src  = ( $img_id = get_post_thumbnail_id( $store->ID ) )
                ? wp_get_attachment_image_url( $img_id, 'tsa-store-banner' ) : '';
    ob_start();
    ?>
    <div class="tsa-store-card">
        <div class="tsa-store-visual" <?php echo $img_src ? 'style="background-image:url(' . esc_url( $img_src ) . ')"' : ''; ?>></div>
        <div class="tsa-store-copy">
            <?php tsa_kicker( 'Now Live', 'light' ); ?>
            <h2><?php echo esc_html( get_the_title( $store->ID ) ); ?></h2>
            <p><?php echo esc_html( $tagline ); ?></p>
            <?php tsa_btn( $cta_url, $cta_text, 'primary' ); ?>
        </div>
    </div>
    <?php
    return ob_get_clean();
}

/** [tsa_service_features service="dtf-printing-services"] */
add_shortcode( 'tsa_service_features', 'tsa_shortcode_service_features' );
function tsa_shortcode_service_features( $atts ) {
    $atts = shortcode_atts( [ 'service' => '' ], $atts );
    $config = tsa_get_service_config( $atts['service'] );
    if ( empty( $config['features'] ) ) return '';
    ob_start();
    ?>
    <div class="tsa-features-grid">
        <?php foreach ( $config['features'] as $f ) : ?>
        <div class="tsa-feature-card">
            <div class="tsa-feature-icon"><?php echo esc_html( $f['icon'] ); ?></div>
            <h4><?php echo esc_html( $f['title'] ); ?></h4>
            <p><?php echo esc_html( $f['desc'] ); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
    <?php
    return ob_get_clean();
}

/** [tsa_party_products category="tee-party"] */
add_shortcode( 'tsa_party_products', 'tsa_shortcode_party_products' );
function tsa_shortcode_party_products( $atts ) {
    $atts = shortcode_atts( [ 'category' => 'tee-party', 'limit' => 8 ], $atts );
    $args = [
        'post_type'      => 'product',
        'posts_per_page' => (int) $atts['limit'],
        'tax_query'      => [
            [
                'taxonomy' => 'product_cat',
                'field'    => 'slug',
                'terms'    => sanitize_key( $atts['category'] ),
            ],
        ],
    ];
    $products = new WP_Query( $args );
    if ( ! $products->have_posts() ) return '<p>No products in this drop yet. Check back soon.</p>';
    ob_start();
    echo '<div class="tsa-design-grid">';
    while ( $products->have_posts() ) {
        $products->the_post();
        $product = wc_get_product( get_the_ID() );
        $price   = $product ? $product->get_price_html() : '';
        ?>
        <a href="<?php the_permalink(); ?>" class="tsa-design-card">
            <div class="tsa-design-card__preview">
                <?php if ( has_post_thumbnail() ) : the_post_thumbnail( 'tsa-design-thumb' );
                else : ?>
                    <span style="color:var(--tsa-muted);font-size:13px;font-weight:700;"><?php the_title(); ?></span>
                <?php endif; ?>
                <div class="tsa-design-card__overlay">
                    <span class="tsa-btn tsa-btn-white tsa-btn-sm">View Details</span>
                </div>
            </div>
            <div class="tsa-design-card__body">
                <div class="tsa-design-card__name"><?php the_title(); ?></div>
                <div class="tsa-design-card__cat"><?php echo wp_kses_post( $price ); ?></div>
            </div>
        </a>
        <?php
    }
    echo '</div>';
    wp_reset_postdata();
    return ob_get_clean();
}

/* ═══════════════════════════════════════════════════════
   11. WOOCOMMERCE TWEAKS
═══════════════════════════════════════════════════════ */

// Add TSA body class on WC pages
add_filter( 'body_class', function( $classes ) {
    if ( is_woocommerce() || is_cart() || is_checkout() ) {
        $classes[] = 'tsa-woo-page';
    }
    return $classes;
} );

// Wrap WooCommerce products list
add_action( 'woocommerce_before_shop_loop', function() {
    echo '<div class="tsa-wc-products-wrap">';
}, 5 );
add_action( 'woocommerce_after_shop_loop', function() {
    echo '</div>';
}, 20 );

/* ═══════════════════════════════════════════════════════
   12. ADMIN BRANDING
═══════════════════════════════════════════════════════ */
add_action( 'admin_enqueue_scripts', 'tsa_admin_styles' );
function tsa_admin_styles() {
    wp_add_inline_style( 'wp-admin', '
        .tsa-admin-badge {
            display: inline-block;
            background: #fec2c0;
            color: #252124;
            border-radius: 999px;
            padding: 2px 8px;
            font-size: 11px;
            font-weight: 700;
            margin-left: 6px;
        }
        #wpbody-content .tsa-field-section h3 {
            background: #fec2c0;
            padding: 8px 14px;
            border-radius: 6px;
            font-size: 13px;
            color: #252124;
        }
    ' );
}

/* ═══════════════════════════════════════════════════════
   13. SCHOOL SUBSITE — DROP CPT + HELPER FUNCTIONS
═══════════════════════════════════════════════════════ */

/** Register the 'drop' custom post type used by school drop calendars. */
add_action( 'init', 'tsa_register_drop_cpt' );
function tsa_register_drop_cpt() {
    register_post_type( 'drop', [
        'labels'        => [
            'name'          => 'Drops',
            'singular_name' => 'Drop',
            'add_new_item'  => 'Add New Drop',
            'edit_item'     => 'Edit Drop',
        ],
        'public'        => false,
        'show_ui'       => true,
        'show_in_menu'  => true,
        'menu_icon'     => 'dashicons-calendar-alt',
        'supports'      => [ 'title', 'thumbnail' ],
        'menu_position' => 26,
    ] );
}

/**
 * Get all WooCommerce product categories for a school.
 * Categories are expected to be prefixed with the school slug, e.g. "dutchtown-band".
 *
 * @param string $school    School slug, e.g. 'dutchtown'
 * @param bool   $live_only Unused — caller filters live/soon manually.
 * @return array WP_Term[]
 */
function tsa_get_school_collections( $school, $live_only = true ) {
    $terms = get_terms( [
        'taxonomy'   => 'product_cat',
        'hide_empty' => false,
        'orderby'    => 'name',
        'order'      => 'ASC',
    ] );

    if ( is_wp_error( $terms ) ) {
        return [];
    }

    // Return only categories whose slug starts with "{school}-"
    $prefix = $school . '-';
    return array_values( array_filter( $terms, function( $t ) use ( $prefix ) {
        return strpos( $t->slug, $prefix ) === 0;
    } ) );
}

/**
 * Get the next upcoming drop for a school.
 *
 * @param string $school School slug, e.g. 'dutchtown'
 * @return WP_Post|null
 */
function tsa_get_next_drop( $school ) {
    $drops = get_posts( [
        'post_type'      => 'drop',
        'posts_per_page' => 1,
        'meta_key'       => '_drop_date',
        'orderby'        => 'meta_value',
        'order'          => 'ASC',
        'meta_query'     => [
            'relation' => 'AND',
            [ 'key' => '_school_slug', 'value' => $school, 'compare' => '=' ],
            [ 'key' => '_drop_date',   'value' => date( 'Y-m-d' ), 'compare' => '>=' ],
        ],
    ] );
    return isset( $drops[0] ) ? $drops[0] : null;
}

/** Enqueue Dutchtown CSS + JS on school pages. */
add_action( 'wp_enqueue_scripts', 'tsa_enqueue_school_assets', 25 );
function tsa_enqueue_school_assets() {
    $school_templates = [
        'schools/dutchtown/page-school-dutchtown.php',
        'schools/dutchtown/page-school-collection.php',
        'schools/dutchtown/page-school-drops.php',
        'schools/dutchtown/page-school-programs.php',
        // Generic premium store templates (brandable dths chrome, any store type)
        'template-store-premium.php',
        'template-store-programs.php',
        'template-store-program.php',
    ];
    $tpl = get_page_template_slug();
    if ( ! in_array( $tpl, $school_templates, true ) ) {
        return;
    }
    $v      = wp_get_theme()->get( 'Version' );
    $dir    = get_stylesheet_directory_uri() . '/schools/dutchtown';
    $base   = get_stylesheet_directory() . '/schools/dutchtown';
    // filemtime() cache-busting so edits to the CSS/JS actually bust LiteSpeed +
    // browser caches (theme-version alone doesn't change when only the file does).
    $css_p  = $base . '/css/dutchtown-scoped.css';
    $js_p   = $base . '/js/dutchtown.js';
    $css_v  = file_exists( $css_p ) ? filemtime( $css_p ) : $v;
    $js_v   = file_exists( $js_p )  ? filemtime( $js_p )  : $v;
    wp_enqueue_style(  'dths-style',  $dir . '/css/dutchtown-scoped.css', [ 'tsa-child' ], $css_v );
    wp_enqueue_script( 'dths-script', $dir . '/js/dutchtown.js',          [ 'jquery' ],    $js_v, true );
}

/* ═══════════════════════════════════════════════════════
   INCLUDES
   Loaded defensively: a missing file (e.g. mid-deploy upload)
   is skipped + logged instead of white-screening the whole site.
═══════════════════════════════════════════════════════ */
$tsa_includes = [
	'inc/platform.php',
	'inc/form-emails.php',
	'inc/page-store-link.php',
	'inc/design-library.php',
	'inc/store-designs.php',
	'inc/store-chrome.php',
	'inc/store-apparel.php',
	'inc/admin-hub.php',
	'inc/design-bulk-upload.php',
	'inc/tee-party.php',
	'inc/store-cart.php',
	'inc/cart-ui.php',
	'inc/school-builder.php',
	'inc/provisioning.php',
	'inc/branding.php',
	'inc/fundraising.php',
	'inc/ambassadors.php',
	'inc/reports.php',
	'inc/dashboard.php',
	'inc/blank-catalog.php',
	'inc/blank-catalog-import.php',
	'inc/featured-designs.php',
	'inc/help-center.php',
	'inc/requests.php',
	'inc/sms-alerts.php',
	'inc/party-alerts.php',
	'inc/bulk-email.php',
	'inc/live-search.php',
	'inc/order-tickets.php',
];
foreach ( $tsa_includes as $tsa_inc ) {
	$tsa_inc_path = get_stylesheet_directory() . '/' . $tsa_inc;
	if ( file_exists( $tsa_inc_path ) ) {
		require_once $tsa_inc_path;
	} else {
		error_log( 'TSA theme: missing include ' . $tsa_inc );
	}
}
unset( $tsa_includes, $tsa_inc, $tsa_inc_path );

/* ═══════════════════════════════════════════════════════
   ACCOUNT → CUSTOMER PORTAL
   Once the Customer Portal page is the WooCommerce "My Account" page,
   301 any leftover /my-account/* URLs (old links, bookmarks, header
   dropdown) to the matching /customer-portal/* path, and relabel the
   header "My account". Safe no-op until the WC account page actually
   points at the /customer-portal/ page.
═══════════════════════════════════════════════════════ */
add_action( 'template_redirect', function () {
    if ( is_admin() || ! function_exists( 'wc_get_page_id' ) ) return;
    $portal = get_page_by_path( 'customer-portal' );
    if ( ! $portal || (int) wc_get_page_id( 'myaccount' ) !== (int) $portal->ID ) return;
    $path = wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH );
    if ( $path && preg_match( '#^/my-account(/.*)?$#i', $path, $m ) ) {
        wp_safe_redirect( home_url( '/customer-portal' . ( $m[1] ?? '/' ) ), 301 );
        exit;
    }
} );

add_filter( 'gettext', function ( $translated, $text ) {
    if ( ! is_admin() && ( 'My account' === $text || 'My Account' === $text ) ) {
        return 'Customer Portal';
    }
    return $translated;
}, 20, 2 );

/* ═══════════════════════════════════════════════════════
   REWRITE FLUSH — for the public tsa_brand (/blank-apparel/{brand}/)
   and configurator_garment (/apparel/{slug}/) single pages. Flushes
   on theme switch, and once after an S&S import (which sets the flag).
═══════════════════════════════════════════════════════ */
add_action( 'after_switch_theme', 'flush_rewrite_rules' );
add_action( 'init', function () {
    if ( get_option( 'tsa_flush_rewrites' ) ) {
        flush_rewrite_rules();
        delete_option( 'tsa_flush_rewrites' );
    }
}, 99 );

/* ═══════════════════════════════════════════════════════
   CONTACT FORM HANDLER (template-contact.php)
   Posts to admin-post.php (action=tsa_contact), emails the
   site admin, then redirects back with ?contact=sent|error.
═══════════════════════════════════════════════════════ */
add_action( 'admin_post_nopriv_tsa_contact', 'tsa_handle_contact_form' );
add_action( 'admin_post_tsa_contact',        'tsa_handle_contact_form' );
function tsa_handle_contact_form() {
    $referer  = wp_get_referer() ?: home_url( '/contact/' );
    $back     = function ( $state ) use ( $referer ) {
        wp_safe_redirect( add_query_arg( 'contact', $state, remove_query_arg( 'contact', $referer ) ) . '#tsa_name' );
        exit;
    };

    // Nonce + honeypot
    if (
        ! isset( $_POST['tsa_contact_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_contact_nonce'] ) ), 'tsa_contact' )
    ) {
        $back( 'error' );
    }
    if ( ! empty( $_POST['tsa_hp'] ) ) {
        $back( 'sent' ); // silently swallow bots
    }

    $name    = sanitize_text_field( wp_unslash( $_POST['tsa_name']    ?? '' ) );
    $email   = sanitize_email(      wp_unslash( $_POST['tsa_email']   ?? '' ) );
    $phone   = sanitize_text_field( wp_unslash( $_POST['tsa_phone']   ?? '' ) );
    $topic   = sanitize_text_field( wp_unslash( $_POST['tsa_topic']   ?? '' ) );
    $message = sanitize_textarea_field( wp_unslash( $_POST['tsa_message'] ?? '' ) );

    if ( ! $name || ! is_email( $email ) || ! $message ) {
        $back( 'error' );
    }

    $to      = function_exists( 'tsa_form_recipient' ) ? tsa_form_recipient( 'contact' ) : get_option( 'admin_email' );
    $subject = sprintf( '[Contact] %s — %s', $topic ?: 'General', $name );
    $body    = "New contact form submission from teeshirtali.com:\n\n"
             . "Name:    {$name}\n"
             . "Email:   {$email}\n"
             . "Phone:   " . ( $phone ?: '—' ) . "\n"
             . "Topic:   " . ( $topic ?: '—' ) . "\n\n"
             . "Message:\n{$message}\n";
    $headers = [
        'Content-Type: text/plain; charset=UTF-8',
        'Reply-To: ' . $name . ' <' . $email . '>',
    ];

    $sent = wp_mail( $to, $subject, $body, $headers );

    if ( function_exists( 'tsa_record_request' ) ) {
        tsa_record_request( [
            'type'    => 'contact',
            'name'    => $name,
            'email'   => $email,
            'phone'   => $phone,
            'message' => ( $topic ? "Topic: {$topic}\n\n" : '' ) . $message,
        ] );
    }

    $back( $sent ? 'sent' : 'error' );
}

/* ═══════════════════════════════════════════════════════
   MEGA-MENU ENHANCEMENT (Services / Stores dropdowns)
   Adds an SVG icon + one-line description to dropdown items
   (depth >= 1) whose URL matches the map, and tags the
   Services/Stores top-level items so CSS lays the dropdown
   out as a multi-column mega panel. Uses core nav filters,
   so it works with Flatsome's default menu walker.
═══════════════════════════════════════════════════════ */
function tsa_mega_map() {
    return [
        // Services
        '/dtf-printing/'         => [ 'printer',   'Vivid full-color DTF transfers' ],
        '/blank-apparel/'        => [ 'shirt',     'Premium blanks, no minimums' ],
        '/brand-catalog/'        => [ 'book',      'The apparel brands we carry' ],
        '/custom-apparel/'       => [ 'shirt',     'Design-first custom apparel' ],
        '/fundraisers/'          => [ 'heart',     'Zero-risk group fundraising' ],
        '/gang-sheet-builder/'   => [ 'layers',    'Build & price your gang sheet' ],
        '/graphic-design/'       => [ 'pen',       'Custom artwork & logos' ],
        '/promotional-products/' => [ 'gift',      'Branded swag & promo items' ],
        '/services/'             => [ 'grid',      'Everything we offer' ],
        // Stores
        '/schools/'              => [ 'cap',       'School spirit stores' ],
        '/team-stores/'          => [ 'users',     'Team & club gear' ],
        '/businesses/'           => [ 'briefcase', 'Business & brand merch' ],
        '/tee-party/'            => [ 'sparkles',  'Limited-time design drops' ],
        '/design-library/'       => [ 'swatch',    'Ready-to-order artwork' ],
    ];
}

function tsa_mega_icon( $key ) {
    $o = '<svg viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round">';
    switch ( $key ) {
        case 'printer':   $p = '<polyline points="6 9 6 2 18 2 18 9"/><path d="M6 18H4a2 2 0 0 1-2-2v-5a2 2 0 0 1 2-2h16a2 2 0 0 1 2 2v5a2 2 0 0 1-2 2h-2"/><rect x="6" y="14" width="12" height="8"/>'; break;
        case 'shirt':     $p = '<path d="M20.38 3.46 16 2a4 4 0 0 1-8 0L3.62 3.46a2 2 0 0 0-1.34 2.23l.58 3.47a1 1 0 0 0 .99.84H6v10c0 1.1.9 2 2 2h8a2 2 0 0 0 2-2V10h2.15a1 1 0 0 0 .99-.84l.58-3.47a2 2 0 0 0-1.34-2.23z"/>'; break;
        case 'book':      $p = '<path d="M4 19.5A2.5 2.5 0 0 1 6.5 17H20"/><path d="M6.5 2H20v20H6.5A2.5 2.5 0 0 1 4 19.5v-15A2.5 2.5 0 0 1 6.5 2z"/>'; break;
        case 'heart':     $p = '<path d="M19 14c1.49-1.46 3-3.21 3-5.5A5.5 5.5 0 0 0 16.5 3c-1.76 0-3 .5-4.5 2-1.5-1.5-2.74-2-4.5-2A5.5 5.5 0 0 0 2 8.5c0 2.29 1.51 4.04 3 5.5l7 7Z"/>'; break;
        case 'layers':    $p = '<polygon points="12 2 2 7 12 12 22 7 12 2"/><polyline points="2 17 12 22 22 17"/><polyline points="2 12 12 17 22 12"/>'; break;
        case 'pen':       $p = '<path d="M12 19l7-7 3 3-7 7-3-3z"/><path d="M18 13l-1.5-7.5L2 2l3.5 14.5L13 18l5-5z"/><path d="M2 2l7.586 7.586"/><circle cx="11" cy="11" r="2"/>'; break;
        case 'gift':      $p = '<polyline points="20 12 20 22 4 22 4 12"/><rect x="2" y="7" width="20" height="5"/><line x1="12" y1="22" x2="12" y2="7"/><path d="M12 7H7.5a2.5 2.5 0 0 1 0-5C11 2 12 7 12 7z"/><path d="M12 7h4.5a2.5 2.5 0 0 0 0-5C13 2 12 7 12 7z"/>'; break;
        case 'grid':      $p = '<rect x="3" y="3" width="7" height="7"/><rect x="14" y="3" width="7" height="7"/><rect x="14" y="14" width="7" height="7"/><rect x="3" y="14" width="7" height="7"/>'; break;
        case 'cap':       $p = '<path d="M22 10 12 5 2 10l10 5 10-5z"/><path d="M6 12v5c3 3 9 3 12 0v-5"/>'; break;
        case 'users':     $p = '<path d="M17 21v-2a4 4 0 0 0-4-4H5a4 4 0 0 0-4 4v2"/><circle cx="9" cy="7" r="4"/><path d="M23 21v-2a4 4 0 0 0-3-3.87"/><path d="M16 3.13a4 4 0 0 1 0 7.75"/>'; break;
        case 'briefcase': $p = '<rect x="2" y="7" width="20" height="14" rx="2"/><path d="M16 21V5a2 2 0 0 0-2-2h-4a2 2 0 0 0-2 2v16"/>'; break;
        case 'sparkles':  $p = '<path d="M12 3l1.9 5.8L20 10l-6.1 1.2L12 17l-1.9-5.8L4 10l6.1-1.2L12 3z"/>'; break;
        case 'swatch':    $p = '<path d="M11 4a2 2 0 0 1 2 2v12a4 4 0 1 1-8 0V6a2 2 0 0 1 2-2z"/><circle cx="9" cy="18" r=".5"/>'; break;
        default:          $p = '<circle cx="12" cy="12" r="9"/>';
    }
    return $o . $p . '</svg>';
}

add_filter( 'nav_menu_item_title', 'tsa_mega_menu_item_title', 10, 4 );
function tsa_mega_menu_item_title( $title, $item, $args, $depth ) {
    if ( is_admin() || (int) $depth < 1 ) return $title;   // dropdown children only
    $path = '/' . trim( (string) parse_url( (string) $item->url, PHP_URL_PATH ), '/' ) . '/';
    $map  = tsa_mega_map();
    if ( ! isset( $map[ $path ] ) ) return $title;
    [ $icon, $desc ] = $map[ $path ];
    return '<span class="tsa-mm-ico" aria-hidden="true">' . tsa_mega_icon( $icon ) . '</span>'
         . '<span class="tsa-mm-text"><span class="tsa-mm-title">' . esc_html( $title ) . '</span>'
         . '<span class="tsa-mm-desc">' . esc_html( $desc ) . '</span></span>';
}

add_filter( 'nav_menu_css_class', 'tsa_mega_parent_class', 10, 4 );
function tsa_mega_parent_class( $classes, $item, $args, $depth ) {
    if ( is_admin() || (int) $depth !== 0 ) return $classes;
    if ( in_array( strtolower( trim( (string) $item->title ) ), [ 'services', 'stores' ], true ) ) {
        $classes[] = 'tsa-mega-parent';
    }
    return $classes;
}

/* ═══════════════════════════════════════════════════════
   OWNER DASHBOARD — keep the page discreet (noindex + no on-site search).
   NOTE: tsa_dash_page_id() and the admin-menu / admin-bar links live in
   inc/dashboard.php. Do NOT redefine the helper here — dashboard.php is
   required above, so a second declaration is a fatal "Cannot redeclare".
═══════════════════════════════════════════════════════ */

// noindex/nofollow on the dashboard page.
add_action( 'wp_head', function () {
    if ( is_page() && function_exists( 'tsa_dash_page_id' ) && get_the_ID() === tsa_dash_page_id() ) {
        echo '<meta name="robots" content="noindex,nofollow,noarchive">' . "\n";
    }
}, 1 );

// Keep it out of on-site search results.
add_action( 'pre_get_posts', function ( $q ) {
    if ( ! is_admin() && $q->is_search() && $q->is_main_query()
        && function_exists( 'tsa_dash_page_id' ) && tsa_dash_page_id() ) {
        $q->set( 'post__not_in', array_merge( (array) $q->get( 'post__not_in' ), [ tsa_dash_page_id() ] ) );
    }
} );

/* ── Whole-site search ───────────────────────────────────────────────
   The header search form hard-codes post_type=product (Flatsome product
   search). Broaden the main search query to a true site search across
   products, pages, posts, and Design Library designs. */
add_action( 'pre_get_posts', function ( $q ) {
    if ( is_admin() || ! $q->is_search() || ! $q->is_main_query() ) {
        return;
    }
    $q->set( 'post_type', [ 'product', 'configurator_garment', 'page', 'post', 'tsa_design' ] );
} );

/* Designs have no public single page → point their permalink at the
   configurator (preloaded), so design search results are clickable. */
add_filter( 'post_type_link', function ( $url, $post ) {
    if ( $post && 'tsa_design' === $post->post_type ) {
        return home_url( '/configurator/?design_id=' . (int) $post->ID );
    }
    return $url;
}, 10, 2 );

/* ═══════════════════════════════════════════════════════
   FUNDRAISERS — temporary "Coming Soon" gate.
   The public /fundraisers/ hub launches AFTER the summer Tee Parties.
   Until then any visit to /fundraisers/ shows a branded coming-soon
   screen with a mailto link (contact-form recipient). All existing
   links/buttons that point at /fundraisers/ land here automatically.
   To re-enable the real page later, remove this block.
═══════════════════════════════════════════════════════ */
add_action( 'template_redirect', function () {
    if ( is_admin() ) return;
    $path = trim( (string) wp_parse_url( wp_unslash( $_SERVER['REQUEST_URI'] ?? '' ), PHP_URL_PATH ), '/' );
    // Catch both the plural /fundraisers/ and the singular /fundraiser/ the
    // Services menu links to, so both land on this coming-soon gate.
    if ( ! in_array( $path, [ 'fundraisers', 'fundraiser' ], true ) ) return;

    // tsa_form_recipient() may return an array (multiple recipients, valid for
    // wp_mail). The mailto link needs a single string, so take the first.
    $email = function_exists( 'tsa_form_recipient' ) ? tsa_form_recipient( 'contact' ) : get_option( 'admin_email' );
    if ( is_array( $email ) ) {
        $email = reset( $email );
    }
    $email = is_string( $email ) ? $email : (string) get_option( 'admin_email' );
    get_header();
    ?>
    <section class="tsa-section" style="text-align:center;padding:90px 7%;">
        <div style="max-width:620px;margin:0 auto;">
            <div class="tsa-kicker" style="display:inline-flex;margin:0 0 18px;">Coming Soon</div>
            <h1 style="font-size:clamp(30px,5vw,46px);margin:0 0 16px;color:var(--tsa-dark,#252124);">Fundraisers are on the way</h1>
            <p style="font-size:18px;line-height:1.6;color:var(--tsa-muted,#6d6268);margin:0 0 28px;">
                We're launching our zero-risk fundraiser program right after this summer's Tee Parties.
                Have a school, team, or group in mind? Reach out and we'll get you on the list.
            </p>
            <a class="tsa-btn tsa-btn-primary" href="mailto:<?php echo esc_attr( antispambot( $email ) ); ?>?subject=<?php echo rawurlencode( 'Fundraiser inquiry' ); ?>">Email us about fundraisers</a>
        </div>
    </section>
    <?php
    get_footer();
    exit;
}, 5 );
