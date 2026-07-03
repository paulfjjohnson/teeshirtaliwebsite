<?php
/**
 * Template Name: TSA FAQ
 *
 * Frequently asked questions, grouped by category (native <details> accordion).
 * Assign to: /faq/
 */

defined( 'ABSPATH' ) || exit;

get_header();

$faqs = [
    'Ordering & Quotes' => [
        [
            'q' => 'How do I get a quote?',
            'a' => 'Use our <a href="' . esc_url( home_url( '/request-a-quote/' ) ) . '">Request a Quote</a> form with your garment, quantity, and design details. For custom apparel you can also build it live in the <a href="' . esc_url( home_url( '/configurator/' ) ) . '">configurator</a> to see pricing instantly.',
        ],
        [
            'q' => 'Is there a minimum order?',
            'a' => 'It depends on the product and decoration method. DTF prints and many items have low or no minimums, while some bulk and promotional products have minimums. Your quote will spell it out.',
        ],
        [
            'q' => 'Do you offer bulk pricing?',
            'a' => 'Yes. Pricing drops as quantity goes up. The configurator and your quote both reflect bulk tiers automatically.',
        ],
    ],
    'Artwork & Design' => [
        [
            'q' => 'What file formats do you accept?',
            'a' => 'Vector files (AI, EPS, SVG, PDF) are best. High-resolution PNG (300 DPI, transparent background) also works well. If you only have a logo from social media, send it over and we\'ll let you know if it\'s usable or needs a redraw.',
        ],
        [
            'q' => 'I don\'t have a design. Can you make one?',
            'a' => 'Absolutely. Our <a href="' . esc_url( home_url( '/graphic-design/' ) ) . '">graphic design</a> team can create artwork from scratch, and you can browse ready-to-use art in the <a href="' . esc_url( home_url( '/design-library/' ) ) . '">Design Library</a>.',
        ],
        [
            'q' => 'Will I see a proof before printing?',
            'a' => 'Yes. For custom orders we send a digital proof for your approval. Production starts only after you sign off.',
        ],
    ],
    'Printing & Products' => [
        [
            'q' => 'What is DTF printing?',
            'a' => 'DTF (Direct-to-Film) printing transfers vivid, full-color designs onto apparel with a soft feel and excellent durability. It works on cotton, blends, and more. Learn more on our <a href="' . esc_url( home_url( '/printing-capabilities/' ) ) . '">Printing Capabilities</a> page.',
        ],
        [
            'q' => 'Can I print on my own garments?',
            'a' => 'In many cases, yes. Contact us with the garment details so we can confirm compatibility before you ship anything.',
        ],
        [
            'q' => 'What sizes do you carry?',
            'a' => 'Youth through extended adult sizes on most styles. Extended sizes (2XL+) carry a small upcharge. See the <a href="' . esc_url( home_url( '/sizing-guide/' ) ) . '">Sizing Guide</a>.',
        ],
    ],
    'Stores & Fundraisers' => [
        [
            'q' => 'How does a school or team store work?',
            'a' => 'We build a dedicated online store for your group with no upfront cost and no inventory. Supporters order directly, and we handle production and fulfillment. Explore <a href="' . esc_url( home_url( '/schools/' ) ) . '">school stores</a> and <a href="' . esc_url( home_url( '/team-stores/' ) ) . '">team stores</a>.',
        ],
        [
            'q' => 'Can you help us fundraise?',
            'a' => 'Yes — our <a href="' . esc_url( home_url( '/fundraisers/' ) ) . '">fundraiser program</a> lets your group earn on every sale with zero inventory risk.',
        ],
        [
            'q' => 'What is a Tee Party drop?',
            'a' => 'Limited-edition designs released on a schedule and available only for a short window. Catch them on the <a href="' . esc_url( home_url( '/tee-party/' ) ) . '">Tee Party</a> page.',
        ],
    ],
    'Shipping & Returns' => [
        [
            'q' => 'How long until I get my order?',
            'a' => 'Production turnaround plus shipping. See <a href="' . esc_url( home_url( '/turnaround-times/' ) ) . '">Turnaround Times</a> and our <a href="' . esc_url( home_url( '/shipping-policy/' ) ) . '">Shipping Policy</a> for current windows.',
        ],
        [
            'q' => 'Do you ship nationwide?',
            'a' => 'Yes, we ship across the U.S. Local pickup and delivery are available in the Baton Rouge area.',
        ],
        [
            'q' => 'What if something is wrong with my order?',
            'a' => 'If the error is ours, we make it right. See our <a href="' . esc_url( home_url( '/returns/' ) ) . '">Returns &amp; Exchanges</a> policy.',
        ],
    ],
];
?>

<section class="tsa-ip-hero">
    <div class="tsa-ip-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">Help Center</div>
        <h1>Frequently Asked Questions</h1>
        <p class="tsa-ip-hero__sub">Quick answers about ordering, artwork, printing, stores, and shipping. Still stuck? We're happy to help.</p>
    </div>
</section>

<div class="tsa-ip-wrap tsa-ip-wrap--narrow">
    <div class="tsa-ip-faq">
        <?php foreach ( $faqs as $cat => $items ) : ?>
            <div class="tsa-ip-faq__cat"><?php echo esc_html( $cat ); ?></div>
            <?php foreach ( $items as $item ) : ?>
            <details>
                <summary><?php echo esc_html( $item['q'] ); ?></summary>
                <div class="tsa-ip-faq__a"><p><?php echo wp_kses_post( $item['a'] ); ?></p></div>
            </details>
            <?php endforeach; ?>
        <?php endforeach; ?>
    </div>
</div>

<section class="tsa-ip-cta">
    <div class="tsa-ip-cta__inner">
        <div>
            <h2>Still have a question?</h2>
            <p>Reach out and we'll get you a real answer fast — usually within one business day.</p>
        </div>
        <div class="tsa-ip-cta__actions">
            <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="tsa-btn tsa-btn-primary">Contact Us</a>
            <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-btn tsa-btn-outline-white">Request a Quote</a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
