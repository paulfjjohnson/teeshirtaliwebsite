<?php
/**
 * Template Name: TSA Custom Apparel
 *
 * Custom apparel showcase — brands, decoration methods, sizing, quote CTA.
 * Assign to: /custom-apparel/
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-ca-hero">
    <div class="tsa-ca-hero__bg" aria-hidden="true"></div>
    <div class="tsa-sd-container tsa-ca-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">500+ Styles. Zero Limits.</div>
        <h1 class="tsa-ca-hero__title">Custom Apparel<br>Done Right.</h1>
        <p class="tsa-ca-hero__sub">From a single DTF tee to a 500-piece team order — we print what you need, when you need it. Full-color, fast turnaround, no minimums on most orders.</p>
        <div class="tsa-ca-hero__actions">
            <a href="<?php echo esc_url( home_url( '/request-a-quote/?cat=apparel' ) ); ?>" class="tsa-btn tsa-btn-primary">Get a Free Quote</a>
            <a href="<?php echo esc_url( home_url( '/configurator/' ) ); ?>" class="tsa-btn tsa-svh-btn-ghost">Open Configurator →</a>
        </div>
        <div class="tsa-ca-hero__stats">
            <div><span class="tsa-ca-stat-num">500+</span><span class="tsa-ca-stat-label">Blank Styles</span></div>
            <div class="tsa-ca-stat-div"></div>
            <div><span class="tsa-ca-stat-num">11+</span><span class="tsa-ca-stat-label">Top Brands</span></div>
            <div class="tsa-ca-stat-div"></div>
            <div><span class="tsa-ca-stat-num">3–5</span><span class="tsa-ca-stat-label">Day Turnaround</span></div>
            <div class="tsa-ca-stat-div"></div>
            <div><span class="tsa-ca-stat-num">No Min</span><span class="tsa-ca-stat-label">on DTF Orders</span></div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     DECORATION METHODS
══════════════════════════════════════ -->
<section class="tsa-ca-section tsa-ca-section--light">
    <div class="tsa-sd-container">
        <div class="tsa-ca-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Print Methods</div>
            <h2>How We Decorate</h2>
            <p>Three proven methods — we'll recommend the best one for your project.</p>
        </div>
        <div class="tsa-ca-methods-grid">
            <?php
            $methods = [
                [
                    'icon'    => '🖨️',
                    'title'   => 'DTF — Direct to Film',
                    'color'   => 'var(--tsa-pink)',
                    'best'    => 'Full color designs, no minimums, any fabric',
                    'pros'    => [ 'No minimum order — order 1 or 1,000', 'Unlimited colors at no extra cost', 'Works on virtually any fabric type', 'Soft feel, durable wash performance', 'Great for photos, gradients & complex art' ],
                    'cons'    => [ 'Slight texture vs. screen print on close inspection' ],
                ],
                [
                    'icon'    => '🎨',
                    'title'   => 'Screen Printing',
                    'color'   => 'var(--tsa-gold)',
                    'best'    => 'Large runs, spot colors, bold designs',
                    'pros'    => [ 'Most cost-effective at 24+ pieces', 'Vibrant, long-lasting ink', 'Ideal for 1–6 color designs', 'Classic, professional look', 'Best for school/team uniforms in quantity' ],
                    'cons'    => [ '6-piece minimum per design', 'Setup fee per color' ],
                ],
                [
                    'icon'    => '🧵',
                    'title'   => 'Embroidery',
                    'color'   => 'var(--tsa-purple)',
                    'best'    => 'Hats, polos, premium branded wear',
                    'pros'    => [ 'Premium, high-end appearance', 'Extremely durable — lasts the life of the garment', 'Perfect for hats, polos, and jackets', 'Professional look for corporate use', 'No fading or peeling' ],
                    'cons'    => [ 'Not ideal for fine detail or photo-realistic designs', 'Slightly higher per-unit cost' ],
                ],
            ];
            foreach ( $methods as $m ) :
            ?>
            <div class="tsa-ca-method" style="--method-color:<?php echo esc_attr($m['color']); ?>">
                <div class="tsa-ca-method__header">
                    <span class="tsa-ca-method__icon"><?php echo esc_html( $m['icon'] ); ?></span>
                    <div>
                        <h3 class="tsa-ca-method__title"><?php echo esc_html( $m['title'] ); ?></h3>
                        <span class="tsa-ca-method__best">Best for: <?php echo esc_html( $m['best'] ); ?></span>
                    </div>
                </div>
                <ul class="tsa-ca-method__pros">
                    <?php foreach ( $m['pros'] as $pro ) : ?>
                    <li>✓ <?php echo esc_html( $pro ); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php if ( ! empty( $m['cons'] ) ) : ?>
                <ul class="tsa-ca-method__cons">
                    <?php foreach ( $m['cons'] as $con ) : ?>
                    <li>— <?php echo esc_html( $con ); ?></li>
                    <?php endforeach; ?>
                </ul>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     BRAND SHOWCASE
══════════════════════════════════════ -->
<section class="tsa-ca-section tsa-ca-section--white">
    <div class="tsa-sd-container">
        <div class="tsa-ca-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Our Brands</div>
            <h2>Top Blanks We Work With</h2>
            <p>We stock and source from the industry's most trusted wholesale apparel brands.</p>
        </div>
        <div class="tsa-ca-brands-grid">
            <?php
            $brands = [
                [ 'name' => 'Bella+Canvas',         'known' => 'Super soft retail fit. The most popular tee in DTF printing.', 'styles' => '80+', 'color' => '#fec2c0' ],
                [ 'name' => 'Next Level Apparel',    'known' => 'Fashion-forward fits, incredibly soft. Great for fashion tees.', 'styles' => '60+', 'color' => '#d8a85f' ],
                [ 'name' => 'Gildan',                'known' => 'Workhorse brand. Best price per piece for high-volume orders.', 'styles' => '100+', 'color' => '#592c82' ],
                [ 'name' => 'Port & Company',        'known' => 'Mid-tier value brand, excellent for school and org orders.', 'styles' => '50+', 'color' => '#1b3464' ],
                [ 'name' => 'Independent Trading',   'known' => 'Premium heavyweight fleece. Best hoodies for spirit wear.', 'styles' => '30+', 'color' => '#dc2626' ],
                [ 'name' => 'Sport-Tek',             'known' => 'Performance athletic wear — moisture wicking, ideal for teams.', 'styles' => '40+', 'color' => '#059669' ],
                [ 'name' => 'Richardson',            'known' => 'Top-tier headwear brand. The 112 trucker is an industry staple.', 'styles' => '20+', 'color' => '#d97706' ],
                [ 'name' => 'Augusta Sportswear',    'known' => 'Sublimation-ready jerseys and team uniforms for any sport.', 'styles' => '40+', 'color' => '#0e7490' ],
            ];
            foreach ( $brands as $brand ) :
            ?>
            <div class="tsa-ca-brand-card">
                <div class="tsa-ca-brand-card__avatar" style="background:<?php echo esc_attr($brand['color']); ?>18;border:2px solid <?php echo esc_attr($brand['color']); ?>30;color:<?php echo esc_attr($brand['color']); ?>">
                    <?php echo esc_html( strtoupper( substr( $brand['name'], 0, 2 ) ) ); ?>
                </div>
                <div class="tsa-ca-brand-card__body">
                    <strong class="tsa-ca-brand-card__name"><?php echo esc_html( $brand['name'] ); ?></strong>
                    <span class="tsa-ca-brand-card__styles"><?php echo esc_html( $brand['styles'] ); ?> styles available</span>
                    <p class="tsa-ca-brand-card__desc"><?php echo esc_html( $brand['known'] ); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align:center;margin-top:32px">
            <a href="<?php echo esc_url( home_url( '/blank-apparel/' ) ); ?>" class="tsa-btn tsa-btn-outline">Browse All Blank Styles</a>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     POPULAR PRODUCT CATEGORIES
══════════════════════════════════════ -->
<section class="tsa-ca-section tsa-ca-section--light">
    <div class="tsa-sd-container">
        <div class="tsa-ca-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Product Categories</div>
            <h2>What We Print On</h2>
        </div>
        <div class="tsa-ca-cats-grid">
            <?php
            $product_cats = [
                [ 'emoji' => '👕', 'name' => 'T-Shirts',           'sub' => 'Short & long sleeve, all fits' ],
                [ 'emoji' => '🧥', 'name' => 'Hoodies & Fleece',   'sub' => 'Pullover, zip, crewneck' ],
                [ 'emoji' => '🩱', 'name' => 'Tanks & Racerbacks', 'sub' => 'Performance & fashion styles' ],
                [ 'emoji' => '🏃', 'name' => 'Athletic Wear',       'sub' => 'Moisture-wicking, sport cuts' ],
                [ 'emoji' => '🧢', 'name' => 'Hats & Caps',         'sub' => 'Structured, trucker, beanie' ],
                [ 'emoji' => '🧣', 'name' => 'Accessories',         'sub' => 'Scarves, beanies, face covers' ],
                [ 'emoji' => '👜', 'name' => 'Bags & Totes',        'sub' => 'Canvas, drawstring, backpack' ],
                [ 'emoji' => '🧸', 'name' => 'Youth & Kids',        'sub' => 'Sizes 2T through youth XL' ],
            ];
            foreach ( $product_cats as $pc ) :
            ?>
            <div class="tsa-ca-cat-pill">
                <span class="tsa-ca-cat-pill__emoji"><?php echo esc_html( $pc['emoji'] ); ?></span>
                <div>
                    <strong><?php echo esc_html( $pc['name'] ); ?></strong>
                    <span><?php echo esc_html( $pc['sub'] ); ?></span>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     SIZE GUIDE TEASER
══════════════════════════════════════ -->
<section class="tsa-ca-section tsa-ca-section--white">
    <div class="tsa-sd-container">
        <div class="tsa-ca-size-banner">
            <div class="tsa-ca-size-banner__copy">
                <h3>Not sure what size to order?</h3>
                <p>Our sizing guide covers measurements for every brand we carry — plus tips on choosing between unisex, fitted, and relaxed cuts.</p>
            </div>
            <a href="<?php echo esc_url( home_url( '/sizing-guide/' ) ); ?>" class="tsa-btn tsa-btn-outline">View Sizing Guide →</a>
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
                <h2>Ready to print your next order?</h2>
                <p>Request a quote and we'll get back with pricing and options within one business day. No commitment, no minimums on most orders.</p>
            </div>
            <div class="tsa-svh-cta__actions">
                <a href="<?php echo esc_url( home_url( '/request-a-quote/?cat=apparel' ) ); ?>" class="tsa-btn tsa-btn-primary">Get a Free Quote →</a>
                <a href="<?php echo esc_url( home_url( '/configurator/' ) ); ?>" class="tsa-btn tsa-btn-outline">Use the Configurator</a>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
