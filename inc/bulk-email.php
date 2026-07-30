<?php
/**
 * TSA Bulk Email — a compose-and-send console for reaching your customers.
 *
 * Flow (one screen):
 *   1. Pick a SOURCE — All customers, a specific Store (School / Team /
 *      Business), a Tee Party, or a pasted list — then click "Load recipients".
 *   2. The actual customers appear as a CHECKLIST (name + email + their latest
 *      order). Tick exactly who you want (select-all provided).
 *   3. Compose a subject + message with merge tags ({first_name},
 *      {order_number}, {order_status}, {order_total}, {order_items}, …).
 *   4. Preview the count, send yourself a Test, then Send to the checked list.
 *
 * Customers are derived from WooCommerce orders, attributed to a store by the
 * order-item meta _ac_configurator.store_slug (the same link reports.php uses).
 * Each customer carries their most recent order for that store, so order-status
 * messages personalize automatically.
 *
 * Sending is BATCHED through WP-Cron (tsa_bulk_process) so a large blast never
 * times out or trips host limits. Every message carries a one-click, HMAC-signed
 * unsubscribe link that adds the address to a global suppression list honoured
 * by every future send. Self-contained: no other module is edited.
 */
defined( 'ABSPATH' ) || exit;

const TSA_BULK_CPT      = 'tsa_bulk_campaign';
const TSA_BULK_BATCH    = 25;                  // recipients per cron tick
const TSA_BULK_SUPPRESS = 'tsa_bulk_suppress'; // option: unsubscribed emails
const TSA_BULK_INDEX_TTL = 600;                // customer-index cache seconds

/* ─── Campaign store (internal CPT, no public UI) ──────────────────── */
add_action( 'init', function () {
	register_post_type( TSA_BULK_CPT, [
		'labels'          => [ 'name' => 'Bulk Campaigns', 'singular_name' => 'Bulk Campaign' ],
		'public'          => false,
		'show_ui'         => false,
		'show_in_menu'    => false,
		'supports'        => [ 'title' ],
		'capability_type' => 'post',
	] );
} );

/* ─── Admin menu ───────────────────────────────────────────────────── */
add_action( 'admin_menu', function () {
	add_menu_page(
		'Bulk Email', 'Bulk Email', 'manage_options',
		'tsa-bulk-email', 'tsa_bulk_email_page', 'dashicons-email-alt2', 27
	);
} );

/* ─── Command Center card ──────────────────────────────────────────── */
add_filter( 'tsa_admin_hub_tools', function ( $groups ) {
	$card = [ 'Bulk Email', 'Email customers by store, party or event', '📣', admin_url( 'admin.php?page=tsa-bulk-email' ) ];
	if ( isset( $groups['Drops & Marketing'] ) && is_array( $groups['Drops & Marketing'] ) ) {
		$groups['Drops & Marketing'][] = $card;
	} else {
		$groups['Drops & Marketing'] = [ $card ];
	}
	return $groups;
} );

/* ─── Customer index (from WooCommerce orders, by store slug) ───────── */
/**
 * Build [ 'all' => [ email => data ], 'stores' => [ slug => [ email => data ] ] ]
 * where data = name + latest-order fields. Newest order wins per customer.
 * Cached; pass $fresh = true to rebuild.
 */
function tsa_bulk_customer_index( $fresh = false ) {
	$key = 'tsa_bulk_cust_index';
	if ( ! $fresh ) { $c = get_transient( $key ); if ( is_array( $c ) ) { return $c; } }

	$index = [ 'all' => [], 'stores' => [] ];
	if ( ! function_exists( 'wc_get_orders' ) ) { set_transient( $key, $index, TSA_BULK_INDEX_TTL ); return $index; }

	// Real customers = paid/fulfilled orders. Scanned newest-first so the first
	// time we see an email is their most recent order.
	$statuses = [ 'processing', 'completed', 'on-hold' ];
	$orders   = wc_get_orders( [ 'limit' => -1, 'status' => $statuses, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' ] );

	foreach ( (array) $orders as $o ) {
		if ( ! is_object( $o ) ) { continue; }
		$email = strtolower( (string) $o->get_billing_email() );
		if ( ! is_email( $email ) ) { continue; }

		$items = []; $slugs = [];
		foreach ( $o->get_items() as $it ) {
			$items[] = $it->get_name() . ' ×' . $it->get_quantity();
			$raw = $it->get_meta( '_ac_configurator' );
			if ( $raw ) {
				$ac = json_decode( $raw, true );
				if ( is_array( $ac ) && ! empty( $ac['store_slug'] ) ) { $slugs[ sanitize_title( $ac['store_slug'] ) ] = 1; }
			}
		}

		$data = [
			'name'         => trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ),
			'order_number' => $o->get_order_number(),
			'order_status' => function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $o->get_status() ) : $o->get_status(),
			'order_status_slug' => $o->get_status(),
			'order_total'  => html_entity_decode( wp_strip_all_tags( $o->get_formatted_order_total() ) ),
			'order_date'   => $o->get_date_created() ? $o->get_date_created()->date( 'M j, Y' ) : '',
			'order_items'  => implode( ', ', $items ),
		];

		if ( ! isset( $index['all'][ $email ] ) ) { $index['all'][ $email ] = $data; }
		foreach ( array_keys( $slugs ) as $slug ) {
			if ( ! isset( $index['stores'][ $slug ][ $email ] ) ) { $index['stores'][ $slug ][ $email ] = $data; }
		}
	}

	set_transient( $key, $index, TSA_BULK_INDEX_TTL );
	return $index;
}

/**
 * Source options for the picker, as optgroups:
 *   [ 'Group label' => [ value => label ] ]
 * Store values are "store:<slug>"; specials are "all" / "manual".
 */
function tsa_bulk_sources() {
	$out = [ 'General' => [ 'all' => 'All customers', 'manual' => 'Custom pasted list' ] ];

	// Stores grouped by type.
	$type_labels = [ 'school' => 'Schools', 'team' => 'Teams', 'business' => 'Businesses' ];
	$stores = get_posts( [ 'post_type' => 'configurator_store', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
	foreach ( $stores as $s ) {
		$type = get_post_meta( $s->ID, '_tsa_store_type', true ) ?: 'school';
		$slug = sanitize_title( get_post_meta( $s->ID, '_ac_store_slug', true ) ?: $s->post_name );
		if ( ! $slug ) { continue; }
		$out[ $type_labels[ $type ] ?? 'Stores' ][ 'store:' . $slug ] = get_the_title( $s );
	}

	// Tee Parties (linked to a store slug → its buyers).
	$parties = get_posts( [
		'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC',
		'meta_key' => '_wp_page_template', 'meta_value' => 'template-tee-party.php',
	] );
	foreach ( $parties as $p ) {
		$slug = sanitize_title( (string) get_post_meta( $p->ID, '_tsa_party_store_slug', true ) );
		if ( ! $slug ) { continue; }
		$out['Tee Parties'][ 'store:' . $slug ] = get_the_title( $p );
	}

	return $out;
}

/** Order statuses offered as the checklist filter (matches what the index scans). */
function tsa_bulk_status_options() {
	$out = [];
	foreach ( [ 'processing', 'completed', 'on-hold' ] as $s ) {
		$out[ $s ] = function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $s ) : ucfirst( $s );
	}
	return $out;
}

/**
 * Resolve a source value → recipient rows [ email, name, data ].
 * $status (slug, or 'any') limits to customers whose latest order is that status.
 */
function tsa_bulk_recipients_for( $source, $manual = '', $fresh = false, $status = 'any' ) {
	$rows = [];

	if ( 'manual' === $source ) {
		foreach ( preg_split( '/[\s,;]+/', (string) $manual ) as $tok ) {
			$email = strtolower( trim( $tok ) );
			if ( is_email( $email ) ) { $rows[] = [ 'email' => $email, 'name' => '', 'data' => [] ]; }
		}
		return tsa_bulk_dedupe_suppress( $rows );
	}

	$index = tsa_bulk_customer_index( $fresh );
	if ( 'all' === $source ) {
		$bucket = $index['all'];
	} elseif ( 0 === strpos( (string) $source, 'store:' ) ) {
		$slug   = sanitize_title( substr( $source, 6 ) );
		$bucket = $index['stores'][ $slug ] ?? [];
	} else {
		$bucket = [];
	}
	foreach ( $bucket as $email => $data ) {
		if ( $status && 'any' !== $status && ( $data['order_status_slug'] ?? '' ) !== $status ) { continue; }
		$rows[] = [ 'email' => $email, 'name' => (string) ( $data['name'] ?? '' ), 'data' => $data ];
	}
	return tsa_bulk_dedupe_suppress( $rows );
}

/* ─── Dedupe + suppression ─────────────────────────────────────────── */
function tsa_bulk_suppressed() {
	return array_map( 'strtolower', (array) get_option( TSA_BULK_SUPPRESS, [] ) );
}

function tsa_bulk_dedupe_suppress( array $rows ) {
	$suppressed = tsa_bulk_suppressed();
	$seen = []; $out = [];
	foreach ( $rows as $r ) {
		$email = strtolower( (string) ( $r['email'] ?? '' ) );
		if ( ! is_email( $email ) || in_array( $email, $suppressed, true ) || isset( $seen[ $email ] ) ) { continue; }
		$seen[ $email ] = 1;
		$out[] = [ 'email' => $email, 'name' => (string) ( $r['name'] ?? '' ), 'data' => (array) ( $r['data'] ?? [] ) ];
	}
	return $out;
}

/* ─── Merge tags ───────────────────────────────────────────────────── */
function tsa_bulk_merge_map( array $r, $for_html ) {
	$name  = trim( (string) ( $r['name'] ?? '' ) );
	$parts = $name !== '' ? preg_split( '/\s+/', $name ) : [];
	$d     = (array) ( $r['data'] ?? [] );
	$vals  = [
		'{first_name}'   => ( $parts[0] ?? '' ) !== '' ? $parts[0] : 'there',
		'{name}'         => $name,
		'{email}'        => (string) ( $r['email'] ?? '' ),
		'{order_number}' => (string) ( $d['order_number'] ?? '' ),
		'{order_status}' => (string) ( $d['order_status'] ?? '' ),
		'{order_total}'  => (string) ( $d['order_total'] ?? '' ),
		'{order_date}'   => (string) ( $d['order_date'] ?? '' ),
		'{order_items}'  => (string) ( $d['order_items'] ?? '' ),
	];
	return $for_html ? array_map( 'esc_html', $vals ) : array_map( 'wp_strip_all_tags', $vals );
}
function tsa_bulk_merge( $tpl, array $r, $for_html ) {
	return strtr( (string) $tpl, tsa_bulk_merge_map( $r, $for_html ) );
}
function tsa_bulk_merge_tags() {
	return [ '{first_name}', '{name}', '{email}', '{order_number}', '{order_status}', '{order_total}', '{order_date}', '{order_items}' ];
}

/* ─── Unsubscribe (one-click, HMAC) ────────────────────────────────── */
function tsa_bulk_unsub_token( $email ) {
	return hash_hmac( 'sha256', strtolower( (string) $email ) . '|bulk', wp_salt( 'auth' ) );
}
add_action( 'init', function () {
	if ( empty( $_GET['tsa_bunsub'] ) ) { return; }
	$email = sanitize_email( wp_unslash( $_GET['e'] ?? '' ) );
	$token = sanitize_text_field( wp_unslash( $_GET['tsa_bunsub'] ) );
	if ( is_email( $email ) && hash_equals( tsa_bulk_unsub_token( $email ), $token ) ) {
		$list = tsa_bulk_suppressed();
		if ( ! in_array( strtolower( $email ), $list, true ) ) {
			$list[] = strtolower( $email );
			update_option( TSA_BULK_SUPPRESS, array_values( array_unique( $list ) ) );
		}
		wp_die(
			'<p style="font:16px system-ui;margin:0 0 14px">You\'ve been unsubscribed — you won\'t receive further email from Tee Shirt Ali.</p>'
			. '<p style="font:14px system-ui"><a href="' . esc_url( home_url( '/' ) ) . '">← Back to Tee Shirt Ali</a></p>',
			'Unsubscribed', [ 'response' => 200 ]
		);
	}
	wp_die( '<p style="font:16px system-ui">That unsubscribe link is invalid or expired.</p>', 'Unsubscribe', [ 'response' => 200 ] );
} );

/* ─── Branded HTML wrapper + single send ───────────────────────────── */
function tsa_bulk_wrap( $inner_html, $to ) {
	$accent = '#d8a85f';
	$unsub  = add_query_arg( [ 'tsa_bunsub' => tsa_bulk_unsub_token( $to ), 'e' => rawurlencode( $to ) ], home_url( '/' ) );
	$name   = get_bloginfo( 'name' );
	return '<div style="background:#f4f1f2;padding:28px 12px;font-family:system-ui,Arial,sans-serif">'
		. '<table role="presentation" width="100%" cellpadding="0" cellspacing="0" style="max-width:600px;margin:0 auto;background:#fff;border-radius:16px;overflow:hidden;border:1px solid #ececec">'
		. '<tr><td style="background:' . esc_attr( $accent ) . ';height:6px"></td></tr>'
		. '<tr><td style="padding:30px 32px;font:16px/1.6 system-ui;color:#33303a">' . $inner_html . '</td></tr>'
		. '<tr><td style="padding:16px 30px 26px;border-top:1px solid #f0eef0;font:12px/1.5 system-ui;color:#9a9298;text-align:center">'
		. esc_html( $name ) . '<br><a href="' . esc_url( $unsub ) . '" style="color:#9a9298">Unsubscribe</a>'
		. '</td></tr></table></div>';
}
function tsa_bulk_send_one( array $r, $subject, $body ) {
	$to   = $r['email'];
	$subj = tsa_bulk_merge( $subject, $r, false );
	$html = tsa_bulk_wrap( wpautop( tsa_bulk_merge( $body, $r, true ) ), $to );
	return (bool) wp_mail( $to, $subj, $html, [ 'Content-Type: text/html; charset=UTF-8' ] );
}

/* ─── Campaign queue + cron processor ──────────────────────────────── */
function tsa_bulk_start_campaign( $subject, $body, array $recipients ) {
	$cid = wp_insert_post( [ 'post_type' => TSA_BULK_CPT, 'post_status' => 'publish', 'post_title' => $subject ?: 'Bulk Email' ], true );
	if ( is_wp_error( $cid ) || ! $cid ) { return 0; }
	update_post_meta( $cid, '_subject', $subject );
	update_post_meta( $cid, '_body', $body );
	update_post_meta( $cid, '_queue', wp_json_encode( array_values( $recipients ) ) );
	update_post_meta( $cid, '_total', count( $recipients ) );
	update_post_meta( $cid, '_sent', 0 );
	update_post_meta( $cid, '_failed', 0 );
	update_post_meta( $cid, '_state', 'sending' );
	wp_schedule_single_event( time() + 5, 'tsa_bulk_process', [ $cid ] );
	spawn_cron();
	return $cid;
}

add_action( 'tsa_bulk_process', 'tsa_bulk_process_campaign' );
function tsa_bulk_process_campaign( $cid ) {
	$cid = (int) $cid;
	if ( get_post_meta( $cid, '_state', true ) !== 'sending' ) { return; }
	if ( get_transient( 'tsa_bulk_lock_' . $cid ) ) { return; }
	set_transient( 'tsa_bulk_lock_' . $cid, 1, 120 );

	$queue = json_decode( get_post_meta( $cid, '_queue', true ) ?: '[]', true ) ?: [];
	if ( ! $queue ) { update_post_meta( $cid, '_state', 'done' ); delete_transient( 'tsa_bulk_lock_' . $cid ); return; }

	$subject = (string) get_post_meta( $cid, '_subject', true );
	$body    = (string) get_post_meta( $cid, '_body', true );
	$batch   = array_splice( $queue, 0, TSA_BULK_BATCH );
	$sent    = (int) get_post_meta( $cid, '_sent', true );
	$failed  = (int) get_post_meta( $cid, '_failed', true );

	foreach ( $batch as $r ) {
		if ( tsa_bulk_send_one( (array) $r, $subject, $body ) ) { $sent++; } else { $failed++; }
	}

	update_post_meta( $cid, '_queue', wp_json_encode( array_values( $queue ) ) );
	update_post_meta( $cid, '_sent', $sent );
	update_post_meta( $cid, '_failed', $failed );
	delete_transient( 'tsa_bulk_lock_' . $cid );

	if ( $queue ) {
		wp_schedule_single_event( time() + MINUTE_IN_SECONDS, 'tsa_bulk_process', [ $cid ] );
	} else {
		update_post_meta( $cid, '_state', 'done' );
	}
}

/** Lazy fallback: on the Bulk Email screen, nudge any stalled sending campaign. */
function tsa_bulk_kick_stalled() {
	$ids = get_posts( [
		'post_type' => TSA_BULK_CPT, 'post_status' => 'publish', 'numberposts' => 5, 'fields' => 'ids',
		'meta_query' => [ [ 'key' => '_state', 'value' => 'sending' ] ],
	] );
	foreach ( (array) $ids as $cid ) {
		if ( ! wp_next_scheduled( 'tsa_bulk_process', [ (int) $cid ] ) ) { tsa_bulk_process_campaign( (int) $cid ); }
	}
}

/* ─── Decode checked recipients from the checklist ─────────────────── */
function tsa_bulk_encode_recipient( array $r ) {
	return base64_encode( wp_json_encode( [ 'email' => $r['email'], 'name' => $r['name'], 'data' => $r['data'] ] ) );
}
function tsa_bulk_decode_recipients( array $tokens ) {
	$out = [];
	foreach ( $tokens as $t ) {
		$j = json_decode( base64_decode( (string) $t ), true );
		if ( ! is_array( $j ) ) { continue; }
		$email = strtolower( sanitize_email( (string) ( $j['email'] ?? '' ) ) );
		if ( ! is_email( $email ) ) { continue; }
		$out[] = [
			'email' => $email,
			'name'  => sanitize_text_field( (string) ( $j['name'] ?? '' ) ),
			'data'  => array_map( 'sanitize_text_field', (array) ( $j['data'] ?? [] ) ),
		];
	}
	return tsa_bulk_dedupe_suppress( $out );
}

/* ─── CSV export of the checked recipients ─────────────────────────── */
add_action( 'admin_post_tsa_bulk_export', 'tsa_bulk_export_csv' );
function tsa_bulk_export_csv() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
	check_admin_referer( 'tsa_bulk_send', 'tsa_bulk_nonce' );
	$rows = tsa_bulk_decode_recipients( (array) ( $_POST['recipient'] ?? [] ) );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=bulk-email-recipients-' . gmdate( 'Ymd-His' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, [ 'Email', 'Name', 'Order #', 'Status', 'Total', 'Date', 'Items' ] );
	foreach ( $rows as $r ) {
		$d = (array) $r['data'];
		fputcsv( $out, [ $r['email'], $r['name'], $d['order_number'] ?? '', $d['order_status'] ?? '', $d['order_total'] ?? '', $d['order_date'] ?? '', $d['order_items'] ?? '' ] );
	}
	fclose( $out );
	exit;
}

/* ─── Admin screen (handles its own POST) ──────────────────────────── */
function tsa_bulk_email_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	tsa_bulk_kick_stalled();

	$notice = '';
	$loaded = [];                 // recipient rows shown as the checklist
	$source = 'all';
	$manual = '';
	$subject = '';
	$body    = '';
	$order_status = 'any';

	if ( ! empty( $_POST['tsa_do'] ) ) {
		check_admin_referer( 'tsa_bulk_send', 'tsa_bulk_nonce' );
		$do      = sanitize_key( $_POST['tsa_do'] );
		$source  = sanitize_text_field( wp_unslash( $_POST['source'] ?? 'all' ) );
		$manual  = sanitize_textarea_field( wp_unslash( $_POST['manual_list'] ?? '' ) );
		$subject = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
		$body    = wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) );
		$order_status = sanitize_text_field( wp_unslash( $_POST['order_status'] ?? 'any' ) );

		if ( 'load' === $do ) {
			$loaded = tsa_bulk_recipients_for( $source, $manual, ! empty( $_POST['refresh'] ), $order_status );
			$notice = $loaded ? [ 'info', count( $loaded ) . ' customer(s) found — tick who to email, then compose below.' ]
			                  : [ 'error', 'No customers found for that source. Try another, or refresh the customer list.' ];
		} else {
			// preview / test / send operate on the checked rows.
			$chosen = tsa_bulk_decode_recipients( (array) ( $_POST['recipient'] ?? [] ) );
			$loaded = $chosen; // keep them on screen

			if ( 'preview' === $do ) {
				$sample = array_slice( wp_list_pluck( $chosen, 'email' ), 0, 15 );
				$notice = [ 'info', count( $chosen ) . ' recipient(s) selected.' . ( $sample ? ' Sample: ' . implode( ', ', $sample ) . ( count( $chosen ) > count( $sample ) ? '…' : '' ) : '' ) ];
			} elseif ( 'test' === $do ) {
				$me = wp_get_current_user();
				$r  = $chosen ? $chosen[0] : [ 'email' => '', 'name' => $me->display_name, 'data' => [] ];
				$r['email'] = $me->user_email;
				tsa_bulk_send_one( $r, $subject ?: '(no subject)', $body ?: '(empty body)' );
				$notice = [ 'success', 'Test email sent to ' . $me->user_email . '.' ];
			} elseif ( 'send' === $do ) {
				if ( ! $chosen ) {
					$notice = [ 'error', 'No recipients checked.' ];
				} elseif ( '' === trim( $subject ) || '' === trim( wp_strip_all_tags( $body ) ) ) {
					$notice = [ 'error', 'Add a subject and a message before sending.' ];
				} else {
					$cid = tsa_bulk_start_campaign( $subject, $body, $chosen );
					$notice = [ 'success', 'Campaign queued for ' . count( $chosen ) . ' recipient(s) — sending in the background. Progress is below.' ];
					$loaded = []; // clear checklist after a successful send
				}
			}
		}
	}

	$sources = tsa_bulk_sources();
	$tags    = tsa_bulk_merge_tags();
	?>
	<div class="wrap">
		<h1>Bulk Email</h1>
		<p style="max-width:780px;color:#50575e">Pick who you're emailing, load the customer list, tick the ones you want, then compose. Messages send in the background in batches, each with a one-click unsubscribe. Use <strong>Send test to me</strong> and <strong>Preview</strong> before sending for real.</p>

		<?php if ( $notice ) : ?>
			<div class="notice notice-<?php echo esc_attr( $notice[0] ); ?> is-dismissible"><p><?php echo esc_html( $notice[1] ); ?></p></div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'tsa_bulk_send', 'tsa_bulk_nonce' ); ?>

			<h2 class="title">1 · Who</h2>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row"><label for="tsa-bulk-source">Source</label></th>
					<td>
						<select id="tsa-bulk-source" name="source">
							<?php foreach ( $sources as $grp => $opts ) : ?>
								<optgroup label="<?php echo esc_attr( $grp ); ?>">
									<?php foreach ( $opts as $val => $lab ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $source, $val ); ?>><?php echo esc_html( $lab ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
						<label style="margin-left:12px"><input type="checkbox" name="refresh" value="1"> Refresh customer list (re-scan orders)</label>
						<p class="description">Customers come from paid orders, attributed to a store/party. Stores &amp; parties with no orders yet won't list anyone.</p>
					</td>
				</tr>
				<tr id="tsa-bulk-manual-row" style="<?php echo 'manual' === $source ? '' : 'display:none'; ?>">
					<th scope="row"><label for="tsa-bulk-manual">Pasted list</label></th>
					<td><textarea id="tsa-bulk-manual" name="manual_list" rows="3" class="large-text" placeholder="emails separated by commas, spaces, or new lines"><?php echo esc_textarea( $manual ); ?></textarea></td>
				</tr>
				<tr id="tsa-bulk-status-row" style="<?php echo 'manual' === $source ? 'display:none' : ''; ?>">
					<th scope="row"><label for="tsa-bulk-status">Order status</label></th>
					<td>
						<select id="tsa-bulk-status" name="order_status">
							<option value="any" <?php selected( $order_status, 'any' ); ?>>Any status</option>
							<?php foreach ( tsa_bulk_status_options() as $sv => $sl ) : ?>
								<option value="<?php echo esc_attr( $sv ); ?>" <?php selected( $order_status, $sv ); ?>><?php echo esc_html( $sl ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">Limit the list to customers whose most recent order has this status (e.g. everyone still in <em>Processing</em>). Ignored for a pasted list.</p>
					</td>
				</tr>
			</tbody></table>
			<p><button type="submit" name="tsa_do" value="load" class="button">Load recipients</button></p>

			<?php if ( $loaded ) : ?>
				<h2 class="title">2 · Recipients (<?php echo count( $loaded ); ?>)</h2>
				<p><label><input type="checkbox" id="tsa-bulk-all" checked> <strong>Select all</strong></label></p>
				<div style="max-height:340px;overflow:auto;border:1px solid #dcdcde;border-radius:6px;background:#fff;max-width:820px">
					<table class="widefat striped" style="border:0">
						<thead><tr><th style="width:32px"></th><th>Name</th><th>Email</th><th>Latest order</th></tr></thead>
						<tbody>
						<?php foreach ( $loaded as $r ) :
							$d = $r['data']; ?>
							<tr>
								<td><input type="checkbox" class="tsa-bulk-cb" name="recipient[]" value="<?php echo esc_attr( tsa_bulk_encode_recipient( $r ) ); ?>" checked></td>
								<td><?php echo esc_html( $r['name'] ?: '—' ); ?></td>
								<td><?php echo esc_html( $r['email'] ); ?></td>
								<td><?php echo esc_html( trim( ( ( $d['order_number'] ?? '' ) ? '#' . $d['order_number'] : '' ) . ' ' . ( $d['order_status'] ?? '' ) ) ?: '—' ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>

				<h2 class="title">3 · Message</h2>
				<table class="form-table" role="presentation"><tbody>
					<tr>
						<th scope="row"><label for="tsa-bulk-subject">Subject</label></th>
						<td><input type="text" id="tsa-bulk-subject" name="subject" class="large-text" value="<?php echo esc_attr( $subject ); ?>" placeholder="e.g. Your order is on the way"></td>
					</tr>
					<tr>
						<th scope="row"><label for="tsa-bulk-body">Message</label></th>
						<td>
							<textarea id="tsa-bulk-body" name="body" rows="10" class="large-text" placeholder="Hi {first_name}, …"><?php echo esc_textarea( $body ); ?></textarea>
							<p class="description">Basic HTML allowed. Merge tags: <?php echo esc_html( implode( '  ', $tags ) ); ?>. A branded header/footer + unsubscribe link are added automatically.</p>
						</td>
					</tr>
				</tbody></table>

				<h2 class="title">4 · Send</h2>
				<p class="submit">
					<button type="submit" name="tsa_do" value="preview" class="button">Preview selected</button>
					<button type="submit" name="tsa_do" value="test" class="button">Send test to me</button>
					<button type="submit" name="action" value="tsa_bulk_export" class="button" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">Export CSV</button>
					<button type="submit" name="tsa_do" value="send" class="button button-primary" onclick="return confirm('Send this email to the checked recipients?');">Send campaign</button>
				</p>
			<?php endif; ?>
		</form>

		<?php
		$recent = get_posts( [ 'post_type' => TSA_BULK_CPT, 'post_status' => 'publish', 'numberposts' => 8 ] );
		if ( $recent ) : ?>
			<h2 class="title">Recent campaigns</h2>
			<table class="widefat striped" style="max-width:820px">
				<thead><tr><th>Subject</th><th>Sent</th><th>Failed</th><th>Total</th><th>State</th><th>When</th></tr></thead>
				<tbody>
				<?php foreach ( $recent as $c ) :
					$t = (int) get_post_meta( $c->ID, '_total', true );
					$s = (int) get_post_meta( $c->ID, '_sent', true );
					$f = (int) get_post_meta( $c->ID, '_failed', true );
					$state = get_post_meta( $c->ID, '_state', true ) ?: 'done'; ?>
					<tr>
						<td><?php echo esc_html( get_the_title( $c ) ); ?></td>
						<td><?php echo (int) $s; ?></td>
						<td><?php echo (int) $f; ?></td>
						<td><?php echo (int) $t; ?></td>
						<td><strong style="color:<?php echo 'sending' === $state ? '#b26a00' : '#1a7f37'; ?>"><?php echo esc_html( 'sending' === $state ? 'Sending…' : 'Done' ); ?></strong></td>
						<td><?php echo esc_html( get_the_date( 'M j, g:i a', $c ) ); ?></td>
					</tr>
				<?php endforeach; ?>
				</tbody>
			</table>
		<?php endif; ?>
	</div>

	<script>
	(function(){
		var src = document.getElementById('tsa-bulk-source'),
		    row = document.getElementById('tsa-bulk-manual-row'),
		    strow = document.getElementById('tsa-bulk-status-row');
		if (src) { src.addEventListener('change', function(){
			var manual = src.value === 'manual';
			if (row)   { row.style.display   = manual ? '' : 'none'; }
			if (strow) { strow.style.display = manual ? 'none' : ''; }
		}); }
		var all = document.getElementById('tsa-bulk-all');
		if (all) { all.addEventListener('change', function(){
			document.querySelectorAll('.tsa-bulk-cb').forEach(function(cb){ cb.checked = all.checked; });
		}); }
	})();
	</script>
	<?php
}
