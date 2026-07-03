<?php
/**
 * TSA Page → Store Link Meta Boxes
 *
 * Adds per-page meta boxes for:
 *  - Store association (_tsa_store_id)
 *  - Store tagline (_tsa_store_tagline)
 *  - Team sport / season (team store template)
 *  - Service config overrides (service template)
 *  - Tee Party end date + product category
 *  - AI shortcode override (customer portal)
 *  - Design library WC category
 *
 * Also conditionally enqueues tsa-templates.css on pages using TSA templates.
 *
 * Included via: require_once get_stylesheet_directory() . '/inc/page-store-link.php';
 */

defined( 'ABSPATH' ) || exit;

/* ══════════════════════════════════════════════════════
   CONDITIONAL CSS ENQUEUE
   Load tsa-templates.css on pages using any TSA template.
══════════════════════════════════════════════════════ */
add_action( 'wp_enqueue_scripts', function () {
    $tsa_templates = [
        'template-homepage.php',
        'template-service.php',
        'template-tee-party.php',
        'template-blank-apparel.php',
        'template-brand-catalog.php',
        'template-design-library.php',
        'template-quote.php',
        'template-fundraiser.php',
        'template-school-store.php',
        'template-team-store.php',
        'template-customer-portal.php',
    ];

    $current_template = get_page_template_slug();
    if ( in_array( $current_template, $tsa_templates, true ) ) {
        wp_enqueue_style(
            'tsa-templates',
            get_stylesheet_directory_uri() . '/assets/css/tsa-templates.css',
            [ 'tsa-main' ],
            '2.0.0'
        );
    }

    // Info / utility / legal pages share one stylesheet (tokens load sitewide).
    $tsa_info_templates = [
        'template-contact.php',
        'template-faq.php',
        'template-sizing-guide.php',
        'template-printing-capabilities.php',
        'template-turnaround-times.php',
        'template-privacy-policy.php',
        'template-terms.php',
        'template-shipping-policy.php',
        'template-returns.php',
        'template-fundraiser-form.php',
    ];
    if ( in_array( $current_template, $tsa_info_templates, true ) ) {
        $info_css = get_stylesheet_directory() . '/assets/css/tsa-info-pages.css';
        wp_enqueue_style(
            'tsa-info-pages',
            get_stylesheet_directory_uri() . '/assets/css/tsa-info-pages.css',
            [ 'tsa-child' ],
            file_exists( $info_css ) ? filemtime( $info_css ) : '1.0.0'
        );
    }

    // Tee Party gets its own dedicated CSS + JS
    if ( $current_template === 'template-tee-party.php' ) {
        $tp_css = get_stylesheet_directory() . '/assets/css/tsa-tee-party.css';
        wp_enqueue_style(
            'tsa-tee-party',
            get_stylesheet_directory_uri() . '/assets/css/tsa-tee-party.css',
            [ 'tsa-templates' ],
            file_exists( $tp_css ) ? filemtime( $tp_css ) : '3.0.0'
        );
        wp_enqueue_script(
            'tsa-tee-party',
            get_stylesheet_directory_uri() . '/assets/js/tsa-tee-party.js',
            [],
            '2.0.0',
            true
        );
    }
}, 20 );

/* ══════════════════════════════════════════════════════
   META BOX REGISTRATION
══════════════════════════════════════════════════════ */
add_action( 'add_meta_boxes', function () {
    add_meta_box(
        'tsa_store_link',
        'TSA Store Settings',
        'tsa_store_link_metabox_html',
        'page',
        'side',
        'high'
    );
    add_meta_box(
        'tsa_page_config',
        'TSA Page Config',
        'tsa_page_config_metabox_html',
        'page',
        'normal',
        'default'
    );
} );

/* ──────────────────────────────────────────────────
   Store Link Sidebar Meta Box
────────────────────────────────────────────────── */
function tsa_store_link_metabox_html( WP_Post $post ) {
    $store_id = (int) get_post_meta( $post->ID, '_tsa_store_id', true );
    $stores   = get_posts( [
        'post_type'      => 'configurator_store',
        'posts_per_page' => -1,
        'orderby'        => 'title',
        'order'          => 'ASC',
    ] );
    wp_nonce_field( 'tsa_store_link_save', 'tsa_store_link_nonce' );
    ?>
    <p>
        <label for="tsa_store_id" style="font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:.4px;">
            Linked Configurator Store
        </label><br>
        <select name="tsa_store_id" id="tsa_store_id" style="width:100%; margin-top:5px;">
            <option value="">— None —</option>
            <?php foreach ( $stores as $store ) : ?>
            <option value="<?php echo esc_attr( $store->ID ); ?>" <?php selected( $store_id, $store->ID ); ?>>
                <?php echo esc_html( $store->post_title ); ?>
            </option>
            <?php endforeach; ?>
        </select>
    </p>
    <?php
}

/* ──────────────────────────────────────────────────
   Page Config Main Meta Box
────────────────────────────────────────────────── */
function tsa_page_config_metabox_html( WP_Post $post ) {
    $template = get_page_template_slug( $post->ID );

    // Load saved values
    $fields = [
        '_tsa_store_tagline'      => get_post_meta( $post->ID, '_tsa_store_tagline', true ),
        '_tsa_team_sport'         => get_post_meta( $post->ID, '_tsa_team_sport', true ),
        '_tsa_team_season'        => get_post_meta( $post->ID, '_tsa_team_season', true ),
        '_tsa_service_tagline'    => get_post_meta( $post->ID, '_tsa_service_tagline', true ),
        '_tsa_service_gradient'   => get_post_meta( $post->ID, '_tsa_service_gradient', true ),
        '_tsa_service_cta_url'    => get_post_meta( $post->ID, '_tsa_service_cta_url', true ),
        '_tsa_service_cta_text'   => get_post_meta( $post->ID, '_tsa_service_cta_text', true ),
        '_tsa_party_end_date'     => get_post_meta( $post->ID, '_tsa_party_end_date', true ),
        '_tsa_party_wc_cat'       => get_post_meta( $post->ID, '_tsa_party_wc_cat', true ),
        '_tsa_party_urgency_text' => get_post_meta( $post->ID, '_tsa_party_urgency_text', true ),
        '_tsa_ai_shortcode'       => get_post_meta( $post->ID, '_tsa_ai_shortcode', true ),
        '_tsa_design_wc_cat'      => get_post_meta( $post->ID, '_tsa_design_wc_cat', true ),
        '_tsa_store_spotlight'    => get_post_meta( $post->ID, '_tsa_store_spotlight', true ),
    ];

    wp_nonce_field( 'tsa_page_config_save', 'tsa_page_config_nonce' );
    ?>
    <style>
        .tsa-mb-group { margin-bottom:18px; }
        .tsa-mb-group label { display:block; font-weight:700; font-size:11px; text-transform:uppercase; letter-spacing:.4px; margin-bottom:5px; color:#1d2327; }
        .tsa-mb-group input[type=text],
        .tsa-mb-group input[type=url],
        .tsa-mb-group input[type=datetime-local],
        .tsa-mb-group textarea,
        .tsa-mb-group select { width:100%; padding:6px 10px; border:1px solid #ccc; border-radius:4px; font-size:13px; }
        .tsa-mb-group textarea { height:80px; resize:vertical; }
        .tsa-mb-section { border-top:1px solid #ddd; padding-top:16px; margin-top:16px; }
        .tsa-mb-section h4 { margin:0 0 12px; font-size:12px; text-transform:uppercase; letter-spacing:.5px; color:#666; }
    </style>

    <!-- General (all templates) -->
    <div class="tsa-mb-group">
        <label for="tsa_store_tagline">Store / Page Tagline</label>
        <input type="text" id="tsa_store_tagline" name="tsa_store_tagline"
               value="<?php echo esc_attr( $fields['_tsa_store_tagline'] ); ?>"
               placeholder="Displayed under the page/store name in the hero" />
    </div>

    <?php if ( in_array( $template, [ 'template-team-store.php', 'template-school-store.php' ], true ) ) : ?>
    <!-- Team Store fields -->
    <div class="tsa-mb-section">
        <h4>Team Store Settings</h4>
        <div class="tsa-mb-group">
            <label for="tsa_team_sport">Sport / Activity</label>
            <input type="text" id="tsa_team_sport" name="tsa_team_sport"
                   value="<?php echo esc_attr( $fields['_tsa_team_sport'] ); ?>"
                   placeholder="e.g. Baseball, Soccer, Basketball" />
        </div>
        <div class="tsa-mb-group">
            <label for="tsa_team_season">Season (e.g. 2025–26)</label>
            <input type="text" id="tsa_team_season" name="tsa_team_season"
                   value="<?php echo esc_attr( $fields['_tsa_team_season'] ); ?>"
                   placeholder="2025–26" />
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $template === 'template-service.php' ) : ?>
    <!-- Service Page fields -->
    <div class="tsa-mb-section">
        <h4>Service Page Overrides</h4>
        <p style="font-size:12px; color:#666; margin:0 0 12px;">Leave blank to use auto-detected values from the page slug.</p>
        <div class="tsa-mb-group">
            <label for="tsa_service_tagline">Hero Tagline (override)</label>
            <input type="text" id="tsa_service_tagline" name="tsa_service_tagline"
                   value="<?php echo esc_attr( $fields['_tsa_service_tagline'] ); ?>" />
        </div>
        <div class="tsa-mb-group">
            <label for="tsa_service_gradient">Hero Gradient CSS (override)</label>
            <input type="text" id="tsa_service_gradient" name="tsa_service_gradient"
                   value="<?php echo esc_attr( $fields['_tsa_service_gradient'] ); ?>"
                   placeholder="linear-gradient(135deg, #f5a3a1 0%, #fec2c0 100%)" />
        </div>
        <div class="tsa-mb-group">
            <label for="tsa_service_cta_text">CTA Button Text</label>
            <input type="text" id="tsa_service_cta_text" name="tsa_service_cta_text"
                   value="<?php echo esc_attr( $fields['_tsa_service_cta_text'] ); ?>"
                   placeholder="Get a Quote" />
        </div>
        <div class="tsa-mb-group">
            <label for="tsa_service_cta_url">CTA Button URL</label>
            <input type="url" id="tsa_service_cta_url" name="tsa_service_cta_url"
                   value="<?php echo esc_attr( $fields['_tsa_service_cta_url'] ); ?>"
                   placeholder="/request-a-quote/" />
        </div>
    </div>
    <?php endif; ?>

    <?php /* Tee Party settings are handled entirely by the Tee Party Settings meta box (inc/tee-party.php). */ ?>

    <?php if ( $template === 'template-customer-portal.php' ) : ?>
    <!-- AI Chat fields -->
    <div class="tsa-mb-section">
        <h4>AI Chat Override</h4>
        <div class="tsa-mb-group">
            <label for="tsa_ai_shortcode">AI Assistant Shortcode</label>
            <input type="text" id="tsa_ai_shortcode" name="tsa_ai_shortcode"
                   value="<?php echo esc_attr( $fields['_tsa_ai_shortcode'] ); ?>"
                   placeholder="[your_ai_shortcode]" />
            <p style="font-size:11px; color:#888; margin:4px 0 0;">Leave blank to use the built-in stub chat UI.</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $template === 'template-design-library.php' ) : ?>
    <!-- Design Library fields -->
    <div class="tsa-mb-section">
        <h4>Design Library Settings</h4>
        <div class="tsa-mb-group">
            <label for="tsa_design_wc_cat">WooCommerce Category Slug</label>
            <input type="text" id="tsa_design_wc_cat" name="tsa_design_wc_cat"
                   value="<?php echo esc_attr( $fields['_tsa_design_wc_cat'] ); ?>"
                   placeholder="designs" />
            <p style="font-size:11px; color:#888; margin:4px 0 0;">Products in this category appear in the design grid.</p>
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $template === 'template-homepage.php' ) : ?>
    <!-- Homepage Settings -->
    <div class="tsa-mb-section">
        <h4>Homepage Settings</h4>
        <div class="tsa-mb-group">
            <label for="tsa_store_spotlight">Spotlight Store Post ID</label>
            <input type="text" id="tsa_store_spotlight" name="tsa_store_spotlight"
                   value="<?php echo esc_attr( $fields['_tsa_store_spotlight'] ); ?>"
                   placeholder="Leave blank to auto-select most recent" />
        </div>
    </div>
    <?php endif; ?>

    <?php if ( $template === 'template-gang-sheet-builder.php' ) :
        $gsb_widths_raw = get_post_meta( get_the_ID(), '_tsa_gsb_widths', true );
        if ( $gsb_widths_raw === '' ) { // seed from the old single rate, plus common widths
            $ppi = get_post_meta( get_the_ID(), '_tsa_gsb_price_per_inch', true ) ?: '0.50';
            $gsb_widths_raw = "13:{$ppi}:200\n22:0.80:200\n24:0.90:200";
        }
    ?>
    <!-- Gang Sheet Builder Settings -->
    <div class="tsa-mb-section">
        <h4>Gang Sheet Builder</h4>
        <p style="font-size:12px; color:#666; margin:0 0 12px;">
            One <strong>simple</strong> WooCommerce product handles all gang-sheet orders; the price is calculated
            (length × the chosen width's rate). No per-length variations needed.
        </p>
        <div class="tsa-mb-group">
            <label for="tsa_gsb_product_id">WooCommerce Product ID</label>
            <input type="text" id="tsa_gsb_product_id" name="tsa_gsb_product_id"
                   value="<?php echo esc_attr( get_post_meta( get_the_ID(), '_tsa_gsb_product_id', true ) ); ?>"
                   placeholder="e.g. 123 — leave blank to use quote form fallback" />
        </div>
        <div class="tsa-mb-group">
            <label for="tsa_gsb_widths">Roll widths &amp; rates</label>
            <textarea id="tsa_gsb_widths" name="tsa_gsb_widths" rows="5" class="large-text code"
                      style="font-family:monospace"><?php echo esc_textarea( $gsb_widths_raw ); ?></textarea>
            <p style="font-size:11px; color:#888; margin:4px 0 0;">
                One width per line as <code>width:pricePerInch:maxLength</code> (max optional, default 200).
                e.g. <code>13:0.50:200</code> · <code>22:0.80:200</code> · <code>24:0.90:200</code> · <code>32:1.20:150</code>.
                The first width is the default. Customers on the <em>Custom Sizes</em> tier see all widths; otherwise the first three (presets).
            </p>
        </div>
    </div>
    <?php endif; ?>
    <?php
}

/* ══════════════════════════════════════════════════════
   SAVE META BOXES
══════════════════════════════════════════════════════ */
add_action( 'save_post_page', function ( $post_id ) {
    // Store link nonce
    if (
        isset( $_POST['tsa_store_link_nonce'] ) &&
        wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_store_link_nonce'] ) ), 'tsa_store_link_save' )
    ) {
        $store_id = isset( $_POST['tsa_store_id'] ) ? (int) $_POST['tsa_store_id'] : 0;
        update_post_meta( $post_id, '_tsa_store_id', $store_id );
    }

    // Page config nonce
    if (
        ! isset( $_POST['tsa_page_config_nonce'] ) ||
        ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_page_config_nonce'] ) ), 'tsa_page_config_save' )
    ) {
        return;
    }

    // Gang Sheet Builder
    if ( isset( $_POST['tsa_gsb_product_id'] ) ) {
        update_post_meta( $post_id, '_tsa_gsb_product_id', (int) $_POST['tsa_gsb_product_id'] );
    }
    if ( isset( $_POST['tsa_gsb_widths'] ) ) {
        update_post_meta( $post_id, '_tsa_gsb_widths', sanitize_textarea_field( wp_unslash( $_POST['tsa_gsb_widths'] ) ) );
    }

    $text_fields = [
        'tsa_store_tagline'      => '_tsa_store_tagline',
        'tsa_team_sport'         => '_tsa_team_sport',
        'tsa_team_season'        => '_tsa_team_season',
        'tsa_service_tagline'    => '_tsa_service_tagline',
        'tsa_service_gradient'   => '_tsa_service_gradient',
        'tsa_service_cta_text'   => '_tsa_service_cta_text',
        'tsa_service_cta_url'    => '_tsa_service_cta_url',
        'tsa_party_end_date'     => '_tsa_party_end_date',
        'tsa_party_wc_cat'       => '_tsa_party_wc_cat',
        'tsa_party_urgency_text' => '_tsa_party_urgency_text',
        'tsa_ai_shortcode'       => '_tsa_ai_shortcode',
        'tsa_design_wc_cat'      => '_tsa_design_wc_cat',
        'tsa_store_spotlight'    => '_tsa_store_spotlight',
    ];

    foreach ( $text_fields as $post_key => $meta_key ) {
        if ( isset( $_POST[ $post_key ] ) ) {
            $value = sanitize_text_field( wp_unslash( $_POST[ $post_key ] ) );
            update_post_meta( $post_id, $meta_key, $value );
        }
    }
} );
