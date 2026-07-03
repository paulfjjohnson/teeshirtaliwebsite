<?php
/**
 * TSA Owner Command Center (admin dashboard, v1).
 *
 * Front-end /dashboard/ page (template-dashboard.php), owner-only
 * (manage_woocommerce), gated by the `dashboard` platform feature. Theme-token
 * styled → auto tenant-recolor. v1 = cockpit core + performance + engagement,
 * with click-through slide-over detail panels (AJAX). Data comes from one
 * timeframe-scoped order scan + the existing report helpers, transient-cached.
 */
defined( 'ABSPATH' ) || exit;

/* Register the dashboard feature in the platform catalog. */
add_filter( 'tsa_feature_catalog', function ( $c ) {
    $c['dashboard'] = [ 'id' => 'dashboard', 'name' => 'Owner Dashboard', 'group' => 'Insight', 'requires' => [], 'store_type' => '', 'lifecycle' => 'ga' ];
    return $c;
} );

/* Assets — only on the dashboard page. */
add_action( 'wp_enqueue_scripts', function () {
    if ( ! is_page_template( 'template-dashboard.php' ) ) return;
    $v = wp_get_theme()->get( 'Version' );
    wp_enqueue_style( 'tsa-dashboard', get_stylesheet_directory_uri() . '/assets/css/tsa-dashboard.css', [ 'tsa-child' ], $v );
    wp_enqueue_script( 'tsa-dashboard', get_stylesheet_directory_uri() . '/assets/js/tsa-dashboard.js', [], $v, true );
    wp_localize_script( 'tsa-dashboard', 'tsaDash', [
        'ajaxUrl'  => admin_url( 'admin-ajax.php' ),
        'nonce'    => wp_create_nonce( 'tsa_dash' ),
        'currency' => get_woocommerce_currency_symbol(),
    ] );
}, 20 );

/** Owner gate for the dashboard page + AJAX. */
function tsa_dash_can(): bool { return is_user_logged_in() && current_user_can( 'manage_woocommerce' ); }

/** Resolve the front-end Command Center page by its template, cached per request. */
function tsa_dash_page_id(): int {
    static $id = null;
    if ( $id !== null ) return $id;
    $q  = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_wp_page_template', 'meta_value' => 'template-dashboard.php' ] );
    $id = $q ? (int) $q[0] : 0;
    return $id;
}

/* Top-level admin-menu entry → opens the front-end Command Center. Owner-only. */
add_action( 'admin_menu', function () {
    if ( ! tsa_dash_can() ) return;
    if ( function_exists( 'tsa_feature_active' ) && ! tsa_feature_active( 'dashboard' ) ) return;
    if ( ! tsa_dash_page_id() ) return; // page not created yet
    $hook = add_menu_page(
        'Command Center',           // page title
        'Command Center',           // menu label
        'manage_woocommerce',       // capability
        'tsa-command-center',       // admin slug (we redirect off it)
        '__return_null',            // callback never renders
        'dashicons-chart-area',     // icon
        3                           // position (just under Dashboard)
    );
    // Redirect the admin slug out to the front-end dashboard before any output.
    add_action( 'load-' . $hook, function () {
        wp_safe_redirect( get_permalink( tsa_dash_page_id() ) );
        exit;
    } );
}, 9 );

function tsa_dash_paid_statuses(): array {
    return function_exists( 'tsa_fundraiser_paid_statuses' ) ? tsa_fundraiser_paid_statuses() : [ 'processing', 'completed' ];
}

/* ─── Timeframe ─── */
function tsa_dash_range( string $tf ): array {
    $now = current_time( 'timestamp' );
    switch ( $tf ) {
        case 'today': $start = strtotime( 'today', $now ); $len = $now - $start; break;
        case '30d':   $len = 30 * DAY_IN_SECONDS; $start = $now - $len; break;
        case 'all':   $start = 0; $len = 0; break;
        case '7d': default: $tf = '7d'; $len = 7 * DAY_IN_SECONDS; $start = $now - $len; break;
    }
    return [ 'tf' => $tf, 'start' => $start, 'end' => $now,
        'pstart' => ( $start && $len ) ? $start - $len : 0, 'pend' => $start, 'len' => $len ];
}
function tsa_dash_trend( $cur, $prior ): string {
    if ( $prior === null ) return '';
    if ( $prior <= 0 ) return $cur > 0 ? '<span class="up">▲ new</span>' : '';
    $p = (int) round( ( $cur - $prior ) / $prior * 100 );
    if ( $p > 0 ) return '<span class="up">▲ ' . $p . '%</span>';
    if ( $p < 0 ) return '<span class="down">▼ ' . abs( $p ) . '%</span>';
    return '<span class="flat">—</span>';
}

/* ─── Order scan ─── */
function tsa_dash_scan( array $statuses, int $start, int $end ): array {
    $args = [ 'limit' => -1, 'status' => $statuses, 'return' => 'objects' ];
    if ( $start ) $args['date_after']  = gmdate( 'Y-m-d H:i:s', $start - ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
    if ( $end )   $args['date_before'] = gmdate( 'Y-m-d H:i:s', $end - ( (int) get_option( 'gmt_offset' ) * HOUR_IN_SECONDS ) );
    $orders = function_exists( 'wc_get_orders' ) ? wc_get_orders( $args ) : [];

    $d = [ 'revenue' => 0.0, 'orders' => 0, 'units' => 0, 'store' => [], 'method' => [], 'program' => [], 'designs' => [], 'daily' => [] ];
    foreach ( (array) $orders as $o ) {
        $d['orders']++; $d['revenue'] += (float) $o->get_total();
        $dt = $o->get_date_created(); if ( $dt ) { $k = $dt->date( 'Y-m-d' ); $d['daily'][ $k ] = ( $d['daily'][ $k ] ?? 0 ) + (float) $o->get_total(); }
        $ostore = sanitize_title( (string) $o->get_meta( '_ac_store_slug' ) );
        foreach ( $o->get_items() as $it ) {
            $qty = (int) $it->get_quantity(); $d['units'] += $qty; $lt = (float) $it->get_total();
            $raw = $it->get_meta( '_ac_configurator' ); $ac = $raw ? json_decode( $raw, true ) : null;
            $m = $it->get_meta( 'Gang Sheet Size' ) ? 'Gang sheets' : ( is_array( $ac ) ? 'Apparel' : 'Other' );
            $d['method'][ $m ] = ( $d['method'][ $m ] ?? 0 ) + $lt;
            $store = ( is_array( $ac ) && ! empty( $ac['store_slug'] ) ) ? sanitize_title( $ac['store_slug'] ) : $ostore;
            if ( $store ) $d['store'][ $store ] = ( $d['store'][ $store ] ?? 0 ) + $lt;
            if ( is_array( $ac ) && ! empty( $ac['designs'] ) ) {
                $primary = 0;
                foreach ( (array) $ac['designs'] as $did ) { if ( $did ) { if ( ! $primary ) $primary = (int) $did; $d['designs'][ (int) $did ] = 1; } }
                if ( $primary ) {
                    $terms = wp_get_post_terms( $primary, 'tsa_design_category', [ 'fields' => 'names' ] );
                    $cat = ( ! is_wp_error( $terms ) && $terms ) ? $terms[0] : 'Uncategorized';
                    if ( ! isset( $d['program'][ $cat ] ) ) $d['program'][ $cat ] = [ 'units' => 0, 'revenue' => 0.0 ];
                    $d['program'][ $cat ]['units'] += $qty; $d['program'][ $cat ]['revenue'] += $lt;
                }
            }
        }
    }
    arsort( $d['store'] ); arsort( $d['method'] );
    uasort( $d['program'], function( $a, $b ) { return $b['units'] <=> $a['units']; } );
    ksort( $d['daily'] );
    return $d;
}
function tsa_dash_sales( string $tf ): array {
    $r = tsa_dash_range( $tf );
    $key = 'tsa_dash_sales_' . $r['tf'];
    $c = get_transient( $key ); if ( is_array( $c ) ) return $c;
    $st = tsa_dash_paid_statuses();
    $out = [ 'tf' => $r['tf'], 'cur' => tsa_dash_scan( $st, $r['start'], $r['end'] ),
        'prior' => ( $r['start'] && $r['pstart'] ) ? tsa_dash_scan( $st, $r['pstart'], $r['pend'] ) : null ];
    set_transient( $key, $out, 5 * MINUTE_IN_SECONDS );
    return $out;
}

/* ─── Production pipeline ─── */
function tsa_dash_pipeline(): array {
    $c = get_transient( 'tsa_dash_pipeline' ); if ( is_array( $c ) ) return $c;
    $lanes = [ 'artwork-review' => 'Artwork review', 'in-production' => 'In production', 'ready-for-pickup' => 'Ready pickup', 'processing' => 'To ship', 'completed' => 'Completed' ];
    $counts = []; $queue = [];
    if ( function_exists( 'wc_get_orders' ) ) {
        foreach ( $lanes as $st => $lbl ) $counts[ $st ] = count( wc_get_orders( [ 'status' => $st, 'limit' => -1, 'return' => 'ids' ] ) );
        foreach ( wc_get_orders( [ 'status' => [ 'artwork-review', 'processing', 'in-production', 'ready-for-pickup' ], 'limit' => 8, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' ] ) as $o ) {
            $queue[] = [ 'num' => $o->get_order_number(), 'status' => wc_get_order_status_name( $o->get_status() ),
                'store' => $o->get_meta( '_ac_store_slug' ), 'total' => $o->get_formatted_order_total(), 'url' => tsa_dash_order_url( $o->get_id() ) ];
        }
    }
    $out = [ 'lanes' => $lanes, 'counts' => $counts, 'queue' => $queue ];
    set_transient( 'tsa_dash_pipeline', $out, 5 * MINUTE_IN_SECONDS );
    return $out;
}
function tsa_dash_order_url( int $id ): string {
    $u = get_edit_post_link( $id );
    return $u ?: admin_url( 'admin.php?page=wc-orders&action=edit&id=' . $id );
}
function tsa_dash_store_name( string $slug ): string {
    if ( $slug === '' ) return 'General';
    return function_exists( 'tsa_store_name' ) ? tsa_store_name( $slug ) : ucwords( str_replace( '-', ' ', $slug ) );
}

/* ─── Renderers (shared by template + AJAX) ─── */
function tsa_dash_render_kpis( array $sales ): string {
    $c = $sales['cur']; $p = $sales['prior']; $cur = get_woocommerce_currency_symbol();
    $aov  = $c['orders'] > 0 ? $c['revenue'] / $c['orders'] : 0;
    $paov = ( $p && $p['orders'] > 0 ) ? $p['revenue'] / $p['orders'] : null;
    $tile = function( $card, $label, $val, $trend, $sub = '' ) {
        return '<button class="tsa-dash-kpi" data-card="' . esc_attr( $card ) . '"><span class="l">' . esc_html( $label ) . '</span><span class="v">' . $val . '</span><span class="t">' . ( $trend ?: ( $sub ? '<span class="flat">' . esc_html( $sub ) . '</span>' : '' ) ) . '</span></button>';
    };
    return '<div class="tsa-dash-kpis">'
        . $tile( 'revenue', 'Revenue', $cur . number_format( $c['revenue'], 0 ), tsa_dash_trend( $c['revenue'], $p ? $p['revenue'] : null ) )
        . $tile( 'orders', 'Orders', (string) (int) $c['orders'], tsa_dash_trend( $c['orders'], $p ? $p['orders'] : null ) )
        . $tile( 'units', 'Units', (string) (int) $c['units'], tsa_dash_trend( $c['units'], $p ? $p['units'] : null ), ( $c['orders'] ? number_format( $c['units'] / max( 1, $c['orders'] ), 1 ) . ' / order' : '' ) )
        . $tile( 'aov', 'Avg order', $cur . number_format( $aov, 0 ), tsa_dash_trend( $aov, $paov ) )
        . '</div>';
}
function tsa_dash_render_performance( array $sales ): string {
    $c = $sales['cur']; $cur = get_woocommerce_currency_symbol();
    ob_start(); ?>
    <div class="tsa-dash-perf">
        <div class="tsa-dash-card">
            <h3>Top stores</h3>
            <?php $stores = array_slice( $c['store'], 0, 5, true ); $max = $stores ? max( $stores ) : 1;
            if ( ! $stores ) echo '<p class="muted">No sales in this period.</p>';
            foreach ( $stores as $slug => $amt ) : $w = max( 4, round( $amt / $max * 100 ) ); ?>
                <button class="tsa-dash-bar" data-card="store:<?php echo esc_attr( $slug ); ?>">
                    <span class="bl"><?php echo esc_html( tsa_dash_store_name( $slug ) ); ?></span>
                    <span class="bt"><span style="width:<?php echo $w; ?>%"></span></span>
                    <span class="bv"><?php echo $cur . number_format( $amt, 0 ); ?></span>
                </button>
            <?php endforeach; ?>
        </div>
        <div class="tsa-dash-card">
            <h3>By method</h3>
            <?php $tot = array_sum( $c['method'] ); if ( ! $tot ) echo '<p class="muted">—</p>';
            foreach ( $c['method'] as $m => $amt ) : ?>
                <button class="tsa-dash-row" data-card="method:<?php echo esc_attr( $m ); ?>"><span><?php echo esc_html( $m ); ?></span><span><?php echo $tot ? round( $amt / $tot * 100 ) : 0; ?>%</span></button>
            <?php endforeach; ?>
            <h3 style="margin-top:14px">Top programs</h3>
            <?php $progs = array_slice( $c['program'], 0, 5, true ); if ( ! $progs ) echo '<p class="muted">—</p>';
            foreach ( $progs as $cat => $v ) : ?>
                <button class="tsa-dash-row" data-card="program:<?php echo esc_attr( $cat ); ?>"><span><?php echo esc_html( $cat ); ?></span><span><?php echo (int) $v['units']; ?> · <?php echo $cur . number_format( $v['revenue'], 0 ); ?></span></button>
            <?php endforeach; ?>
        </div>
    </div>
    <?php return ob_get_clean();
}

/* ─── AJAX: timeframe refresh ─── */
add_action( 'wp_ajax_tsa_dash_refresh', function () {
    check_ajax_referer( 'tsa_dash', 'nonce' );
    if ( ! tsa_dash_can() ) wp_send_json_error();
    $tf = sanitize_key( $_POST['tf'] ?? '7d' );
    $sales = tsa_dash_sales( $tf );
    wp_send_json_success( [ 'kpis' => tsa_dash_render_kpis( $sales ), 'performance' => tsa_dash_render_performance( $sales ) ] );
} );

/* ─── AJAX: card detail panel ─── */
add_action( 'wp_ajax_tsa_dash_panel', function () {
    check_ajax_referer( 'tsa_dash', 'nonce' );
    if ( ! tsa_dash_can() ) wp_send_json_error();
    $card = sanitize_text_field( wp_unslash( $_POST['card'] ?? '' ) );
    $tf   = sanitize_key( $_POST['tf'] ?? '7d' );
    wp_send_json_success( [ 'html' => tsa_dash_panel_html( $card, $tf ) ] );
} );

function tsa_dash_panel_html( string $card, string $tf ): string {
    $cur = get_woocommerce_currency_symbol();
    [ $type, $arg ] = array_pad( explode( ':', $card, 2 ), 2, '' );
    $title = ''; $body = '';

    if ( in_array( $type, [ 'revenue', 'orders', 'units', 'aov' ], true ) ) {
        $sales = tsa_dash_sales( $tf ); $c = $sales['cur'];
        $labels = [ 'revenue' => 'Revenue', 'orders' => 'Orders', 'units' => 'Units', 'aov' => 'Avg order' ];
        $title = $labels[ $type ] . ' · ' . $tf;
        // daily trend
        $body .= '<div class="tsa-dp-h">Daily trend</div><div class="tsa-dp-spark">';
        $days = $c['daily']; $peak = $days ? max( $days ) : 1;
        if ( ! $days ) $body .= '<p class="muted">No sales.</p>';
        foreach ( $days as $day => $amt ) { $h = max( 4, round( $amt / $peak * 100 ) ); $body .= '<span title="' . esc_attr( $day . ' · ' . $cur . number_format( $amt, 0 ) ) . '" style="height:' . $h . '%"></span>'; }
        $body .= '</div>';
        $body .= tsa_dash_kv( 'By store', array_map( function( $a ) use ( $cur ) { return $cur . number_format( $a, 0 ); }, array_slice( $c['store'], 0, 6, true ) ), 'tsa_dash_store_name' );
        $body .= tsa_dash_kv( 'By method', array_map( function( $a ) use ( $cur ) { return $cur . number_format( $a, 0 ); }, $c['method'] ) );
        $body .= tsa_dash_recent_orders();
    } elseif ( $type === 'lane' ) {
        $names = tsa_dash_pipeline()['lanes'];
        $title = ( $names[ $arg ] ?? ucwords( str_replace( '-', ' ', $arg ) ) ) . ' orders';
        $body  = tsa_dash_order_list( [ $arg ], 25 );
    } elseif ( $type === 'store' ) {
        $title = tsa_dash_store_name( $arg ) . ' · recent orders';
        $body  = tsa_dash_order_list( tsa_dash_paid_statuses(), 25, $arg )
               . '<a class="tsa-dp-cta" href="' . esc_url( admin_url( 'admin.php?page=tsa-reports&school=' . rawurlencode( $arg ) ) ) . '">View full report →</a>';
    } elseif ( $type === 'method' || $type === 'program' ) {
        $sales = tsa_dash_sales( $tf ); $c = $sales['cur'];
        $title = $arg . ' · ' . $tf;
        if ( $type === 'method' ) { $amt = $c['method'][ $arg ] ?? 0; $body = '<div class="tsa-dp-big">' . $cur . number_format( $amt, 0 ) . '</div><p class="muted">Sales via ' . esc_html( $arg ) . ' this period.</p>'; }
        else { $v = $c['program'][ $arg ] ?? [ 'units' => 0, 'revenue' => 0 ]; $body = '<div class="tsa-dp-big">' . $cur . number_format( $v['revenue'], 0 ) . '</div><p class="muted">' . (int) $v['units'] . ' shirts in ' . esc_html( $arg ) . ' this period.</p>'; }
        $body .= '<a class="tsa-dp-cta" href="' . esc_url( admin_url( 'admin.php?page=tsa-reports' ) ) . '">Open Reports →</a>';
    } elseif ( $type === 'fundraiser' && function_exists( 'tsa_fundraiser_report' ) ) {
        $id = (int) $arg; $r = tsa_fundraiser_report( $id ); $title = get_the_title( $id );
        $body = tsa_dash_kv( 'Campaign', [ 'Gross' => $cur . number_format( $r['gross'], 0 ), 'Units' => (string) $r['units'], 'Payout (est)' => $cur . number_format( $r['payout'], 0 ), 'Goal' => $r['goal'] > 0 ? $r['goal_pct'] . '%' : '—' ] )
             . '<a class="tsa-dp-cta" href="' . esc_url( get_edit_post_link( $id ) ) . '">Open campaign →</a>';
    } elseif ( $type === 'ambassador' && function_exists( 'tsa_ambassador_report' ) ) {
        $id = (int) $arg; $r = tsa_ambassador_report( $id ); $title = get_the_title( $id );
        $body = tsa_dash_kv( 'Attributed', [ 'Orders' => (string) $r['orders'], 'Shirts' => (string) $r['units'], 'Sales' => $cur . number_format( $r['sales'], 0 ), 'Reward' => $r['type'] !== 'none' ? $cur . number_format( $r['reward'], 0 ) : '—' ] )
             . '<a class="tsa-dp-cta" href="' . esc_url( get_edit_post_link( $id ) ) . '">Open ambassador →</a>';
    } elseif ( $type === 'health' ) {
        $title = 'Needs attention';
        $body  = tsa_dash_health_list( $arg );
    } else {
        $title = 'Details'; $body = '<p class="muted">No detail available.</p>';
    }

    return '<div class="tsa-dp-head"><span class="tsa-dp-title">' . esc_html( $title ) . '</span><button class="tsa-dp-close" aria-label="Close">&times;</button></div><div class="tsa-dp-body">' . $body . '</div>';
}

/* small panel helpers */
function tsa_dash_kv( string $head, array $rows, string $namecb = '' ): string {
    if ( ! $rows ) return '';
    $h = '<div class="tsa-dp-h">' . esc_html( $head ) . '</div>';
    foreach ( $rows as $k => $v ) {
        $label = $namecb && function_exists( $namecb ) ? $namecb( (string) $k ) : (string) $k;
        $h .= '<div class="tsa-dp-row"><span>' . esc_html( $label ) . '</span><strong>' . esc_html( $v ) . '</strong></div>';
    }
    return $h;
}
function tsa_dash_recent_orders( int $limit = 8 ): string {
    return '<div class="tsa-dp-h">Recent orders</div>' . tsa_dash_order_list( tsa_dash_paid_statuses(), $limit );
}
function tsa_dash_order_list( array $statuses, int $limit = 20, string $store = '' ): string {
    if ( ! function_exists( 'wc_get_orders' ) ) return '';
    $args = [ 'status' => $statuses, 'limit' => $limit, 'orderby' => 'date', 'order' => 'DESC', 'return' => 'objects' ];
    if ( $store !== '' ) $args['meta_query'] = [ [ 'key' => '_ac_store_slug', 'value' => $store ] ];
    $orders = wc_get_orders( $args );
    if ( ! $orders ) return '<p class="muted">No orders.</p>';
    $h = '';
    foreach ( $orders as $o ) {
        $h .= '<a class="tsa-dp-order" href="' . esc_url( tsa_dash_order_url( $o->get_id() ) ) . '"><span>#' . esc_html( $o->get_order_number() ) . ' · ' . esc_html( tsa_dash_store_name( sanitize_title( (string) $o->get_meta( '_ac_store_slug' ) ) ) ) . '<br><em>' . esc_html( wc_get_order_status_name( $o->get_status() ) ) . '</em></span><span>' . wp_kses_post( $o->get_formatted_order_total() ) . ' ›</span></a>';
    }
    return $h;
}
function tsa_dash_health_list( string $type ): string {
    if ( $type === 'cost' ) {
        $g = get_posts( [ 'post_type' => 'configurator_garment', 'post_status' => 'publish', 'numberposts' => 50, 'fields' => 'ids', 'meta_query' => [ 'relation' => 'OR', [ 'key' => '_tsa_garment_cost', 'compare' => 'NOT EXISTS' ], [ 'key' => '_tsa_garment_cost', 'value' => '0', 'compare' => '<=' ] ] ] );
        if ( ! $g ) return '<p class="muted">All garments have a cost basis. 👍</p>';
        $h = '<div class="tsa-dp-h">Garments missing cost basis</div>';
        foreach ( $g as $id ) $h .= '<a class="tsa-dp-order" href="' . esc_url( get_edit_post_link( $id ) ) . '"><span>' . esc_html( get_the_title( $id ) ) . '</span><span>Set cost ›</span></a>';
        return $h;
    }
    if ( $type === 'soon' ) {
        $recs = function_exists( 'tsa_school_store_records' ) ? tsa_school_store_records() : [];
        $soon = array_filter( $recs, function( $r ) { return $r['status'] !== 'live'; } );
        if ( ! $soon ) return '<p class="muted">No coming-soon stores.</p>';
        $h = '<div class="tsa-dp-h">Coming-soon stores</div>';
        foreach ( $soon as $r ) $h .= '<a class="tsa-dp-order" href="' . esc_url( get_edit_post_link( $r['post_id'] ) ) . '"><span>' . esc_html( $r['name'] ) . '</span><span>Open ›</span></a>';
        return $h;
    }
    return '<p class="muted">—</p>';
}
