<?php
/**
 * TSA Reports (Phase 6 of the school framework).
 *
 * Read-only dashboards aggregated from existing order data — no new tracking.
 * Pick a school + date window: headline revenue/units/orders, breakdowns by
 * program (design category) / garment / size / color / placement zone, top
 * designs, plus fundraiser + ambassador summaries (reuse Phase 4/5 reports).
 */
defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
    add_menu_page( 'TSA Reports', 'TSA Reports', 'manage_options', 'tsa-reports', 'tsa_reports_page', 'dashicons-chart-bar', 29 );
} );

/**
 * Aggregate a school's paid sales over a window. Returns totals + breakdowns.
 * Transient-cached 15 min per school+window.
 */
function tsa_school_report( string $school, string $start = '', string $end = '', bool $fresh = false ): array {
    $key = 'tsa_report_' . md5( $school . '|' . $start . '|' . $end );
    if ( ! $fresh ) { $c = get_transient( $key ); if ( is_array( $c ) ) return $c; }

    $r = [ 'revenue' => 0.0, 'units' => 0, 'orders' => 0,
        'savings_provided' => 0.0, 'savings_coupons' => 0.0, 'savings_total' => 0.0,
        'programs' => [], 'garments' => [], 'sizes' => [], 'colors' => [], 'zones' => [], 'designs' => [] ];

    if ( ! function_exists( 'wc_get_orders' ) || ! $school ) { set_transient( $key, $r, 15 * MINUTE_IN_SECONDS ); return $r; }

    $statuses = function_exists( 'tsa_fundraiser_paid_statuses' ) ? tsa_fundraiser_paid_statuses() : [ 'processing', 'completed' ];
    $args = [ 'limit' => -1, 'status' => $statuses, 'return' => 'objects' ];
    if ( $start ) $args['date_after']  = $start . ' 00:00:00';
    if ( $end )   $args['date_before'] = $end . ' 23:59:59';
    $orders  = wc_get_orders( $args );
    $zlabels = [ 'front_full' => 'Front', 'front_pocket' => 'Front Pocket', 'back_full' => 'Back Full' ];

    foreach ( (array) $orders as $o ) {
        $counted = false;
        foreach ( $o->get_items() as $it ) {
            $raw = $it->get_meta( '_ac_configurator' ); if ( ! $raw ) continue;
            $ac  = json_decode( $raw, true ); if ( ! is_array( $ac ) ) continue;
            if ( sanitize_title( $ac['store_slug'] ?? '' ) !== $school ) continue;

            $qmap  = (array) ( $ac['quantities'] ?? [] );
            $units = array_sum( array_map( 'absint', $qmap ) );
            $rev   = (float) $it->get_total();
            $r['revenue'] += $rev; $r['units'] += $units; $counted = true;

            foreach ( $qmap as $size => $q ) { $size = sanitize_text_field( (string) $size ); $r['sizes'][ $size ] = ( $r['sizes'][ $size ] ?? 0 ) + absint( $q ); }
            $color = sanitize_text_field( $ac['color'] ?? '—' ) ?: '—'; $r['colors'][ $color ] = ( $r['colors'][ $color ] ?? 0 ) + $units;
            $gname = get_the_title( absint( $ac['garment_id'] ?? 0 ) ) ?: '—'; $r['garments'][ $gname ] = ( $r['garments'][ $gname ] ?? 0 ) + $units;
            foreach ( (array) ( $ac['zones'] ?? [] ) as $z ) { $zl = $zlabels[ $z ] ?? $z; $r['zones'][ $zl ] = ( $r['zones'][ $zl ] ?? 0 ) + $units; }

            $primary = 0;
            foreach ( (array) ( $ac['designs'] ?? [] ) as $d ) { if ( $d ) { $primary = absint( $d ); break; } }
            if ( $primary ) {
                $dn = get_the_title( $primary ) ?: ( '#' . $primary );
                $r['designs'][ $dn ] = ( $r['designs'][ $dn ] ?? 0 ) + $units;
                $terms = wp_get_post_terms( $primary, 'tsa_design_category', [ 'fields' => 'names' ] );
                $cat   = ( ! is_wp_error( $terms ) && $terms ) ? $terms[0] : 'Uncategorized';
                if ( ! isset( $r['programs'][ $cat ] ) ) $r['programs'][ $cat ] = [ 'units' => 0, 'revenue' => 0.0 ];
                $r['programs'][ $cat ]['units']   += $units;
                $r['programs'][ $cat ]['revenue'] += $rev;
            }
        }
        if ( $counted ) {
            $r['orders']++;
            // Savings: negative fees = the Tee Party quantity-tier discount we provided
            // (added via add_fee); coupon discount = coupons applied. One-store-per-cart
            // means these order-level totals belong to this school.
            foreach ( $o->get_fees() as $fee ) { $ft = (float) $fee->get_total(); if ( $ft < 0 ) $r['savings_provided'] += -$ft; }
            $r['savings_coupons'] += (float) $o->get_total_discount();
        }
    }

    arsort( $r['sizes'] ); arsort( $r['colors'] ); arsort( $r['garments'] ); arsort( $r['zones'] ); arsort( $r['designs'] );
    uasort( $r['programs'], function( $a, $b ) { return $b['units'] <=> $a['units']; } );
    $r['revenue'] = round( $r['revenue'], 2 );
    $r['savings_provided'] = round( $r['savings_provided'], 2 );
    $r['savings_coupons']  = round( $r['savings_coupons'], 2 );
    $r['savings_total']    = round( $r['savings_provided'] + $r['savings_coupons'], 2 );
    foreach ( $r['programs'] as $k => $v ) $r['programs'][ $k ]['revenue'] = round( $v['revenue'], 2 );

    set_transient( $key, $r, 15 * MINUTE_IN_SECONDS );
    return $r;
}

/** Render a simple breakdown table (label → count) with proportional bars.
 *  $label_w = max label column width (px). Wide labels (>200) wrap instead of
 *  truncating, so long names (e.g. design titles) show in full. */
function tsa_report_bars( string $title, array $data, int $max = 10, int $label_w = 140 ): void {
    echo '<div style="flex:1 1 240px;min-width:240px"><h3 style="font-size:14px;margin:0 0 8px">' . esc_html( $title ) . '</h3>';
    if ( ! $data ) { echo '<p style="color:#888;font-size:13px">No data.</p></div>'; return; }
    $top = array_slice( $data, 0, $max, true );
    $peak = max( $top ) ?: 1;
    // Wide variant: give the label a real (min-)width so the width:100% bar cell
    // can't squeeze it to 1ch (which made names wrap vertically); wrap at spaces.
    // Narrow variant: single-line with ellipsis.
    $lblcss = $label_w > 200
        ? 'width:' . (int) $label_w . 'px;min-width:' . (int) $label_w . 'px;white-space:normal;overflow-wrap:break-word'
        : 'max-width:' . (int) $label_w . 'px;white-space:nowrap;overflow:hidden;text-overflow:ellipsis';
    echo '<table style="width:100%;font-size:13px;border-collapse:collapse">';
    foreach ( $top as $label => $count ) {
        $w = max( 3, (int) round( $count / $peak * 100 ) );
        echo '<tr><td style="padding:3px 8px 3px 0;vertical-align:top;' . $lblcss . '" title="' . esc_attr( $label ) . '">' . esc_html( $label ) . '</td>'
           . '<td style="width:100%;padding:3px 0"><span style="display:inline-block;height:10px;border-radius:3px;background:#2271b1;width:' . $w . '%"></span></td>'
           . '<td style="padding:3px 0 3px 8px;text-align:right;font-weight:600">' . (int) $count . '</td></tr>';
    }
    echo '</table></div>';
}

/* ─── APPAREL ORDER SHEET (printable PO) ─────────────────────────────
   Re-aggregates the same paid orders as tsa_school_report() into an
   orderable garment → color → size cross-tab. See docs/apparel-order-sheet-design.md */

/** Garment label for the order sheet: "Brand · Name" (the S&S title already carries
 *  the style number); prepend the brand only if the title doesn't already include it. */
function tsa_order_sheet_garment_label( int $gid ): string {
    if ( ! $gid ) return '—';
    $name  = trim( (string) get_the_title( $gid ) ) ?: ( '#' . $gid );
    $brand = trim( (string) get_post_meta( $gid, '_ac_brand', true ) );
    if ( $brand === '' ) return $name;
    $norm = static function ( $s ) { return preg_replace( '/[^a-z0-9]/', '', strtolower( (string) $s ) ); };
    return ( strpos( $norm( $name ), $norm( $brand ) ) !== false ) ? $name : ( $brand . ' · ' . $name );
}

/** garment → color → size → qty cross-tab for a school + window. */
function tsa_school_order_sheet( string $school, string $start = '', string $end = '' ): array {
    $out = [ 'garments' => [], 'sizes' => [], 'units' => 0 ];
    if ( ! function_exists( 'wc_get_orders' ) || ! $school ) return $out;

    $statuses = function_exists( 'tsa_fundraiser_paid_statuses' ) ? tsa_fundraiser_paid_statuses() : [ 'processing', 'completed' ];
    $args = [ 'limit' => -1, 'status' => $statuses, 'return' => 'objects' ];
    if ( $start ) $args['date_after']  = $start . ' 00:00:00';
    if ( $end )   $args['date_before'] = $end . ' 23:59:59';

    $size_seen = [];
    foreach ( (array) wc_get_orders( $args ) as $o ) {
        foreach ( $o->get_items() as $it ) {
            $raw = $it->get_meta( '_ac_configurator' ); if ( ! $raw ) continue;
            $ac  = json_decode( $raw, true ); if ( ! is_array( $ac ) ) continue;
            if ( sanitize_title( $ac['store_slug'] ?? '' ) !== $school ) continue;

            $gid = absint( $ac['garment_id'] ?? 0 );
            if ( ! isset( $out['garments'][ $gid ] ) ) {
                $out['garments'][ $gid ] = [ 'label' => tsa_order_sheet_garment_label( $gid ), 'colors' => [], 'total' => 0 ];
            }
            $color = sanitize_text_field( $ac['color'] ?? '' ) ?: '—';
            foreach ( (array) ( $ac['quantities'] ?? [] ) as $size => $q ) {
                $size = sanitize_text_field( (string) $size ); $q = absint( $q );
                if ( $q < 1 ) continue;
                $out['garments'][ $gid ]['colors'][ $color ][ $size ] = ( $out['garments'][ $gid ]['colors'][ $color ][ $size ] ?? 0 ) + $q;
                $out['garments'][ $gid ]['total'] += $q;
                $out['units'] += $q;
                $size_seen[ $size ] = true;
            }
        }
    }

    // Size columns: standard apparel run first, then any extras in first-seen order.
    $ordered = [];
    foreach ( [ 'XS','S','M','L','XL','2XL','3XL','4XL','5XL' ] as $s ) {
        if ( isset( $size_seen[ $s ] ) ) { $ordered[] = $s; unset( $size_seen[ $s ] ); }
    }
    foreach ( array_keys( $size_seen ) as $s ) $ordered[] = $s;
    $out['sizes'] = $ordered;

    uasort( $out['garments'], static function ( $a, $b ) { return $b['total'] <=> $a['total']; } );
    foreach ( $out['garments'] as &$g ) {
        uasort( $g['colors'], static function ( $a, $b ) { return array_sum( $b ) <=> array_sum( $a ); } );
    }
    unset( $g );
    return $out;
}

/** Standalone, admin-only, print-ready order sheet (opened in a new tab). */
add_action( 'admin_post_tsa_order_sheet', 'tsa_render_order_sheet' );
function tsa_render_order_sheet(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_die( 'Not allowed.' );
    check_admin_referer( 'tsa_order_sheet' );

    $school = isset( $_GET['school'] ) ? sanitize_title( wp_unslash( $_GET['school'] ) ) : '';
    $start  = isset( $_GET['start'] )  ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : '';
    $end    = isset( $_GET['end'] )    ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : '';

    $sheet = tsa_school_order_sheet( $school, $start, $end );
    $sname = $school;
    if ( function_exists( 'tsa_school_store_records' ) ) {
        foreach ( tsa_school_store_records() as $s ) { if ( $s['slug'] === $school ) $sname = $s['name']; }
    }
    $window = ( $start || $end ) ? ( ( $start ?: '…' ) . ' → ' . ( $end ?: '…' ) ) : 'all time';
    $sizes  = $sheet['sizes'];

    nocache_headers();
    header( 'Content-Type: text/html; charset=utf-8' );
    ?>
<!doctype html>
<html lang="en">
<head>
<meta charset="utf-8">
<meta name="viewport" content="width=device-width, initial-scale=1">
<title>Apparel order sheet — <?php echo esc_html( $sname ); ?></title>
<style>
*{box-sizing:border-box}
body{font:14px/1.5 -apple-system,Segoe UI,Roboto,Arial,sans-serif;color:#1a1a1a;margin:0;padding:28px 32px;background:#fff}
.wrap{max-width:900px;margin:0 auto}
.head{display:flex;justify-content:space-between;align-items:flex-start;border-bottom:2px solid #1a1a1a;padding-bottom:10px;margin-bottom:6px}
.head h1{font-size:20px;margin:0}
.head .meta{text-align:right;font-size:12px;color:#555;line-height:1.6}
h2.g{font-size:15px;margin:20px 0 6px}
table{width:100%;border-collapse:collapse;font-size:13px;margin-bottom:4px}
th,td{border:1px solid #cfcdc4;padding:5px 8px;text-align:center}
th{background:#f1efe8}
td.l,th.l{text-align:left;white-space:nowrap}
td.tot,th.tot{background:#efece2;font-weight:600}
.z{color:#bbb}
.styletot td{background:#f7f6f1;font-weight:600}
.grand{display:flex;justify-content:flex-end;border-top:2px solid #1a1a1a;margin-top:12px;padding-top:10px;font-size:16px;font-weight:600}
.bar{margin:0 0 18px}
.btn{display:inline-block;background:#1a1a1a;color:#fff;text-decoration:none;font-size:13px;padding:8px 18px;border-radius:6px;cursor:pointer;border:0}
@media print{.no-print{display:none!important}body{padding:0}}
</style>
</head>
<body>
<div class="wrap">
  <div class="bar no-print"><button class="btn" onclick="window.print()">Print / Save as PDF</button></div>
  <div class="head">
    <h1>Apparel order sheet</h1>
    <div class="meta"><?php echo esc_html( $sname ); ?> &middot; <?php echo esc_html( $window ); ?><br>
      Generated <?php echo esc_html( date_i18n( 'M j, Y' ) ); ?> &middot; <?php echo (int) $sheet['units']; ?> units</div>
  </div>
  <?php if ( ! $sheet['garments'] ) : ?>
    <p style="margin-top:24px;color:#777">No apparel sold for this school in the selected window.</p>
  <?php else : foreach ( $sheet['garments'] as $g ) : ?>
    <h2 class="g"><?php echo esc_html( $g['label'] ); ?></h2>
    <table>
      <thead><tr>
        <th class="l">Color</th>
        <?php foreach ( $sizes as $sz ) : ?><th><?php echo esc_html( $sz ); ?></th><?php endforeach; ?>
        <th class="tot">Total</th>
      </tr></thead>
      <tbody>
        <?php foreach ( $g['colors'] as $color => $row ) : ?>
        <tr>
          <td class="l"><?php echo esc_html( $color ); ?></td>
          <?php foreach ( $sizes as $sz ) : $q = (int) ( $row[ $sz ] ?? 0 ); ?>
            <td<?php echo $q ? '' : ' class="z"'; ?>><?php echo $q ? (int) $q : '—'; ?></td>
          <?php endforeach; ?>
          <td class="tot"><?php echo (int) array_sum( $row ); ?></td>
        </tr>
        <?php endforeach; ?>
        <tr class="styletot">
          <td class="l" colspan="<?php echo count( $sizes ) + 1; ?>" style="text-align:right">Style total</td>
          <td class="tot"><?php echo (int) $g['total']; ?></td>
        </tr>
      </tbody>
    </table>
  <?php endforeach; endif; ?>
  <?php if ( $sheet['garments'] ) : ?><div class="grand">Grand total: <?php echo (int) $sheet['units']; ?> units</div><?php endif; ?>
</div>
</body>
</html>
    <?php
    exit;
}

/* ─── BACKFILL: size + placement meta onto EXISTING configurator orders ──────
   Orders placed before the order-line writer persisted Sizes/placement still
   carry the hidden _ac_configurator JSON on each line. This surfaces the missing
   visible meta from it — additive only (no price/total/status change), idempotent
   (skips lines already updated). Batched over AJAX from the button below. */
add_action( 'wp_ajax_tsa_backfill_order_meta', 'tsa_backfill_order_meta' );
function tsa_backfill_order_meta(): void {
    if ( ! current_user_can( 'manage_options' ) ) wp_send_json_error( [ 'message' => 'Not allowed.' ] );
    check_ajax_referer( 'tsa_backfill_order_meta', 'nonce' );
    if ( ! function_exists( 'wc_get_orders' ) ) wp_send_json_error( [ 'message' => 'WooCommerce not active.' ] );

    $offset = absint( $_POST['offset'] ?? 0 );
    $batch  = 30;
    $orders = wc_get_orders( [ 'limit' => $batch, 'offset' => $offset, 'orderby' => 'ID', 'order' => 'ASC', 'status' => 'any', 'return' => 'objects' ] );

    $updated = 0;
    foreach ( (array) $orders as $o ) {
        foreach ( $o->get_items() as $item ) {
            $raw = $item->get_meta( '_ac_configurator' ); if ( ! $raw ) continue;
            $ac  = json_decode( $raw, true ); if ( ! is_array( $ac ) ) continue;
            if ( tsa_backfill_order_line( $item, $ac ) ) $updated++;
        }
    }
    $n = count( (array) $orders );
    wp_send_json_success( [ 'processed' => $n, 'updated_items' => $updated, 'next_offset' => $offset + $n, 'done' => $n < $batch ] );
}

/** Add missing Sizes + placement meta to one configurator line item. True if changed.
 *  Labels mirror the plugin's write_order_line_item_meta exactly so old + new match. */
function tsa_backfill_order_line( WC_Order_Item $item, array $ac ): bool {
    $added = false;
    if ( '' === (string) $item->get_meta( 'Sizes' ) && ! empty( $ac['quantities'] ) ) {
        $parts = [];
        foreach ( (array) $ac['quantities'] as $s => $q ) { $q = (int) $q; if ( $q > 0 ) $parts[] = sanitize_text_field( (string) $s ) . ' × ' . $q; }
        if ( $parts ) { $item->add_meta_data( 'Sizes', implode( ', ', $parts ), true ); $added = true; }
    }
    if ( ! empty( $ac['zones'] ) && ! empty( $ac['designs'] ) ) {
        foreach ( (array) $ac['zones'] as $zone ) {
            $label = ucwords( str_replace( '_', ' ', (string) $zone ) );
            if ( '' !== (string) $item->get_meta( $label ) ) continue;
            $did   = absint( $ac['designs'][ $zone ] ?? 0 );
            $item->add_meta_data( $label, $did ? get_the_title( $did ) : 'Custom artwork', true );
            $added = true;
        }
    }
    if ( $added ) $item->save();
    return $added;
}

function tsa_reports_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;
    $schools = function_exists( 'tsa_school_store_records' ) ? tsa_school_store_records() : [];
    $school  = isset( $_GET['school'] ) ? sanitize_title( wp_unslash( $_GET['school'] ) ) : '';
    $start   = isset( $_GET['start'] ) ? sanitize_text_field( wp_unslash( $_GET['start'] ) ) : '';
    $end     = isset( $_GET['end'] ) ? sanitize_text_field( wp_unslash( $_GET['end'] ) ) : '';
    ?>
    <div class="wrap">
        <h1><span class="dashicons dashicons-chart-bar" style="font-size:28px;width:28px;height:28px;vertical-align:-4px"></span> TSA Reports</h1>

        <form method="get" style="margin:14px 0;display:flex;gap:10px;align-items:flex-end;flex-wrap:wrap">
            <input type="hidden" name="page" value="tsa-reports">
            <label>School<br>
                <select name="school" style="min-width:220px">
                    <option value="">— Select a school —</option>
                    <?php foreach ( $schools as $s ) : ?>
                    <option value="<?php echo esc_attr( $s['slug'] ); ?>" <?php selected( $school, $s['slug'] ); ?>><?php echo esc_html( $s['name'] ); ?></option>
                    <?php endforeach; ?>
                </select>
            </label>
            <label>From<br><input type="date" name="start" value="<?php echo esc_attr( $start ); ?>"></label>
            <label>To<br><input type="date" name="end" value="<?php echo esc_attr( $end ); ?>"></label>
            <button class="button button-primary">Run report</button>
            <span style="color:#888;font-size:12px">Leave dates blank for all-time.</span>
        </form>

        <?php $tsa_bf_nonce = wp_create_nonce( 'tsa_backfill_order_meta' ); ?>
        <div style="margin:0 0 18px;padding:12px 14px;background:#fff;border:1px solid #dcdcde;border-radius:8px;max-width:660px">
            <strong>Backfill order details</strong>
            <p style="margin:4px 0 8px;color:#666;font-size:13px">Adds <em>Sizes</em> + placement lines to <strong>existing</strong> configurator orders from their stored configuration, so older orders match new ones. Additive only — never changes prices, totals, or status. Safe to run once; re-running skips lines already updated.</p>
            <button type="button" class="button" id="tsa-backfill-btn">Backfill order details</button>
            <span id="tsa-backfill-msg" style="margin-left:10px;font-size:13px;color:#555"></span>
        </div>
        <script>
        (function(){
            var btn=document.getElementById('tsa-backfill-btn'),msg=document.getElementById('tsa-backfill-msg');
            if(!btn)return; var nonce='<?php echo esc_js( $tsa_bf_nonce ); ?>';
            function run(offset,tp,tu){
                var fd=new FormData();fd.append('action','tsa_backfill_order_meta');fd.append('nonce',nonce);fd.append('offset',offset);
                fetch(ajaxurl,{method:'POST',body:fd,credentials:'same-origin'}).then(function(r){return r.json();}).then(function(j){
                    if(!j||!j.success){msg.textContent='Error — '+((j&&j.data&&j.data.message)||'try again');btn.disabled=false;return;}
                    var d=j.data;tp+=d.processed;tu+=d.updated_items;
                    if(d.done){msg.textContent='Done — scanned '+tp+' orders, updated '+tu+' line items.';btn.disabled=false;}
                    else{msg.textContent='Scanned '+tp+' orders, updated '+tu+' items…';run(d.next_offset,tp,tu);}
                }).catch(function(){msg.textContent='Network error — try again';btn.disabled=false;});
            }
            btn.addEventListener('click',function(){btn.disabled=true;msg.textContent='Working…';run(0,0,0);});
        })();
        </script>

        <?php
        if ( ! $school ) { echo '<p>Select a school to see its report.</p>'; if ( ! $schools ) echo '<p><em>No schools yet — build one in TSA Store Builder.</em></p>'; echo '</div>'; return; }

        $r     = tsa_school_report( $school, $start, $end, true );
        $sname = '';
        foreach ( $schools as $s ) if ( $s['slug'] === $school ) $sname = $s['name'];
        $card  = function( $label, $val ) { return '<div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:14px 16px;min-width:130px"><div style="font-size:12px;color:#666">' . esc_html( $label ) . '</div><div style="font-size:24px;font-weight:700">' . esc_html( $val ) . '</div></div>'; };
        ?>
        <h2 style="margin-top:6px"><?php echo esc_html( $sname ?: $school ); ?><?php echo ( $start || $end ) ? ' · ' . esc_html( $start ?: '…' ) . ' → ' . esc_html( $end ?: '…' ) : ' · all time'; ?></h2>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin:10px 0 22px">
            <?php
            echo $card( 'Revenue', '$' . number_format( $r['revenue'], 2 ) );
            echo $card( 'Shirts', number_format( $r['units'] ) );
            echo $card( 'Orders', number_format( $r['orders'] ) );
            echo $card( 'Programs', (string) count( $r['programs'] ) );
            ?>
        </div>

        <h2 style="margin-top:6px">Customer savings</h2>
        <div style="display:flex;gap:12px;flex-wrap:wrap;margin:10px 0 6px">
            <?php
            echo $card( 'Discounts we provided', '$' . number_format( $r['savings_provided'], 2 ) );
            echo $card( 'Coupons applied', '$' . number_format( $r['savings_coupons'], 2 ) );
            echo '<div style="background:#e8f5ee;border:1px solid #b6e0c8;border-radius:8px;padding:14px 16px;min-width:130px"><div style="font-size:12px;color:#1a7f47">Total customer savings</div><div style="font-size:24px;font-weight:700;color:#1a7f47">$' . esc_html( number_format( $r['savings_total'], 2 ) ) . '</div></div>';
            ?>
        </div>
        <p style="color:#555;font-size:13px;margin:0 0 24px">
            <?php if ( $r['savings_total'] > 0 ) : ?>
                You've saved <?php echo esc_html( $sname ?: $school ); ?> shoppers <strong>$<?php echo esc_html( number_format( $r['savings_total'], 2 ) ); ?></strong> through discounts in this window.
            <?php else : ?>
                No discounts were applied for this school in this window.
            <?php endif; ?>
        </p>

        <h2>By program</h2>
        <?php if ( $r['programs'] ) : ?>
        <table class="widefat striped" style="max-width:560px;margin-bottom:24px"><thead><tr><th>Program</th><th style="text-align:right">Shirts</th><th style="text-align:right">Revenue</th></tr></thead><tbody>
            <?php foreach ( $r['programs'] as $cat => $v ) : ?>
            <tr><td><?php echo esc_html( $cat ); ?></td><td style="text-align:right"><?php echo (int) $v['units']; ?></td><td style="text-align:right">$<?php echo number_format( $v['revenue'], 2 ); ?></td></tr>
            <?php endforeach; ?>
        </tbody></table>
        <?php else : ?><p style="color:#888">No sales in this window.</p><?php endif; ?>

        <h2>Production breakdown</h2>
        <div style="display:flex;gap:30px;flex-wrap:wrap;background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin-bottom:24px">
            <?php
            tsa_report_bars( 'By garment', $r['garments'] );
            tsa_report_bars( 'By size', $r['sizes'] );
            tsa_report_bars( 'By color', $r['colors'] );
            tsa_report_bars( 'By placement', $r['zones'] );
        ?></div>

        <?php if ( $r['units'] > 0 ) :
            $sheet_url = wp_nonce_url(
                admin_url( 'admin-post.php?action=tsa_order_sheet&school=' . rawurlencode( $school ) . '&start=' . rawurlencode( $start ) . '&end=' . rawurlencode( $end ) ),
                'tsa_order_sheet'
            );
        ?>
        <p style="margin:-10px 0 26px">
            <a class="button button-primary" href="<?php echo esc_url( $sheet_url ); ?>" target="_blank" rel="noopener">
                <span class="dashicons dashicons-printer" style="vertical-align:-4px"></span> Export order sheet
            </a>
            <span style="color:#888;font-size:12px;margin-left:8px">Garment &times; color &times; size, ready to order from the wholesaler — opens a print-ready page.</span>
        </p>
        <?php endif; ?>

        <h2>Top designs</h2>
        <div style="background:#fff;border:1px solid #dcdcde;border-radius:8px;padding:16px;margin-bottom:24px;max-width:720px">
            <?php tsa_report_bars( 'Shirts by design', $r['designs'], 12, 340 ); ?>
        </div>

        <?php
        // Fundraiser summary for this school
        $frs = get_posts( [ 'post_type' => 'tsa_fundraiser', 'numberposts' => -1, 'post_status' => 'publish',
            'meta_query' => [ [ 'key' => '_tsa_fr_school', 'value' => $school ] ] ] );
        if ( $frs && function_exists( 'tsa_fundraiser_report' ) ) : ?>
        <h2>Fundraisers</h2>
        <table class="widefat striped" style="max-width:760px;margin-bottom:24px"><thead><tr><th>Campaign</th><th>Type</th><th style="text-align:right">Gross</th><th style="text-align:right">Payout</th><th style="text-align:right">Goal</th></tr></thead><tbody>
            <?php foreach ( $frs as $f ) : $fr = tsa_fundraiser_report( $f->ID ); ?>
            <tr><td><a href="<?php echo esc_url( get_edit_post_link( $f->ID ) ); ?>"><?php echo esc_html( get_the_title( $f ) ); ?></a></td>
                <td><?php echo esc_html( str_replace( '_', ' ', $fr['type'] ) ); ?></td>
                <td style="text-align:right">$<?php echo number_format( $fr['gross'], 2 ); ?></td>
                <td style="text-align:right;font-weight:600">$<?php echo number_format( $fr['payout'], 2 ); ?></td>
                <td style="text-align:right"><?php echo $fr['goal'] > 0 ? (int) $fr['goal_pct'] . '%' : '—'; ?></td></tr>
            <?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>

        <?php
        // Ambassador leaderboard for this school
        $ambs = get_posts( [ 'post_type' => 'tsa_ambassador', 'numberposts' => -1, 'post_status' => 'publish',
            'meta_query' => [ [ 'key' => '_tsa_amb_school', 'value' => $school ] ] ] );
        if ( $ambs && function_exists( 'tsa_ambassador_report' ) ) : ?>
        <h2>Ambassadors</h2>
        <table class="widefat striped" style="max-width:760px;margin-bottom:24px"><thead><tr><th>Ambassador</th><th>Code</th><th style="text-align:right">Shirts</th><th style="text-align:right">Sales</th><th style="text-align:right">Reward</th></tr></thead><tbody>
            <?php
            $rows = [];
            foreach ( $ambs as $a ) { $ar = tsa_ambassador_report( $a->ID ); $rows[] = [ $a, $ar ]; }
            usort( $rows, function( $x, $y ) { return $y[1]['units'] <=> $x[1]['units']; } );
            foreach ( $rows as $pair ) : list( $a, $ar ) = $pair; ?>
            <tr><td><a href="<?php echo esc_url( get_edit_post_link( $a->ID ) ); ?>"><?php echo esc_html( get_the_title( $a ) ); ?></a></td>
                <td><code><?php echo esc_html( get_post_meta( $a->ID, '_tsa_amb_code', true ) ); ?></code></td>
                <td style="text-align:right"><?php echo (int) $ar['units']; ?></td>
                <td style="text-align:right">$<?php echo number_format( $ar['sales'], 2 ); ?></td>
                <td style="text-align:right;font-weight:600"><?php echo $ar['type'] !== 'none' ? '$' . number_format( $ar['reward'], 2 ) : '—'; ?></td></tr>
            <?php endforeach; ?>
        </tbody></table>
        <?php endif; ?>

        <p style="color:#888;font-size:12px">Sales counted from paid orders (<?php echo esc_html( implode( ', ', tsa_fundraiser_paid_statuses() ) ); ?>). Program/design figures attribute each line to its first design's category.</p>
    </div>
    <?php
}
