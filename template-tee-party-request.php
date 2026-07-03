<?php
/**
 * Template Name: TSA Tee Party Request
 *
 * Standalone "Request a Tee Party" intake form. Self-POSTs, emails the routed
 * recipient (Settings → TSA Form Emails → "Tee Party requests"), shows a
 * success state. Styling reuses the shared .tsa-ip-* components.
 * Assign to: /request-a-tee-party/
 */

defined( 'ABSPATH' ) || exit;

/* ── Submission handler ─────────────────────────────────────────────── */
$submitted    = false;
$submit_error = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['tsa_tp_nonce'] ) ) {

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_tp_nonce'] ) ), 'tsa_teeparty_request' ) ) {
        $submit_error = 'Security check failed. Please refresh and try again.';
    } elseif ( ! empty( $_POST['tsa_hp'] ) ) {
        $submitted = true; // honeypot — silently swallow bots
    } else {
        $name      = sanitize_text_field(     wp_unslash( $_POST['tp_name']       ?? '' ) );
        $email     = sanitize_email(          wp_unslash( $_POST['tp_email']      ?? '' ) );
        $phone     = sanitize_text_field(     wp_unslash( $_POST['tp_phone']      ?? '' ) );
        $org       = sanitize_text_field(     wp_unslash( $_POST['tp_org']        ?? '' ) );
        $type      = sanitize_text_field(     wp_unslash( $_POST['tp_type']       ?? '' ) );
        $occasion  = sanitize_text_field(     wp_unslash( $_POST['tp_occasion']   ?? '' ) );
        $audience  = sanitize_text_field(     wp_unslash( $_POST['tp_audience']   ?? '' ) );
        $designs   = sanitize_text_field(     wp_unslash( $_POST['tp_designs']    ?? '' ) );
        $launch    = sanitize_text_field(     wp_unslash( $_POST['tp_launch']     ?? '' ) );
        $fundraise = sanitize_text_field(     wp_unslash( $_POST['tp_fundraise']  ?? '' ) );
        $apparel   = isset( $_POST['tp_apparel'] ) ? array_map( 'sanitize_text_field', (array) wp_unslash( $_POST['tp_apparel'] ) ) : [];
        $heard     = sanitize_text_field(     wp_unslash( $_POST['tp_heard']      ?? '' ) );
        $notes     = sanitize_textarea_field( wp_unslash( $_POST['tp_notes']      ?? '' ) );

        // Optional logo / artwork upload
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $logo_url = ''; $logo_path = '';
        if ( isset( $_FILES['tp_logo'] ) && UPLOAD_ERR_OK === (int) $_FILES['tp_logo']['error']
             && (int) $_FILES['tp_logo']['size'] <= 10 * 1024 * 1024 ) {
            $up = wp_handle_upload( $_FILES['tp_logo'], [
                'test_form' => false,
                'mimes'     => [
                    'jpg|jpeg|jpe' => 'image/jpeg',
                    'png'          => 'image/png',
                    'gif'          => 'image/gif',
                    'svg'          => 'image/svg+xml',
                    'pdf'          => 'application/pdf',
                    'eps'          => 'application/postscript',
                ],
            ] );
            if ( $up && ! isset( $up['error'] ) ) { $logo_url = $up['url']; $logo_path = $up['file']; }
        }

        if ( ! $name || ! is_email( $email ) || ! $org ) {
            $submit_error = 'Please fill in all required fields (Name, Email, and Group / Organization).';
        } else {
            $body  = "NEW TEE PARTY REQUEST\n" . str_repeat( '=', 42 ) . "\n\n";
            $body .= "── CONTACT ──────────────────────────────\n";
            $body .= "Name:          {$name}\n";
            $body .= "Email:         {$email}\n";
            $body .= "Phone:         " . ( $phone ?: '—' ) . "\n\n";
            $body .= "── GROUP ────────────────────────────────\n";
            $body .= "Group / Org:   {$org}\n";
            $body .= "Group type:    " . ( $type ?: '—' ) . "\n";
            $body .= "Audience size: " . ( $audience ?: '—' ) . "\n\n";
            $body .= "── THE TEE PARTY ────────────────────────\n";
            $body .= "What it's for: " . ( $occasion ?: '—' ) . "\n";
            $body .= "# of designs:  " . ( $designs ?: '—' ) . "\n";
            $body .= "Ideal launch:  " . ( $launch ?: '—' ) . "\n";
            $body .= "Fundraiser?:   " . ( $fundraise ?: '—' ) . "\n";
            $body .= "Apparel:       " . ( $apparel ? implode( ', ', $apparel ) : '—' ) . "\n\n";
            $body .= "── HOW THEY HEARD ───────────────────────\n" . ( $heard ?: '—' ) . "\n\n";
            $body .= "── NOTES ────────────────────────────────\n" . ( $notes ?: '(none)' ) . "\n";
            if ( $logo_url ) $body .= "\n── LOGO / ARTWORK ───────────────────────\n{$logo_url}\n";

            $headers = [ 'Content-Type: text/plain; charset=UTF-8', "Reply-To: {$name} <{$email}>" ];
            $to      = function_exists( 'tsa_form_recipient' ) ? tsa_form_recipient( 'teeparty' ) : get_option( 'admin_email' );
            wp_mail( $to, "[TSA] New Tee Party Request — {$org}", $body, $headers, $logo_path ? [ $logo_path ] : [] );
            if ( function_exists( 'tsa_record_request' ) ) {
                tsa_record_request( [
                    'type'    => 'teeparty',
                    'name'    => $name,
                    'email'   => $email,
                    'phone'   => $phone,
                    'org'     => $org,
                    'message' => $body,
                ] );
            }
            $submitted = true;
        }
    }
}

get_header();
?>

<section class="tsa-ip-hero">
    <div class="tsa-ip-hero__inner">
        <div class="tsa-kicker tsa-kicker--pink">Limited-Time Design Drops</div>
        <h1>Request a Tee Party</h1>
        <p class="tsa-ip-hero__sub">Host your own design showcase — we reveal exclusive designs on a schedule, your people order online during the window, and we print and ship. No upfront cost, no inventory. Tell us about your group and we'll respond within one business day.</p>
    </div>
</section>

<div class="tsa-ip-wrap tsa-ip-wrap--narrow">

    <?php if ( $submitted ) : ?>
        <div class="tsa-ip-formcard" style="text-align:center">
            <div style="font-size:46px;color:var(--status-success);line-height:1">✓</div>
            <h2 style="margin:10px 0 6px">Your Tee Party request is in!</h2>
            <p style="color:var(--text-muted);margin:0 0 22px">Thanks — we'll review the details and reach out within one business day to plan your showcase.</p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="tsa-btn tsa-btn-primary">Back to Home</a>
                <a href="<?php echo esc_url( home_url( '/tee-party/' ) ); ?>" class="tsa-btn tsa-btn-outline">See Live Tee Parties</a>
            </div>
        </div>
    <?php else : ?>

        <div class="tsa-ip-formcard">
            <?php if ( $submit_error ) : ?>
                <div class="tsa-ip-note" style="border-left-color:var(--status-error);margin:0 0 20px"><strong><?php echo esc_html( $submit_error ); ?></strong></div>
            <?php endif; ?>

            <form action="<?php echo esc_url( get_permalink() ); ?>" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'tsa_teeparty_request', 'tsa_tp_nonce' ); ?>
                <div style="position:absolute;left:-9999px" aria-hidden="true">
                    <label>Leave empty <input type="text" name="tsa_hp" tabindex="-1" autocomplete="off"></label>
                </div>

                <!-- Contact -->
                <div class="tsa-ip-row">
                    <div class="tsa-ip-field">
                        <label for="tp_name">Your Name *</label>
                        <input class="tsa-ip-input" type="text" id="tp_name" name="tp_name" required value="<?php echo esc_attr( $_POST['tp_name'] ?? '' ); ?>">
                    </div>
                    <div class="tsa-ip-field">
                        <label for="tp_email">Email *</label>
                        <input class="tsa-ip-input" type="email" id="tp_email" name="tp_email" required value="<?php echo esc_attr( $_POST['tp_email'] ?? '' ); ?>">
                    </div>
                </div>
                <div class="tsa-ip-row">
                    <div class="tsa-ip-field">
                        <label for="tp_phone">Phone <span style="opacity:.6;text-transform:none">(optional)</span></label>
                        <input class="tsa-ip-input" type="tel" id="tp_phone" name="tp_phone" value="<?php echo esc_attr( $_POST['tp_phone'] ?? '' ); ?>">
                    </div>
                    <div class="tsa-ip-field">
                        <label for="tp_org">Group / Organization *</label>
                        <input class="tsa-ip-input" type="text" id="tp_org" name="tp_org" placeholder="e.g. Dutchtown High Color Guard" required value="<?php echo esc_attr( $_POST['tp_org'] ?? '' ); ?>">
                    </div>
                </div>

                <!-- About the group -->
                <div class="tsa-ip-row">
                    <div class="tsa-ip-field">
                        <label for="tp_type">Group Type</label>
                        <select class="tsa-ip-select" id="tp_type" name="tp_type">
                            <option value="">— Select —</option>
                            <option>School / PTO</option>
                            <option>Sports Team</option>
                            <option>Band / Color Guard / Arts</option>
                            <option>Church / Ministry</option>
                            <option>Business / Company</option>
                            <option>Club / Group</option>
                            <option>Other</option>
                        </select>
                    </div>
                    <div class="tsa-ip-field">
                        <label for="tp_audience">How many people will you promote to?</label>
                        <select class="tsa-ip-select" id="tp_audience" name="tp_audience">
                            <option value="">— Select —</option>
                            <option>Under 50</option>
                            <option>50–150</option>
                            <option>150–400</option>
                            <option>400–1,000</option>
                            <option>1,000+</option>
                            <option>Not sure yet</option>
                        </select>
                    </div>
                </div>

                <!-- About the party -->
                <div class="tsa-ip-field">
                    <label for="tp_occasion">What's the Tee Party for?</label>
                    <input class="tsa-ip-input" type="text" id="tp_occasion" name="tp_occasion" placeholder="e.g. spirit week, holiday drop, season kickoff, fundraiser" value="<?php echo esc_attr( $_POST['tp_occasion'] ?? '' ); ?>">
                </div>
                <div class="tsa-ip-row">
                    <div class="tsa-ip-field">
                        <label for="tp_designs">How many designs are you thinking?</label>
                        <select class="tsa-ip-select" id="tp_designs" name="tp_designs">
                            <option value="">— Select —</option>
                            <option>1–2</option>
                            <option>3–5</option>
                            <option>6–10</option>
                            <option>10+</option>
                            <option>Not sure — need ideas</option>
                        </select>
                    </div>
                    <div class="tsa-ip-field">
                        <label for="tp_launch">When would you like it live?</label>
                        <input class="tsa-ip-input" type="date" id="tp_launch" name="tp_launch" value="<?php echo esc_attr( $_POST['tp_launch'] ?? '' ); ?>">
                    </div>
                </div>
                <div class="tsa-ip-field">
                    <label for="tp_fundraise">Is this also a fundraiser?</label>
                    <select class="tsa-ip-select" id="tp_fundraise" name="tp_fundraise">
                        <option value="">— Select —</option>
                        <option>Yes — we want to raise money</option>
                        <option>No — just gear for our group</option>
                        <option>Maybe — tell me how it works</option>
                    </select>
                </div>

                <!-- Apparel interest -->
                <div class="tsa-ip-field">
                    <label>Apparel you're interested in <span style="opacity:.6;text-transform:none">(optional — check any)</span></label>
                    <div style="display:flex;flex-wrap:wrap;gap:10px 18px;margin-top:6px">
                        <?php foreach ( [ 'T-shirts', 'Long sleeve', 'Hoodies / crewnecks', 'Hats', 'Other / not sure' ] as $opt ) : ?>
                        <label style="display:flex;align-items:center;gap:7px;font-weight:500;text-transform:none;letter-spacing:0">
                            <input type="checkbox" name="tp_apparel[]" value="<?php echo esc_attr( $opt ); ?>"> <?php echo esc_html( $opt ); ?>
                        </label>
                        <?php endforeach; ?>
                    </div>
                </div>

                <div class="tsa-ip-field">
                    <label for="tp_heard">How did you hear about us?</label>
                    <select class="tsa-ip-select" id="tp_heard" name="tp_heard">
                        <option value="">— Select —</option>
                        <option>Word of mouth / referral</option>
                        <option>Social media</option>
                        <option>Search (Google)</option>
                        <option>Another school / team</option>
                        <option>Saw a Tee Shirt Ali store</option>
                        <option>Other</option>
                    </select>
                </div>
                <div class="tsa-ip-field">
                    <label for="tp_notes">Tell us about your Tee Party</label>
                    <textarea class="tsa-ip-textarea" id="tp_notes" name="tp_notes" placeholder="Design ideas, colors, theme, key dates, anything else we should know…"><?php echo esc_textarea( $_POST['tp_notes'] ?? '' ); ?></textarea>
                </div>
                <div class="tsa-ip-field">
                    <label for="tp_logo">Logo or artwork <span style="opacity:.6;text-transform:none">(optional — JPG, PNG, SVG, PDF, EPS)</span></label>
                    <input type="file" id="tp_logo" name="tp_logo" accept=".jpg,.jpeg,.png,.gif,.svg,.pdf,.eps" style="font:inherit;color:var(--text-muted)">
                </div>

                <button type="submit" class="tsa-btn tsa-btn-primary tsa-btn-block">Submit Tee Party Request</button>
            </form>
        </div>

        <p class="tsa-ip-note">Prefer to talk it through first? Reach us on the <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" style="color:var(--brand-rose)">contact page</a>, or <a href="<?php echo esc_url( home_url( '/tee-party/' ) ); ?>" style="color:var(--brand-rose)">see how Tee Parties work</a>.</p>

    <?php endif; ?>

</div>

<?php get_footer(); ?>
