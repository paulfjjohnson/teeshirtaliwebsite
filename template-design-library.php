<?php
/**
 * Template Name: TSA Design Library
 *
 * Filterable gallery of all approved designs.
 * Designs load via /wp-json/tsa/v1/designs REST endpoint.
 * Assign to: /design-library/
 */

defined( 'ABSPATH' ) || exit;

/* ── Build filter data ── */
$all_stores  = get_terms( [ 'taxonomy' => 'tsa_design_store',    'hide_empty' => true, 'orderby' => 'name' ] );
// Top-level categories always show (so the filter is visible even before
// designs are tagged); child categories below stay hide_empty so they appear
// as designs get assigned.
$all_cats    = get_terms( [ 'taxonomy' => 'tsa_design_category', 'hide_empty' => false, 'orderby' => 'name', 'parent' => 0 ] );
$all_methods = get_terms( [ 'taxonomy' => 'tsa_design_method',   'hide_empty' => true ] );

/* A. Hide school/store-named categories. A school is a STORE ("who owns it"),
   surfaced via the Schools nav card → store link — never its own flat design
   category (docs/40-design-library.md). Drop any top-level category whose
   slug/name matches a design Store term; keep the 'schools' nav category (handled
   below). This filters both the sidebar list and the landing cards, which share
   $all_cats. The B migration removes the underlying terms; this is the display
   backstop for any that get re-created. */
if ( ! empty( $all_cats ) && ! is_wp_error( $all_cats ) ) {
    $tsa_norm_key  = static function ( $s ) { return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $s ) ); };
    $tsa_owner_key = [];
    foreach ( (array) get_terms( [ 'taxonomy' => 'tsa_design_store', 'hide_empty' => false ] ) as $tsa_stt ) {
        if ( is_wp_error( $tsa_stt ) || ! is_object( $tsa_stt ) ) { continue; }
        $tsa_owner_key[ $tsa_norm_key( $tsa_stt->slug ) ] = true;
        $tsa_owner_key[ $tsa_norm_key( $tsa_stt->name ) ] = true;
    }
    $tsa_keep_cat = apply_filters( 'tsa_schools_category_slug', 'schools' );
    $all_cats = array_values( array_filter( $all_cats, static function ( $t ) use ( $tsa_owner_key, $tsa_keep_cat, $tsa_norm_key ) {
        if ( $t->slug === $tsa_keep_cat ) { return true; }
        return ! isset( $tsa_owner_key[ $tsa_norm_key( $t->slug ) ] ) && ! isset( $tsa_owner_key[ $tsa_norm_key( $t->name ) ] );
    } ) );
}

$active_store = sanitize_key( $_GET['store'] ?? '' );
$active_cat   = sanitize_text_field( wp_unslash( $_GET['cat'] ?? '' ) ); // may be comma-separated

/* ── Category landing cards (name + scoped design count + a preview) ──
   Counts are scoped to the SAME designs the grid will show (Main TSA, or the
   active store + Main TSA) and include child categories — so a card's count
   matches exactly what clicking it reveals. One light query per top-level
   category; categories with zero designs are skipped. */
$main_store  = apply_filters( 'tsa_design_main_store_slug', 'tsa' );
$store_terms = ( $active_store && $active_store !== $main_store ) ? [ $active_store, $main_store ] : [ $main_store ];

// Scoped count + a representative preview for one category slug (include_children).
$tsa_cat_stat = static function ( $slug ) use ( $store_terms ) {
    $cq = new WP_Query( [
        'post_type' => 'tsa_design', 'post_status' => 'publish', 'posts_per_page' => 1,
        'no_found_rows' => false, 'orderby' => 'date', 'order' => 'DESC',
        'tax_query' => [
            [ 'taxonomy' => 'tsa_design_store',    'field' => 'slug', 'terms' => $store_terms ],
            [ 'taxonomy' => 'tsa_design_category', 'field' => 'slug', 'terms' => [ $slug ], 'include_children' => true ],
        ],
    ] );
    $preview = '';
    if ( ! empty( $cq->posts ) ) {
        $pid     = $cq->posts[0]->ID;
        $preview = get_post_meta( $pid, '_design_preview_url', true );
        if ( ! $preview ) { $tid = get_post_thumbnail_id( $pid ); $preview = $tid ? wp_get_attachment_image_url( $tid, 'medium' ) : ''; }
    }
    wp_reset_postdata();
    return [ 'count' => (int) $cq->found_posts, 'preview' => $preview ];
};

// The user's real "Schools" tsa_design_category is a NAVIGATION category: rather
// than listing designs, its card drills into the canonical school directory.
// Identify it by slug (filterable) and source its card from the directory below.
$schools_cat_slug = apply_filters( 'tsa_schools_category_slug', 'schools' );

// Schools directory (canonical source) — drives the Schools nav card + drill.
$schools_flat = function_exists( 'tsa_school_records_all' ) ? tsa_school_records_all() : [];
if ( ! $schools_flat && function_exists( 'tsa_school_directory' ) ) { // fallback to the public directory
    foreach ( tsa_school_directory() as $grp ) { foreach ( $grp as $s ) { $schools_flat[] = $s; } }
}
$schools_count   = count( $schools_flat );
$schools_cat_name = 'Schools';   // overwritten with the real term name if present
$has_schools_nav  = false;       // true once the Schools nav card is added

// Build the category tree: top-level cards, each with its (non-empty) children.
$cat_cards = [];
if ( ! empty( $all_cats ) && ! is_wp_error( $all_cats ) ) {
    foreach ( $all_cats as $parent ) {
        // Schools = nav category: ignore its own designs, drill into the directory.
        if ( $parent->slug === $schools_cat_slug ) {
            if ( $schools_count < 1 ) { continue; }   // nothing to drill into
            $schools_cat_name = $parent->name;
            $has_schools_nav  = true;
            $cat_cards[] = [
                'slug' => $parent->slug, 'name' => $parent->name, 'count' => $schools_count,
                'preview' => '', 'children' => [], 'is_schools' => true,
            ];
            continue;
        }
        $stat = $tsa_cat_stat( $parent->slug );
        if ( $stat['count'] < 1 ) { continue; }
        $children    = [];
        $child_terms = get_terms( [ 'taxonomy' => 'tsa_design_category', 'parent' => $parent->term_id, 'hide_empty' => false, 'orderby' => 'name' ] );
        if ( ! is_wp_error( $child_terms ) ) {
            foreach ( $child_terms as $ch ) {
                $cstat = $tsa_cat_stat( $ch->slug );
                if ( $cstat['count'] < 1 ) { continue; }
                $children[] = [ 'slug' => $ch->slug, 'name' => $ch->name, 'count' => $cstat['count'], 'preview' => $cstat['preview'] ];
            }
        }
        $cat_cards[] = [ 'slug' => $parent->slug, 'name' => $parent->name, 'count' => $stat['count'], 'preview' => $stat['preview'], 'children' => $children, 'is_schools' => false ];
    }
}

/* Smart-collection counts (New + Featured), scoped like the category cards. */
$collection_base = [ 'post_type' => 'tsa_design', 'post_status' => 'publish', 'fields' => 'ids', 'posts_per_page' => 1, 'no_found_rows' => false,
    'tax_query' => [ [ 'taxonomy' => 'tsa_design_store', 'field' => 'slug', 'terms' => $store_terms ] ] ];
$feat_q = new WP_Query( array_merge( $collection_base, [ 'meta_query' => [ [ 'key' => '_design_featured', 'value' => '1' ] ] ] ) );
$new_q  = new WP_Query( array_merge( $collection_base, [ 'meta_query' => [ [ 'key' => '_design_new_until', 'value' => time(), 'compare' => '>=', 'type' => 'NUMERIC' ] ] ] ) );
$featured_count = (int) $feat_q->found_posts;
$new_count      = (int) $new_q->found_posts;
wp_reset_postdata();

get_header();
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-dl-hero">
    <div class="tsa-sd-container">
        <div class="tsa-kicker tsa-kicker--pink">Ready-to-Order Artwork</div>
        <h1 class="tsa-dl-hero__title">Design Library</h1>
        <p class="tsa-dl-hero__sub">Browse hundreds of print-ready designs — exclusive to their store, organized by program, sport, and activity. Pick one, choose your apparel, and order.</p>
    </div>
</section>

<!-- ══════════════════════════════════════
     HOW DESIGNS WORK
══════════════════════════════════════ -->
<div class="tsa-dl-how">
    <div class="tsa-sd-container">
        <div class="tsa-dl-how__steps">
            <div class="tsa-dl-how__step">
                <span class="tsa-dl-how__num">1</span>
                <div>
                    <strong>Find Your Design</strong>
                    <span>Filter by store, program, or sport</span>
                </div>
            </div>
            <span class="tsa-dl-how__arrow">→</span>
            <div class="tsa-dl-how__step">
                <span class="tsa-dl-how__num">2</span>
                <div>
                    <strong>Choose Your Apparel</strong>
                    <span>Select garments, colors &amp; sizes</span>
                </div>
            </div>
            <span class="tsa-dl-how__arrow">→</span>
            <div class="tsa-dl-how__step">
                <span class="tsa-dl-how__num">3</span>
                <div>
                    <strong>Add to Cart</strong>
                    <span>Checkout instantly or request a quote</span>
                </div>
            </div>
        </div>
    </div>
</div>

<!-- ══════════════════════════════════════
     FILTERS + GALLERY
══════════════════════════════════════ -->
<section class="tsa-dl-body">
    <div class="tsa-sd-container">
        <div class="tsa-dl-layout">

            <!-- SIDEBAR FILTERS -->
            <aside class="tsa-dl-sidebar" id="tsa-dl-sidebar">

                <div class="tsa-dl-sidebar__header">
                    <h2 class="tsa-dl-sidebar__title">Filters</h2>
                    <button class="tsa-dl-clear-all" id="tsa-dl-clear-all" type="button">Clear all</button>
                </div>

                <div class="tsa-dl-filter-group">
                    <label class="tsa-dl-filter-label" for="tsa-dl-search">Search</label>
                    <div class="tsa-dl-search-wrap">
                        <input type="text" id="tsa-dl-search" class="tsa-dl-search"
                               placeholder="Search designs…" autocomplete="off">
                        <span class="tsa-dl-search-icon">🔍</span>
                    </div>
                </div>

                <?php /* Store picker intentionally removed: the public Design Library
                         shows only Main TSA designs. Store-specific libraries are
                         reached via ?store=slug (linked from each store), which the
                         REST endpoint scopes to that store + Main TSA shared designs. */ ?>

                <?php if ( ! empty( $all_cats ) && ! is_wp_error( $all_cats ) ) : ?>
                <div class="tsa-dl-filter-group">
                    <button class="tsa-dl-filter-label tsa-dl-accordion-btn" type="button"
                            aria-expanded="true" aria-controls="tsa-dl-cats">
                        Category <span class="tsa-dl-chevron"></span>
                    </button>
                    <div class="tsa-dl-accordion-body" id="tsa-dl-cats">
                        <?php foreach ( $all_cats as $parent ) :
                            // Schools is a nav category (drills to the directory), not a design filter.
                            if ( $parent->slug === $schools_cat_slug ) { continue; }
                            $children = get_terms( [
                                'taxonomy'   => 'tsa_design_category',
                                'parent'     => $parent->term_id,
                                'hide_empty' => true,
                                'orderby'    => 'name',
                            ] );
                        ?>
                        <div class="tsa-dl-cat-group">
                            <label class="tsa-dl-radio-label tsa-dl-radio-label--parent">
                                <input type="checkbox" name="tsa_cat_filter[]"
                                       value="<?php echo esc_attr( $parent->slug ); ?>"
                                       class="tsa-dl-filter-check" data-filter="category">
                                <?php echo esc_html( $parent->name ); ?>
                                <span class="tsa-dl-count"><?php echo $parent->count; ?></span>
                            </label>
                            <?php if ( ! empty( $children ) && ! is_wp_error( $children ) ) : ?>
                            <div class="tsa-dl-cat-children">
                                <?php foreach ( $children as $child ) : ?>
                                <label class="tsa-dl-radio-label tsa-dl-radio-label--child">
                                    <input type="checkbox" name="tsa_cat_filter[]"
                                           value="<?php echo esc_attr( $child->slug ); ?>"
                                           class="tsa-dl-filter-check" data-filter="category">
                                    <?php echo esc_html( $child->name ); ?>
                                    <span class="tsa-dl-count"><?php echo $child->count; ?></span>
                                </label>
                                <?php endforeach; ?>
                            </div>
                            <?php endif; ?>
                        </div>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

                <?php if ( ! empty( $all_methods ) && ! is_wp_error( $all_methods ) ) : ?>
                <div class="tsa-dl-filter-group">
                    <button class="tsa-dl-filter-label tsa-dl-accordion-btn" type="button"
                            aria-expanded="false" aria-controls="tsa-dl-methods">
                        Print Method <span class="tsa-dl-chevron"></span>
                    </button>
                    <div class="tsa-dl-accordion-body" id="tsa-dl-methods" hidden>
                        <?php foreach ( $all_methods as $method ) : ?>
                        <label class="tsa-dl-radio-label">
                            <input type="checkbox" name="tsa_method_filter[]"
                                   value="<?php echo esc_attr( $method->slug ); ?>"
                                   class="tsa-dl-filter-check" data-filter="method">
                            <?php echo esc_html( $method->name ); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>
                <?php endif; ?>

            </aside>

            <!-- GALLERY COLUMN -->
            <div class="tsa-dl-gallery-col<?php echo ( $active_cat || empty( $cat_cards ) ) ? ' is-designs' : ''; ?>">

                <!-- CATEGORY LANDING (default view) -->
                <div class="tsa-dl-categories" id="tsa-dl-categories">
                    <?php if ( ! empty( $cat_cards ) ) : ?>

                    <div class="tsa-dl-cats-crumb" id="tsa-dl-cats-crumb" hidden>
                        <button type="button" class="tsa-dl-cats-back" data-cats-home>← All Categories</button>
                        <span class="tsa-dl-cats-crumb__sep">›</span>
                        <span id="tsa-dl-cats-crumb-name"></span>
                    </div>

                    <div class="tsa-dl-cats-head" id="tsa-dl-cats-head">
                        <h2>Browse by Category</h2>
                        <p>Pick a category to see its print-ready designs — or use the filters to narrow down.</p>
                    </div>

                    <div class="tsa-dl-cat-grid" id="tsa-dl-cat-top">
                        <?php if ( $new_count ) : ?>
                        <button type="button" class="tsa-dl-cat-card tsa-dl-cat-card--new" data-collection="new">
                            <span class="tsa-dl-cat-card__visual"><span class="tsa-dl-cat-card__noimg">🆕</span></span>
                            <span class="tsa-dl-cat-card__body">
                                <span class="tsa-dl-cat-card__name">New</span>
                                <span class="tsa-dl-cat-card__count"><?php echo (int) $new_count; ?> design<?php echo $new_count === 1 ? '' : 's'; ?></span>
                            </span>
                        </button>
                        <?php endif; ?>
                        <?php if ( $featured_count ) : ?>
                        <button type="button" class="tsa-dl-cat-card tsa-dl-cat-card--featured" data-collection="featured">
                            <span class="tsa-dl-cat-card__visual"><span class="tsa-dl-cat-card__noimg">★</span></span>
                            <span class="tsa-dl-cat-card__body">
                                <span class="tsa-dl-cat-card__name">Featured</span>
                                <span class="tsa-dl-cat-card__count"><?php echo (int) $featured_count; ?> design<?php echo $featured_count === 1 ? '' : 's'; ?></span>
                            </span>
                        </button>
                        <?php endif; ?>
                        <?php foreach ( $cat_cards as $cc ) : ?>
                        <?php if ( ! empty( $cc['is_schools'] ) ) : /* Schools nav card → drills into the school directory */ ?>
                        <button type="button" class="tsa-dl-cat-card tsa-dl-cat-card--schools tsa-dl-cat-card--has-children" data-parent="<?php echo esc_attr( $cc['slug'] ); ?>">
                            <span class="tsa-dl-cat-card__visual"><span class="tsa-dl-cat-card__noimg">🏫</span></span>
                            <span class="tsa-dl-cat-card__body">
                                <span class="tsa-dl-cat-card__name"><?php echo esc_html( $cc['name'] ); ?> <span class="tsa-dl-cat-card__chev">›</span></span>
                                <span class="tsa-dl-cat-card__count"><?php echo (int) $cc['count']; ?> school<?php echo $cc['count'] === 1 ? '' : 's'; ?></span>
                            </span>
                        </button>
                        <?php else :
                            $has_children = ! empty( $cc['children'] ); ?>
                        <button type="button" class="tsa-dl-cat-card<?php echo $has_children ? ' tsa-dl-cat-card--has-children' : ''; ?>"
                                <?php echo $has_children ? 'data-parent="' . esc_attr( $cc['slug'] ) . '"' : 'data-cat="' . esc_attr( $cc['slug'] ) . '"'; ?>>
                            <span class="tsa-dl-cat-card__visual tsa-autocontrast">
                                <?php if ( $cc['preview'] ) : ?><img src="<?php echo esc_url( $cc['preview'] ); ?>" alt="<?php echo esc_attr( $cc['name'] ); ?>" loading="lazy"><?php else : ?><span class="tsa-dl-cat-card__noimg">🎨</span><?php endif; ?>
                            </span>
                            <span class="tsa-dl-cat-card__body">
                                <span class="tsa-dl-cat-card__name"><?php echo esc_html( $cc['name'] ); ?><?php if ( $has_children ) : ?> <span class="tsa-dl-cat-card__chev">›</span><?php endif; ?></span>
                                <span class="tsa-dl-cat-card__count"><?php echo (int) $cc['count']; ?> design<?php echo $cc['count'] === 1 ? '' : 's'; ?></span>
                            </span>
                        </button>
                        <?php endif; ?>
                        <?php endforeach; ?>
                    </div>

                    <?php foreach ( $cat_cards as $cc ) : if ( empty( $cc['children'] ) ) continue; ?>
                    <div class="tsa-dl-cat-grid tsa-dl-subcats" id="tsa-dl-sub-<?php echo esc_attr( $cc['slug'] ); ?>" data-parent-name="<?php echo esc_attr( $cc['name'] ); ?>" hidden>
                        <button type="button" class="tsa-dl-cat-card tsa-dl-cat-card--all" data-cat="<?php echo esc_attr( $cc['slug'] ); ?>">
                            <span class="tsa-dl-cat-card__visual"><span class="tsa-dl-cat-card__noimg">▦</span></span>
                            <span class="tsa-dl-cat-card__body">
                                <span class="tsa-dl-cat-card__name">All <?php echo esc_html( $cc['name'] ); ?></span>
                                <span class="tsa-dl-cat-card__count"><?php echo (int) $cc['count']; ?> design<?php echo $cc['count'] === 1 ? '' : 's'; ?></span>
                            </span>
                        </button>
                        <?php foreach ( $cc['children'] as $ch ) : ?>
                        <button type="button" class="tsa-dl-cat-card" data-cat="<?php echo esc_attr( $ch['slug'] ); ?>">
                            <span class="tsa-dl-cat-card__visual tsa-autocontrast">
                                <?php if ( $ch['preview'] ) : ?><img src="<?php echo esc_url( $ch['preview'] ); ?>" alt="<?php echo esc_attr( $ch['name'] ); ?>" loading="lazy"><?php else : ?><span class="tsa-dl-cat-card__noimg">🎨</span><?php endif; ?>
                            </span>
                            <span class="tsa-dl-cat-card__body">
                                <span class="tsa-dl-cat-card__name"><?php echo esc_html( $ch['name'] ); ?></span>
                                <span class="tsa-dl-cat-card__count"><?php echo (int) $ch['count']; ?> design<?php echo $ch['count'] === 1 ? '' : 's'; ?></span>
                            </span>
                        </button>
                        <?php endforeach; ?>
                    </div>
                    <?php endforeach; ?>

                    <?php if ( $has_schools_nav ) : ?>
                    <style>
                    .tsa-dl-school-card .tsa-dl-cat-card__visual{height:104px;position:relative}
                    .tsa-dl-school-card__mono{width:56px;height:56px;border-radius:50%;background:rgba(255,255,255,.16);border:1px solid rgba(255,255,255,.4);display:flex;align-items:center;justify-content:center;font-size:23px;font-weight:700}
                    .tsa-dl-school-card__logo{max-width:64%;max-height:66px;object-fit:contain}
                    .tsa-dl-school-badge{position:absolute;top:8px;right:8px;font-size:11px;font-weight:700;padding:3px 9px;border-radius:999px;background:rgba(255,255,255,.93);line-height:1.3}
                    .tsa-dl-school-card__foot{margin-top:6px;font-size:13px;font-weight:700;display:inline-flex;align-items:center;gap:5px}
                    .tsa-dl-school-card--coming-soon{cursor:default}
                    .tsa-dl-school-card--coming-soon:hover{transform:none;box-shadow:none;border-color:var(--tsa-line)}
                    </style>
                    <div class="tsa-dl-cat-grid tsa-dl-subcats" id="tsa-dl-sub-<?php echo esc_attr( $schools_cat_slug ); ?>" data-parent-name="<?php echo esc_attr( $schools_cat_name ); ?>" hidden>
                        <?php foreach ( $schools_flat as $s ) :
                            $st_status  = $s['status'] ?? 'coming-soon';
                            if ( $st_status === 'hidden' ) continue; // Hidden = not shown anywhere; requests go to the CTA card below.
                            $sc_name    = $s['name'];
                            $sc_primary = ! empty( $s['color'] ) ? $s['color'] : ( function_exists( 'tsa_get_school_colors' ) ? ( tsa_get_school_colors( $sc_name )['primary'] ?? '#5D4777' ) : '#5D4777' );
                            $sc_txt     = function_exists( 'tsa_readable_text' ) ? tsa_readable_text( $sc_primary ) : '#ffffff';
                            $sc_logo    = $s['logo'] ?? '';
                            $sc_mascot  = trim( (string) ( $s['mascot'] ?? '' ) );
                            $sc_sub     = $sc_mascot !== '' ? 'Home of the ' . $sc_mascot : ( $s['level'] ?? '' );
                            $sc_initial = strtoupper( mb_substr( $sc_name, 0, 1 ) );
                            if ( $st_status === 'live' ) {
                                $sc_href = ! empty( $s['url'] ) ? home_url( $s['url'] ) : home_url( '/design-library/?store=' . rawurlencode( $s['slug'] ?? sanitize_title( $sc_name ) ) );
                                $sc_foot = 'Visit store'; $sc_badge = 'Live';        $sc_badge_c = '#1f8a4c'; $sc_tag = 'a';
                            } else { // coming-soon — shown, not shoppable, no per-card request
                                $sc_href = ''; $sc_foot = 'Coming soon'; $sc_badge = 'Coming soon'; $sc_badge_c = '#9a6b00'; $sc_tag = 'div';
                            }
                        ?>
                        <<?php echo $sc_tag; ?> class="tsa-dl-cat-card tsa-dl-school-card tsa-dl-school-card--<?php echo esc_attr( $st_status ); ?>"<?php if ( $sc_tag === 'a' ) : ?> href="<?php echo esc_url( $sc_href ); ?>"<?php endif; ?>>
                            <span class="tsa-dl-cat-card__visual" style="background:<?php echo esc_attr( $sc_primary ); ?>;color:<?php echo esc_attr( $sc_txt ); ?>">
                                <?php if ( $sc_logo ) : ?>
                                    <img class="tsa-dl-school-card__logo" src="<?php echo esc_url( $sc_logo ); ?>" alt="<?php echo esc_attr( $sc_name ); ?> logo" loading="lazy">
                                <?php else : ?>
                                    <span class="tsa-dl-school-card__mono"><?php echo esc_html( $sc_initial ); ?></span>
                                <?php endif; ?>
                                <span class="tsa-dl-school-badge" style="color:<?php echo esc_attr( $sc_badge_c ); ?>"><?php echo esc_html( $sc_badge ); ?></span>
                            </span>
                            <span class="tsa-dl-cat-card__body">
                                <span class="tsa-dl-cat-card__name"><?php echo esc_html( $sc_name ); ?></span>
                                <?php if ( $sc_sub !== '' ) : ?><span class="tsa-dl-cat-card__count"><?php echo esc_html( $sc_sub ); ?></span><?php endif; ?>
                                <span class="tsa-dl-school-card__foot" style="color:<?php echo $st_status === 'coming-soon' ? 'var(--tsa-muted)' : 'var(--tsa-pink)'; ?>"><?php echo esc_html( $sc_foot ); ?><?php if ( $sc_tag === 'a' ) : ?> &rarr;<?php endif; ?></span>
                            </span>
                        </<?php echo $sc_tag; ?>>
                        <?php endforeach; ?>
                        <?php /* Always-visible request CTA — decoupled from per-school status, so any customer can ask for a store. */ ?>
                        <a class="tsa-dl-cat-card tsa-dl-school-card tsa-dl-school-card--request" href="<?php echo esc_url( home_url( '/request-a-store/?type=school' ) ); ?>">
                            <span class="tsa-dl-cat-card__visual" style="background:var(--tsa-pink-soft,#fbeaf0)">
                                <span class="tsa-dl-school-card__mono" style="background:#fff;border:1px dashed var(--tsa-pink,#d4537e);color:var(--tsa-pink,#d4537e)">+</span>
                            </span>
                            <span class="tsa-dl-cat-card__body">
                                <span class="tsa-dl-cat-card__name">Don&rsquo;t see your school?</span>
                                <span class="tsa-dl-cat-card__count">Tell us and we&rsquo;ll set it up</span>
                                <span class="tsa-dl-school-card__foot" style="color:var(--tsa-pink,#d4537e)">Request a store &rarr;</span>
                            </span>
                        </a>
                    </div>
                    <?php endif; ?>
                    <?php else : ?>
                    <div class="tsa-dl-empty" style="display:block">
                        <span class="tsa-dl-empty__icon">🎨</span>
                        <h3>Designs are on the way</h3>
                        <p>Our library is being stocked. <a href="<?php echo esc_url( home_url( '/request-a-quote/?cat=design' ) ); ?>">Request a custom design</a> in the meantime.</p>
                    </div>
                    <?php endif; ?>
                </div>

                <div class="tsa-dl-results-bar" id="tsa-dl-results-bar">
                    <button type="button" class="tsa-dl-back" id="tsa-dl-back">← All Categories</button>
                    <span class="tsa-dl-results-count" id="tsa-dl-count">Loading…</span>
                    <div class="tsa-dl-active-filters" id="tsa-dl-active-filters"></div>
                    <label class="tsa-dl-sort" style="margin-left:auto;display:inline-flex;align-items:center;gap:8px;font-size:13px;color:var(--text-muted);white-space:nowrap">
                        Sort
                        <select id="tsa-dl-sort" style="padding:7px 12px;border:1px solid var(--border-strong);border-radius:var(--radius-md);background:#fff;font:inherit;color:var(--text-primary);cursor:pointer">
                            <option value="newest">Newest</option>
                            <option value="oldest">Oldest</option>
                            <option value="az">A&ndash;Z</option>
                            <option value="za">Z&ndash;A</option>
                        </select>
                    </label>
                </div>

                <div class="tsa-dl-grid" id="tsa-dl-grid">
                    <div class="tsa-dl-loading" id="tsa-dl-loading">
                        <div class="tsa-dl-spinner"></div>
                        <span>Loading designs…</span>
                    </div>
                </div>

                <div class="tsa-dl-empty" id="tsa-dl-empty" style="display:none">
                    <span class="tsa-dl-empty__icon">🎨</span>
                    <h3>No designs found</h3>
                    <p>Try adjusting your filters, or
                        <a href="<?php echo esc_url( home_url( '/request-a-quote/?cat=design' ) ); ?>">request a custom design</a>.
                    </p>
                </div>

                <div class="tsa-dl-pagination" id="tsa-dl-pagination" style="display:none">
                    <button class="tsa-dl-page-btn" id="tsa-dl-prev" type="button" disabled>← Prev</button>
                    <span class="tsa-dl-page-info" id="tsa-dl-page-info"></span>
                    <button class="tsa-dl-page-btn" id="tsa-dl-next" type="button">Next →</button>
                </div>

            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     UPLOAD YOUR OWN CTA
══════════════════════════════════════ -->
<section class="tsa-dl-upload-cta">
    <div class="tsa-sd-container">
        <div class="tsa-dl-upload-cta__inner">
            <div>
                <h2>Don't see what you're looking for?</h2>
                <p>Upload your own artwork and we'll build your order instantly — or our design team can create something from scratch.</p>
            </div>
            <div style="display:flex;gap:12px;flex-wrap:wrap;flex-shrink:0">
                <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-btn tsa-btn-primary">Upload Your Design</a>
                <a href="<?php echo esc_url( home_url( '/graphic-design/' ) ); ?>" class="tsa-btn tsa-btn-outline">Get a Custom Design</a>
            </div>
        </div>
    </div>
</section>

<!-- Customize It modal -->
<div class="tsa-dl-modal" id="tsa-dl-modal" hidden>
    <div class="tsa-dl-modal__backdrop" data-close></div>
    <div class="tsa-dl-modal__box" role="dialog" aria-modal="true" aria-labelledby="tsa-dl-modal-title">
        <button type="button" class="tsa-dl-modal__x" data-close aria-label="Close">&times;</button>
        <div class="tsa-dl-modal__head">
            <img id="tsa-dl-modal-img" src="" alt="" class="tsa-dl-modal__img">
            <div>
                <div class="tsa-dl-modal__eyebrow">Customize this design</div>
                <h3 id="tsa-dl-modal-title" class="tsa-dl-modal__title"></h3>
            </div>
        </div>
        <form id="tsa-dl-modal-form">
            <input type="hidden" name="design_id" id="tsa-dl-modal-design-id" value="">
            <label class="tsa-dl-modal__label" for="tsa-dl-modal-email">Email</label>
            <input type="email" id="tsa-dl-modal-email" name="email" class="tsa-dl-modal__input" required value="<?php echo esc_attr( is_user_logged_in() ? wp_get_current_user()->user_email : '' ); ?>">
            <label class="tsa-dl-modal__label" for="tsa-dl-modal-desc">What customization do you need?</label>
            <textarea id="tsa-dl-modal-desc" name="description" class="tsa-dl-modal__input" rows="4" required placeholder="e.g. change the colors, add a name, adjust the wording…"></textarea>
            <div class="tsa-dl-modal__msg" id="tsa-dl-modal-msg"></div>
            <button type="submit" class="tsa-btn tsa-btn-primary" id="tsa-dl-modal-submit">Send Request</button>
        </form>
    </div>
</div>

<?php get_footer(); ?>

<script>
(function () {
    var API   = '<?php echo esc_js( rest_url( "tsa/v1/designs" ) ); ?>';
    var CZ_AJAX  = '<?php echo esc_js( admin_url( "admin-ajax.php" ) ); ?>';
    var CZ_NONCE = '<?php echo esc_js( wp_create_nonce( "tsa_design_customize" ) ); ?>';

    var state = {
        store: '<?php echo esc_js( $active_store ); ?>',
        category: '<?php echo esc_js( $active_cat ); ?>',
        method: '', search: '', sort: 'newest', page: 1, perPage: 24,
        collection: '', loading: false, total: 0, totalPages: 0
    };

    var grid        = document.getElementById('tsa-dl-grid');
    var loadingEl   = document.getElementById('tsa-dl-loading');
    var emptyEl     = document.getElementById('tsa-dl-empty');
    var countEl     = document.getElementById('tsa-dl-count');
    var prevBtn     = document.getElementById('tsa-dl-prev');
    var nextBtn     = document.getElementById('tsa-dl-next');
    var pageInfo    = document.getElementById('tsa-dl-page-info');
    var pagination  = document.getElementById('tsa-dl-pagination');
    var activeFilEl = document.getElementById('tsa-dl-active-filters');
    var galCol      = document.querySelector('.tsa-dl-gallery-col');
    var backBtn     = document.getElementById('tsa-dl-back');
    var HAS_CATS    = <?php echo ! empty( $cat_cards ) ? 'true' : 'false'; ?>;

    function anyFilterActive(){ return !!(state.category || state.method || state.search); }
    function showDesigns(){ if (galCol) galCol.classList.add('is-designs'); }
    function showCategories(){
        if (galCol) galCol.classList.remove('is-designs');
        if (pagination) pagination.style.display = 'none';
        if (emptyEl)    emptyEl.style.display = 'none';
        state.collection = '';
        if (typeof czCatHome === 'function') czCatHome();
        updateURL();
    }

    function esc(s) {
        return String(s || '').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;');
    }

    /* ── Fetch ── */
    function fetch_designs() {
        if (state.loading) return;
        state.loading = true;
        loadingEl.style.display = 'flex';
        emptyEl.style.display   = 'none';
        grid.querySelectorAll('.tsa-dl-card').forEach(function(c){ c.remove(); });

        var p = new URLSearchParams({
            store: state.store, category: state.category,
            method: state.method, search: state.search, sort: state.sort,
            page: state.page, per_page: state.perPage,
            featured: state.collection === 'featured' ? 1 : '',
            new:      state.collection === 'new' ? 1 : ''
        });

        window.fetch(API + '?' + p.toString())
            .then(function(r){ return r.json(); })
            .then(function(data){
                state.loading    = false;
                state.total      = data.total      || 0;
                state.totalPages = data.total_pages || 0;
                loadingEl.style.display = 'none';
                countEl.textContent = state.total + ' design' + (state.total !== 1 ? 's' : '');

                if (!data.designs || !data.designs.length) {
                    emptyEl.style.display = 'block';
                    pagination.style.display = 'none';
                    return;
                }
                data.designs.forEach(function(d){ grid.appendChild(buildCard(d)); });
                updatePagination();
                updateURL();
            })
            .catch(function(){
                state.loading = false;
                loadingEl.style.display = 'none';
                countEl.textContent = 'Could not load designs.';
            });
    }

    /* ── Build card ── */
    function buildCard(d) {
        var el = document.createElement('article');
        el.className = 'tsa-dl-card';

        var visual = d.preview
            ? '<img src="'+esc(d.preview)+'" alt="'+esc(d.title)+'" class="tsa-dl-card__img" loading="lazy">'
            : '<div class="tsa-dl-card__no-preview">🎨</div>';

        var stores = (d.store||[]).map(function(s){ return '<span class="tsa-dl-card__store">'+esc(s)+'</span>'; }).join('');
        var cats   = (d.categories||[]).slice(0,2).map(function(c){ return '<span class="tsa-dl-card__tag">'+esc(c)+'</span>'; }).join('');
        var meths  = (d.methods||[]).map(function(m){ return '<span class="tsa-dl-card__method">'+esc(m)+'</span>'; }).join('');
        var badge  = d.is_new ? '<span class="tsa-dl-card__badge">New</span>' : '';

        el.innerHTML =
            '<div class="tsa-dl-card__visual tsa-autocontrast">'+badge+
                '<a href="'+esc(d.config_url)+'" class="tsa-dl-card__imglink" aria-label="Use '+esc(d.title)+' in the configurator" style="display:block;line-height:0;text-decoration:none">'+visual+'</a>'+
                '<div class="tsa-dl-card__overlay">'+
                    '<a href="'+esc(d.config_url)+'" class="tsa-dl-card__action tsa-dl-card__action--primary">Use in Configurator</a>'+
                    '<button type="button" class="tsa-dl-card__action tsa-dl-card__action--secondary tsa-dl-customize" data-id="'+esc(d.id)+'" data-title="'+esc(d.title)+'" data-preview="'+esc(d.preview||'')+'">Customize It</button>'+
                '</div>'+
            '</div>'+
            '<div class="tsa-dl-card__body">'+
                '<div class="tsa-dl-card__meta">'+stores+'</div>'+
                '<h3 class="tsa-dl-card__title"><a href="'+esc(d.config_url)+'" style="color:inherit;text-decoration:none">'+esc(d.title)+'</a></h3>'+
                '<div class="tsa-dl-card__tags">'+cats+'</div>'+
                (meths ? '<div class="tsa-dl-card__methods">'+meths+'</div>' : '')+
            '</div>';

        if ( window.tsaAutoContrastScan ) window.tsaAutoContrastScan( el );
        return el;
    }

    /* ── Pagination ── */
    function updatePagination() {
        pagination.style.display = state.totalPages > 1 ? 'flex' : 'none';
        prevBtn.disabled = state.page <= 1;
        nextBtn.disabled = state.page >= state.totalPages;
        pageInfo.textContent = 'Page ' + state.page + ' of ' + state.totalPages;
    }

    function scrollToGrid() { window.scrollTo({ top: grid.getBoundingClientRect().top + window.scrollY - 96, behavior:'smooth' }); }

    if (prevBtn) prevBtn.addEventListener('click', function(){ if (state.page > 1) { state.page--; fetch_designs(); scrollToGrid(); } });
    if (nextBtn) nextBtn.addEventListener('click', function(){ if (state.page < state.totalPages) { state.page++; fetch_designs(); scrollToGrid(); } });

    /* ── Filter checkboxes (multi-select, OR within a facet) ── */
    function collectFilter(filter) {
        var vals = [];
        document.querySelectorAll('.tsa-dl-filter-check[data-filter="'+filter+'"]:checked').forEach(function(c){ vals.push(c.value); });
        return vals.join(',');
    }
    document.querySelectorAll('.tsa-dl-filter-check').forEach(function(c){
        c.addEventListener('change', function(){
            state.collection = ''; // category/method filtering is its own mode
            state[this.dataset.filter] = collectFilter(this.dataset.filter);
            state.page = 1;
            if (anyFilterActive()) { showDesigns(); fetch_designs(); }
            else { showCategories(); }
            buildFilterPills();
        });
    });
    // Reflect any URL-provided category filters into the checkboxes on load
    if (state.category) state.category.split(',').forEach(function(v){
        var b = document.querySelector('.tsa-dl-filter-check[data-filter="category"][value="'+v+'"]');
        if (b) b.checked = true;
    });

    /* ── Sort ── */
    var sortEl = document.getElementById('tsa-dl-sort');
    if (sortEl) sortEl.addEventListener('change', function(){
        state.sort = sortEl.value; state.page = 1; fetch_designs();
    });

    /* ── Search ── */
    var timer;
    var searchEl = document.getElementById('tsa-dl-search');
    if (searchEl) searchEl.addEventListener('input', function(){
        clearTimeout(timer);
        timer = setTimeout(function(){
            state.collection = ''; // searching is its own mode
            state.search = searchEl.value.trim(); state.page = 1;
            if (anyFilterActive()) { showDesigns(); fetch_designs(); }
            else { showCategories(); }
        }, 380);
    });

    /* ── Clear all ── */
    var clearBtn = document.getElementById('tsa-dl-clear-all');
    if (clearBtn) clearBtn.addEventListener('click', function(){
        // Keep state.store — it's the URL store context, not a user-set filter.
        state.category = state.method = state.search = '';
        state.page = 1;
        document.querySelectorAll('.tsa-dl-filter-check').forEach(function(c){ c.checked = false; });
        if (searchEl) searchEl.value = '';
        showCategories();
        buildFilterPills();
    });

    /* ── Filter pills ── */
    function buildFilterPills() {
        if (!activeFilEl) return;
        activeFilEl.innerHTML = '';
        ['category','method'].forEach(function(k){
            if (!state[k]) return;
            state[k].split(',').forEach(function(val){
                if (!val) return;
                var box   = document.querySelector('.tsa-dl-filter-check[data-filter="'+k+'"][value="'+val+'"]');
                var label = val;
                if (box && box.closest('label')) {
                    label = box.closest('label').textContent.trim().replace(/\s+\d+$/, ''); // strip trailing count
                }
                var pill = document.createElement('span');
                pill.className = 'tsa-dl-filter-pill';
                pill.innerHTML = esc(label) + ' <button type="button" aria-label="Remove">✕</button>';
                pill.querySelector('button').addEventListener('click', function(){
                    if (box) box.checked = false;
                    state[k] = collectFilter(k);
                    state.page = 1;
                    if (anyFilterActive()) { fetch_designs(); }
                    else { showCategories(); }
                    buildFilterPills();
                });
                activeFilEl.appendChild(pill);
            });
        });
    }

    /* ── Accordion ── */
    document.querySelectorAll('.tsa-dl-accordion-btn').forEach(function(btn){
        btn.addEventListener('click', function(){
            var open  = btn.getAttribute('aria-expanded') === 'true';
            var body  = document.getElementById(btn.getAttribute('aria-controls'));
            btn.setAttribute('aria-expanded', String(!open));
            if (body) body.hidden = open;
        });
    });

    /* ── Mobile sidebar toggle ── */
    var mobileBtn = document.createElement('button');
    mobileBtn.type = 'button';
    mobileBtn.className = 'tsa-dl-mobile-filter-btn';
    mobileBtn.textContent = '⚙ Filters';
    mobileBtn.addEventListener('click', function(){
        var sb = document.getElementById('tsa-dl-sidebar');
        sb.classList.toggle('is-open');
        mobileBtn.textContent = sb.classList.contains('is-open') ? '✕ Close Filters' : '⚙ Filters';
    });
    if (galCol) galCol.insertBefore(mobileBtn, galCol.firstChild);

    /* ── Category landing cards → drill into that category ── */
    document.querySelectorAll('.tsa-dl-cat-card[data-cat]').forEach(function(card){
        card.addEventListener('click', function(){
            var slug = card.getAttribute('data-cat');
            var box  = document.querySelector('.tsa-dl-filter-check[data-filter="category"][value="'+slug+'"]');
            if (box) box.checked = true;
            state.collection = '';
            state.category = collectFilter('category') || slug;
            state.search = ''; if (searchEl) searchEl.value = '';
            state.page = 1;
            showDesigns();
            fetch_designs();
            buildFilterPills();
            scrollToGrid();
        });
    });

    /* ── New / Featured collection cards → filtered design view ── */
    document.querySelectorAll('.tsa-dl-cat-card[data-collection]').forEach(function(card){
        card.addEventListener('click', function(){
            state.collection = card.getAttribute('data-collection');
            state.category = ''; state.search = ''; if (searchEl) searchEl.value = '';
            document.querySelectorAll('.tsa-dl-filter-check').forEach(function(c){ c.checked = false; });
            state.page = 1;
            showDesigns();
            fetch_designs();
            buildFilterPills();
            scrollToGrid();
        });
    });

    /* ── Back to the category landing ── */
    if (backBtn) backBtn.addEventListener('click', function(){
        state.category = state.method = state.search = '';
        state.page = 1;
        document.querySelectorAll('.tsa-dl-filter-check').forEach(function(c){ c.checked = false; });
        if (searchEl) searchEl.value = '';
        showCategories();
        buildFilterPills();
        if (galCol) window.scrollTo({ top: galCol.getBoundingClientRect().top + window.scrollY - 96, behavior:'smooth' });
    });

    /* ── URL sync ── */
    function updateURL() {
        var p = new URLSearchParams();
        if (state.store)    p.set('store', state.store);
        if (state.category) p.set('cat',   state.category);
        if (state.page > 1) p.set('page',  state.page);
        history.replaceState(null, '', p.toString() ? '?'+p.toString() : location.pathname);
    }

    /* ── Category drill-down (top category → its subcategory cards) ── */
    var catHead   = document.getElementById('tsa-dl-cats-head');
    var catTop    = document.getElementById('tsa-dl-cat-top');
    var catCrumb  = document.getElementById('tsa-dl-cats-crumb');
    var catCrumbN = document.getElementById('tsa-dl-cats-crumb-name');
    function czCatHome(){
        if (catCrumb) catCrumb.hidden = true;
        if (catHead)  catHead.hidden = false;
        if (catTop)   catTop.hidden = false;
        document.querySelectorAll('.tsa-dl-subcats').forEach(function(s){ s.hidden = true; });
    }
    function czCatDrill(slug){
        var sub = document.getElementById('tsa-dl-sub-' + slug);
        if (!sub) return;
        if (catHead) catHead.hidden = true;
        if (catTop)  catTop.hidden = true;
        document.querySelectorAll('.tsa-dl-subcats').forEach(function(s){ s.hidden = (s !== sub); });
        if (catCrumbN) catCrumbN.textContent = sub.getAttribute('data-parent-name') || '';
        if (catCrumb)  catCrumb.hidden = false;
    }
    document.querySelectorAll('.tsa-dl-cat-card[data-parent]').forEach(function(card){
        card.addEventListener('click', function(){ czCatDrill(card.getAttribute('data-parent')); });
    });
    var catsBack = document.querySelector('[data-cats-home]');
    if (catsBack) catsBack.addEventListener('click', czCatHome);

    // Init — land on the category cards. Jump straight to the designs grid only
    // when a category deep-link (?cat=) is present or there are no category cards.
    if (state.category || !HAS_CATS) { showDesigns(); fetch_designs(); }
    else { showCategories(); }
    buildFilterPills();

    /* ── "Customize It" modal ── */
    var modal = document.getElementById('tsa-dl-modal');
    function czOpen(id, title, preview){
        document.getElementById('tsa-dl-modal-design-id').value = id || '';
        document.getElementById('tsa-dl-modal-title').textContent = title || 'this design';
        var img = document.getElementById('tsa-dl-modal-img');
        if (preview) { img.src = preview; img.style.display = ''; } else { img.style.display = 'none'; }
        document.getElementById('tsa-dl-modal-msg').textContent = '';
        if (modal) modal.hidden = false;
    }
    function czClose(){ if (modal) modal.hidden = true; }
    document.addEventListener('click', function(e){
        var btn = e.target.closest('.tsa-dl-customize');
        if (btn) { e.preventDefault(); czOpen(btn.getAttribute('data-id'), btn.getAttribute('data-title'), btn.getAttribute('data-preview')); return; }
        if (e.target.closest('[data-close]')) czClose();
    });
    document.addEventListener('keydown', function(e){ if (e.key === 'Escape') czClose(); });
    var czForm = document.getElementById('tsa-dl-modal-form');
    if (czForm) czForm.addEventListener('submit', function(e){
        e.preventDefault();
        var sub = document.getElementById('tsa-dl-modal-submit');
        var msg = document.getElementById('tsa-dl-modal-msg');
        sub.disabled = true; sub.textContent = 'Sending…';
        var fd = new FormData(czForm);
        fd.append('action','tsa_design_customize'); fd.append('nonce', CZ_NONCE);
        window.fetch(CZ_AJAX, { method:'POST', body:fd, credentials:'same-origin' })
            .then(function(r){ return r.json(); })
            .then(function(j){
                sub.disabled = false; sub.textContent = 'Send Request';
                if (j && j.success) { msg.style.color = '#1a7f37'; msg.textContent = 'Sent! We\'ll be in touch shortly.'; setTimeout(czClose, 1800); }
                else { msg.style.color = '#b32d2e'; msg.textContent = (j && j.data && j.data.message) || 'Could not send. Please try again.'; }
            })
            .catch(function(){ sub.disabled = false; sub.textContent = 'Send Request'; msg.style.color = '#b32d2e'; msg.textContent = 'Network error.'; });
    });
})();
</script>
