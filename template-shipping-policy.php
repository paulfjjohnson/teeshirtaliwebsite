<?php
/**
 * Template Name: TSA Shipping Policy
 *
 * Shipping policy / info page.
 * Assign to: /shipping-policy/
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="tsa-ip-hero">
    <div class="tsa-ip-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">Orders</div>
        <h1>Shipping Policy</h1>
        <p class="tsa-ip-hero__sub">How and when your Tee Shirt Ali order gets to you.</p>
        <p class="tsa-ip-hero__meta">Last updated: <?php echo esc_html( date( 'F j, Y' ) ); ?></p>
    </div>
</section>

<div class="tsa-ip-wrap tsa-ip-wrap--narrow">
    <div class="tsa-ip-prose">

        <h2>Processing Time</h2>
        <p>Shipping time is separate from production time. Most custom orders are produced within our standard turnaround before they ship — see our <a href="<?php echo esc_url( home_url( '/turnaround-times/' ) ); ?>">Turnaround Times</a> page for current production windows. Your order ships once production and quality check are complete.</p>

        <h2>Shipping Methods &amp; Rates</h2>
        <p>Shipping is calculated at checkout based on weight, order size, and destination. We ship via major carriers (USPS, UPS, and FedEx). Rush and expedited options may be available for time-sensitive orders — <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">contact us</a> before ordering if you have a hard deadline.</p>

        <h2>Local Pickup &amp; Delivery</h2>
        <p>We're based in Baton Rouge, Louisiana. Local pickup and local delivery may be available for area customers, schools, and teams. Choose the local option at checkout where offered, or ask us to arrange it.</p>

        <h2>School Stores, Team Stores &amp; Fundraisers</h2>
        <p>Store and fundraiser orders are typically produced together after the order window closes. Depending on the program, items may be bulk-shipped to the organizer for distribution or shipped individually to each buyer. The chosen method is communicated when the store is set up.</p>

        <h2>Order Tracking</h2>
        <p>When your order ships, we email a tracking number to the address on your order. You can also view order status in your account.</p>

        <h2>Delivery Issues</h2>
        <p>Please double-check your shipping address at checkout — we are not responsible for orders sent to an incorrectly entered address. If a package is lost or arrives damaged, contact us right away and we'll help resolve it with the carrier.</p>

        <h2>Questions</h2>
        <p>Need help with shipping on a specific order? Reach us through our <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">contact page</a>.</p>

    </div>
</div>

<?php get_footer(); ?>
