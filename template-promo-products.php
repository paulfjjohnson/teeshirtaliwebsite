<?php
/**
 * Template Name: TSA Promotional Products
 *
 * Promotional products category showcase with quote CTA.
 * Assign to: /promotional-products/
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-pp-hero">
    <div class="tsa-pp-hero__bg" aria-hidden="true"></div>
    <div class="tsa-sd-container tsa-pp-hero__inner">
        <div class="tsa-kicker tsa-kicker--light">Beyond Apparel</div>
        <h1 class="tsa-pp-hero__title">Your Logo on Everything.</h1>
        <p class="tsa-pp-hero__sub">Branded drinkware, bags, tech accessories, headwear, and more — customized with your logo and ready to represent your brand wherever they go.</p>
        <div class="tsa-pp-hero__actions">
            <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-btn tsa-btn-primary">Request a Quote</a>
            <a href="#categories" class="tsa-btn tsa-svh-btn-ghost">Browse Categories ↓</a>
        </div>
        <div class="tsa-pp-hero__tags">
            <?php foreach ( [ 'Trade Shows', 'Corporate Gifts', 'Employee Swag', 'Brand Activations', 'Client Appreciation', 'Event Giveaways' ] as $tag ) : ?>
            <span class="tsa-pp-hero__tag"><?php echo esc_html( $tag ); ?></span>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     PRODUCT CATEGORIES
══════════════════════════════════════ -->
<section id="categories" class="tsa-pp-section tsa-pp-section--light">
    <div class="tsa-sd-container">
        <div class="tsa-pp-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Product Categories</div>
            <h2>What We Can Brand for You</h2>
            <p>If it can hold a logo, we can put one on it. Here's what we work with most.</p>
        </div>
        <div class="tsa-pp-cat-grid">
            <?php
            $cats = [
                [
                    'icon'     => '☕',
                    'title'    => 'Drinkware',
                    'color'    => '#0e7490',
                    'examples' => [ 'Tumblers & Travel Mugs', 'Water Bottles (Yeti, RTIC, ORCA)', 'Coffee Mugs & Ceramic', 'Can Coolers & Koozies', 'Branded Wine Glasses' ],
                    'popular'  => 'Yeti 20oz Tumbler',
                ],
                [
                    'icon'     => '🎒',
                    'title'    => 'Bags & Totes',
                    'color'    => '#7c3aed',
                    'examples' => [ 'Backpacks & Daypacks', 'Tote Bags & Canvas Bags', 'Drawstring Bags', 'Laptop Bags & Sleeves', 'Cinch Packs' ],
                    'popular'  => 'Port Authority BG210',
                ],
                [
                    'icon'     => '💻',
                    'title'    => 'Tech & Office',
                    'color'    => '#1d4ed8',
                    'examples' => [ 'USB Flash Drives & Hubs', 'Wireless Chargers', 'Phone Wallets & Stands', 'Branded Notebooks', 'Stylus Pens & Multi-tools' ],
                    'popular'  => 'Wireless Charging Pad',
                ],
                [
                    'icon'     => '🧢',
                    'title'    => 'Headwear',
                    'color'    => '#dc2626',
                    'examples' => [ 'Structured & Unstructured Caps', 'Snapbacks & Flexfits', 'Beanies & Knit Hats', 'Visors & Sun Hats', 'Trucker Caps' ],
                    'popular'  => 'Richardson 112 Snapback',
                ],
                [
                    'icon'     => '✏️',
                    'title'    => 'Writing & Stationery',
                    'color'    => '#d97706',
                    'examples' => [ 'Branded Pens & Markers', 'Custom Notebooks & Journals', 'Sticky Notes & Notepads', 'Desk Accessories', 'Calendars' ],
                    'popular'  => 'Journal + Pen Gift Set',
                ],
                [
                    'icon'     => '🎁',
                    'title'    => 'Gift Sets & Kits',
                    'color'    => '#059669',
                    'examples' => [ 'Welcome Kits & Swag Boxes', 'Employee Onboarding Kits', 'Client Gift Packages', 'Event Goodie Bags', 'Custom Packaging & Boxes' ],
                    'popular'  => 'Custom Swag Box',
                ],
            ];
            foreach ( $cats as $cat ) :
            ?>
            <div class="tsa-pp-cat-card">
                <div class="tsa-pp-cat-card__header" style="background:linear-gradient(135deg,<?php echo esc_attr($cat['color']); ?>22,<?php echo esc_attr($cat['color']); ?>11);border-bottom:3px solid <?php echo esc_attr($cat['color']); ?>">
                    <span class="tsa-pp-cat-card__icon"><?php echo esc_html( $cat['icon'] ); ?></span>
                    <div>
                        <h3 class="tsa-pp-cat-card__title"><?php echo esc_html( $cat['title'] ); ?></h3>
                        <span class="tsa-pp-cat-card__popular">Most popular: <strong><?php echo esc_html( $cat['popular'] ); ?></strong></span>
                    </div>
                </div>
                <ul class="tsa-pp-cat-card__list">
                    <?php foreach ( $cat['examples'] as $ex ) : ?>
                    <li><?php echo esc_html( $ex ); ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-pp-cat-card__cta" style="color:<?php echo esc_attr($cat['color']); ?>">Quote This Category →</a>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     USE CASES
══════════════════════════════════════ -->
<section class="tsa-pp-section tsa-pp-section--white">
    <div class="tsa-sd-container">
        <div class="tsa-pp-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Use Cases</div>
            <h2>Where Promo Products Work Best</h2>
        </div>
        <div class="tsa-pp-use-grid">
            <?php
            $uses = [
                [ 'icon' => '🏢', 'title' => 'Corporate & B2B',     'desc' => 'Build brand recognition with employees, partners, and clients using cohesive branded merchandise.' ],
                [ 'icon' => '🎪', 'title' => 'Trade Shows',         'desc' => 'Stand out at your booth with high-value giveaways that people actually want to keep and use.' ],
                [ 'icon' => '🎓', 'title' => 'Schools & Education', 'desc' => 'Welcome kits, staff appreciation gifts, student awards, and school pride merchandise.' ],
                [ 'icon' => '🤝', 'title' => 'Client Appreciation', 'desc' => 'Send branded gift sets to top clients that reinforce your relationship and stay on their desk.' ],
                [ 'icon' => '🎉', 'title' => 'Events & Launches',   'desc' => 'Product launches, grand openings, company anniversaries — merch people take home and remember.' ],
                [ 'icon' => '💼', 'title' => 'Employee Onboarding', 'desc' => 'Make new hires feel like part of the team from day one with a branded welcome kit.' ],
            ];
            foreach ( $uses as $u ) :
            ?>
            <div class="tsa-pp-use-card">
                <span class="tsa-pp-use-card__icon"><?php echo esc_html( $u['icon'] ); ?></span>
                <h4 class="tsa-pp-use-card__title"><?php echo esc_html( $u['title'] ); ?></h4>
                <p class="tsa-pp-use-card__desc"><?php echo esc_html( $u['desc'] ); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     HOW IT WORKS
══════════════════════════════════════ -->
<section class="tsa-pp-section tsa-pp-section--light">
    <div class="tsa-sd-container">
        <div class="tsa-pp-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Our Process</div>
            <h2>How to Order Promo Products</h2>
        </div>
        <div class="tsa-fr-steps">
            <?php
            $steps = [
                [ 'num' => '01', 'icon' => '📋', 'title' => 'Tell Us What You Need',    'desc' => 'Share your product category, quantity, budget, and timeline. The more detail, the faster we can quote.' ],
                [ 'num' => '02', 'icon' => '📦', 'title' => 'We Source & Sample',       'desc' => 'We pull samples and spec sheets for your preferred items. You approve products before we finalize your order.' ],
                [ 'num' => '03', 'icon' => '🎨', 'title' => 'Artwork & Proof',          'desc' => 'We prep your logo for the decoration method and send a digital proof for your approval before production.' ],
                [ 'num' => '04', 'icon' => '🚚', 'title' => 'Production & Delivery',    'desc' => 'Your order is produced and shipped to your door or drop-shipped to multiple locations as needed.' ],
            ];
            foreach ( $steps as $s ) :
            ?>
            <div class="tsa-fr-step">
                <div class="tsa-fr-step__icon"><?php echo esc_html( $s['icon'] ); ?></div>
                <div class="tsa-fr-step__num"><?php echo esc_html( $s['num'] ); ?></div>
                <h4 class="tsa-fr-step__title"><?php echo esc_html( $s['title'] ); ?></h4>
                <p class="tsa-fr-step__desc"><?php echo esc_html( $s['desc'] ); ?></p>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     CTA
══════════════════════════════════════ -->
<section class="tsa-svh-cta">
    <div class="tsa-sd-container">
        <div class="tsa-svh-cta__inner">
            <div class="tsa-svh-cta__copy">
                <h2>Ready to brand beyond the shirt?</h2>
                <p>Request a quote for any promo product category — we'll respond within one business day with options, pricing, and samples.</p>
            </div>
            <div class="tsa-svh-cta__actions">
                <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-btn tsa-btn-primary">Request a Quote →</a>
                <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="tsa-btn tsa-btn-outline">Contact Us</a>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
