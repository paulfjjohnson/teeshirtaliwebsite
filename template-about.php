<?php
/**
 * Template Name: TSA About
 *
 * Brand story + values + impact + how-we-work + CTA.
 * Uses the shared .tsa-ip-* components (tsa-info-pages.css, loaded sitewide)
 * plus a few .tsa-about-* helpers. Icons reuse tsa_mega_icon().
 * Assign to: /about/
 */

defined( 'ABSPATH' ) || exit;

get_header();

$ico = function ( $k ) { return function_exists( 'tsa_mega_icon' ) ? tsa_mega_icon( $k ) : ''; };

$values = [
    [ 'heart',     'Community First',     'We started in Baton Rouge to help local schools, teams, and businesses look good and rally their people. Your community is the point.' ],
    [ 'pen',       'Design-Driven',       'From a rough idea to print-ready art, our design-first flow means every order starts with something worth wearing.' ],
    [ 'gift',      'Zero-Risk Stores',    'School, team, and fundraiser stores with no upfront cost and no inventory. Your group earns; we handle the rest.' ],
    [ 'printer',   'Quality That Lasts',  'Vivid, durable DTF prints and trusted blanks. We sweat the details so your gear holds up wash after wash.' ],
    [ 'sparkles',  'Built for Spirit',    'Limited Tee Party drops, spirit wear, and seasonal collections that keep your crowd excited and coming back.' ],
    [ 'users',     'Local & Personal',    'Real people you can reach. We answer fast, we proof everything, and we treat your order like it is our own.' ],
];

$stats = [
    [ '32',   'Ascension Schools' ],
    [ '$0',   'Upfront for Stores' ],
    [ '100%', 'Online Ordering' ],
    [ '1', 'Business Day Replies' ],
];
?>

<section class="tsa-ip-hero">
    <div class="tsa-ip-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">Our Story</div>
        <h1>Custom Apparel.<br>School Spirit. Local Heart.</h1>
        <p class="tsa-ip-hero__sub">Tee Shirt Ali is a Baton Rouge custom-apparel and DTF-printing studio built to give schools, teams, businesses, and communities a better, easier way to make merch people actually want to wear.</p>
    </div>
</section>

<div class="tsa-ip-wrap">

    <!-- Story -->
    <div class="tsa-ip-section tsa-about-story">
        <div>
            <div class="tsa-kicker tsa-kicker--pink">Who We Are</div>
            <h2>Made for your community — not a faceless catalog.</h2>
        </div>
        <div class="tsa-about-story__body">
            <p>We're a local team that lives and works in Ascension Parish. We saw schools and groups struggling with clunky order forms, big minimums, and inventory risk — so we built a platform that fixes all of it.</p>
            <p>Today, Tee Shirt Ali powers dedicated online stores for schools, teams, and businesses, an exclusive design configurator, a growing design library, and limited-time Tee Party drops — all backed by in-house DTF printing and a design team that treats your art like it matters.</p>
            <p>No upfront cost. No inventory to manage. Just great-looking gear, fast turnaround, and people who pick up the phone.</p>
        </div>
    </div>

    <!-- Impact stats -->
    <div class="tsa-ip-section">
        <div class="tsa-about-stats">
            <?php foreach ( $stats as $s ) : ?>
            <div class="tsa-about-stat">
                <span class="tsa-about-stat__num"><?php echo esc_html( $s[0] ); ?></span>
                <span class="tsa-about-stat__label"><?php echo esc_html( $s[1] ); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- Values -->
    <div class="tsa-ip-section">
        <div class="tsa-ip-section-head">
            <div class="tsa-kicker tsa-kicker--pink">What We Stand For</div>
            <h2>The values behind every order</h2>
        </div>
        <div class="tsa-ip-cards">
            <?php foreach ( $values as $v ) : ?>
            <div class="tsa-ip-card">
                <span class="tsa-about-ico" aria-hidden="true"><?php echo $ico( $v[0] ); // phpcs:ignore ?></span>
                <h3><?php echo esc_html( $v[1] ); ?></h3>
                <p><?php echo esc_html( $v[2] ); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <!-- How we work -->
    <div class="tsa-ip-section">
        <div class="tsa-ip-section-head">
            <div class="tsa-kicker tsa-kicker--pink">How It Works</div>
            <h2>From idea to doorstep — without the headache</h2>
        </div>
        <div class="tsa-ip-cards">
            <div class="tsa-ip-card">
                <span class="tsa-about-ico" aria-hidden="true"><?php echo $ico( 'swatch' ); // phpcs:ignore ?></span>
                <h3>1 · Pick or Design</h3>
                <p>Browse the design library, request a custom piece, or build it live in the configurator on any apparel.</p>
            </div>
            <div class="tsa-ip-card">
                <span class="tsa-about-ico" aria-hidden="true"><?php echo $ico( 'shirt' ); // phpcs:ignore ?></span>
                <h3>2 · We Print</h3>
                <p>We proof your art, then print and press in-house with vivid, wash-durable DTF on quality blanks.</p>
            </div>
            <div class="tsa-ip-card">
                <span class="tsa-about-ico" aria-hidden="true"><?php echo $ico( 'gift' ); // phpcs:ignore ?></span>
                <h3>3 · You Get It Fast</h3>
                <p>Ship to your door or distribute to your group. Stores and fundraisers pay out automatically — zero risk.</p>
            </div>
        </div>
    </div>

</div>

<!-- CTA -->
<section class="tsa-ip-cta">
    <div class="tsa-ip-cta__inner">
        <div>
            <h2>Let's make something worth wearing.</h2>
            <p>Tell us about your school, team, business, or event — we'll get you a plan and a price fast.</p>
        </div>
        <div class="tsa-ip-cta__actions">
            <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-btn tsa-btn-primary">Request a Quote</a>
            <a href="<?php echo esc_url( home_url( '/configurator/' ) ); ?>" class="tsa-btn tsa-btn-outline-white">Start Designing</a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
