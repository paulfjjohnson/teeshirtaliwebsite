<?php
/**
 * Template Name: TSA Request a Store
 *
 * Unified request form for School / Team / Business stores.
 * Pre-selects store type via ?type=school|team|business query param.
 * Assign to: /request-a-store/
 */

defined( 'ABSPATH' ) || exit;

/* ══════════════════════════════════════════════════════
   FORM SUBMISSION
══════════════════════════════════════════════════════ */
$submitted    = false;
$submit_error = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['tsa_rs_nonce'] ) ) {

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_rs_nonce'] ) ), 'tsa_store_request' ) ) {

        $submit_error = 'Security check failed. Please refresh and try again.';

    } else {

        // Universal fields
        $f_type   = sanitize_key(             $_POST['rs_type']   ?? 'general' );
        $f_name   = sanitize_text_field(      $_POST['rs_name']   ?? '' );
        $f_email  = sanitize_email(           $_POST['rs_email']  ?? '' );
        $f_phone  = sanitize_text_field(      $_POST['rs_phone']  ?? '' );
        $f_org    = sanitize_text_field(      $_POST['rs_org']    ?? '' );
        $f_city   = sanitize_text_field(      $_POST['rs_city']   ?? '' );
        $f_state  = sanitize_text_field(      $_POST['rs_state']  ?? '' );
        $f_count  = sanitize_text_field(      $_POST['rs_count']  ?? '' );
        $f_launch = sanitize_text_field(      $_POST['rs_launch'] ?? '' );
        $f_heard  = sanitize_text_field(      $_POST['rs_heard']  ?? '' );
        $f_notes  = sanitize_textarea_field(  $_POST['rs_notes']  ?? '' );

        // Logo upload
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $logo_path = '';
        $logo_url  = '';
        if ( isset( $_FILES['rs_logo'] ) && UPLOAD_ERR_OK === (int) $_FILES['rs_logo']['error'] ) {
            if ( (int) $_FILES['rs_logo']['size'] <= 10 * 1024 * 1024 ) {
                $logo_overrides = [
                    'test_form' => false,
                    'mimes'     => [
                        'jpg|jpeg|jpe' => 'image/jpeg',
                        'png'          => 'image/png',
                        'gif'          => 'image/gif',
                        'svg'          => 'image/svg+xml',
                        'pdf'          => 'application/pdf',
                        'eps'          => 'application/postscript',
                    ],
                ];
                $uploaded = wp_handle_upload( $_FILES['rs_logo'], $logo_overrides );
                if ( $uploaded && ! isset( $uploaded['error'] ) ) {
                    $logo_url  = $uploaded['url'];
                    $logo_path = $uploaded['file'];
                }
            }
        }

        // School-specific
        $f_school_name  = sanitize_text_field( $_POST['rs_school_name']  ?? '' );
        $f_school_role  = sanitize_text_field( $_POST['rs_school_role']  ?? '' );
        $f_school_progs = sanitize_text_field( $_POST['rs_school_progs'] ?? '' );

        // Team-specific
        $f_sport  = sanitize_text_field( $_POST['rs_sport']  ?? '' );
        $f_league = sanitize_text_field( $_POST['rs_league'] ?? '' );
        $f_age    = sanitize_text_field( $_POST['rs_age']    ?? '' );
        $f_season = sanitize_text_field( $_POST['rs_season'] ?? '' );

        // Business-specific
        $f_biz_type  = sanitize_text_field( $_POST['rs_biz_type'] ?? '' );
        $f_biz_freq  = sanitize_text_field( $_POST['rs_biz_freq'] ?? '' );
        $f_products  = isset( $_POST['rs_products'] )
                        ? array_map( 'sanitize_text_field', (array) $_POST['rs_products'] )
                        : [];

        if ( ! $f_name || ! $f_email || ! $f_org ) {
            $submit_error = 'Please fill in all required fields (Name, Email, and Organization/Team/School Name).';
        } else {

            $type_labels = [
                'school'   => 'School Store',
                'team'     => 'Team Store',
                'business' => 'Business Store',
                'general'  => 'Store (General)',
            ];
            $type_label = $type_labels[ $f_type ] ?? 'Store Request';

            // Build plain-text email
            $body  = "NEW TSA STORE REQUEST\n";
            $body .= str_repeat( '=', 42 ) . "\n\n";
            $body .= "Store Type:    {$type_label}\n\n";
            $body .= "── CONTACT ──────────────────────────────\n";
            $body .= "Name:          {$f_name}\n";
            $body .= "Email:         {$f_email}\n";
            $body .= "Phone:         {$f_phone}\n\n";
            $body .= "── ORGANIZATION ─────────────────────────\n";
            $body .= "Name:          {$f_org}\n";
            $body .= "City / State:  {$f_city}, {$f_state}\n";
            $body .= "Est. Members:  {$f_count}\n";
            $body .= "Target Launch: {$f_launch}\n\n";

            if ( 'school' === $f_type ) {
                $body .= "── SCHOOL DETAILS ───────────────────────\n";
                $body .= "School:        {$f_school_name}\n";
                $body .= "Your Role:     {$f_school_role}\n";
                $body .= "Programs:      {$f_school_progs}\n\n";
            } elseif ( 'team' === $f_type ) {
                $body .= "── TEAM DETAILS ─────────────────────────\n";
                $body .= "Sport:         {$f_sport}\n";
                $body .= "League / Org:  {$f_league}\n";
                $body .= "Age / Division:{$f_age}\n";
                $body .= "Season Dates:  {$f_season}\n\n";
            } elseif ( 'business' === $f_type ) {
                $body .= "── BUSINESS DETAILS ─────────────────────\n";
                $body .= "Business Type: {$f_biz_type}\n";
                $body .= "Order Freq:    {$f_biz_freq}\n";
                $body .= "Products:      " . implode( ', ', $f_products ) . "\n\n";
            }

            $body .= "── HOW THEY HEARD ───────────────────────\n";
            $body .= "{$f_heard}\n\n";
            $body .= "── ADDITIONAL NOTES ─────────────────────\n";
            $body .= $f_notes ? $f_notes : '(none)';
            $body .= "\n";

            if ( $logo_url ) {
                $body .= "── LOGO ─────────────────────────────────\n";
                $body .= "File URL:    {$logo_url}\n\n";
            }

            $to      = function_exists( 'tsa_form_recipient' ) ? tsa_form_recipient( 'store' ) : get_option( 'admin_email' );
            $subject = "[TSA] New {$type_label} Request — {$f_org}";
            $headers = [
                'Content-Type: text/plain; charset=UTF-8',
                "Reply-To: {$f_name} <{$f_email}>",
            ];
            $attachments = $logo_path ? [ $logo_path ] : [];

            wp_mail( $to, $subject, $body, $headers, $attachments );
            if ( function_exists( 'tsa_record_request' ) ) {
                tsa_record_request( [
                    'type'    => 'store',
                    'name'    => $f_name,
                    'email'   => $f_email,
                    'phone'   => isset( $f_phone ) ? $f_phone : '',
                    'org'     => $f_org,
                    'message' => $body,
                ] );
            }
            $submitted = true;
        }
    }
}

/* ══════════════════════════════════════════════════════
   TYPE CONFIG  (used by hero + form)
══════════════════════════════════════════════════════ */
$type = $submitted
    ? sanitize_key( $_POST['rs_type'] ?? 'general' )
    : sanitize_key( $_GET['type']     ?? 'general' );

$types = [
    'school'   => [
        'title'  => 'Get Your School Store',
        'kicker' => 'School Spirit Stores',
        'sub'    => 'Fill out the form and we\'ll build a dedicated online store for your school or program — no upfront cost, no minimums.',
        'btn'    => 'Submit School Store Request',
        'accent' => 'var(--tsa-pink)',
    ],
    'team'     => [
        'title'  => 'Get Your Team Store',
        'kicker' => 'Team Spirit Stores',
        'sub'    => 'Tell us about your team and we\'ll set up a custom store your whole squad can shop from — gear drops, custom jerseys, the works.',
        'btn'    => 'Submit Team Store Request',
        'accent' => 'var(--tsa-gold)',
    ],
    'business' => [
        'title'  => 'Get Your Business Store',
        'kicker' => 'Business Stores',
        'sub'    => 'Custom branded merchandise for your staff, clients, and events. We\'ll build your company store and handle fulfillment.',
        'btn'    => 'Submit Business Store Request',
        'accent' => 'var(--tsa-purple)',
    ],
    'general'  => [
        'title'  => 'Get Your Store',
        'kicker' => 'Custom Merch Stores',
        'sub'    => 'Tell us what you need and we\'ll set up a custom online store for your group, team, school, or business.',
        'btn'    => 'Submit Your Request',
        'accent' => 'var(--tsa-pink)',
    ],
];
$t = $types[ $type ] ?? $types['general'];

get_header();
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-rs-hero">
    <div class="tsa-rs-container">
        <div class="tsa-kicker tsa-kicker--pink" id="tsa-rs-kicker"><?php echo esc_html( $t['kicker'] ); ?></div>
        <h1 id="tsa-rs-title"><?php echo esc_html( $t['title'] ); ?></h1>
        <p class="tsa-rs-hero__sub" id="tsa-rs-sub"><?php echo esc_html( $t['sub'] ); ?></p>
    </div>
</section>

<!-- ══════════════════════════════════════
     FORM BODY
══════════════════════════════════════ -->
<section class="tsa-rs-section">
    <div class="tsa-rs-container">
        <div class="tsa-rs-body">

            <!-- ── Form Column ── -->
            <div class="tsa-rs-form-col">

                <?php if ( $submitted ) : ?>
                <!-- SUCCESS STATE -->
                <div class="tsa-rs-success">
                    <div class="tsa-rs-success__icon">✓</div>
                    <h2 class="tsa-rs-success__title">We got it!</h2>
                    <p class="tsa-rs-success__msg">Thanks for reaching out. We'll review your request and get back to you within <strong>24 hours</strong>.</p>
                    <div class="tsa-rs-success__links">
                        <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="tsa-btn tsa-btn-primary">Back to Home</a>
                        <a href="<?php echo esc_url( home_url( '/team-stores/' ) ); ?>" class="tsa-btn tsa-btn-outline">Browse Stores</a>
                    </div>
                </div>

                <?php else : ?>

                <?php if ( $submit_error ) : ?>
                <div class="tsa-rs-error"><?php echo esc_html( $submit_error ); ?></div>
                <?php endif; ?>

                <!-- FORM -->
                <form class="tsa-rs-form" method="POST" enctype="multipart/form-data"
                      action="<?php echo esc_url( get_permalink() . '?type=' . esc_attr( $type ) ); ?>">
                    <?php wp_nonce_field( 'tsa_store_request', 'tsa_rs_nonce' ); ?>

                    <!-- ① STORE TYPE SELECTOR -->
                    <div class="tsa-rs-fieldset">
                        <div class="tsa-rs-fieldset-title">What type of store do you need?</div>
                        <div class="tsa-rs-type-tabs">
                            <?php
                            $tab_opts = [
                                'school'   => 'School Store',
                                'team'     => 'Team Store',
                                'business' => 'Business Store',
                            ];
                            foreach ( $tab_opts as $val => $label ) : ?>
                            <label class="tsa-rs-type-tab<?php echo $type === $val ? ' is-active' : ''; ?>">
                                <input type="radio" name="rs_type" value="<?php echo esc_attr( $val ); ?>"
                                       <?php checked( $type, $val ); ?>>
                                <?php echo esc_html( $label ); ?>
                            </label>
                            <?php endforeach; ?>
                        </div>
                    </div>

                    <!-- ② CONTACT INFO -->
                    <div class="tsa-rs-fieldset">
                        <div class="tsa-rs-fieldset-title">Your Contact Info</div>
                        <div class="tsa-rs-row">
                            <div class="tsa-rs-field tsa-rs-field--half">
                                <label class="tsa-rs-label" for="rs_name">Full Name <span class="tsa-rs-req">*</span></label>
                                <input class="tsa-rs-input" type="text" id="rs_name" name="rs_name"
                                       placeholder="Jane Smith" required
                                       value="<?php echo esc_attr( $_POST['rs_name'] ?? '' ); ?>">
                            </div>
                            <div class="tsa-rs-field tsa-rs-field--half">
                                <label class="tsa-rs-label" for="rs_email">Email Address <span class="tsa-rs-req">*</span></label>
                                <input class="tsa-rs-input" type="email" id="rs_email" name="rs_email"
                                       placeholder="jane@example.com" required
                                       value="<?php echo esc_attr( $_POST['rs_email'] ?? '' ); ?>">
                            </div>
                        </div>
                        <div class="tsa-rs-field">
                            <label class="tsa-rs-label" for="rs_phone">Phone Number</label>
                            <input class="tsa-rs-input" type="tel" id="rs_phone" name="rs_phone"
                                   placeholder="(225) 555-0100"
                                   value="<?php echo esc_attr( $_POST['rs_phone'] ?? '' ); ?>">
                        </div>
                    </div>

                    <!-- ③ ORGANIZATION INFO -->
                    <div class="tsa-rs-fieldset">
                        <div class="tsa-rs-fieldset-title" id="tsa-rs-org-heading">About Your Organization</div>
                        <div class="tsa-rs-field">
                            <label class="tsa-rs-label" for="rs_org">
                                <span id="tsa-rs-org-label">School / Team / Business Name</span>
                                <span class="tsa-rs-req">*</span>
                            </label>
                            <input class="tsa-rs-input" type="text" id="rs_org" name="rs_org"
                                   placeholder="e.g. Muddawgs Baseball" required
                                   value="<?php echo esc_attr( $_POST['rs_org'] ?? '' ); ?>">
                        </div>
                        <div class="tsa-rs-row">
                            <div class="tsa-rs-field tsa-rs-field--half">
                                <label class="tsa-rs-label" for="rs_city">City</label>
                                <input class="tsa-rs-input" type="text" id="rs_city" name="rs_city"
                                       placeholder="Prairieville"
                                       value="<?php echo esc_attr( $_POST['rs_city'] ?? '' ); ?>">
                            </div>
                            <div class="tsa-rs-field tsa-rs-field--half">
                                <label class="tsa-rs-label" for="rs_state">State</label>
                                <input class="tsa-rs-input" type="text" id="rs_state" name="rs_state"
                                       placeholder="LA"
                                       value="<?php echo esc_attr( $_POST['rs_state'] ?? 'LA' ); ?>">
                            </div>
                        </div>
                        <div class="tsa-rs-row">
                            <div class="tsa-rs-field tsa-rs-field--half">
                                <label class="tsa-rs-label" for="rs_count">Estimated Number of Members / Participants</label>
                                <input class="tsa-rs-input" type="text" id="rs_count" name="rs_count"
                                       placeholder="e.g. 45"
                                       value="<?php echo esc_attr( $_POST['rs_count'] ?? '' ); ?>">
                            </div>
                            <div class="tsa-rs-field tsa-rs-field--half">
                                <label class="tsa-rs-label" for="rs_launch">When do you want the store open?</label>
                                <input class="tsa-rs-input" type="text" id="rs_launch" name="rs_launch"
                                       placeholder="e.g. Before Spring season"
                                       value="<?php echo esc_attr( $_POST['rs_launch'] ?? '' ); ?>">
                            </div>
                        </div>
                        <!-- Logo upload -->
                        <div class="tsa-rs-field">
                            <label class="tsa-rs-label">
                                <span id="tsa-rs-logo-label">Logo / Brand Files</span>
                                <span class="tsa-rs-label-opt"> &mdash; optional</span>
                            </label>
                            <div class="tsa-rs-upload-zone" id="tsa-rs-upload-zone">
                                <input type="file" id="rs_logo" name="rs_logo"
                                       class="tsa-rs-file-input"
                                       accept=".jpg,.jpeg,.png,.gif,.svg,.pdf,.eps,.ai">
                                <div class="tsa-rs-upload-inner" id="tsa-rs-upload-inner">
                                    <div class="tsa-rs-upload-icon">
                                        <svg width="28" height="28" viewBox="0 0 24 24" fill="none" xmlns="http://www.w3.org/2000/svg" aria-hidden="true">
                                            <path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                            <polyline points="17 8 12 3 7 8" stroke="currentColor" stroke-width="1.75" stroke-linecap="round" stroke-linejoin="round"/>
                                            <line x1="12" y1="3" x2="12" y2="15" stroke="currentColor" stroke-width="1.75" stroke-linecap="round"/>
                                        </svg>
                                    </div>
                                    <p class="tsa-rs-upload-text">
                                        <span class="tsa-rs-upload-browse">Click to upload</span> or drag &amp; drop
                                    </p>
                                    <p class="tsa-rs-upload-hint">JPG &nbsp;&middot;&nbsp; PNG &nbsp;&middot;&nbsp; SVG &nbsp;&middot;&nbsp; PDF &nbsp;&middot;&nbsp; EPS &nbsp;&middot;&nbsp; AI &nbsp;&middot;&nbsp; Max&nbsp;10&nbsp;MB</p>
                                </div>
                                <div class="tsa-rs-upload-preview" id="tsa-rs-upload-preview" style="display:none">
                                    <img class="tsa-rs-preview-img" id="tsa-rs-preview-img" src="" alt="" style="display:none">
                                    <div class="tsa-rs-preview-info">
                                        <span class="tsa-rs-preview-name" id="tsa-rs-preview-name"></span>
                                        <span class="tsa-rs-preview-size" id="tsa-rs-preview-size"></span>
                                    </div>
                                    <button type="button" class="tsa-rs-upload-remove" id="tsa-rs-upload-remove" aria-label="Remove file">&times;</button>
                                </div>
                            </div>
                            <p class="tsa-rs-field-note">Don't have a logo yet? No problem &mdash; we can work with you to create one.</p>
                        </div>
                    </div>

                    <!-- ④ SCHOOL-SPECIFIC FIELDS -->
                    <div class="tsa-rs-type-section" data-type="school"
                         style="<?php echo $type !== 'school' ? 'display:none' : ''; ?>">
                        <div class="tsa-rs-fieldset tsa-rs-fieldset--accent">
                            <div class="tsa-rs-fieldset-title">School Details</div>
                            <div class="tsa-rs-field">
                                <label class="tsa-rs-label" for="rs_school_name">Which school?</label>
                                <select class="tsa-rs-select" id="rs_school_name" name="rs_school_name">
                                    <option value="">— Select a school —</option>
                                    <?php if ( function_exists( 'tsa_school_directory' ) ) :
                                        $rs_sel = $_POST['rs_school_name'] ?? '';
                                        foreach ( tsa_school_directory() as $rs_grp_label => $rs_grp_schools ) : ?>
                                    <optgroup label="<?php echo esc_attr( $rs_grp_label ); ?>">
                                        <?php foreach ( $rs_grp_schools as $rs_school ) : ?>
                                        <option <?php selected( $rs_sel, $rs_school['name'] ); ?>><?php echo esc_html( $rs_school['name'] ); ?></option>
                                        <?php endforeach; ?>
                                    </optgroup>
                                    <?php endforeach; endif; ?>
                                    <optgroup label="Other">
                                        <option <?php selected( $_POST['rs_school_name'] ?? '', 'Other / Not Listed' ); ?> value="Other / Not Listed">Other / Not Listed</option>
                                    </optgroup>
                                </select>
                            </div>
                            <div class="tsa-rs-field">
                                <label class="tsa-rs-label" for="rs_school_role">Your role at the school</label>
                                <select class="tsa-rs-select" id="rs_school_role" name="rs_school_role">
                                    <option value="">— Select your role —</option>
                                    <option <?php selected( $_POST['rs_school_role'] ?? '', 'Parent / Guardian' ); ?>>Parent / Guardian</option>
                                    <option <?php selected( $_POST['rs_school_role'] ?? '', 'Booster Club / PTG Member' ); ?>>Booster Club / PTG Member</option>
                                    <option <?php selected( $_POST['rs_school_role'] ?? '', 'Coach or Teacher' ); ?>>Coach or Teacher</option>
                                    <option <?php selected( $_POST['rs_school_role'] ?? '', 'School Administrator' ); ?>>School Administrator</option>
                                    <option <?php selected( $_POST['rs_school_role'] ?? '', 'Student Organization Rep' ); ?>>Student Organization Rep</option>
                                    <option <?php selected( $_POST['rs_school_role'] ?? '', 'Other' ); ?>>Other</option>
                                </select>
                            </div>
                            <div class="tsa-rs-field">
                                <label class="tsa-rs-label" for="rs_school_progs">Which programs or sports do you want stores for?</label>
                                <textarea class="tsa-rs-textarea" id="rs_school_progs" name="rs_school_progs"
                                          rows="2"
                                          placeholder="e.g. Color Guard, Marching Band, Football, Basketball..."><?php echo esc_textarea( $_POST['rs_school_progs'] ?? '' ); ?></textarea>
                            </div>
                        </div>
                    </div>

                    <!-- ④ TEAM-SPECIFIC FIELDS -->
                    <div class="tsa-rs-type-section" data-type="team"
                         style="<?php echo $type !== 'team' ? 'display:none' : ''; ?>">
                        <div class="tsa-rs-fieldset tsa-rs-fieldset--accent">
                            <div class="tsa-rs-fieldset-title">Team Details</div>
                            <div class="tsa-rs-row">
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="rs_sport">Sport / Activity <span class="tsa-rs-req">*</span></label>
                                    <input class="tsa-rs-input" type="text" id="rs_sport" name="rs_sport"
                                           placeholder="e.g. Baseball, Soccer, Dance"
                                           value="<?php echo esc_attr( $_POST['rs_sport'] ?? '' ); ?>">
                                </div>
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="rs_league">League or Association</label>
                                    <input class="tsa-rs-input" type="text" id="rs_league" name="rs_league"
                                           placeholder="e.g. USSSA, Rec League"
                                           value="<?php echo esc_attr( $_POST['rs_league'] ?? '' ); ?>">
                                </div>
                            </div>
                            <div class="tsa-rs-row">
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="rs_age">Age Group / Division</label>
                                    <input class="tsa-rs-input" type="text" id="rs_age" name="rs_age"
                                           placeholder="e.g. 12U, Varsity, Adult"
                                           value="<?php echo esc_attr( $_POST['rs_age'] ?? '' ); ?>">
                                </div>
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="rs_season">Season Dates</label>
                                    <input class="tsa-rs-input" type="text" id="rs_season" name="rs_season"
                                           placeholder="e.g. Spring 2026"
                                           value="<?php echo esc_attr( $_POST['rs_season'] ?? '' ); ?>">
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ④ BUSINESS-SPECIFIC FIELDS -->
                    <div class="tsa-rs-type-section" data-type="business"
                         style="<?php echo $type !== 'business' ? 'display:none' : ''; ?>">
                        <div class="tsa-rs-fieldset tsa-rs-fieldset--accent">
                            <div class="tsa-rs-fieldset-title">Business Details</div>
                            <div class="tsa-rs-row">
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="rs_biz_type">Type of Business</label>
                                    <select class="tsa-rs-select" id="rs_biz_type" name="rs_biz_type">
                                        <option value="">— Select type —</option>
                                        <option <?php selected( $_POST['rs_biz_type'] ?? '', 'Restaurant / Food Service' ); ?>>Restaurant / Food Service</option>
                                        <option <?php selected( $_POST['rs_biz_type'] ?? '', 'Salon / Spa / Beauty' ); ?>>Salon / Spa / Beauty</option>
                                        <option <?php selected( $_POST['rs_biz_type'] ?? '', 'Healthcare / Medical' ); ?>>Healthcare / Medical</option>
                                        <option <?php selected( $_POST['rs_biz_type'] ?? '', 'Corporate / Office' ); ?>>Corporate / Office</option>
                                        <option <?php selected( $_POST['rs_biz_type'] ?? '', 'Church / Non-Profit' ); ?>>Church / Non-Profit</option>
                                        <option <?php selected( $_POST['rs_biz_type'] ?? '', 'Construction / Trades' ); ?>>Construction / Trades</option>
                                        <option <?php selected( $_POST['rs_biz_type'] ?? '', 'Sports / Recreation' ); ?>>Sports / Recreation</option>
                                        <option <?php selected( $_POST['rs_biz_type'] ?? '', 'Retail' ); ?>>Retail</option>
                                        <option <?php selected( $_POST['rs_biz_type'] ?? '', 'Other' ); ?>>Other</option>
                                    </select>
                                </div>
                                <div class="tsa-rs-field tsa-rs-field--half">
                                    <label class="tsa-rs-label" for="rs_biz_freq">Expected Order Frequency</label>
                                    <select class="tsa-rs-select" id="rs_biz_freq" name="rs_biz_freq">
                                        <option value="">— Select frequency —</option>
                                        <option <?php selected( $_POST['rs_biz_freq'] ?? '', 'One-time order' ); ?>>One-time order</option>
                                        <option <?php selected( $_POST['rs_biz_freq'] ?? '', 'Seasonal' ); ?>>Seasonal</option>
                                        <option <?php selected( $_POST['rs_biz_freq'] ?? '', 'Monthly' ); ?>>Monthly</option>
                                        <option <?php selected( $_POST['rs_biz_freq'] ?? '', 'Ongoing store' ); ?>>Ongoing store</option>
                                    </select>
                                </div>
                            </div>
                            <div class="tsa-rs-field">
                                <label class="tsa-rs-label">Products you're interested in</label>
                                <div class="tsa-rs-checkboxes">
                                    <?php
                                    $prod_opts = [ 'T-Shirts', 'Hoodies / Sweatshirts', 'Polo Shirts', 'Hats / Caps', 'Bags / Totes', 'Jackets / Outerwear' ];
                                    $sel_prods = (array) ( $_POST['rs_products'] ?? [] );
                                    foreach ( $prod_opts as $opt ) : ?>
                                    <label class="tsa-rs-check-label">
                                        <input type="checkbox" name="rs_products[]"
                                               value="<?php echo esc_attr( $opt ); ?>"
                                               <?php checked( in_array( $opt, $sel_prods, true ) ); ?>>
                                        <?php echo esc_html( $opt ); ?>
                                    </label>
                                    <?php endforeach; ?>
                                </div>
                            </div>
                        </div>
                    </div>

                    <!-- ⑤ FINAL DETAILS -->
                    <div class="tsa-rs-fieldset">
                        <div class="tsa-rs-fieldset-title">Final Details</div>
                        <div class="tsa-rs-field">
                            <label class="tsa-rs-label" for="rs_heard">How did you hear about Tee Shirt Ali?</label>
                            <select class="tsa-rs-select" id="rs_heard" name="rs_heard">
                                <option value="">— Select one —</option>
                                <option <?php selected( $_POST['rs_heard'] ?? '', 'Instagram' ); ?>>Instagram</option>
                                <option <?php selected( $_POST['rs_heard'] ?? '', 'Facebook' ); ?>>Facebook</option>
                                <option <?php selected( $_POST['rs_heard'] ?? '', 'TikTok' ); ?>>TikTok</option>
                                <option <?php selected( $_POST['rs_heard'] ?? '', 'Google / Search' ); ?>>Google / Search</option>
                                <option <?php selected( $_POST['rs_heard'] ?? '', 'Friend or Family' ); ?>>Friend or Family</option>
                                <option <?php selected( $_POST['rs_heard'] ?? '', 'School / Team Announcement' ); ?>>School / Team Announcement</option>
                                <option <?php selected( $_POST['rs_heard'] ?? '', 'Tee Party Event' ); ?>>Tee Party Event</option>
                                <option <?php selected( $_POST['rs_heard'] ?? '', 'Other' ); ?>>Other</option>
                            </select>
                        </div>
                        <div class="tsa-rs-field">
                            <label class="tsa-rs-label" for="rs_notes">Anything else we should know?</label>
                            <textarea class="tsa-rs-textarea" id="rs_notes" name="rs_notes" rows="4"
                                      placeholder="Tell us about your design ideas, specific products you want, your timeline, or anything else..."><?php echo esc_textarea( $_POST['rs_notes'] ?? '' ); ?></textarea>
                        </div>
                    </div>

                    <!-- ⑥ SUBMIT -->
                    <div class="tsa-rs-submit-row">
                        <button type="submit" class="tsa-btn tsa-btn-primary tsa-rs-submit-btn">
                            <?php echo esc_html( $t['btn'] ); ?>
                        </button>
                        <p class="tsa-rs-submit-note">No upfront cost. We'll reach out within 24 hours.</p>
                    </div>

                </form>
                <?php endif; ?>

            </div><!-- /form-col -->

            <!-- ── Sidebar ── -->
            <aside class="tsa-rs-sidebar">

                <div class="tsa-rs-sidebar-card">
                    <div class="tsa-rs-sidebar-heading">What happens next</div>
                    <ol class="tsa-rs-steps">
                        <li class="tsa-rs-step">
                            <span class="tsa-rs-step__num">1</span>
                            <div class="tsa-rs-step__body">
                                <strong>We review your request</strong>
                                <span>You'll hear from us within 24 hours — usually faster.</span>
                            </div>
                        </li>
                        <li class="tsa-rs-step">
                            <span class="tsa-rs-step__num">2</span>
                            <div class="tsa-rs-step__body">
                                <strong>We build your store</strong>
                                <span>Custom branded store with your colors, logo, and products. Ready in 3–5 business days.</span>
                            </div>
                        </li>
                        <li class="tsa-rs-step">
                            <span class="tsa-rs-step__num">3</span>
                            <div class="tsa-rs-step__body">
                                <strong>You share the link</strong>
                                <span>Send it to your group. Everyone shops on their own — no collecting money or counting sizes.</span>
                            </div>
                        </li>
                        <li class="tsa-rs-step">
                            <span class="tsa-rs-step__num">4</span>
                            <div class="tsa-rs-step__body">
                                <strong>Orders ship direct</strong>
                                <span>Every order ships to the customer. You earn store credit on every sale.</span>
                            </div>
                        </li>
                    </ol>
                </div>

                <div class="tsa-rs-sidebar-card tsa-rs-sidebar-card--faq">
                    <div class="tsa-rs-sidebar-heading">Quick Answers</div>
                    <dl class="tsa-rs-faq">
                        <dt>Is there a setup fee?</dt>
                        <dd>None. Free to set up, free to run.</dd>
                        <dt>Are there order minimums?</dt>
                        <dd>No minimums — one item or one thousand.</dd>
                        <dt>What products can we sell?</dt>
                        <dd>T-shirts, hoodies, hats, bags, polos, jackets, and more.</dd>
                        <dt>Can we use our own logo?</dt>
                        <dd>Yes — send us your logo and we'll brand everything to match.</dd>
                    </dl>
                </div>

            </aside><!-- /sidebar -->

        </div><!-- /body -->
    </div><!-- /container -->
</section>

<!-- ── Type-switch JS ── -->
<script>
(function () {
    var tabs     = document.querySelectorAll('.tsa-rs-type-tab');
    var sections = document.querySelectorAll('.tsa-rs-type-section');
    var btn      = document.querySelector('.tsa-rs-submit-btn');
    var kicker   = document.getElementById('tsa-rs-kicker');
    var titleEl  = document.getElementById('tsa-rs-title');
    var subEl    = document.getElementById('tsa-rs-sub');
    var orgLabel = document.getElementById('tsa-rs-org-label');

    var logoLabel = document.getElementById('tsa-rs-logo-label');

    var meta = {
        school:   { kicker: 'School Spirit Stores', title: 'Get Your School Store',   sub: 'Fill out the form and we\'ll build a dedicated online store for your school or program — no upfront cost, no minimums.', btn: 'Submit School Store Request',   org: 'School Name',   logo: 'School Logo' },
        team:     { kicker: 'Team Spirit Stores',   title: 'Get Your Team Store',     sub: 'Tell us about your team and we\'ll set up a custom store your whole squad can shop from — gear drops, custom jerseys, the works.', btn: 'Submit Team Store Request',     org: 'Team Name',     logo: 'Team Logo' },
        business: { kicker: 'Business Stores',      title: 'Get Your Business Store', sub: 'Custom branded merchandise for your staff, clients, and events. We\'ll build your company store and handle fulfillment.', btn: 'Submit Business Store Request', org: 'Business Name', logo: 'Business Logo' }
    };

    function switchType(val) {
        // Active tab highlight
        tabs.forEach(function (tab) {
            tab.classList.toggle('is-active', tab.querySelector('input').value === val);
        });
        // Show/hide type-specific sections
        sections.forEach(function (s) {
            s.style.display = s.dataset.type === val ? 'block' : 'none';
        });
        // Update hero copy
        var m = meta[val];
        if (!m) return;
        if (kicker)   kicker.textContent   = m.kicker;
        if (titleEl)  titleEl.textContent  = m.title;
        if (subEl)    subEl.textContent    = m.sub;
        if (btn)      btn.textContent      = m.btn;
        if (orgLabel)   orgLabel.textContent   = m.org;
        if (logoLabel)  logoLabel.textContent  = m.logo;
    }

    // Wire up radio change
    tabs.forEach(function (tab) {
        tab.querySelector('input').addEventListener('change', function () {
            switchType(this.value);
        });
    });

    // Init on load to match pre-selected type
    var active = document.querySelector('.tsa-rs-type-tab.is-active input');
    if (active) switchType(active.value);
})();

// ── Logo Upload Zone ─────────────────────────────────
(function () {
    var zone    = document.getElementById('tsa-rs-upload-zone');
    var input   = document.getElementById('rs_logo');
    var inner   = document.getElementById('tsa-rs-upload-inner');
    var preview = document.getElementById('tsa-rs-upload-preview');
    var pImg    = document.getElementById('tsa-rs-preview-img');
    var pName   = document.getElementById('tsa-rs-preview-name');
    var pSize   = document.getElementById('tsa-rs-preview-size');
    var removeBtn = document.getElementById('tsa-rs-upload-remove');

    if (!zone || !input) return;

    function fmtSize(bytes) {
        if (bytes < 1024)    return bytes + ' B';
        if (bytes < 1048576) return (bytes / 1024).toFixed(1) + ' KB';
        return (bytes / 1048576).toFixed(1) + ' MB';
    }

    function showPreview(file) {
        pName.textContent = file.name;
        pSize.textContent = fmtSize(file.size);
        var isRasterImg = file.type.startsWith('image/') && file.type !== 'image/svg+xml';
        if (isRasterImg) {
            var reader = new FileReader();
            reader.onload = function (e) {
                pImg.src = e.target.result;
                pImg.style.display = 'block';
            };
            reader.readAsDataURL(file);
        } else {
            pImg.style.display = 'none';
        }
        inner.style.display   = 'none';
        preview.style.display = 'flex';
        zone.classList.add('has-file');
    }

    function clearFile() {
        input.value           = '';
        pImg.src              = '';
        pImg.style.display    = 'none';
        pName.textContent     = '';
        pSize.textContent     = '';
        inner.style.display   = '';
        preview.style.display = 'none';
        zone.classList.remove('has-file', 'is-dragging');
    }

    function handleFile(file) {
        if (!file) return;
        if (file.size > 10 * 1024 * 1024) {
            alert('That file is over 10 MB. Please upload a smaller version of your logo.');
            input.value = '';
            return;
        }
        showPreview(file);
    }

    // Click anywhere in the zone to open file picker (when no file selected)
    zone.addEventListener('click', function (e) {
        if (!zone.classList.contains('has-file')) input.click();
    });

    // File chosen via the native picker
    input.addEventListener('change', function () {
        if (this.files && this.files[0]) handleFile(this.files[0]);
    });

    // Drag over
    zone.addEventListener('dragover', function (e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.add('is-dragging');
    });
    zone.addEventListener('dragleave', function (e) {
        e.stopPropagation();
        zone.classList.remove('is-dragging');
    });

    // Drop
    zone.addEventListener('drop', function (e) {
        e.preventDefault();
        e.stopPropagation();
        zone.classList.remove('is-dragging');
        var file = e.dataTransfer && e.dataTransfer.files[0];
        if (file) {
            // Assign dropped file to the input so it submits with the form
            try {
                var dt = new DataTransfer();
                dt.items.add(file);
                input.files = dt.files;
            } catch (ex) { /* Safari < 14 doesn't support DataTransfer constructor */ }
            handleFile(file);
        }
    });

    // Remove button
    if (removeBtn) {
        removeBtn.addEventListener('click', function (e) {
            e.stopPropagation();
            clearFile();
        });
    }
})();
</script>

<script>
/* Prefill the school dropdown from ?school= (used by the Design Library "Schools" cards). */
(function(){
    var school = (new URLSearchParams(location.search).get('school') || '').trim().toLowerCase();
    if (!school) return;
    var sel = document.getElementById('rs_school_name');
    if (!sel) return;
    for (var i = 0; i < sel.options.length; i++) {
        if (sel.options[i].text.trim().toLowerCase() === school) { sel.selectedIndex = i; break; }
    }
})();
</script>

<?php get_footer(); ?>
