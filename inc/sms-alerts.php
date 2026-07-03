<?php
/**
 * TSA SMS Alerts — text the owner when something needs attention.
 *
 * Provider-agnostic: ships with a built-in Twilio sender, but any provider can
 * be plugged in via the `tsa_sms_send` filter (return true/false to take over).
 *
 * Settings: Settings → TSA Alerts.
 * Triggers: new requests (per type) + optionally new WooCommerce orders.
 */
defined( 'ABSPATH' ) || exit;

function tsa_sms_settings() {
	return wp_parse_args( get_option( 'tsa_sms_settings', [] ), [
		'enabled'      => 0,
		'provider'     => 'twilio',
		'twilio_sid'   => '',
		'twilio_token' => '',
		'from'         => '',
		'to'           => '',
		'events'       => [],
	] );
}

/* ── Send a text. "To" may be one OR several comma-separated numbers.
 *    Returns true if at least one recipient succeeded. ─────────────── */
function tsa_send_sms( $message, $to = '' ) {
	$s  = tsa_sms_settings();
	$to = $to ?: $s['to'];
	if ( empty( $s['enabled'] ) || ! $to || ! $message ) { return false; }

	$numbers = array_filter( array_map( 'trim', explode( ',', $to ) ) );
	if ( ! $numbers ) { return false; }

	$any = false;
	foreach ( $numbers as $num ) {
		$any = tsa_send_sms_single( $message, $num, $s ) || $any;
	}
	return $any;
}

/* Send to a single number via the configured provider. */
function tsa_send_sms_single( $message, $to, $s ) {
	// Provider abstraction: a plugin/filter may handle the send entirely.
	$handled = apply_filters( 'tsa_sms_send', null, $message, $to, $s );
	if ( null !== $handled ) { return (bool) $handled; }

	// Built-in default: Twilio.
	if ( 'twilio' !== $s['provider'] ) { return false; }
	if ( ! $s['twilio_sid'] || ! $s['twilio_token'] || ! $s['from'] ) { return false; }

	$resp = wp_remote_post(
		'https://api.twilio.com/2010-04-01/Accounts/' . rawurlencode( $s['twilio_sid'] ) . '/Messages.json',
		[
			'timeout' => 15,
			'headers' => [ 'Authorization' => 'Basic ' . base64_encode( $s['twilio_sid'] . ':' . $s['twilio_token'] ) ],
			'body'    => [ 'From' => $s['from'], 'To' => $to, 'Body' => $message ],
		]
	);
	if ( is_wp_error( $resp ) ) {
		error_log( 'TSA SMS error: ' . $resp->get_error_message() );
		return false;
	}
	$code = (int) wp_remote_retrieve_response_code( $resp );
	if ( $code < 200 || $code >= 300 ) {
		error_log( 'TSA SMS HTTP ' . $code . ': ' . wp_remote_retrieve_body( $resp ) );
		return false;
	}
	return true;
}

/* ── Trigger: a new request was recorded ─────────────────────────── */
add_action( 'tsa_request_recorded', function ( $post_id, $args ) {
	$s = tsa_sms_settings();
	if ( empty( $s['enabled'] ) ) { return; }
	if ( ! in_array( $args['type'], (array) $s['events'], true ) ) { return; }

	$labels = function_exists( 'tsa_request_type_labels' ) ? tsa_request_type_labels() : [];
	$tlab   = isset( $labels[ $args['type'] ] ) ? $labels[ $args['type'] ] : ucfirst( (string) $args['type'] );
	$who    = $args['name'] ?: ( $args['email'] ?: 'someone' );

	$msg = sprintf(
		"New %s request from %s%s.\n%s",
		$tlab,
		$who,
		$args['phone'] ? ' (' . $args['phone'] . ')' : '',
		admin_url( 'post.php?post=' . $post_id . '&action=edit' )
	);
	tsa_send_sms( $msg );
}, 10, 2 );

/* Which store an order belongs to (one store per cart, so first hit wins). */
function tsa_order_store_name( $order ) {
	if ( ! $order || ! function_exists( 'tsa_product_store_key' ) ) { return ''; }
	foreach ( $order->get_items() as $item ) {
		$key = tsa_product_store_key( (int) $item->get_product_id() );
		if ( '' === $key ) {
			// Configurator/store items may carry the slug in line-item meta.
			foreach ( [ 'store_slug', '_ac_store_slug', 'Store' ] as $mk ) {
				$v = $item->get_meta( $mk );
				if ( $v ) { $key = sanitize_title( is_array( $v ) ? reset( $v ) : $v ); break; }
			}
		}
		if ( '' !== $key ) {
			return function_exists( 'tsa_store_name' ) ? ( tsa_store_name( $key ) ?: $key ) : $key;
		}
	}
	return ''; // no store match → general / main shop
}

/* ── Trigger: a new WooCommerce order (all orders, names the store) ─ */
add_action( 'woocommerce_checkout_order_processed', function ( $order_id ) {
	$s = tsa_sms_settings();
	if ( empty( $s['enabled'] ) || ! in_array( 'order', (array) $s['events'], true ) ) { return; }
	$order = wc_get_order( $order_id );
	if ( ! $order ) { return; }
	$store = tsa_order_store_name( $order );
	$msg = sprintf(
		"New order #%s · %s — %s%s.\n%s",
		$order->get_order_number(),
		$store ?: 'Main shop',
		html_entity_decode( wp_strip_all_tags( $order->get_formatted_order_total() ) ),
		$order->get_billing_first_name() ? ' from ' . $order->get_billing_first_name() : '',
		admin_url( 'post.php?post=' . $order_id . '&action=edit' )
	);
	tsa_send_sms( $msg );
} );

/* ── Settings page ───────────────────────────────────────────────── */
add_action( 'admin_menu', function () {
	add_options_page( 'TSA Alerts', 'TSA Alerts', 'manage_options', 'tsa-alerts', 'tsa_sms_settings_page' );
} );

function tsa_sms_settings_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	// Save
	if ( isset( $_POST['tsa_sms_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_sms_nonce'] ) ), 'tsa_sms_save' ) ) {
		$events = isset( $_POST['tsa_sms_events'] ) && is_array( $_POST['tsa_sms_events'] )
			? array_map( 'sanitize_key', wp_unslash( $_POST['tsa_sms_events'] ) ) : [];
		update_option( 'tsa_sms_settings', [
			'enabled'      => empty( $_POST['tsa_sms_enabled'] ) ? 0 : 1,
			'provider'     => sanitize_key( wp_unslash( $_POST['tsa_sms_provider'] ?? 'twilio' ) ),
			'twilio_sid'   => sanitize_text_field( wp_unslash( $_POST['tsa_sms_sid'] ?? '' ) ),
			'twilio_token' => sanitize_text_field( wp_unslash( $_POST['tsa_sms_token'] ?? '' ) ),
			'from'         => sanitize_text_field( wp_unslash( $_POST['tsa_sms_from'] ?? '' ) ),
			'to'           => sanitize_text_field( wp_unslash( $_POST['tsa_sms_to'] ?? '' ) ),
			'events'       => $events,
		] );
		echo '<div class="notice notice-success is-dismissible"><p>Alert settings saved.</p></div>';
	}

	// Send test
	if ( isset( $_POST['tsa_sms_test_nonce'] ) && wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_sms_test_nonce'] ) ), 'tsa_sms_test' ) ) {
		$ok = tsa_send_sms( 'TSA test alert — your SMS alerts are working. ✅' );
		echo '<div class="notice notice-' . ( $ok ? 'success' : 'error' ) . ' is-dismissible"><p>'
			. ( $ok ? 'Test text sent.' : 'Test failed — check your credentials, From/To numbers (E.164 like +15551234567), and that alerts are enabled.' )
			. '</p></div>';
	}

	$s          = tsa_sms_settings();
	$event_opts = [
		'contact'    => 'Contact form',
		'quote'      => 'Quote request',
		'store'      => 'Store request',
		'teeparty'   => 'Tee Party request',
		'fundraiser' => 'Fundraiser request',
		'design'     => 'Design customization request',
		'order'      => 'New WooCommerce order',
	];
	?>
	<div class="wrap">
		<h1>TSA Alerts (SMS)</h1>
		<p>Get a text when something needs your attention. Numbers must be in <strong>E.164</strong> format, e.g. <code>+15551234567</code>.</p>

		<form method="post">
			<?php wp_nonce_field( 'tsa_sms_save', 'tsa_sms_nonce' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row">Enable SMS alerts</th>
					<td><label><input type="checkbox" name="tsa_sms_enabled" value="1" <?php checked( $s['enabled'], 1 ); ?>> Send texts for the events below</label></td>
				</tr>
				<tr>
					<th scope="row"><label for="tsa_sms_provider">Provider</label></th>
					<td>
						<select name="tsa_sms_provider" id="tsa_sms_provider">
							<option value="twilio" <?php selected( $s['provider'], 'twilio' ); ?>>Twilio</option>
						</select>
						<p class="description">Twilio is built in. Other providers can be added via the <code>tsa_sms_send</code> filter.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tsa_sms_sid">Twilio Account SID</label></th>
					<td><input type="text" class="regular-text" id="tsa_sms_sid" name="tsa_sms_sid" value="<?php echo esc_attr( $s['twilio_sid'] ); ?>" autocomplete="off"></td>
				</tr>
				<tr>
					<th scope="row"><label for="tsa_sms_token">Twilio Auth Token</label></th>
					<td><input type="password" class="regular-text" id="tsa_sms_token" name="tsa_sms_token" value="<?php echo esc_attr( $s['twilio_token'] ); ?>" autocomplete="off"></td>
				</tr>
				<tr>
					<th scope="row"><label for="tsa_sms_from">From number</label></th>
					<td><input type="text" class="regular-text" id="tsa_sms_from" name="tsa_sms_from" value="<?php echo esc_attr( $s['from'] ); ?>" placeholder="+1XXXXXXXXXX"></td>
				</tr>
				<tr>
					<th scope="row"><label for="tsa_sms_to">Send alerts to</label></th>
					<td><input type="text" class="regular-text" id="tsa_sms_to" name="tsa_sms_to" value="<?php echo esc_attr( $s['to'] ); ?>" placeholder="+1XXXXXXXXXX, +1XXXXXXXXXX">
						<p class="description">One or more numbers, <strong>comma-separated</strong> — each gets its own text. E.164 format (e.g. <code>+15551234567</code>).</p></td>
				</tr>
				<tr>
					<th scope="row">Text me about</th>
					<td>
						<?php foreach ( $event_opts as $val => $lab ) : ?>
						<label style="display:block;margin:3px 0"><input type="checkbox" name="tsa_sms_events[]" value="<?php echo esc_attr( $val ); ?>" <?php checked( in_array( $val, (array) $s['events'], true ) ); ?>> <?php echo esc_html( $lab ); ?></label>
						<?php endforeach; ?>
					</td>
				</tr>
			</tbody></table>
			<?php submit_button( 'Save Alert Settings' ); ?>
		</form>

		<hr>
		<form method="post">
			<?php wp_nonce_field( 'tsa_sms_test', 'tsa_sms_test_nonce' ); ?>
			<p><button type="submit" class="button">Send test text</button> <span class="description">Sends one text to the number above using the saved settings.</span></p>
		</form>
	</div>
	<?php
}
