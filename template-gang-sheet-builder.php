<?php
/**
 * Template Name: TSA Gang Sheet Builder
 *
 * Customer-facing gang sheet builder. Configurable roll widths (per-width
 * price-per-inch), calculated cart price, tenant-themed. Gated by the
 * `gang_sheet` platform feature; the `gang_sheet_custom_sizes` feature unlocks
 * all configured widths (otherwise the first three presets show).
 * Assign to: /gang-sheet-builder/
 */

defined( 'ABSPATH' ) || exit;

get_header();

$page_id    = get_the_ID();
$product_id = (int) get_post_meta( $page_id, '_tsa_gsb_product_id', true );
$widths     = function_exists( 'tsa_gsb_widths' ) ? tsa_gsb_widths( $page_id ) : [ [ 'w' => 13, 'rate' => 0.50, 'max' => 200 ] ];

// Tier: without custom-sizes, show only the first three widths (presets).
$custom_sizes = ! function_exists( 'tsa_feature_active' ) || tsa_feature_active( 'gang_sheet_custom_sizes' );
if ( ! $custom_sizes ) $widths = array_slice( $widths, 0, 3 );
if ( empty( $widths ) ) $widths = [ [ 'w' => 13, 'rate' => 0.50, 'max' => 200 ] ];

$def_w    = $widths[0]['w'];
$def_rate = $widths[0]['rate'];
$cur      = get_woocommerce_currency_symbol();

// Tenant/brand accent (filterable — e.g. school colors on a school store, brand gold by default).
$accent     = apply_filters( 'tsa_gsb_accent', '#d8a85f' );
$accent_ink = function_exists( 'tsa_readable_text' ) ? tsa_readable_text( $accent ) : '#1a1018';

// Feature gate (fail-open on the reference build).
$gsb_on = ! function_exists( 'tsa_feature_active' ) || tsa_feature_active( 'gang_sheet' );

if ( $gsb_on ) {
    wp_localize_script( 'tsa-gang-sheet', 'tsaGSB', [
        'ajaxUrl'   => admin_url( 'admin-ajax.php' ),
        'nonce'     => wp_create_nonce( 'tsa_gsb_nonce' ),
        'productId' => $product_id,
        'pageId'    => $page_id,
        'widths'    => array_values( $widths ),
        'currency'  => $cur,
        'loggedIn'  => is_user_logged_in() ? 1 : 0,
    ] );
}
?>

<style>
.tsa-gsb-root{ --gsb-accent:<?php echo esc_html( $accent ); ?>; --gsb-accent-ink:<?php echo esc_html( $accent_ink ); ?>; }
</style>

<?php if ( ! $gsb_on ) : ?>
<section class="tsa-section" style="text-align:center;padding:80px 7%">
    <div class="tsa-kicker">DTF Printing</div>
    <h1>Gang Sheet Builder</h1>
    <p style="color:var(--tsa-muted);max-width:520px;margin:12px auto 24px">This tool isn't included on your current plan. <a href="/contact/">Contact us</a> to enable it.</p>
</section>
<?php get_footer(); return; endif; ?>

<div class="tsa-gsb-root">

<!-- ══ HERO ══════════════════════════════════════════════ -->
<section class="tsa-gsb-hero">
    <div class="tsa-gsb-container">
        <div class="tsa-kicker">DTF Printing</div>
        <h1>Gang Sheet Builder</h1>
        <p>Upload your designs, we'll pack them tight and print them fast. Choose your roll width, fill the sheet, and order — print-ready in 3–5 days.</p>
        <div class="tsa-gsb-hero-pills">
            <span class="tsa-gsb-pill"><?php echo esc_html( count( $widths ) ); ?> roll width<?php echo count( $widths ) === 1 ? '' : 's'; ?></span>
            <span class="tsa-gsb-pill">300 DPI minimum</span>
            <span class="tsa-gsb-pill">3–5 day turnaround</span>
            <span class="tsa-gsb-pill">No minimums</span>
        </div>
    </div>
</section>

<!-- ══ NOT SURE WHERE TO BEGIN ════════════════════════════ -->
<?php
// Routed Contact recipient (Settings → TSA Form Emails). Coerce array → string (PHP 8).
$gsb_email = function_exists( 'tsa_form_recipient' ) ? tsa_form_recipient( 'contact' ) : get_option( 'admin_email' );
if ( is_array( $gsb_email ) ) { $gsb_email = reset( $gsb_email ); }
$gsb_email = is_string( $gsb_email ) ? $gsb_email : (string) get_option( 'admin_email' );
?>
<div class="tsa-gsb-help">
    <div class="tsa-gsb-container">
        <div class="tsa-gsb-help__inner">
            <span class="tsa-gsb-help__lead">Not sure where to begin?</span>
            <span class="tsa-gsb-help__rest">Email us at
                <a class="tsa-gsb-help__mail" href="mailto:<?php echo antispambot( $gsb_email ); // phpcs:ignore WordPress.Security.EscapeOutput ?>?subject=<?php echo rawurlencode( 'Gangsheet Question' ); ?>"><?php echo antispambot( $gsb_email ); // phpcs:ignore WordPress.Security.EscapeOutput ?></a>
                — or —
            </span>
            <a class="tsa-gsb-btn tsa-gsb-btn--primary tsa-gsb-btn--sm tsa-gsb-help__quote" href="<?php echo esc_url( home_url( '/request-a-quote/?cat=dtf' ) ); ?>">Request a Quote →</a>
        </div>
    </div>
</div>

<!-- ══ BUILDER ════════════════════════════════════════════ -->
<section class="tsa-gsb-tool" id="builder">
    <div class="tsa-gsb-container tsa-gsb-container--wide">

        <div class="tsa-gsb-topbar">
            <div class="tsa-gsb-topbar__left">
                <span class="tsa-gsb-badge tsa-gsb-badge--live">● Builder active</span>
                <span class="tsa-gsb-topbar__note" id="gsb-topbar-note">PNG files · 300 DPI min</span>
            </div>
            <div class="tsa-gsb-topbar__right">
                <?php if ( is_user_logged_in() ) : ?>
                <button class="tsa-gsb-btn tsa-gsb-btn--ghost tsa-gsb-btn--sm" id="gsb-save-btn">Save sheet</button>
                <?php endif; ?>
                <button class="tsa-gsb-btn tsa-gsb-btn--ghost tsa-gsb-btn--sm" id="gsb-clear-all">Clear all</button>
                <button class="tsa-gsb-btn tsa-gsb-btn--ghost tsa-gsb-btn--sm" id="gsb-auto-pack">Auto-pack</button>
            </div>
        </div>

        <!-- Saved sheets (reorder) -->
        <div class="tsa-gsb-saved" id="gsb-saved" style="margin:0 0 14px;padding:12px 14px;border:1px solid rgba(0,0,0,.08);border-radius:10px;background:rgba(0,0,0,.015)">
            <div style="display:flex;align-items:center;justify-content:space-between;gap:10px;margin-bottom:8px">
                <strong style="font-size:13px">Your saved sheets</strong>
                <?php if ( ! is_user_logged_in() ) : ?>
                <span style="font-size:12px;color:var(--tsa-muted,#777)"><a href="<?php echo esc_url( wp_login_url( get_permalink() ) ); ?>">Log in</a> to save &amp; reorder</span>
                <?php endif; ?>
            </div>
            <div id="gsb-saved-list" style="display:flex;flex-wrap:wrap;gap:8px">
                <?php if ( is_user_logged_in() ) : ?>
                <span class="tsa-gsb-saved__empty" style="font-size:12px;color:var(--tsa-muted,#777)">No saved sheets yet — build one and hit <em>Save sheet</em>.</span>
                <?php else : ?>
                <span style="font-size:12px;color:var(--tsa-muted,#777)">Sign in to save your builds and reorder them any time.</span>
                <?php endif; ?>
            </div>
        </div>

        <div class="tsa-gsb-layout">

            <!-- LEFT -->
            <div class="tsa-gsb-left">
                <div class="tsa-gsb-upload" id="gsb-upload-zone">
                    <input type="file" id="gsb-file-input" accept="image/png" multiple hidden />
                    <div class="tsa-gsb-upload__icon" aria-hidden="true">＋</div>
                    <div class="tsa-gsb-upload__title">Drop PNG files here</div>
                    <div class="tsa-gsb-upload__sub">or <span id="gsb-browse-link">browse your computer</span></div>
                    <div class="tsa-gsb-upload__hint">PNG only · max 50 MB per file · 300 DPI recommended</div>
                </div>

                <div class="tsa-gsb-items" id="gsb-item-list">
                    <div class="tsa-gsb-items__empty" id="gsb-empty-state">
                        <span>No designs added yet.</span><br>
                        <span style="font-size:12px; opacity:.6;">Upload PNG files above to get started.</span>
                    </div>
                </div>

                <div class="tsa-gsb-dpi-warn" id="gsb-dpi-warn" style="display:none;">
                    One or more designs are below 300 DPI at the chosen print size. Low-resolution files may print blurry.
                </div>
            </div>

            <!-- RIGHT -->
            <div class="tsa-gsb-right">
                <div class="tsa-gsb-canvas-wrap">
                    <div class="tsa-gsb-canvas-header">
                        <span class="tsa-gsb-canvas-header__label" id="gsb-canvas-label">Sheet preview</span>
                        <span class="tsa-gsb-canvas-header__util" id="gsb-util-display">–</span>
                    </div>
                    <div class="tsa-gsb-canvas-outer" id="gsb-canvas-outer">
                        <canvas id="gsb-canvas"></canvas>
                        <div class="tsa-gsb-canvas-empty" id="gsb-canvas-empty">
                            <span>↑ Upload designs to preview your sheet</span>
                        </div>
                    </div>
                    <div class="tsa-gsb-canvas-ruler" id="gsb-canvas-ruler"></div>
                </div>

                <div class="tsa-gsb-controls">

                    <div class="tsa-gsb-controls__row">
                        <div class="tsa-gsb-controls__group">
                            <label class="tsa-gsb-label" for="gsb-width-select">Roll width</label>
                            <select id="gsb-width-select" class="tsa-gsb-select">
                                <?php foreach ( $widths as $i => $wd ) : ?>
                                <option value="<?php echo esc_attr( $wd['w'] ); ?>" <?php selected( $i, 0 ); ?>><?php echo esc_html( rtrim( rtrim( number_format( $wd['w'], 2 ), '0' ), '.' ) ); ?>″</option>
                                <?php endforeach; ?>
                            </select>
                            <?php if ( ! $custom_sizes && function_exists( 'tsa_feature_active' ) ) : ?>
                            <div class="tsa-gsb-controls__hint">More sizes on the Custom Sizes plan.</div>
                            <?php endif; ?>
                        </div>
                        <div class="tsa-gsb-controls__group">
                            <label class="tsa-gsb-label" for="gsb-length-select">Sheet length</label>
                            <select id="gsb-length-select" class="tsa-gsb-select"><!-- populated by JS --></select>
                            <div class="tsa-gsb-controls__hint" id="gsb-min-length-hint"></div>
                        </div>
                    </div>

                    <div class="tsa-gsb-controls__row">
                        <div class="tsa-gsb-controls__group">
                            <label class="tsa-gsb-label" for="gsb-gap-select">Item gap</label>
                            <select id="gsb-gap-select" class="tsa-gsb-select">
                                <option value="0.125">⅛″ (tight)</option>
                                <option value="0.25" selected>¼″ (standard)</option>
                                <option value="0.375">⅜″</option>
                                <option value="0.5">½″ (loose)</option>
                            </select>
                        </div>
                        <div class="tsa-gsb-controls__group">
                            <label class="tsa-gsb-label" style="display:flex;align-items:center;gap:8px;">
                                <input type="checkbox" id="gsb-rotation-toggle" checked style="accent-color:var(--gsb-accent);" />
                                Allow rotation (fits more)
                            </label>
                        </div>
                    </div>

                    <div class="tsa-gsb-stats" id="gsb-stats" style="display:none;">
                        <div class="tsa-gsb-stat"><div class="tsa-gsb-stat__val" id="gsb-stat-items">0</div><div class="tsa-gsb-stat__label">Designs placed</div></div>
                        <div class="tsa-gsb-stat"><div class="tsa-gsb-stat__val" id="gsb-stat-util">0%</div><div class="tsa-gsb-stat__label">Sheet used</div></div>
                        <div class="tsa-gsb-stat"><div class="tsa-gsb-stat__val" id="gsb-stat-min">–</div><div class="tsa-gsb-stat__label">Min length needed</div></div>
                    </div>

                    <div class="tsa-gsb-cart-row">
                        <div class="tsa-gsb-price-wrap">
                            <div class="tsa-gsb-price-label">Total</div>
                            <div class="tsa-gsb-price" id="gsb-price"><?php echo esc_html( $cur ); ?><?php echo number_format( 10 * $def_rate, 2 ); ?></div>
                            <div class="tsa-gsb-price-note">gang sheet</div>
                        </div>
                        <div class="tsa-gsb-cart-btns">
                            <?php if ( $product_id ) : ?>
                            <button class="tsa-gsb-btn tsa-gsb-btn--primary" id="gsb-add-to-cart">Add to cart →</button>
                            <?php else : ?>
                            <a href="/request-a-quote/" class="tsa-gsb-btn tsa-gsb-btn--primary">Request a Quote →</a>
                            <?php endif; ?>
                        </div>
                    </div>

                    <div class="tsa-gsb-cart-msg" id="gsb-cart-msg" style="display:none;"></div>
                </div>
            </div>
        </div>
    </div>
</section>

<!-- ══ HOW IT WORKS ═══════════════════════════════════════ -->
<section class="tsa-section" style="background:var(--tsa-pink-soft);">
    <div class="tsa-gsb-container">
        <div class="tsa-gsb-section-head"><div class="tsa-kicker">The Process</div><h2>How It Works</h2></div>
        <div class="tsa-gsb-steps">
            <div class="tsa-gsb-step"><div class="tsa-gsb-step__num">1</div><h4>Upload your PNGs</h4><p>Drag and drop your PNG design files. Multiple files at once.</p></div>
            <div class="tsa-gsb-step"><div class="tsa-gsb-step__num">2</div><h4>Pick width &amp; sizes</h4><p>Choose your roll width and set each design's print size. Height auto-calculates; set quantities.</p></div>
            <div class="tsa-gsb-step"><div class="tsa-gsb-step__num">3</div><h4>Auto-pack &amp; review</h4><p>Hit Auto-pack to fill the sheet efficiently. Drag any design to reposition.</p></div>
            <div class="tsa-gsb-step"><div class="tsa-gsb-step__num">4</div><h4>Order &amp; we print</h4><p>Pick your length, add to cart, check out. We print and ship in 3–5 business days.</p></div>
        </div>
    </div>
</section>

<!-- ══ PRICING ════════════════════════════════════════════ -->
<section class="tsa-section" style="background:#fff;">
    <div class="tsa-gsb-container">
        <div class="tsa-gsb-section-head">
            <div class="tsa-kicker">Pricing</div>
            <h2 id="gsb-pricing-title">Simple, by the inch</h2>
            <p>Pay for the length you need. No setup fees, no minimums. Prices update with your chosen roll width.</p>
        </div>
        <div class="tsa-gsb-pricing-grid" id="gsb-pricing-grid"><!-- populated by JS per width --></div>
        <p style="text-align:center; color:var(--tsa-muted); font-size:13px; margin-top:24px;">
            Lengths available in 10″ increments. Select your exact width and length in the builder above.
        </p>
    </div>
</section>

<!-- ══ FAQ ════════════════════════════════════════════════ -->
<section class="tsa-section" style="background:var(--tsa-pink-soft);">
    <div class="tsa-gsb-container tsa-gsb-container--narrow">
        <div class="tsa-gsb-section-head"><div class="tsa-kicker">FAQ</div><h2>Common Questions</h2></div>
        <div class="tsa-gsb-faq">
            <?php
            $faqs = [
                [ 'What file format should I use?', 'PNG files only, with a transparent background for best results. JPEGs aren\'t accepted because they don\'t support transparency.' ],
                [ 'What DPI do I need?', 'Minimum 300 DPI at your intended print size. The builder warns you if any design falls below that. 300–360 DPI is ideal for DTF.' ],
                [ 'What roll widths are available?', 'Pick from the widths in the builder\'s Roll Width selector. Each width is priced per linear inch — wider rolls fit more per row.' ],
                [ 'Can I put the same design multiple times?', 'Yes — set the quantity per design and Auto-pack duplicates and arranges all copies efficiently.' ],
                [ 'How do I know what length to order?', 'Auto-pack tells you the minimum length needed to fit your designs. Order exactly that, or add a little extra.' ],
                [ 'What is the turnaround time?', 'Standard production is 3–5 business days. Rush options available — contact us before ordering.' ],
                [ 'Can I reposition designs manually?', 'Yes — after auto-packing, click and drag any design on the canvas. Utilization and min length update live.' ],
                [ 'What if my designs don\'t fit the selected length?', 'The builder warns you before add-to-cart if your designs overflow. Choose a longer sheet or remove some designs.' ],
            ];
            foreach ( $faqs as $faq ) : ?>
            <details class="tsa-gsb-faq__item">
                <summary class="tsa-gsb-faq__q"><?php echo esc_html( $faq[0] ); ?></summary>
                <p class="tsa-gsb-faq__a"><?php echo esc_html( $faq[1] ); ?></p>
            </details>
            <?php endforeach; ?>
        </div>
    </div>
</section>

<!-- ══ CTA ════════════════════════════════════════════════ -->
<section class="tsa-section tsa-cta">
    <h2>Ready to Print?</h2>
    <p>Build your gang sheet above, or contact us if you need help with file setup, sizing, or bulk orders.</p>
    <div class="tsa-actions tsa-actions--center">
        <a href="#builder" class="tsa-btn tsa-btn-primary">Build Your Sheet</a>
        <?php tsa_btn( '/contact/', 'Contact Us', 'outline-white' ); ?>
    </div>
</section>

</div><!-- .tsa-gsb-root -->

<?php get_footer(); ?>
