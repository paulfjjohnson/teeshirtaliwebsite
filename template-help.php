<?php
/**
 * Template Name: TSA Help
 *
 * Front-end Help Center. Renders the same articles as the wp-admin "TSA Help"
 * page (sourced from /docs/*.md). Gated to logged-in store managers/admins.
 * Assign to: /help/
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<section class="tsa-ip-hero">
    <div class="tsa-ip-hero__inner">
        <div class="tsa-kicker tsa-kicker--gold">Help Center</div>
        <h1>How your platform works</h1>
        <p class="tsa-ip-hero__sub">Guides for running your stores, designs, Tee Parties, fundraisers, and everything else in your site.</p>
    </div>
</section>

<div class="tsa-ip-wrap" style="padding-top:8px;">
    <?php
    if ( is_user_logged_in() && current_user_can( 'manage_woocommerce' ) && function_exists( 'tsa_help_render' ) ) {
        echo tsa_help_render(); // phpcs:ignore WordPress.Security.EscapeOutput — converter escapes text internally
    } else {
        ?>
        <div style="max-width:520px;margin:48px auto;text-align:center;">
            <p style="font-size:16px;color:var(--tsa-muted,#6d6268);line-height:1.6;margin:0 0 18px;">
                The Help Center is available to store managers. Please log in with a manager account to view it.
            </p>
            <a class="tsa-btn tsa-btn-primary" href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Log In</a>
        </div>
        <?php
    }
    ?>
</div>

<?php get_footer(); ?>
