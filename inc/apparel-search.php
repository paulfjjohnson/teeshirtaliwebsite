<?php
/**
 * TSA Apparel Ordered — search/aggregate all apparel ordered, site-wide or by
 * scope (store: school / team / business, a Tee Party, or a Fundraiser).
 *
 * Answers "what blanks do we need to order?" It scans paid WooCommerce orders,
 * reads the configurator line meta (_ac_configurator: store_slug, garment_id,
 * color, quantities-by-size), and rolls it up into a garment → color → size
 * cross-tab with totals. Pairs with the Wholesale Orders tracker: a copy-ready
 * summary drops straight into a wholesale order's Items field, and CSV export
 * is one click.
 *
 * Reuses reports.php's per-line logic; this generalizes it to any scope
 * (empty slug = site-wide) and adds a scope picker + date range + export.
 */
defined( 'ABSPATH' ) || exit;

/** Paid statuses counted (mirrors the reports module). */
function tsa_apparel_statuses() {
	return function_exists( 'tsa_fundraiser_paid_statuses' ) ? tsa_fundraiser_paid_statuses() : [ 'processing', 'completed' ];
}

/** Human garment label (reuse reports.php helper when present). */
function tsa_apparel_garment_label( $gid ) {
	if ( function_exists( 'tsa_order_sheet_garment_label' ) ) { return tsa_order_sheet_garment_label( (int) $gid ); }
	return $gid ? ( get_the_title( (int) $gid ) ?: ( '#' . (int) $gid ) ) : '—';
}

/** All apparel offered site-wide (configurator_garment): [ id => label ]. */
function tsa_apparel_garments() {
	$out = [];
	foreach ( get_posts( [ 'post_type' => 'configurator_garment', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) as $g ) {
		$out[ $g->ID ] = tsa_apparel_garment_label( $g->ID );
	}
	return $out;
}

/** store_slug → [ label, type ] for event attribution (stores + tee parties). */
function tsa_apparel_events_map() {
	static $map = null;
	if ( null !== $map ) { return $map; }
	$map = [];
	$type_labels = [ 'school' => 'School', 'team' => 'Team', 'business' => 'Business' ];
	foreach ( get_posts( [ 'post_type' => 'configurator_store', 'post_status' => 'any', 'numberposts' => -1 ] ) as $s ) {
		$slug = sanitize_title( get_post_meta( $s->ID, '_ac_store_slug', true ) ?: $s->post_name );
		$type = get_post_meta( $s->ID, '_tsa_store_type', true ) ?: 'school';
		if ( $slug ) { $map[ $slug ] = [ 'label' => get_the_title( $s ), 'type' => $type_labels[ $type ] ?? 'Store' ]; }
	}
	foreach ( get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'meta_key' => '_wp_page_template', 'meta_value' => 'template-tee-party.php' ] ) as $p ) {
		$slug = sanitize_title( (string) get_post_meta( $p->ID, '_tsa_party_store_slug', true ) );
		if ( $slug && ! isset( $map[ $slug ] ) ) { $map[ $slug ] = [ 'label' => get_the_title( $p ), 'type' => 'Tee Party' ]; }
	}
	return $map;
}

/** HPOS-aware admin edit URL for a WooCommerce order id. */
function tsa_apparel_order_url( $order_id ) {
	if ( function_exists( 'tsa_wo_order_edit_url' ) ) { return tsa_wo_order_edit_url( $order_id ); }
	if ( class_exists( '\Automattic\WooCommerce\Utilities\OrderUtil' )
		&& \Automattic\WooCommerce\Utilities\OrderUtil::custom_orders_table_usage_is_enabled() ) {
		return admin_url( 'admin.php?page=wc-orders&action=edit&id=' . (int) $order_id );
	}
	return admin_url( 'post.php?post=' . (int) $order_id . '&action=edit' );
}

/** Resolve a line's store slug → event label + type. */
function tsa_apparel_event_for( $slug ) {
	$slug = sanitize_title( (string) $slug );
	if ( '' === $slug ) { return [ 'label' => 'Site-wide / General', 'type' => 'General' ]; }
	$map = tsa_apparel_events_map();
	return $map[ $slug ] ?? [ 'label' => $slug, 'type' => 'Other' ];
}

/**
 * Aggregate apparel ordered.
 * @param string $slug        store slug to scope to; '' = site-wide (all apparel).
 * @param int    $garment_id  limit to one garment; 0 = all apparel.
 * @param bool   $with_detail also collect per-line customer + event rows.
 * @return array garments[gid]=>[label,colors[color][size]=>qty,total], sizes[], units, orders, detail[]
 */
function tsa_apparel_aggregate( string $slug = '', string $start = '', string $end = '', int $garment_id = 0, bool $with_detail = false ): array {
	$out = [ 'garments' => [], 'sizes' => [], 'units' => 0, 'orders' => 0, 'detail' => [] ];
	if ( ! function_exists( 'wc_get_orders' ) ) { return $out; }

	$args = [ 'limit' => -1, 'status' => tsa_apparel_statuses(), 'return' => 'objects' ];
	if ( $start ) { $args['date_after']  = $start . ' 00:00:00'; }
	if ( $end )   { $args['date_before'] = $end . ' 23:59:59'; }

	$size_seen = [];
	foreach ( (array) wc_get_orders( $args ) as $o ) {
		$counted = false;
		$cust    = trim( $o->get_billing_first_name() . ' ' . $o->get_billing_last_name() );
		$cemail  = $o->get_billing_email();
		$odate   = $o->get_date_created() ? $o->get_date_created()->date( 'M j, Y' ) : '';
		foreach ( $o->get_items() as $it ) {
			$raw = $it->get_meta( '_ac_configurator' ); if ( ! $raw ) { continue; }
			$ac  = json_decode( $raw, true ); if ( ! is_array( $ac ) ) { continue; }
			$lslug = sanitize_title( $ac['store_slug'] ?? '' );
			if ( '' !== $slug && $lslug !== $slug ) { continue; }

			$gid = absint( $ac['garment_id'] ?? 0 );
			if ( $garment_id && $gid !== $garment_id ) { continue; }
			if ( ! isset( $out['garments'][ $gid ] ) ) {
				$out['garments'][ $gid ] = [ 'label' => tsa_apparel_garment_label( $gid ), 'colors' => [], 'total' => 0 ];
			}
			$color = sanitize_text_field( $ac['color'] ?? '' ) ?: '—';
			$line_sizes = []; $line_qty = 0;
			foreach ( (array) ( $ac['quantities'] ?? [] ) as $size => $q ) {
				$size = sanitize_text_field( (string) $size ); $q = absint( $q );
				if ( $q < 1 ) { continue; }
				$out['garments'][ $gid ]['colors'][ $color ][ $size ] = ( $out['garments'][ $gid ]['colors'][ $color ][ $size ] ?? 0 ) + $q;
				$out['garments'][ $gid ]['total'] += $q;
				$out['units'] += $q;
				$size_seen[ $size ] = true;
				$line_sizes[ $size ] = ( $line_sizes[ $size ] ?? 0 ) + $q;
				$line_qty += $q;
				$counted = true;
			}
			if ( $with_detail && $line_qty > 0 ) {
				$ev    = tsa_apparel_event_for( $lslug );
				$parts = [];
				foreach ( $line_sizes as $sz => $qq ) { $parts[] = $sz . ':' . $qq; }
				$out['detail'][] = [
					'customer' => $cust ?: '—',
					'email'    => $cemail,
					'event'    => $ev['label'],
					'type'     => $ev['type'],
					'garment'  => $out['garments'][ $gid ]['label'],
					'color'    => $color,
					'sizes'    => implode( ' ', $parts ),
					'qty'      => $line_qty,
					'order'    => $o->get_order_number(),
					'order_id' => $o->get_id(),
					'date'     => $odate,
				];
			}
		}
		if ( $counted ) { $out['orders']++; }
	}

	// Size column order: standard run first, then extras in first-seen order.
	$ordered = [];
	foreach ( [ 'XS','S','M','L','XL','2XL','3XL','4XL','5XL' ] as $s ) {
		if ( isset( $size_seen[ $s ] ) ) { $ordered[] = $s; unset( $size_seen[ $s ] ); }
	}
	foreach ( array_keys( $size_seen ) as $s ) { $ordered[] = $s; }
	$out['sizes'] = $ordered;

	uasort( $out['garments'], static function ( $a, $b ) { return $b['total'] <=> $a['total']; } );
	foreach ( $out['garments'] as &$g ) {
		uasort( $g['colors'], static function ( $a, $b ) { return array_sum( $b ) <=> array_sum( $a ); } );
	}
	unset( $g );

	if ( $with_detail && $out['detail'] ) {
		usort( $out['detail'], static function ( $a, $b ) {
			return [ $a['event'], $a['customer'] ] <=> [ $b['event'], $b['customer'] ];
		} );
	}
	return $out;
}

/** Scope options for the picker, as optgroups: [ group => [ value => label ] ]. */
function tsa_apparel_scopes() {
	$out = [ 'General' => [ 'all' => 'Site-wide (all apparel)' ] ];

	$type_labels = [ 'school' => 'Schools', 'team' => 'Teams', 'business' => 'Businesses' ];
	foreach ( get_posts( [ 'post_type' => 'configurator_store', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) as $s ) {
		$type = get_post_meta( $s->ID, '_tsa_store_type', true ) ?: 'school';
		$slug = sanitize_title( get_post_meta( $s->ID, '_ac_store_slug', true ) ?: $s->post_name );
		if ( $slug ) { $out[ $type_labels[ $type ] ?? 'Stores' ][ 'store:' . $slug ] = get_the_title( $s ); }
	}
	foreach ( get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC', 'meta_key' => '_wp_page_template', 'meta_value' => 'template-tee-party.php' ] ) as $p ) {
		$slug = sanitize_title( (string) get_post_meta( $p->ID, '_tsa_party_store_slug', true ) );
		if ( $slug ) { $out['Tee Parties'][ 'store:' . $slug ] = get_the_title( $p ); }
	}
	foreach ( get_posts( [ 'post_type' => 'tsa_fundraiser', 'post_status' => 'any', 'numberposts' => -1, 'orderby' => 'title', 'order' => 'ASC' ] ) as $f ) {
		$out['Fundraisers'][ 'fr:' . $f->ID ] = get_the_title( $f );
	}
	return $out;
}

/** Resolve a scope value → store slug (''=site-wide). Fills start/end from a fundraiser window when blank. */
function tsa_apparel_resolve( string $scope, string &$start, string &$end ): string {
	if ( '' === $scope || 'all' === $scope ) { return ''; }
	if ( 0 === strpos( $scope, 'store:' ) ) { return sanitize_title( substr( $scope, 6 ) ); }
	if ( 0 === strpos( $scope, 'fr:' ) ) {
		$id = absint( substr( $scope, 3 ) );
		if ( '' === $start ) { $start = sanitize_text_field( (string) get_post_meta( $id, '_tsa_fr_start', true ) ); }
		if ( '' === $end )   { $end   = sanitize_text_field( (string) get_post_meta( $id, '_tsa_fr_end', true ) ); }
		return sanitize_title( (string) get_post_meta( $id, '_tsa_fr_school', true ) );
	}
	return '';
}

/** Flat rows for CSV/summary: [ [garment, color, size, qty], … ] sorted by garment total. */
function tsa_apparel_flatten( array $agg ): array {
	$rows = [];
	foreach ( $agg['garments'] as $g ) {
		foreach ( $g['colors'] as $color => $sizes ) {
			foreach ( $agg['sizes'] as $size ) {
				$q = (int) ( $sizes[ $size ] ?? 0 );
				if ( $q > 0 ) { $rows[] = [ $g['label'], $color, $size, $q ]; }
			}
			// any non-standard sizes not in the ordered list
			foreach ( $sizes as $size => $q ) {
				if ( ! in_array( $size, $agg['sizes'], true ) && (int) $q > 0 ) { $rows[] = [ $g['label'], $color, $size, (int) $q ]; }
			}
		}
	}
	return $rows;
}

/** A copy-ready summary for pasting into a wholesale order's Items field. */
function tsa_apparel_summary_text( array $agg ): string {
	$lines = [];
	foreach ( $agg['garments'] as $g ) {
		foreach ( $g['colors'] as $color => $sizes ) {
			$parts = [];
			foreach ( $agg['sizes'] as $size ) { if ( ! empty( $sizes[ $size ] ) ) { $parts[] = $size . ':' . (int) $sizes[ $size ]; } }
			foreach ( $sizes as $size => $q ) { if ( ! in_array( $size, $agg['sizes'], true ) && (int) $q ) { $parts[] = $size . ':' . (int) $q; } }
			$total   = array_sum( $sizes );
			$lines[] = sprintf( '%d× %s — %s (%s)', $total, $g['label'], $color, implode( ' ', $parts ) );
		}
	}
	return implode( "\n", $lines );
}

/* ─── CSV export ───────────────────────────────────────────────────── */
add_action( 'admin_post_tsa_apparel_export', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
	check_admin_referer( 'tsa_apparel', 'tsa_apparel_nonce' );
	$scope   = sanitize_text_field( wp_unslash( $_POST['scope'] ?? 'all' ) );
	$start   = sanitize_text_field( wp_unslash( $_POST['start'] ?? '' ) );
	$end     = sanitize_text_field( wp_unslash( $_POST['end'] ?? '' ) );
	$garment = absint( $_POST['garment'] ?? 0 );
	$slug    = tsa_apparel_resolve( $scope, $start, $end );
	$rows    = tsa_apparel_flatten( tsa_apparel_aggregate( $slug, $start, $end, $garment ) );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=apparel-ordered-' . gmdate( 'Ymd-His' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, [ 'Garment', 'Color', 'Size', 'Quantity' ] );
	foreach ( $rows as $r ) { fputcsv( $out, $r ); }
	fclose( $out );
	exit;
} );

/* ─── CSV export — customer + event detail ─────────────────────────── */
add_action( 'admin_post_tsa_apparel_export_detail', function () {
	if ( ! current_user_can( 'manage_options' ) ) { wp_die( 'Not allowed.' ); }
	check_admin_referer( 'tsa_apparel', 'tsa_apparel_nonce' );
	$scope   = sanitize_text_field( wp_unslash( $_POST['scope'] ?? 'all' ) );
	$start   = sanitize_text_field( wp_unslash( $_POST['start'] ?? '' ) );
	$end     = sanitize_text_field( wp_unslash( $_POST['end'] ?? '' ) );
	$garment = absint( $_POST['garment'] ?? 0 );
	$slug    = tsa_apparel_resolve( $scope, $start, $end );
	$agg     = tsa_apparel_aggregate( $slug, $start, $end, $garment, true );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=apparel-by-customer-' . gmdate( 'Ymd-His' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, [ 'Customer', 'Email', 'Event', 'Event type', 'Garment', 'Color', 'Sizes', 'Qty', 'Order #', 'Date' ] );
	foreach ( $agg['detail'] as $d ) {
		fputcsv( $out, [ $d['customer'], $d['email'], $d['event'], $d['type'], $d['garment'], $d['color'], $d['sizes'], $d['qty'], $d['order'], $d['date'] ] );
	}
	fclose( $out );
	exit;
} );

/* ─── Admin screen ─────────────────────────────────────────────────── */
add_action( 'admin_menu', function () {
	add_submenu_page( 'tsa-reports', 'Apparel Ordered', 'Apparel Ordered', 'manage_options', 'tsa-apparel-ordered', 'tsa_apparel_search_page' );
}, 20 );

add_filter( 'tsa_admin_hub_tools', function ( $groups ) {
	$card = [ 'Apparel Ordered', 'What apparel got ordered, by scope', '👕', admin_url( 'admin.php?page=tsa-apparel-ordered' ) ];
	if ( isset( $groups['Orders & Ops'] ) && is_array( $groups['Orders & Ops'] ) ) { $groups['Orders & Ops'][] = $card; }
	else { $groups['Orders & Ops'] = [ $card ]; }
	return $groups;
} );

function tsa_apparel_search_page() {
	if ( ! current_user_can( 'manage_options' ) ) { return; }

	$scope = 'all'; $start = ''; $end = ''; $garment = 0; $agg = null;
	if ( ! empty( $_POST['tsa_apparel_go'] ) && check_admin_referer( 'tsa_apparel', 'tsa_apparel_nonce' ) ) {
		$scope   = sanitize_text_field( wp_unslash( $_POST['scope'] ?? 'all' ) );
		$start   = sanitize_text_field( wp_unslash( $_POST['start'] ?? '' ) );
		$end     = sanitize_text_field( wp_unslash( $_POST['end'] ?? '' ) );
		$garment = absint( $_POST['garment'] ?? 0 );
		$slug    = tsa_apparel_resolve( $scope, $start, $end );
		$agg     = tsa_apparel_aggregate( $slug, $start, $end, $garment, true );
	}
	?>
	<div class="wrap">
		<h1>Apparel Ordered</h1>
		<p style="max-width:780px;color:#50575e">Roll up every garment ordered — site-wide or scoped to a store, Tee Party, or Fundraiser — into a color × size breakdown. Use it to place blank orders; the copy-ready summary drops into a <a href="<?php echo esc_url( admin_url( 'edit.php?post_type=tsa_wholesale_order' ) ); ?>">Wholesale Order</a>.</p>

		<form method="post">
			<?php wp_nonce_field( 'tsa_apparel', 'tsa_apparel_nonce' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<tr>
					<th scope="row"><label for="tsa-ap-scope">Scope</label></th>
					<td>
						<select id="tsa-ap-scope" name="scope">
							<?php foreach ( tsa_apparel_scopes() as $grp => $opts ) : ?>
								<optgroup label="<?php echo esc_attr( $grp ); ?>">
									<?php foreach ( $opts as $val => $lab ) : ?>
										<option value="<?php echo esc_attr( $val ); ?>" <?php selected( $scope, $val ); ?>><?php echo esc_html( $lab ); ?></option>
									<?php endforeach; ?>
								</optgroup>
							<?php endforeach; ?>
						</select>
						<p class="description">Stores &amp; Tee Parties match orders by store; Fundraisers also apply their campaign date window.</p>
					</td>
				</tr>
				<tr>
					<th scope="row"><label for="tsa-ap-garment">Apparel item</label></th>
					<td>
						<select id="tsa-ap-garment" name="garment">
							<option value="0">All apparel offered</option>
							<?php foreach ( tsa_apparel_garments() as $gid => $glabel ) : ?>
								<option value="<?php echo (int) $gid; ?>" <?php selected( $garment, $gid ); ?>><?php echo esc_html( $glabel ); ?></option>
							<?php endforeach; ?>
						</select>
						<p class="description">Limit to one blank/garment offered site-wide, or leave as all.</p>
					</td>
				</tr>
				<tr>
					<th scope="row">Date range <span style="font-weight:400;color:#777">(optional)</span></th>
					<td>From <input type="date" name="start" value="<?php echo esc_attr( $start ); ?>"> &nbsp; to <input type="date" name="end" value="<?php echo esc_attr( $end ); ?>"></td>
				</tr>
			</tbody></table>
			<p class="submit">
				<button type="submit" name="tsa_apparel_go" value="1" class="button button-primary">Search</button>
				<?php if ( $agg && $agg['units'] ) : ?>
					<button type="submit" name="action" value="tsa_apparel_export" class="button" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">Export totals CSV</button>
					<button type="submit" name="action" value="tsa_apparel_export_detail" class="button" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">Export by customer CSV</button>
				<?php endif; ?>
			</p>
		</form>

		<?php if ( $agg !== null ) : ?>
			<?php if ( ! $agg['units'] ) : ?>
				<div class="notice notice-warning"><p>No apparel found for that scope/date range.</p></div>
			<?php else : ?>
				<h2><?php echo (int) $agg['units']; ?> pieces &middot; <?php echo count( $agg['garments'] ); ?> garment style(s) &middot; <?php echo (int) $agg['orders']; ?> order(s)</h2>

				<?php foreach ( $agg['garments'] as $g ) : ?>
					<h3 style="margin:18px 0 6px"><?php echo esc_html( $g['label'] ); ?> <span style="color:#777;font-weight:400">— <?php echo (int) $g['total']; ?> pcs</span></h3>
					<table class="widefat striped" style="max-width:900px">
						<thead><tr><th>Color</th><?php foreach ( $agg['sizes'] as $s ) echo '<th style="text-align:center">' . esc_html( $s ) . '</th>'; ?><th style="text-align:center">Total</th></tr></thead>
						<tbody>
						<?php foreach ( $g['colors'] as $color => $sizes ) : ?>
							<tr>
								<td><strong><?php echo esc_html( $color ); ?></strong></td>
								<?php foreach ( $agg['sizes'] as $s ) : $q = (int) ( $sizes[ $s ] ?? 0 ); ?>
									<td style="text-align:center"><?php echo $q ? (int) $q : '<span style="color:#ccc">·</span>'; ?></td>
								<?php endforeach; ?>
								<td style="text-align:center"><strong><?php echo (int) array_sum( $sizes ); ?></strong></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				<?php endforeach; ?>

				<h3 style="margin:22px 0 6px">Copy-ready summary</h3>
				<p class="description">Paste into a Wholesale Order's <em>Items (summary)</em> field.</p>
				<textarea readonly rows="<?php echo max( 3, min( 20, count( $agg['garments'] ) + 2 ) ); ?>" style="width:100%;max-width:900px;font:13px/1.5 ui-monospace,Menlo,Consolas,monospace" onclick="this.select()"><?php echo esc_textarea( tsa_apparel_summary_text( $agg ) ); ?></textarea>

				<?php $detail = $agg['detail']; $cap = 800; ?>
				<h3 style="margin:26px 0 6px">By customer &amp; event (<?php echo count( $detail ); ?> line<?php echo count( $detail ) === 1 ? '' : 's'; ?>)</h3>
				<p class="description">Who ordered this apparel and under which event. Use <strong>Export by customer CSV</strong> for the full list.</p>
				<div style="max-height:460px;overflow:auto;border:1px solid #dcdcde;border-radius:6px;background:#fff;max-width:1100px">
					<table class="widefat striped" style="border:0">
						<thead><tr><th>Customer</th><th>Event</th><th>Type</th><th>Garment</th><th>Color</th><th>Sizes</th><th style="text-align:center">Qty</th><th>Order</th><th>Date</th></tr></thead>
						<tbody>
						<?php foreach ( array_slice( $detail, 0, $cap ) as $d ) : ?>
							<tr>
								<td><?php echo esc_html( $d['customer'] ); ?><?php if ( $d['email'] ) : ?><br><span style="color:#777;font-size:12px"><?php echo esc_html( $d['email'] ); ?></span><?php endif; ?></td>
								<td><?php echo esc_html( $d['event'] ); ?></td>
								<td><?php echo esc_html( $d['type'] ); ?></td>
								<td><?php echo esc_html( $d['garment'] ); ?></td>
								<td><?php echo esc_html( $d['color'] ); ?></td>
								<td><?php echo esc_html( $d['sizes'] ); ?></td>
								<td style="text-align:center"><?php echo (int) $d['qty']; ?></td>
								<td><a href="<?php echo esc_url( tsa_apparel_order_url( (int) $d['order_id'] ) ); ?>">#<?php echo esc_html( $d['order'] ); ?></a></td>
								<td style="white-space:nowrap"><?php echo esc_html( $d['date'] ); ?></td>
							</tr>
						<?php endforeach; ?>
						</tbody>
					</table>
				</div>
				<?php if ( count( $detail ) > $cap ) : ?>
					<p class="description">Showing first <?php echo (int) $cap; ?> of <?php echo count( $detail ); ?> lines — export the CSV for all.</p>
				<?php endif; ?>
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
