<?php
/**
 * Template Name: TSA Fundraiser Request
 *
 * Standalone fundraiser intake form. Self-POSTs, emails the site admin
 * (with optional logo attachment), shows a success state.
 * Styling reuses the shared .tsa-ip-* form components (tsa-info-pages.css).
 * Assign to: /start-a-fundraiser/
 */

defined( 'ABSPATH' ) || exit;

/* ── Submission handler ─────────────────────────────────────────────── */
$submitted    = false;
$submit_error = '';

if ( 'POST' === $_SERVER['REQUEST_METHOD'] && isset( $_POST['tsa_fr_nonce'] ) ) {

    if ( ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_fr_nonce'] ) ), 'tsa_fundraiser_request' ) ) {
        $submit_error = 'Security check failed. Please refresh and try again.';
    } elseif ( ! empty( $_POST['tsa_hp'] ) ) {
        $submitted = true; // honeypot — silently swallow bots
    } else {
        $name       = sanitize_text_field(     wp_unslash( $_POST['fr_name']       ?? '' ) );
        $email      = sanitize_email(          wp_unslash( $_POST['fr_email']      ?? '' ) );
        $phone      = sanitize_text_field(     wp_unslash( $_POST['fr_phone']      ?? '' ) );
        $org        = sanitize_text_field(     wp_unslash( $_POST['fr_org']        ?? '' ) );
        $cause      = sanitize_text_field(     wp_unslash( $_POST['fr_cause']      ?? '' ) );
        $goal       = sanitize_text_field(     wp_unslash( $_POST['fr_goal']       ?? '' ) );
        $start      = sanitize_text_field(     wp_unslash( $_POST['fr_start']      ?? '' ) );
        $end        = sanitize_text_field(     wp_unslash( $_POST['fr_end']        ?? '' ) );
        $supporters = sanitize_text_field(     wp_unslash( $_POST['fr_supporters'] ?? '' ) );
        $heard      = sanitize_text_field(     wp_unslash( $_POST['fr_heard']      ?? '' ) );
        $notes      = sanitize_textarea_field( wp_unslash( $_POST['fr_notes']      ?? '' ) );

        // Optional logo / artwork upload
        require_once ABSPATH . 'wp-admin/includes/file.php';
        $logo_url = ''; $logo_path = '';
        if ( isset( $_FILES['fr_logo'] ) && UPLOAD_ERR_OK === (int) $_FILES['fr_logo']['error']
             && (int) $_FILES['fr_logo']['size'] <= 10 * 1024 * 1024 ) {
            $up = wp_handle_upload( $_FILES['fr_logo'], [
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
            $body  = "NEW TSA FUNDRAISER REQUEST\n" . str_repeat( '=', 42 ) . "\n\n";
            $body .= "── CONTACT ──────────────────────────────\n";
            $body .= "Name:          {$name}\n";
            $body .= "Email:         {$email}\n";
            $body .= "Phone:         " . ( $phone ?: '—' ) . "\n\n";
            $body .= "── FUNDRAISER ───────────────────────────\n";
            $body .= "Group / Org:   {$org}\n";
            $body .= "Raising for:   " . ( $cause ?: '—' ) . "\n";
            $body .= "Goal:          " . ( $goal ?: '—' ) . "\n";
            $body .= "Est. Support:  " . ( $supporters ?: '—' ) . "\n";
            $body .= "Campaign:      " . ( $start ?: '?' ) . "  →  " . ( $end ?: '?' ) . "\n\n";
            $body .= "── HOW THEY HEARD ───────────────────────\n" . ( $heard ?: '—' ) . "\n\n";
            $body .= "── NOTES ────────────────────────────────\n" . ( $notes ?: '(none)' ) . "\n";
            if ( $logo_url ) $body .= "\n── LOGO / ARTWORK ───────────────────────\n{$logo_url}\n";

            $headers = [ 'Content-Type: text/plain; charset=UTF-8', "Reply-To: {$name} <{$email}>" ];
            $to      = function_exists( 'tsa_form_recipient' ) ? tsa_form_recipient( 'fundraiser' ) : get_option( 'admin_email' );
            wp_mail( $to, "[TSA] New Fundraiser Request — {$org}", $body, $headers, $logo_path ? [ $logo_path ] : [] );
            if ( function_exists( 'tsa_record_request' ) ) {
                tsa_record_request( [
                    'type'    => 'fundraiser',
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
        <div class="tsa-kicker tsa-kicker--pink">Zero-Risk Fundraising</div>
        <h1>Start Your Fundraiser</h1>
        <p class="tsa-ip-hero__sub">Tell us about your group and goal. We'll set up your online store — no upfront cost, no inventory — and your group keeps the profit. We respond within one business day.</p>
    </div>
</section>

<div class="tsa-ip-wrap tsa-ip-wrap--narrow">

    <?php if ( $submitted ) : ?>
        <div class="tsa-ip-formcard" style="text-align:center">
            <div style="font-size:46px;color:var(--status-success);line-height:1">✓</div>
            <h2 style="margin:10px 0 6px">Your fundraiser request is in!</h2>
            <p style="color:var(--text-muted);margin:0 0 22px">Thanks — we'll review it and reach out within one business day to get your store set up.</p>
            <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap">
                <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="tsa-btn tsa-btn-primary">Back to Home</a>
                <a href="<?php echo esc_url( home_url( '/fundraisers/' ) ); ?>" class="tsa-btn tsa-btn-outline">How Fundraisers Work</a>
            </div>
        </div>
    <?php else : ?>

        <div class="tsa-ip-formcard">
            <?php if ( $submit_error ) : ?>
                <div class="tsa-ip-note" style="border-left-color:var(--status-error);margin:0 0 20px"><strong><?php echo esc_html( $submit_error ); ?></strong></div>
            <?php endif; ?>

            <form action="<?php echo esc_url( get_permalink() ); ?>" method="post" enctype="multipart/form-data">
                <?php wp_nonce_field( 'tsa_fundraiser_request', 'tsa_fr_nonce' ); ?>
                <div style="position:absolute;left:-9999px" aria-hidden="true">
                    <label>Leave empty <input type="text" name="tsa_hp" tabindex="-1" autocomplete="off"></label>
                </div>

                <div class="tsa-ip-row">
                    <div class="tsa-ip-field">
                        <label for="fr_name">Your Name *</label>
                        <input class="tsa-ip-input tsa-rs-input" type="text" id="fr_name" name="fr_name" required value="<?php echo esc_attr( $_POST['fr_name'] ?? '' ); ?>">
                    </div>
                    <div class="tsa-ip-field">
                        <label for="fr_email">Email *</label>
                        <input class="tsa-ip-input tsa-rs-input" type="email" id="fr_email" name="fr_email" required value="<?php echo esc_attr( $_POST['fr_email'] ?? '' ); ?>">
                    </div>
                </div>
                <div class="tsa-ip-row">
                    <div class="tsa-ip-field">
                        <label for="fr_phone">Phone <span style="opacity:.6;text-transform:none">(optional)</span></label>
                        <input class="tsa-ip-input tsa-rs-input" type="tel" id="fr_phone" name="fr_phone" value="<?php echo esc_attr( $_POST['fr_phone'] ?? '' ); ?>">
                    </div>
                    <div class="tsa-ip-field">
                        <label for="fr_org">Group / Organization *</label>
                        <input class="tsa-ip-input tsa-rs-input" type="text" id="fr_org" name="fr_org" placeholder="e.g. Dutchtown Band Boosters" required value="<?php echo esc_attr( $_POST['fr_org'] ?? '' ); ?>">
                    </div>
                </div>
                <div class="tsa-ip-field">
                    <label for="fr_cause">What are you raising money for?</label>
                    <input class="tsa-ip-input tsa-rs-input" type="text" id="fr_cause" name="fr_cause" placeholder="e.g. new uniforms, travel costs, equipment" value="<?php echo esc_attr( $_POST['fr_cause'] ?? '' ); ?>">
                </div>
                <div class="tsa-ip-row">
                    <div class="tsa-ip-field">
                        <label for="fr_goal">Fundraising Goal <span style="opacity:.6;text-transform:none">(optional)</span></label>
                        <input class="tsa-ip-input tsa-rs-input" type="text" id="fr_goal" name="fr_goal" placeholder="$5,000" value="<?php echo esc_attr( $_POST['fr_goal'] ?? '' ); ?>">
                    </div>
                    <div class="tsa-ip-field">
                        <label for="fr_supporters">Estimated Supporters</label>
                        <input class="tsa-ip-input tsa-rs-input" type="text" id="fr_supporters" name="fr_supporters" placeholder="e.g. 150" value="<?php echo esc_attr( $_POST['fr_supporters'] ?? '' ); ?>">
                    </div>
                </div>
                <div class="tsa-ip-row">
                    <div class="tsa-ip-field">
                        <label for="fr_start">Ideal Start Date</label>
                        <input class="tsa-ip-input tsa-rs-input" type="date" id="fr_start" name="fr_start" value="<?php echo esc_attr( $_POST['fr_start'] ?? '' ); ?>">
                    </div>
                    <div class="tsa-ip-field">
                        <label for="fr_end">Ideal End Date</label>
                        <input class="tsa-ip-input tsa-rs-input" type="date" id="fr_end" name="fr_end" value="<?php echo esc_attr( $_POST['fr_end'] ?? '' ); ?>">
                    </div>
                </div>
                <div class="tsa-ip-field">
                    <label for="fr_heard">How did you hear about us?</label>
                    <select class="tsa-ip-select tsa-rs-select" id="fr_heard" name="fr_heard">
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
                    <label for="fr_notes">Tell us about your fundraiser</label>
                    <textarea class="tsa-ip-textarea tsa-rs-textarea" id="fr_notes" name="fr_notes" placeholder="Design ideas, themes, deadlines, anything else we should know…"><?php echo esc_textarea( $_POST['fr_notes'] ?? '' ); ?></textarea>
                </div>
                <div class="tsa-ip-field">
                    <label for="fr_logo">Logo or artwork <span style="opacity:.6;text-transform:none">(optional — JPG, PNG, SVG, PDF, EPS)</span></label>
                    <input type="file" id="fr_logo" name="fr_logo" accept=".jpg,.jpeg,.png,.gif,.svg,.pdf,.eps" style="font:inherit;color:var(--text-muted)">
                </div>

                <button type="submit" class="tsa-btn tsa-btn-primary tsa-btn-block">Submit Fundraiser Request</button>
            </form>
        </div>

        <p class="tsa-ip-note">Prefer to talk it through first? Reach us on the <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>" style="color:var(--brand-rose)">contact page</a> or learn more about <a href="<?php echo esc_url( home_url( '/fundraisers/' ) ); ?>" style="color:var(--brand-rose)">how fundraisers work</a>.</p>

    <?php endif; ?>

</div>

<?php get_footer(); ?>
