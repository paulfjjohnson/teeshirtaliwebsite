<?php
/**
 * TSA Form Email Routing
 *
 * Central recipient config for all front-end forms. Admins set where each
 * form's submissions go at Settings → TSA Form Emails. Each form accepts one
 * or more addresses (comma-separated). Blank → use Default; Default blank →
 * the WordPress admin email.
 *
 * Included via functions.php.
 */

defined( 'ABSPATH' ) || exit;

/** Forms registry: option key => human label. */
function tsa_form_email_forms() {
    return [
        'default'    => 'Default (fallback for all forms)',
        'quote'      => 'Request a Quote',
        'contact'    => 'Contact form',
        'teeparty'   => 'Tee Party requests',
        'fundraiser' => 'Fundraiser requests',
        'store'      => 'Store requests (school / team / business)',
        'design'     => 'New design upload notifications',
    ];
}

/**
 * Resolve recipient(s) for a form key.
 * Returns a string or array of emails suitable for wp_mail().
 * Chain: form-specific → Default → WordPress admin email.
 */
function tsa_form_recipient( $key = 'default' ) {
    $opts = get_option( 'tsa_form_emails', [] );

    $raw = isset( $opts[ $key ] ) ? trim( (string) $opts[ $key ] ) : '';
    if ( '' === $raw && 'default' !== $key ) {
        $raw = isset( $opts['default'] ) ? trim( (string) $opts['default'] ) : '';
    }
    if ( '' === $raw ) {
        return get_option( 'admin_email' );
    }

    $emails = array_values( array_filter( array_map( 'trim', explode( ',', $raw ) ), 'is_email' ) );
    return ! empty( $emails ) ? $emails : get_option( 'admin_email' );
}

/* ── Admin settings page (Settings → TSA Form Emails) ─────────────── */
add_action( 'admin_menu', function () {
    add_options_page(
        'TSA Form Emails',
        'TSA Form Emails',
        'manage_options',
        'tsa-form-emails',
        'tsa_form_emails_page'
    );
} );

function tsa_form_emails_page() {
    if ( ! current_user_can( 'manage_options' ) ) return;

    if ( isset( $_POST['tsa_form_emails_nonce'] )
         && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_form_emails_nonce'] ) ), 'tsa_form_emails_save' ) ) {
        $new = [];
        foreach ( tsa_form_email_forms() as $key => $label ) {
            $new[ $key ] = sanitize_text_field( wp_unslash( $_POST[ 'tsa_fe_' . $key ] ?? '' ) );
        }
        update_option( 'tsa_form_emails', $new );
        echo '<div class="notice notice-success is-dismissible"><p>Form email recipients saved.</p></div>';
    }

    $opts  = get_option( 'tsa_form_emails', [] );
    $admin = get_option( 'admin_email' );
    ?>
    <div class="wrap">
        <h1>TSA Form Emails</h1>
        <p>Choose where each form's submissions are sent. Enter one or more email addresses (comma-separated). Leave a field blank to use the <strong>Default</strong>; if Default is blank, the WordPress admin email (<code><?php echo esc_html( $admin ); ?></code>) is used. The customer's address is always set as <em>Reply-To</em>.</p>
        <form method="post">
            <?php wp_nonce_field( 'tsa_form_emails_save', 'tsa_form_emails_nonce' ); ?>
            <table class="form-table" role="presentation"><tbody>
                <?php foreach ( tsa_form_email_forms() as $key => $label ) :
                    $val = isset( $opts[ $key ] ) ? $opts[ $key ] : ''; ?>
                <tr>
                    <th scope="row"><label for="tsa_fe_<?php echo esc_attr( $key ); ?>"><?php echo esc_html( $label ); ?></label></th>
                    <td>
                        <input type="text" class="regular-text" id="tsa_fe_<?php echo esc_attr( $key ); ?>"
                               name="tsa_fe_<?php echo esc_attr( $key ); ?>" value="<?php echo esc_attr( $val ); ?>"
                               placeholder="<?php echo 'default' === $key ? esc_attr( $admin ) : 'orders@teeshirtali.com'; ?>">
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody></table>
            <?php submit_button( 'Save Recipients' ); ?>
        </form>
    </div>
    <?php
}
