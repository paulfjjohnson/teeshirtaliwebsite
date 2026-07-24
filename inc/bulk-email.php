<?php
/**
 * TSA Bulk Email — a compose-and-send console for reaching your audiences.
 *
 * One admin screen (Bulk Email) where you:
 *   1. Build an audience from segments — Customers (WooCommerce, by order
 *      status), Stores (school / team / business), Tee Party subscribers,
 *      Ambassadors, Fundraiser contacts, Requests-inbox contacts, or a pasted
 *      list — and combine as many as you like.
 *   2. Compose a subject + HTML message with merge tags ({first_name},
 *      {order_number}, {order_status}, {order_total}, {order_items}, …).
 *   3. Preview the recipient count, send yourself a test, then send.
 *
 * Sending is BATCHED through WP-Cron (tsa_bulk_process) so a large blast never
 * times out the request or trips host rate limits. Every message carries a
 * one-click unsubscribe link (HMAC-signed) that adds the address to a global
 * suppression list honoured by every future send.
 *
 * Self-contained: no other module is edited. Nothing sends automatically —
 * a human composes and clicks Send.
 */
defined( 'ABSPATH' ) || exit;

const TSA_BULK_CPT       = 'tsa_bulk_campaign';
const TSA_BULK_BATCH     = 25;                       // recipients per cron tick
const TSA_BULK_SUPPRESS  = 'tsa_bulk_suppress';      // option: unsubscribed emails
const TSA_BULK_MAX_ORDERS = 5000;                    // safety cap when scanning Woo orders

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
	$card = [ 'Bulk Email', 'Email customers, stores & subscribers', '📣', admin_url( 'admin.php?page=tsa-bulk-email' ) ];
	if ( isset( $groups['Drops & Marketing'] ) && is_array( $groups['Drops & Marketing'] ) ) {
		$groups['Drops & Marketing'][] = $card;
	} else {
		$groups['Drops & Marketing'] = [ $card ];
	}
	return $groups;
} );

/* ─── Segment / provider registry ──────────────────────────────────── */
/**
 * Each provider returns rows: [ 'email' => , 'name' => , 'data' => [merge…] ].
 * $filters carries per-segment options from the form.
 */
function tsa_bulk_segments() {
	return [
		'customers'   => [ 'label' => 'StA Customers',        'cb' => 'tsa_bulk_src_customers'  ],
		'stores'      => [ 'label' => 'Stores',               'cb' => 'tsa_bulk_src_stores'     ],
		'party'       => [ 'label' => 'Tee Party subscribers','cb' => 'tsa_bulk_src_party'      ],
		'ambassadors' => [ 'label' => 'Ambassadors',          'cb' => 'tsa_bulk_src_ambassadors'],
		'fundraisers' => [ 'label' => 'Fundraiser contacts',  'cb' => 'tsa_bulk_src_fundraisers'],
		'requests'    => [ 'label' => 'Requests inbox',       'cb' => 'tsa_bulk_src_requests'   ],
		'manual'      => [ 'label' => 'Custom pasted list',   'cb' => 'tsa_bulk_src_manual'     ],
	];
}

/** WooCommerce customers, optionally one row per order (personalized). */
function tsa_bulk_src_customers( $filters ) {
	if ( ! function_exists( 'wc_get_orders' ) ) { return []; }
	$status      = $filters['order_status'] ?? 'any';
	$personalize = ! empty( $filters['personalize'] );
	$args = [ 'limit' => TSA_BULK_MAX_ORDERS, 'orderby' => 'date', 'order' => 'DESC' ];
	if ( $status && 'any' !== $status ) { $args['status'] = $status; }
	$orders = wc_get_orders( $args );
	$out = []; $seen = [];
	foreach ( (array) $orders as $o ) {
		if ( ! is_object( $o ) ) { continue; }
		$email = strtolower( (string) $o->get_billing_email() );
		if ( ! is_email( $email ) ) { continue; }
		if ( ! $personalize && isset( $seen[ $email ] ) ) { continue; }
		$seen[ $email ] = 1;
		$items = [];
		foreach ( $o->get_items() as $it ) { $items[] = $it->get_name() . ' ×' . $it->get_quantity(); }
		$out[] = [
			'email' => $email,
			'name'  => trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() ),
			'data'  => [
				'order_number' => $o->get_order_number(),
				'order_status' => function_exists( 'wc_get_order_status_name' ) ? wc_get_order_status_name( $o->get_status() ) : $o->get_status(),
				'order_total'  => html_entity_decode( wp_strip_all_tags( $o->get_formatted_order_total() ) ),
				'order_date'   => $o->get_date_created() ? $o->get_date_created()->date( 'M j, Y' ) : '',
				'order_items'  => implode( ', ', $items ),
			],
		];
	}
	return $out;
}

/** Store records (configurator_store), filterable by school / team / business. */
function tsa_bulk_src_stores( $filters ) {
	$type = $filters['store_type'] ?? 'any';
	$ids  = get_posts( [ 'post_type' => 'configurator_store', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] );
	$out  = [];
	foreach ( (array) $ids as $id ) {
		$stype = get_post_meta( $id, '_tsa_store_type', true ) ?: 'school'; // legacy blank = school
		if ( $type && 'any' !== $type && $stype !== $type ) { continue; }
		$email = strtolower( (string) get_post_meta( $id, '_tsa_school_contact_email', true ) );
		if ( ! is_email( $email ) ) { continue; }
		$name  = get_the_title( $id );
		$out[] = [ 'email' => $email, 'name' => $name, 'data' => [ 'store_name' => $name ] ];
	}
	return $out;
}

/** Tee Party subscribers — one party, or all parties when party_id is 0. */
function tsa_bulk_src_party( $filters ) {
	if ( ! function_exists( 'tsa_party_subscribers' ) ) { return []; }
	$pid   = absint( $filters['party_id'] ?? 0 );
	$pages = $pid ? [ $pid ] : get_posts( [
		'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'fields' => 'ids',
		'meta_key' => '_wp_page_template', 'meta_value' => 'template-tee-party.php',
	] );
	$out = [];
	foreach ( (array) $pages as $p ) {
		$party = get_the_title( $p );
		foreach ( tsa_party_subscribers( $p ) as $email ) {
			$out[] = [ 'email' => strtolower( $email ), 'name' => '', 'data' => [ 'party' => $party ] ];
		}
	}
	return $out;
}

/** Ambassadors (tsa_ambassador). */
function tsa_bulk_src_ambassadors( $filters ) {
	$ids = get_posts( [ 'post_type' => 'tsa_ambassador', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] );
	$out = [];
	foreach ( (array) $ids as $id ) {
		$email = strtolower( (string) get_post_meta( $id, '_tsa_amb_email', true ) );
		if ( ! is_email( $email ) ) { continue; }
		$out[] = [ 'email' => $email, 'name' => get_the_title( $id ), 'data' => [] ];
	}
	return $out;
}

/** Fundraiser contacts (tsa_fundraiser payout email). */
function tsa_bulk_src_fundraisers( $filters ) {
	$ids = get_posts( [ 'post_type' => 'tsa_fundraiser', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] );
	$out = [];
	foreach ( (array) $ids as $id ) {
		$email = strtolower( (string) get_post_meta( $id, '_tsa_fr_payout_email', true ) );
		if ( ! is_email( $email ) ) { continue; }
		$out[] = [ 'email' => $email, 'name' => get_the_title( $id ), 'data' => [] ];
	}
	return $out;
}

/** Requests-inbox contacts (tsa_request), filterable by request type. */
function tsa_bulk_src_requests( $filters ) {
	$rtype = $filters['req_type'] ?? 'any';
	$args  = [ 'post_type' => 'tsa_request', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ];
	if ( $rtype && 'any' !== $rtype ) {
		$args['meta_query'] = [ [ 'key' => '_tsa_req_type', 'value' => sanitize_key( $rtype ) ] ];
	}
	$out = [];
	foreach ( (array) get_posts( $args ) as $id ) {
		$email = strtolower( (string) get_post_meta( $id, '_tsa_req_email', true ) );
		if ( ! is_email( $email ) ) { continue; }
		$out[] = [ 'email' => $email, 'name' => get_post_meta( $id, '_tsa_req_name', true ), 'data' => [] ];
	}
	return $out;
}

/** A pasted / newline / comma separated address list. */
function tsa_bulk_src_manual( $filters ) {
	$raw = (string) ( $filters['manual_list'] ?? '' );
	$out = [];
	foreach ( preg_split( '/[\s,;]+/', $raw ) as $tok ) {
		$email = strtolower( trim( $tok ) );
		if ( is_email( $email ) ) { $out[] = [ 'email' => $email, 'name' => '', 'data' => [] ]; }
	}
	return $out;
}

/* ─── Gather + dedupe + suppression ────────────────────────────────── */
function tsa_bulk_suppressed() {
	return array_map( 'strtolower', (array) get_option( TSA_BULK_SUPPRESS, [] ) );
}

/**
 * Build the final recipient list from the chosen segments.
 * Dedupe by email (by email+order when personalizing, so each order stands).
 */
function tsa_bulk_gather( array $segments, array $filters ) {
	$defs = tsa_bulk_segments();
	$rows = [];
	foreach ( $segments as $seg ) {
		if ( isset( $defs[ $seg ]['cb'] ) && is_callable( $defs[ $seg ]['cb'] ) ) {
			$rows = array_merge( $rows, (array) call_user_func( $defs[ $seg ]['cb'], $filters ) );
		}
	}
	$personalize = ! empty( $filters['personalize'] );
	$suppressed  = tsa_bulk_suppressed();
	$seen = []; $final = [];
	foreach ( $rows as $r ) {
		$email = strtolower( (string) ( $r['email'] ?? '' ) );
		if ( ! is_email( $email ) || in_array( $email, $suppressed, true ) ) { continue; }
		$key = $personalize ? $email . '|' . ( $r['data']['order_number'] ?? '' ) : $email;
		if ( isset( $seen[ $key ] ) ) { continue; }
		$seen[ $key ] = 1;
		$final[] = [ 'email' => $email, 'name' => (string) ( $r['name'] ?? '' ), 'data' => (array) ( $r['data'] ?? [] ) ];
	}
	return $final;
}

/* ─── Merge tags ───────────────────────────────────────────────────── */
function tsa_bulk_merge_map( array $r, $for_html ) {
	$name  = trim( (string) ( $r['name'] ?? '' ) );
	$parts = $name !== '' ? preg_split( '/\s+/', $name ) : [];
	$first = $parts[0] ?? '';
	$d     = (array) ( $r['data'] ?? [] );
	$vals  = [
		'{first_name}'   => $first !== '' ? $first : 'there',
		'{name}'         => $name,
		'{email}'        => (string) ( $r['email'] ?? '' ),
		'{order_number}' => (string) ( $d['order_number'] ?? '' ),
		'{order_status}' => (string) ( $d['order_status'] ?? '' ),
		'{order_total}'  => (string) ( $d['order_total'] ?? '' ),
		'{order_date}'   => (string) ( $d['order_date'] ?? '' ),
		'{order_items}'  => (string) ( $d['order_items'] ?? '' ),
		'{store_name}'   => (string) ( $d['store_name'] ?? '' ),
		'{party}'        => (string) ( $d['party'] ?? '' ),
	];
	if ( $for_html ) { $vals = array_map( 'esc_html', $vals ); }
	else { $vals = array_map( 'wp_strip_all_tags', $vals ); }
	return $vals;
}

function tsa_bulk_merge( $tpl, array $r, $for_html ) {
	return strtr( (string) $tpl, tsa_bulk_merge_map( $r, $for_html ) );
}

/** The list of tags, for the compose-screen helper. */
function tsa_bulk_merge_tags() {
	return [ '{first_name}', '{name}', '{email}', '{order_number}', '{order_status}', '{order_total}', '{order_date}', '{order_items}', '{store_name}', '{party}' ];
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
	// Body is admin-authored HTML; merge values are escaped for the HTML context.
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
	// Guard against overlapping runs (cron + lazy kick).
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
		if ( ! wp_next_scheduled( 'tsa_bulk_process', [ (int) $cid ] ) ) {
			tsa_bulk_process_campaign( (int) $cid );
		}
	}
}

/* ─── Form handler (admin-post) ────────────────────────────────────── */
add_action( 'admin_post_tsa_bulk_action', 'tsa_bulk_handle' );
function tsa_bulk_handle() {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
	check_admin_referer( 'tsa_bulk_send', 'tsa_bulk_nonce' );

	$do       = sanitize_key( $_POST['tsa_do'] ?? '' );
	$segments = array_map( 'sanitize_key', (array) ( $_POST['segments'] ?? [] ) );
	$subject  = sanitize_text_field( wp_unslash( $_POST['subject'] ?? '' ) );
	$body     = wp_kses_post( wp_unslash( $_POST['body'] ?? '' ) );
	$filters  = [
		'order_status' => sanitize_text_field( wp_unslash( $_POST['order_status'] ?? 'any' ) ),
		'store_type'   => sanitize_key( $_POST['store_type'] ?? 'any' ),
		'party_id'     => absint( $_POST['party_id'] ?? 0 ),
		'req_type'     => sanitize_key( $_POST['req_type'] ?? 'any' ),
		'personalize'  => ! empty( $_POST['personalize'] ),
		'manual_list'  => sanitize_textarea_field( wp_unslash( $_POST['manual_list'] ?? '' ) ),
	];
	$back = admin_url( 'admin.php?page=tsa-bulk-email' );

	// Send a test to the current admin, using sample/first-recipient data.
	if ( 'test' === $do ) {
		$me = wp_get_current_user();
		$sample = tsa_bulk_gather( $segments, $filters );
		$r = $sample ? $sample[0] : [ 'email' => $me->user_email, 'name' => $me->display_name, 'data' => [] ];
		$r['email'] = $me->user_email; // deliver the test to yourself
		tsa_bulk_send_one( $r, $subject ?: '(no subject)', $body ?: '(empty body)' );
		wp_safe_redirect( add_query_arg( 'tsa_notice', 'test', $back ) );
		exit;
	}

	$recipients = tsa_bulk_gather( $segments, $filters );

	// Preview: stash count + a small sample for display, then redirect back.
	if ( 'preview' === $do ) {
		set_transient( 'tsa_bulk_preview_' . get_current_user_id(), [
			'count'   => count( $recipients ),
			'sample'  => array_slice( wp_list_pluck( $recipients, 'email' ), 0, 15 ),
		], 300 );
		wp_safe_redirect( add_query_arg( 'tsa_notice', 'preview', $back ) );
		exit;
	}

	// Send.
	if ( 'send' === $do ) {
		if ( ! $recipients ) { wp_safe_redirect( add_query_arg( 'tsa_notice', 'empty', $back ) ); exit; }
		if ( '' === trim( $subject ) || '' === trim( wp_strip_all_tags( $body ) ) ) {
			wp_safe_redirect( add_query_arg( 'tsa_notice', 'nomsg', $back ) ); exit;
		}
		$cid = tsa_bulk_start_campaign( $subject, $body, $recipients );
		wp_safe_redirect( add_query_arg( [ 'tsa_notice' => 'sent', 'c' => (int) $cid ], $back ) );
		exit;
	}

	wp_safe_redirect( $back );
	exit;
}

/* ─── Admin screen ─────────────────────────────────────────────────── */
function tsa_bulk_email_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	tsa_bulk_kick_stalled();

	$notice = sanitize_key( $_GET['tsa_notice'] ?? '' );
	$tags   = tsa_bulk_merge_tags();

	// Party options for the subscriber filter.
	$parties = get_posts( [
		'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1,
		'meta_key' => '_wp_page_template', 'meta_value' => 'template-tee-party.php', 'orderby' => 'title', 'order' => 'ASC',
	] );

	// Woo order statuses for the customer filter.
	$statuses = function_exists( 'wc_get_order_statuses' ) ? wc_get_order_statuses() : [];

	// Request types for the requests filter.
	$req_types = function_exists( 'tsa_request_type_labels' ) ? tsa_request_type_labels() : [];

	$suppressed = tsa_bulk_suppressed();
	?>
	<div class="wrap">
		<h1>Bulk Email</h1>
		<p style="max-width:760px;color:#50575e">Compose a message, choose who receives it, and send. Messages go out in batches in the background, each with a one-click unsubscribe. Use <strong>Send test to me</strong> and <strong>Preview recipients</strong> before you send for real.</p>

		<?php
		if ( 'sent' === $notice )    { echo '<div class="notice notice-success is-dismissible"><p>Campaign queued — sending in the background. Progress is shown below.</p></div>'; }
		if ( 'test' === $notice )    { echo '<div class="notice notice-success is-dismissible"><p>Test email sent to your address.</p></div>'; }
		if ( 'empty' === $notice )   { echo '<div class="notice notice-error is-dismissible"><p>No recipients matched — pick at least one audience with valid emails.</p></div>'; }
		if ( 'nomsg' === $notice )   { echo '<div class="notice notice-error is-dismissible"><p>Add a subject and a message before sending.</p></div>'; }
		if ( 'preview' === $notice ) {
			$pv = get_transient( 'tsa_bulk_preview_' . get_current_user_id() );
			if ( $pv ) {
				echo '<div class="notice notice-info is-dismissible"><p><strong>' . (int) $pv['count'] . ' recipient(s)</strong> match your selection.';
				if ( ! empty( $pv['sample'] ) ) { echo ' Sample: ' . esc_html( implode( ', ', $pv['sample'] ) ) . ( $pv['count'] > count( $pv['sample'] ) ? '…' : '' ); }
				echo '</p></div>';
			}
		}
		?>

		<form method="post" action="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">
			<input type="hidden" name="action" value="tsa_bulk_action">
			<?php wp_nonce_field( 'tsa_bulk_send', 'tsa_bulk_nonce' ); ?>

			<h2 class="title">1 · Audience</h2>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row">Segments</th>
					<td>
						<?php foreach ( tsa_bulk_segments() as $key => $seg ) : ?>
							<label style="display:block;margin:3px 0"><input type="checkbox" name="segments[]" value="<?php echo esc_attr( $key ); ?>"> <?php echo esc_html( $seg['label'] ); ?></label>
						<?php endforeach; ?>
						<p class="description">Tick any combination. Duplicate addresses across segments are sent to once.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Customer filter</th>
					<td>
						<label>Order status
							<select name="order_status">
								<option value="any">Any status</option>
								<?php foreach ( $statuses as $sk => $sl ) : ?>
									<option value="<?php echo esc_attr( str_replace( 'wc-', '', $sk ) ); ?>"><?php echo esc_html( $sl ); ?></option>
								<?php endforeach; ?>
							</select>
						</label>
						&nbsp;&nbsp;
						<label><input type="checkbox" name="personalize" value="1"> Personalize per order (one email per order, enables <code>{order_*}</code> tags)</label>
					</td>
				</tr>
				<tr>
					<th scope="row">Store filter</th>
					<td>
						<select name="store_type">
							<option value="any">All store types</option>
							<option value="school">Schools</option>
							<option value="team">Teams</option>
							<option value="business">Businesses</option>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Tee Party filter</th>
					<td>
						<select name="party_id">
							<option value="0">All parties</option>
							<?php foreach ( $parties as $p ) : ?>
								<option value="<?php echo (int) $p->ID; ?>"><?php echo esc_html( get_the_title( $p ) ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Requests filter</th>
					<td>
						<select name="req_type">
							<option value="any">All request types</option>
							<?php foreach ( $req_types as $rk => $rl ) : ?>
								<option value="<?php echo esc_attr( $rk ); ?>"><?php echo esc_html( $rl ); ?></option>
							<?php endforeach; ?>
						</select>
					</td>
				</tr>
				<tr>
					<th scope="row">Custom list</th>
					<td><textarea name="manual_list" rows="3" class="large-text" placeholder="paste emails separated by commas, spaces, or new lines"></textarea></td>
				</tr>
			</tbody></table>

			<h2 class="title">2 · Message</h2>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row"><label for="tsa-bulk-subject">Subject</label></th>
					<td><input type="text" id="tsa-bulk-subject" name="subject" class="large-text" placeholder="e.g. Your order is on the way"></td>
				</tr>
				<tr>
					<th scope="row"><label for="tsa-bulk-body">Message</label></th>
					<td>
						<textarea id="tsa-bulk-body" name="body" rows="10" class="large-text" placeholder="Hi {first_name}, …"></textarea>
						<p class="description">Basic HTML is allowed. Merge tags: <?php echo esc_html( implode( '  ', $tags ) ); ?>. A branded header/footer and unsubscribe link are added automatically.</p>
					</td>
				</tr>
			</tbody></table>

			<h2 class="title">3 · Send</h2>
			<p class="submit">
				<button type="submit" name="tsa_do" value="preview" class="button">Preview recipients</button>
				<button type="submit" name="tsa_do" value="test" class="button">Send test to me</button>
				<button type="submit" name="tsa_do" value="send" class="button button-primary" onclick="return confirm('Send this email to everyone in the selected audience?');">Send campaign</button>
			</p>
		</form>

		<?php
		// Recent campaigns + progress.
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
					$state = get_post_meta( $c->ID, '_state', true ) ?: 'done';
					?>
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
			<?php if ( array_filter( $recent, function ( $c ) { return get_post_meta( $c->ID, '_state', true ) === 'sending'; } ) ) : ?>
				<p class="description">A send is in progress — reload this page to refresh progress.</p>
			<?php endif; ?>
		<?php endif; ?>

		<?php if ( $suppressed ) : ?>
			<h2 class="title">Unsubscribed (<?php echo count( $suppressed ); ?>)</h2>
			<p class="description" style="max-width:820px">These addresses have opted out and are skipped by every send: <?php echo esc_html( implode( ', ', array_slice( $suppressed, 0, 50 ) ) ); ?><?php echo count( $suppressed ) > 50 ? '…' : ''; ?></p>
		<?php endif; ?>
	</div>
	<?php
}
