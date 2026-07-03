<?php
/**
 * Template Name: TSA Business Directory
 *
 * Lists TSA business / brand merch stores grouped by industry.
 * Mirrors the School + Team directories (shared .tsa-sd-* / .tsa-td-* styles).
 * Assign to: /businesses/
 */

defined( 'ABSPATH' ) || exit;

get_header();

/* ── Business roster ───────────────────────────────────────────────────────────
 * status:  'live' | 'coming-soon'
 * url:     absolute path — only set for live stores
 * color/2: primary / secondary brand hex
 * type:    subtitle shown on the card
 * ──────────────────────────────────────────────────────────────────────────── */
$business_groups = [

    'Corporate & Office' => [
        [ 'name' => 'List Your Business', 'status' => 'coming-soon', 'type' => 'Corporate Swag & Uniforms' ],
    ],

    'Restaurants & Hospitality' => [
        [ 'name' => 'List Your Business', 'status' => 'coming-soon', 'type' => 'Staff Apparel & Branded Merch' ],
    ],

    'Local Brands & Retail' => [
        [ 'name' => 'List Your Business', 'status' => 'coming-soon', 'type' => 'Retail Merch & Drops' ],
    ],

    'Nonprofits & Community' => [
        [ 'name' => 'List Your Business', 'status' => 'coming-soon', 'type' => 'Event & Volunteer Apparel' ],
    ],

];

// Count only real partner businesses (non-placeholder entries)
$live_count      = 0;
$total_businesses = 0;
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-td-hero">
    <div class="tsa-sd-container">
        <div class="tsa-kicker tsa-kicker--gold">Business & Brand Stores</div>
        <h1>Merch That Works for Your Brand</h1>
        <p class="tsa-td-hero__sub">Dedicated online merch stores for companies, restaurants, local brands, and nonprofits. Branded apparel and swag with no upfront cost and no inventory to manage.</p>
        <div class="tsa-sd-stats">
            <div class="tsa-sd-stat">
                <span class="tsa-sd-stat__num"><?php echo (int) $total_businesses; ?></span>
                <span class="tsa-sd-stat__label">Partner Brands</span>
            </div>
            <div class="tsa-sd-stat-divider"></div>
            <div class="tsa-sd-stat">
                <span class="tsa-sd-stat__num tsa-sd-stat__num--live"><?php echo (int) $live_count; ?></span>
                <span class="tsa-sd-stat__label">Stores Live Now</span>
            </div>
            <div class="tsa-sd-stat-divider"></div>
            <div class="tsa-sd-stat">
                <span class="tsa-sd-stat__num">4</span>
                <span class="tsa-sd-stat__label">Industries Served</span>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     WHAT WE DO FOR BUSINESSES
══════════════════════════════════════ -->
<section class="tsa-td-spotlight-wrap">
    <div class="tsa-sd-container">
        <div class="tsa-td-spotlight">
            <div class="tsa-td-spotlight__body">
                <div class="tsa-td-spotlight__name">Your Brand, On Everything</div>
                <div class="tsa-td-spotlight__sub">Employee uniforms, customer giveaways, event merch, and online swag stores — we handle production and fulfillment so you don't carry inventory.</div>
                <div class="tsa-td-spotlight__tags">
                    <span class="tsa-td-tag tsa-td-tag--live">Branded Apparel</span>
                    <span class="tsa-td-tag tsa-td-tag--live">Promo Products</span>
                    <span class="tsa-td-tag">Online Swag Stores</span>
                </div>
            </div>
            <a href="<?php echo esc_url( home_url( '/request-a-store/?type=business' ) ); ?>" class="tsa-td-spotlight__cta">
                Start a Store →
            </a>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     BUSINESS GROUPS
══════════════════════════════════════ -->
<section class="tsa-td-directory">
    <div class="tsa-sd-container">

        <?php foreach ( $business_groups as $industry => $businesses ) :
            $group_live  = count( array_filter( $businesses, function( $b ) { return $b['status'] === 'live'; } ) );
            $group_total = count( $businesses );
        ?>
        <div class="tsa-sd-group">
            <div class="tsa-sd-group__header">
                <h2 class="tsa-sd-group__title"><?php echo esc_html( $industry ); ?></h2>
                <span class="tsa-sd-group__count">
                    <?php echo (int) $group_total; ?> <?php echo $group_total === 1 ? 'brand' : 'brands'; ?>
                    <?php if ( $group_live > 0 ) : ?>
                    &nbsp;·&nbsp; <span class="tsa-sd-count-live"><?php echo (int) $group_live; ?> live</span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="tsa-sd-grid">
                <?php foreach ( $businesses as $biz ) :
                    $is_live  = $biz['status'] === 'live';
                    $color1   = isset( $biz['color']  ) ? $biz['color']  : 'var(--tsa-gold)';
                    $color2   = isset( $biz['color2'] ) ? $biz['color2'] : '#c0c0c0';
                    $initials = '';
                    $words    = explode( ' ', $biz['name'] );
                    foreach ( $words as $w ) {
                        if ( ! in_array( strtolower( $w ), [ 'your', 'business', 'here', 'list' ], true ) ) {
                            $initials .= strtoupper( substr( $w, 0, 1 ) );
                        }
                        if ( strlen( $initials ) >= 2 ) break;
                    }
                    if ( empty( $initials ) ) $initials = strtoupper( substr( $biz['name'], 0, 2 ) );
                ?>

                <?php if ( $is_live ) : ?>
                <a href="<?php echo esc_url( home_url( $biz['url'] ) ); ?>" class="tsa-sd-card tsa-sd-card--live">
                    <div class="tsa-sd-card__stripe" style="background:linear-gradient(90deg,<?php echo esc_attr($color1); ?>,<?php echo esc_attr($color2); ?>)"></div>
                    <div class="tsa-sd-card__avatar" style="background:<?php echo esc_attr($color1); ?>;color:#fff">
                        <?php echo esc_html( $initials ); ?>
                    </div>
                    <div class="tsa-sd-card__body">
                        <div class="tsa-sd-card__name"><?php echo esc_html( $biz['name'] ); ?></div>
                        <?php if ( isset( $biz['type'] ) ) : ?>
                        <div class="tsa-td-sport-badge"><?php echo esc_html( $biz['type'] ); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="tsa-sd-card__right">
                        <div class="tsa-sd-card__status tsa-sd-card__status--live">
                            <span class="tsa-sd-dot"></span> Live
                        </div>
                        <div class="tsa-sd-card__arrow">→</div>
                    </div>
                </a>

                <?php else : ?>
                <div class="tsa-sd-card tsa-sd-card--soon">
                    <div class="tsa-sd-card__body">
                        <div class="tsa-sd-card__name"><?php echo esc_html( $biz['name'] ); ?></div>
                        <?php if ( isset( $biz['type'] ) ) : ?>
                        <div class="tsa-td-sport-badge"><?php echo esc_html( $biz['type'] ); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="tsa-sd-card__right">
                        <div class="tsa-sd-card__status tsa-sd-card__status--soon">Soon</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</section>

<!-- ══════════════════════════════════════
     CTA — START A BUSINESS STORE
══════════════════════════════════════ -->
<section class="tsa-td-cta">
    <div class="tsa-sd-container">
        <div class="tsa-td-cta__inner">
            <div class="tsa-td-cta__copy">
                <h2>Ready to put your brand to work?</h2>
                <p>We set up branded merch stores and swag programs for businesses of every size. No upfront cost, no inventory — just professional merch with fast turnaround.</p>
            </div>
            <div class="tsa-td-cta__actions">
                <a href="<?php echo esc_url( home_url( '/request-a-store/?type=business' ) ); ?>" class="tsa-btn tsa-btn-primary">Start a Business Store</a>
                <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-btn tsa-btn-outline">Request a Quote</a>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
