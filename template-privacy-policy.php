<?php
/**
 * Template Name: TSA Privacy Policy
 *
 * Privacy policy (legal). Baseline content — have it reviewed by counsel.
 * Assign to: /privacy-policy/
 */

defined( 'ABSPATH' ) || exit;

get_header();

// Identity tokens (from Business Profile; fall back to the TSA reference values).
$biz_name   = function_exists( 'tsa_biz' ) ? tsa_biz( 'name' ) : 'Tee Shirt Ali';
$biz_domain = preg_replace( '#^www\.#', '', (string) wp_parse_url( home_url(), PHP_URL_HOST ) ) ?: 'teeshirtali.com';
?>

<section class="tsa-ip-hero">
    <div class="tsa-ip-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">Legal</div>
        <h1>Privacy Policy</h1>
        <p class="tsa-ip-hero__sub">How <?php echo esc_html( $biz_name ); ?> collects, uses, and protects your information.</p>
        <p class="tsa-ip-hero__meta">Last updated: <?php echo esc_html( date( 'F j, Y' ) ); ?></p>
    </div>
</section>

<div class="tsa-ip-wrap tsa-ip-wrap--narrow">
    <div class="tsa-ip-prose">

        <p><?php echo esc_html( $biz_name ); ?> ("we," "us," or "our") operates <?php echo esc_html( $biz_domain ); ?> (the "Site"). This Privacy Policy explains what information we collect, how we use it, and the choices you have. By using the Site or placing an order, you agree to the practices described here.</p>

        <h2>Information We Collect</h2>
        <p>We collect information you provide directly to us, including:</p>
        <ul>
            <li><strong>Order &amp; account details</strong> — name, email, phone, billing and shipping addresses.</li>
            <li><strong>Design &amp; project information</strong> — artwork, logos, store/team details, and any notes you submit through quotes, the configurator, or store requests.</li>
            <li><strong>Payment information</strong> — processed securely by our payment provider (PayPal). We do not store full card numbers on our servers.</li>
            <li><strong>Communications</strong> — messages you send us by email, contact form, or phone.</li>
        </ul>
        <p>We also automatically collect limited technical data such as IP address, browser type, device information, and pages visited, through cookies and similar technologies.</p>

        <h2>How We Use Your Information</h2>
        <ul>
            <li>To process, fulfill, and ship your orders.</li>
            <li>To create and manage school stores, team stores, and fundraisers.</li>
            <li>To respond to quotes, questions, and support requests.</li>
            <li>To send order updates and, where you have opted in, marketing communications.</li>
            <li>To improve the Site, prevent fraud, and meet legal obligations.</li>
        </ul>

        <h2>How We Share Information</h2>
        <p>We do not sell your personal information. We share it only with:</p>
        <ul>
            <li><strong>Service providers</strong> — payment processors, print/fulfillment partners, shipping carriers, and hosting/analytics vendors who help us operate.</li>
            <li><strong>Legal authorities</strong> — when required by law or to protect our rights.</li>
        </ul>

        <h2>Cookies</h2>
        <p>We use cookies to keep your cart and session working, remember preferences, and understand how the Site is used. You can control cookies through your browser settings; disabling them may affect Site functionality.</p>

        <h2>Data Retention &amp; Security</h2>
        <p>We retain order and account information for as long as needed to provide our services and comply with legal, tax, and accounting requirements. We use reasonable administrative and technical safeguards to protect your information, though no method of transmission is 100% secure.</p>

        <h2>Your Choices</h2>
        <ul>
            <li>You may request access to, correction of, or deletion of your personal information.</li>
            <li>You may unsubscribe from marketing emails at any time using the link in each message.</li>
        </ul>

        <h2>Children's Privacy</h2>
        <p>The Site is not directed to children under 13, and we do not knowingly collect their personal information.</p>

        <h2>Changes to This Policy</h2>
        <p>We may update this Privacy Policy from time to time. The "Last updated" date above reflects the most recent revision.</p>

        <h2>Contact Us</h2>
        <p>Questions about this policy? Reach us through our <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">contact page</a>.</p>

        <p class="tsa-ip-note">This is a general baseline policy provided for convenience. Please have it reviewed by qualified legal counsel before publishing to ensure it fits your business and complies with applicable laws (e.g., CCPA, GDPR).</p>

    </div>
</div>

<?php get_footer(); ?>
