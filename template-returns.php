<?php
/**
 * Template Name: TSA Returns Policy
 *
 * Returns & exchanges policy.
 * Assign to: /returns/
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="tsa-ip-hero">
    <div class="tsa-ip-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">Orders</div>
        <h1>Returns &amp; Exchanges</h1>
        <p class="tsa-ip-hero__sub">We stand behind our work. Here's how returns and exchanges work.</p>
        <p class="tsa-ip-hero__meta">Last updated: <?php echo esc_html( date( 'F j, Y' ) ); ?></p>
    </div>
</section>

<div class="tsa-ip-wrap tsa-ip-wrap--narrow">
    <div class="tsa-ip-prose">

        <h2>Custom &amp; Personalized Items</h2>
        <p>Because custom-printed and personalized products are made to order specifically for you, they generally cannot be returned or exchanged for buyer's remorse, ordering the wrong size, or a change of mind. Please review your proof, sizing, and quantities carefully before approving production.</p>

        <h2>If Something's Wrong</h2>
        <p>We absolutely make it right when the fault is ours. You're eligible for a reprint, replacement, or refund if:</p>
        <ul>
            <li>The item is defective or misprinted.</li>
            <li>You received the wrong item, size, or design versus your approved proof.</li>
            <li>There is a clear quality issue with the blank or the print.</li>
        </ul>

        <h2>How to Start a Claim</h2>
        <p>Contact us within <strong>7 days</strong> of delivery with your order number and clear photos of the issue. Reach us through our <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">contact page</a>. We'll review and respond promptly with the best resolution.</p>

        <h2>Blank Apparel &amp; Stock Items</h2>
        <p>Unworn, unwashed, undecorated blank items may be eligible for return within <strong>14 days</strong> in original condition. Return shipping may apply. Contact us first to confirm eligibility before sending anything back.</p>

        <h2>Sizing</h2>
        <p>Ordering the wrong size is the most common avoidable issue. Check our <a href="<?php echo esc_url( home_url( '/sizing-guide/' ) ); ?>">Sizing Guide</a> before you order, and ask us for a sample if you're unsure on a large order.</p>

        <h2>Refunds</h2>
        <p>Approved refunds are issued to your original payment method. Depending on your bank, it may take several business days to appear.</p>

        <h2>Questions</h2>
        <p>Not sure if your situation qualifies? Just ask — reach us through our <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">contact page</a> and we'll help.</p>

    </div>
</div>

<?php get_footer(); ?>
