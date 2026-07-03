<?php
/**
 * Template Name: TSA Terms of Service
 *
 * Terms of service (legal). Baseline content — have it reviewed by counsel.
 * Assign to: /terms-of-service/
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="tsa-ip-hero">
    <div class="tsa-ip-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">Legal</div>
        <h1>Terms of Service</h1>
        <p class="tsa-ip-hero__sub">The terms that govern your use of teeshirtali.com and our products.</p>
        <p class="tsa-ip-hero__meta">Last updated: <?php echo esc_html( date( 'F j, Y' ) ); ?></p>
    </div>
</section>

<div class="tsa-ip-wrap tsa-ip-wrap--narrow">
    <div class="tsa-ip-prose">

        <p>These Terms of Service ("Terms") govern your access to and use of teeshirtali.com (the "Site") and the products and services offered by Tee Shirt Ali ("we," "us," or "our"). By using the Site or placing an order, you agree to these Terms.</p>

        <h2>Orders &amp; Acceptance</h2>
        <p>All orders are subject to acceptance and product availability. We reserve the right to refuse or cancel any order, including for pricing errors, suspected fraud, or content that violates these Terms. A confirmed order and approved proof are required before production begins.</p>

        <h2>Pricing &amp; Payment</h2>
        <p>Prices are shown in U.S. dollars and may change without notice. Custom and bulk orders may be quoted individually. Payment is processed securely through PayPal. Production timelines begin only after full payment (or an agreed deposit) and proof approval are received.</p>

        <h2>Artwork &amp; Intellectual Property</h2>
        <ul>
            <li>You represent that you own or have permission to use any artwork, logos, names, or marks you submit, and that they do not infringe third-party rights.</li>
            <li>You grant us a limited license to reproduce your artwork solely to fulfill your order.</li>
            <li>We may decline to print content that is unlawful, infringing, or offensive.</li>
            <li>Site content, designs in our own Design Library, and branding remain our property or that of our licensors.</li>
        </ul>

        <h2>Proofs &amp; Approvals</h2>
        <p>For custom work, we provide a digital proof for your review. You are responsible for checking spelling, colors, sizing, and placement. Once you approve a proof, production proceeds based on that approved artwork.</p>

        <h2>Custom Products</h2>
        <p>Because custom and personalized items are made to order, they are generally non-returnable except in cases of our error or a defect. See our <a href="<?php echo esc_url( home_url( '/returns/' ) ); ?>">Returns Policy</a> for details.</p>

        <h2>Stores, Teams &amp; Fundraisers</h2>
        <p>School stores, team stores, and fundraisers may have specific order windows, minimums, and payout terms communicated at setup. Those program terms apply in addition to these Terms.</p>

        <h2>Limitation of Liability</h2>
        <p>To the fullest extent permitted by law, Tee Shirt Ali is not liable for indirect, incidental, or consequential damages. Our total liability for any order is limited to the amount you paid for that order.</p>

        <h2>Governing Law</h2>
        <p>These Terms are governed by the laws of the State of Louisiana, without regard to conflict-of-law principles.</p>

        <h2>Changes to These Terms</h2>
        <p>We may update these Terms at any time. Continued use of the Site after changes constitutes acceptance of the revised Terms.</p>

        <h2>Contact</h2>
        <p>Questions about these Terms? Reach us through our <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">contact page</a>.</p>

        <p class="tsa-ip-note">This is a general baseline document provided for convenience. Please have it reviewed by qualified legal counsel before publishing.</p>

    </div>
</div>

<?php get_footer(); ?>
