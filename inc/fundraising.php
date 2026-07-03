<?php
/**
 * TSA Fundraising Engine (Phase 4 of the school framework).
 *
 * A fundraiser is a campaign (tsa_fundraiser CPT) scoped to a school, optionally
 * narrowed to a program (design category) and/or a Tee Party drop, over a date
 * window. Sales are attributed from existing configurator order data
 * (_ac_configurator: store_slug / party_id / designs / quantities), so no new
 * order tracking is required.
 *
 * Payouts are REPORT-ONLY — the engine estimates and shows a payout figure; the
 * owner disburses manually. Profit-split needs a garment cost basis (added as a
 * meta box on configurator_garment) plus each design's _ac_print_cost; if a
 * garment cost is missing the report is flagged incomplete.
 */
defined( 'ABSPATH' ) || exit;

/* ─────────────────────────────────────────────────────────────────
   CPT
───────────────────────────────────────────────────────────────── */
add_action( 'init', 'tsa_register_fundraiser_cpt' );
function tsa_register_fundraiser_cpt(): void {
    register_post_type( 'tsa_fundraiser', [
        'labels' => [
            'name' => 'Fundraisers', 'singular_name' => 'Fundraiser',
            'add_new_item' => 'Add Fundraiser', 'edit_item' => 'Edit Fundraiser',
            'menu_name' => 'Fundraisers',
        ],
        'public'       => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-heart',
        'menu_position'=> 27,
        'supports'     => [ 'title', 'editor', 'thumbnail' ],
        'rewrite'      => [ 'slug' => 'fundraiser' ],
        'has_archive'  => false,
    ] );

    // One-time rewrite flush so /fundraiser/{slug}/ resolves.
    if ( get_option( 'tsa_fr_rewrites' ) !== '1' ) {
        flush_rewrite_rules( false );
        update_option( 'tsa_fr_rewrites', '1' );
    }
}

/** Order statuses that count as a real (paid) sale. */
function tsa_fundraiser_paid_statuses(): array {
    return apply_filters( 'tsa_fundraiser_paid_statuses', [
        'processing', 'completed', 'in-production', 'shipped', 'ready-for-pickup', 'fundraiser-counted',
    ] );
}

/* ─────────────────────────────────────────────────────────────────
   CONFIG META BOX
───────────────────────────────────────────────────────────────── */
add_action( 'add_meta_boxes', function () {
    add_meta_box( 'tsa_fr_cfg', 'Fundraiser Settings', 'tsa_fr_cfg_box', 'tsa_fundraiser', 'normal', 'high' );
    add_meta_box( 'tsa_fr_report', 'Fundraiser Report', 'tsa_fr_report_box', 'tsa_fundraiser', 'side', 'default' );
} );

function tsa_fr_cfg_box( WP_Post $post ): void {
    wp_nonce_field( 'tsa_fr_save', 'tsa_fr_nonce' );
    $g = function( $k, $d = '' ) use ( $post ) { return get_post_meta( $post->ID, $k, true ) ?: $d; };
    $school   = $g( '_tsa_fr_school' );
    $program  = $g( '_tsa_fr_program' );
    $party    = (int) $g( '_tsa_fr_party_id' );
    $type     = $g( '_tsa_fr_type', 'per_item' );
    $rate     = $g( '_tsa_fr_rate' );
    $goal     = $g( '_tsa_fr_goal' );
    $start    = $g( '_tsa_fr_start' );
    $end      = $g( '_tsa_fr_end' );
    $recip    = $g( '_tsa_fr_recipient' );
    $payemail = $g( '_tsa_fr_payout_email' );

    $schools = function_exists( 'tsa_school_store_records' ) ? tsa_school_store_records() : [];
    $parties = get_posts( [ 'post_type' => 'page', 'post_status' => 'publish', 'numberposts' => 100,
        'meta_query' => [ [ 'key' => '_tsa_party_store_slug', 'compare' => 'EXISTS' ] ], 'orderby' => 'title', 'order' => 'ASC' ] );
    ?>
    <style>
    .tsa-fr-grid{display:grid;grid-template-columns:160px 1fr;gap:10px 14px;align-items:center;font-size:13px;max-width:680px}
    .tsa-fr-grid label{font-weight:600}
    .tsa-fr-grid input[type=text],.tsa-fr-grid input[type=number],.tsa-fr-grid input[type=date],.tsa-fr-grid input[type=email],.tsa-fr-grid select{width:100%;max-width:340px;padding:5px 8px;border:1px solid #ddd;border-radius:4px}
    .tsa-fr-grid .desc{grid-column:2;color:#888;font-size:12px;margin:-4px 0 4px}
    </style>
    <div class="tsa-fr-grid">
        <label>School</label>
        <select name="tsa_fr_school">
            <option value="">— Select a school —</option>
            <?php foreach ( $schools as $s ) : ?>
            <option value="<?php echo esc_attr( $s['slug'] ); ?>" <?php selected( $school, $s['slug'] ); ?>><?php echo esc_html( $s['name'] ); ?></option>
            <?php endforeach; ?>
        </select>

        <label>Program</label>
        <input type="text" name="tsa_fr_program" value="<?php echo esc_attr( $program ); ?>" placeholder="band (optional)">
        <div class="desc">Optional design-category slug to narrow the campaign to one program. Blank = the whole school.</div>

        <label>Tee Party drop</label>
        <select name="tsa_fr_party_id">
            <option value="0">— None (any sales) —</option>
            <?php foreach ( $parties as $p ) : ?>
            <option value="<?php echo esc_attr( $p->ID ); ?>" <?php selected( $party, $p->ID ); ?>><?php echo esc_html( get_the_title( $p ) ); ?></option>
            <?php endforeach; ?>
        </select>
        <div class="desc">Optional — count only sales from a specific drop.</div>

        <label>Type</label>
        <select name="tsa_fr_type" id="tsa-fr-type">
            <option value="per_item"     <?php selected( $type, 'per_item' ); ?>>Per-item donation ($ per shirt)</option>
            <option value="pct_sales"    <?php selected( $type, 'pct_sales' ); ?>>Percentage of sales (% of gross)</option>
            <option value="profit_split" <?php selected( $type, 'profit_split' ); ?>>Profit split (% of profit)</option>
        </select>

        <label>Rate</label>
        <input type="number" step="0.01" min="0" name="tsa_fr_rate" value="<?php echo esc_attr( $rate ); ?>">
        <div class="desc" id="tsa-fr-rate-hint">Dollars per shirt (per-item) or a percentage (sales / profit).</div>

        <label>Goal ($)</label>
        <input type="number" step="1" min="0" name="tsa_fr_goal" value="<?php echo esc_attr( $goal ); ?>" placeholder="2500">

        <label>Start date</label>
        <input type="date" name="tsa_fr_start" value="<?php echo esc_attr( $start ); ?>">
        <label>End date</label>
        <input type="date" name="tsa_fr_end" value="<?php echo esc_attr( $end ); ?>">

        <label>Recipient</label>
        <input type="text" name="tsa_fr_recipient" value="<?php echo esc_attr( $recip ); ?>" placeholder="Dutchtown Band Boosters">
        <label>Payout contact</label>
        <input type="email" name="tsa_fr_payout_email" value="<?php echo esc_attr( $payemail ); ?>" placeholder="boosters@school.org">
    </div>
    <p style="color:#888;font-size:12px;margin-top:12px">The post title is the campaign name, the editor below is the campaign story, and the featured image is the hero. Payouts are <strong>report-only</strong> — disburse manually.</p>
    <script>
    (function(){
        var t=document.getElementById('tsa-fr-type'), h=document.getElementById('tsa-fr-rate-hint');
        function hint(){ h.textContent = t.value==='per_item' ? 'Dollars donated per shirt sold.' : (t.value==='pct_sales' ? 'Percentage of gross sales (e.g. 20 = 20%).' : 'Percentage of profit after garment + print costs (e.g. 50).'); }
        if(t){ t.addEventListener('change',hint); hint(); }
    })();
    </script>
    <?php
}

add_action( 'save_post_tsa_fundraiser', 'tsa_fr_save' );
function tsa_fr_save( int $post_id ): void {
    if ( ! isset( $_POST['tsa_fr_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_fr_nonce'] ) ), 'tsa_fr_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $type = sanitize_key( $_POST['tsa_fr_type'] ?? 'per_item' );
    update_post_meta( $post_id, '_tsa_fr_school',       sanitize_title( wp_unslash( $_POST['tsa_fr_school'] ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_fr_program',      sanitize_title( wp_unslash( $_POST['tsa_fr_program'] ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_fr_party_id',     absint( $_POST['tsa_fr_party_id'] ?? 0 ) );
    update_post_meta( $post_id, '_tsa_fr_type',         in_array( $type, [ 'per_item', 'pct_sales', 'profit_split' ], true ) ? $type : 'per_item' );
    update_post_meta( $post_id, '_tsa_fr_rate',         (float) ( $_POST['tsa_fr_rate'] ?? 0 ) );
    update_post_meta( $post_id, '_tsa_fr_goal',         (float) ( $_POST['tsa_fr_goal'] ?? 0 ) );
    update_post_meta( $post_id, '_tsa_fr_start',        sanitize_text_field( wp_unslash( $_POST['tsa_fr_start'] ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_fr_end',          sanitize_text_field( wp_unslash( $_POST['tsa_fr_end'] ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_fr_recipient',    sanitize_text_field( wp_unslash( $_POST['tsa_fr_recipient'] ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_fr_payout_email', sanitize_email( wp_unslash( $_POST['tsa_fr_payout_email'] ?? '' ) ) );

    delete_transient( 'tsa_fr_report_' . $post_id ); // recompute next view
}

/* ─────────────────────────────────────────────────────────────────
   GARMENT COST (for profit-split) — theme meta box on the plugin CPT
───────────────────────────────────────────────────────────────── */
add_action( 'add_meta_boxes', function () {
    if ( post_type_exists( 'configurator_garment' ) ) {
        add_meta_box( 'tsa_garment_cost', 'Cost Basis (fundraiser profit)', 'tsa_garment_cost_box', 'configurator_garment', 'side', 'default' );
    }
} );
function tsa_garment_cost_box( WP_Post $post ): void {
    wp_nonce_field( 'tsa_garment_cost_save', 'tsa_garment_cost_nonce' );
    $cost = get_post_meta( $post->ID, '_tsa_garment_cost', true );
    echo '<p><label>Blank garment cost ($ each)<br><input type="number" step="0.01" min="0" name="tsa_garment_cost" value="' . esc_attr( $cost ) . '" style="width:120px" placeholder="0.00"></label></p>';
    echo '<p style="color:#888;font-size:12px">What TSA pays per blank. Used (with each design\'s print cost) to compute profit for profit-split fundraisers.</p>';
}
add_action( 'save_post_configurator_garment', function ( $post_id ) {
    if ( ! isset( $_POST['tsa_garment_cost_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_garment_cost_nonce'] ) ), 'tsa_garment_cost_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;
    update_post_meta( $post_id, '_tsa_garment_cost', (float) ( $_POST['tsa_garment_cost'] ?? 0 ) );
} );

/* ─────────────────────────────────────────────────────────────────
   REPORTING ENGINE
───────────────────────────────────────────────────────────────── */
function tsa_fundraiser_report( int $id, bool $fresh = false ): array {
    $key = 'tsa_fr_report_' . $id;
    if ( ! $fresh ) { $cached = get_transient( $key ); if ( is_array( $cached ) ) return $cached; }

    $school  = sanitize_title( get_post_meta( $id, '_tsa_fr_school', true ) );
    $program = sanitize_title( get_post_meta( $id, '_tsa_fr_program', true ) );
    $party   = absint( get_post_meta( $id, '_tsa_fr_party_id', true ) );
    $type    = get_post_meta( $id, '_tsa_fr_type', true ) ?: 'per_item';
    $rate    = (float) get_post_meta( $id, '_tsa_fr_rate', true );
    $goal    = (float) get_post_meta( $id, '_tsa_fr_goal', true );
    $start   = get_post_meta( $id, '_tsa_fr_start', true );
    $end     = get_post_meta( $id, '_tsa_fr_end', true );

    $r = [ 'gross' => 0.0, 'units' => 0, 'cost' => 0.0, 'profit' => 0.0, 'payout' => 0.0,
        'orders' => 0, 'goal' => $goal, 'goal_pct' => null, 'incomplete' => false, 'type' => $type, 'rate' => $rate ];

    if ( ! function_exists( 'wc_get_orders' ) || ! $school ) {
        set_transient( $key, $r, 15 * MINUTE_IN_SECONDS );
        return $r;
    }

    $args = [ 'limit' => -1, 'status' => tsa_fundraiser_paid_statuses(), 'return' => 'objects' ];
    if ( $start ) $args['date_after']  = $start . ' 00:00:00';
    if ( $end )   $args['date_before'] = $end . ' 23:59:59';
    $orders = wc_get_orders( $args );

    foreach ( (array) $orders as $order ) {
        $counted = false;
        foreach ( $order->get_items() as $item ) {
            $raw = $item->get_meta( '_ac_configurator' ); if ( ! $raw ) continue;
            $ac  = json_decode( $raw, true ); if ( ! is_array( $ac ) ) continue;
            if ( sanitize_title( $ac['store_slug'] ?? '' ) !== $school ) continue;
            if ( $party && absint( $ac['party_id'] ?? 0 ) !== $party ) continue;

            if ( $program ) {
                $match = false;
                foreach ( (array) ( $ac['designs'] ?? [] ) as $did ) {
                    $terms = wp_get_post_terms( absint( $did ), 'tsa_design_category', [ 'fields' => 'slugs' ] );
                    if ( ! is_wp_error( $terms ) && in_array( $program, $terms, true ) ) { $match = true; break; }
                }
                if ( ! $match ) continue;
            }

            $units = array_sum( array_map( 'absint', (array) ( $ac['quantities'] ?? [] ) ) );
            $rev   = (float) $item->get_total();

            $gcost = (float) get_post_meta( absint( $ac['garment_id'] ?? 0 ), '_tsa_garment_cost', true );
            if ( $gcost <= 0 ) $r['incomplete'] = true;
            $pcost = 0.0;
            $zones = ! empty( $ac['zones'] ) ? (array) $ac['zones'] : array_keys( (array) ( $ac['designs'] ?? [] ) );
            foreach ( $zones as $z ) {
                $did = absint( $ac['designs'][ $z ] ?? 0 );
                if ( $did ) $pcost += (float) get_post_meta( $did, '_ac_print_cost', true );
            }

            $r['gross'] += $rev;
            $r['units'] += $units;
            $r['cost']  += $units * $gcost + $units * $pcost;
            $counted = true;
        }
        if ( $counted ) $r['orders']++;
    }

    $r['profit'] = $r['gross'] - $r['cost'];
    if ( $type === 'per_item' )          $r['payout'] = $r['units'] * $rate;
    elseif ( $type === 'pct_sales' )     $r['payout'] = $r['gross'] * $rate / 100;
    elseif ( $type === 'profit_split' )  $r['payout'] = max( 0, $r['profit'] ) * $rate / 100;
    if ( $goal > 0 ) $r['goal_pct'] = min( 100, (int) round( $r['payout'] / $goal * 100 ) );

    foreach ( [ 'gross', 'cost', 'profit', 'payout' ] as $k ) $r[ $k ] = round( $r[ $k ], 2 );

    set_transient( $key, $r, 15 * MINUTE_IN_SECONDS );
    return $r;
}

/** Admin report panel (always computes fresh). */
function tsa_fr_report_box( WP_Post $post ): void {
    if ( get_post_status( $post ) === 'auto-draft' ) { echo '<p style="color:#888">Save the campaign to see its report.</p>'; return; }
    $r   = tsa_fundraiser_report( $post->ID, true );
    $c   = function( $n ) { return '$' . number_format( (float) $n, 2 ); };
    $row = function( $label, $val, $strong = false ) { return sprintf( '<tr><td style="color:#666;padding:3px 0">%s</td><td style="text-align:right;padding:3px 0%s">%s</td></tr>', esc_html( $label ), $strong ? ';font-weight:700' : '', esc_html( $val ) ); };
    echo '<table style="width:100%;font-size:13px">';
    echo $row( 'Gross sales', $c( $r['gross'] ) );
    echo $row( 'Units sold', (string) $r['units'] );
    echo $row( 'Cost basis', $c( $r['cost'] ) );
    echo $row( 'Profit', $c( $r['profit'] ) );
    echo $row( 'Orders', (string) $r['orders'] );
    echo $row( 'Estimated payout', $c( $r['payout'] ), true );
    if ( $r['goal'] > 0 ) echo $row( 'Goal progress', $r['goal_pct'] . '% of ' . $c( $r['goal'] ) );
    echo '</table>';
    if ( $r['incomplete'] && $r['type'] === 'profit_split' ) {
        echo '<p style="color:#b32d2e;font-size:12px;margin-top:8px">⚠️ Some garments have no cost set — profit/payout is understated. Add costs on those garments (Cost Basis box) for an accurate profit split.</p>';
    }
    echo '<p style="color:#888;font-size:12px;margin-top:8px">Report-only — disburse the payout manually. Refreshes when you save.</p>';
}

/* ─────────────────────────────────────────────────────────────────
   PUBLIC CAMPAIGN — shortcode + renderer (single template calls render)
───────────────────────────────────────────────────────────────── */
add_shortcode( 'tsa_fundraiser', function ( $atts ) {
    $atts = shortcode_atts( [ 'id' => 0 ], $atts, 'tsa_fundraiser' );
    $id   = absint( $atts['id'] ) ?: ( is_singular( 'tsa_fundraiser' ) ? get_the_ID() : 0 );
    return $id ? tsa_fr_render( $id ) : '';
} );

function tsa_fr_render( int $id ): string {
    if ( get_post_type( $id ) !== 'tsa_fundraiser' ) return '';
    $r        = tsa_fundraiser_report( $id );
    $school   = sanitize_title( get_post_meta( $id, '_tsa_fr_school', true ) );
    $program  = sanitize_title( get_post_meta( $id, '_tsa_fr_program', true ) );
    $type     = get_post_meta( $id, '_tsa_fr_type', true ) ?: 'per_item';
    $rate     = (float) get_post_meta( $id, '_tsa_fr_rate', true );
    $goal     = (float) get_post_meta( $id, '_tsa_fr_goal', true );
    $end      = get_post_meta( $id, '_tsa_fr_end', true );
    $recip    = get_post_meta( $id, '_tsa_fr_recipient', true );

    $store_id = function_exists( 'tsa_sb_find_store_by_slug' ) ? tsa_sb_find_store_by_slug( $school ) : 0;
    $sname    = $store_id ? get_the_title( $store_id ) : ( function_exists( 'tsa_store_name' ) ? tsa_store_name( $school ) : ucwords( str_replace( '-', ' ', $school ) ) );
    $cols     = function_exists( 'tsa_get_school_colors' ) ? tsa_get_school_colors( $sname ) : [ 'primary' => '#5D4777', 'secondary' => '#9991A4' ];
    $primary  = ( $store_id ? get_post_meta( $store_id, '_tsa_school_primary', true ) : '' ) ?: $cols['primary'];
    $second   = ( $store_id ? get_post_meta( $store_id, '_tsa_school_secondary', true ) : '' ) ?: ( $cols['secondary'] ?? $primary );
    $txt      = function_exists( 'tsa_readable_text' ) ? tsa_readable_text( $primary ) : '#fff';

    // Shop CTA: program → Design Library scoped to store+program; else the school store.
    $shop = $program
        ? home_url( '/design-library/?store=' . rawurlencode( $school ) . '&cat=' . rawurlencode( $program ) )
        : ( $store_id ? home_url( '/schools/' . $school . '/' ) : home_url( '/design-library/?store=' . rawurlencode( $school ) ) );

    $rate_str = ( floor( $rate ) === $rate ) ? (string) (int) $rate : rtrim( rtrim( number_format( $rate, 2 ), '0' ), '.' );
    if ( $type === 'per_item' )         $contrib = '$' . $rate_str . ' per shirt to ' . $recip;
    elseif ( $type === 'pct_sales' )    $contrib = $rate_str . '% of sales to ' . $recip;
    else                                $contrib = $rate_str . '% of profit to ' . $recip;

    $days = '';
    if ( $end ) { $d = ( strtotime( $end . ' 23:59:59' ) - time() ); $days = $d > 0 ? ceil( $d / 86400 ) . ' days left' : 'Campaign ended'; }

    $raised = '$' . number_format( $r['payout'], 0 );
    $goalfmt= '$' . number_format( $goal, 0 );
    $pct    = $r['goal_pct'] !== null ? $r['goal_pct'] : 0;
    $title  = get_the_title( $id );
    $story  = apply_filters( 'the_content', get_post_field( 'post_content', $id ) );
    $share  = rawurlencode( get_permalink( $id ) );

    ob_start();
    ?>
    <div class="tsa-fr" style="max-width:760px;margin:0 auto">
        <div class="tsa-fr__hero" style="background:<?php echo esc_attr( $primary ); ?>;color:<?php echo esc_attr( $txt ); ?>;border-radius:16px;padding:26px 22px">
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;opacity:.85;margin-bottom:6px"><?php echo esc_html( $sname ); ?> · Fundraiser</div>
            <h1 style="margin:0;font-size:clamp(24px,4vw,34px);color:<?php echo esc_attr( $txt ); ?>"><?php echo esc_html( $title ); ?></h1>
            <?php if ( $recip ) : ?><div style="font-size:14px;opacity:.85;margin-top:6px">Supporting <?php echo esc_html( $recip ); ?></div><?php endif; ?>
        </div>

        <div style="background:#fff;border:1px solid rgba(0,0,0,.08);border-radius:16px;padding:22px;margin-top:-14px;position:relative">
            <div style="display:flex;justify-content:space-between;align-items:baseline;margin-bottom:8px">
                <span style="font-size:24px;font-weight:800;color:#222"><?php echo esc_html( $raised ); ?> <span style="font-size:14px;font-weight:500;color:#888">raised<?php echo $goal > 0 ? ' of ' . esc_html( $goalfmt ) : ''; ?></span></span>
                <?php if ( $goal > 0 ) : ?><span style="font-size:13px;color:#888"><?php echo (int) $pct; ?>%</span><?php endif; ?>
            </div>
            <?php if ( $goal > 0 ) : ?>
            <div style="height:12px;border-radius:999px;background:#eee;overflow:hidden"><div style="width:<?php echo (int) $pct; ?>%;height:100%;background:<?php echo esc_attr( $primary ); ?>"></div></div>
            <?php endif; ?>
            <div style="display:flex;gap:18px;flex-wrap:wrap;margin-top:12px;font-size:13px;color:#666">
                <span><?php echo (int) $r['units']; ?> shirts sold</span>
                <?php if ( $days ) : ?><span><?php echo esc_html( $days ); ?></span><?php endif; ?>
                <span><?php echo esc_html( $contrib ); ?></span>
            </div>

            <?php if ( $story ) : ?><div class="tsa-fr__story" style="margin:16px 0;color:#444;line-height:1.6"><?php echo wp_kses_post( $story ); ?></div><?php endif; ?>

            <div style="display:flex;gap:10px;flex-wrap:wrap;margin-top:8px">
                <a href="<?php echo esc_url( $shop ); ?>" class="tsa-btn tsa-btn-primary" style="background:<?php echo esc_attr( $primary ); ?>;color:<?php echo esc_attr( $txt ); ?>;border-color:<?php echo esc_attr( $primary ); ?>;text-decoration:none">Shop to support →</a>
                <a href="https://www.facebook.com/sharer/sharer.php?u=<?php echo $share; ?>" target="_blank" rel="noopener" class="tsa-btn tsa-btn-outline" style="text-decoration:none">Share on Facebook</a>
                <a href="https://twitter.com/intent/tweet?url=<?php echo $share; ?>" target="_blank" rel="noopener" class="tsa-btn tsa-btn-outline" style="text-decoration:none">Share on X</a>
            </div>
        </div>
    </div>
    <?php
    return ob_get_clean();
}
