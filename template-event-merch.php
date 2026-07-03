<?php
/**
 * Template Name: TSA Event Merchandise
 *
 * Event merchandise landing page with event type grid and quote integration.
 * Assign to: /event-merchandise/
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-em-hero">
    <div class="tsa-em-hero__bg" aria-hidden="true"></div>
    <div class="tsa-sd-container tsa-em-hero__inner">
        <div class="tsa-kicker tsa-kicker--light">Events Made Memorable</div>
        <h1 class="tsa-em-hero__title">Custom Merch for<br>Every Event.</h1>
        <p class="tsa-em-hero__sub">From 50-person reunions to 5,000-person concerts — we handle the design, printing, and logistics so your event merch is ready when you need it.</p>
        <div class="tsa-em-hero__actions">
            <a href="<?php echo esc_url( home_url( '/request-a-quote/?cat=apparel' ) ); ?>" class="tsa-btn tsa-btn-primary">Start Your Event Quote</a>
            <a href="#event-types" class="tsa-btn tsa-svh-btn-ghost">See Event Types ↓</a>
        </div>
        <div class="tsa-em-hero__pills">
            <span>✓ Fast turnaround</span>
            <span>✓ No minimums</span>
            <span>✓ Design included</span>
            <span>✓ Rush available</span>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     EVENT TYPES
══════════════════════════════════════ -->
<section id="event-types" class="tsa-em-section tsa-em-section--light">
    <div class="tsa-sd-container">
        <div class="tsa-em-section-head">
            <div class="tsa-kicker tsa-kicker--pink">Event Types</div>
            <h2>We've Got You Covered</h2>
            <p>Whatever the occasion — we've produced merch for it.</p>
        </div>
        <div class="tsa-em-type-grid">
            <?php
            $types = [
                [ 'icon' => '🎓', 'title' => 'Graduations',          'color' => '#7c3aed', 'desc' => 'Senior shirts, graduation tees, class-of merch, and staff appreciation gifts. Fast turnaround for the big day.', 'url' => '/request-a-quote/?cat=apparel' ],
                [ 'icon' => '🎵', 'title' => 'Concerts & Festivals', 'color' => '#dc2626', 'desc' => 'Band tees, festival gear, and limited-edition drops for live events. Screen print or DTF — we handle volume.', 'url' => '/request-a-quote/?cat=apparel' ],
                [ 'icon' => '🏢', 'title' => 'Corporate Events',     'color' => '#1d4ed8', 'desc' => 'Company retreats, team-building events, product launches, and trade show branded apparel and giveaways.', 'url' => '/request-a-quote/?cat=apparel' ],
                [ 'icon' => '👨‍👩‍👧', 'title' => 'Family Reunions',   'color' => '#059669', 'desc' => 'Custom family reunion shirts with names, dates, and custom artwork. Affordable pricing for any size group.', 'url' => '/request-a-quote/?cat=apparel' ],
                [ 'icon' => '🤲', 'title' => 'Charity & Fundraisers','color' => '#d97706', 'desc' => 'Awareness walk shirts, charity drive merch, and cause-branded apparel that makes supporters feel part of the mission.', 'url' => '/fundraisers/' ],
                [ 'icon' => '⚾', 'title' => 'Sports Tournaments',   'color' => '#0e7490', 'desc' => 'Tournament tees, championship gear, coach gifts, and team awards apparel for any sport at any level.', 'url' => '/request-a-quote/?cat=apparel' ],
            ];
            foreach ( $types as $type ) :
            ?>
            <a href="<?php echo esc_url( home_url( $type['url'] ) ); ?>" class="tsa-em-type-card">
                <div class="tsa-em-type-card__icon-wrap" style="background:<?php echo esc_attr($type['color']); ?>15;border:2px solid <?php echo esc_attr($type['color']); ?>30">
                    <span class="tsa-em-type-card__icon"><?php echo esc_html( $type['icon'] ); ?></span>
                </div>
                <h3 class="tsa-em-type-card__title"><?php echo esc_html( $type['title'] ); ?></h3>
                <p class="tsa-em-type-card__desc"><?php echo esc_html( $type['desc'] ); ?></p>
                <span class="tsa-em-type-card__link" style="color:<?php echo esc_attr($type['color']); ?>">Get a Quote →</span>
            </a>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     WHAT WE PRODUCE
══════════════════════════════════════ -->
<section class="tsa-em-section tsa-em-section--white">
    <div class="tsa-sd-container">
        <div class="tsa-em-section-head">
            <div class="tsa-kicker tsa-kicker--pink">What We Make</div>
            <h2>Popular Event Merch Items</h2>
        </div>
        <div class="tsa-em-items-grid">
            <?php
            $items = [
                [ 'emoji' => '👕', 'name' => 'Event T-Shirts',        'note' => 'The staple — fully custom, any color, any design.' ],
                [ 'emoji' => '🧥', 'name' => 'Hoodies & Pullovers',   'note' => 'Premium feel, great for evening events and fall.' ],
                [ 'emoji' => '🧢', 'name' => 'Caps & Hats',           'note' => 'Structured or unstructured — embroidered or DTF.' ],
                [ 'emoji' => '🏅', 'name' => 'Award & Recognition',   'note' => 'Staff shirts, coach gifts, volunteer appreciation.' ],
                [ 'emoji' => '🎒', 'name' => 'Bags & Drawstrings',    'note' => 'Custom branded bags for goodie bags or swag kits.' ],
                [ 'emoji' => '☕', 'name' => 'Drinkware & Koozies',   'note' => 'Branded tumblers and koozies for outdoor events.' ],
            ];
            foreach ( $items as $item ) :
            ?>
            <div class="tsa-em-item">
                <span class="tsa-em-item__emoji"><?php echo esc_html( $item['emoji'] ); ?></span>
                <strong class="tsa-em-item__name"><?php echo esc_html( $item['name'] ); ?></strong>
                <span class="tsa-em-item__note"><?php echo esc_html( $item['note'] ); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     TIMELINE / PLANNING
══════════════════════════════════════ -->
<section class="tsa-em-section tsa-em-section--dark">
    <div class="tsa-sd-container">
        <div class="tsa-em-section-head tsa-em-section-head--light">
            <div class="tsa-kicker" style="background:rgba(254,194,192,.12);color:var(--tsa-pink);border:1px solid rgba(254,194,192,.25)">Plan Ahead</div>
            <h2 style="color:#fff">Event Merch Timeline Guide</h2>
            <p style="color:rgba(255,255,255,.6)">Order early for the best results. Here's what to expect at each lead time.</p>
        </div>
        <div class="tsa-em-timeline">
            <?php
            $timeline = [
                [ 'time' => '4+ weeks out', 'label' => 'Ideal',   'color' => '#4ade80', 'desc' => 'Full design revisions, product selection, samples available, standard production and shipping.' ],
                [ 'time' => '2–3 weeks',    'label' => 'Good',    'color' => '#facc15', 'desc' => 'Standard production, limited revisions, ground shipping. Most event orders fall here.' ],
                [ 'time' => '1 week',        'label' => 'Tight',   'color' => '#fb923c', 'desc' => 'Rush production available (fees apply). Design must be approved same day. Expedited shipping.' ],
                [ 'time' => '48–72 hours',   'label' => 'Rush',    'color' => '#f87171', 'desc' => 'Emergency rush — contact us directly. DTF only, limited styles, overnight shipping required.' ],
            ];
            foreach ( $timeline as $tl ) :
            ?>
            <div class="tsa-em-timeline-item">
                <div class="tsa-em-timeline-item__time"><?php echo esc_html( $tl['time'] ); ?></div>
                <div class="tsa-em-timeline-item__badge" style="background:<?php echo esc_attr($tl['color']); ?>22;color:<?php echo esc_attr($tl['color']); ?>;border:1px solid <?php echo esc_attr($tl['color']); ?>44"><?php echo esc_html( $tl['label'] ); ?></div>
                <p class="tsa-em-timeline-item__desc"><?php echo esc_html( $tl['desc'] ); ?></p>
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
                <h2>Tell us about your event.</h2>
                <p>Share your date, headcount, and product ideas — we'll quote it and get the artwork started within one business day.</p>
            </div>
            <div class="tsa-svh-cta__actions">
                <a href="<?php echo esc_url( home_url( '/request-a-quote/?cat=apparel' ) ); ?>" class="tsa-btn tsa-btn-primary">Start Your Event Quote →</a>
                <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="tsa-btn tsa-btn-outline">Contact Us</a>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
