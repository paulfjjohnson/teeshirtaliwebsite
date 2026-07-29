<?php
/**
 * TSA Wholesale Orders — track blanks ordered from wholesalers.
 *
 * A lightweight purchasing log: record each order you place with a wholesaler
 * (S&S, SanMar, …) for blank apparel — wholesaler, PO #, quantity, cost, a
 * short item summary, and the date ordered. Move it Ordered → Received when it
 * arrives. Each log entry can be linked to the WooCommerce customer order(s)
 * it's restocking for, and those orders show their linked wholesale orders on
 * the order screen — so you can see at a glance whether an order's blanks are
 * on the way.
 *
 * Self-contained: a `tsa_wholesale_order` CPT + one meta box + admin columns.
 */
defined( 'ABSPATH' ) || exit;

/** Wholesaler suggestions (filterable). */
function tsa_wholesalers() {
	return apply_filters( 'tsa_wholesalers', [ 'S&S Activewear', 'SanMar', 'Alphabroder', 'TSC Apparel', 'Carolina Made', 'Other' ] );
}

/** Statuses: ordered → received. */
function tsa_wo_statuses() {
	return [ 'ordered' => 'Ordered', 'received' => 'Received' ];
}

/* ─── CPT ──────────────────────────────────────────────────────────── */
add_action( 'init', function () {
	register_post_type( 'tsa_wholesale_order', [
		'labels' => [
			'name'          => 'Wholesale Orders',
			'singular_name' => 'Wholesale Order',
			'menu_name'     => 'Wholesale Orders',
			'add_new_item'  => 'Log Wholesale Order',
			'edit_item'     => 'Wholesale Order',
			'search_items'  => 'Search Wholesale Orders',
			'not_found'     => 'No wholesale orders logged yet.',
		],
		'public'          => false,
		'show_ui'         => true,
		'show_in_menu'    => true,
		'menu_icon'       => 'dashicons-cart',
		'menu_position'   => 25,
		'capability_type' => 'post',
		'map_meta_cap'    => true,
		'supports'        => [ 'title' ],
		'show_in_rest'    => false,
	] );
} );

/* Auto-title hint (the title is generated from the details on save). */
add_filter( 'enter_title_here', function ( $text, $post ) {
	return ( $post && 'tsa_wholesale_order' === $post->post_type ) ? 'Reference (auto-filled from details below)' : $text;
}, 10, 2 );

/* ─── Command Center card ──────────────────────────────────────────── */
add_filter( 'tsa_admin_hub_tools', function ( $groups ) {
	$card = [ 'Wholesale Orders', 'Track blanks ordered from wholesalers', '📦', admin_url( 'edit.php?post_type=tsa_wholesale_order' ) ];
	if ( isset( $groups['Orders & Ops'] ) && is_array( $groups['Orders & Ops'] ) ) {
		$groups['Orders & Ops'][] = $card;
	} else {
		$groups['Orders & Ops'] = [ $card ];
	}
	return $groups;
} );

/* ─── Details meta box ─────────────────────────────────────────────── */
add_action( 'add_meta_boxes', function () {
	add_meta_box( 'tsa_wo_details', 'Wholesale Order Details', 'tsa_wo_details_box', 'tsa_wholesale_order', 'normal', 'high' );
} );

function tsa_wo_details_box( $post ) {
	wp_nonce_field( 'tsa_wo_save', 'tsa_wo_nonce' );
	$g = function ( $k, $d = '' ) use ( $post ) { return get_post_meta( $post->ID, $k, true ) ?: $d; };
	$wholesaler = $g( '_tsa_wo_wholesaler' );
	$status     = $g( '_tsa_wo_status', 'ordered' );
	$orders_csv = $g( '_tsa_wo_customer_orders' );
	$field_css  = 'width:100%;max-width:360px';
	?>
	<style>
		.tsa-wo-grid{display:grid;grid-template-columns:150px 1fr;gap:12px 16px;align-items:start;max-width:760px;margin-top:6px}
		.tsa-wo-grid label{font-weight:600;padding-top:6px}
		.tsa-wo-grid .desc{grid-column:2;color:#666;font-size:12px;margin:-6px 0 4px}
	</style>
	<div class="tsa-wo-grid">
		<label for="tsa_wo_wholesaler">Wholesaler</label>
		<div>
			<input type="text" list="tsa_wo_wholesalers" id="tsa_wo_wholesaler" name="tsa_wo_wholesaler" value="<?php echo esc_attr( $wholesaler ); ?>" style="<?php echo esc_attr( $field_css ); ?>">
			<datalist id="tsa_wo_wholesalers"><?php foreach ( tsa_wholesalers() as $w ) echo '<option value="' . esc_attr( $w ) . '">'; ?></datalist>
		</div>

		<label for="tsa_wo_po">PO / Reference #</label>
		<input type="text" id="tsa_wo_po" name="tsa_wo_po" value="<?php echo esc_attr( $g( '_tsa_wo_po_number' ) ); ?>" style="<?php echo esc_attr( $field_css ); ?>">

		<label for="tsa_wo_status">Status</label>
		<select id="tsa_wo_status" name="tsa_wo_status">
			<?php foreach ( tsa_wo_statuses() as $sv => $sl ) : ?>
				<option value="<?php echo esc_attr( $sv ); ?>" <?php selected( $status, $sv ); ?>><?php echo esc_html( $sl ); ?></option>
			<?php endforeach; ?>
		</select>

		<label for="tsa_wo_date_ordered">Date ordered</label>
		<input type="date" id="tsa_wo_date_ordered" name="tsa_wo_date_ordered" value="<?php echo esc_attr( $g( '_tsa_wo_date_ordered' ) ); ?>">

		<label for="tsa_wo_date_received">Date received</label>
		<div>
			<input type="date" id="tsa_wo_date_received" name="tsa_wo_date_received" value="<?php echo esc_attr( $g( '_tsa_wo_date_received' ) ); ?>">
			<span class="desc" style="display:block">Auto-filled with today when you set status to <em>Received</em> and leave this blank.</span>
		</div>

		<label for="tsa_wo_qty">Total quantity</label>
		<input type="number" min="0" step="1" id="tsa_wo_qty" name="tsa_wo_qty" value="<?php echo esc_attr( $g( '_tsa_wo_qty' ) ); ?>" style="width:140px">

		<label for="tsa_wo_cost">Total cost ($)</label>
		<input type="number" min="0" step="0.01" id="tsa_wo_cost" name="tsa_wo_cost" value="<?php echo esc_attr( $g( '_tsa_wo_cost' ) ); ?>" style="width:140px">

		<label for="tsa_wo_tracking">Tracking #</label>
		<input type="text" id="tsa_wo_tracking" name="tsa_wo_tracking" value="<?php echo esc_attr( $g( '_tsa_wo_tracking' ) ); ?>" style="<?php echo esc_attr( $field_css ); ?>">

		<label for="tsa_wo_summary">Items (summary)</label>
		<textarea id="tsa_wo_summary" name="tsa_wo_summary" rows="3" style="<?php echo esc_attr( $field_css ); ?>;max-width:560px" placeholder="e.g. 24× Gildan 5000 Black (S/M/L/XL), 12× Bella 3001 White"><?php echo esc_textarea( $g( '_tsa_wo_summary' ) ); ?></textarea>

		<label for="tsa_wo_customer_orders">Customer order #s</label>
		<div>
			<input type="text" id="tsa_wo_customer_orders" name="tsa_wo_customer_orders" value="<?php echo esc_attr( $orders_csv ); ?>" style="<?php echo esc_attr( $field_css ); ?>" placeholder="e.g. 1042, 1050">
			<span class="desc" style="display:block">Optional. Comma-separated WooCommerce order numbers this restock is for.</span>
			<?php
			$links = tsa_wo_order_links( $orders_csv );
			if ( $links ) { echo '<p style="margin:6px 0 0">Linked: ' . wp_kses_post( implode( ' · ', $links ) ) . '</p>'; }
			?>
		</div>
	</div>
	<?php
}

/* ─── Save ─────────────────────────────────────────────────────────── */
add_action( 'save_post_tsa_wholesale_order', 'tsa_wo_save', 10, 2 );
function tsa_wo_save( $post_id, $post ) {
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) { return; }
	if ( ! isset( $_POST['tsa_wo_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_wo_nonce'] ) ), 'tsa_wo_save' ) ) { return; }
	if ( ! current_user_can( 'edit_post', $post_id ) ) { return; }

	$wholesaler = sanitize_text_field( wp_unslash( $_POST['tsa_wo_wholesaler'] ?? '' ) );
	$po         = sanitize_text_field( wp_unslash( $_POST['tsa_wo_po'] ?? '' ) );
	$status     = array_key_exists( sanitize_key( $_POST['tsa_wo_status'] ?? '' ), tsa_wo_statuses() ) ? sanitize_key( $_POST['tsa_wo_status'] ) : 'ordered';
	$ordered    = sanitize_text_field( wp_unslash( $_POST['tsa_wo_date_ordered'] ?? '' ) );
	$received   = sanitize_text_field( wp_unslash( $_POST['tsa_wo_date_received'] ?? '' ) );

	// Auto-fill received date when marked received and left blank.
	if ( 'received' === $status && '' === $received ) { $received = current_time( 'Y-m-d' ); }

	update_post_meta( $post_id, '_tsa_wo_wholesaler',   $wholesaler );
	update_post_meta( $post_id, '_tsa_wo_po_number',    $po );
	update_post_meta( $post_id, '_tsa_wo_status',       $status );
	update_post_meta( $post_id, '_tsa_wo_date_ordered', $ordered );
	update_post_meta( $post_id, '_tsa_wo_date_received', $received );
	update_post_meta( $post_id, '_tsa_wo_qty',          absint( $_POST['tsa_wo_qty'] ?? 0 ) );
	update_post_meta( $post_id, '_tsa_wo_cost',         (float) ( $_POST['tsa_wo_cost'] ?? 0 ) );
	update_post_meta( $post_id, '_tsa_wo_tracking',     sanitize_text_field( wp_unslash( $_POST['tsa_wo_tracking'] ?? '' ) ) );
	update_post_meta( $post_id, '_tsa_wo_summary',      sanitize_textarea_field( wp_unslash( $_POST['tsa_wo_summary'] ?? '' ) ) );

	// Customer order links: keep a display CSV + one exact-match meta row per id
	// (so the reverse lookup on the order screen is precise).
	$raw = (string) wp_unslash( $_POST['tsa_wo_customer_orders'] ?? '' );
	$ids = array_values( array_unique( array_filter( array_map( 'absint', preg_split( '/[\s,;]+/', $raw ) ) ) ) );
	update_post_meta( $post_id, '_tsa_wo_customer_orders', implode( ', ', $ids ) );
	delete_post_meta( $post_id, '_tsa_wo_order' );
	foreach ( $ids as $oid ) { add_post_meta( $post_id, '_tsa_wo_order', $oid ); }

	// Generate a tidy title from the details (avoid recursion).
	$bits  = array_filter( [ $wholesaler ?: 'Wholesale order', $po ? 'PO ' . $po : '', $ordered ] );
	$title = implode( ' · ', $bits );
	remove_action( 'save_post_tsa_wholesale_order', 'tsa_wo_save', 10 );
	wp_update_post( [ 'ID' => $post_id, 'post_title' => $title ] );
	add_action( 'save_post_tsa_wholesale_order', 'tsa_wo_save', 10, 2 );
}

/* ─── Helpers ──────────────────────────────────────────────────────── */
/** Admin edit URL for a WooCommerce order id (HPOS-aware). */
function tsa_wo_order_edit_url( $order_id ) {
	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
		&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . (int) $order_id );
	}
	return admin_url( 'post.php?post=' . (int) $order_id . '&action=edit' );
}

/** Turn a CSV of order ids into a list of linked <a> tags (valid orders only). */
function tsa_wo_order_links( $csv ) {
	$out = [];
	foreach ( array_filter( array_map( 'absint', preg_split( '/[\s,;]+/', (string) $csv ) ) ) as $oid ) {
		$label = '#' . $oid;
		if ( function_exists( 'wc_get_order' ) && wc_get_order( $oid ) ) {
			$out[] = '<a href="' . esc_url( tsa_wo_order_edit_url( $oid ) ) . '">' . esc_html( $label ) . '</a>';
		} else {
			$out[] = esc_html( $label );
		}
	}
	return $out;
}

/* ─── Admin list columns ───────────────────────────────────────────── */
add_filter( 'manage_tsa_wholesale_order_posts_columns', function ( $cols ) {
	return [
		'cb'         => $cols['cb'] ?? '',
		'title'      => 'Reference',
		'wo_seller'  => 'Wholesaler',
		'wo_qty'     => 'Qty',
		'wo_cost'    => 'Cost',
		'wo_status'  => 'Status',
		'wo_ordered' => 'Ordered',
		'wo_recd'    => 'Received',
		'wo_orders'  => 'Customer orders',
	];
} );

add_action( 'manage_tsa_wholesale_order_posts_custom_column', function ( $col, $post_id ) {
	switch ( $col ) {
		case 'wo_seller':
			echo esc_html( get_post_meta( $post_id, '_tsa_wo_wholesaler', true ) ?: '—' );
			break;
		case 'wo_qty':
			echo esc_html( (string) ( (int) get_post_meta( $post_id, '_tsa_wo_qty', true ) ?: '—' ) );
			break;
		case 'wo_cost':
			$c = (float) get_post_meta( $post_id, '_tsa_wo_cost', true );
			echo $c ? esc_html( ( function_exists( 'wc_price' ) ? wp_strip_all_tags( wc_price( $c ) ) : '$' . number_format( $c, 2 ) ) ) : '—';
			break;
		case 'wo_status':
			$s   = get_post_meta( $post_id, '_tsa_wo_status', true ) ?: 'ordered';
			$map = [ 'ordered' => [ 'Ordered', '#b26a00' ], 'received' => [ 'Received', '#1a7f37' ] ];
			$m   = $map[ $s ] ?? $map['ordered'];
			echo '<strong style="color:' . esc_attr( $m[1] ) . '">' . esc_html( $m[0] ) . '</strong>';
			break;
		case 'wo_ordered':
			echo esc_html( get_post_meta( $post_id, '_tsa_wo_date_ordered', true ) ?: '—' );
			break;
		case 'wo_recd':
			echo esc_html( get_post_meta( $post_id, '_tsa_wo_date_received', true ) ?: '—' );
			break;
		case 'wo_orders':
			$links = tsa_wo_order_links( get_post_meta( $post_id, '_tsa_wo_customer_orders', true ) );
			echo $links ? wp_kses_post( implode( ', ', $links ) ) : '—';
			break;
	}
}, 10, 2 );

/* ─── Reverse view: on a WooCommerce order, show linked wholesale orders ── */
add_action( 'add_meta_boxes', function () {
	$screens = [ 'shop_order' ]; // legacy
	if ( class_exists( '\Automattic\WooCommerce\Internal\Admin\Orders\PageController' ) ) {
		$screens[] = wc_get_page_screen_id( 'shop-order' ); // HPOS
	}
	foreach ( array_filter( $screens ) as $screen ) {
		add_meta_box( 'tsa_wo_for_order', 'Wholesale Orders (blanks)', 'tsa_wo_order_side_box', $screen, 'side', 'default' );
	}
} );

function tsa_wo_order_side_box( $post_or_order ) {
	$order_id = is_a( $post_or_order, 'WP_Post' ) ? (int) $post_or_order->ID : (int) $post_or_order->get_id();
	$linked   = get_posts( [
		'post_type'   => 'tsa_wholesale_order',
		'post_status' => 'any',
		'numberposts' => -1,
		'meta_query'  => [ [ 'key' => '_tsa_wo_order', 'value' => $order_id ] ],
	] );
	if ( ! $linked ) {
		echo '<p style="color:#777;margin:0">No wholesale order logged for this order\'s blanks yet.</p>';
		echo '<p style="margin:8px 0 0"><a class="button button-small" href="' . esc_url( admin_url( 'post-new.php?post_type=tsa_wholesale_order' ) ) . '">Log wholesale order</a></p>';
		return;
	}
	echo '<ul style="margin:0">';
	foreach ( $linked as $wo ) {
		$s   = get_post_meta( $wo->ID, '_tsa_wo_status', true ) ?: 'ordered';
		$col = 'received' === $s ? '#1a7f37' : '#b26a00';
		echo '<li style="margin:0 0 8px">'
			. '<a href="' . esc_url( get_edit_post_link( $wo->ID ) ) . '">' . esc_html( get_the_title( $wo ) ) . '</a>'
			. ' — <strong style="color:' . esc_attr( $col ) . '">' . esc_html( ucfirst( $s ) ) . '</strong>'
			. '</li>';
	}
	echo '</ul>';
}
