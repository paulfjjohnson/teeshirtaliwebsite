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

/**
 * Aggregate apparel ordered.
 * @param string $slug  store slug to scope to; '' = site-wide (all apparel).
 * @return array garments[gid]=>[label,colors[color][size]=>qty,total], sizes[], units, orders
 */
function tsa_apparel_aggregate( string $slug = '', string $start = '', string $end = '' ): array {
	$out = [ 'garments' => [], 'sizes' => [], 'units' => 0, 'orders' => 0 ];
	if ( ! function_exists( 'wc_get_orders' ) ) { return $out; }

	$args = [ 'limit' => -1, 'status' => tsa_apparel_statuses(), 'return' => 'objects' ];
	if ( $start ) { $args['date_after']  = $start . ' 00:00:00'; }
	if ( $end )   { $args['date_before'] = $end . ' 23:59:59'; }

	$size_seen = [];
	foreach ( (array) wc_get_orders( $args ) as $o ) {
		$counted = false;
		foreach ( $o->get_items() as $it ) {
			$raw = $it->get_meta( '_ac_configurator' ); if ( ! $raw ) { continue; }
			$ac  = json_decode( $raw, true ); if ( ! is_array( $ac ) ) { continue; }
			if ( '' !== $slug && sanitize_title( $ac['store_slug'] ?? '' ) !== $slug ) { continue; }

			$gid = absint( $ac['garment_id'] ?? 0 );
			if ( ! isset( $out['garments'][ $gid ] ) ) {
				$out['garments'][ $gid ] = [ 'label' => tsa_apparel_garment_label( $gid ), 'colors' => [], 'total' => 0 ];
			}
			$color = sanitize_text_field( $ac['color'] ?? '' ) ?: '—';
			foreach ( (array) ( $ac['quantities'] ?? [] ) as $size => $q ) {
				$size = sanitize_text_field( (string) $size ); $q = absint( $q );
				if ( $q < 1 ) { continue; }
				$out['garments'][ $gid ]['colors'][ $color ][ $size ] = ( $out['garments'][ $gid ]['colors'][ $color ][ $size ] ?? 0 ) + $q;
				$out['garments'][ $gid ]['total'] += $q;
				$out['units'] += $q;
				$size_seen[ $size ] = true;
				$counted = true;
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
	$scope = sanitize_text_field( wp_unslash( $_POST['scope'] ?? 'all' ) );
	$start = sanitize_text_field( wp_unslash( $_POST['start'] ?? '' ) );
	$end   = sanitize_text_field( wp_unslash( $_POST['end'] ?? '' ) );
	$slug  = tsa_apparel_resolve( $scope, $start, $end );
	$rows  = tsa_apparel_flatten( tsa_apparel_aggregate( $slug, $start, $end ) );
	nocache_headers();
	header( 'Content-Type: text/csv; charset=utf-8' );
	header( 'Content-Disposition: attachment; filename=apparel-ordered-' . gmdate( 'Ymd-His' ) . '.csv' );
	$out = fopen( 'php://output', 'w' );
	fputcsv( $out, [ 'Garment', 'Color', 'Size', 'Quantity' ] );
	foreach ( $rows as $r ) { fputcsv( $out, $r ); }
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

	$scope = 'all'; $start = ''; $end = ''; $agg = null;
	if ( ! empty( $_POST['tsa_apparel_go'] ) && check_admin_referer( 'tsa_apparel', 'tsa_apparel_nonce' ) ) {
		$scope = sanitize_text_field( wp_unslash( $_POST['scope'] ?? 'all' ) );
		$start = sanitize_text_field( wp_unslash( $_POST['start'] ?? '' ) );
		$end   = sanitize_text_field( wp_unslash( $_POST['end'] ?? '' ) );
		$slug  = tsa_apparel_resolve( $scope, $start, $end );
		$agg   = tsa_apparel_aggregate( $slug, $start, $end );
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
					<th scope="row">Date range <span style="font-weight:400;color:#777">(optional)</span></th>
					<td>From <input type="date" name="start" value="<?php echo esc_attr( $start ); ?>"> &nbsp; to <input type="date" name="end" value="<?php echo esc_attr( $end ); ?>"></td>
				</tr>
			</tbody></table>
			<p class="submit">
				<button type="submit" name="tsa_apparel_go" value="1" class="button button-primary">Search</button>
				<?php if ( $agg && $agg['units'] ) : ?>
					<button type="submit" name="action" value="tsa_apparel_export" class="button" formaction="<?php echo esc_url( admin_url( 'admin-post.php' ) ); ?>">Export CSV</button>
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
			<?php endif; ?>
		<?php endif; ?>
	</div>
	<?php
}
