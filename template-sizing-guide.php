<?php
/**
 * Template Name: TSA Sizing Guide
 *
 * Apparel sizing charts (adult + youth) with measuring tips.
 * Assign to: /sizing-guide/
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>

<section class="tsa-ip-hero">
    <div class="tsa-ip-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">Fit Guide</div>
        <h1>Sizing Guide</h1>
        <p class="tsa-ip-hero__sub">Get the fit right the first time. Charts below are general guidelines — specific styles may vary slightly.</p>
    </div>
</section>

<div class="tsa-ip-wrap tsa-ip-wrap--narrow">

    <div class="tsa-ip-section">
        <div class="tsa-ip-section-head">
            <div class="tsa-kicker tsa-kicker--pink">How to Measure</div>
            <h2>Measuring for the Best Fit</h2>
        </div>
        <div class="tsa-ip-cards">
            <div class="tsa-ip-card">
                <span class="tsa-ip-card__icon">📏</span>
                <h3>Chest / Width</h3>
                <p>Lay a similar shirt flat and measure across the chest, 1″ below the armhole, seam to seam. Double it for full chest circumference.</p>
            </div>
            <div class="tsa-ip-card">
                <span class="tsa-ip-card__icon">📐</span>
                <h3>Length</h3>
                <p>Measure from the highest point of the shoulder straight down to the bottom hem.</p>
            </div>
            <div class="tsa-ip-card">
                <span class="tsa-ip-card__icon">👕</span>
                <h3>Best Practice</h3>
                <p>Compare to a garment you already love. When between sizes, size up for a relaxed fit. Ask us for a sample on large orders.</p>
            </div>
        </div>
    </div>

    <div class="tsa-ip-section">
        <div class="tsa-ip-table-wrap">
            <table class="tsa-ip-table">
                <caption>Adult Unisex T-Shirt (inches)</caption>
                <thead>
                    <tr><th>Size</th><th>Chest (Width)</th><th>Body Length</th></tr>
                </thead>
                <tbody>
                    <tr><td>S</td><td>18"</td><td>28"</td></tr>
                    <tr><td>M</td><td>20"</td><td>29"</td></tr>
                    <tr><td>L</td><td>22"</td><td>30"</td></tr>
                    <tr><td>XL</td><td>24"</td><td>31"</td></tr>
                    <tr><td>2XL</td><td>26"</td><td>32"</td></tr>
                    <tr><td>3XL</td><td>28"</td><td>33"</td></tr>
                    <tr><td>4XL</td><td>30"</td><td>34"</td></tr>
                </tbody>
            </table>
        </div>
        <p class="tsa-ip-note">Extended sizes (2XL and up) include a small per-size upcharge, applied automatically at checkout and in the configurator.</p>
    </div>

    <div class="tsa-ip-section">
        <div class="tsa-ip-table-wrap">
            <table class="tsa-ip-table">
                <caption>Youth T-Shirt (inches)</caption>
                <thead>
                    <tr><th>Size</th><th>Chest (Width)</th><th>Body Length</th></tr>
                </thead>
                <tbody>
                    <tr><td>XS (2–4)</td><td>14"</td><td>20"</td></tr>
                    <tr><td>S (6–8)</td><td>15.5"</td><td>22"</td></tr>
                    <tr><td>M (10–12)</td><td>17"</td><td>24"</td></tr>
                    <tr><td>L (14–16)</td><td>18.5"</td><td>26"</td></tr>
                    <tr><td>XL (18–20)</td><td>20"</td><td>27"</td></tr>
                </tbody>
            </table>
        </div>
    </div>

    <div class="tsa-ip-section">
        <div class="tsa-ip-table-wrap">
            <table class="tsa-ip-table">
                <caption>Hoodies &amp; Crewneck Sweatshirts (inches)</caption>
                <thead>
                    <tr><th>Size</th><th>Chest (Width)</th><th>Body Length</th></tr>
                </thead>
                <tbody>
                    <tr><td>S</td><td>20"</td><td>27"</td></tr>
                    <tr><td>M</td><td>22"</td><td>28"</td></tr>
                    <tr><td>L</td><td>24"</td><td>29"</td></tr>
                    <tr><td>XL</td><td>26"</td><td>30"</td></tr>
                    <tr><td>2XL</td><td>28"</td><td>31"</td></tr>
                    <tr><td>3XL</td><td>30"</td><td>32"</td></tr>
                </tbody>
            </table>
        </div>
        <p class="tsa-ip-note">Charts are general guidelines. Exact measurements vary by brand and style (Gildan, Bella+Canvas, Comfort Colors, etc.). Need specs for a specific blank? <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" style="color:var(--brand-rose)">Ask us</a>.</p>
    </div>

</div>

<section class="tsa-ip-cta">
    <div class="tsa-ip-cta__inner">
        <div>
            <h2>Ready to build your order?</h2>
            <p>Pick your garment and size in the configurator and see live pricing as you go.</p>
        </div>
        <div class="tsa-ip-cta__actions">
            <a href="<?php echo esc_url( home_url( '/configurator/' ) ); ?>" class="tsa-btn tsa-btn-primary">Start Designing</a>
            <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-btn tsa-btn-outline-white">Request a Quote</a>
        </div>
    </div>
</section>

<?php get_footer(); ?>
