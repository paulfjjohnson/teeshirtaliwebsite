<?php
/**
 * Template Name: TSA Spirit Wear
 *
 * Spirit wear drops, seasonal collections, and school/team gear.
 * Assign to: /spirit-wear/
 */

defined( 'ABSPATH' ) || exit;

get_header();

/* ── Active season drop ── */
$current_season = 'Fall / Football Season';
$season_color   = '#d97706';   // amber for fall
$season_color2  = '#92400e';
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-sw-hero">
    <div class="tsa-sw-hero__bg" aria-hidden="true"></div>
    <div class="tsa-sd-container tsa-sw-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">New Drops Every Season</div>
        <h1 class="tsa-sw-hero__title">Rep Your Team.<br>Rep Your School.</h1>
        <p class="tsa-sw-hero__sub">Seasonal spirit wear drops with limited-edition designs for schools, teams, and fan sections. New collections every semester — shop before they're gone.</p>
        <div class="tsa-sw-hero__actions">
            <a href="<?php echo esc_url( home_url( '/schools/' ) ); ?>" class="tsa-btn tsa-btn-primary">Shop School Stores</a>
            <a href="<?php echo esc_url( home_url( '/request-a-quote/?cat=apparel' ) ); ?>" class="tsa-btn tsa-svh-btn-ghost">Start a Drop →</a>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     CURRENT SEASON BANNER
══════════════════════════════════════ -->
<div class="tsa-sw-season-banner" style="background:linear-gradient(90deg,<?php echo esc_attr($season_color); ?>,<?php echo esc_attr($season_color2); ?>)">
    <div class="tsa-sd-container tsa-sw-season-banner__inner">
        <span class="tsa-sw-season-banner__label">🏈 Current Season Drop</span>
        <span class="tsa-sw-season-banner__name"><?php echo esc_html( $current_season ); ?></span>
        <a href="<?php echo esc_url( home_url( '/schools/' ) ); ?>" class="tsa-sw-season-banner__link">Shop Now →</a>
    </div>
</div>

<!-- ══════════════════════════════════════
     SPIRIT WEAR CATEGORIES
══════════════════════════════════════ -->
<section class="tsa-sw-section tsa-sw-section--light">
    <div class="tsa-sd-container">
        <div class="tsa-sw-section-head">
            <div class="tsa-kicker tsa-kicker--pink">What We Offer</div>
            <h2>Spirit Wear for Every Occasion</h2>
            <p>From student sections to faculty staff shirts — we outfit every part of your school or team.</p>
        </div>
        <div class="tsa-sw-cat-grid">
            <?php
            $cats = [
                [ 'icon' => '🏈', 'title' => 'Game Day Gear',         'desc' => 'Student section tees, fan hoodies, and game-day apparel that builds hype in the stands.', 'color' => '#dc2626', 'url' => '/schools/' ],
                [ 'icon' => '🎺', 'title' => 'Band & Guard',           'desc' => 'Matching spirit wear for marching bands, color guard, and performance arts programs.', 'color' => '#592c82', 'url' => '/schools/dutchtown/' ],
                [ 'icon' => '📚', 'title' => 'Staff & Faculty',        'desc' => 'Branded polos, tees, and pullovers for teachers, coaches, and administration staff.', 'color' => '#1d4ed8', 'url' => '/request-a-quote/?cat=apparel' ],
                [ 'icon' => '🏆', 'title' => 'Championship Merch',     'desc' => 'Celebrate the win. We produce championship tees fast — same-day artwork, 48hr turnaround.', 'color' => '#d97706', 'url' => '/request-a-quote/?cat=apparel' ],
                [ 'icon' => '👩‍👧', 'title' => 'Booster & PTG',          'desc' => 'Custom shirts for booster clubs, PTG, and parent organizations that support the school.', 'color' => '#059669', 'url' => '/request-a-quote/?cat=apparel' ],
                [ 'icon' => '🎓', 'title' => 'Senior & Graduation',    'desc' => 'Senior class shirts, graduation apparel, and class-of merch for the big milestone.', 'color' => '#7c3aed', 'url' => '/request-a-quote/?cat=apparel' ],
            ];
            foreach ( $cats as $cat ) :
            ?>
            <a href="<?php echo esc_url( home_url( $cat['url'] ) ); ?>" class="tsa-sw-cat-card">
                <div class="tsa-sw-cat-card__bar" style="background:<?php echo esc_attr( $cat['color'] ); ?>"></div>
                <span class="tsa-sw-cat-card__icon"><?php echo esc_html( $cat['icon'] ); ?></span>
                <h3 class="tsa-sw-cat-card__title"><?php echo esc_html( $cat['title'] ); ?></h3>
                <p class="tsa-sw-cat-card__desc"><?php echo esc_html( $cat['desc'] ); ?></p>
                <span class="tsa-sw-cat-card__link" style="color:<?php echo esc_attr( $cat['color'] ); ?>">Shop Now →</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     POPULAR ITEMS
══════════════════════════════════════ -->
<section class="tsa-sw-section tsa-sw-section--white">
    <div class="tsa-sd-container">
        <div class="tsa-sw-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Top Sellers</div>
            <h2>Most Popular Spirit Wear Items</h2>
            <p>These are the styles fans reach for first — and they're all fully customizable.</p>
        </div>
        <div class="tsa-sw-items-grid">
            <?php
            $items = [
                [ 'icon' => '👕', 'name' => 'Classic Tee',          'brands' => 'Bella+Canvas 3001 · Next Level 3600', 'from' => '$14',  'color' => '#fec2c0' ],
                [ 'icon' => '🧥', 'name' => 'Pullover Hoodie',       'brands' => 'Gildan 18500 · Independent Trading SS4500', 'from' => '$28', 'color' => '#d8a85f' ],
                [ 'icon' => '🩱', 'name' => 'Long Sleeve Tee',       'brands' => 'Bella+Canvas 3501 · Next Level 3601', 'from' => '$18',  'color' => '#592c82' ],
                [ 'icon' => '🧢', 'name' => 'Structured Hat',        'brands' => 'Richardson 112 · Otto Cap', 'from' => '$20',              'color' => '#1b3464' ],
                [ 'icon' => '🏃', 'name' => 'Performance Tee',       'brands' => 'Sport-Tek ST350 · A4 N3264', 'from' => '$16',             'color' => '#dc2626' ],
                [ 'icon' => '👜', 'name' => 'Spirit Bag / Tote',     'brands' => 'Liberty Bags · Augusta Sportswear', 'from' => '$12',     'color' => '#059669' ],
            ];
            foreach ( $items as $item ) :
            ?>
            <div class="tsa-sw-item-card">
                <div class="tsa-sw-item-card__visual" style="background:<?php echo esc_attr( $item['color'] ); ?>22;border-color:<?php echo esc_attr( $item['color'] ); ?>33">
                    <span class="tsa-sw-item-card__icon"><?php echo esc_html( $item['icon'] ); ?></span>
                </div>
                <div class="tsa-sw-item-card__body">
                    <div class="tsa-sw-item-card__name"><?php echo esc_html( $item['name'] ); ?></div>
                    <div class="tsa-sw-item-card__brands"><?php echo esc_html( $item['brands'] ); ?></div>
                    <div class="tsa-sw-item-card__from">From <strong style="color:var(--tsa-pink)"><?php echo esc_html( $item['from'] ); ?></strong> / each</div>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
        <div style="text-align:center;margin-top:32px">
            <a href="<?php echo esc_url( home_url( '/blank-apparel/' ) ); ?>" class="tsa-btn tsa-btn-outline">Browse All Styles</a>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     START A DROP
══════════════════════════════════════ -->
<section class="tsa-sw-section tsa-sw-section--dark">
    <div class="tsa-sd-container">
        <div class="tsa-sw-drop-inner">
            <div class="tsa-sw-drop-copy">
                <div class="tsa-kicker" style="background:rgba(254,194,192,.12);color:var(--tsa-pink);border:1px solid rgba(254,194,192,.25)">Start a Drop</div>
                <h2 class="tsa-sw-drop-title">Want Your Own<br>Spirit Wear Drop?</h2>
                <p class="tsa-sw-drop-sub">We'll create a limited-edition collection for your school, team, or organization — design included, no upfront cost. Drops open for 2–4 weeks, then close and ship.</p>
                <ul class="tsa-sw-drop-list">
                    <li>✓ Custom design created by our team</li>
                    <li>✓ Online store opens in 3–5 business days</li>
                    <li>✓ No minimum order quantities</li>
                    <li>✓ Orders ship directly to buyers</li>
                </ul>
                <a href="<?php echo esc_url( home_url( '/request-a-store/' ) ); ?>" class="tsa-btn tsa-btn-primary" style="margin-top:8px">Request a Drop Store →</a>
            </div>
            <div class="tsa-sw-drop-graphic" aria-hidden="true">
                <div class="tsa-sw-drop-badge">
                    <span class="tsa-sw-drop-badge__top">Limited</span>
                    <span class="tsa-sw-drop-badge__num">DROP</span>
                    <span class="tsa-sw-drop-badge__sub">New every season</span>
                </div>
            </div>
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
                <h2>Ready to gear up your school or team?</h2>
                <p>Request a quote and we'll have your spirit wear store live in under a week — custom designed, zero upfront cost.</p>
            </div>
            <div class="tsa-svh-cta__actions">
                <a href="<?php echo esc_url( home_url( '/request-a-quote/?cat=apparel' ) ); ?>" class="tsa-btn tsa-btn-primary">Get a Quote</a>
                <a href="<?php echo esc_url( home_url( '/schools/' ) ); ?>" class="tsa-btn tsa-btn-outline">View School Stores</a>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
