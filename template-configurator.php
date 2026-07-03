<?php
/**
 * Template Name: TSA Exclusive Configurator
 *
 * Wrapper for the [apparel_configurator] plugin shortcode.
 * Add the shortcode to the page content in the WordPress editor.
 * Assign to: /configurator/
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-cfg-hero">
    <div class="tsa-cfg-hero__bg" aria-hidden="true"></div>
    <div class="tsa-sd-container tsa-cfg-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">Design It Your Way</div>
        <h1 class="tsa-cfg-hero__title">The Apparel Configurator</h1>
        <p class="tsa-cfg-hero__sub">Choose your blank, pick your colors, upload your art, and set your size run — all in one place with live pricing. No emails back and forth.</p>

        <div class="tsa-cfg-steps-row">
            <?php
            // Step number is auto-generated from the loop index — add, remove or
            // reorder rows and the "Step N" labels renumber themselves.
            $steps = [
                [ 'label' => 'Pick a Design',    'icon' => '🖼️' ],
                [ 'label' => 'Pick a Style',     'icon' => '👕' ],
                [ 'label' => 'Choose Colors',    'icon' => '🎨' ],
                [ 'label' => 'Set Sizes & Qty',  'icon' => '📏' ],
                [ 'label' => 'Add to Cart',      'icon' => '🛒' ],
            ];
            foreach ( $steps as $i => $s ) :
            ?>
            <div class="tsa-cfg-step">
                <div class="tsa-cfg-step__bubble"><?php echo esc_html( $s['icon'] ); ?></div>
                <div class="tsa-cfg-step__num">Step <?php echo (int) $i + 1; ?></div>
                <div class="tsa-cfg-step__label"><?php echo esc_html( $s['label'] ); ?></div>
            </div>
            <?php if ( $i < count( $steps ) - 1 ) : ?>
            <div class="tsa-cfg-step-arrow" aria-hidden="true">→</div>
            <?php endif; ?>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     CONFIGURATOR EMBED
     Place shortcode [apparel_configurator store="slug"] in page content
══════════════════════════════════════ -->
<section class="tsa-cfg-embed">
    <div class="tsa-sd-container">
        <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); ?>
            <?php
            $content = get_the_content();
            if ( $content ) :
                echo '<div class="tsa-cfg-embed__wrap">' . do_shortcode( apply_filters( 'the_content', $content ) ) . '</div>';
            else :
            ?>
            <div class="tsa-cfg-placeholder">
                <div class="tsa-cfg-placeholder__icon">⚙️</div>
                <h3>Configurator Loading…</h3>
                <p>Add <code>[apparel_configurator store="your-store-slug"]</code> to this page's content in the WordPress editor to activate the configurator here.</p>
                <a href="<?php echo esc_url( home_url( '/request-a-quote/?cat=apparel' ) ); ?>" class="tsa-btn tsa-btn-primary">Request a Quote Instead</a>
            </div>
            <?php
            endif;
            ?>
        <?php endwhile; endif; ?>
    </div>
</section>

<!-- ══════════════════════════════════════
     WHY USE THE CONFIGURATOR
══════════════════════════════════════ -->
<section class="tsa-cfg-why">
    <div class="tsa-sd-container">
        <div class="tsa-cfg-why__head">
            <h2>Why Use the Configurator?</h2>
            <p>Built for people who know what they want and want it fast.</p>
        </div>
        <div class="tsa-cfg-why__grid">
            <?php
            $perks = [
                [ 'icon' => '⚡', 'title' => 'Live Pricing',       'desc' => 'See your total update in real time as you add sizes and quantities — no waiting for a quote.' ],
                [ 'icon' => '🎨', 'title' => 'Upload Any Art',     'desc' => 'Drop in your logo, vector file, or image. We\'ll confirm print-readiness before production.' ],
                [ 'icon' => '📏', 'title' => 'Full Size Runs',     'desc' => 'Enter quantities per size — XS through 4XL — and the configurator builds your order automatically.' ],
                [ 'icon' => '🛒', 'title' => 'Order Instantly',    'desc' => 'Approve and add to cart without any email threads. Pay securely via WooCommerce checkout.' ],
                [ 'icon' => '📦', 'title' => '500+ Blanks',        'desc' => 'Choose from over 500 garment styles across 11+ top brands — all available in the configurator.' ],
                [ 'icon' => '🔒', 'title' => 'Secure Checkout',   'desc' => 'Payment is processed through our secure WooCommerce store. No accounts required to order.' ],
            ];
            foreach ( $perks as $p ) :
            ?>
            <div class="tsa-cfg-perk">
                <span class="tsa-cfg-perk__icon"><?php echo esc_html( $p['icon'] ); ?></span>
                <div>
                    <strong class="tsa-cfg-perk__title"><?php echo esc_html( $p['title'] ); ?></strong>
                    <p class="tsa-cfg-perk__desc"><?php echo esc_html( $p['desc'] ); ?></p>
                </div>
            </div>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     FAQ
══════════════════════════════════════ -->
<section class="tsa-cfg-faq">
    <div class="tsa-sd-container">
        <h2 class="tsa-cfg-faq__title">Frequently Asked Questions</h2>
        <div class="tsa-qr-faq" id="tsa-cfg-faq-list">
            <?php
            $faqs = [
                [ 'q' => 'What file types can I upload for my artwork?',
                  'a' => 'We accept AI, EPS, PDF, SVG (vector formats preferred), as well as high-resolution PNG or JPG at 300 DPI or higher. The configurator will flag low-resolution files before you complete your order.' ],
                [ 'q' => 'Can I order just one piece?',
                  'a' => 'Yes — for DTF-printed garments there is no minimum order. For screen-printed orders the minimum is 6 pieces. The configurator will display applicable minimums based on your decoration method selection.' ],
                [ 'q' => 'How long does production take after I order?',
                  'a' => 'Standard production is 5–7 business days after artwork is confirmed. Rush production (2–3 business days) is available — add a note at checkout or contact us after ordering.' ],
                [ 'q' => 'Can I proof my order before it prints?',
                  'a' => 'Yes. For any order with custom artwork, we send a digital proof via email before production begins. No order prints until you approve.' ],
                [ 'q' => 'What if I need help with design?',
                  'a' => 'Our design team can create or prep your artwork. After placing your configurator order, note in the order comments that you need design help and we\'ll reach out within one business day.' ],
            ];
            foreach ( $faqs as $i => $faq ) :
            ?>
            <div class="tsa-qr-faq-item" id="tsa-cfg-faq-<?php echo $i; ?>">
                <button class="tsa-qr-faq-q" aria-expanded="false" aria-controls="tsa-cfg-faq-a-<?php echo $i; ?>">
                    <span><?php echo esc_html( $faq['q'] ); ?></span>
                    <span class="tsa-qr-faq-chevron" aria-hidden="true"></span>
                </button>
                <div class="tsa-qr-faq-a" id="tsa-cfg-faq-a-<?php echo $i; ?>" role="region" hidden>
                    <p><?php echo esc_html( $faq['a'] ); ?></p>
                </div>
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
                <h2>Need a custom quote instead?</h2>
                <p>If your order is complex — mixed garments, special sizing, or you'd prefer to talk it through — our team is ready to help.</p>
            </div>
            <div class="tsa-svh-cta__actions">
                <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-btn tsa-btn-primary">Request a Quote</a>
                <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="tsa-btn tsa-btn-outline">Contact Us</a>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>

<script>
// FAQ accordion — reuses .tsa-qr-faq-* classes
document.querySelectorAll('#tsa-cfg-faq-list .tsa-qr-faq-q').forEach(function(btn){
    btn.addEventListener('click', function(){
        var open = btn.getAttribute('aria-expanded') === 'true';
        document.querySelectorAll('#tsa-cfg-faq-list .tsa-qr-faq-q').forEach(function(b){
            b.setAttribute('aria-expanded','false');
            b.closest('.tsa-qr-faq-item').classList.remove('is-open');
            var a = document.getElementById(b.getAttribute('aria-controls'));
            if(a) a.hidden = true;
        });
        if(!open){ btn.setAttribute('aria-expanded','true'); btn.closest('.tsa-qr-faq-item').classList.add('is-open'); var ans=document.getElementById(btn.getAttribute('aria-controls')); if(ans) ans.hidden=false; }
    });
});
</script>
