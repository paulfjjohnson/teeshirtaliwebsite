<?php
/**
 * Template Name: TSA Request a Quote
 *
 * Category-driven quote form: Custom Apparel · DTF / Gang Sheets · Graphic Design.
 * "Store" tile redirects to /request-a-store/.
 * Assign to: /request-a-quote/
 */

defined( 'ABSPATH' ) || exit;

/* ══════════════════════════════════════════════════════════
   FORM SUBMISSION
══════════════════════════════════════════════════════════ */
$submitted    = false;
$submit_error = '';

if ( $_SERVER['REQUEST_METHOD'] === 'POST' && isset( $_POST['tsa_quote_nonce'] ) ) {
    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_quote_nonce'] ) ), 'tsa_quote_request' ) ) {
        $submit_error = 'Security check failed. Please try again.';
    } else {

        /* ── Universal fields ── */
        $q_cat   = sanitize_key(            $_POST['q_category']  ?? 'apparel' );
        $q_name  = sanitize_text_field(     $_POST['q_name']      ?? '' );
        $q_email = sanitize_email(          $_POST['q_email']     ?? '' );
        $q_phone = sanitize_text_field(     $_POST['q_phone']     ?? '' );
        $q_org   = sanitize_text_field(     $_POST['q_org']       ?? '' );
        $q_rush  = isset( $_POST['q_rush'] ) ? 'Yes — Rush order requested' : 'No';
        $q_notes = sanitize_textarea_field( $_POST['q_notes']     ?? '' );

        /* ── Apparel fields ── */
        $q_product   = sanitize_text_field( $_POST['q_product']   ?? '' );
        $q_qty       = sanitize_text_field( $_POST['q_qty']       ?? '' );
        $q_gcolors   = sanitize_text_field( $_POST['q_gcolors']   ?? '' );
        $q_locations = isset( $_POST['q_locations'] )
                        ? implode( ', ', array_map( 'sanitize_text_field', (array) $_POST['q_locations'] ) )
                        : '';
        $q_pstyle    = sanitize_text_field( $_POST['q_pstyle']    ?? '' );
        $q_artwork   = sanitize_text_field( $_POST['q_artwork']   ?? '' );
        $q_deadline  = sanitize_text_field( $_POST['q_deadline']  ?? '' );

        /* ── DTF fields ── */
        $q_sheets    = isset( $_POST['q_sheets'] )
                        ? implode( ', ', array_map( 'sanitize_text_field', (array) $_POST['q_sheets'] ) )
                        : '';
        $q_dtf_qty   = sanitize_text_field( $_POST['q_dtf_qty']   ?? '' );
        $q_dtf_dims  = sanitize_text_field( $_POST['q_dtf_dims']  ?? '' );
        $q_dtf_dead  = sanitize_text_field( $_POST['q_dtf_dead']  ?? '' );

        /* ── Design fields ── */
        $q_dtype     = sanitize_text_field( $_POST['q_dtype']     ?? '' );
        $q_brief     = sanitize_textarea_field( $_POST['q_brief'] ?? '' );
        $q_des_dead  = sanitize_text_field( $_POST['q_des_dead']  ?? '' );

        /* ── Artwork / reference file upload ── */
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $art_path = '';
        $art_url  = '';
        // Artwork upload (optional). NEVER drop a file silently — if the customer
        // attached something we can't accept, block the submit and tell them, so a
        // quote never goes through thinking it sent art when it didn't.
        $art_max = (int) apply_filters( 'tsa_quote_upload_max_bytes', 50 * 1024 * 1024 ); // 50 MB
        // The form exposes two optional file inputs — "q_artfile" (apparel/DTF
        // artwork) and "q_reffile" (design reference). Only one category's field
        // is visible at a time, but the browser still submits the hidden, empty
        // sibling. Pick whichever input actually carries a file so an empty one
        // can't shadow a real upload (which would drop the file silently).
        $art_file = null;
        foreach ( [ 'q_artfile', 'q_reffile' ] as $art_field ) {
            if ( isset( $_FILES[ $art_field ] ) && ! empty( $_FILES[ $art_field ]['name'] ) ) {
                $art_file = $_FILES[ $art_field ];
                break;
            }
        }
        if ( $art_file ) {
            $ferr = (int) $art_file['error'];
            if ( in_array( $ferr, [ UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE ], true ) || (int) $art_file['size'] > $art_max ) {
                $submit_error = 'Your file is too large (limit ' . size_format( $art_max ) . '). Please send a smaller file, or email your artwork to us separately.';
            } elseif ( $ferr !== UPLOAD_ERR_OK ) {
                $submit_error = 'Your file couldn\'t be uploaded — please try again, or email your artwork to us separately.';
            } else {
                $art_overrides = [
                    'test_form' => false,
                    'mimes'     => apply_filters( 'tsa_quote_upload_mimes', [
                        'jpg|jpeg|jpe' => 'image/jpeg',
                        'png'          => 'image/png',
                        'gif'          => 'image/gif',
                        'webp'         => 'image/webp',
                        'bmp'          => 'image/bmp',
                        'tif|tiff'     => 'image/tiff',
                        'heic'         => 'image/heic',
                        'heif'         => 'image/heif',
                        'svg'          => 'image/svg+xml',
                        'pdf'          => 'application/pdf',
                        'eps'          => 'application/postscript',
                        'ai'           => 'application/postscript',
                        'psd'          => 'image/vnd.adobe.photoshop',
                        'zip'          => 'application/zip',
                    ] ),
                ];
                // Keep quote uploads tidy: route them under /uploads/quote-requests/YYYY/MM.
                $q_updir = function ( $dirs ) {
                    $dirs['subdir'] = '/quote-requests' . $dirs['subdir'];
                    $dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
                    $dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
                    return $dirs;
                };
                add_filter( 'upload_dir', $q_updir );
                $uploaded = wp_handle_upload( $art_file, $art_overrides );
                remove_filter( 'upload_dir', $q_updir );
                if ( $uploaded && empty( $uploaded['error'] ) ) {
                    $art_url  = $uploaded['url'];
                    $art_path = $uploaded['file'];
                } else {
                    $submit_error = 'We couldn\'t accept that file type. Supported: JPG, PNG, GIF, WEBP, HEIC, TIFF, BMP, SVG, PDF, EPS, AI, PSD, ZIP. Please convert your file or email it to us separately.';
                }
            }
        }

        /* ── Validation ── */
        if ( ! $q_name || ! $q_email ) {
            $submit_error = 'Please fill in your name and email address.';
        }

        // Only send if nothing failed — including a rejected/too-big upload above.
        if ( ! $submit_error ) {

            /* ── Build email ── */
            $cat_labels = [
                'apparel' => 'Custom Apparel',
                'dtf'     => 'DTF Transfers / Gang Sheets',
                'design'  => 'Graphic Design',
            ];
            $cat_label = $cat_labels[ $q_cat ] ?? ucfirst( $q_cat );

            $body  = "NEW QUOTE REQUEST — TSA\n";
            $body .= str_repeat( '=', 44 ) . "\n\n";
            $body .= "Category:    {$cat_label}\n";
            $body .= "Rush Order:  {$q_rush}\n\n";

            $body .= "CONTACT\n";
            $body .= "Name:        {$q_name}\n";
            $body .= "Email:       {$q_email}\n";
            $body .= "Phone:       {$q_phone}\n";
            $body .= "Org / Group: {$q_org}\n\n";

            if ( $q_cat === 'apparel' ) {
                $body .= "APPAREL DETAILS\n";
                $body .= "Product:     {$q_product}\n";
                $body .= "Quantity:    {$q_qty}\n";
                $body .= "Colors:      {$q_gcolors}\n";
                $body .= "Locations:   {$q_locations}\n";
                $body .= "Print Style: {$q_pstyle}\n";
                $body .= "Artwork:     {$q_artwork}\n";
                $body .= "Deadline:    {$q_deadline}\n\n";
            } elseif ( $q_cat === 'dtf' ) {
                $body .= "DTF / GANG SHEET DETAILS\n";
                $body .= "Sheet Sizes: {$q_sheets}\n";
                $body .= "Quantity:    {$q_dtf_qty}\n";
                $body .= "Dimensions:  {$q_dtf_dims}\n";
                $body .= "Deadline:    {$q_dtf_dead}\n\n";
            } elseif ( $q_cat === 'design' ) {
                $body .= "DESIGN SERVICE DETAILS\n";
                $body .= "Type:        {$q_dtype}\n";
                $body .= "Deadline:    {$q_des_dead}\n\n";
                $body .= "Brief:\n{$q_brief}\n\n";
            }

            if ( $art_url ) {
                $body .= "── FILE UPLOAD ──────────────────────────\n";
                $body .= "URL:         {$art_url}\n\n";
            }

            if ( $q_notes ) {
                $body .= "ADDITIONAL NOTES:\n{$q_notes}\n";
            }

            $to          = function_exists( 'tsa_form_recipient' ) ? tsa_form_recipient( 'quote' ) : get_option( 'admin_email' );
            $subject     = "[TSA Quote] {$cat_label} — {$q_name}" . ( $q_rush === 'Yes — Rush order requested' ? ' 🔴 RUSH' : '' );
            $headers     = [
                'Content-Type: text/plain; charset=UTF-8',
                "Reply-To: {$q_name} <{$q_email}>",
            ];
            $attachments = $art_path ? [ $art_path ] : [];

            wp_mail( $to, $subject, $body, $headers, $attachments );
            if ( function_exists( 'tsa_record_request' ) ) {
                tsa_record_request( [
                    'type'       => 'quote',
                    'name'       => $q_name,
                    'email'      => $q_email,
                    'phone'      => isset( $q_phone ) ? $q_phone : '',
                    'message'    => $body,
                    'attachment' => $art_url,
                ] );
            }
            $submitted = true;
        }
    }
}

/* ── Active category (POST or GET) ── */
$q_cat = sanitize_key( $_POST['q_category'] ?? $_GET['cat'] ?? 'apparel' );
if ( ! in_array( $q_cat, [ 'apparel', 'dtf', 'design' ], true ) ) $q_cat = 'apparel';

/* ── Preselect the design service from ?svc= (deep-links from the Graphic Design cards) ── */
$q_svc_map = [
    'logo'   => 'Logo / Brand Mark',
    'print'  => 'Print Layout (shirt, hat, etc.)',
    'social' => 'Social Media Graphic',
    'flyer'  => 'Flyer / Poster',
    'brand'  => 'Full Brand Package',
];
$q_svc        = sanitize_key( $_GET['svc'] ?? '' );
$q_dtype_sel  = ( isset( $_POST['q_dtype'] ) && $_POST['q_dtype'] !== '' )
    ? sanitize_text_field( wp_unslash( $_POST['q_dtype'] ) )
    : ( $q_svc_map[ $q_svc ] ?? '' );
$q_dtype_opts = [
    'Logo / Brand Mark',
    'Print Layout (shirt, hat, etc.)',
    'Social Media Graphic',
    'Flyer / Poster',
    'Full Brand Package',
    "Other — I'll explain below",
];

get_header();
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-qr-hero">
    <div class="tsa-sd-container">
        <div class="tsa-kicker tsa-kicker--pink">Custom Merch & Printing</div>
        <h1 class="tsa-qr-hero__title">Request a Quote</h1>
        <p class="tsa-qr-hero__sub">Tell us about your project and we'll respond with pricing, timeline, and options within one business day.</p>

        <div class="tsa-qr-perks">
            <div class="tsa-qr-perk">
                <span class="tsa-qr-perk__icon">⚡</span>
                <span>Response within 1 business day</span>
            </div>
            <div class="tsa-qr-perk">
                <span class="tsa-qr-perk__icon">💰</span>
                <span>Transparent pricing — no hidden fees</span>
            </div>
            <div class="tsa-qr-perk">
                <span class="tsa-qr-perk__icon">🎨</span>
                <span>Free design consultation included</span>
            </div>
            <div class="tsa-qr-perk">
                <span class="tsa-qr-perk__icon">📦</span>
                <span>No minimums on most DTF orders</span>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     CATEGORY TILES + FORM
══════════════════════════════════════ -->
<section class="tsa-qr-section">
    <div class="tsa-sd-container">

        <!-- ── Category Selector ── -->
        <div class="tsa-qr-tiles" id="tsa-qr-tiles" role="radiogroup" aria-label="Quote category">

            <label class="tsa-qr-tile <?php echo $q_cat === 'apparel' ? 'is-active' : ''; ?>">
                <input type="radio" name="q_cat_ui" value="apparel" <?php checked( $q_cat, 'apparel' ); ?> class="tsa-qr-tile__radio">
                <span class="tsa-qr-tile__icon">👕</span>
                <span class="tsa-qr-tile__label">Custom Apparel</span>
                <span class="tsa-qr-tile__desc">T-shirts, hoodies, hats, bags &amp; more</span>
            </label>

            <label class="tsa-qr-tile <?php echo $q_cat === 'dtf' ? 'is-active' : ''; ?>">
                <input type="radio" name="q_cat_ui" value="dtf" <?php checked( $q_cat, 'dtf' ); ?> class="tsa-qr-tile__radio">
                <span class="tsa-qr-tile__icon">🖨️</span>
                <span class="tsa-qr-tile__label">DTF Transfers</span>
                <span class="tsa-qr-tile__desc">Gang sheets &amp; ready-to-press transfers</span>
            </label>

            <label class="tsa-qr-tile <?php echo $q_cat === 'design' ? 'is-active' : ''; ?>">
                <input type="radio" name="q_cat_ui" value="design" <?php checked( $q_cat, 'design' ); ?> class="tsa-qr-tile__radio">
                <span class="tsa-qr-tile__icon">🎨</span>
                <span class="tsa-qr-tile__label">Graphic Design</span>
                <span class="tsa-qr-tile__desc">Logos, layouts &amp; brand artwork</span>
            </label>

            <a href="<?php echo esc_url( home_url( '/request-a-store/' ) ); ?>" class="tsa-qr-tile tsa-qr-tile--store">
                <span class="tsa-qr-tile__icon">🏪</span>
                <span class="tsa-qr-tile__label">Store Setup</span>
                <span class="tsa-qr-tile__desc">School, team, or business store</span>
                <span class="tsa-qr-tile__arrow">→</span>
            </a>

        </div>

        <!-- ── Two-Column Body ── -->
        <div class="tsa-qr-body">

            <!-- FORM COLUMN -->
            <div class="tsa-qr-form-col">

                <?php if ( $submitted ) : ?>
                <!-- ── Success state ── -->
                <div class="tsa-qr-success">
                    <div class="tsa-qr-success__icon">✓</div>
                    <h2 class="tsa-qr-success__title">Quote Request Received!</h2>
                    <p class="tsa-qr-success__sub">We'll review your project details and reach back out within one business day with pricing and options.</p>
                    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="tsa-btn tsa-btn-primary" style="margin-top:8px">Back to Home</a>
                </div>

                <?php else : ?>

                <?php if ( $submit_error ) : ?>
                <div class="tsa-qr-error"><?php echo esc_html( $submit_error ); ?></div>
                <?php endif; ?>

                <form class="tsa-rs-form" method="POST" enctype="multipart/form-data"
                      action="<?php echo esc_url( get_permalink() . '?cat=' . esc_attr( $q_cat ) ); ?>">
                    <?php wp_nonce_field( 'tsa_quote_request', 'tsa_quote_nonce' ); ?>
                    <input type="hidden" id="q_category" name="q_category" value="<?php echo esc_attr( $q_cat ); ?>">

                    <!-- ①  CUSTOM APPAREL -->
                    <div class="tsa-qr-cat-section" data-cat="apparel"
                         style="<?php echo $q_cat !== 'apparel' ? 'display:none' : ''; ?>">
                        <div class="tsa-rs-fieldset">
                            <div class="tsa-rs-fieldset-title">Apparel Details</div>

                            <div class="tsa-rs-row">
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="q_product">Product Type</label>
                                    <select class="tsa-rs-select" id="q_product" name="q_product">
                                        <option value="">Select…</option>
                                        <option <?php selected( $_POST['q_product'] ?? '', 'T-Shirt (Short Sleeve)' ); ?>>T-Shirt (Short Sleeve)</option>
                                        <option <?php selected( $_POST['q_product'] ?? '', 'Long Sleeve Tee' ); ?>>Long Sleeve Tee</option>
                                        <option <?php selected( $_POST['q_product'] ?? '', 'Hoodie / Sweatshirt' ); ?>>Hoodie / Sweatshirt</option>
                                        <option <?php selected( $_POST['q_product'] ?? '', 'Performance / Athletic' ); ?>>Performance / Athletic</option>
                                        <option <?php selected( $_POST['q_product'] ?? '', 'Tank Top' ); ?>>Tank Top</option>
                                        <option <?php selected( $_POST['q_product'] ?? '', 'Hat / Cap' ); ?>>Hat / Cap</option>
                                        <option <?php selected( $_POST['q_product'] ?? '', 'Jacket / Outerwear' ); ?>>Jacket / Outerwear</option>
                                        <option <?php selected( $_POST['q_product'] ?? '', 'Bag / Tote' ); ?>>Bag / Tote</option>
                                        <option <?php selected( $_POST['q_product'] ?? '', 'Multiple types' ); ?>>Multiple types</option>
                                        <option <?php selected( $_POST['q_product'] ?? '', 'Not sure yet' ); ?>>Not sure yet</option>
                                    </select>
                                </div>
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="q_qty">Estimated Quantity</label>
                                    <select class="tsa-rs-select" id="q_qty" name="q_qty">
                                        <option value="">Select range…</option>
                                        <option>1–12 pieces</option>
                                        <option>13–24 pieces</option>
                                        <option>25–49 pieces</option>
                                        <option>50–99 pieces</option>
                                        <option>100–249 pieces</option>
                                        <option>250+ pieces</option>
                                        <option>Not sure yet</option>
                                    </select>
                                </div>
                            </div>

                            <div class="tsa-rs-field">
                                <label class="tsa-rs-label">Print Locations <span class="tsa-rs-label-opt"> — check all that apply</span></label>
                                <div class="tsa-rs-checkboxes">
                                    <?php foreach ( [ 'Front', 'Back', 'Left Chest', 'Right Chest', 'Left Sleeve', 'Right Sleeve', 'Full Wrap', 'Not sure' ] as $loc ) : ?>
                                    <label class="tsa-rs-check-label">
                                        <input type="checkbox" name="q_locations[]" value="<?php echo esc_attr( $loc ); ?>"
                                               <?php checked( in_array( $loc, (array) ( $_POST['q_locations'] ?? [] ), true ) ); ?>>
                                        <?php echo esc_html( $loc ); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="tsa-rs-row">
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="q_gcolors">Garment Color(s)</label>
                                    <input class="tsa-rs-input" type="text" id="q_gcolors" name="q_gcolors"
                                           placeholder="e.g. Black, White, Navy"
                                           value="<?php echo esc_attr( $_POST['q_gcolors'] ?? '' ); ?>">
                                </div>
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="q_pstyle">Print Style</label>
                                    <select class="tsa-rs-select" id="q_pstyle" name="q_pstyle">
                                        <option value="">Select…</option>
                                        <option>Full Color (DTF)</option>
                                        <option>1–3 Spot Colors</option>
                                        <option>Embroidery</option>
                                        <option>Not sure — recommend one</option>
                                    </select>
                                </div>
                            </div>

                            <div class="tsa-rs-row">
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="q_artwork">Do You Have Artwork?</label>
                                    <select class="tsa-rs-select" id="q_artwork" name="q_artwork">
                                        <option value="">Select…</option>
                                        <option>Yes — print-ready file</option>
                                        <option>Yes — rough idea / logo</option>
                                        <option>No — I need design help</option>
                                        <option>I want a design from the library</option>
                                    </select>
                                </div>
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="q_deadline">Target In-Hands Date</label>
                                    <input class="tsa-rs-input" type="text" id="q_deadline" name="q_deadline"
                                           placeholder="e.g. June 15 or ASAP"
                                           value="<?php echo esc_attr( $_POST['q_deadline'] ?? '' ); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ②  DTF TRANSFERS / GANG SHEETS -->
                    <div class="tsa-qr-cat-section" data-cat="dtf"
                         style="<?php echo $q_cat !== 'dtf' ? 'display:none' : ''; ?>">
                        <div class="tsa-rs-fieldset">
                            <div class="tsa-rs-fieldset-title">Transfer Details</div>

                            <div class="tsa-rs-field">
                                <label class="tsa-rs-label">Sheet / Transfer Sizes <span class="tsa-rs-label-opt"> — check all that apply</span></label>
                                <div class="tsa-rs-checkboxes">
                                    <?php foreach ( [ '11×17 (Letter)', '13×19 (Tabloid)', '22×17 (Double Letter)', 'Custom / Gang Sheet', 'Not sure — advise me' ] as $sz ) : ?>
                                    <label class="tsa-rs-check-label">
                                        <input type="checkbox" name="q_sheets[]" value="<?php echo esc_attr( $sz ); ?>"
                                               <?php checked( in_array( $sz, (array) ( $_POST['q_sheets'] ?? [] ), true ) ); ?>>
                                        <?php echo esc_html( $sz ); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>

                            <div class="tsa-rs-row">
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="q_dtf_qty">Quantity</label>
                                    <input class="tsa-rs-input" type="text" id="q_dtf_qty" name="q_dtf_qty"
                                           placeholder="e.g. 50 sheets"
                                           value="<?php echo esc_attr( $_POST['q_dtf_qty'] ?? '' ); ?>">
                                </div>
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="q_dtf_dims">Design Dimensions <span class="tsa-rs-label-opt">(if known)</span></label>
                                    <input class="tsa-rs-input" type="text" id="q_dtf_dims" name="q_dtf_dims"
                                           placeholder='e.g. 12" × 14"'
                                           value="<?php echo esc_attr( $_POST['q_dtf_dims'] ?? '' ); ?>">
                                </div>
                            </div>

                            <div class="tsa-rs-field">
                                <label class="tsa-rs-label" for="q_dtf_dead">Target Date</label>
                                <input class="tsa-rs-input" type="text" id="q_dtf_dead" name="q_dtf_dead"
                                       placeholder="e.g. June 15 or ASAP"
                                       value="<?php echo esc_attr( $_POST['q_dtf_dead'] ?? '' ); ?>">
                            </div>
                        </div>
                    </div>

                    <!-- ③  GRAPHIC DESIGN -->
                    <div class="tsa-qr-cat-section" data-cat="design"
                         style="<?php echo $q_cat !== 'design' ? 'display:none' : ''; ?>">
                        <div class="tsa-rs-fieldset">
                            <div class="tsa-rs-fieldset-title">Design Details</div>

                            <div class="tsa-rs-row">
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="q_dtype">What Do You Need?</label>
                                    <select class="tsa-rs-select" id="q_dtype" name="q_dtype">
                                        <option value="">Select…</option>
                                        <?php foreach ( $q_dtype_opts as $opt ) : ?>
                                        <option <?php selected( $q_dtype_sel, $opt ); ?>><?php echo esc_html( $opt ); ?></option>
                                        <?php endforeach; ?>
                                    </select>
                                </div>
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="q_des_dead">Deadline</label>
                                    <input class="tsa-rs-input" type="text" id="q_des_dead" name="q_des_dead"
                                           placeholder="e.g. June 15 or ASAP"
                                           value="<?php echo esc_attr( $_POST['q_des_dead'] ?? '' ); ?>">
                                </div>
                            </div>

                            <div class="tsa-rs-field">
                                <label class="tsa-rs-label" for="q_brief">Describe Your Vision</label>
                                <textarea class="tsa-rs-textarea" id="q_brief" name="q_brief" rows="4"
                                          placeholder="Colors, style, vibe, references — anything that helps us understand what you're going for."><?php echo esc_textarea( $_POST['q_brief'] ?? '' ); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- ④  ARTWORK / FILE UPLOAD (Apparel + DTF) -->
                    <div class="tsa-qr-cat-section" data-cat="apparel dtf"
                         style="<?php echo ! in_array( $q_cat, [ 'apparel', 'dtf' ], true ) ? 'display:none' : ''; ?>">
                        <div class="tsa-rs-fieldset">
                            <div class="tsa-rs-fieldset-title">Upload Artwork <span style="font-weight:400;opacity:.6;font-size:12px">— optional</span></div>
                            <div class="tsa-rs-field">
                                <div class="tsa-rs-upload-zone" id="tsa-qr-upload-zone">
                                    <input type="file" id="q_artfile" name="q_artfile"
                                           class="tsa-rs-file-input"
                                           accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.heic,.heif,.svg,.pdf,.eps,.ai,.psd,.zip">
                                    <div class="tsa-rs-upload-inner" id="tsa-qr-upload-inner">
                                        <div class="tsa-rs-upload-icon">
                                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                <polyline points="17 8 12 3 7 8" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                <line x1="12" y1="3" x2="12" y2="15" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                                            </svg>
                                        </div>
                                        <p class="tsa-rs-upload-text">
                                            <span class="tsa-rs-upload-browse">Click to upload</span> or drag &amp; drop
                                        </p>
                                        <p class="tsa-rs-upload-hint">AI &nbsp;&middot;&nbsp; EPS &nbsp;&middot;&nbsp; PSD &nbsp;&middot;&nbsp; PDF &nbsp;&middot;&nbsp; SVG &nbsp;&middot;&nbsp; PNG &nbsp;&middot;&nbsp; JPG &nbsp;&middot;&nbsp; WEBP &nbsp;&middot;&nbsp; HEIC &nbsp;&middot;&nbsp; ZIP &nbsp;&middot;&nbsp; Max&nbsp;50&nbsp;MB</p>
                                    </div>
                                    <div class="tsa-rs-upload-preview" id="tsa-qr-upload-preview" style="display:none">
                                        <img class="tsa-rs-preview-img" id="tsa-qr-preview-img" src="" alt="" style="display:none">
                                        <div class="tsa-rs-preview-info">
                                            <span class="tsa-rs-preview-name" id="tsa-qr-preview-name"></span>
                                            <span class="tsa-rs-preview-size" id="tsa-qr-preview-size"></span>
                                        </div>
                                        <button type="button" class="tsa-rs-upload-remove" id="tsa-qr-upload-remove" aria-label="Remove file">&times;</button>
                                    </div>
                                </div>
                                <p class="tsa-rs-field-note">Don't have a file ready? No problem — you can email it later or we can work from a rough sketch.</p>
                            </div>
                        </div>
                    </div>

                    <!-- ⑤  REFERENCE UPLOAD (Design only) -->
                    <div class="tsa-qr-cat-section" data-cat="design"
                         style="<?php echo $q_cat !== 'design' ? 'display:none' : ''; ?>">
                        <div class="tsa-rs-fieldset">
                            <div class="tsa-rs-fieldset-title">Reference / Inspiration <span style="font-weight:400;opacity:.6;font-size:12px">— optional</span></div>
                            <div class="tsa-rs-field">
                                <div class="tsa-rs-upload-zone" id="tsa-qr-ref-zone">
                                    <input type="file" id="q_reffile" name="q_reffile"
                                           class="tsa-rs-file-input"
                                           accept=".jpg,.jpeg,.png,.gif,.webp,.bmp,.tif,.tiff,.heic,.heif,.svg,.pdf">
                                    <div class="tsa-rs-upload-inner" id="tsa-qr-ref-inner">
                                        <div class="tsa-rs-upload-icon">
                                            <svg width="28" height="28" viewBox="0 0 24 24" fill="none" aria-hidden="true">
                                                <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                <polyline points="17 8 12 3 7 8" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                                <line x1="12" y1="3" x2="12" y2="15" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                                            </svg>
                                        </div>
                                        <p class="tsa-rs-upload-text">
                                            <span class="tsa-rs-upload-browse">Upload a reference image</span>
                                        </p>
                                        <p class="tsa-rs-upload-hint">PNG &nbsp;&middot;&nbsp; JPG &nbsp;&middot;&nbsp; WEBP &nbsp;&middot;&nbsp; HEIC &nbsp;&middot;&nbsp; PDF &nbsp;&middot;&nbsp; Max&nbsp;50&nbsp;MB</p>
                                    </div>
                                    <div class="tsa-rs-upload-preview" id="tsa-qr-ref-preview" style="display:none">
                                        <img class="tsa-rs-preview-img" id="tsa-qr-ref-img" src="" alt="" style="display:none">
                                        <div class="tsa-rs-preview-info">
                                            <span class="tsa-rs-preview-name" id="tsa-qr-ref-name"></span>
                                            <span class="tsa-rs-preview-size" id="tsa-qr-ref-size"></span>
                                        </div>
                                        <button type="button" class="tsa-rs-upload-remove" id="tsa-qr-ref-remove" aria-label="Remove">&times;</button>
                                    </div>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ⑥  CONTACT + UNIVERSAL -->
                    <div class="tsa-rs-fieldset">
                        <div class="tsa-rs-fieldset-title">Your Info</div>

                        <div class="tsa-rs-row">
                            <div class="tsa-rs-field tsa-rs-field--half">
                                <label class="tsa-rs-label" for="q_name">Full Name <span class="tsa-rs-required">*</span></label>
                                <input class="tsa-rs-input" type="text" id="q_name" name="q_name" required
                                       placeholder="First Last"
                                       value="<?php echo esc_attr( $_POST['q_name'] ?? '' ); ?>">
                            </div>
                            <div class="tsa-rs-field tsa-rs-field--half">
                                <label class="tsa-rs-label" for="q_email">Email Address <span class="tsa-rs-required">*</span></label>
                                <input class="tsa-rs-input" type="email" id="q_email" name="q_email" required
                                       placeholder="you@email.com"
                                       value="<?php echo esc_attr( $_POST['q_email'] ?? '' ); ?>">
                            </div>
                        </div>

                        <div class="tsa-rs-row">
                            <div class="tsa-rs-field tsa-rs-field--half">
                                <label class="tsa-rs-label" for="q_phone">Phone Number</label>
                                <input class="tsa-rs-input" type="tel" id="q_phone" name="q_phone"
                                       placeholder="(225) 555-0100"
                                       value="<?php echo esc_attr( $_POST['q_phone'] ?? '' ); ?>">
                            </div>
                            <div class="tsa-rs-field tsa-rs-field--half">
                                <label class="tsa-rs-label" for="q_org">School / Team / Business <span class="tsa-rs-label-opt"> — if applicable</span></label>
                                <input class="tsa-rs-input" type="text" id="q_org" name="q_org"
                                       placeholder="Organization name"
                                       value="<?php echo esc_attr( $_POST['q_org'] ?? '' ); ?>">
                            </div>
                        </div>

                        <div class="tsa-rs-field">
                            <label class="tsa-rs-label" for="q_notes">Additional Notes</label>
                            <textarea class="tsa-rs-textarea" id="q_notes" name="q_notes" rows="3"
                                      placeholder="Budget, special requirements, questions for us…"><?php echo esc_textarea( $_POST['q_notes'] ?? '' ); ?></textarea>
                        </div>
                    </div>

                    <!-- ⑦  RUSH + SUBMIT -->
                    <div class="tsa-qr-submit-row">
                        <label class="tsa-qr-rush-label">
                            <input type="checkbox" name="q_rush" value="1" class="tsa-qr-rush-check"
                                   <?php checked( isset( $_POST['q_rush'] ) ); ?>>
                            <span class="tsa-qr-rush-text">
                                <strong>Rush Order</strong> — I need this in 2–3 business days
                                <span class="tsa-qr-rush-note">Additional fees may apply</span>
                            </span>
                        </label>
                        <button type="submit" class="tsa-btn tsa-btn-primary tsa-qr-submit-btn" id="tsa-qr-submit">
                            Submit Quote Request →
                        </button>
                    </div>

                </form>
                <?php endif; ?>

            </div><!-- /form col -->

            <!-- SIDEBAR -->
            <aside class="tsa-qr-sidebar">

                <div class="tsa-qr-sidebar-card">
                    <div class="tsa-qr-sidebar-card__title">Quick Info</div>
                    <ul class="tsa-qr-info-list">
                        <li>
                            <span class="tsa-qr-info-icon">⚡</span>
                            <div>
                                <strong>Turnaround</strong>
                                <span>5–7 business days standard<br>2–3 days rush (fees apply)</span>
                            </div>
                        </li>
                        <li>
                            <span class="tsa-qr-info-icon">📁</span>
                            <div>
                                <strong>Accepted File Types</strong>
                                <span>AI · EPS · PSD · PDF · SVG · PNG · JPG · WEBP · HEIC · ZIP (300 dpi+)</span>
                            </div>
                        </li>
                        <li>
                            <span class="tsa-qr-info-icon">📦</span>
                            <div>
                                <strong>Minimums</strong>
                                <span>1 piece — DTF transfers<br>6 pieces — screen print</span>
                            </div>
                        </li>
                        <li>
                            <span class="tsa-qr-info-icon">💬</span>
                            <div>
                                <strong>Prefer to Talk?</strong>
                                <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" class="tsa-qr-contact-link">Contact Us →</a>
                            </div>
                        </li>
                    </ul>
                </div>

                <div class="tsa-qr-sidebar-card tsa-qr-sidebar-card--steps">
                    <div class="tsa-qr-sidebar-card__title">What Happens Next</div>
                    <ol class="tsa-qr-steps">
                        <li>
                            <span class="tsa-qr-step-num">1</span>
                            <div>
                                <strong>We review your request</strong>
                                <span>Within one business day</span>
                            </div>
                        </li>
                        <li>
                            <span class="tsa-qr-step-num">2</span>
                            <div>
                                <strong>Quote sent to your inbox</strong>
                                <span>Pricing, options &amp; timeline</span>
                            </div>
                        </li>
                        <li>
                            <span class="tsa-qr-step-num">3</span>
                            <div>
                                <strong>Approve &amp; we get started</strong>
                                <span>Production begins immediately</span>
                            </div>
                        </li>
                    </ol>
                </div>

            </aside>

        </div><!-- /tsa-qr-body -->

    </div>
</section>

<!-- ══════════════════════════════════════
     TRUST STRIP
══════════════════════════════════════ -->
<section class="tsa-qr-trust">
    <div class="tsa-sd-container">
        <div class="tsa-qr-trust-grid">
            <div class="tsa-qr-trust-item">
                <span class="tsa-qr-trust-num">500+</span>
                <span class="tsa-qr-trust-label">Orders Completed</span>
            </div>
            <div class="tsa-qr-trust-divider"></div>
            <div class="tsa-qr-trust-item">
                <span class="tsa-qr-trust-num">24 hr</span>
                <span class="tsa-qr-trust-label">Quote Turnaround</span>
            </div>
            <div class="tsa-qr-trust-divider"></div>
            <div class="tsa-qr-trust-item">
                <span class="tsa-qr-trust-num">3–5</span>
                <span class="tsa-qr-trust-label">Day Avg. Production</span>
            </div>
            <div class="tsa-qr-trust-divider"></div>
            <div class="tsa-qr-trust-item">
                <span class="tsa-qr-trust-num">5 ★</span>
                <span class="tsa-qr-trust-label">Customer Rating</span>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     Q & A / FAQ
══════════════════════════════════════ -->
<section class="tsa-qr-faq-section">
    <div class="tsa-sd-container">

        <div class="tsa-qr-faq-head">
            <div class="tsa-kicker tsa-kicker--pink">Common Questions</div>
            <h2 class="tsa-qr-faq-title">Before You Hit Submit</h2>
        </div>

        <div class="tsa-qr-faq" id="tsa-qr-faq">

            <?php
            $faqs = [
                [
                    'q' => 'How long does a standard order take?',
                    'a' => 'Most orders are ready within 5–7 business days after artwork is approved. If you have a hard deadline, mention it in the notes and we\'ll do our best to accommodate. Rush production (2–3 business days) is available for an additional fee.',
                ],
                [
                    'q' => 'Do you have order minimums?',
                    'a' => 'For DTF transfers, there is no minimum — you can order a single transfer. For custom apparel with screen printing, the minimum is 6 pieces per design. For embroidery, minimums vary by product. There is no minimum for gang sheet orders.',
                ],
                [
                    'q' => 'What file types do you accept for artwork?',
                    'a' => 'The best files are vector formats: AI, EPS, PDF, or SVG — these scale to any size without losing quality. We also accept PSD, high-resolution raster files (PNG, JPG, WEBP, TIFF, HEIC at 300 dpi or higher), and ZIP archives, up to 50 MB. If you only have a low-resolution file, our design team can recreate it — just mention it in the notes. Anything larger, email it to us and we\'ll take it from there.',
                ],
                [
                    'q' => 'What if I don\'t have artwork ready?',
                    'a' => 'No problem. We offer graphic design services and can create your artwork from a rough sketch, a description, or an inspiration image. Select "No — I need design help" in the artwork field, and we\'ll include a design quote with your response.',
                ],
                [
                    'q' => 'Can I see a proof before you print?',
                    'a' => 'Yes, always. We send a digital proof for your approval before any production begins. No order goes to print until you say go. Revisions to the proof are included.',
                ],
                [
                    'q' => 'Do you offer rush turnaround?',
                    'a' => 'Yes. Rush orders (2–3 business days) are available when production schedule allows. Check the Rush Order box on the form and we\'ll confirm availability in our quote response. Rush fees vary by order size.',
                ],
                [
                    'q' => 'How does pricing work — is there a price list?',
                    'a' => 'Pricing depends on the garment type, print method, quantity, and number of print locations. Because every order is a little different, we quote each project individually. You\'ll receive a full itemized quote via email within one business day — no obligation to move forward.',
                ],
                [
                    'q' => 'Do you ship, or is it local pickup only?',
                    'a' => 'Both. We offer local pickup in the Ascension Parish area and ship anywhere in the continental US. Shipping costs are calculated at the time of order and included in your final invoice.',
                ],
            ];
            foreach ( $faqs as $i => $faq ) :
            ?>
            <div class="tsa-qr-faq-item" id="tsa-faq-<?php echo $i; ?>">
                <button class="tsa-qr-faq-q" aria-expanded="false" aria-controls="tsa-faq-a-<?php echo $i; ?>">
                    <span><?php echo esc_html( $faq['q'] ); ?></span>
                    <span class="tsa-qr-faq-chevron" aria-hidden="true"></span>
                </button>
                <div class="tsa-qr-faq-a" id="tsa-faq-a-<?php echo $i; ?>" role="region" hidden>
                    <p><?php echo esc_html( $faq['a'] ); ?></p>
                </div>
            </div>
            <?php endforeach; ?>

        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     BOTTOM CTA
══════════════════════════════════════ -->
<section class="tsa-qr-bottom-cta">
    <div class="tsa-sd-container">
        <div class="tsa-qr-bottom-cta__inner">
            <div>
                <h2 class="tsa-qr-bottom-cta__title">Need a full store instead?</h2>
                <p class="tsa-qr-bottom-cta__sub">Set up a dedicated online store for your school, team, or business — no upfront cost, no minimums.</p>
            </div>
            <a href="<?php echo esc_url( home_url( '/request-a-store/' ) ); ?>" class="tsa-btn tsa-btn-primary">Get Your Store →</a>
        </div>
    </div>
</section>

<?php get_footer(); ?>

<script>
(function () {

    // ── Category tile switching ───────────────────────────────
    var tiles    = document.querySelectorAll('.tsa-qr-tile:not(.tsa-qr-tile--store)');
    var sections = document.querySelectorAll('.tsa-qr-cat-section');
    var catInput = document.getElementById('q_category');
    var submitBtn = document.getElementById('tsa-qr-submit');

    var btnLabels = {
        apparel: 'Submit Apparel Quote →',
        dtf:     'Submit DTF Quote →',
        design:  'Submit Design Quote →'
    };

    function switchCat(val) {
        tiles.forEach(function (t) {
            t.classList.toggle('is-active', t.querySelector('input') && t.querySelector('input').value === val);
        });
        sections.forEach(function (s) {
            var cats = s.dataset.cat.split(' ');
            s.style.display = cats.indexOf(val) !== -1 ? '' : 'none';
        });
        if (catInput) catInput.value = val;
        if (submitBtn && btnLabels[val]) submitBtn.textContent = btnLabels[val];
    }

    tiles.forEach(function (tile) {
        var radio = tile.querySelector('input[type="radio"]');
        if (!radio) return;
        tile.addEventListener('click', function () {
            switchCat(radio.value);
        });
    });

    // Init
    switchCat(catInput ? catInput.value : 'apparel');


    // ── Upload zones (reusable) ───────────────────────────────
    function initUploadZone(zoneId, inputId, innerId, previewId, imgId, nameId, sizeId, removeId) {
        var zone    = document.getElementById(zoneId);
        var input   = document.getElementById(inputId);
        var inner   = document.getElementById(innerId);
        var preview = document.getElementById(previewId);
        var pImg    = document.getElementById(imgId);
        var pName   = document.getElementById(nameId);
        var pSize   = document.getElementById(sizeId);
        var removeBtn = document.getElementById(removeId);
        if (!zone || !input) return;

        function fmtSize(b) {
            if (b < 1024) return b + ' B';
            if (b < 1048576) return (b / 1024).toFixed(1) + ' KB';
            return (b / 1048576).toFixed(1) + ' MB';
        }
        function showPreview(file) {
            pName.textContent = file.name;
            pSize.textContent = fmtSize(file.size);
            if (file.type.startsWith('image/') && file.type !== 'image/svg+xml') {
                var r = new FileReader();
                r.onload = function (e) { pImg.src = e.target.result; pImg.style.display = 'block'; };
                r.readAsDataURL(file);
            } else { pImg.style.display = 'none'; }
            inner.style.display = 'none';
            preview.style.display = 'flex';
            zone.classList.add('has-file');
        }
        function clearFile() {
            input.value = '';
            pImg.src = ''; pImg.style.display = 'none';
            pName.textContent = ''; pSize.textContent = '';
            inner.style.display = '';
            preview.style.display = 'none';
            zone.classList.remove('has-file', 'is-dragging');
        }
        function handleFile(file) {
            if (!file) return;
            if (file.size > 50 * 1024 * 1024) { alert('File is over 50 MB. Please upload a smaller file, or email it to us separately.'); input.value = ''; return; }
            showPreview(file);
        }
        zone.addEventListener('click', function () { if (!zone.classList.contains('has-file')) input.click(); });
        input.addEventListener('change', function () { if (this.files && this.files[0]) handleFile(this.files[0]); });
        zone.addEventListener('dragover', function (e) { e.preventDefault(); zone.classList.add('is-dragging'); });
        zone.addEventListener('dragleave', function () { zone.classList.remove('is-dragging'); });
        zone.addEventListener('drop', function (e) {
            e.preventDefault(); zone.classList.remove('is-dragging');
            var file = e.dataTransfer && e.dataTransfer.files[0];
            if (file) {
                try { var dt = new DataTransfer(); dt.items.add(file); input.files = dt.files; } catch(ex) {}
                handleFile(file);
            }
        });
        if (removeBtn) removeBtn.addEventListener('click', function (e) { e.stopPropagation(); clearFile(); });
    }

    initUploadZone('tsa-qr-upload-zone','q_artfile','tsa-qr-upload-inner','tsa-qr-upload-preview','tsa-qr-preview-img','tsa-qr-preview-name','tsa-qr-preview-size','tsa-qr-upload-remove');
    initUploadZone('tsa-qr-ref-zone','q_reffile','tsa-qr-ref-inner','tsa-qr-ref-preview','tsa-qr-ref-img','tsa-qr-ref-name','tsa-qr-ref-size','tsa-qr-ref-remove');


    // ── FAQ accordion ─────────────────────────────────────────
    document.querySelectorAll('.tsa-qr-faq-q').forEach(function (btn) {
        btn.addEventListener('click', function () {
            var isOpen = btn.getAttribute('aria-expanded') === 'true';
            var answer = document.getElementById(btn.getAttribute('aria-controls'));

            // Close all others
            document.querySelectorAll('.tsa-qr-faq-q').forEach(function (b) {
                b.setAttribute('aria-expanded', 'false');
                b.closest('.tsa-qr-faq-item').classList.remove('is-open');
                var a = document.getElementById(b.getAttribute('aria-controls'));
                if (a) a.hidden = true;
            });

            if (!isOpen) {
                btn.setAttribute('aria-expanded', 'true');
                btn.closest('.tsa-qr-faq-item').classList.add('is-open');
                if (answer) answer.hidden = false;
            }
        });
    });

})();
</script>
