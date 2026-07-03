<?php
/**
 * Template Name: TSA Printing Capabilities
 *
 * Overview of decoration methods, materials, and print specs.
 * Assign to: /printing-capabilities/
 */

defined( 'ABSPATH' ) || exit;

get_header();

$methods = [
    [
        'icon'  => '🎨',
        'title' => 'DTF Transfers',
        'desc'  => 'Direct-to-Film prints deliver vivid, full-color designs with a soft hand and excellent wash durability — our specialty.',
        'best'  => 'Full-color art, photos, gradients, small runs',
    ],
    [
        'icon'  => '🖨️',
        'title' => 'Screen Printing',
        'desc'  => 'Cost-effective and bold for larger runs with a limited color count. Vibrant, long-lasting solid colors.',
        'best'  => 'Bulk orders, 1–4 spot colors',
    ],
    [
        'icon'  => '🧵',
        'title' => 'Embroidery',
        'desc'  => 'Premium, textured branding stitched directly into the garment. Perfect for polos, caps, and corporate wear.',
        'best'  => 'Logos, hats, polos, jackets',
    ],
    [
        'icon'  => '✨',
        'title' => 'Specialty Finishes',
        'desc'  => 'Puff, metallic, glitter, and more for designs that need to stand out and feel premium.',
        'best'  => 'Statement pieces, drops, fashion',
    ],
];

$materials = [
    '100% Cotton', 'Cotton/Poly Blends', 'Tri-Blends', '100% Polyester / Performance',
    'Fleece (Hoodies & Crews)', 'Canvas & Tote Bags', 'Headwear & Caps', 'Bags & Accessories',
];
?>

<section class="tsa-ip-hero">
    <div class="tsa-ip-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">What We Do</div>
        <h1>Printing Capabilities</h1>
        <p class="tsa-ip-hero__sub">From single custom tees to full team and school runs — here's how we bring your designs to life.</p>
    </div>
</section>

<div class="tsa-ip-wrap">

    <div class="tsa-ip-section">
        <div class="tsa-ip-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Decoration Methods</div>
            <h2>The Right Method for Every Job</h2>
            <p>We match the technique to your design, garment, and budget. Not sure which fits? We'll recommend the best option with your quote.</p>
        </div>
        <div class="tsa-ip-cards">
            <?php foreach ( $methods as $m ) : ?>
            <div class="tsa-ip-card">
                <span class="tsa-ip-card__icon"><?php echo esc_html( $m['icon'] ); ?></span>
                <h3><?php echo esc_html( $m['title'] ); ?></h3>
                <p><?php echo esc_html( $m['desc'] ); ?></p>
                <ul><li><strong>Best for:</strong> <?php echo esc_html( $m['best'] ); ?></li></ul>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tsa-ip-section">
        <div class="tsa-ip-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Materials</div>
            <h2>What We Print On</h2>
            <p>We work with trusted brands like Gildan, Bella+Canvas, Comfort Colors, Next Level, and more across a wide range of materials.</p>
        </div>
        <div class="tsa-ip-cards">
            <?php foreach ( $materials as $mat ) : ?>
            <div class="tsa-ip-card" style="padding:18px 22px">
                <p style="color:var(--text-primary);font-weight:700"><?php echo esc_html( $mat ); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>

    <div class="tsa-ip-section">
        <div class="tsa-ip-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Specs &amp; Artwork</div>
            <h2>Getting Print-Ready Results</h2>
        </div>
        <div class="tsa-ip-table-wrap">
            <table class="tsa-ip-table">
                <caption>Recommended Artwork Specs</caption>
                <thead><tr><th>Item</th><th>Recommendation</th></tr></thead>
                <tbody>
                    <tr><td>Resolution</td><td>300 DPI at actual print size</td></tr>
                    <tr><td>Best file types</td><td>Vector (AI, EPS, SVG, PDF)</td></tr>
                    <tr><td>Also accepted</td><td>High-res PNG with transparent background</td></tr>
                    <tr><td>Color mode</td><td>CMYK preferred; RGB accepted</td></tr>
                    <tr><td>Max print area (front)</td><td>Approx. 12" × 16" (varies by garment size)</td></tr>
                </tbody>
            </table>
        </div>
        <p class="tsa-ip-note">Only have a low-res logo? Send it over — our <a href="<?php echo esc_url( home_url( '/graphic-design/' ) ); ?>" style="color:var(--brand-rose)">design team</a> can clean it up or redraw it print-ready.</p>
    </div>

</div>

<section class="tsa-ip-cta">
    <div class="tsa-ip-cta__inner">
        <div>
            <h2>Have a project in mind?</h2>
            <p>Tell us what you're making and we'll recommend the best print method and a price.</p>
        </div>
        <div class="tsa-ip-cta__actions">
            <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-btn tsa-btn-primary">Request a Quote</a>
            <a href="<?php echo esc_url( home_url( '/configurator/' ) ); ?>" class="tsa-btn tsa-btn-outline-white">Start Designing</a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
