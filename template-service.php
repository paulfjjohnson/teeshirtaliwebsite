<?php
/**
 * Template Name: TSA Service Page
 *
 * Reusable template for all service pages:
 * - DTF Printing Services
 * - Graphic Design Services
 * - Promotional Products
 * - Business Branding Services
 * - Business Merch Solutions
 * - Printing Capabilities
 * - Turnaround Times
 * - Services Overview
 * - Fundraiser Solutions
 *
 * Hero gradient, features, and steps are auto-populated from tsa_get_service_config()
 * keyed by the page slug. Override in Page settings > TSA Service Page Settings.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$slug    = get_post_field( 'post_name', get_the_ID() );
$config  = tsa_get_service_config( $slug );

// Allow per-page overrides via meta
$meta_tagline  = get_post_meta( get_the_ID(), '_tsa_service_tagline', true );
$meta_gradient = get_post_meta( get_the_ID(), '_tsa_service_gradient', true );
$meta_cta_url  = get_post_meta( get_the_ID(), '_tsa_service_cta_url', true );
$meta_cta_text = get_post_meta( get_the_ID(), '_tsa_service_cta_text', true );

$tagline  = $meta_tagline  ?: ( $config['tagline']  ?: get_the_excerpt() );
$gradient = $meta_gradient ?: $config['gradient'];
$cta_url  = $meta_cta_url  ?: '/request-a-quote/';
$cta_text = $meta_cta_text ?: 'Get a Free Quote';

$features = $config['features'];
$steps    = $config['steps'];
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-service-hero" style="background: <?php echo esc_attr( $gradient ); ?>">
    <div class="tsa-service-hero__inner">
        <div class="tsa-kicker tsa-kicker--light"><?php echo esc_html( $config['kicker'] ); ?></div>
        <h1><?php the_title(); ?></h1>
        <?php if ( $tagline ) : ?>
            <p class="tsa-service-hero__tagline"><?php echo esc_html( $tagline ); ?></p>
        <?php endif; ?>
        <div class="tsa-actions">
            <?php tsa_btn( $cta_url, $cta_text, 'primary' ); ?>
            <?php tsa_btn( '/contact/', 'Contact Us', 'outline-white' ); ?>
        </div>
    </div>
</section>

<!-- Breadcrumb -->
<div class="tsa-breadcrumb">
    <a href="/">Home</a>
    <span class="sep">/</span>
    <a href="/services/">Services</a>
    <span class="sep">/</span>
    <?php the_title(); ?>
</div>


<?php if ( ! empty( $features ) ) : ?>
<!-- ══════════════════════════════════════
     FEATURES GRID
══════════════════════════════════════ -->
<section class="tsa-section" style="background: var(--tsa-pink-soft);">
    <div class="tsa-section-head">
        <h2>What's Included</h2>
        <p>Everything you need — from first artwork file to finished garment.</p>
    </div>
    <div class="tsa-features-grid">
        <?php foreach ( $features as $f ) : ?>
        <div class="tsa-feature-card">
            <div class="tsa-feature-icon"><?php echo esc_html( $f['icon'] ); ?></div>
            <h4><?php echo esc_html( $f['title'] ); ?></h4>
            <p><?php echo esc_html( $f['desc'] ); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>


<?php if ( ! empty( $steps ) ) : ?>
<!-- ══════════════════════════════════════
     PROCESS STEPS
══════════════════════════════════════ -->
<section class="tsa-section" style="background:#fff;">
    <div class="tsa-section-head">
        <h2>How It Works</h2>
        <p>A straightforward process from start to ship.</p>
    </div>
    <div class="tsa-process">
        <?php foreach ( $steps as $step ) : ?>
        <div class="tsa-process-step">
            <h4><?php echo esc_html( $step['title'] ); ?></h4>
            <p><?php echo esc_html( $step['desc'] ); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>
<?php endif; ?>


<!-- ══════════════════════════════════════
     PAGE CONTENT (Flatsome UX Builder)
══════════════════════════════════════ -->
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
    <?php if ( get_the_content() ) : ?>
    <section class="tsa-section tsa-service-content">
        <div class="tsa-service-content__inner">
            <?php the_content(); ?>
        </div>
    </section>
    <?php endif; ?>
<?php endwhile; endif; ?>


<!-- ══════════════════════════════════════
     CTA
══════════════════════════════════════ -->
<section class="tsa-section tsa-cta">
    <h2>Ready to Get Started?</h2>
    <p>Request a quote or contact our team — we'll get back to you fast with pricing, timelines, and options.</p>
    <div class="tsa-actions tsa-actions--center">
        <?php tsa_btn( $cta_url, $cta_text, 'primary' ); ?>
        <?php tsa_btn( '/contact/', 'Contact Us', 'outline-white' ); ?>
    </div>
</section>

<?php get_footer(); ?>
