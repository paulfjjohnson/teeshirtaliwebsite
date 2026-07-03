<?php
/**
 * Blank Apparel Catalog — admin-managed brand cards.
 *
 * CPT `tsa_brand` replaces the hardcoded $brands array. Each post = one brand
 * card (Featured Image = card image; meta = type, category, colors, swatches,
 * note, detail URL). The grid (template-blank-apparel.php) and the per-brand
 * detail pages (tsa_get_brand_config) read from here, falling back to the
 * built-in defaults when no brands have been added yet.
 *
 * Phase 2 (separate): "Import from S&S" search + browse tool.
 */
defined( 'ABSPATH' ) || exit;

/** Make the plugin's garment CPT publicly viewable at /apparel/{slug}/ (no plugin edit). */
add_filter( 'register_post_type_args', function ( $args, $post_type ) {
    if ( 'configurator_garment' === $post_type ) {
        $args['public']              = true;
        $args['publicly_queryable']  = true;
        $args['exclude_from_search'] = true;
        $args['has_archive']         = false;
        $args['rewrite']             = [ 'slug' => 'apparel', 'with_front' => false ];
        // Enable the content editor so the apparel-page Description can be
        // filled by hand (and auto-populated from S&S on import).
        $args['supports'] = array_values( array_unique( array_merge( (array) ( $args['supports'] ?? [ 'title' ] ), [ 'editor' ] ) ) );
    }
    return $args;
}, 20, 2 );

/** Catalog filter categories (the grid tabs). */
function tsa_brand_categories() {
    return [
        'tees'        => 'Tees',
        'fleece'      => 'Hoodies & Fleece',
        'performance' => 'Performance',
        'headwear'    => 'Headwear',
    ];
}

/* ─── CPT ──────────────────────────────────────────────────────────── */
add_action( 'init', function () {
    register_post_type( 'tsa_brand', [
        'labels' => [
            'name'          => 'Blank Apparel',
            'singular_name' => 'Brand',
            'menu_name'     => 'Blank Apparel',
            'add_new'       => 'Add Brand',
            'add_new_item'  => 'Add Brand',
            'edit_item'     => 'Edit Brand',
            'new_item'      => 'New Brand',
            'view_item'     => 'View Brand',
            'search_items'  => 'Search Brands',
            'all_items'     => 'All Brands',
        ],
        'public'             => true,
        'publicly_queryable' => true,
        'exclude_from_search'=> true,
        'show_ui'            => true,
        'show_in_menu'       => true,
        'has_archive'        => false,
        'rewrite'            => [ 'slug' => 'blank-apparel', 'with_front' => false ],
        'menu_icon'          => 'dashicons-tag',
        'menu_position'      => 26,
        'supports'           => [ 'title', 'thumbnail', 'page-attributes' ],
        'capability_type'    => 'post',
    ] );
} );

// Rename the Featured Image label to "Card Image" on this CPT.
add_filter( 'gettext', function ( $t, $orig ) {
    if ( $orig === 'Featured image' && get_post_type() === 'tsa_brand' ) return 'Card Image';
    return $t;
}, 10, 2 );

/* ─── Meta box ─────────────────────────────────────────────────────── */
add_action( 'add_meta_boxes', function () {
    add_meta_box( 'tsa_brand_details', 'Brand Card Details', 'tsa_brand_details_box', 'tsa_brand', 'normal', 'high' );
} );

function tsa_brand_details_box( $post ) {
    wp_nonce_field( 'tsa_brand_save', 'tsa_brand_nonce' );
    $type   = get_post_meta( $post->ID, '_tsa_brand_type', true );
    $cat    = get_post_meta( $post->ID, '_tsa_brand_category', true ) ?: 'tees';
    $colors = get_post_meta( $post->ID, '_tsa_brand_colors', true );
    $note   = get_post_meta( $post->ID, '_tsa_brand_note', true );
    $sw     = get_post_meta( $post->ID, '_tsa_brand_swatches', true );
    $url    = get_post_meta( $post->ID, '_tsa_brand_url', true );
    $ss     = get_post_meta( $post->ID, '_tsa_ss_brand', true );
    $sw_txt = is_array( $sw ) ? implode( "\n", $sw ) : '';
    ?>
    <p style="margin-top:0;color:#666">The card image is the <strong>Card Image</strong> box in the sidebar. These fields fill the rest of the card.</p>
    <table class="form-table"><tbody>
        <tr><th><label for="tsa_brand_type">Type label</label></th>
            <td><input type="text" id="tsa_brand_type" name="tsa_brand_type" value="<?php echo esc_attr( $type ); ?>" class="regular-text" placeholder="e.g. Premium Tees &amp; Tanks"></td></tr>
        <tr><th><label for="tsa_brand_category">Category (filter tab)</label></th>
            <td><select id="tsa_brand_category" name="tsa_brand_category">
                <?php foreach ( tsa_brand_categories() as $k => $l ) printf( '<option value="%s"%s>%s</option>', esc_attr( $k ), selected( $cat, $k, false ), esc_html( $l ) ); ?>
            </select></td></tr>
        <tr><th><label for="tsa_brand_colors">Color count</label></th>
            <td><input type="number" id="tsa_brand_colors" name="tsa_brand_colors" value="<?php echo esc_attr( $colors ); ?>" class="small-text" min="0"> <span class="description">used in the footer note</span></td></tr>
        <tr><th><label for="tsa_brand_note">Footer note</label></th>
            <td><input type="text" id="tsa_brand_note" name="tsa_brand_note" value="<?php echo esc_attr( $note ); ?>" class="large-text" placeholder="e.g. 100+ colors · Unisex, women's, youth"></td></tr>
        <tr><th><label for="tsa_brand_swatches">Swatch colors</label></th>
            <td><textarea id="tsa_brand_swatches" name="tsa_brand_swatches" rows="5" class="code" style="width:200px" placeholder="#000000&#10;#ffffff&#10;#c0392b"><?php echo esc_textarea( $sw_txt ); ?></textarea>
                <p class="description">One hex per line (4–6 recommended).</p></td></tr>
        <tr><th><label for="tsa_brand_url">Detail page URL</label></th>
            <td><input type="text" id="tsa_brand_url" name="tsa_brand_url" value="<?php echo esc_attr( $url ); ?>" class="regular-text" placeholder="/blank-apparel/bella-canvas/">
                <p class="description">Where the card links. Leave blank to auto-use the brand slug.</p></td></tr>
    </tbody></table>
    <?php if ( $ss ) : ?><p class="description">Imported from S&amp;S Activewear: <code><?php echo esc_html( $ss ); ?></code></p><?php endif;
}

add_action( 'save_post_tsa_brand', 'tsa_brand_save_details' );
function tsa_brand_save_details( $post_id ) {
    if ( ! isset( $_POST['tsa_brand_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_brand_nonce'] ) ), 'tsa_brand_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    update_post_meta( $post_id, '_tsa_brand_type', sanitize_text_field( wp_unslash( $_POST['tsa_brand_type'] ?? '' ) ) );
    $cat = sanitize_key( $_POST['tsa_brand_category'] ?? 'tees' );
    update_post_meta( $post_id, '_tsa_brand_category', array_key_exists( $cat, tsa_brand_categories() ) ? $cat : 'tees' );
    update_post_meta( $post_id, '_tsa_brand_colors', absint( $_POST['tsa_brand_colors'] ?? 0 ) );
    update_post_meta( $post_id, '_tsa_brand_note', sanitize_text_field( wp_unslash( $_POST['tsa_brand_note'] ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_brand_url', esc_url_raw( wp_unslash( $_POST['tsa_brand_url'] ?? '' ) ) );
    $sw = array_values( array_filter( array_map( 'sanitize_text_field', preg_split( '/\r\n|\r|\n/', (string) ( $_POST['tsa_brand_swatches'] ?? '' ) ) ) ) );
    update_post_meta( $post_id, '_tsa_brand_swatches', $sw );
}

/* ─── Feed brand cards into the garment "Brand" dropdown ───────────────
   The plugin's garment editor Brand <select> is a fixed list (built-ins +
   already-saved values) with no free-text, and it doesn't know about the
   tsa_brand cards. So a newly added brand (e.g. "Pennant Sportswear") can't
   be assigned to a garment — leaving its brand page empty. Merge every
   published brand-card title into the options so new brands are selectable. */
add_filter( 'ac_garment_brand_options', function ( $brands ) {
    $titles = get_posts( [
        'post_type'   => 'tsa_brand',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby'     => 'title',
        'order'       => 'ASC',
        'fields'      => 'ids',
    ] );
    foreach ( $titles as $pid ) {
        $name = get_the_title( $pid );
        if ( $name !== '' ) $brands[] = $name;
    }
    return array_values( array_unique( (array) $brands ) );
} );

/* ─── Grid data source ─────────────────────────────────────────────── */
/** Brand cards from the CPT, normalised to the grid's array shape. Empty array if none. */
function tsa_get_catalog_brands() {
    $posts = get_posts( [ 'post_type' => 'tsa_brand', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => [ 'menu_order' => 'ASC', 'title' => 'ASC' ] ] );
    if ( ! $posts ) return [];
    $out = [];
    foreach ( $posts as $p ) {
        $sw  = get_post_meta( $p->ID, '_tsa_brand_swatches', true );
        $url = get_post_meta( $p->ID, '_tsa_brand_url', true );
        $out[] = [
            'name'     => $p->post_title,
            'slug'     => $p->post_name,
            'url'      => $url ?: home_url( '/blank-apparel/' . $p->post_name . '/' ),
            'type'     => get_post_meta( $p->ID, '_tsa_brand_type', true ),
            'note'     => get_post_meta( $p->ID, '_tsa_brand_note', true ),
            'category' => get_post_meta( $p->ID, '_tsa_brand_category', true ) ?: 'tees',
            'swatches' => is_array( $sw ) ? $sw : [],
            'image'    => get_the_post_thumbnail_url( $p->ID, 'medium' ) ?: '',
            'colors'   => get_post_meta( $p->ID, '_tsa_brand_colors', true ),
        ];
    }
    return $out;
}

/** Single brand (for the detail page) by slug, from the CPT. Null if not found. */
function tsa_get_catalog_brand_by_slug( $slug ) {
    $q = get_posts( [ 'post_type' => 'tsa_brand', 'name' => $slug, 'post_status' => 'publish', 'numberposts' => 1 ] );
    if ( ! $q ) return null;
    $p  = $q[0];
    $sw = get_post_meta( $p->ID, '_tsa_brand_swatches', true );
    return [
        'name'     => $p->post_title,
        'slug'     => $p->post_name,
        'type'     => get_post_meta( $p->ID, '_tsa_brand_type', true ),
        'desc'     => $p->post_content ? wp_strip_all_tags( $p->post_content ) : '',
        'colors'   => get_post_meta( $p->ID, '_tsa_brand_colors', true ),
        'styles'   => '',
        'swatches' => is_array( $sw ) ? $sw : [],
        'image'    => get_the_post_thumbnail_url( $p->ID, 'large' ) ?: '',
    ];
}

/* ─── Derived brand attributes (live from the brand's garments) ──────── */
/**
 * Live attributes for a brand from its active configurator garments:
 * unique color count, type labels (Tees/Fleece/…), a swatch sample, style count.
 * Returns [ 'colors'=>int, 'swatches'=>[hex,…], 'types'=>[label,…], 'styles'=>int ].
 */
function tsa_brand_derived_attrs( $brand_name ) {
    // Tolerant brand match (case/space/punctuation-insensitive) with a title
    // fallback — mirrors the grid query in template-brand-catalog.php.
    $norm   = function ( $s ) { return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $s ) ); };
    $target = $norm( $brand_name );
    $all    = get_posts( [ 'post_type' => 'configurator_garment', 'post_status' => 'publish', 'numberposts' => -1 ] );
    $garments = ( $target === '' ) ? [] : array_filter( $all, function ( $g ) use ( $norm, $target ) {
        $b = $norm( get_post_meta( $g->ID, '_ac_brand', true ) );
        if ( $b !== '' ) return $b === $target;
        return strpos( $norm( get_the_title( $g ) ), $target ) === 0;
    } );
    $hexes = []; $types = []; $count_styles = 0;
    foreach ( $garments as $g ) {
        $flag = get_post_meta( $g->ID, '_tsa_in_catalog', true );
        $is_import = (bool) get_post_meta( $g->ID, '_ac_ss_style_id', true );
        // Shown unless explicitly hidden ('0'); imports default visible, manual default hidden.
        if ( $flag !== '1' && ! ( $flag === '' && $is_import ) ) continue;
        $count_styles++;
        $styles = get_post_meta( $g->ID, '_ac_styles', true );
        if ( is_array( $styles ) ) foreach ( $styles as $st ) {
            if ( empty( $st['colors'] ) || ! is_array( $st['colors'] ) ) continue;
            foreach ( $st['colors'] as $c ) { if ( ! empty( $c['hex'] ) ) $hexes[ strtolower( $c['hex'] ) ] = $c['hex']; }
        }
        $t = strtolower( $g->post_title );
        if ( strpos( $t, 'hood' ) !== false || strpos( $t, 'fleece' ) !== false || strpos( $t, 'crew' ) !== false || strpos( $t, 'sweat' ) !== false ) $types['Fleece'] = 1;
        elseif ( strpos( $t, 'tank' ) !== false ) $types['Tanks'] = 1;
        elseif ( strpos( $t, 'cap' ) !== false || strpos( $t, 'hat' ) !== false || strpos( $t, 'beanie' ) !== false || strpos( $t, 'trucker' ) !== false ) $types['Headwear'] = 1;
        else $types['Tees'] = 1;
    }
    return [
        'colors'   => count( $hexes ),
        'swatches' => array_slice( array_values( $hexes ), 0, 6 ),
        'types'    => array_keys( $types ),
        'styles'   => $count_styles,
    ];
}

/* ─── Garment toggle: "Show in Blank Catalog" (By Catalog visibility) ──
   Shared garments, two independent switches:
     • _ac_is_active (plugin "Active" checkbox) → By Design configurator picker
     • _tsa_in_catalog (this box)               → By Catalog brand pages + grid */
add_action( 'add_meta_boxes', function () {
    add_meta_box( 'tsa_garment_catalog', 'Blank Apparel Catalog', 'tsa_garment_catalog_box', 'configurator_garment', 'side', 'default' );
} );
function tsa_garment_catalog_box( $post ) {
    wp_nonce_field( 'tsa_garment_catalog_save', 'tsa_garment_catalog_nonce' );
    $val     = get_post_meta( $post->ID, '_tsa_in_catalog', true );
    $checked = ( $val === '1' ) || ( $val === '' && get_post_meta( $post->ID, '_ac_ss_style_id', true ) );
    ?>
    <label style="display:flex; gap:8px; align-items:flex-start;">
        <input type="checkbox" name="tsa_in_catalog" value="1" <?php checked( $checked ); ?> style="margin-top:2px;">
        <span><strong>Show in the Blank Apparel catalog</strong></span>
    </label>
    <p class="description" style="margin-top:8px;">Controls the <strong>By&nbsp;Catalog</strong> workflow (brand pages + the <code>/blank-apparel/</code> grid). The configurator's <strong>“Active”</strong> setting controls the separate <strong>By&nbsp;Design</strong> picker — the two are independent.</p>
    <?php
}
add_action( 'save_post_configurator_garment', function ( $post_id ) {
    if ( ! isset( $_POST['tsa_garment_catalog_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_garment_catalog_nonce'] ) ), 'tsa_garment_catalog_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    update_post_meta( $post_id, '_tsa_in_catalog', isset( $_POST['tsa_in_catalog'] ) ? '1' : '0' );
} );

/* ─── Size Chart (Specs) — optional per-garment table ─────────────────────
   Pipe-delimited; renders on the apparel page under the Description only when
   filled. The API doesn't expose measurement charts, so this is manual. */
add_action( 'add_meta_boxes', function () {
    add_meta_box( 'tsa_garment_sizechart', 'Size Chart (Specs)', 'tsa_garment_sizechart_box', 'configurator_garment', 'normal', 'low' );
} );
function tsa_garment_sizechart_box( $post ) {
    wp_nonce_field( 'tsa_garment_sizechart_save', 'tsa_garment_sizechart_nonce' );
    $val = get_post_meta( $post->ID, '_tsa_size_chart', true );
    ?>
    <p class="description" style="margin-top:0">Optional. One row per line; columns separated by <code>|</code>. The first line is the header row — leave its first cell empty for the measurement-label column. Shows on the apparel page only when filled.</p>
    <textarea name="tsa_size_chart" rows="8" class="large-text code" placeholder="|XS|S|M|L|XL|2XL|3XL|4XL|5XL&#10;Body Length|27|28|29|30|31|32|33|34|35&#10;Chest Width|16 1/2|18|20|22|24|26|28|30|32&#10;Neck Size|6|6 1/2|6 3/4|7|7 1/2|7 3/4|7 3/4|8|"><?php echo esc_textarea( $val ); ?></textarea>
    <?php
}
add_action( 'save_post_configurator_garment', function ( $post_id ) {
    if ( ! isset( $_POST['tsa_garment_sizechart_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_garment_sizechart_nonce'] ) ), 'tsa_garment_sizechart_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    update_post_meta( $post_id, '_tsa_size_chart', sanitize_textarea_field( (string) wp_unslash( $_POST['tsa_size_chart'] ?? '' ) ) );
} );
