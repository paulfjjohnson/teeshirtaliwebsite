<?php
/**
 * Template Name: TSA Graphic Design
 *
 * Graphic design services showcase — tiers, process, portfolio callout, quote.
 * Assign to: /graphic-design/
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-gd-hero">
    <div class="tsa-gd-hero__bg" aria-hidden="true"></div>
    <div class="tsa-sd-container tsa-gd-hero__inner">
        <div class="tsa-kicker tsa-kicker--light">In-House Design Team</div>
        <h1 class="tsa-gd-hero__title">Designs That<br>Make an Impact.</h1>
        <p class="tsa-gd-hero__sub">From a simple logo to a full brand package — our in-house design team brings your vision to life. Flat-rate pricing, fast turnaround, revisions included.</p>
        <div class="tsa-gd-hero__actions">
            <a href="<?php echo esc_url( home_url( '/request-a-quote/?cat=design' ) ); ?>" class="tsa-btn tsa-btn-primary">Request Design Services</a>
            <a href="#services" class="tsa-btn tsa-svh-btn-ghost">View Services ↓</a>
        </div>
        <div class="tsa-gd-hero__pills">
            <span>✓ Revisions included</span>
            <span>✓ Flat-rate pricing</span>
            <span>✓ Print-ready files</span>
            <span>✓ Quick turnaround</span>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     SERVICE TIERS
══════════════════════════════════════ -->
<section id="services" class="tsa-gd-section tsa-gd-section--light">
    <div class="tsa-sd-container">
        <div class="tsa-gd-section-head">
            <div class="tsa-kicker tsa-kicker--pink">What We Design</div>
            <h2>Design Services &amp; Pricing</h2>
            <p>Flat-rate pricing with no hourly billing surprises. Revisions included on every project.</p>
        </div>
        <div class="tsa-gd-tiers">
            <?php
            $tiers = [
                [
                    'title'    => 'Print Layout',
                    'icon'     => '👕',
                    'price'    => 'From $25',
                    'color'    => 'var(--tsa-pink)',
                    'popular'  => false,
                    'desc'     => 'Best for customers who have a logo or art and need it placed on a garment correctly — scaled, positioned, and print-ready.',
                    'includes' => [ 'Logo prep & cleanup', 'Mockup on garment', '2 revision rounds', 'Print-ready PNG + PDF', 'Same-day turnaround on most orders' ],
                    'url'      => '/request-a-quote/?cat=design&svc=print',
                ],
                [
                    'title'    => 'Logo Design',
                    'icon'     => '✏️',
                    'price'    => 'From $75',
                    'color'    => 'var(--tsa-gold)',
                    'popular'  => true,
                    'desc'     => 'A new logo or brand mark built from scratch — custom typography, icon, and color palette that works across apparel and digital.',
                    'includes' => [ 'Concept development', '3 initial directions', '3 revision rounds', 'Final AI + PNG + PDF + SVG files', 'Full color + 1-color versions' ],
                    'url'      => '/request-a-quote/?cat=design&svc=logo',
                ],
                [
                    'title'    => 'Brand Package',
                    'icon'     => '🎨',
                    'price'    => 'From $200',
                    'color'    => 'var(--tsa-purple)',
                    'popular'  => false,
                    'desc'     => 'Complete brand identity system — logo, color palette, typography, and usage guidelines. Everything you need to look consistent everywhere.',
                    'includes' => [ 'Logo (primary + secondary)', 'Full color palette', 'Typography selection', 'Brand usage guide', 'Social media kit', 'Business card design' ],
                    'url'      => '/request-a-quote/?cat=design&svc=brand',
                ],
                [
                    'title'    => 'Social & Digital',
                    'icon'     => '📱',
                    'price'    => 'From $15',
                    'color'    => '#7c3aed',
                    'popular'  => false,
                    'desc'     => 'Social media graphics, event flyers, announcements, and digital banners — designed to match your brand and ready to post.',
                    'includes' => [ 'Instagram / Facebook posts', 'Story & reel graphics', 'Event flyers & posters', 'Email header graphics', 'Delivered in correct dimensions' ],
                    'url'      => '/request-a-quote/?cat=design&svc=social',
                ],
            ];
            foreach ( $tiers as $tier ) :
            ?>
            <div class="tsa-gd-tier <?php echo $tier['popular'] ? 'tsa-gd-tier--popular' : ''; ?>"
                 style="--tier-color:<?php echo esc_attr( $tier['color'] ); ?>">
                <?php if ( $tier['popular'] ) : ?>
                <div class="tsa-gd-tier__popular-badge">Most Requested</div>
                <?php endif; ?>
                <div class="tsa-gd-tier__icon"><?php echo esc_html( $tier['icon'] ); ?></div>
                <h3 class="tsa-gd-tier__title"><?php echo esc_html( $tier['title'] ); ?></h3>
                <div class="tsa-gd-tier__price"><?php echo esc_html( $tier['price'] ); ?></div>
                <p class="tsa-gd-tier__desc"><?php echo esc_html( $tier['desc'] ); ?></p>
                <ul class="tsa-gd-tier__list">
                    <?php foreach ( $tier['includes'] as $inc ) : ?>
                    <li><?php echo esc_html( $inc ); ?></li>
                    <?php endforeach; ?>
                </ul>
                <a href="<?php echo esc_url( home_url( $tier['url'] ) ); ?>" class="tsa-gd-tier__btn">
                    Request This Service →
                </a>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="tsa-gd-tiers__note">All pricing is a starting rate. Complex projects are quoted individually. Request a design quote for a firm price before committing.</p>
    </div>
</section>

<!-- ══════════════════════════════════════
     OUR PROCESS
══════════════════════════════════════ -->
<section class="tsa-gd-section tsa-gd-section--white">
    <div class="tsa-sd-container">
        <div class="tsa-gd-section-head">
            <div class="tsa-kicker tsa-kicker--pink">How It Works</div>
            <h2>Our Design Process</h2>
            <p>From brief to final file — here's what working with us looks like.</p>
        </div>
        <div class="tsa-fr-steps">
            <?php
            $steps = [
                [ 'num' => '01', 'icon' => '📋', 'title' => 'Submit Your Brief',      'desc' => 'Fill out the design request form. Tell us what you need, the vibe you\'re going for, and any references you love.' ],
                [ 'num' => '02', 'icon' => '🎨', 'title' => 'We Create Concepts',     'desc' => 'Our designer gets to work within one business day. You\'ll receive initial concepts via email for review.' ],
                [ 'num' => '03', 'icon' => '💬', 'title' => 'Revisions',              'desc' => 'Feedback, tweaks, adjustments — revisions are included in every project so we get it exactly right.' ],
                [ 'num' => '04', 'icon' => '📁', 'title' => 'Final Files Delivered',  'desc' => 'Approved? We deliver print-ready files in all formats — AI, PDF, PNG, SVG — ready to use immediately.' ],
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
     WHAT WE CAN DO
══════════════════════════════════════ -->
<section class="tsa-gd-section tsa-gd-section--light">
    <div class="tsa-sd-container">
        <div class="tsa-gd-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Capabilities</div>
            <h2>If You Can Describe It, We Can Design It</h2>
        </div>
        <div class="tsa-gd-caps-grid">
            <?php
            $caps = [
                'Logo creation & redesign',           'Mascot & character illustration',
                'Screen print separations',           'DTF-ready full-color artwork',
                'Embroidery digitizing',              'Jersey & uniform numbering',
                'Typographic & wordmark design',      'Custom script & lettering',
                'Event flyers & posters',             'Social media graphics',
                'Merchandise mockups',                'Multi-color gang sheet layouts',
            ];
            foreach ( $caps as $cap ) :
            ?>
            <div class="tsa-gd-cap">✓ <?php echo esc_html( $cap ); ?></div>
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
                <h2>Have a design project in mind?</h2>
                <p>Tell us what you need and we'll send a quote within one business day — no commitment to move forward until you're ready.</p>
            </div>
            <div class="tsa-svh-cta__actions">
                <a href="<?php echo esc_url( home_url( '/request-a-quote/?cat=design' ) ); ?>" class="tsa-btn tsa-btn-primary">Request Design Help →</a>
                <a href="<?php echo esc_url( home_url( '/design-library/' ) ); ?>" class="tsa-btn tsa-btn-outline">Browse Design Library</a>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
