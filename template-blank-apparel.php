<?php
/**
 * Template Name: TSA Blank Apparel Catalog
 *
 * Overview of all blank apparel brands available through Tee Shirt Ali.
 * Each brand card links to its individual brand catalog page.
 * Assign this to: /blank-apparel/
 */

defined( 'ABSPATH' ) || exit;

get_header();

// Brand cards come exclusively from the admin-managed Blank Apparel CPT
// (tsa_brand). No hardcoded placeholder brands — an empty catalog shows the
// empty state below until brands are added or imported from S&S.
$brands = [];

// Load the admin-managed brands (Blank Apparel CPT). Stays empty if none exist.
if ( function_exists( 'tsa_get_catalog_brands' ) ) {
    $cpt_brands = tsa_get_catalog_brands();
    if ( ! empty( $cpt_brands ) ) $brands = $cpt_brands;
}

// Fill missing card details live from each brand's garments (type, swatches,
// footer note). The footer note shows the number of apparel STYLES offered
// for the brand (the catalog-visible garments shown on the brand page) — more
// meaningful on a brand card than a raw color count. Swatches stay as a visual
// sample. Manual meta still wins (only empty fields are filled).
foreach ( $brands as $i => $b ) {
    if ( ! function_exists( 'tsa_brand_derived_attrs' ) ) break;
    $d = tsa_brand_derived_attrs( $b['name'] );
    if ( $d['swatches'] && empty( $b['swatches'] ) ) $brands[ $i ]['swatches'] = $d['swatches'];
    if ( $d['types']    && empty( $b['type'] ) )     $brands[ $i ]['type']     = implode( ' · ', $d['types'] );
    if ( $d['styles']   && empty( $b['note'] ) )     $brands[ $i ]['note']     = $d['styles'] . ' style' . ( $d['styles'] === 1 ? '' : 's' );
}

// Resolve every brand's filter category once — saved CPT value if present,
// else inferred from the type label — and tag it back onto the brand so the
// tabs and the card markup share a single computed value.
$tsa_resolve_cat = static function ( $brand ) {
    if ( ! empty( $brand['category'] ) ) return $brand['category'];
    $t = strtolower( $brand['type'] ?? '' );
    if ( strpos( $t, 'tee' ) !== false || strpos( $t, 'garment' ) !== false || strpos( $t, 'tri' ) !== false || strpos( $t, 'soft' ) !== false ) return 'tees';
    if ( strpos( $t, 'fleece' ) !== false || strpos( $t, 'hood' ) !== false ) return 'fleece';
    if ( strpos( $t, 'perform' ) !== false || strpos( $t, 'athletic' ) !== false ) return 'performance';
    if ( strpos( $t, 'head' ) !== false || strpos( $t, 'cap' ) !== false || strpos( $t, 'hat' ) !== false ) return 'headwear';
    return 'tees';
};
$used_cats = [];
foreach ( $brands as $i => $brand ) {
    $cat = $tsa_resolve_cat( $brand );
    $brands[ $i ]['category'] = $cat;
    $used_cats[ $cat ] = true;
}

// Build the filter tabs dynamically: "All Brands" first, then only the
// categories that actually have at least one brand. Labels come from the
// catalog include (single source of truth) when available.
$cat_labels = function_exists( 'tsa_brand_categories' ) ? tsa_brand_categories() : [
    'tees'        => 'Tees',
    'fleece'      => 'Hoodies & Fleece',
    'performance' => 'Performance',
    'headwear'    => 'Headwear',
];
$categories = [ 'all' => 'All Brands' ];
foreach ( $cat_labels as $key => $label ) {
    if ( ! empty( $used_cats[ $key ] ) ) $categories[ $key ] = $label;
}
?>

<!-- ══════════════════════════════════════
     CATALOG HERO
══════════════════════════════════════ -->
<section class="tsa-catalog-hero">
    <div>
        <div class="tsa-kicker">Premium Blanks</div>
        <h1>Blank Apparel Catalog</h1>
        <p>Start with the perfect blank, choose a design from the library, and build your custom apparel through the Tee Shirt Ali configurator.</p>
        <div class="tsa-actions">
            <?php tsa_btn( '/design-library/', 'Browse Design Library', 'primary' ); ?>
            <?php tsa_btn( '/request-a-quote/', 'Request a Quote', 'outline' ); ?>
        </div>
    </div>
    <div style="display:flex; align-items:center; justify-content:center;">
        <div class="tsa-stats">
            <div>
                <div class="tsa-stat__num"><?php echo (int) count( $brands ); ?></div>
                <div class="tsa-stat__label">Brands</div>
            </div>
            <div>
                <div class="tsa-stat__num">500+</div>
                <div class="tsa-stat__label">Color Options</div>
            </div>
            <div>
                <div class="tsa-stat__num">100+</div>
                <div class="tsa-stat__label">Styles</div>
            </div>
        </div>
    </div>
</section>

<!-- Breadcrumb -->
<div class="tsa-breadcrumb">
    <a href="/">Home</a><span class="sep">/</span>
    Blank Apparel
</div>


<!-- ══════════════════════════════════════
     BRANDS GRID
══════════════════════════════════════ -->
<section class="tsa-section" style="background:#f3f1f2;">

    <?php if ( empty( $brands ) ) : ?>
    <div class="tsa-catalog-empty" style="text-align:center; max-width:560px; margin:0 auto; padding:48px 20px;">
        <h2 style="margin:0 0 10px;">Our blank catalog is being stocked</h2>
        <p style="margin:0 0 22px; color:#666;">We're loading our full lineup of premium blank apparel brands. Check back soon — or start your project now and we'll recommend the perfect garment for your design, quantity, and budget.</p>
        <div class="tsa-actions tsa-actions--center">
            <?php tsa_btn( '/request-a-quote/', 'Request a Quote', 'primary' ); ?>
            <?php tsa_btn( '/design-library/', 'Browse Design Library', 'outline' ); ?>
        </div>
    </div>
    <?php else : ?>

    <!-- Filter tabs (JS-driven) -->
    <div class="tsa-filter-tabs" id="tsa-catalog-filters">
        <?php foreach ( $categories as $key => $label ) : ?>
        <button class="tsa-filter-tab<?php echo $key === 'all' ? ' active' : ''; ?>"
                data-filter="<?php echo esc_attr( $key ); ?>">
            <?php echo esc_html( $label ); ?>
        </button>
        <?php endforeach; ?>
    </div>

    <div class="tsa-brand-grid" id="tsa-brand-grid">
        <?php foreach ( $brands as $brand ) : ?>
        <a href="<?php echo esc_url( $brand['url'] ); ?>" class="tsa-brand-card"
           data-category="<?php echo esc_attr( $brand['category'] ); ?>">
            <?php if ( ! empty( $brand['image'] ) ) : ?>
            <div class="tsa-brand-card__img"><img src="<?php echo esc_url( $brand['image'] ); ?>" alt="<?php echo esc_attr( $brand['name'] ); ?>" loading="lazy"></div>
            <?php endif; ?>
            <div class="tsa-brand-card__name"><?php echo esc_html( $brand['name'] ); ?></div>
            <div class="tsa-brand-card__type"><?php echo esc_html( $brand['type'] ); ?></div>
            <div class="tsa-brand-card__swatches">
                <?php foreach ( $brand['swatches'] as $color ) : ?>
                    <span class="tsa-swatch" style="background:<?php echo esc_attr( $color ); ?>"></span>
                <?php endforeach; ?>
            </div>
            <div class="tsa-brand-card__footer">
                <span><?php echo esc_html( $brand['note'] ); ?></span>
                <span style="font-weight:900; color:var(--tsa-dark);">View →</span>
            </div>
        </a>
        <?php endforeach; ?>
    </div>
    <?php endif; ?>

</section>


<!-- ══════════════════════════════════════
     PAGE CONTENT
══════════════════════════════════════ -->
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
    <?php if ( get_the_content() ) : ?>
    <section class="tsa-section" style="background: var(--tsa-pink-soft);">
        <div style="max-width: 860px; margin: 0 auto;">
            <?php the_content(); ?>
        </div>
    </section>
    <?php endif; ?>
<?php endwhile; endif; ?>


<!-- ══════════════════════════════════════
     CTA
══════════════════════════════════════ -->
<section class="tsa-section tsa-cta">
    <h2>Need Help Choosing a Blank?</h2>
    <p>Tell us about your project and we'll recommend the perfect garment for your design, quantity, and budget.</p>
    <div class="tsa-actions tsa-actions--center">
        <?php tsa_btn( '/request-a-quote/', 'Get a Recommendation', 'primary' ); ?>
        <?php tsa_btn( '/contact/', 'Ask a Question', 'outline-white' ); ?>
    </div>
</section>

<?php get_footer(); ?>
