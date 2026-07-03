<?php
/**
 * Template Name: TSA Homepage
 *
 * Full homepage layout for teeshirtali.com.
 * Assign to the front page via Page > Attributes > Template.
 */

defined( 'ABSPATH' ) || exit;

get_header();

// Spotlight store (still used for the Hero's "Shop School Gear" link only —
// the Section 2 "Active Store Spotlight" card below now loops every live
// store instead of just the one flagged _tsa_is_spotlight).
$spotlight_store   = tsa_get_spotlight_store();
$spotlight_cta_url = $spotlight_store ? get_post_meta( $spotlight_store->ID, '_tsa_store_cta_url', true ) : '/schools/dutchtown/';
?>

<!-- ══════════════════════════════════════
     SECTION 1 — HERO
══════════════════════════════════════ -->
<section class="tsa-section tsa-hero">
    <div>
        <div class="tsa-kicker">Custom Apparel + School Spirit</div>

        <h1>Custom Apparel. School Spirit. Creative Merch Solutions.</h1>

        <p>Tee Shirt Ali creates custom spirit wear, team gear, business apparel, fundraiser merch, and specialty collections for schools, groups, businesses, and events.</p>

        <div class="tsa-actions">
            <?php tsa_btn( $spotlight_cta_url ?: '/schools/', 'Shop School Gear', 'primary' ); ?>
            <?php tsa_btn( '/request-a-quote/', 'Start a Custom Project', 'outline' ); ?>
        </div>
    </div>

    <div class="tsa-hero-card">
        <div class="tsa-mock-stack">
            <div class="tsa-mini-card">
                <strong>School Stores</strong>
                <span>Dedicated merch hubs for teams, clubs, and school communities.</span>
            </div>
            <div class="tsa-mini-card">
                <strong>Limited-Time Drops</strong>
                <span>Seasonal designs, spirit campaigns, and fundraiser collections.</span>
            </div>
            <div class="tsa-mini-card">
                <strong>Custom Requests</strong>
                <span>Apparel programs for groups, businesses, and special events.</span>
            </div>
        </div>
    </div>
</section>


<!-- ══════════════════════════════════════
     SECTION 2 — LIVE SCHOOL STORES
     One big spotlight-style card per live store (was: a single card for
     whichever store had the _tsa_is_spotlight flag, plus a separate small-
     card grid further down the page — the grid is gone, this now covers
     every live store the grid used to list).
══════════════════════════════════════ -->
<?php
$live_stores = array_filter( tsa_get_homepage_stores(), function ( $s ) {
    return get_post_meta( $s->ID, '_tsa_homepage_status', true ) === 'live';
} );
foreach ( $live_stores as $store ) :
    $store_slug     = get_post_meta( $store->ID, '_ac_store_slug', true );
    $store_cta_url  = get_post_meta( $store->ID, '_tsa_store_cta_url', true );
    if ( ! $store_cta_url ) {
        $store_cta_url = function_exists( 'tsa_store_url' ) && $store_slug ? tsa_store_url( $store_slug ) : home_url( '/schools/' . $store_slug . '/' );
    }
    $store_tagline  = get_post_meta( $store->ID, '_tsa_store_tagline', true );
    $store_cta_text = get_post_meta( $store->ID, '_tsa_store_cta_text', true ) ?: 'Enter Store';
    // Homepage preview image (dedicated field, Store Builder) → Logo (featured
    // image) → nothing. Fall back to the original full-size upload when the
    // tsa-store-banner crop hasn't been generated for that attachment (e.g. it
    // was uploaded before that image size was registered — WP doesn't backfill
    // sizes retroactively).
    $store_img_id   = (int) get_post_meta( $store->ID, '_tsa_store_preview_id', true ) ?: get_post_thumbnail_id( $store->ID );
    $store_img_src  = $store_img_id
        ? ( wp_get_attachment_image_url( $store_img_id, 'tsa-store-banner' ) ?: wp_get_attachment_image_url( $store_img_id, 'full' ) )
        : '';
    $store_img_css  = $store_img_src ? 'background-image:url(' . esc_url( $store_img_src ) . ');' : '';

    // Card color: store's own saved color → school palette → neutral TSA
    // default — same resolution chain used on the store's own landing-page
    // hero (inc/store-chrome.php), so this card matches instead of showing
    // one hardcoded color for every school.
    $store_primary = get_post_meta( $store->ID, '_tsa_school_primary', true );
    if ( ! $store_primary && function_exists( 'tsa_get_school_colors' ) ) {
        $store_pal     = tsa_get_school_colors( get_the_title( $store->ID ) );
        $store_primary = $store_pal['primary'] ?? '';
    }
    $store_primary   = $store_primary ?: '#d8a85f';
    $store_card_text = function_exists( 'tsa_readable_text' ) ? tsa_readable_text( $store_primary ) : '#ffffff';
?>
<section class="tsa-section tsa-active-store">
    <div class="tsa-store-card" style="background:<?php echo esc_attr( $store_primary ); ?>;color:<?php echo esc_attr( $store_card_text ); ?>;">
        <div class="tsa-store-visual" style="<?php echo esc_attr( $store_img_css ); ?>"></div>
        <div class="tsa-store-copy">
            <div class="tsa-kicker tsa-kicker--light">Now Live</div>
            <h2><?php echo esc_html( get_the_title( $store->ID ) ); ?></h2>
            <p><?php echo esc_html( $store_tagline ); ?></p>
            <?php tsa_btn( $store_cta_url, $store_cta_text, 'primary' ); ?>
        </div>
    </div>
</section>
<?php endforeach; ?>


<!-- ══════════════════════════════════════
     SECTION 2b — FEATURED DESIGNS (by category)
══════════════════════════════════════ -->
<?php
$tsa_featured_html = do_shortcode( '[tsa_featured_designs]' );
if ( trim( $tsa_featured_html ) !== '' ) :
?>
<section class="tsa-section tsa-featured-designs" style="background:linear-gradient(180deg,#fff1f0 0%,#ffffff 78%) !important;border-top:2px solid rgba(216,168,95,.45);border-bottom:2px solid rgba(216,168,95,.45);">
    <div class="tsa-section-head">
        <div style="display:inline-block;background:#d8a85f;color:#252124;font-size:15px;font-weight:900;letter-spacing:2px;text-transform:uppercase;padding:11px 30px;border-radius:999px;box-shadow:0 10px 26px rgba(216,168,95,.45);margin-bottom:16px;">Shop Featured Designs</div>
        <p style="font-size:18px;color:#5f5a5d;max-width:580px;margin:0 auto;">Pick a ready-made design, drop it straight into the configurator, and make it your own.</p>
    </div>
    <?php echo $tsa_featured_html; // phpcs:ignore WordPress.Security.EscapeOutput — shortcode output is escaped internally ?>
</section>
<?php endif; ?>


<!-- ══════════════════════════════════════
     SECTION 3 — WHAT TSA DOES
══════════════════════════════════════ -->
<section class="tsa-section tsa-what">
    <div class="tsa-section-head">
        <h2>What Tee Shirt Ali Does</h2>
        <p>One brand powering flexible merch experiences for schools, organizations, businesses, teams, and community events.</p>
    </div>

    <div class="tsa-grid-3">
        <a href="/schools/" class="tsa-service-card">
            <h3>Schools</h3>
            <p>Spirit wear, team gear, fundraiser apparel, clubs, activities, parent gear, and limited school drops.</p>
        </a>
        <a href="/businesses/" class="tsa-service-card">
            <h3>Businesses</h3>
            <p>Branded apparel, uniforms, promotional merch, company gear, and local business merchandise programs.</p>
        </a>
        <a href="/tee-party/" class="tsa-service-card">
            <h3>Events &amp; Drops</h3>
            <p>Limited-time collections, campaign launches, fundraiser merch, and custom specialty apparel.</p>
        </a>
    </div>
</section>


<!-- ══════════════════════════════════════
     SECTION 4 — FEATURED SERVICES PILLS
══════════════════════════════════════ -->
<section class="tsa-section tsa-services">
    <div class="tsa-section-head">
        <h2>Featured Services</h2>
        <p>Everything you need from design to delivery — school merch, custom apparel, and professional print solutions.</p>
    </div>

    <div class="tsa-pill-grid">
        <?php
        $services = [
            [ 'label' => 'Custom Apparel',       'url' => '/custom-apparel/' ],
            [ 'label' => 'Design Library',        'url' => '/design-library/' ],
            [ 'label' => 'Exclusive Configurator','url' => '/configurator/' ],
            [ 'label' => 'Blank Apparel',         'url' => '/blank-apparel/' ],
            [ 'label' => 'School Stores',         'url' => '/schools/' ],
            [ 'label' => 'Fundraiser Merch',      'url' => '/fundraisers/' ],
            [ 'label' => 'Spirit Wear',           'url' => '/spirit-wear/' ],
            [ 'label' => 'Team Gear',             'url' => '/team-stores/' ],
            [ 'label' => 'DTF Printing',          'url' => '/dtf-printing/' ],
            [ 'label' => 'Event Merchandise',     'url' => '/event-merchandise/' ],
            [ 'label' => 'Graphic Design',        'url' => '/graphic-design/' ],
            [ 'label' => 'Promotional Products',  'url' => '/promotional-products/' ],
        ];
        foreach ( $services as $svc ) :
        ?>
            <a href="<?php echo esc_url( $svc['url'] ); ?>" class="tsa-pill"><?php echo esc_html( $svc['label'] ); ?></a>
        <?php endforeach; ?>
    </div>
</section>


<!-- ══════════════════════════════════════
     SECTION 5 — CONFIGURATOR FEATURE
══════════════════════════════════════ -->
<section class="tsa-section tsa-configurator">
    <div class="tsa-config-grid">
        <div class="tsa-config-copy">
            <div class="tsa-kicker">Design-First</div>
            <h2>Choose a Design. Build Your Apparel.</h2>
            <p>Browse the Tee Shirt Ali design library, choose your artwork, then use the exclusive configurator to select your blank apparel, garment color, size, and quantity.</p>
            <div class="tsa-actions">
                <?php tsa_btn( '/design-library/', 'Browse Design Library', 'primary' ); ?>
                <?php tsa_btn( '/blank-apparel/', 'View Blank Apparel', 'outline' ); ?>
            </div>
        </div>

        <div class="tsa-config-placeholder">
            <div class="tsa-config-window">
                <div class="tsa-config-bar"><span></span><span></span><span></span></div>
                <div class="tsa-config-body">
                    <div class="tsa-shirt-preview">Apparel Preview</div>
                    <div class="tsa-config-options">
                        <strong>Configurator</strong>
                        <small>Design • Apparel • Color • Size</small>
                    </div>
                </div>
            </div>
        </div>
    </div>
</section>


<!-- ══════════════════════════════════════
     SECTION 6 — BLANK APPAREL CATEGORIES
══════════════════════════════════════ -->
<section class="tsa-section tsa-apparel">
    <div class="tsa-section-head">
        <h2>Blank Apparel Options</h2>
        <p>Start with a blank garment, choose your design, and build your custom apparel through the Tee Shirt Ali configurator.</p>
    </div>

    <div class="tsa-grid-3">
        <a href="/blank-apparel/" class="tsa-service-card">
            <h3>Tees</h3>
            <p>Bella Canvas, Gildan, Next Level, Comfort Colors, and more — soft, durable, and printable.</p>
        </a>
        <a href="/blank-apparel/" class="tsa-service-card">
            <h3>Hoodies &amp; Fleece</h3>
            <p>ITC, Tultex, Jerzees, and Gildan hoodies for teams, clubs, and seasonal collections.</p>
        </a>
        <a href="/blank-apparel/" class="tsa-service-card">
            <h3>Hats &amp; Headwear</h3>
            <p>Richardson, YP Classics — snapbacks, truckers, beanies, and visors ready for your logo.</p>
        </a>
    </div>
</section>


<!-- ══════════════════════════════════════
     SECTION 8 — CTA BANNER
══════════════════════════════════════ -->
<section class="tsa-section tsa-cta">
    <h2>Need Custom Apparel or Merch?</h2>
    <p>Start with an idea, school group, team, business, event, or fundraiser. Tee Shirt Ali can help turn it into a clean merch experience.</p>
    <div class="tsa-actions tsa-actions--center">
        <?php tsa_btn( '/request-a-quote/', 'Start a Project', 'primary' ); ?>
        <?php tsa_btn( '/contact/', 'Contact Us', 'outline-white' ); ?>
    </div>
</section>

<?php get_footer(); ?>
