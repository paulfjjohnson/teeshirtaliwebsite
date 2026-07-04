<?php
/**
 * Template Name: TSA Homepage
 *
 * Full homepage layout for teeshirtali.com.
 * Assign to the front page via Page > Attributes > Template.
 */

defined( 'ABSPATH' ) || exit;

get_header();

// Spotlight store
$spotlight_store    = tsa_get_spotlight_store();
$spotlight_cta_url  = $spotlight_store ? get_post_meta( $spotlight_store->ID, '_tsa_store_cta_url', true ) : '/schools/dutchtown/';
$spotlight_title    = $spotlight_store ? get_the_title( $spotlight_store->ID ) : 'Dutchtown High School Spirit Store';
$spotlight_tagline  = $spotlight_store ? get_post_meta( $spotlight_store->ID, '_tsa_store_tagline', true ) : 'Shop Color Guard, parent gear, fan apparel, and exclusive collections.';
$spotlight_cta_text = $spotlight_store ? get_post_meta( $spotlight_store->ID, '_tsa_store_cta_text', true ) : 'Enter Store';
$spotlight_img_id   = $spotlight_store ? get_post_thumbnail_id( $spotlight_store->ID ) : 0;
$spotlight_img_src  = $spotlight_img_id ? wp_get_attachment_image_url( $spotlight_img_id, 'tsa-store-banner' ) : '';
$spotlight_img_css  = $spotlight_img_src ? 'style="background-image:url(' . esc_url( $spotlight_img_src ) . ')"' : '';
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
     SECTION 2 — ACTIVE STORE SPOTLIGHT
══════════════════════════════════════ -->
<?php if ( $spotlight_store ) : ?>
<section class="tsa-section tsa-active-store">
    <div class="tsa-store-card">
        <div class="tsa-store-visual" <?php echo $spotlight_img_css; // phpcs:ignore ?>></div>
        <div class="tsa-store-copy">
            <div class="tsa-kicker tsa-kicker--light">Now Live</div>
            <h2><?php echo esc_html( $spotlight_title ); ?></h2>
            <p><?php echo esc_html( $spotlight_tagline ); ?></p>
            <?php tsa_btn( $spotlight_cta_url, $spotlight_cta_text, 'primary' ); ?>
        </div>
    </div>
</section>
<?php endif; ?>


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
     SECTION 7 — SCHOOL STORES GRID
══════════════════════════════════════ -->
<section class="tsa-section tsa-schools">
    <div class="tsa-section-head">
        <h2>School Stores</h2>
        <p>Each school gets its own branded storefront under Tee Shirt Ali — spirit wear, team gear, and exclusive drops all in one place.</p>
    </div>

    <?php
    $stores = tsa_get_homepage_stores();
    if ( $stores ) :
    ?>
    <div class="tsa-school-grid">
        <?php foreach ( $stores as $store ) :
            $status  = get_post_meta( $store->ID, '_tsa_homepage_status', true );
            $is_live = ( $status === 'live' );

            if ( $is_live ) {
                $card_cta_url = get_post_meta( $store->ID, '_tsa_store_cta_url', true );
                if ( ! $card_cta_url ) {
                    $card_slug    = get_post_meta( $store->ID, '_ac_store_slug', true );
                    $card_cta_url = function_exists( 'tsa_store_url' ) && $card_slug ? tsa_store_url( $card_slug ) : home_url( '/schools/' . $card_slug . '/' );
                }
                $card_tagline = get_post_meta( $store->ID, '_tsa_store_tagline', true );

                // Homepage preview image (dedicated field) → Logo (featured image) → nothing.
                $card_img_id  = (int) get_post_meta( $store->ID, '_tsa_store_preview_id', true ) ?: get_post_thumbnail_id( $store->ID );
                $card_img_src = $card_img_id
                    ? ( wp_get_attachment_image_url( $card_img_id, 'tsa-school-card' ) ?: wp_get_attachment_image_url( $card_img_id, 'full' ) )
                    : '';

                // Card color: store's own saved color → school palette → neutral default.
                $card_primary = get_post_meta( $store->ID, '_tsa_school_primary', true );
                if ( ! $card_primary && function_exists( 'tsa_get_school_colors' ) ) {
                    $card_pal     = tsa_get_school_colors( get_the_title( $store->ID ) );
                    $card_primary = $card_pal['primary'] ?? '';
                }
                $card_primary = $card_primary ?: '#d8a85f';
                $card_text    = function_exists( 'tsa_readable_text' ) ? tsa_readable_text( $card_primary ) : '#ffffff';
            ?>
            <a href="<?php echo esc_url( $card_cta_url ); ?>" class="tsa-school-card live" style="background:<?php echo esc_attr( $card_primary ); ?>;color:<?php echo esc_attr( $card_text ); ?>;padding:0;overflow:hidden;display:flex;flex-direction:column;">
                <?php if ( $card_img_src ) : ?>
                <div style="height:110px;background-image:url(<?php echo esc_url( $card_img_src ); ?>);background-size:cover;background-position:center;"></div>
                <?php endif; ?>
                <div style="padding:18px 20px;">
                    <h3 style="margin:0 0 4px;font-size:19px;"><?php echo esc_html( get_the_title( $store->ID ) ); ?></h3>
                    <?php if ( $card_tagline ) : ?><p style="margin:0 0 10px;font-size:13px;opacity:.85;"><?php echo esc_html( $card_tagline ); ?></p><?php endif; ?>
                    <p style="margin:0;display:inline-flex;align-items:center;gap:6px;font-size:11px;font-weight:800;text-transform:uppercase;letter-spacing:.6px;"><span style="width:8px;height:8px;border-radius:50%;background:#22c55e;"></span>Now live</p>
                </div>
            </a>
            <?php else : ?>
            <div class="tsa-school-card">
                <h3><?php echo esc_html( get_the_title( $store->ID ) ); ?></h3>
                <p>Coming soon</p>
            </div>
            <?php endif; ?>
        <?php endforeach; ?>
    </div>
    <?php else : ?>
    <div class="tsa-school-grid">
        <a href="/schools/dutchtown/" class="tsa-school-card live">
            <h3>Dutchtown</h3>
            <p>Now live</p>
        </a>
        <div class="tsa-school-card">
            <h3>Prairieville</h3>
            <p>Coming soon</p>
        </div>
        <div class="tsa-school-card">
            <h3>St. Amant</h3>
            <p>Coming soon</p>
        </div>
        <div class="tsa-school-card">
            <h3>East Ascension</h3>
            <p>Coming soon</p>
        </div>
    </div>
    <?php endif; ?>

    <div style="text-align:center; margin-top: 32px;">
        <?php tsa_btn( '/schools/', 'View All Schools', 'dark' ); ?>
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
