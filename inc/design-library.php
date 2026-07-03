<?php
/**
 * TSA Design Library System
 *
 * Registers the tsa_design CPT, taxonomies, category tree,
 * Store Coordinator role, admin access restrictions,
 * auto-store assignment, and admin notifications.
 *
 * Included via functions.php:
 *   require_once get_stylesheet_directory() . '/inc/design-library.php';
 */

defined( 'ABSPATH' ) || exit;

/* ─── New / Featured design helpers ──────────────────────────────────── */

/** The "New for N days" window (days). */
function tsa_design_new_window() {
    return max( 0, (int) get_option( 'tsa_design_new_window_days', 30 ) );
}

/** Recompute `_design_new_until` from the override + publish date + window. */
function tsa_design_recompute_new_until( $post_id ) {
    $override = get_post_meta( $post_id, '_design_new_override', true );
    if ( $override === 'never' ) {
        $until = 0;
    } elseif ( $override === 'always' ) {
        $until = PHP_INT_MAX;
    } else { // auto
        $published = (int) get_post_time( 'U', true, $post_id );
        $until     = $published + ( tsa_design_new_window() * DAY_IN_SECONDS );
    }
    update_post_meta( $post_id, '_design_new_until', (string) $until );
    return $until;
}

/** Is this design currently "New"? */
function tsa_design_is_new( $post_id ) {
    return (int) get_post_meta( $post_id, '_design_new_until', true ) >= time();
}

/** Is this design Featured? */
function tsa_design_is_featured( $post_id ) {
    return get_post_meta( $post_id, '_design_featured', true ) === '1';
}

/* ─── Design Library settings (New window) ───────────────────────────── */
add_action( 'admin_init', function () {
    register_setting( 'tsa_design_settings', 'tsa_design_new_window_days', [
        'type' => 'integer', 'sanitize_callback' => 'absint', 'default' => 30,
    ] );
} );

add_action( 'admin_menu', function () {
    add_submenu_page( 'edit.php?post_type=tsa_design', 'Design Settings', 'Settings',
        'manage_options', 'tsa-design-settings', 'tsa_design_settings_page' );
} );

function tsa_design_settings_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;
    ?>
    <div class="wrap">
        <h1>Design Library Settings</h1>
        <form method="post" action="options.php">
            <?php settings_fields( 'tsa_design_settings' ); ?>
            <table class="form-table"><tbody>
                <tr>
                    <th scope="row"><label for="tsa_design_new_window_days">Mark designs as &ldquo;New&rdquo; for</label></th>
                    <td>
                        <input type="number" min="0" class="small-text" id="tsa_design_new_window_days"
                               name="tsa_design_new_window_days" value="<?php echo esc_attr( get_option( 'tsa_design_new_window_days', 30 ) ); ?>"> days
                        <p class="description">Designs added within this window show in the &ldquo;New&rdquo; collection automatically (then expire). Per-design overrides win.</p>
                    </td>
                </tr>
            </tbody></table>
            <?php submit_button(); ?>
        </form>
    </div>
    <?php
}

/** Recompute _design_new_until for every design (window change / one-time backfill). */
function tsa_design_recompute_all_new_until() {
    $ids = get_posts( [ 'post_type' => 'tsa_design', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] );
    foreach ( $ids as $id ) tsa_design_recompute_new_until( $id );
}
add_action( 'add_option_tsa_design_new_window_days',    'tsa_design_recompute_all_new_until' );
add_action( 'update_option_tsa_design_new_window_days', 'tsa_design_recompute_all_new_until' );

/** One-time backfill so existing designs get a New-until immediately. */
add_action( 'admin_init', function () {
    if ( get_option( 'tsa_design_new_until_backfilled' ) ) return;
    tsa_design_recompute_all_new_until();
    update_option( 'tsa_design_new_until_backfilled', 1 );
} );

/* ─── "Customize It" request (from a design card popup) ──────────────────
   Emails the design-upload notification recipient with the customer's
   request. Free; works logged-in or out. */
add_action( 'wp_ajax_tsa_design_customize',        'tsa_design_customize_ajax' );
add_action( 'wp_ajax_nopriv_tsa_design_customize', 'tsa_design_customize_ajax' );
function tsa_design_customize_ajax() {
    check_ajax_referer( 'tsa_design_customize', 'nonce' );
    $design_id = absint( $_POST['design_id'] ?? 0 );
    $email     = sanitize_email( wp_unslash( $_POST['email'] ?? '' ) );
    $desc      = sanitize_textarea_field( wp_unslash( $_POST['description'] ?? '' ) );
    if ( ! is_email( $email ) ) wp_send_json_error( [ 'message' => 'Please enter a valid email.' ] );
    if ( $desc === '' )         wp_send_json_error( [ 'message' => 'Please describe the customization you need.' ] );

    $title = $design_id ? get_the_title( $design_id ) : '(unspecified)';
    $link  = $design_id ? get_edit_post_link( $design_id, 'raw' ) : '';
    $to    = function_exists( 'tsa_form_recipient' ) ? tsa_form_recipient( 'design' ) : get_option( 'admin_email' );

    $body  = "New design customization request.\n\n";
    $body .= "Design: {$title}" . ( $design_id ? " (#{$design_id})" : '' ) . "\n";
    if ( $link ) { $body .= "Admin link: {$link}\n"; }
    $body .= "From: {$email}\n\n";
    $body .= "Customization requested:\n{$desc}\n";

    // Save to the Requests inbox so the request is never lost if email is
    // delayed or spam-filtered (fires the SMS-alert hook too).
    if ( function_exists( 'tsa_record_request' ) ) {
        tsa_record_request( [
            'type'    => 'design',
            'email'   => $email,
            'message' => 'Design: ' . $title . ( $design_id ? " (#{$design_id})" : '' ) . "\n\n" . $desc,
        ] );
    }

    $headers = [ 'Reply-To: ' . $email ];
    $sent = wp_mail( $to, 'Design customization request: ' . $title, $body, $headers );
    if ( ! $sent ) wp_send_json_error( [ 'message' => 'We could not send the request — please contact us directly.' ] );
    wp_send_json_success( [ 'ok' => true ] );
}

/* ══════════════════════════════════════════════════════════
   A. CUSTOM POST TYPE — tsa_design
══════════════════════════════════════════════════════════ */
add_action( 'init', 'tsa_register_design_cpt' );
function tsa_register_design_cpt() {
    $labels = [
        'name'               => 'Designs',
        'singular_name'      => 'Design',
        'menu_name'          => 'Design Library',
        'add_new'            => 'Upload Design',
        'add_new_item'       => 'Upload New Design',
        'edit_item'          => 'Edit Design',
        'new_item'           => 'New Design',
        'view_item'          => 'View Design',
        'search_items'       => 'Search Designs',
        'not_found'          => 'No designs found',
        'not_found_in_trash' => 'No designs in trash',
        'all_items'          => 'All Designs',
    ];

    register_post_type( 'tsa_design', [
        'labels'              => $labels,
        'public'              => false,
        'show_ui'             => true,
        'show_in_menu'        => true,
        'show_in_rest'        => true,
        'menu_position'       => 25,
        'menu_icon'           => 'dashicons-art',
        'supports'            => [ 'title', 'thumbnail', 'author' ],
        'capability_type'     => [ 'tsa_design', 'tsa_designs' ],
        'map_meta_cap'        => true,
        'has_archive'         => false,
        'rewrite'             => false,
    ] );
}

/* ══════════════════════════════════════════════════════════
   B. TAXONOMIES
══════════════════════════════════════════════════════════ */
add_action( 'init', 'tsa_register_design_taxonomies' );
function tsa_register_design_taxonomies() {

    // Which store this design belongs to
    register_taxonomy( 'tsa_design_store', 'tsa_design', [
        'labels'            => [
            'name'          => 'Store',
            'singular_name' => 'Store',
            'all_items'     => 'All Stores',
            'edit_item'     => 'Edit Store',
            'add_new_item'  => 'Add Store',
        ],
        'hierarchical'      => false,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => false,
    ] );

    // Hierarchical category tree (Sports / Clubs / Events / etc.)
    register_taxonomy( 'tsa_design_category', 'tsa_design', [
        'labels'            => [
            'name'              => 'Category',
            'singular_name'     => 'Category',
            'all_items'         => 'All Categories',
            'parent_item'       => 'Parent Category',
            'parent_item_colon' => 'Parent Category:',
            'edit_item'         => 'Edit Category',
            'add_new_item'      => 'Add Category',
        ],
        'hierarchical'      => true,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => false,
    ] );

    // Print method tags (dtf / screen-print / embroidery)
    register_taxonomy( 'tsa_design_method', 'tsa_design', [
        'labels'            => [
            'name'          => 'Print Method',
            'singular_name' => 'Print Method',
            'all_items'     => 'All Methods',
        ],
        'hierarchical'      => false,
        'show_ui'           => true,
        'show_in_rest'      => true,
        'show_admin_column' => true,
        'rewrite'           => false,
    ] );
}

/* ══════════════════════════════════════════════════════════
   C. CATEGORY TREE POPULATION (runs once per version key)
══════════════════════════════════════════════════════════ */
add_action( 'init', 'tsa_populate_design_category_tree', 20 );
function tsa_populate_design_category_tree() {
    if ( get_option( 'tsa_design_cats_v3' ) ) return;

    /* ── School categories ── */
    $school_tree = [
        'Sports' => [
            'Football', 'Basketball — Boys', 'Basketball — Girls',
            'Baseball', 'Softball', 'Soccer — Boys', 'Soccer — Girls',
            'Track & Field', 'Cross Country', 'Tennis', 'Golf',
            'Swimming & Diving', 'Wrestling', 'Volleyball',
            'Cheerleading', 'Dance Team', 'Bowling', 'Powerlifting',
            'Flag Football', 'Lacrosse', 'Gymnastics', 'Hockey',
        ],
        'Performing Arts' => [
            'Marching Band', 'Color Guard', 'Winter Guard',
            'Concert Band', 'Jazz Band', 'Choir / Chorus',
            'Orchestra', 'Theater / Drama', 'Show Choir',
            'Step Team', 'Majorettes',
        ],
        'Student Organizations' => [
            'Student Council / SGA', 'National Honor Society (NHS)',
            'Beta Club', 'JROTC', 'Key Club', 'Interact Club',
            'FBLA', 'DECA', 'FFA', 'HOSA', 'FCCLA',
            'Robotics Club', 'Art Club', 'Science Club',
            'Math Club', 'Book Club / Literary',
            'French Club', 'Spanish Club', 'Debate',
        ],
        'School Events' => [
            'Homecoming', 'Prom / Formal', 'Graduation / Senior Class',
            'Spirit Week', 'Field Day', 'Founders Day / Anniversary',
            'Black History Month', 'Red Ribbon Week',
            'Teacher Appreciation', 'Fundraiser / Booster',
        ],
        'Faculty & Staff' => [
            'Teachers / Educators', 'Administration',
            'Support Staff', 'Athletic Coaches',
            'Fine Arts Faculty', 'Counselors',
        ],
    ];

    /* ── Team store categories ── */
    $team_tree = [
        'Team Gear' => [
            'Game Day', 'Practice & Training', 'Tournament / Travel',
            'Fan Gear', 'Coaching Staff', 'Alumni',
        ],
        'By Sport' => [
            'Baseball', 'Softball', 'Football', 'Basketball',
            'Soccer', 'Volleyball', 'Track & Field',
            'Swimming', 'Wrestling', 'Golf', 'Tennis', 'Other',
        ],
    ];

    /* ── Business store categories ── */
    $business_tree = [
        'Business' => [
            'Staff & Uniforms', 'Promotional / Marketing',
            'Client / Customer Gifts', 'Corporate Events',
            'Management / Executive', 'Seasonal / Holiday',
            'Trade Show', 'Employee Appreciation',
        ],
    ];

    /* ── General (TSA-wide) ── */
    $general_tree = [
        'General' => [
            'Typography / Wordmark', 'Sports Generic',
            'Motivational / Inspirational', 'Community / Local',
            'Seasonal', 'Custom / Text Only',
        ],
    ];

    /* ── Print methods ── */
    $methods = [ 'DTF Transfer', 'Screen Print', 'Embroidery' ];

    // Helper: insert parent → children
    $insert_tree = function( $tree ) {
        foreach ( $tree as $parent_name => $children ) {
            $parent = get_term_by( 'name', $parent_name, 'tsa_design_category' );
            if ( ! $parent ) {
                $result = wp_insert_term( $parent_name, 'tsa_design_category' );
                $parent_id = is_wp_error( $result ) ? 0 : $result['term_id'];
            } else {
                $parent_id = $parent->term_id;
            }
            if ( ! $parent_id ) continue;
            foreach ( $children as $child_name ) {
                if ( ! get_term_by( 'name', $child_name, 'tsa_design_category' ) ) {
                    wp_insert_term( $child_name, 'tsa_design_category', [ 'parent' => $parent_id ] );
                }
            }
        }
    };

    $insert_tree( $school_tree );
    $insert_tree( $team_tree );
    $insert_tree( $business_tree );
    $insert_tree( $general_tree );

    foreach ( $methods as $method ) {
        if ( ! get_term_by( 'name', $method, 'tsa_design_method' ) ) {
            wp_insert_term( $method, 'tsa_design_method' );
        }
    }

    update_option( 'tsa_design_cats_v3', true );
}

/* ══════════════════════════════════════════════════════════
   C2. CLEANUP — school-named categories → Store tag (one-time)

   A school is a Store ("who owns it"), not a design category ("what it is")
   — see docs/40-design-library.md. A "Dutchtown" tsa_design_category (with its
   designs) is the wrong model. This migration: for every category term whose
   slug/name matches a design Store (or a school record), it ensures the Store
   term exists, appends that Store to each of the category's designs, strips the
   bogus category from them, then deletes the now-empty term. Idempotent and
   guarded by an option; runs once on the next admin load. DESTRUCTIVE (deletes
   the matched category terms) — test on staging first.
══════════════════════════════════════════════════════════ */
add_action( 'admin_init', 'tsa_migrate_school_categories_to_stores' );
function tsa_migrate_school_categories_to_stores() {
    if ( get_option( 'tsa_school_cat_cleanup_v1' ) === 'done' ) return;
    if ( ! current_user_can( 'manage_options' ) ) return;
    if ( ! taxonomy_exists( 'tsa_design_category' ) || ! taxonomy_exists( 'tsa_design_store' ) ) return;

    $norm = static function ( $s ) { return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $s ) ); };

    // Owner key (normalised) → store slug. Sourced from existing Store terms and
    // the school directory records, so a school with no Store term yet still maps.
    $store_by_key = [];
    foreach ( (array) get_terms( [ 'taxonomy' => 'tsa_design_store', 'hide_empty' => false ] ) as $stt ) {
        if ( is_wp_error( $stt ) || ! is_object( $stt ) ) continue;
        $store_by_key[ $norm( $stt->slug ) ] = $stt->slug;
        $store_by_key[ $norm( $stt->name ) ] = $stt->slug;
    }
    if ( function_exists( 'tsa_school_store_records' ) ) {
        foreach ( (array) tsa_school_store_records() as $sr ) {
            $slug = sanitize_title( $sr['slug'] ?? '' );
            if ( $slug === '' ) continue;
            $store_by_key[ $norm( $slug ) ] = $slug;
            if ( ! empty( $sr['name'] ) ) { $store_by_key[ $norm( $sr['name'] ) ] = $slug; }
        }
    }
    if ( ! $store_by_key ) { update_option( 'tsa_school_cat_cleanup_v1', 'done' ); return; }

    $keep_schools = apply_filters( 'tsa_schools_category_slug', 'schools' );
    $cpts = array_values( array_filter( [ 'tsa_design', 'configurator_design' ], 'post_type_exists' ) ) ?: [ 'tsa_design' ];

    foreach ( (array) get_terms( [ 'taxonomy' => 'tsa_design_category', 'hide_empty' => false ] ) as $term ) {
        if ( is_wp_error( $term ) || ! is_object( $term ) ) continue;
        if ( $term->slug === $keep_schools ) continue; // the Schools nav category stays
        $store_slug = $store_by_key[ $norm( $term->slug ) ] ?? ( $store_by_key[ $norm( $term->name ) ] ?? '' );
        if ( $store_slug === '' ) continue;            // not a school/store-named category

        // Make sure the destination Store term exists.
        if ( ! term_exists( $store_slug, 'tsa_design_store' ) ) {
            $sname = function_exists( 'tsa_store_name' ) ? ( tsa_store_name( $store_slug ) ?: '' ) : '';
            if ( $sname === '' ) { $sname = ucwords( str_replace( '-', ' ', $store_slug ) ); }
            wp_insert_term( $sname, 'tsa_design_store', [ 'slug' => $store_slug ] );
        }

        // Re-home each design, then strip the bogus category from it.
        $ids = get_posts( [
            'post_type'   => $cpts,
            'post_status' => 'any',
            'numberposts' => -1,
            'fields'      => 'ids',
            'tax_query'   => [ [ 'taxonomy' => 'tsa_design_category', 'field' => 'term_id', 'terms' => (int) $term->term_id ] ],
        ] );
        foreach ( $ids as $did ) {
            wp_set_post_terms( $did, [ $store_slug ], 'tsa_design_store', true );        // append, keep other stores
            wp_remove_object_terms( $did, (int) $term->term_id, 'tsa_design_category' );  // drop the school category
        }

        wp_delete_term( (int) $term->term_id, 'tsa_design_category' );
        error_log( sprintf( 'TSA cleanup: moved %d designs from category "%s" → store "%s", deleted the category.', count( $ids ), $term->name, $store_slug ) );
    }

    update_option( 'tsa_school_cat_cleanup_v1', 'done' );
}

/* ══════════════════════════════════════════════════════════
   D. STORE COORDINATOR USER ROLE
══════════════════════════════════════════════════════════ */
add_action( 'init', 'tsa_register_coordinator_role' );
function tsa_register_coordinator_role() {
    if ( get_role( 'tsa_store_coordinator' ) ) return;

    add_role( 'tsa_store_coordinator', 'Store Coordinator', [
        'read'                        => true,
        'upload_files'                => true,
        // tsa_design CPT capabilities
        'edit_tsa_designs'            => true,
        'edit_tsa_design'             => true,
        'publish_tsa_designs'         => false,  // pending admin approval
        'delete_tsa_designs'          => true,
        'delete_tsa_design'           => true,
        'read_tsa_design'             => true,
        'read_private_tsa_designs'    => false,
        'edit_others_tsa_designs'     => false,
        'delete_others_tsa_designs'   => false,
    ] );
}

// Grant administrator all tsa_design capabilities
add_filter( 'user_has_cap', 'tsa_grant_admin_design_caps', 10, 3 );
function tsa_grant_admin_design_caps( $caps, $cap, $args ) {
    if ( isset( $caps['administrator'] ) && $caps['administrator'] ) {
        $design_caps = [
            'edit_tsa_designs', 'edit_tsa_design', 'publish_tsa_designs',
            'delete_tsa_designs', 'delete_tsa_design', 'read_tsa_design',
            'read_private_tsa_designs', 'edit_others_tsa_designs',
            'delete_others_tsa_designs', 'edit_published_tsa_designs',
            // Required to TRASH/DELETE published or private designs, edit private,
            // and create new ones (map_meta_cap maps delete_post → these).
            'delete_published_tsa_designs', 'delete_private_tsa_designs',
            'edit_private_tsa_designs', 'create_tsa_designs',
        ];
        foreach ( $design_caps as $c ) {
            $caps[ $c ] = true;
        }
    }
    return $caps;
}

/* ══════════════════════════════════════════════════════════
   E. RESTRICT COORDINATOR ADMIN ACCESS
══════════════════════════════════════════════════════════ */

// Only show their store's designs in WP list table
add_action( 'pre_get_posts', 'tsa_restrict_coordinator_designs' );
function tsa_restrict_coordinator_designs( $query ) {
    if ( ! is_admin() || ! $query->is_main_query() ) return;
    if ( current_user_can( 'administrator' ) ) return;

    $user = wp_get_current_user();
    if ( ! in_array( 'tsa_store_coordinator', (array) $user->roles, true ) ) return;
    if ( $query->get( 'post_type' ) !== 'tsa_design' ) return;

    $store_slug = get_user_meta( $user->ID, 'tsa_assigned_store', true );
    if ( $store_slug ) {
        $query->set( 'tax_query', [ [
            'taxonomy' => 'tsa_design_store',
            'field'    => 'slug',
            'terms'    => $store_slug,
        ] ] );
    }
}

// Remove unnecessary admin menu items for coordinators
add_action( 'admin_menu', 'tsa_simplify_coordinator_menu', 999 );
function tsa_simplify_coordinator_menu() {
    $user = wp_get_current_user();
    if ( ! in_array( 'tsa_store_coordinator', (array) $user->roles, true ) ) return;

    $remove = [
        'index.php',            // Dashboard
        'edit.php',             // Posts
        'upload.php',           // Media (they access via design meta box)
        'edit-comments.php',    // Comments
        'themes.php',           // Appearance
        'plugins.php',          // Plugins
        'users.php',            // Users
        'tools.php',            // Tools
        'options-general.php',  // Settings
        'woocommerce',          // WooCommerce
        'edit.php?post_type=page',
        'edit.php?post_type=product',
    ];

    foreach ( $remove as $item ) {
        remove_menu_page( $item );
    }
}

// Redirect coordinators away from dashboard to their design library
add_action( 'admin_init', 'tsa_redirect_coordinator_to_library' );
function tsa_redirect_coordinator_to_library() {
    $user = wp_get_current_user();
    if ( ! in_array( 'tsa_store_coordinator', (array) $user->roles, true ) ) return;

    $screen = get_current_screen();
    if ( $screen && $screen->id === 'dashboard' ) {
        wp_safe_redirect( admin_url( 'edit.php?post_type=tsa_design' ) );
        exit;
    }
}

// Block coordinators from accessing posts they don't own / aren't in their store
add_action( 'load-post.php', 'tsa_block_coordinator_wrong_store' );
function tsa_block_coordinator_wrong_store() {
    $user = wp_get_current_user();
    if ( ! in_array( 'tsa_store_coordinator', (array) $user->roles, true ) ) return;

    $post_id = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
    if ( ! $post_id ) return;

    $post = get_post( $post_id );
    if ( ! $post || $post->post_type !== 'tsa_design' ) {
        wp_safe_redirect( admin_url( 'edit.php?post_type=tsa_design' ) );
        exit;
    }

    $store_slug = get_user_meta( $user->ID, 'tsa_assigned_store', true );
    $post_stores = wp_get_post_terms( $post_id, 'tsa_design_store', [ 'fields' => 'slugs' ] );
    if ( $store_slug && ! in_array( $store_slug, $post_stores, true ) ) {
        wp_safe_redirect( admin_url( 'edit.php?post_type=tsa_design' ) );
        exit;
    }
}

/* ══════════════════════════════════════════════════════════
   F. AUTO-ASSIGN STORE ON UPLOAD
══════════════════════════════════════════════════════════ */
add_action( 'save_post_tsa_design', 'tsa_auto_assign_design_store', 10, 3 );
function tsa_auto_assign_design_store( $post_id, $post, $update ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;

    $user = wp_get_current_user();

    // Only auto-assign for coordinators (admins set store manually)
    if ( ! in_array( 'tsa_store_coordinator', (array) $user->roles, true ) ) return;

    $store_slug = get_user_meta( $user->ID, 'tsa_assigned_store', true );
    if ( ! $store_slug ) return;

    // Ensure the store term exists
    $term = get_term_by( 'slug', $store_slug, 'tsa_design_store' );
    if ( ! $term ) {
        $store_name = get_user_meta( $user->ID, 'tsa_store_display_name', true );
        $result = wp_insert_term( $store_name ?: $store_slug, 'tsa_design_store', [ 'slug' => $store_slug ] );
        $term_id = is_wp_error( $result ) ? 0 : $result['term_id'];
    } else {
        $term_id = $term->term_id;
    }

    if ( $term_id ) {
        // Don't overwrite if already set (allow admin edits)
        $current = wp_get_post_terms( $post_id, 'tsa_design_store', [ 'fields' => 'ids' ] );
        if ( empty( $current ) ) {
            wp_set_post_terms( $post_id, [ $term_id ], 'tsa_design_store', false );
        }
    }
}

/* ══════════════════════════════════════════════════════════
   G. ADMIN NOTIFICATION ON NEW DESIGN UPLOAD
══════════════════════════════════════════════════════════ */
add_action( 'transition_post_status', 'tsa_notify_admin_new_design', 10, 3 );
function tsa_notify_admin_new_design( $new_status, $old_status, $post ) {
    if ( $post->post_type !== 'tsa_design' ) return;
    // Only fire on first save (from auto-draft / new)
    if ( ! in_array( $old_status, [ 'new', 'auto-draft' ], true ) ) return;

    $uploader   = get_userdata( $post->post_author );
    $store_slug = get_user_meta( $post->post_author, 'tsa_assigned_store', true );
    $store_name = get_user_meta( $post->post_author, 'tsa_store_display_name', true ) ?: $store_slug;
    $store_type = get_user_meta( $post->post_author, 'tsa_store_type', true ) ?: 'unknown';

    $categories = wp_get_post_terms( $post->ID, 'tsa_design_category', [ 'fields' => 'names' ] );
    $methods    = wp_get_post_terms( $post->ID, 'tsa_design_method', [ 'fields' => 'names' ] );

    $body  = "A new design has been uploaded to the TSA Design Library.\n\n";
    $body .= "Title:       " . get_the_title( $post->ID ) . "\n";
    $body .= "Store:       " . $store_name . " (" . ucfirst( $store_type ) . ")\n";
    $body .= "Uploaded by: " . $uploader->display_name . " <" . $uploader->user_email . ">\n";
    $body .= "Status:      " . $new_status . "\n";
    $body .= "Categories:  " . ( $categories ? implode( ', ', $categories ) : 'None set' ) . "\n";
    $body .= "Methods:     " . ( $methods ? implode( ', ', $methods ) : 'None set' ) . "\n\n";
    $body .= "Review & approve: " . admin_url( 'post.php?post=' . $post->ID . '&action=edit' ) . "\n\n";
    $body .= "Design Library: " . admin_url( 'edit.php?post_type=tsa_design' );

    wp_mail(
        function_exists( 'tsa_form_recipient' ) ? tsa_form_recipient( 'design' ) : get_option( 'admin_email' ),
        '[TSA] New Design Upload — ' . get_the_title( $post->ID ) . ' (' . $store_name . ')',
        $body,
        [ 'Content-Type: text/plain; charset=UTF-8' ]
    );
}

/* ══════════════════════════════════════════════════════════
   H. META BOXES — DESIGN DETAILS
══════════════════════════════════════════════════════════ */
add_action( 'add_meta_boxes', 'tsa_design_meta_boxes' );
function tsa_design_meta_boxes() {
    add_meta_box(
        'tsa_design_store_info',
        'Store Assignment',
        'tsa_design_store_info_cb',
        'tsa_design',
        'side',
        'high'
    );
    add_meta_box(
        'tsa_design_files',
        'Design Files',
        'tsa_design_files_cb',
        'tsa_design',
        'normal',
        'high'
    );
    add_meta_box(
        'tsa_design_details',
        'Design Details',
        'tsa_design_details_cb',
        'tsa_design',
        'normal',
        'default'
    );
    add_meta_box(
        'tsa_design_promote',
        'Featured & New',
        'tsa_design_promote_cb',
        'tsa_design',
        'side',
        'default'
    );
}

/* ── Featured & New meta box ── */
function tsa_design_promote_cb( $post ) {
    wp_nonce_field( 'tsa_design_promote_nonce', 'tsa_design_promote_nonce' );
    $featured = get_post_meta( $post->ID, '_design_featured', true ) === '1';
    $override = get_post_meta( $post->ID, '_design_new_override', true );
    ?>
    <p><label><input type="checkbox" name="tsa_design_featured" value="1" <?php checked( $featured ); ?>> <strong>Featured</strong></label></p>
    <p>
        <label for="tsa_design_new_override"><strong>New status</strong></label><br>
        <select id="tsa_design_new_override" name="tsa_design_new_override" style="width:100%;margin-top:4px">
            <option value=""       <?php selected( $override, '' ); ?>>Auto (by date added)</option>
            <option value="always" <?php selected( $override, 'always' ); ?>>Always New</option>
            <option value="never"  <?php selected( $override, 'never' ); ?>>Not New</option>
        </select>
        <span class="description"><?php echo tsa_design_is_new( $post->ID ) ? 'Currently shows as New.' : 'Not currently New.'; ?></span>
    </p>
    <?php
}

/* ── Store Info meta box ── */
function tsa_design_store_info_cb( $post ) {
    wp_nonce_field( 'tsa_design_store_nonce', 'tsa_design_store_nonce' );

    $current_user = wp_get_current_user();
    $is_coord     = in_array( 'tsa_store_coordinator', (array) $current_user->roles, true );
    $store_slug   = $is_coord
                    ? get_user_meta( $current_user->ID, 'tsa_assigned_store', true )
                    : get_post_meta( $post->ID, '_design_store_slug', true );
    $store_name   = $is_coord
                    ? ( get_user_meta( $current_user->ID, 'tsa_store_display_name', true ) ?: $store_slug )
                    : $store_slug;
    $store_type   = $is_coord
                    ? get_user_meta( $current_user->ID, 'tsa_store_type', true )
                    : get_post_meta( $post->ID, '_design_store_type', true );

    if ( $is_coord ) :
    ?>
    <p style="font-weight:700;margin-bottom:4px"><?php echo esc_html( $store_name ); ?></p>
    <p style="font-size:12px;color:#666"><?php echo esc_html( ucfirst( $store_type ) ); ?> Store — auto-assigned on upload</p>
    <input type="hidden" name="tsa_design_store_slug" value="<?php echo esc_attr( $store_slug ); ?>">
    <input type="hidden" name="tsa_design_store_type" value="<?php echo esc_attr( $store_type ); ?>">
    <?php else : ?>
    <div style="background:#fff8e6;border:1px solid #e8d28a;border-left:3px solid #d8a85f;border-radius:6px;padding:9px 11px;margin:0 0 10px;font-size:11.5px;line-height:1.55;color:#5b4a1e">
        <strong>Category vs. Store &mdash; two different things:</strong><br>
        &bull; <strong>Design Categories</strong> box = <em>what the design is</em>, e.g. <code>By Sport &rarr; Football</code>.<br>
        &bull; <strong>Store</strong> (below) = <em>who owns it</em>, e.g. <code>dutchtown</code>.<br>
        Set <u>both</u>. Don&rsquo;t make a category per school &mdash; every school reuses the same shared categories, filtered by its Store.
    </div>
    <p style="font-size:12px;color:#666;margin-bottom:6px">Assign this design to a store.</p>
    <label style="display:block;font-size:12px;font-weight:600;margin-bottom:3px">Store Slug</label>
    <input type="text" name="tsa_design_store_slug" value="<?php echo esc_attr( $store_slug ); ?>"
           style="width:100%" placeholder="e.g. dutchtown">
    <label style="display:block;font-size:12px;font-weight:600;margin:8px 0 3px">Store Type</label>
    <select name="tsa_design_store_type" style="width:100%">
        <?php foreach ( [ '' => 'Select…', 'school' => 'School', 'team' => 'Team', 'business' => 'Business', 'general' => 'General (All Stores)' ] as $val => $label ) : ?>
        <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $store_type, $val ); ?>><?php echo esc_html( $label ); ?></option>
        <?php endforeach; ?>
    </select>
    <?php endif;
}

/* ── Design Files meta box ── */
function tsa_design_files_cb( $post ) {
    wp_nonce_field( 'tsa_design_files_nonce', 'tsa_design_files_nonce' );

    $print_file   = get_post_meta( $post->ID, '_design_print_file_url', true );
    $preview_url  = get_post_meta( $post->ID, '_design_preview_url', true );
    $print_file_id = get_post_meta( $post->ID, '_design_print_file_id', true );
    $preview_id    = get_post_meta( $post->ID, '_design_preview_id', true );
    ?>
    <table style="width:100%;border-spacing:0">
        <tr>
            <td style="width:50%;padding-right:12px;vertical-align:top">
                <p style="font-weight:600;font-size:12px;margin:0 0 6px">Hi-Res Print File <span style="color:#c00">*</span></p>
                <p style="font-size:11px;color:#666;margin:0 0 8px">AI · EPS · PDF · SVG · PNG (300dpi+)</p>
                <div id="tsa-print-file-wrap" style="border:2px dashed #ddd;border-radius:6px;padding:12px;text-align:center;min-height:60px">
                    <?php if ( $print_file ) : ?>
                    <p style="margin:0;font-size:12px;color:#333;word-break:break-all"><?php echo esc_html( basename( $print_file ) ); ?></p>
                    <?php endif; ?>
                </div>
                <input type="hidden" id="tsa_design_print_file_url" name="tsa_design_print_file_url" value="<?php echo esc_attr( $print_file ); ?>">
                <input type="hidden" id="tsa_design_print_file_id"  name="tsa_design_print_file_id"  value="<?php echo esc_attr( $print_file_id ); ?>">
                <button type="button" class="button tsa-media-picker" data-target="print_file" style="margin-top:8px;width:100%">
                    <?php echo $print_file ? 'Replace File' : 'Upload / Select File'; ?>
                </button>
                <?php if ( $print_file ) : ?>
                <button type="button" class="button tsa-media-clear" data-target="print_file" style="margin-top:4px;width:100%">Remove File</button>
                <?php endif; ?>
            </td>
            <td style="width:50%;padding-left:12px;vertical-align:top">
                <p style="font-weight:600;font-size:12px;margin:0 0 6px">Preview / Mockup Image</p>
                <p style="font-size:11px;color:#666;margin:0 0 8px">JPG · PNG · shown in gallery</p>
                <div id="tsa-preview-wrap" style="border:2px dashed #ddd;border-radius:6px;padding:4px;text-align:center;min-height:80px;display:flex;align-items:center;justify-content:center">
                    <?php if ( $preview_url ) : ?>
                    <img src="<?php echo esc_url( $preview_url ); ?>" style="max-width:100%;max-height:120px;border-radius:4px">
                    <?php else : ?>
                    <span style="font-size:12px;color:#999">No preview yet</span>
                    <?php endif; ?>
                </div>
                <input type="hidden" id="tsa_design_preview_url" name="tsa_design_preview_url" value="<?php echo esc_attr( $preview_url ); ?>">
                <input type="hidden" id="tsa_design_preview_id"  name="tsa_design_preview_id"  value="<?php echo esc_attr( $preview_id ); ?>">
                <button type="button" class="button tsa-media-picker" data-target="preview" style="margin-top:8px;width:100%">
                    <?php echo $preview_url ? 'Replace Preview' : 'Upload Preview Image'; ?>
                </button>
                <?php if ( $preview_url ) : ?>
                <button type="button" class="button tsa-media-clear" data-target="preview" style="margin-top:4px;width:100%">Remove Preview</button>
                <?php endif; ?>
            </td>
        </tr>
    </table>

    <script>
    jQuery(function($){
        function openPicker(target) {
            var frame = wp.media({
                title: target === 'preview' ? 'Select Preview Image' : 'Select Print File',
                multiple: false,
                library: target === 'preview' ? { type: 'image' } : {}
            });
            frame.on('select', function(){
                var att = frame.state().get('selection').first().toJSON();
                if (target === 'preview') {
                    $('#tsa_design_preview_url').val(att.url);
                    $('#tsa_design_preview_id').val(att.id);
                    $('#tsa-preview-wrap').html('<img src="'+att.url+'" style="max-width:100%;max-height:120px;border-radius:4px">');
                } else {
                    $('#tsa_design_print_file_url').val(att.url);
                    $('#tsa_design_print_file_id').val(att.id);
                    $('#tsa-print-file-wrap').html('<p style="margin:0;font-size:12px;color:#333;word-break:break-all">'+att.filename+'</p>');
                }
            });
            frame.open();
        }
        $(document).on('click','.tsa-media-picker',function(e){
            e.preventDefault();
            openPicker($(this).data('target'));
        });
        $(document).on('click','.tsa-media-clear',function(e){
            e.preventDefault();
            var t = $(this).data('target');
            if (t === 'preview') {
                $('#tsa_design_preview_url, #tsa_design_preview_id').val('');
                $('#tsa-preview-wrap').html('<span style="font-size:12px;color:#999">No preview yet</span>');
            } else {
                $('#tsa_design_print_file_url, #tsa_design_print_file_id').val('');
                $('#tsa-print-file-wrap').html('');
            }
        });
    });
    </script>
    <?php
}

/* ── Design Details meta box ── */
function tsa_design_details_cb( $post ) {
    wp_nonce_field( 'tsa_design_details_nonce', 'tsa_design_details_nonce' );

    $colors  = get_post_meta( $post->ID, '_design_colors', true ) ?: '';
    $notes   = get_post_meta( $post->ID, '_design_notes', true ) ?: '';
    $tags    = wp_get_post_terms( $post->ID, 'tsa_design_method', [ 'fields' => 'slugs' ] );

    $all_methods = get_terms( [ 'taxonomy' => 'tsa_design_method', 'hide_empty' => false ] );
    ?>
    <table style="width:100%;border-spacing:0 8px">
        <tr>
            <td style="vertical-align:top;padding-right:16px;width:50%">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Print Methods</label>
                <?php foreach ( $all_methods as $m ) : ?>
                <label style="display:flex;align-items:center;gap:6px;margin-bottom:4px;font-size:13px">
                    <input type="checkbox" name="tsa_design_methods[]"
                           value="<?php echo esc_attr( $m->slug ); ?>"
                           <?php checked( in_array( $m->slug, $tags, true ) ); ?>>
                    <?php echo esc_html( $m->name ); ?>
                </label>
                <?php endforeach; ?>
            </td>
            <td style="vertical-align:top;padding-left:16px;width:50%">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Colors in Design <span style="color:#666;font-weight:400">(hex, comma-separated)</span></label>
                <input type="text" name="tsa_design_colors" value="<?php echo esc_attr( $colors ); ?>"
                       style="width:100%" placeholder="#ffffff, #000000, #592c82">
            </td>
        </tr>
        <tr>
            <td colspan="2">
                <label style="display:block;font-size:12px;font-weight:600;margin-bottom:4px">Design Notes <span style="color:#666;font-weight:400">(visible to admin only)</span></label>
                <textarea name="tsa_design_notes" style="width:100%;min-height:60px;resize:vertical" placeholder="Any notes about this design — print constraints, color limitations, etc."><?php echo esc_textarea( $notes ); ?></textarea>
            </td>
        </tr>
    </table>
    <?php
}

/* ── Save all meta box data ── */
add_action( 'save_post_tsa_design', 'tsa_save_design_meta', 20, 2 );
function tsa_save_design_meta( $post_id, $post ) {
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( wp_is_post_revision( $post_id ) ) return;

    // Store info
    if ( isset( $_POST['tsa_design_store_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_design_store_nonce'] ) ), 'tsa_design_store_nonce' ) ) {
        $store_slug = sanitize_key( $_POST['tsa_design_store_slug'] ?? '' );
        $store_type = sanitize_key( $_POST['tsa_design_store_type'] ?? '' );
        update_post_meta( $post_id, '_design_store_slug', $store_slug );
        update_post_meta( $post_id, '_design_store_type', $store_type );

        if ( $store_slug ) {
            $term = get_term_by( 'slug', $store_slug, 'tsa_design_store' );
            if ( $term ) {
                wp_set_post_terms( $post_id, [ $term->term_id ], 'tsa_design_store', false );
            }
        }
    }

    // Files
    if ( isset( $_POST['tsa_design_files_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_design_files_nonce'] ) ), 'tsa_design_files_nonce' ) ) {
        update_post_meta( $post_id, '_design_print_file_url', esc_url_raw( $_POST['tsa_design_print_file_url'] ?? '' ) );
        update_post_meta( $post_id, '_design_print_file_id',  (int) ( $_POST['tsa_design_print_file_id']  ?? 0 ) );
        update_post_meta( $post_id, '_design_preview_url',    esc_url_raw( $_POST['tsa_design_preview_url']    ?? '' ) );
        update_post_meta( $post_id, '_design_preview_id',     (int) ( $_POST['tsa_design_preview_id']     ?? 0 ) );
    }

    // Details
    if ( isset( $_POST['tsa_design_details_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_design_details_nonce'] ) ), 'tsa_design_details_nonce' ) ) {
        update_post_meta( $post_id, '_design_colors', sanitize_text_field( $_POST['tsa_design_colors'] ?? '' ) );
        update_post_meta( $post_id, '_design_notes',  sanitize_textarea_field( $_POST['tsa_design_notes'] ?? '' ) );

        $methods = isset( $_POST['tsa_design_methods'] )
                    ? array_map( 'sanitize_key', (array) $_POST['tsa_design_methods'] )
                    : [];
        $term_ids = [];
        foreach ( $methods as $slug ) {
            $t = get_term_by( 'slug', $slug, 'tsa_design_method' );
            if ( $t ) $term_ids[] = $t->term_id;
        }
        wp_set_post_terms( $post_id, $term_ids, 'tsa_design_method', false );
    }

    // Featured / New
    if ( isset( $_POST['tsa_design_promote_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_design_promote_nonce'] ) ), 'tsa_design_promote_nonce' ) ) {
        update_post_meta( $post_id, '_design_featured', isset( $_POST['tsa_design_featured'] ) ? '1' : '0' );
        $ov = sanitize_key( $_POST['tsa_design_new_override'] ?? '' );
        update_post_meta( $post_id, '_design_new_override', in_array( $ov, [ 'always', 'never' ], true ) ? $ov : '' );
        tsa_design_recompute_new_until( $post_id );
    }
}

/* ══════════════════════════════════════════════════════════
   I. USER PROFILE — STORE ASSIGNMENT FIELDS
   Visible on WP Admin → Users → Edit User (admin only)
══════════════════════════════════════════════════════════ */
add_action( 'show_user_profile', 'tsa_coordinator_profile_fields' );
add_action( 'edit_user_profile', 'tsa_coordinator_profile_fields' );
function tsa_coordinator_profile_fields( $user ) {
    if ( ! current_user_can( 'administrator' ) ) return;

    $assigned_store = get_user_meta( $user->ID, 'tsa_assigned_store', true );
    $store_name     = get_user_meta( $user->ID, 'tsa_store_display_name', true );
    $store_type     = get_user_meta( $user->ID, 'tsa_store_type', true );
    ?>
    <h3>TSA Store Coordinator Settings</h3>
    <table class="form-table">
        <tr>
            <th><label for="tsa_assigned_store">Assigned Store Slug</label></th>
            <td>
                <input type="text" id="tsa_assigned_store" name="tsa_assigned_store"
                       value="<?php echo esc_attr( $assigned_store ); ?>" class="regular-text"
                       placeholder="e.g. dutchtown, muddawgs">
                <p class="description">Must match the store's WooCommerce/taxonomy slug exactly.</p>
            </td>
        </tr>
        <tr>
            <th><label for="tsa_store_display_name">Store Display Name</label></th>
            <td>
                <input type="text" id="tsa_store_display_name" name="tsa_store_display_name"
                       value="<?php echo esc_attr( $store_name ); ?>" class="regular-text"
                       placeholder="e.g. Dutchtown High School">
            </td>
        </tr>
        <tr>
            <th><label for="tsa_store_type">Store Type</label></th>
            <td>
                <select id="tsa_store_type" name="tsa_store_type">
                    <?php foreach ( [ '' => 'Select…', 'school' => 'School', 'team' => 'Team', 'business' => 'Business' ] as $val => $label ) : ?>
                    <option value="<?php echo esc_attr( $val ); ?>" <?php selected( $store_type, $val ); ?>><?php echo esc_html( $label ); ?></option>
                    <?php endforeach; ?>
                </select>
                <p class="description">Controls which design categories are shown on upload.</p>
            </td>
        </tr>
    </table>
    <?php wp_nonce_field( 'tsa_coordinator_profile_nonce', 'tsa_coordinator_profile_nonce' ); ?>
    <?php
}

add_action( 'personal_options_update', 'tsa_save_coordinator_profile_fields' );
add_action( 'edit_user_profile_update', 'tsa_save_coordinator_profile_fields' );
function tsa_save_coordinator_profile_fields( $user_id ) {
    if ( ! current_user_can( 'administrator' ) ) return;
    if ( ! isset( $_POST['tsa_coordinator_profile_nonce'] ) ) return;
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_coordinator_profile_nonce'] ) ), 'tsa_coordinator_profile_nonce' ) ) return;

    update_user_meta( $user_id, 'tsa_assigned_store',     sanitize_key( $_POST['tsa_assigned_store']     ?? '' ) );
    update_user_meta( $user_id, 'tsa_store_display_name', sanitize_text_field( $_POST['tsa_store_display_name'] ?? '' ) );
    update_user_meta( $user_id, 'tsa_store_type',         sanitize_key( $_POST['tsa_store_type']         ?? '' ) );
}

/* ══════════════════════════════════════════════════════════
   J. CATEGORY FILTER IN META BOX — SHOW RELEVANT TREE
   Hides irrelevant top-level categories based on store type.
   Runs via admin_footer JS on the design edit screen.
══════════════════════════════════════════════════════════ */
add_action( 'admin_footer-post.php', 'tsa_filter_category_meta_box_js' );
add_action( 'admin_footer-post-new.php', 'tsa_filter_category_meta_box_js' );
function tsa_filter_category_meta_box_js() {
    $screen = get_current_screen();
    if ( ! $screen || $screen->post_type !== 'tsa_design' ) return;

    $user       = wp_get_current_user();
    $store_type = get_user_meta( $user->ID, 'tsa_store_type', true );
    if ( ! $store_type && current_user_can( 'administrator' ) ) return; // Admin sees all

    // Map store type → relevant parent category names to SHOW
    $show_parents = [
        'school'   => [ 'Sports', 'Performing Arts', 'Student Organizations', 'School Events', 'Faculty & Staff', 'General' ],
        'team'     => [ 'Team Gear', 'By Sport', 'General' ],
        'business' => [ 'Business', 'General' ],
    ];
    $visible = isset( $show_parents[ $store_type ] ) ? $show_parents[ $store_type ] : [];
    if ( empty( $visible ) ) return;

    $visible_json = wp_json_encode( $visible );
    ?>
    <script>
    jQuery(function($){
        var show = <?php echo $visible_json; ?>;
        // tsa_design_category meta box uses .categorychecklist
        $('#tsa_design_categorychecklist > li').each(function(){
            var label = $(this).find('label').first().text().trim();
            if (show.indexOf(label) === -1) {
                $(this).hide();
            }
        });
    });
    </script>
    <?php
}

/* ══════════════════════════════════════════════════════════
   K. REST API ENDPOINT — Design search for front-end gallery
══════════════════════════════════════════════════════════ */
add_action( 'rest_api_init', 'tsa_register_design_library_route' );
function tsa_register_design_library_route() {
    register_rest_route( 'tsa/v1', '/designs', [
        'methods'             => 'GET',
        'callback'            => 'tsa_design_library_api',
        'permission_callback' => '__return_true',
        'args'                => [
            'store'    => [ 'sanitize_callback' => 'sanitize_key' ],
            'category' => [ 'sanitize_callback' => 'sanitize_text_field' ], // comma-separated slugs
            'method'   => [ 'sanitize_callback' => 'sanitize_text_field' ], // comma-separated slugs
            'search'   => [ 'sanitize_callback' => 'sanitize_text_field' ],
            'sort'     => [ 'sanitize_callback' => 'sanitize_key' ],
            'page'     => [ 'sanitize_callback' => 'absint' ],
            'per_page' => [ 'sanitize_callback' => 'absint' ],
            'featured' => [ 'sanitize_callback' => 'absint' ],
            'new'      => [ 'sanitize_callback' => 'absint' ],
        ],
    ] );
}

function tsa_design_library_api( $request ) {
    $args = [
        'post_type'      => 'tsa_design',
        'post_status'    => 'publish',
        'posts_per_page' => min( (int) ( $request['per_page'] ?? 24 ), 48 ),
        'paged'          => max( 1, (int) ( $request['page'] ?? 1 ) ),
        'tax_query'      => [],
    ];

    if ( $request['search'] ) {
        $args['s'] = $request['search'];
    }

    /* ── Store scoping ─────────────────────────────────────────────
       Public library (no store param) shows ONLY Main TSA designs.
       A store context (?store=slug) shows that store PLUS Main TSA
       shared designs. Store-exclusive art never leaks to the public
       page. "Main TSA" = the store term with slug 'tsa' (filterable). */
    $main_store  = apply_filters( 'tsa_design_main_store_slug', 'tsa' );
    $store       = $request['store'] ?? '';
    $store_terms = ( $store && $store !== $main_store ) ? [ $store, $main_store ] : [ $main_store ];
    $args['tax_query'][] = [
        'taxonomy' => 'tsa_design_store',
        'field'    => 'slug',
        'terms'    => $store_terms,
    ];

    // Category / method are comma-separated slugs → OR within each facet (operator IN).
    $cats = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', (string) ( $request['category'] ?? '' ) ) ) ) );
    if ( $cats ) {
        $args['tax_query'][] = [ 'taxonomy' => 'tsa_design_category', 'field' => 'slug', 'terms' => array_values( $cats ), 'include_children' => true, 'operator' => 'IN' ];
    }
    $meths = array_filter( array_map( 'sanitize_key', array_map( 'trim', explode( ',', (string) ( $request['method'] ?? '' ) ) ) ) );
    if ( $meths ) {
        $args['tax_query'][] = [ 'taxonomy' => 'tsa_design_method', 'field' => 'slug', 'terms' => array_values( $meths ), 'operator' => 'IN' ];
    }

    // Smart collections (Piece 1): featured + new
    $args['meta_query'] = $args['meta_query'] ?? [];
    if ( (int) ( $request['featured'] ?? 0 ) === 1 ) {
        $args['meta_query'][] = [ 'key' => '_design_featured', 'value' => '1' ];
    }
    if ( (int) ( $request['new'] ?? 0 ) === 1 ) {
        $args['meta_query'][] = [ 'key' => '_design_new_until', 'value' => time(), 'compare' => '>=', 'type' => 'NUMERIC' ];
    }

    /* ── Sort ──────────────────────────────────────────────────── */
    switch ( sanitize_key( $request['sort'] ?? 'newest' ) ) {
        case 'oldest': $args['orderby'] = 'date';  $args['order'] = 'ASC';  break;
        case 'az':     $args['orderby'] = 'title'; $args['order'] = 'ASC';  break;
        case 'za':     $args['orderby'] = 'title'; $args['order'] = 'DESC'; break;
        default:       $args['orderby'] = 'date';  $args['order'] = 'DESC'; break; // newest
    }

    $query   = new WP_Query( $args );
    $designs = [];

    foreach ( $query->posts as $post ) {
        $preview = get_post_meta( $post->ID, '_design_preview_url', true );
        if ( ! $preview ) {
            $thumb_id = get_post_thumbnail_id( $post->ID );
            $preview  = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
        }
        $store_names = wp_get_post_terms( $post->ID, 'tsa_design_store',    [ 'fields' => 'names' ] );
        $cats     = wp_get_post_terms( $post->ID, 'tsa_design_category', [ 'fields' => 'names' ] );
        $methods  = wp_get_post_terms( $post->ID, 'tsa_design_method',   [ 'fields' => 'names' ] );

        $designs[] = [
            'id'          => $post->ID,
            'title'       => get_the_title( $post->ID ),
            'preview'     => $preview,
            'store'       => $store_names,
            'categories'  => $cats,
            'methods'     => $methods,
            'colors'      => get_post_meta( $post->ID, '_design_colors', true ),
            'config_url'  => home_url( '/configurator/?design_id=' . $post->ID . ( $store ? '&store=' . rawurlencode( $store ) : '' ) ),
            'quote_url'   => home_url( '/request-a-quote/?cat=apparel&design_id=' . $post->ID ),
            'is_new'      => tsa_design_is_new( $post->ID ),
            'featured'    => tsa_design_is_featured( $post->ID ),
        ];
    }

    return rest_ensure_response( [
        'designs'     => $designs,
        'total'       => (int) $query->found_posts,
        'total_pages' => (int) $query->max_num_pages,
        'page'        => (int) ( $request['page'] ?? 1 ),
    ] );
}
