<?php
/**
 * Template Name: TSA Services Hub
 *
 * Main services overview page — all 12 services with descriptions,
 * category grouping, and visual treatment.
 * Assign to: /services/
 */

defined( 'ABSPATH' ) || exit;

/* ══════════════════════════════════════════════════════════
   SERVICE DATA
   To add a photo: set 'image' to a full URL or use
   get_template_directory_uri() . '/assets/img/svc-xxx.jpg'
══════════════════════════════════════════════════════════ */
$service_groups = [

    'Printing & Apparel' => [
        [
            'title'  => 'Custom Apparel',
            'icon'   => '👕',
            'color'  => '#fec2c0',
            'color2' => '#f99b98',
            'text'   => '#831843',
            'desc'   => 'From a single piece to a thousand-unit run, we print exactly what you need. Choose from 500+ blank styles across 11+ top brands — tees, hoodies, hats, bags, and more — decorated with full-color DTF or classic screen print.',
            'url'    => '/request-a-quote/?cat=apparel',
            'cta'    => 'Get a Quote',
            'image'  => '',
        ],
        [
            'title'  => 'DTF Printing',
            'icon'   => '🖨️',
            'color'  => '#252124',
            'color2' => '#3d1a1a',
            'text'   => '#fec2c0',
            'desc'   => 'Direct-to-film transfers print on virtually any fabric in full color with zero minimums. Order single transfers or fill a gang sheet — press-ready and shipped fast. The most versatile print method we offer.',
            'url'    => '/gang-sheet-builder/',
            'cta'    => 'Build a Gang Sheet',
            'image'  => '',
        ],
        [
            'title'  => 'Blank Apparel',
            'icon'   => '📦',
            'color'  => '#d8a85f',
            'color2' => '#c9943c',
            'text'   => '#fff',
            'desc'   => 'Need blanks for your own decorating operation? We stock wholesale blanks from the industry\'s top brands — Bella+Canvas, Gildan, Next Level, Port &amp; Company, and more. Bulk pricing, fast shipping.',
            'url'    => '/blank-apparel/',
            'cta'    => 'Browse Blanks',
            'image'  => '',
        ],
    ],

    'Stores & Programs' => [
        [
            'title'  => 'School Stores',
            'icon'   => '🏫',
            'color'  => '#592c82',
            'color2' => '#3d1a6e',
            'text'   => '#fff',
            'desc'   => 'Every school deserves its own online store. We build dedicated merch stores for schools, clubs, sports programs, and student organizations — no upfront cost, no inventory, new drops all year. Ascension Parish schools get priority setup.',
            'url'    => '/schools/',
            'cta'    => 'View School Stores',
            'image'  => '',
        ],
        [
            'title'  => 'Team Gear',
            'icon'   => '⚾',
            'color'  => '#1b3464',
            'color2' => '#0f2144',
            'text'   => '#fff',
            'desc'   => 'Outfit your entire team from head to toe. Custom jerseys, practice gear, warm-ups, bags, and accessories — all with your team name, numbers, and colors. Built for travel teams, rec leagues, and school sports.',
            'url'    => '/team-stores/',
            'cta'    => 'View Team Stores',
            'image'  => '',
        ],
        [
            'title'  => 'Spirit Wear',
            'icon'   => '⭐',
            'color'  => '#fec2c0',
            'color2' => '#e8a8a6',
            'text'   => '#831843',
            'desc'   => 'Rep your team, your school, your city. We create seasonal spirit wear drops with limited-edition designs that build buzz and move fast. Perfect for booster clubs, student sections, and diehard fan gear.',
            'url'    => '/request-a-quote/?cat=apparel',
            'cta'    => 'Start a Drop',
            'image'  => '',
        ],
        [
            'title'  => 'Fundraiser Merch',
            'icon'   => '💰',
            'color'  => '#166534',
            'color2' => '#14532d',
            'text'   => '#fff',
            'desc'   => 'Turn merch into money for your cause. We build custom fundraiser stores where your supporters shop and your organization earns a percentage of every sale — zero upfront cost, zero risk, 100% of the profit goes to you.',
            'url'    => '/request-a-store/',
            'cta'    => 'Set Up a Fundraiser',
            'image'  => '',
        ],
    ],

    'Design & Creative' => [
        [
            'title'  => 'Design Library',
            'icon'   => '🎨',
            'color'  => '#d8a85f',
            'color2' => '#b8832a',
            'text'   => '#fff',
            'desc'   => 'Browse hundreds of pre-made designs ready to put on any garment. Filter by sport, school, event, or theme. Choose your colors, add your name or number, and we print on demand — no designer required.',
            'url'    => '/design-library/',
            'cta'    => 'Browse Designs',
            'image'  => '',
        ],
        [
            'title'  => 'Graphic Design',
            'icon'   => '✏️',
            'color'  => '#252124',
            'color2' => '#3d1a2e',
            'text'   => '#d8a85f',
            'desc'   => 'Our in-house design team brings your vision to life. Logos, print layouts, brand packages, social graphics, illustrations — if you can describe it, we can design it. Revisions included, fast turnaround, flat-rate pricing.',
            'url'    => '/request-a-quote/?cat=design',
            'cta'    => 'Request Design Help',
            'image'  => '',
        ],
        [
            'title'  => 'Exclusive Configurator',
            'icon'   => '⚙️',
            'color'  => '#231532',
            'color2' => '#3a1f56',
            'text'   => '#fec2c0',
            'desc'   => 'Build your perfect order step by step with our custom product configurator. Choose your blank, select colors, upload your art, set your size run — all in one place with live pricing. No back-and-forth emails.',
            'url'    => '/configurator/',
            'cta'    => 'Open Configurator',
            'image'  => '',
        ],
    ],

    'Events & Promotions' => [
        [
            'title'  => 'Event Merchandise',
            'icon'   => '🎉',
            'color'  => '#7c3aed',
            'color2' => '#5b21b6',
            'text'   => '#fff',
            'desc'   => 'Make your event unforgettable with custom merch. Concerts, graduations, reunions, corporate outings — we handle the artwork, printing, and fulfillment so you can focus on the event itself. Fast turnaround available.',
            'url'    => '/request-a-quote/?cat=apparel',
            'cta'    => 'Plan Your Event Merch',
            'image'  => '',
        ],
        [
            'title'  => 'Promotional Products',
            'icon'   => '🏆',
            'color'  => '#0e7490',
            'color2' => '#0c4a6e',
            'text'   => '#fff',
            'desc'   => 'Go beyond apparel. Branded drinkware, bags, hats, pens, tech accessories, and more — all customized with your logo. Perfect for trade shows, employee onboarding kits, corporate gifts, and brand activations.',
            'url'    => '/request-a-quote/',
            'cta'    => 'Get a Quote',
            'image'  => '',
        ],
    ],

];

get_header();
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-svh-hero">
    <div class="tsa-svh-hero__bg" aria-hidden="true"></div>
    <div class="tsa-sd-container tsa-svh-hero__inner">
        <div class="tsa-kicker tsa-kicker--light">What We Do</div>
        <h1 class="tsa-svh-hero__title">Custom Merch.<br>Fast. Your Way.</h1>
        <p class="tsa-svh-hero__sub">From a single DTF transfer to a school-wide store — we handle the printing, the design, and the platform so you can focus on what matters.</p>
        <div class="tsa-svh-hero__actions">
            <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-btn tsa-btn-primary">Request a Quote</a>
            <a href="<?php echo esc_url( home_url( '/design-library/' ) ); ?>" class="tsa-btn tsa-svh-btn-ghost">Browse Designs →</a>
        </div>
        <div class="tsa-svh-stats">
            <div class="tsa-svh-stat"><span class="tsa-svh-stat__num">500+</span><span class="tsa-svh-stat__label">Orders Completed</span></div>
            <div class="tsa-svh-stat-div"></div>
            <div class="tsa-svh-stat"><span class="tsa-svh-stat__num">11+</span><span class="tsa-svh-stat__label">Apparel Brands</span></div>
            <div class="tsa-svh-stat-div"></div>
            <div class="tsa-svh-stat"><span class="tsa-svh-stat__num">3–5</span><span class="tsa-svh-stat__label">Day Turnaround</span></div>
            <div class="tsa-svh-stat-div"></div>
            <div class="tsa-svh-stat"><span class="tsa-svh-stat__num">No Min</span><span class="tsa-svh-stat__label">DTF Transfers</span></div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     SERVICE GROUPS
══════════════════════════════════════ -->
<div class="tsa-svh-page">
    <div class="tsa-sd-container">

        <?php foreach ( $service_groups as $group_name => $services ) : ?>
        <div class="tsa-svh-group">

            <div class="tsa-svh-group__header">
                <h2 class="tsa-svh-group__title"><?php echo esc_html( $group_name ); ?></h2>
                <span class="tsa-svh-group__count"><?php echo count( $services ); ?> services</span>
            </div>

            <div class="tsa-svh-grid">
                <?php foreach ( $services as $svc ) :
                    $has_img = ! empty( $svc['image'] );
                ?>
                <article class="tsa-svh-card">

                    <!-- Visual header — swap gradient for real image by setting 'image' in $services -->
                    <div class="tsa-svh-card__visual"
                         style="<?php echo $has_img
                            ? 'background-image:url(' . esc_url( $svc['image'] ) . ');background-size:cover;background-position:center'
                            : 'background:linear-gradient(135deg,' . esc_attr( $svc['color'] ) . ' 0%,' . esc_attr( $svc['color2'] ) . ' 100%)'; ?>">
                        <span class="tsa-svh-card__icon" aria-hidden="true"><?php echo esc_html( $svc['icon'] ); ?></span>
                        <?php if ( $has_img ) : ?>
                        <div class="tsa-svh-card__visual-overlay"></div>
                        <?php endif; ?>
                    </div>

                    <!-- Content -->
                    <div class="tsa-svh-card__body">
                        <h3 class="tsa-svh-card__title"><?php echo esc_html( $svc['title'] ); ?></h3>
                        <p class="tsa-svh-card__desc"><?php echo wp_kses_post( $svc['desc'] ); ?></p>
                        <a href="<?php echo esc_url( home_url( $svc['url'] ) ); ?>"
                           class="tsa-svh-card__link"
                           style="color:<?php echo esc_attr( $svc['color'] ); ?>">
                            <?php echo esc_html( $svc['cta'] ); ?> →
                        </a>
                    </div>

                </article>
                <?php endforeach; ?>
            </div>

        </div>
        <?php endforeach; ?>

    </div>
</div>

<!-- ══════════════════════════════════════
     HOW IT WORKS
══════════════════════════════════════ -->
<section class="tsa-svh-how">
    <div class="tsa-sd-container">
        <div class="tsa-svh-how__head">
            <div class="tsa-kicker tsa-kicker--pink">Simple Process</div>
            <h2 class="tsa-svh-how__title">From Idea to Inbox in 4 Steps</h2>
        </div>
        <div class="tsa-svh-steps">
            <?php
            $steps = [
                [ 'num' => '01', 'title' => 'Tell Us What You Need',       'desc' => 'Fill out a quick quote request or jump into the configurator. The more detail you give us, the faster we can respond.' ],
                [ 'num' => '02', 'title' => 'We Quote &amp; Create',       'desc' => 'We respond with pricing within one business day. Our design team finalizes the artwork and sends you a proof for approval.' ],
                [ 'num' => '03', 'title' => 'You Approve, We Print',       'desc' => 'Once you say go, production starts immediately. Most orders are printed and ready within 3–5 business days.' ],
                [ 'num' => '04', 'title' => 'Pick Up or Ship',             'desc' => 'Local pickup in Ascension Parish or we ship anywhere in the continental US. Tracking provided on every order.' ],
            ];
            foreach ( $steps as $step ) :
            ?>
            <div class="tsa-svh-step">
                <div class="tsa-svh-step__num"><?php echo esc_html( $step['num'] ); ?></div>
                <div class="tsa-svh-step__body">
                    <h4 class="tsa-svh-step__title"><?php echo wp_kses_post( $step['title'] ); ?></h4>
                    <p class="tsa-svh-step__desc"><?php echo esc_html( $step['desc'] ); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     BOTTOM CTA
══════════════════════════════════════ -->
<section class="tsa-svh-cta">
    <div class="tsa-sd-container">
        <div class="tsa-svh-cta__inner">
            <div class="tsa-svh-cta__copy">
                <h2>Not sure which service you need?</h2>
                <p>Tell us what you're trying to accomplish and we'll point you in the right direction — no commitment required.</p>
            </div>
            <div class="tsa-svh-cta__actions">
                <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-btn tsa-btn-primary">Request a Quote</a>
                <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="tsa-btn tsa-btn-outline">Contact Us</a>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
