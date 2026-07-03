<?php
/**
 * Production order tickets — printable per-order job slips for the shop floor.
 *
 * Bulk action on the Orders list + a per-order button → a standalone, admin-only
 * print page that renders one ticket per order (page-break between), with each
 * item's design image, garment/color/sizes, placements, customer + fulfillment.
 * See docs/production-order-tickets-design.md.
 */
defined( 'ABSPATH' ) || exit;

/* ─── Data: assemble one order's ticket payload ─────────────────────── */
function tsa_order_ticket_data( WC_Order $order ): array {
	$norm  = static function ( $s ) { return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $s ) ); };
	$items = [];
	$units = 0;
	$party = '';

	foreach ( $order->get_items() as $item ) {
		$raw = $item->get_meta( '_ac_configurator' );
		$ac  = $raw ? json_decode( $raw, true ) : null;

		if ( ! is_array( $ac ) ) { // non-configurator line — still list it
			$q = (int) $item->get_quantity(); $units += $q;
			$items[] = [ 'name' => $item->get_name(), 'garment' => $item->get_name(), 'color' => '', 'sizes' => '', 'placements' => [], 'img' => '', 'units' => $q ];
			continue;
		}

		$gid     = absint( $ac['garment_id'] ?? 0 );
		$garment = $gid ? trim( (string) get_the_title( $gid ) ) : '';
		$brand   = $gid ? trim( (string) get_post_meta( $gid, '_ac_brand', true ) ) : '';
		if ( $brand !== '' && strpos( $norm( $garment ), $norm( $brand ) ) === false ) {
			$garment = trim( $brand . ' ' . $garment );
		}

		$sizes = []; $iu = 0;
		foreach ( (array) ( $ac['quantities'] ?? [] ) as $s => $q ) {
			$q = (int) $q; if ( $q < 1 ) continue;
			$sizes[] = sanitize_text_field( (string) $s ) . ' × ' . $q; $iu += $q;
		}
		$units += $iu;

		$placements = [];
		foreach ( (array) ( $ac['zones'] ?? [] ) as $zone ) {
			$did = absint( $ac['designs'][ $zone ] ?? 0 );
			$placements[] = [ 'zone' => ucwords( str_replace( '_', ' ', (string) $zone ) ), 'design' => $did ? get_the_title( $did ) : 'Custom artwork' ];
		}

		$img = '';
		foreach ( (array) ( $ac['designs'] ?? [] ) as $did ) {
			$did = absint( $did ); if ( ! $did ) continue;
			$img = get_post_meta( $did, '_design_preview_url', true ) ?: ( get_the_post_thumbnail_url( $did, 'medium' ) ?: '' );
			if ( $img ) break;
		}
		if ( ! $img && $gid ) $img = get_the_post_thumbnail_url( $gid, 'medium' ) ?: '';

		if ( ! $party && ! empty( $ac['party_id'] ) ) $party = get_the_title( absint( $ac['party_id'] ) ) ?: '';

		$items[] = [
			'name'       => $item->get_name(),
			'garment'    => $garment ?: $item->get_name(),
			'color'      => sanitize_text_field( $ac['color'] ?? '' ),
			'sizes'      => implode( ', ', $sizes ),
			'placements' => $placements,
			'img'        => $img,
			'units'      => $iu,
		];
	}

	// Fulfillment: pickup location vs shipping address (from the chosen shipping method).
	$ship_label = '';
	foreach ( $order->get_shipping_methods() as $m ) { $ship_label = $m->get_name(); break; }
	$is_pickup = $ship_label !== '' && stripos( $ship_label, 'pickup' ) !== false;
	$ship_addr = $order->get_formatted_shipping_address() ?: $order->get_formatted_billing_address();

	$store_slug = get_post_meta( $order->get_id(), '_ac_store_slug', true );
	$store_name = $store_slug ? ( function_exists( 'tsa_store_name' ) ? ( tsa_store_name( $store_slug ) ?: $store_slug ) : $store_slug ) : '';

	// Notes for the floor: the customer's checkout note + any manual order notes
	// staff added (skip WooCommerce's automated/system status notes).
	$customer_note = trim( (string) $order->get_customer_note() );
	$notes = [];
	if ( function_exists( 'wc_get_order_notes' ) ) {
		foreach ( wc_get_order_notes( [ 'order_id' => $order->get_id(), 'order_by' => 'date_created', 'order' => 'ASC' ] ) as $n ) {
			if ( ( $n->added_by ?? '' ) === 'system' ) continue; // skip automated status notes
			$txt = trim( wp_strip_all_tags( (string) $n->content ) );
			if ( $txt !== '' ) $notes[] = $txt;
		}
	}

	return [
		'number'        => $order->get_order_number(),
		'date'          => $order->get_date_created() ? wc_format_datetime( $order->get_date_created(), 'M j, Y' ) : '',
		'status'        => wc_get_order_status_name( $order->get_status() ),
		'customer'      => trim( $order->get_formatted_billing_full_name() ),
		'phone'         => $order->get_billing_phone(),
		'email'         => $order->get_billing_email(),
		'is_pickup'     => $is_pickup,
		'ship_label'    => $ship_label,
		'ship_addr'     => $ship_addr,
		'store'         => $store_name,
		'party'         => $party,
		'customer_note' => $customer_note,
		'notes'         => $notes,
		'items'         => $items,
		'units'         => $units,
	];
}

/* ─── Front-end picker: select orders by store/party/fundraiser/search and
   print production tickets, from the Command Center — no wp-admin needed.
   Reuses the same print page (admin_post_tsa_order_tickets). Owner-gated. ── */
function tsa_order_tickets_panel(): void {
	if ( ! function_exists( 'tsa_dash_can' ) || ! tsa_dash_can() ) return;
	if ( ! function_exists( 'wc_get_orders' ) ) return;

	$src    = isset( $_GET['tk_src'] )    ? sanitize_text_field( wp_unslash( $_GET['tk_src'] ) ) : '';
	$q      = isset( $_GET['tk_q'] )      ? trim( sanitize_text_field( wp_unslash( $_GET['tk_q'] ) ) ) : '';
	$status = isset( $_GET['tk_status'] ) ? sanitize_key( $_GET['tk_status'] ) : 'processing';
	$fulfil = isset( $_GET['tk_fulfil'] ) ? sanitize_key( $_GET['tk_fulfil'] ) : ''; // '' | pickup | shipped

	// Source dropdown options.
	$stores  = get_posts( [ 'post_type' => 'configurator_store', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] );
	$parties = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'meta_key' => '_wp_page_template', 'meta_value' => 'template-tee-party.php' ] );
	$funds   = post_type_exists( 'tsa_fundraiser' ) ? get_posts( [ 'post_type' => 'tsa_fundraiser', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) : [];

	// Fetch orders for the chosen status, then derive + filter in PHP (order
	// volume is small; store is order meta, party lives in item data).
	$statuses = $status === 'all' ? array_keys( wc_get_order_statuses() ) : [ $status ];
	$orders   = wc_get_orders( [ 'status' => $statuses, 'limit' => 400, 'orderby' => 'date', 'order' => 'DESC' ] );

	[ $s_type, $s_val ] = array_pad( explode( ':', $src, 2 ), 2, '' );
	$fund_store = ( $s_type === 'fund' && $s_val ) ? sanitize_title( get_post_meta( absint( $s_val ), '_tsa_fr_school', true ) ) : '';

	$rows = [];
	foreach ( $orders as $o ) {
		if ( ! $o instanceof WC_Order ) continue;
		$o_store  = sanitize_title( (string) $o->get_meta( '_ac_store_slug' ) );
		$o_parties = [];
		foreach ( $o->get_items() as $it ) {
			$ac = ( $raw = $it->get_meta( '_ac_configurator' ) ) ? json_decode( $raw, true ) : null;
			if ( is_array( $ac ) ) {
				if ( ! $o_store && ! empty( $ac['store_slug'] ) ) $o_store = sanitize_title( $ac['store_slug'] ); // store lives in item data
				if ( ! empty( $ac['party_id'] ) ) $o_parties[] = absint( $ac['party_id'] );
			}
		}
		// Also fall back to the product category store key (regular products).
		if ( ! $o_store && function_exists( 'tsa_product_store_key' ) ) {
			foreach ( $o->get_items() as $it ) {
				$k = tsa_product_store_key( (int) $it->get_product_id() );
				if ( $k ) { $o_store = $k; break; }
			}
		}
		$name = trim( $o->get_formatted_billing_full_name() );
		$num  = (string) $o->get_order_number();

		// Fulfillment: pickup vs shipped, from the chosen shipping method name.
		$o_fulfil = '';
		foreach ( $o->get_shipping_methods() as $m ) {
			$o_fulfil = ( stripos( $m->get_name(), 'pickup' ) !== false ) ? 'pickup' : 'shipped';
			break;
		}

		// Filters.
		if ( $s_type === 'store' && $o_store !== $s_val ) continue;
		if ( $s_type === 'party' && ! in_array( absint( $s_val ), $o_parties, true ) ) continue;
		if ( $s_type === 'fund'  && ( ! $fund_store || $o_store !== $fund_store ) ) continue;
		if ( $fulfil && $o_fulfil !== $fulfil ) continue;
		if ( $q !== '' && stripos( $num, $q ) === false && stripos( $name, $q ) === false ) continue;

		$rows[] = [
			'id'     => $o->get_id(),
			'num'    => $num,
			'name'   => $name ?: '(guest)',
			'store'  => $o_store ? ( function_exists( 'tsa_store_name' ) ? tsa_store_name( $o_store ) : $o_store ) : '',
			'party'  => $o_parties ? get_the_title( $o_parties[0] ) : '',
			'status' => wc_get_order_status_name( $o->get_status() ),
			'fulfil' => $o_fulfil,
			'date'   => $o->get_date_created() ? wc_format_datetime( $o->get_date_created(), 'M j' ) : '',
		];
		if ( count( $rows ) >= 200 ) break;
	}

	$nonce = wp_create_nonce( 'tsa_order_tickets' );
	$post  = esc_url( admin_url( 'admin-post.php' ) );
	?>
	<style>
	.tsa-tk{margin:0 0 30px;padding:20px 22px 22px;border-radius:16px;background:#fff;border:1px solid rgba(0,0,0,.12);color:#252124}
	.tsa-tk h2{font-size:20px;font-weight:800;margin:0 0 2px;color:#252124}
	.tsa-tk__sub{margin:0 0 14px;font-size:13px;color:#6d6268}
	.tsa-tk__filters{display:flex;gap:8px;flex-wrap:wrap;align-items:center;margin-bottom:14px}
	.tsa-tk__filters select,.tsa-tk__filters input[type=text]{padding:7px 10px;border-radius:8px;border:1px solid rgba(0,0,0,.22);background:#fff;color:#252124;font-size:13px}
	.tsa-tk__btn{padding:7px 14px;border-radius:8px;border:1px solid rgba(0,0,0,.15);background:#f3f0ec;color:#252124;font-size:13px;font-weight:700;cursor:pointer;text-decoration:none}
	.tsa-tk__btn--go{background:#d8a85f;color:#161616;border-color:#d8a85f}
	.tsa-tk__list{border:1px solid rgba(0,0,0,.12);border-radius:10px;max-height:420px;overflow:auto}
	.tsa-tk__row{display:grid;grid-template-columns:26px 78px 1fr 118px 84px 78px 50px;gap:10px;align-items:center;padding:9px 12px;border-bottom:1px solid rgba(0,0,0,.07);font-size:13px;color:#252124}
	.tsa-tk__row:last-child{border-bottom:0}
	.tsa-tk__row--head{position:sticky;top:0;background:#f3f0ec;font-size:11px;text-transform:uppercase;letter-spacing:.08em;color:#6d6268;font-weight:800}
	.tsa-tk__src{color:#6d6268;font-size:12px}
	.tsa-tk__bar{display:flex;align-items:center;justify-content:space-between;gap:10px;margin-top:14px;flex-wrap:wrap}
	.tsa-tk__count{font-size:12px;color:#6d6268}
	</style>
	<div class="tsa-tk" id="tsa-tk">
		<h2>Production Tickets</h2>
		<p class="tsa-tk__sub">Pick orders by store, tee party, fundraiser, order # or customer — then print their production tickets.</p>

		<form class="tsa-tk__filters" method="get" action="">
			<?php // preserve the current page path; filters are query args on the dashboard ?>
			<select name="tk_src">
				<option value="">— All sources —</option>
				<?php if ( $stores ) : ?><optgroup label="Stores">
					<?php foreach ( $stores as $st ) : $sl = sanitize_title( get_post_meta( $st->ID, '_ac_store_slug', true ) ); if ( ! $sl ) continue; ?>
					<option value="store:<?php echo esc_attr( $sl ); ?>" <?php selected( $src, 'store:' . $sl ); ?>><?php echo esc_html( $st->post_title ); ?></option>
					<?php endforeach; ?>
				</optgroup><?php endif; ?>
				<?php if ( $parties ) : ?><optgroup label="Tee Parties">
					<?php foreach ( $parties as $p ) : ?>
					<option value="party:<?php echo (int) $p->ID; ?>" <?php selected( $src, 'party:' . $p->ID ); ?>><?php echo esc_html( $p->post_title ); ?></option>
					<?php endforeach; ?>
				</optgroup><?php endif; ?>
				<?php if ( $funds ) : ?><optgroup label="Fundraisers">
					<?php foreach ( $funds as $f ) : ?>
					<option value="fund:<?php echo (int) $f->ID; ?>" <?php selected( $src, 'fund:' . $f->ID ); ?>><?php echo esc_html( $f->post_title ); ?></option>
					<?php endforeach; ?>
				</optgroup><?php endif; ?>
			</select>
			<input type="text" name="tk_q" value="<?php echo esc_attr( $q ); ?>" placeholder="Order # or customer name" />
			<select name="tk_status">
				<?php foreach ( [ 'processing' => 'Processing', 'all' => 'All statuses', 'completed' => 'Completed', 'on-hold' => 'On hold' ] as $sv => $sl ) : ?>
				<option value="<?php echo esc_attr( $sv ); ?>" <?php selected( $status, $sv ); ?>><?php echo esc_html( $sl ); ?></option>
				<?php endforeach; ?>
			</select>
			<select name="tk_fulfil">
				<?php foreach ( [ '' => 'Ship + Pickup', 'pickup' => 'Pickup only', 'shipped' => 'Shipped only' ] as $fv => $fl ) : ?>
				<option value="<?php echo esc_attr( $fv ); ?>" <?php selected( $fulfil, $fv ); ?>><?php echo esc_html( $fl ); ?></option>
				<?php endforeach; ?>
			</select>
			<button type="submit" class="tsa-tk__btn tsa-tk__btn--go">Filter</button>
		</form>

		<div class="tsa-tk__list" id="tsa-tk-list">
			<div class="tsa-tk__row tsa-tk__row--head">
				<span><input type="checkbox" id="tsa-tk-all"></span><span>Order</span><span>Customer</span><span>Source</span><span>Status</span><span>Ship</span><span>Date</span>
			</div>
			<?php if ( ! $rows ) : ?>
				<div class="tsa-tk__row"><span></span><span colspan="5" style="grid-column:2/7;color:var(--tsa-muted,#8a8a8a)">No matching orders.</span></div>
			<?php else : foreach ( $rows as $r ) : ?>
			<label class="tsa-tk__row">
				<span><input type="checkbox" class="tsa-tk-cb" value="<?php echo (int) $r['id']; ?>"></span>
				<span>#<?php echo esc_html( $r['num'] ); ?></span>
				<span><?php echo esc_html( $r['name'] ); ?></span>
				<span class="tsa-tk__src"><?php echo esc_html( $r['party'] ?: $r['store'] ?: '—' ); ?></span>
				<span><?php echo esc_html( $r['status'] ); ?></span>
				<span><?php echo $r['fulfil'] === 'pickup' ? '🏪 Pickup' : ( $r['fulfil'] === 'shipped' ? '📦 Ship' : '—' ); ?></span>
				<span><?php echo esc_html( $r['date'] ); ?></span>
			</label>
			<?php endforeach; endif; ?>
		</div>

		<div class="tsa-tk__bar">
			<span class="tsa-tk__count"><?php echo count( $rows ); ?> order<?php echo count( $rows ) === 1 ? '' : 's'; ?> shown · <span id="tsa-tk-sel">0</span> selected</span>
			<button type="button" class="tsa-tk__btn tsa-tk__btn--go" id="tsa-tk-print">🖨 Print production tickets</button>
		</div>
	</div>
	<script>
	(function(){
		// Filtering reloads the page (GET form) — scroll the panel back into view
		// so you land on your results instead of the top/bottom of the page.
		try {
			var _p = new URLSearchParams(location.search);
			if ( _p.has('tk_status') || _p.has('tk_src') || _p.has('tk_q') || _p.has('tk_fulfil') ) {
				var _el = document.getElementById('tsa-tk');
				if ( _el ) _el.scrollIntoView({ block: 'start' });
			}
		} catch(e) {}
		var box=document.getElementById('tsa-tk-list'); if(!box) return;
		var all=document.getElementById('tsa-tk-all'), sel=document.getElementById('tsa-tk-sel');
		function cbs(){ return [].slice.call(box.querySelectorAll('.tsa-tk-cb')); }
		function count(){ sel.textContent = cbs().filter(function(c){return c.checked;}).length; }
		if(all) all.addEventListener('change',function(){ cbs().forEach(function(c){c.checked=all.checked;}); count(); });
		box.addEventListener('change',count);
		document.getElementById('tsa-tk-print').addEventListener('click',function(){
			var ids=cbs().filter(function(c){return c.checked;}).map(function(c){return c.value;});
			if(!ids.length){ alert('Select at least one order.'); return; }
			var url=<?php echo wp_json_encode( $post ); ?>+'?action=tsa_order_tickets&orders='+ids.join(',')+'&_wpnonce='+<?php echo wp_json_encode( $nonce ); ?>;
			window.open(url,'_blank','noopener');
		});
	})();
	</script>
	<?php
}

/* ─── Triggers: bulk action (classic + HPOS) + per-order button ─────── */
add_action( 'admin_init', 'tsa_order_tickets_hooks' );
function tsa_order_tickets_hooks(): void {
	foreach ( [ 'edit-shop_order', 'woocommerce_page_wc-orders' ] as $screen ) {
		add_filter( "bulk_actions-$screen", function ( $actions ) {
			$actions['tsa_print_tickets'] = __( 'Print production tickets', 'tsa-child' );
			return $actions;
		} );
		add_filter( "handle_bulk_actions-$screen", 'tsa_handle_print_tickets_bulk', 10, 3 );
	}
}
/** Nonce'd print-page URL with RAW ampersands — safe for both an HTTP redirect
 *  (bulk action) and, via esc_url, an HTML link (per-order button). Do NOT use
 *  wp_nonce_url here: it esc_html-encodes & → &amp;, which a redirect won't decode,
 *  dropping _wpnonce and causing "the link you followed has expired". */
function tsa_order_tickets_url( array $ids ): string {
	return add_query_arg( [
		'action'   => 'tsa_order_tickets',
		'orders'   => implode( ',', array_map( 'absint', $ids ) ),
		'_wpnonce' => wp_create_nonce( 'tsa_order_tickets' ),
	], admin_url( 'admin-post.php' ) );
}
/** Bulk handler → redirect to the print page for the selected orders. */
function tsa_handle_print_tickets_bulk( $redirect, $action, $ids ) {
	if ( $action !== 'tsa_print_tickets' || empty( $ids ) ) return $redirect;
	return tsa_order_tickets_url( $ids );
}
/** Per-order "Print production ticket" button on the order edit screen. */
add_action( 'woocommerce_admin_order_data_after_order_details', 'tsa_order_ticket_button' );
function tsa_order_ticket_button( $order ): void {
	if ( ! $order instanceof WC_Order ) return;
	$url = tsa_order_tickets_url( [ $order->get_id() ] );
	echo '<p class="form-field form-field-wide" style="margin-top:10px"><a href="' . esc_url( $url ) . '" target="_blank" rel="noopener" class="button"><span class="dashicons dashicons-printer" style="vertical-align:-4px"></span> ' . esc_html__( 'Print production ticket', 'tsa-child' ) . '</a></p>';
}

/* ─── Render: standalone print page, one ticket per order ───────────── */
add_action( 'admin_post_tsa_order_tickets', 'tsa_render_order_tickets' );
function tsa_render_order_tickets(): void {
	if ( ! current_user_can( 'manage_woocommerce' ) && ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
	check_admin_referer( 'tsa_order_tickets' );

	$ids = array_filter( array_map( 'absint', explode( ',', (string) ( $_GET['orders'] ?? '' ) ) ) );
	if ( ! $ids ) wp_die( 'No orders selected.' );

	$back = admin_url( 'edit.php?post_type=shop_order' );
	nocache_headers();
	header( 'Content-Type: text/html; charset=utf-8' );
	?>
<!doctype html>
<html lang="en"><head>
<meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1">
<title>Production tickets</title>
<style>
*{box-sizing:border-box}
body{font:14px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1a1a1a;margin:0;padding:24px;background:#eee}
.bar{max-width:680px;margin:0 auto 16px;display:flex;gap:10px}
.btn{display:inline-block;background:#1a1a1a;color:#fff;text-decoration:none;font-size:13px;padding:8px 18px;border-radius:6px;cursor:pointer;border:0}
.btn--ghost{background:#fff;color:#1a1a1a;border:1px solid #cfcdc4}
.ticket{max-width:680px;margin:0 auto 18px;background:#fff;border:1px solid #cfcdc4;border-radius:10px;padding:18px 20px}
.thead{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1a1a1a;padding-bottom:10px}
.eyebrow{font-size:11px;font-weight:700;letter-spacing:1px;color:#777}
.onum{font-size:25px;font-weight:800;line-height:1.1}
.badge{display:inline-block;background:#f3eafc;color:#5d2e8c;font-size:12px;font-weight:700;padding:4px 12px;border-radius:999px}
.meta2{display:grid;grid-template-columns:1fr 1fr;gap:14px;padding:12px 0;border-bottom:1px solid #e5e3da}
.lbl{font-size:11px;font-weight:700;color:#888;margin-bottom:2px}
.sec{font-size:11px;font-weight:700;color:#888;margin:12px 0 8px}
.item{display:flex;gap:14px;border:1px solid #e5e3da;border-radius:8px;padding:12px;margin-bottom:8px}
.item img,.noimg{flex:0 0 92px;width:92px;height:92px;object-fit:contain;background:#faf8f9;border:1px solid #eee;border-radius:6px}
.noimg{display:flex;align-items:center;justify-content:center;color:#bbb;font-size:11px}
.itab{width:100%;font-size:13px;border-collapse:collapse;margin-top:6px}
.itab td{padding:2px 0;vertical-align:top}
.itab .k{color:#777;width:84px}
.foot{display:flex;justify-content:space-between;align-items:center;border-top:2px solid #1a1a1a;margin-top:12px;padding-top:10px}
@media print{.bar{display:none!important}body{background:#fff;padding:0}.ticket{border:0;border-radius:0;margin:0;max-width:none;padding:24px;page-break-after:always}}
</style></head><body>
<div class="bar">
	<button class="btn" onclick="window.print()">Print all tickets</button>
	<a class="btn btn--ghost" href="<?php echo esc_url( $back ); ?>">&larr; Back to orders</a>
</div>
<?php
foreach ( $ids as $oid ) {
	$order = wc_get_order( $oid );
	if ( ! $order ) continue;
	$t = tsa_order_ticket_data( $order );
	$tag = trim( implode( ' · ', array_filter( [ $t['store'], $t['party'] ] ) ) );
	?>
	<div class="ticket">
		<div class="thead">
			<div>
				<div class="eyebrow">PRODUCTION TICKET</div>
				<div class="onum">Order #<?php echo esc_html( $t['number'] ); ?></div>
				<div style="font-size:12px;color:#666"><?php echo esc_html( $t['date'] ); ?> &middot; <?php echo esc_html( $t['status'] ); ?></div>
			</div>
			<div style="text-align:right">
				<?php if ( $tag ) : ?><span class="badge"><?php echo esc_html( $tag ); ?></span><?php endif; ?>
				<div style="font-size:12px;color:#666;margin-top:6px"><?php echo count( $t['items'] ); ?> item<?php echo count( $t['items'] ) === 1 ? '' : 's'; ?> &middot; <?php echo (int) $t['units']; ?> unit<?php echo $t['units'] === 1 ? '' : 's'; ?></div>
			</div>
		</div>
		<div class="meta2">
			<div>
				<div class="lbl">CUSTOMER</div>
				<div style="font-size:14px"><?php echo esc_html( $t['customer'] ); ?></div>
				<?php if ( $t['phone'] ) : ?><div style="font-size:12px;color:#555"><?php echo esc_html( $t['phone'] ); ?></div><?php endif; ?>
				<?php if ( $t['email'] ) : ?><div style="font-size:12px;color:#555"><?php echo esc_html( $t['email'] ); ?></div><?php endif; ?>
			</div>
			<div>
				<div class="lbl">FULFILLMENT</div>
				<div style="font-size:14px"><?php echo $t['is_pickup'] ? 'Local pickup' : 'Ship'; ?></div>
				<div style="font-size:12px;color:#555"><?php echo wp_kses_post( $t['is_pickup'] ? esc_html( $t['ship_label'] ) : $t['ship_addr'] ); ?></div>
			</div>
		</div>
		<?php if ( $t['customer_note'] || ! empty( $t['notes'] ) ) : ?>
		<div class="sec">NOTES</div>
		<div style="border:1px solid #e5c98a;background:#fffbe9;border-radius:8px;padding:10px 12px;font-size:13px;margin-bottom:4px">
			<?php if ( $t['customer_note'] ) : ?><div style="margin-bottom:6px"><strong>Customer:</strong> <?php echo esc_html( $t['customer_note'] ); ?></div><?php endif; ?>
			<?php foreach ( (array) $t['notes'] as $n ) : ?><div style="margin-bottom:3px">&bull; <?php echo esc_html( $n ); ?></div><?php endforeach; ?>
		</div>
		<?php endif; ?>
		<div class="sec">ITEMS TO PRODUCE</div>
		<?php foreach ( $t['items'] as $it ) : ?>
		<div class="item">
			<?php if ( $it['img'] ) : ?><img src="<?php echo esc_url( $it['img'] ); ?>" alt=""><?php else : ?><span class="noimg">no preview</span><?php endif; ?>
			<div style="flex:1">
				<div style="font-size:15px;font-weight:700"><?php echo esc_html( $it['garment'] ); ?></div>
				<table class="itab">
					<?php if ( $it['color'] ) : ?><tr><td class="k">Color</td><td style="font-weight:600"><?php echo esc_html( $it['color'] ); ?></td></tr><?php endif; ?>
					<?php if ( $it['sizes'] ) : ?><tr><td class="k">Sizes</td><td style="font-weight:600"><?php echo esc_html( $it['sizes'] ); ?></td></tr><?php endif; ?>
					<?php foreach ( $it['placements'] as $p ) : ?>
					<tr><td class="k"><?php echo esc_html( $p['zone'] ); ?></td><td><?php echo esc_html( $p['design'] ); ?></td></tr>
					<?php endforeach; ?>
				</table>
			</div>
		</div>
		<?php endforeach; ?>
		<div class="foot">
			<span style="font-size:12px;color:#777">Tee Shirt Ali &middot; Production</span>
			<span style="font-size:15px;font-weight:800">Total: <?php echo (int) $t['units']; ?> unit<?php echo $t['units'] === 1 ? '' : 's'; ?></span>
		</div>
	</div>
	<?php
}
?>
</body></html>
	<?php
	exit;
}
