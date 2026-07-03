<?php
/**
 * Template Name: TSA Team Store
 *
 * Team/sports store landing page — distinct from school store.
 * Used for: Muddawgs, sports teams, travel squads, business teams.
 * Assign to: /team-stores/muddawgs/ etc.
 */

defined( 'ABSPATH' ) || exit;

get_header();

$store_id    = (int) get_post_meta( get_the_ID(), '_tsa_store_id', true );
$store_name  = $store_id ? get_the_title( $store_id ) : get_the_title();
$store_slug  = $store_id ? get_post_meta( $store_id, '_ac_store_slug', true ) : '';
$store_tagline = $store_id ? get_post_meta( $store_id, '_tsa_store_tagline', true ) : '';
$team_sport  = get_post_meta( get_the_ID(), '_tsa_team_sport', true ) ?: 'Sports Team';
$team_season = get_post_meta( get_the_ID(), '_tsa_team_season', true );
$img_id      = get_post_thumbnail_id();
$img_src     = $img_id ? wp_get_attachment_image_url( $img_id, 'tsa-team-banner' ) : '';
?>

<!-- ══════════════════════════════════════
     TEAM HERO
══════════════════════════════════════ -->
<section class="tsa-team-hero">
    <?php if ( $img_src ) : ?>
    <div class="tsa-team-hero__bg" style="background-image: url(<?php echo esc_url( $img_src ); ?>)"></div>
    <?php else : ?>
    <div class="tsa-team-hero__bg" style="background: linear-gradient(135deg, #1a2d1a, #3a5a3a);"></div>
    <?php endif; ?>
    <div class="tsa-team-hero__overlay"></div>

    <div class="tsa-team-hero__inner">

        <?php if ( $team_sport || $team_season ) : ?>
        <div class="tsa-team-meta">
            <?php if ( $team_sport ) : ?>
            <div class="tsa-team-meta__item">⚽ <?php echo esc_html( $team_sport ); ?></div>
            <?php endif; ?>
            <?php if ( $team_season ) : ?>
            <div class="tsa-team-meta__item">📅 <?php echo esc_html( $team_season ); ?> Season</div>
            <?php endif; ?>
            <div class="tsa-badge tsa-badge--live" style="background:rgba(34,197,94,.25); color:#6ee7b7;">Active Store</div>
        </div>
        <?php endif; ?>

        <h1><?php echo esc_html( $store_name ); ?></h1>
        <?php if ( $store_tagline ) : ?>
            <p><?php echo esc_html( $store_tagline ); ?></p>
        <?php endif; ?>

        <div class="tsa-actions">
            <a href="#team-products" class="tsa-btn tsa-btn-primary">Shop Team Gear</a>
            <?php tsa_btn( '/team-stores/', '← All Teams', 'outline-white' ); ?>
        </div>
    </div>
</section>

<!-- Breadcrumb -->
<div class="tsa-breadcrumb">
    <a href="/">Home</a><span class="sep">/</span>
    <a href="/team-stores/">Team Stores</a><span class="sep">/</span>
    <?php echo esc_html( $store_name ); ?>
</div>


<!-- ══════════════════════════════════════
     TEAM STORE PRODUCTS / CONFIGURATOR
══════════════════════════════════════ -->
<section id="team-products" class="tsa-section" style="background:#fff;">
    <?php if ( $store_slug ) : ?>
        <?php echo do_shortcode( '[apparel_configurator store="' . esc_attr( $store_slug ) . '"]' ); ?>
    <?php else : ?>
    <div style="max-width:720px; margin:0 auto; text-align:center; padding:40px 0;">
        <div class="tsa-kicker">Store Loading</div>
        <h2>Team Store Coming Soon</h2>
        <p style="color:var(--tsa-muted); font-size:18px; margin:0 0 28px; line-height:1.55;">
            The <?php echo esc_html( $store_name ); ?> gear store is being set up. Reach out to get your team's store live.
        </p>
        <?php tsa_btn( '/request-a-quote/', 'Set Up Our Store', 'primary' ); ?>
    </div>
    <?php endif; ?>
</section>


<!-- Page content -->
<?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
    <?php if ( get_the_content() ) : ?>
    <section class="tsa-section">
        <div style="max-width:860px; margin:0 auto;">
            <?php the_content(); ?>
        </div>
    </section>
    <?php endif; ?>
<?php endwhile; endif; ?>


<!-- CTA -->
<section class="tsa-section tsa-cta">
    <h2>Need a Store for Your Team?</h2>
    <p>We build custom team stores for sports programs, travel squads, and recreation leagues. Online ordering, no minimums, fast turnaround.</p>
    <div class="tsa-actions tsa-actions--center">
        <?php tsa_btn( '/request-a-quote/', 'Set Up a Team Store', 'primary' ); ?>
        <?php tsa_btn( '/team-stores/', 'All Team Stores', 'outline-white' ); ?>
    </div>
</section>

<?php get_footer(); ?>
