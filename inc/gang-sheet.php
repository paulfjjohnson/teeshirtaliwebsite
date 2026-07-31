<?php
/**
 * TSA Gang Sheet Builder — server pipeline.
 *
 * Turns the front-end builder from a pricing visualizer into a real production
 * tool by capturing the customer's ARTWORK + LAYOUT onto the order:
 *
 *   1. Each PNG uploads as it's added (wp_ajax tsa_gsb_upload) into
 *      /uploads/gang-sheets/YYYY/MM/, returning an HMAC-signed token.
 *   2. Add-to-cart (tsa_gsb_add_to_cart) sends the tokens + a layout JSON
 *      (per-design size/qty/DPI + placements). The server verifies each token,
 *      resolves it to the stored file, and stashes files+layout on the cart line.
 *   3. On checkout the files, layout, and a readable summary persist to the
 *      order line item; the admin order screen shows thumbnails + download links
 *      so production has everything it needs.
 *
 * Price is still computed server-side from the page's width config (length ×
 * per-inch) — never trusted from the client. Supersedes the old inline handler
 * in functions.php; tsa_gsb_widths() still lives there.
 */
defined( 'ABSPATH' ) || exit;

const TSA_GSB_SUBDIR    = '/gang-sheets';
const TSA_GSB_MAX_BYTES = 52428800; // 50 MB per file
const TSA_GSB_MAX_FILES = 200;      // sanity caps
const TSA_GSB_MAX_PLACE = 1000;

/* Route gang-sheet uploads under /uploads/gang-sheets/YYYY/MM. */
function tsa_gsb_updir( $dirs ) {
	$dirs['subdir'] = TSA_GSB_SUBDIR . $dirs['subdir'];
	$dirs['path']   = $dirs['basedir'] . $dirs['subdir'];
	$dirs['url']    = $dirs['baseurl'] . $dirs['subdir'];
	return $dirs;
}

/* Base dir/url of the gang-sheets store (unfiltered uploads root + subdir). */
function tsa_gsb_store() {
	$u = wp_get_upload_dir();
	return [ 'dir' => $u['basedir'] . TSA_GSB_SUBDIR, 'url' => $u['baseurl'] . TSA_GSB_SUBDIR ];
}

/* Sign/verify a stored file's relative path so the client can't forge one. */
function tsa_gsb_sign( $rel ) { return hash_hmac( 'sha256', $rel, wp_salt( 'auth' ) ); }
function tsa_gsb_token( $rel ) { return $rel . '|' . tsa_gsb_sign( $rel ); }
/** Verify a token → [ rel, path, url ] or null. */
function tsa_gsb_resolve_token( $token ) {
	$token = (string) $token;
	$pos   = strrpos( $token, '|' );
	if ( false === $pos ) { return null; }
	$rel = substr( $token, 0, $pos );
	$sig = substr( $token, $pos + 1 );
	// Only allow safe relative paths (no traversal).
	if ( $rel === '' || preg_match( '#(^/|\.\.|[^A-Za-z0-9._/\-])#', $rel ) ) { return null; }
	if ( ! hash_equals( tsa_gsb_sign( $rel ), (string) $sig ) ) { return null; }
	$store = tsa_gsb_store();
	$path  = $store['dir'] . '/' . $rel;
	if ( ! is_file( $path ) ) { return null; }
	return [ 'rel' => $rel, 'path' => $path, 'url' => $store['url'] . '/' . $rel ];
}

/* ─── AJAX: upload one PNG → signed token + url ─────────────────────── */
add_action( 'wp_ajax_tsa_gsb_upload',        'tsa_gsb_upload' );
add_action( 'wp_ajax_nopriv_tsa_gsb_upload', 'tsa_gsb_upload' );
function tsa_gsb_upload() {
	if ( ! check_ajax_referer( 'tsa_gsb_nonce', 'nonce', false ) ) { wp_send_json_error( 'Invalid nonce.' ); }
	if ( function_exists( 'tsa_feature_active' ) && ! tsa_feature_active( 'gang_sheet' ) ) { wp_send_json_error( 'Gang sheet builder is not available.' ); }
	if ( empty( $_FILES['file'] ) || empty( $_FILES['file']['name'] ) ) { wp_send_json_error( 'No file received.' ); }

	$f = $_FILES['file'];
	if ( (int) $f['error'] !== UPLOAD_ERR_OK ) { wp_send_json_error( 'Your file could not be uploaded — please try again.' ); }
	if ( (int) $f['size'] > TSA_GSB_MAX_BYTES ) { wp_send_json_error( 'File is over 50 MB — please export a smaller PNG.' ); }

	require_once ABSPATH . 'wp-admin/includes/file.php';
	$overrides = [ 'test_form' => false, 'mimes' => [ 'png' => 'image/png' ] ];
	add_filter( 'upload_dir', 'tsa_gsb_updir' );
	$up = wp_handle_upload( $f, $overrides );
	remove_filter( 'upload_dir', 'tsa_gsb_updir' );

	if ( ! $up || ! empty( $up['error'] ) ) { wp_send_json_error( $up['error'] ?? 'Only PNG files are accepted.' ); }

	$store = tsa_gsb_store();
	$rel   = ltrim( str_replace( $store['dir'], '', $up['file'] ), '/' );
	wp_send_json_success( [ 'token' => tsa_gsb_token( $rel ), 'url' => $up['url'] ] );
}

/* ─── Sanitize the layout JSON the client posts ────────────────────── */
function tsa_gsb_clean_layout( $raw ) {
	$in = json_decode( (string) wp_unslash( $raw ), true );
	if ( ! is_array( $in ) ) { return [ 'items' => [], 'placements' => [] ]; }
	$items = [];
	foreach ( array_slice( (array) ( $in['items'] ?? [] ), 0, TSA_GSB_MAX_FILES ) as $it ) {
		$items[] = [
			'name' => sanitize_text_field( (string) ( $it['name'] ?? '' ) ),
			'w'    => round( (float) ( $it['inW'] ?? $it['w'] ?? 0 ), 2 ),
			'h'    => round( (float) ( $it['inH'] ?? $it['h'] ?? 0 ), 2 ),
			'qty'  => max( 1, absint( $it['qty'] ?? 1 ) ),
			'dpi'  => absint( $it['dpi'] ?? 0 ),
		];
	}
	$place = [];
	foreach ( array_slice( (array) ( $in['placements'] ?? [] ), 0, TSA_GSB_MAX_PLACE ) as $p ) {
		$place[] = [
			'name' => sanitize_text_field( (string) ( $p['name'] ?? '' ) ),
			'x'    => round( (float) ( $p['x'] ?? 0 ), 2 ),
			'y'    => round( (float) ( $p['y'] ?? 0 ), 2 ),
			'w'    => round( (float) ( $p['w'] ?? 0 ), 2 ),
			'h'    => round( (float) ( $p['h'] ?? 0 ), 2 ),
			'r'    => ! empty( $p['rotated'] ) ? 1 : 0,
		];
	}
	return [ 'items' => $items, 'placements' => $place ];
}

/* ─── AJAX: add the built sheet (files + layout) to the cart ───────── */
add_action( 'wp_ajax_tsa_gsb_add_to_cart',        'tsa_gsb_add_to_cart' );
add_action( 'wp_ajax_nopriv_tsa_gsb_add_to_cart', 'tsa_gsb_add_to_cart' );
function tsa_gsb_add_to_cart() {
	if ( ! check_ajax_referer( 'tsa_gsb_nonce', 'nonce', false ) ) { wp_send_json_error( 'Invalid nonce.' ); }
	if ( function_exists( 'tsa_feature_active' ) && ! tsa_feature_active( 'gang_sheet' ) ) { wp_send_json_error( 'Gang sheet builder is not available.' ); }

	$product_id = (int) ( $_POST['product_id'] ?? 0 );
	$page_id    = (int) ( $_POST['page_id'] ?? 0 );
	$width      = (float) ( $_POST['width'] ?? 0 );
	$length     = (int) ( $_POST['length'] ?? 0 );
	$quantity   = max( 1, (int) ( $_POST['quantity'] ?? 1 ) );

	if ( ! $product_id ) { wp_send_json_error( 'Product not configured.' ); }
	if ( $width <= 0 || $length <= 0 ) { wp_send_json_error( 'Invalid sheet size.' ); }

	// Price: server-side from the page's width config only.
	$rate = 0;
	if ( function_exists( 'tsa_gsb_widths' ) ) {
		foreach ( tsa_gsb_widths( $page_id ) as $cfg ) {
			if ( abs( $cfg['w'] - $width ) < 0.01 ) { $rate = $cfg['rate']; break; }
		}
	}
	if ( $rate <= 0 ) { wp_send_json_error( 'That sheet width is not available.' ); }
	$price = round( $length * $rate, 2 );

	// Resolve uploaded files from their signed tokens.
	$tokens = (array) ( $_POST['tokens'] ?? [] );
	$files  = [];
	foreach ( array_slice( $tokens, 0, TSA_GSB_MAX_FILES ) as $t ) {
		$r = tsa_gsb_resolve_token( sanitize_text_field( wp_unslash( $t ) ) );
		if ( $r ) { $files[] = [ 'name' => wp_basename( $r['rel'] ), 'url' => $r['url'] ]; }
	}
	if ( ! $files ) { wp_send_json_error( 'Your designs didn\'t finish uploading — please wait for uploads to complete, then try again.' ); }

	$layout = tsa_gsb_clean_layout( $_POST['layout'] ?? '' );
	$pieces = 0;
	foreach ( $layout['items'] as $it ) { $pieces += (int) $it['qty']; }
	if ( ! $pieces ) { $pieces = count( $files ); }

	$cart_item_data = [
		'tsa_gang_sheet_width'  => $width,
		'tsa_gang_sheet_length' => $length,
		'tsa_gsb_price'         => $price,
		'tsa_gsb_files'         => $files,
		'tsa_gsb_layout'        => $layout,
		'tsa_gsb_designs'       => count( $files ),
		'tsa_gsb_pieces'        => $pieces,
		'tsa_gsb_unique'        => md5( $width . '|' . $length . '|' . microtime( true ) ),
	];

	$added = WC()->cart->add_to_cart( $product_id, $quantity, 0, [], $cart_item_data );
	if ( $added ) {
		wp_send_json_success( [
			'message'   => 'Gang sheet added to cart.',
			'cart_link' => '<a href="' . esc_url( wc_get_cart_url() ) . '">View cart →</a>',
		] );
	}
	wp_send_json_error( 'Could not add to cart. Please try again.' );
}

/* ─── Cart line price ──────────────────────────────────────────────── */
add_action( 'woocommerce_before_calculate_totals', function ( $cart ) {
	if ( is_admin() && ! wp_doing_ajax() ) { return; }
	if ( ! ( $cart instanceof WC_Cart ) ) { return; }
	foreach ( $cart->get_cart() as $item ) {
		if ( ! empty( $item['tsa_gsb_price'] ) && ! empty( $item['data'] ) ) {
			$item['data']->set_price( (float) $item['tsa_gsb_price'] );
		}
	}
}, 20 );

/* ─── Cart/checkout display ────────────────────────────────────────── */
add_filter( 'woocommerce_get_item_data', function ( $data, $item ) {
	if ( ! empty( $item['tsa_gang_sheet_width'] ) && ! empty( $item['tsa_gang_sheet_length'] ) ) {
		$data[] = [ 'name' => 'Gang sheet', 'value' => $item['tsa_gang_sheet_width'] . '″ × ' . $item['tsa_gang_sheet_length'] . '″' ];
	}
	if ( ! empty( $item['tsa_gsb_designs'] ) ) {
		$data[] = [ 'name' => 'Artwork', 'value' => (int) $item['tsa_gsb_designs'] . ' design' . ( (int) $item['tsa_gsb_designs'] === 1 ? '' : 's' ) . ', ' . (int) $item['tsa_gsb_pieces'] . ' piece' . ( (int) $item['tsa_gsb_pieces'] === 1 ? '' : 's' ) ];
	}
	return $data;
}, 10, 2 );

/* ─── Persist artwork + layout onto the order line ─────────────────── */
add_action( 'woocommerce_checkout_create_order_line_item', function ( $line, $key, $values ) {
	if ( empty( $values['tsa_gang_sheet_width'] ) ) { return; }
	$line->add_meta_data( 'Gang Sheet Size', $values['tsa_gang_sheet_width'] . '″ × ' . ( $values['tsa_gang_sheet_length'] ?? '' ) . '″', true );
	if ( ! empty( $values['tsa_gsb_designs'] ) ) {
		$line->add_meta_data( 'Artwork', (int) $values['tsa_gsb_designs'] . ' designs, ' . (int) $values['tsa_gsb_pieces'] . ' pieces', true );
	}
	if ( ! empty( $values['tsa_gsb_files'] ) ) {
		$line->add_meta_data( '_tsa_gsb_files', wp_json_encode( $values['tsa_gsb_files'] ), true );
	}
	if ( ! empty( $values['tsa_gsb_layout'] ) ) {
		$line->add_meta_data( '_tsa_gsb_layout', wp_json_encode( $values['tsa_gsb_layout'] ), true );
	}
}, 10, 3 );

/* ─── Admin order screen: show the production files + layout ────────── */
add_action( 'woocommerce_after_order_itemmeta', function ( $item_id, $item ) {
	if ( ! is_a( $item, 'WC_Order_Item_Product' ) ) { return; }
	$files = json_decode( (string) $item->get_meta( '_tsa_gsb_files' ), true );
	if ( ! is_array( $files ) || ! $files ) { return; }
	$layout = json_decode( (string) $item->get_meta( '_tsa_gsb_layout' ), true );

	echo '<div class="tsa-gsb-order" style="margin:8px 0 4px">';
	echo '<strong style="display:block;margin-bottom:6px">Gang sheet artwork (' . count( $files ) . ')</strong>';
	echo '<div style="display:flex;flex-wrap:wrap;gap:8px">';
	foreach ( $files as $f ) {
		$url  = esc_url( $f['url'] ?? '' );
		$name = esc_html( $f['name'] ?? 'file.png' );
		if ( ! $url ) { continue; }
		echo '<a href="' . $url . '" target="_blank" rel="noopener" download title="' . $name . '" '
			. 'style="display:block;width:64px;height:64px;border:1px solid #dcdcde;border-radius:6px;overflow:hidden;background:#fff">'
			. '<img src="' . $url . '" alt="" style="width:100%;height:100%;object-fit:contain" /></a>';
	}
	echo '</div>';
	if ( is_array( $layout ) && ! empty( $layout['items'] ) ) {
		$rows = [];
		foreach ( $layout['items'] as $it ) {
			$rows[] = esc_html( ( $it['name'] ?? '—' ) . ' — ' . ( $it['w'] ?? '?' ) . '″×' . ( $it['h'] ?? '?' ) . '″ ×' . ( $it['qty'] ?? 1 ) . ( ! empty( $it['dpi'] ) ? ' (' . (int) $it['dpi'] . ' DPI)' : '' ) );
		}
		echo '<div style="margin-top:6px;font:12px/1.6 monospace;color:#555">' . implode( '<br>', $rows ) . '</div>';
	}
	echo '</div>';
}, 10, 2 );
