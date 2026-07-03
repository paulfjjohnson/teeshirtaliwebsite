<?php
/**
 * TSA Requests — capture every site form submission as a record, surface them in
 * a dashboard "Requests" inbox + a native admin list, and fire a hook
 * (tsa_request_recorded) that the SMS-alerts module listens to.
 *
 * Forms still email as before; this records a copy so nothing is ever lost and
 * you have one place to see all incoming requests.
 *
 * Capture: call tsa_record_request([...]) from any form handler.
 */
defined( 'ABSPATH' ) || exit;

function tsa_request_type_labels() {
	return [
		'contact'    => 'Contact',
		'quote'      => 'Quote',
		'store'      => 'Store Request',
		'teeparty'   => 'Tee Party',
		'fundraiser' => 'Fundraiser',
		'design'     => 'Design',
		'general'    => 'Request',
	];
}

/* ── CPT ─────────────────────────────────────────────────────────── */
add_action( 'init', function () {
	register_post_type( 'tsa_request', [
		'labels'          => [
			'name'          => 'Requests',
			'singular_name' => 'Request',
			'menu_name'     => 'Requests',
			'all_items'     => 'All Requests',
			'edit_item'     => 'View Request',
			'search_items'  => 'Search Requests',
			'not_found'     => 'No requests yet.',
		],
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_icon'       => 'dashicons-email-alt',
		'menu_position'   => 26,
		'capability_type' => 'post',
		'map_meta_cap'    => true,
		'capabilities'    => [ 'create_posts' => 'do_not_allow' ], // no manual "Add New"
		'supports'        => [ 'title' ],
		'show_in_rest'    => false,
	] );
} );

/* ── Record a submission. Returns post ID or 0. ──────────────────── */
function tsa_record_request( $args ) {
	if ( ! post_type_exists( 'tsa_request' ) ) { return 0; }

	$args = wp_parse_args( $args, [
		'type'    => 'general',
		'name'    => '',
		'email'   => '',
		'phone'   => '',
		'org'        => '',
		'message'    => '',
		'source'     => '',
		'attachment' => '', // public URL of an uploaded file (artwork), if any
	] );
	if ( $args['source'] === '' ) {
		$args['source'] = wp_get_referer() ?: '';
	}

	$labels = tsa_request_type_labels();
	$tlabel = isset( $labels[ $args['type'] ] ) ? $labels[ $args['type'] ] : ucfirst( $args['type'] );
	$who    = $args['name'] ?: ( $args['email'] ?: 'Request' );

	$post_id = wp_insert_post( [
		'post_type'    => 'tsa_request',
		'post_status'  => 'publish',
		'post_title'   => $tlabel . ' — ' . $who,
		'post_content' => (string) $args['message'],
	], true );
	if ( is_wp_error( $post_id ) || ! $post_id ) { return 0; }

	update_post_meta( $post_id, '_tsa_req_type',   sanitize_key( $args['type'] ) );
	update_post_meta( $post_id, '_tsa_req_name',   sanitize_text_field( $args['name'] ) );
	update_post_meta( $post_id, '_tsa_req_email',  sanitize_email( $args['email'] ) );
	update_post_meta( $post_id, '_tsa_req_phone',  sanitize_text_field( $args['phone'] ) );
	update_post_meta( $post_id, '_tsa_req_org',    sanitize_text_field( $args['org'] ) );
	update_post_meta( $post_id, '_tsa_req_source', esc_url_raw( $args['source'] ) );
	update_post_meta( $post_id, '_tsa_req_status', 'new' );
	if ( ! empty( $args['attachment'] ) ) {
		update_post_meta( $post_id, '_tsa_req_file', esc_url_raw( $args['attachment'] ) );
	} elseif ( preg_match( '#URL:\s*(https?://\S+)#i', (string) $args['message'], $m ) ) {
		// Message already carries the uploaded file URL (e.g. quote form) — capture it.
		update_post_meta( $post_id, '_tsa_req_file', esc_url_raw( $m[1] ) );
	}

	/** Fires after a request is stored. SMS-alerts hooks this. */
	do_action( 'tsa_request_recorded', $post_id, $args );

	return $post_id;
}

/* One-time backfill: older requests (submitted before the attachment field
   existed) carry the upload URL only in their message body. Pull it into the
   structured _tsa_req_file field so they show in the File column + thumbnail. */
add_action( 'admin_init', function () {
	if ( get_option( 'tsa_req_file_backfilled_v1' ) ) return;
	if ( ! post_type_exists( 'tsa_request' ) ) return;
	foreach ( get_posts( [ 'post_type' => 'tsa_request', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids' ] ) as $rid ) {
		if ( get_post_meta( $rid, '_tsa_req_file', true ) ) continue;
		$content = (string) get_post_field( 'post_content', $rid );
		if ( preg_match( '#URL:\s*(https?://\S+)#i', $content, $m ) ) {
			update_post_meta( $rid, '_tsa_req_file', esc_url_raw( $m[1] ) );
		}
	}
	update_option( 'tsa_req_file_backfilled_v1', 1 );
} );

/* ── Counts ──────────────────────────────────────────────────────── */
function tsa_requests_unread_count() {
	$ids = get_posts( [
		'post_type'   => 'tsa_request',
		'post_status' => 'publish',
		'fields'      => 'ids',
		'numberposts' => -1,
		'meta_query'  => [ [ 'key' => '_tsa_req_status', 'value' => 'new' ] ],
	] );
	return count( $ids );
}

/* ── Admin list columns ──────────────────────────────────────────── */
add_filter( 'manage_tsa_request_posts_columns', function ( $cols ) {
	return [
		'cb'          => isset( $cols['cb'] ) ? $cols['cb'] : '',
		'title'       => 'Request',
		'tsa_type'    => 'Type',
		'tsa_contact' => 'Contact',
		'tsa_file'    => 'File',
		'tsa_status'  => 'Status',
		'date'        => 'Received',
	];
} );

add_action( 'manage_tsa_request_posts_custom_column', function ( $col, $post_id ) {
	if ( 'tsa_type' === $col ) {
		$labels = tsa_request_type_labels();
		$t      = get_post_meta( $post_id, '_tsa_req_type', true );
		echo esc_html( isset( $labels[ $t ] ) ? $labels[ $t ] : ucfirst( $t ) );
	} elseif ( 'tsa_contact' === $col ) {
		$e = get_post_meta( $post_id, '_tsa_req_email', true );
		$p = get_post_meta( $post_id, '_tsa_req_phone', true );
		echo $e ? '<a href="mailto:' . esc_attr( $e ) . '">' . esc_html( $e ) . '</a>' : '&mdash;';
		if ( $p ) { echo '<br><span style="color:#666">' . esc_html( $p ) . '</span>'; }
	} elseif ( 'tsa_file' === $col ) {
		$f = get_post_meta( $post_id, '_tsa_req_file', true );
		echo $f ? '<a href="' . esc_url( $f ) . '" target="_blank" rel="noopener" title="Open upload">&#128206; View</a>' : '&mdash;';
	} elseif ( 'tsa_status' === $col ) {
		$s   = get_post_meta( $post_id, '_tsa_req_status', true ) ?: 'new';
		$map = [ 'new' => [ 'New', '#b3261e' ], 'read' => [ 'Read', '#6d6268' ], 'done' => [ 'Done', '#1a7f37' ] ];
		$m   = isset( $map[ $s ] ) ? $map[ $s ] : $map['read'];
		echo '<strong style="color:' . esc_attr( $m[1] ) . '">' . esc_html( $m[0] ) . '</strong>';
	}
}, 10, 2 );

/* Mark a "new" request "read" when it's opened. */
add_action( 'load-post.php', function () {
	$pid = isset( $_GET['post'] ) ? (int) $_GET['post'] : 0;
	if ( $pid && get_post_type( $pid ) === 'tsa_request'
		&& get_post_meta( $pid, '_tsa_req_status', true ) === 'new' ) {
		update_post_meta( $pid, '_tsa_req_status', 'read' );
	}
} );

/* ── Detail meta box (read-only view + status control) ───────────── */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'tsa_req_detail', 'Request Details', 'tsa_request_detail_box', 'tsa_request', 'normal', 'high' );
} );

function tsa_request_detail_box( $post ) {
	wp_nonce_field( 'tsa_req_status_save', 'tsa_req_status_nonce' );
	$labels = tsa_request_type_labels();
	$type   = get_post_meta( $post->ID, '_tsa_req_type', true );
	$name   = get_post_meta( $post->ID, '_tsa_req_name', true );
	$email  = get_post_meta( $post->ID, '_tsa_req_email', true );
	$phone  = get_post_meta( $post->ID, '_tsa_req_phone', true );
	$org    = get_post_meta( $post->ID, '_tsa_req_org', true );
	$source = get_post_meta( $post->ID, '_tsa_req_source', true );
	$status = get_post_meta( $post->ID, '_tsa_req_status', true ) ?: 'new';
	$row    = function ( $label, $val ) {
		if ( $val === '' || $val === null ) { return; }
		echo '<tr><th style="text-align:left;width:120px;padding:6px 10px 6px 0;vertical-align:top;color:#555">' . esc_html( $label ) . '</th><td style="padding:6px 0">' . $val . '</td></tr>'; // phpcs:ignore
	};
	echo '<table style="width:100%;border-collapse:collapse;font-size:14px">';
	$row( 'Type', esc_html( isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( $type ) ) );
	$row( 'Name', esc_html( $name ) );
	$row( 'Email', $email ? '<a href="mailto:' . esc_attr( $email ) . '">' . esc_html( $email ) . '</a>' : '' );
	$row( 'Phone', $phone ? '<a href="tel:' . esc_attr( preg_replace( '/[^0-9+]/', '', $phone ) ) . '">' . esc_html( $phone ) . '</a>' : '' );
	$row( 'Group / Org', esc_html( $org ) );
	$row( 'Submitted from', $source ? '<a href="' . esc_url( $source ) . '" target="_blank" rel="noopener">' . esc_html( $source ) . '</a>' : '' );

	$file = get_post_meta( $post->ID, '_tsa_req_file', true );
	if ( $file ) {
		$fname = basename( (string) wp_parse_url( $file, PHP_URL_PATH ) );
		$link  = '<a href="' . esc_url( $file ) . '" target="_blank" rel="noopener" download>' . esc_html( $fname ) . ' &#8659;</a>';
		if ( preg_match( '/\.(png|jpe?g|gif|webp|svg)$/i', $fname ) ) {
			$link .= '<br><a href="' . esc_url( $file ) . '" target="_blank" rel="noopener"><img src="' . esc_url( $file ) . '" alt="" style="max-width:240px;max-height:240px;margin-top:8px;border:1px solid #ddd;border-radius:6px;background:#fff" /></a>';
		}
		$row( 'Attachment', $link );
	}
	echo '</table>';

	$msg = (string) $post->post_content;
	if ( trim( $msg ) !== '' ) {
		echo '<h4 style="margin:16px 0 6px">Message / Details</h4>';
		echo '<pre style="white-space:pre-wrap;word-break:break-word;background:#f6f7f7;border:1px solid #e0e0e0;border-radius:6px;padding:12px 14px;font:13px/1.5 ui-monospace,Menlo,Consolas,monospace;margin:0">' . esc_html( $msg ) . '</pre>';
	}

	echo '<p style="margin:16px 0 4px"><label style="font-weight:600;margin-right:8px">Status</label>';
	echo '<select name="tsa_req_status">';
	foreach ( [ 'new' => 'New', 'read' => 'Read', 'done' => 'Done' ] as $val => $lab ) {
		echo '<option value="' . esc_attr( $val ) . '" ' . selected( $status, $val, false ) . '>' . esc_html( $lab ) . '</option>';
	}
	echo '</select> <span style="color:#777;font-size:12px">— set to <em>Done</em> when handled.</span></p>';
}

add_action( 'save_post_tsa_request', function ( $post_id ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! isset( $_POST['tsa_req_status_nonce'] )
		|| ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_req_status_nonce'] ) ), 'tsa_req_status_save' ) ) {
		return;
	}
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }
	if ( isset( $_POST['tsa_req_status'] ) ) {
		$s = sanitize_key( wp_unslash( $_POST['tsa_req_status'] ) );
		if ( in_array( $s, [ 'new', 'read', 'done' ], true ) ) {
			update_post_meta( $post_id, '_tsa_req_status', $s );
		}
	}
} );

/* Unread badge on the Requests admin menu. */
add_action( 'admin_menu', function () {
	global $menu;
	$count = tsa_requests_unread_count();
	if ( ! $count || ! is_array( $menu ) ) { return; }
	foreach ( $menu as $k => $item ) {
		if ( isset( $item[2] ) && $item[2] === 'edit.php?post_type=tsa_request' ) {
			$menu[ $k ][0] .= ' <span class="awaiting-mod"><span class="pending-count">' . (int) $count . '</span></span>';
			break;
		}
	}
}, 99 );

/* ── Dashboard card (reuses existing .tsa-dash-* styles) ─────────── */
function tsa_requests_dashboard_card() {
	$recent = get_posts( [ 'post_type' => 'tsa_request', 'post_status' => 'publish', 'numberposts' => 8 ] );
	$unread = tsa_requests_unread_count();
	$labels = tsa_request_type_labels();

	ob_start();
	?>
	<div class="tsa-dash-card">
		<div class="tsa-dash-card__head">
			<span class="tsa-dash-card__title">Requests</span>
			<?php if ( $unread ) : ?><span class="tsa-dash-chip"><?php echo (int) $unread; ?> new</span><?php endif; ?>
		</div>
		<?php if ( ! $recent ) : ?>
			<p style="color:var(--tsa-muted,#6d6268);font-size:13px;margin:10px 0 0">No requests yet. Submissions from your Contact, Quote, Store, Tee Party &amp; Fundraiser forms will land here.</p>
		<?php else : ?>
			<div class="tsa-dash-queue">
				<?php
				foreach ( $recent as $r ) :
					$t     = get_post_meta( $r->ID, '_tsa_req_type', true );
					$st    = get_post_meta( $r->ID, '_tsa_req_status', true ) ?: 'new';
					$email = get_post_meta( $r->ID, '_tsa_req_email', true );
					$tlab  = isset( $labels[ $t ] ) ? $labels[ $t ] : ucfirst( $t );
					?>
				<a class="tsa-dash-qrow" href="<?php echo esc_url( admin_url( 'post.php?post=' . $r->ID . '&action=edit' ) ); ?>">
					<span><?php
						if ( 'new' === $st ) { echo '<span style="color:#b3261e">&#9679; </span>'; }
						echo esc_html( $tlab . ' — ' . ( $email ?: get_the_title( $r ) ) );
					?></span>
					<span style="color:var(--tsa-muted,#6d6268);font-size:12px;white-space:nowrap"><?php echo esc_html( human_time_diff( (int) get_post_time( 'U', true, $r ), (int) current_time( 'timestamp', true ) ) . ' ago' ); ?></span>
				</a>
				<?php endforeach; ?>
			</div>
			<a href="<?php echo esc_url( admin_url( 'edit.php?post_type=tsa_request' ) ); ?>" style="display:inline-block;margin-top:12px;font-size:13px;font-weight:700;color:var(--tsa-gold-dark,#a8772f);text-decoration:none">View all requests &rarr;</a>
		<?php endif; ?>
	</div>
	<?php
	return ob_get_clean();
}
