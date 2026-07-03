<?php
/**
 * Template Name: TSA School Store
 *
 * School/store microsite landing page.
 * Assign to individual school pages (e.g. /schools/dutchtown/).
 *
 * Reads store context from the page's linked configurator_store post.
 * Set custom field "_tsa_store_id" on the page pointing to the store post ID,
 * or fall back to slug-matching via _ac_store_slug meta.
 */

defined( 'ABSPATH' ) || exit;

get_header();

// Resolve which store this page represents
$store_id = (int) get_post_meta( get_the_ID(), '_tsa_store_id', true );

if ( ! $store_id ) {
    global $wpdb;
    $page_slug = get_post_field( 'post_name', get_the_ID() );
    $store_id  = (int) $wpdb->get_var( $wpdb->prepare(
        "SELECT post_id FROM {$wpdb->postmeta} WHERE meta_key = '_ac_store_slug' AND meta_value = %s LIMIT 1",
        $page_slug
    ) );
}

$store_name    = $store_id ? get_the_title( $store_id ) : get_the_title();
$store_slug    = $store_id ? get_post_meta( $store_id, '_ac_store_slug', true ) : '';
$store_tagline = $store_id ? get_post_meta( $store_id, '_tsa_store_tagline', true ) : '';
$store_type    = $store_id ? get_post_meta( $store_id, '_tsa_store_type', true ) : 'school';
$store_img_id  = $store_id ? get_post_thumbnail_id( $store_id ) : 0;
$store_img_src = $store_img_id ? wp_get_attachment_image_url( $store_img_id, 'tsa-store-banner' ) : '';

// Accent gradient. School stores use their own brand colors (Store Builder /
// brand guide); other store types fall back to the per-type gradient.
$accent_map = [
    'school'   => 'linear-gradient(135deg, #231532, #5f35a5)',
    'business' => 'linear-gradient(135deg, #12243a, #1e4d8c)',
    'team'     => 'linear-gradient(135deg, #1a2d1a, #2e6b2e)',
    'event'    => 'linear-gradient(135deg, #3a1520, #8c2d3c)',
];
$accent_bg  = $accent_map[ $store_type ] ?? $accent_map['school'];
$hero_text  = '#ffffff';
$store_p    = $store_id ? get_post_meta( $store_id, '_tsa_school_primary',   true ) : '';
$store_s    = $store_id ? get_post_meta( $store_id, '_tsa_school_secondary', true ) : '';
if ( ! $store_p && $store_type === 'school' && function_exists( 'tsa_get_school_colors' ) ) {
    $sc      = tsa_get_school_colors( $store_name );
    $store_p = $sc['primary'];
    $store_s = $sc['secondary'] ?? $sc['primary'];
}
if ( $store_p ) {
    // Validated #RRGGBB values; esc_attr is applied once at output.
    $store_s   = $store_s ?: $store_p;
    $accent_bg = 'linear-gradient(135deg, ' . $store_p . ', ' . $store_s . ')';
    if ( function_exists( 'tsa_readable_text' ) ) $hero_text = tsa_readable_text( $store_p );
}

$store_type_labels = [
    'school'   => 'School Store',
    'business' => 'Business Store',
    'team'     => 'Team Store',
    'event'    => 'Event Store',
];
$type_label = $store_type_labels[ $store_type ] ?? 'School Store';
?>

<!-- ══════════════════════════════════════
     STORE HERO
══════════════════════════════════════ -->
<section class="tsa-school-hero" style="background: <?php echo esc_attr( $accent_bg ); ?>; color: <?php echo esc_attr( $hero_text ); ?>">
    <?php if ( $store_img_src ) : ?>
        <div class="tsa-school-hero__bg" style="background-image: url(<?php echo esc_url( $store_img_src ); ?>)"></div>
    <?php endif; ?>

    <div class="tsa-school-hero__inner">
        <div class="tsa-kicker tsa-kicker--light"><?php echo esc_html( $type_label ); ?></div>
        <h1><?php echo esc_html( $store_name ); ?></h1>
        <?php if ( $store_tagline ) : ?>
            <p><?php echo esc_html( $store_tagline ); ?></p>
        <?php endif; ?>
        <div class="tsa-actions">
            <a href="#store-products" class="tsa-btn tsa-btn-primary">Shop Now</a>
            <?php tsa_btn( '/', '← Back to TSA', 'outline-white' ); ?>
        </div>
    </div>
</section>

<!-- Breadcrumb -->
<div class="tsa-breadcrumb">
    <a href="/">Home</a><span class="sep">/</span>
    <a href="/schools/">Schools</a><span class="sep">/</span>
    <?php echo esc_html( $store_name ); ?>
</div>


<!-- ══════════════════════════════════════
     SHOP BY PROGRAM (if any)
══════════════════════════════════════ -->
<?php
$school_programs = ( $store_id && function_exists( 'tsa_school_programs' ) ) ? tsa_school_programs( $store_id ) : [];
if ( $school_programs && $store_slug ) : ?>
<section class="tsa-section" style="background:var(--surface-tint,#f7f7f7)">
    <div style="max-width:1080px;margin:0 auto;padding:0 7%">
        <div class="tsa-section-head" style="text-align:left;margin-bottom:18px">
            <h2 style="margin:0">Shop by program</h2>
            <p style="color:var(--text-muted)">Pick your program to see its designs.</p>
        </div>
        <?php echo do_shortcode( '[tsa_school_programs school="' . esc_attr( $store_slug ) . '"]' ); ?>
    </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════
     LIVE / UPCOMING DROPS (if any)
══════════════════════════════════════ -->
<?php
$drops_html = $store_slug ? do_shortcode( '[tsa_school_drops school="' . esc_attr( $store_slug ) . '" active_only="1"]' ) : '';
if ( $drops_html ) : ?>
<section class="tsa-section" style="background:#fff">
    <div style="max-width:1080px;margin:0 auto;padding:0 7%">
        <div class="tsa-section-head" style="text-align:left;margin-bottom:18px">
            <h2 style="margin:0">Drops</h2>
            <p style="color:var(--text-muted)">Limited design drops — exclusive while the party is live.</p>
        </div>
        <?php echo $drops_html; // already escaped in the shortcode ?>
    </div>
</section>
<?php endif; ?>

<!-- ══════════════════════════════════════
     CONFIGURATOR / PRODUCTS
══════════════════════════════════════ -->
<section id="store-products" class="tsa-section" style="background:#fff;">
    <?php if ( $store_slug ) : ?>
        <?php echo do_shortcode( '[apparel_configurator store="' . esc_attr( $store_slug ) . '"]' ); ?>
    <?php else : ?>
    <div style="max-width:720px; margin:0 auto; text-align:center; padding:40px 0;">
        <div class="tsa-kicker">Coming Soon</div>
        <h2>Store Launching Soon</h2>
        <p style="color:var(--tsa-muted); font-size:18px; margin:0 0 28px; line-height:1.55;">
            The <?php echo esc_html( $store_name ); ?> store is being set up. Check back for the latest collections or contact us to inquire.
        </p>
        <?php tsa_btn( '/contact/', 'Get Notified', 'primary' ); ?>
    </div>
    <?php endif; ?>
</section>


<!-- ══════════════════════════════════════
     PAGE CONTENT (optional UX Builder)
══════════════════════════════════════ -->
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
    <?php if ( get_the_content() ) : ?>
    <section class="tsa-section tsa-school-content">
        <div class="tsa-school-content__inner">
            <?php the_content(); ?>
        </div>
    </section>
    <?php endif; ?>
<?php endwhile; endif; ?>


<!-- ══════════════════════════════════════
     CTA — OTHER STORES
══════════════════════════════════════ -->
<section class="tsa-section tsa-cta">
    <h2>Looking for More?</h2>
    <p>Tee Shirt Ali serves schools, businesses, teams, and events. Explore more stores or start a custom project for your group.</p>
    <div class="tsa-actions tsa-actions--center">
        <?php tsa_btn( '/schools/', 'All School Stores', 'white' ); ?>
        <?php tsa_btn( '/request-a-quote/', 'Start a Project', 'primary' ); ?>
    </div>
</section>

<?php get_footer(); ?>
