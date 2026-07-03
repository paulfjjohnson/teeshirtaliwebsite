<?php
/**
 * Brand page renderer (level 2) — brand hero + a grid of that brand's apparel
 * styles (configurator_garment posts tagged `_ac_brand` = brand). Each card
 * links to the product detail page (/apparel/{garment}/), NOT the configurator.
 *
 * Defines tsa_render_brand_page() so single-tsa_brand.php can render the brand
 * from the current post. Also self-renders when assigned as a Page template
 * (legacy), guarded so the single can require this file without double output.
 *
 * Template Name: TSA Brand Catalog
 */

defined( 'ABSPATH' ) || exit;

if ( ! function_exists( 'tsa_render_brand_page' ) ) :
function tsa_render_brand_page( $brand_name ) {
    $cfg     = function_exists( 'tsa_get_brand_config' ) ? tsa_get_brand_config( sanitize_title( $brand_name ) ) : null;
    $brand   = is_array( $cfg ) ? $cfg : [ 'name' => $brand_name, 'type' => '', 'desc' => '', 'colors' => 0 ];
    $brand['name'] = $brand_name; // canonical from the post
    $derived = function_exists( 'tsa_brand_derived_attrs' ) ? tsa_brand_derived_attrs( $brand_name ) : [ 'colors' => 0, 'types' => [], 'styles' => 0 ];

    // Normalise a brand string → lowercase, alphanumerics only. Makes the brand
    // match tolerant of case, spacing and punctuation ("BELLA + CANVAS",
    // "Bella+Canvas", "bella  canvas" all collapse to "bellacanvas").
    $norm   = static function ( $s ) { return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $s ) ); };
    $target = $norm( $brand_name );

    // Pull all catalog-visible garments, then match the brand in PHP (tolerant,
    // with a title fallback for garments whose _ac_brand meta is blank).
    $all = get_posts( [
        'post_type'   => 'configurator_garment',
        'post_status' => 'publish',
        'numberposts' => -1,
        'orderby'     => [ 'menu_order' => 'ASC', 'title' => 'ASC' ],
        'meta_query'  => [
            // By Catalog visibility: shown unless explicitly hidden. Imports are
            // visible by default; manual garments need the box ticked ('1').
            [ 'relation' => 'OR',
                [ 'key' => '_tsa_in_catalog', 'value' => '1' ],
                [ 'relation' => 'AND',
                    [ 'key' => '_tsa_in_catalog', 'compare' => 'NOT EXISTS' ],
                    [ 'key' => '_ac_ss_style_id',  'compare' => 'EXISTS' ],
                ],
            ],
        ],
    ] );
    $garments = ( $target === '' ) ? [] : array_values( array_filter( $all, function ( $g ) use ( $norm, $target ) {
        $b = $norm( get_post_meta( $g->ID, '_ac_brand', true ) );
        if ( $b !== '' ) return $b === $target;                  // brand meta set → exact (normalised) match
        return strpos( $norm( get_the_title( $g ) ), $target ) === 0; // fallback: title starts with the brand
    } ) );

    $hero_type   = $brand['type'] ?: implode( ' · ', $derived['types'] );
    $hero_colors = (int) ( $brand['colors'] ?: $derived['colors'] );
    $hero_styles = (int) ( $derived['styles'] ?: count( $garments ) );

    // Per-garment helpers.
    $g_colors = static function ( $gid ) {
        $styles = get_post_meta( $gid, '_ac_styles', true );
        $seen = []; $swatches = [];
        if ( is_array( $styles ) ) foreach ( $styles as $st ) {
            if ( empty( $st['colors'] ) || ! is_array( $st['colors'] ) ) continue;
            foreach ( $st['colors'] as $c ) {
                $hex = $c['hex'] ?? ''; $key = strtolower( $c['name'] ?? '' ) ?: $hex;
                if ( $key === '' || isset( $seen[ $key ] ) ) continue;
                $seen[ $key ] = true; if ( $hex ) $swatches[] = $hex;
            }
        }
        return [ 'count' => count( $seen ), 'swatches' => $swatches ];
    };
    $g_image = static function ( $gid ) {
        $img = get_the_post_thumbnail_url( $gid, 'medium' );
        if ( ! $img ) { $mock = get_post_meta( $gid, '_ac_mockup_images', true );
            if ( is_array( $mock ) ) foreach ( $mock as $m ) { if ( ! empty( $m['front'] ) ) { $img = $m['front']; break; } } }
        return $img ?: '';
    };
    $g_fit = static function ( $text ) {
        $t = strtolower( (string) $text );
        if ( strpos( $t, 'youth' ) !== false || strpos( $t, 'kids' ) !== false )   return 'Youth';
        if ( strpos( $t, 'toddler' ) !== false )                                   return 'Toddler';
        if ( strpos( $t, 'infant' ) !== false || strpos( $t, 'onesie' ) !== false ) return 'Infant';
        if ( strpos( $t, 'women' ) !== false || strpos( $t, 'ladies' ) !== false || strpos( $t, 'juniors' ) !== false ) return "Women's";
        if ( strpos( $t, "men's" ) !== false || strpos( $t, 'mens' ) !== false )    return "Men's";
        return 'Unisex';
    };
    ?>
    <section class="tsa-brand-hero">
        <div>
            <?php if ( $hero_type ) : ?><div class="tsa-kicker tsa-kicker--light"><?php echo esc_html( $hero_type ); ?></div><?php endif; ?>
            <h1><?php echo esc_html( $brand['name'] ); ?></h1>
            <?php if ( ! empty( $brand['desc'] ) ) : ?><p><?php echo esc_html( $brand['desc'] ); ?></p><?php endif; ?>
            <div class="tsa-actions" style="margin-top:28px;">
                <?php tsa_btn( '/blank-apparel/', '← All Brands', 'outline-white' ); ?>
            </div>
        </div>
        <?php if ( $hero_colors || $hero_styles ) : ?>
        <div style="text-align:center; flex-shrink:0;">
            <?php if ( $hero_colors ) : ?>
            <div style="font-size:60px; font-weight:900; line-height:1; color:#fff;"><?php echo $hero_colors; ?>+</div>
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; color:rgba(255,255,255,.5); font-weight:700; margin-bottom:20px;">Colors</div>
            <?php endif; ?>
            <?php if ( $hero_styles ) : ?>
            <div style="font-size:48px; font-weight:900; line-height:1; color:var(--tsa-pink);"><?php echo $hero_styles; ?></div>
            <div style="font-size:12px; text-transform:uppercase; letter-spacing:1px; color:rgba(255,255,255,.5); font-weight:700;">Styles</div>
            <?php endif; ?>
        </div>
        <?php endif; ?>
    </section>

    <div class="tsa-breadcrumb">
        <a href="/">Home</a><span class="sep">/</span>
        <a href="/blank-apparel/">Blank Apparel</a><span class="sep">/</span>
        <?php echo esc_html( $brand['name'] ); ?>
    </div>

    <section class="tsa-section" style="background:#f3f1f2;">
        <div class="tsa-section-head tsa-section-head--left" style="max-width:none;">
            <h2><?php echo esc_html( $brand['name'] ); ?> Styles</h2>
            <p>Pick a style to see its colors and sizes, then add your design.</p>
        </div>

        <?php if ( $garments ) : ?>
        <div class="tsa-brand-grid">
            <?php foreach ( $garments as $g ) :
                $name  = get_the_title( $g );
                $info  = $g_colors( $g->ID );
                $image = $g_image( $g->ID );
                $fit   = $g_fit( $name );
                $url   = get_permalink( $g->ID );
                $shown = array_slice( $info['swatches'], 0, 8 );
                $extra = max( 0, $info['count'] - count( $shown ) );
            ?>
            <a href="<?php echo esc_url( $url ); ?>" class="tsa-brand-card">
                <?php if ( $image ) : ?>
                <div class="tsa-brand-card__img"><img src="<?php echo esc_url( $image ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy"></div>
                <?php endif; ?>
                <div class="tsa-brand-card__name"><?php echo esc_html( $name ); ?></div>
                <div class="tsa-brand-card__type"><?php echo esc_html( $fit ); ?></div>
                <?php if ( $shown ) : ?>
                <div class="tsa-brand-card__swatches">
                    <?php foreach ( $shown as $hex ) : ?><span class="tsa-swatch" style="background:<?php echo esc_attr( $hex ); ?>"></span><?php endforeach; ?>
                    <?php if ( $extra ) : ?><span class="tsa-brand-card__type" style="margin:0; align-self:center;">+<?php echo (int) $extra; ?></span><?php endif; ?>
                </div>
                <?php endif; ?>
                <div class="tsa-brand-card__footer">
                    <span><?php echo (int) $info['count']; ?> color<?php echo $info['count'] === 1 ? '' : 's'; ?></span>
                    <span style="font-weight:900; color:var(--tsa-dark);">View options →</span>
                </div>
            </a>
            <?php endforeach; ?>
        </div>
        <?php else : ?>
        <div class="tsa-catalog-empty" style="text-align:center; max-width:560px; margin:0 auto; padding:40px 20px;">
            <h3 style="margin:0 0 10px;"><?php echo esc_html( $brand['name'] ); ?> styles are on the way</h3>
            <p style="margin:0 0 22px; color:#666;">We're still loading this brand's lineup. Tell us what you need and we'll match you to the right <?php echo esc_html( $brand['name'] ); ?> style.</p>
            <div class="tsa-actions tsa-actions--center">
                <?php tsa_btn( '/request-a-quote/', 'Request a Quote', 'primary' ); ?>
                <?php tsa_btn( '/design-library/', 'Browse Design Library', 'outline' ); ?>
            </div>
        </div>
        <?php endif; ?>
    </section>

    <section class="tsa-section" style="background:#fff;">
        <div class="tsa-section-head">
            <h2>Explore Other Brands</h2>
            <p>Not finding the right fit? Browse our full blank apparel catalog.</p>
        </div>
        <div style="text-align:center;"><?php tsa_btn( '/blank-apparel/', 'View All Brands', 'dark' ); ?></div>
    </section>

    <section class="tsa-section tsa-cta">
        <h2>Need a hand?</h2>
        <p>Submit a quote with your quantity, style, and design requirements and we'll get back to you fast.</p>
        <div class="tsa-actions tsa-actions--center">
            <?php tsa_btn( '/request-a-quote/', 'Request a Quote', 'primary' ); ?>
            <?php tsa_btn( '/design-library/', 'Browse Designs', 'outline-white' ); ?>
        </div>
    </section>
    <?php
}
endif;

/* Legacy: when assigned as a Page template, render from the page title.
   single-tsa_brand.php defines TSA_BRAND_SINGLE before requiring this file. */
if ( ! defined( 'TSA_BRAND_SINGLE' ) ) {
    get_header();
    tsa_render_brand_page( get_the_title() );
    get_footer();
}
