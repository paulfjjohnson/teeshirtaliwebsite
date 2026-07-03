<?php
/**
 * Template Name: TSA Turnaround Times
 *
 * Production turnaround expectations by order type, plus rush info.
 * Assign to: /turnaround-times/
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="tsa-ip-hero">
    <div class="tsa-ip-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">Timing</div>
        <h1>Turnaround Times</h1>
        <p class="tsa-ip-hero__sub">How long production takes before your order ships. Turnaround starts after proof approval and payment.</p>
    </div>
</section>

<div class="tsa-ip-wrap tsa-ip-wrap--narrow">

    <div class="tsa-ip-section">
        <div class="tsa-ip-table-wrap">
            <table class="tsa-ip-table">
                <caption>Standard Production Turnaround (business days)</caption>
                <thead><tr><th>Order Type</th><th>Typical Turnaround</th></tr></thead>
                <tbody>
                    <tr><td>DTF transfers (ship only)</td><td>2–4 business days</td></tr>
                    <tr><td>Custom apparel (printed)</td><td>5–7 business days</td></tr>
                    <tr><td>Embroidery</td><td>7–10 business days</td></tr>
                    <tr><td>Bulk / large orders</td><td>7–14 business days</td></tr>
                    <tr><td>Promotional products</td><td>10–15 business days</td></tr>
                </tbody>
            </table>
        </div>
        <p class="tsa-ip-note">These are production windows only and don't include shipping transit time. See our <a href="<?php echo esc_url( home_url( '/shipping-policy/' ) ); ?>" style="color:var(--brand-rose)">Shipping Policy</a> for delivery details.</p>
    </div>

    <div class="tsa-ip-section">
        <div class="tsa-ip-section-head">
            <div class="tsa-kicker tsa-kicker--pink">What Affects Timing</div>
            <h2>What Can Speed Things Up — or Slow Them Down</h2>
        </div>
        <div class="tsa-ip-cards">
            <div class="tsa-ip-card">
                <span class="tsa-ip-card__icon">✅</span>
                <h3>Speeds It Up</h3>
                <ul>
                    <li>Print-ready artwork on the first send</li>
                    <li>Fast proof approval</li>
                    <li>In-stock garments &amp; standard sizes</li>
                </ul>
            </div>
            <div class="tsa-ip-card">
                <span class="tsa-ip-card__icon">⏳</span>
                <h3>Adds Time</h3>
                <ul>
                    <li>Artwork that needs cleanup or redraw</li>
                    <li>Delayed proof approval</li>
                    <li>Special-order garments or peak season</li>
                </ul>
            </div>
            <div class="tsa-ip-card">
                <span class="tsa-ip-card__icon">⚡</span>
                <h3>Need It Faster?</h3>
                <ul>
                    <li>Rush production may be available</li>
                    <li>Tell us your deadline up front</li>
                    <li>Local pickup saves shipping days</li>
                </ul>
            </div>
        </div>
    </div>

    <div class="tsa-ip-section">
        <div class="tsa-ip-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Stores &amp; Fundraisers</div>
            <h2>Store &amp; Fundraiser Timing</h2>
            <p>School stores, team stores, and fundraisers run on order windows. Production typically begins after the window closes, and standard turnaround applies from there. Your store's specific dates are shared at setup.</p>
        </div>
    </div>

    <p class="tsa-ip-note">On a deadline? <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" style="color:var(--brand-rose)">Contact us</a> before you order and we'll confirm whether we can hit your date.</p>

</div>

<section class="tsa-ip-cta">
    <div class="tsa-ip-cta__inner">
        <div>
            <h2>Working against a deadline?</h2>
            <p>Tell us when you need it and we'll let you know what's possible.</p>
        </div>
        <div class="tsa-ip-cta__actions">
            <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-btn tsa-btn-primary">Request a Quote</a>
            <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="tsa-btn tsa-btn-outline-white">Contact Us</a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
