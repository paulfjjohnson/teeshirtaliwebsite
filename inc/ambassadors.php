<?php
/**
 * TSA Ambassadors + QR (Phase 5 of the school framework).
 *
 * Ambassadors promote a school/program via a referral code + link (?ref=CODE).
 * A 30-day cookie attributes their sales (saved on the order as _tsa_ref_code);
 * a per-ambassador report tallies attributed orders/units/sales and the reward
 * owed. Rewards are REPORT-ONLY (pay manually). QR codes for any URL are
 * generated offline in-browser (vendored qrcodejs — no external calls).
 *
 * Pure referral tracking — codes do NOT discount the shopper (by design).
 */
defined( 'ABSPATH' ) || exit;

/* ─────────────────────────────────────────────────────────────────
   CPT + helpers
───────────────────────────────────────────────────────────────── */
add_action( 'init', function () {
    register_post_type( 'tsa_ambassador', [
        'labels' => [
            'name' => 'Ambassadors', 'singular_name' => 'Ambassador',
            'add_new_item' => 'Add Ambassador', 'edit_item' => 'Edit Ambassador', 'menu_name' => 'Ambassadors',
        ],
        'public'       => false,
        'show_ui'      => true,
        'show_in_menu' => true,
        'menu_icon'    => 'dashicons-megaphone',
        'menu_position'=> 28,
        'supports'     => [ 'title' ],
    ] );
} );

function tsa_amb_types(): array {
    return [ 'student' => 'Student', 'parent' => 'Parent', 'coach' => 'Coach / Sponsor', 'booster' => 'Booster Club', 'faculty' => 'Faculty' ];
}

/** Find an ambassador post id by referral code (0 if none). */
function tsa_ambassador_by_code( string $code ): int {
    if ( $code === '' ) return 0;
    $q = get_posts( [ 'post_type' => 'tsa_ambassador', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
        'meta_query' => [ [ 'key' => '_tsa_amb_code', 'value' => $code ] ] ] );
    return $q ? (int) $q[0] : 0;
}

/** Build a unique referral code from school / program / first name. */
function tsa_amb_generate_code( string $school, string $program, string $name ): string {
    $clean = function( $s ) { return strtoupper( preg_replace( '/[^A-Za-z0-9]/', '', (string) $s ) ); };
    $first = $name ? explode( ' ', trim( $name ) )[0] : '';
    $bits  = array_filter( [ $clean( $school ), $clean( $program ), $clean( $first ) ] );
    $code  = implode( '-', $bits ) ?: 'TSA-AMB';
    $base = $code; $i = 2;
    while ( tsa_ambassador_by_code( $code ) ) { $code = $base . '-' . $i; $i++; }
    return $code;
}

function tsa_amb_referral_url( string $code ): string {
    return add_query_arg( 'ref', rawurlencode( $code ), home_url( '/' ) );
}

/* ─────────────────────────────────────────────────────────────────
   REFERRAL TRACKING
───────────────────────────────────────────────────────────────── */
add_action( 'init', function () {
    if ( is_admin() || empty( $_GET['ref'] ) ) return;
    $code = sanitize_text_field( wp_unslash( $_GET['ref'] ) );
    if ( $code !== '' ) {
        setcookie( 'tsa_ref', $code, time() + 30 * DAY_IN_SECONDS, COOKIEPATH ?: '/', COOKIE_DOMAIN );
        $_COOKIE['tsa_ref'] = $code;
    }
} );

add_action( 'woocommerce_checkout_create_order', function ( $order ) {
    if ( ! empty( $_COOKIE['tsa_ref'] ) ) {
        $code = sanitize_text_field( wp_unslash( $_COOKIE['tsa_ref'] ) );
        if ( $code !== '' ) $order->update_meta_data( '_tsa_ref_code', $code );
    }
}, 10, 1 );

/* ─────────────────────────────────────────────────────────────────
   CONFIG META BOX
───────────────────────────────────────────────────────────────── */
add_action( 'add_meta_boxes', function () {
    add_meta_box( 'tsa_amb_cfg', 'Ambassador Settings', 'tsa_amb_cfg_box', 'tsa_ambassador', 'normal', 'high' );
    add_meta_box( 'tsa_amb_report', 'Reward Report', 'tsa_amb_report_box', 'tsa_ambassador', 'side', 'default' );
} );

function tsa_amb_cfg_box( WP_Post $post ): void {
    wp_nonce_field( 'tsa_amb_save', 'tsa_amb_nonce' );
    $g = function( $k, $d = '' ) use ( $post ) { return get_post_meta( $post->ID, $k, true ) ?: $d; };
    $school  = $g( '_tsa_amb_school' );
    $program = $g( '_tsa_amb_program' );
    $type    = $g( '_tsa_amb_type', 'parent' );
    $code    = $g( '_tsa_amb_code' );
    $rtype   = $g( '_tsa_amb_reward_type', 'none' );
    $rate    = $g( '_tsa_amb_reward_rate' );
    $notes   = $g( '_tsa_amb_reward_notes' );
    $email   = $g( '_tsa_amb_email' );
    $schools = function_exists( 'tsa_school_store_records' ) ? tsa_school_store_records() : [];
    $url     = $code ? tsa_amb_referral_url( $code ) : '';
    ?>
    <style>
    .tsa-amb-grid{display:grid;grid-template-columns:160px 1fr;gap:10px 14px;align-items:center;font-size:13px;max-width:680px}
    .tsa-amb-grid label{font-weight:600}
    .tsa-amb-grid input[type=text],.tsa-amb-grid input[type=number],.tsa-amb-grid input[type=email],.tsa-amb-grid select{width:100%;max-width:340px;padding:5px 8px;border:1px solid #ddd;border-radius:4px}
    .tsa-amb-grid .desc{grid-column:2;color:#888;font-size:12px;margin:-4px 0 4px}
    </style>
    <div class="tsa-amb-grid">
        <label>School</label>
        <select name="tsa_amb_school">
            <option value="">— Select a school —</option>
            <?php foreach ( $schools as $s ) : ?>
            <option value="<?php echo esc_attr( $s['slug'] ); ?>" <?php selected( $school, $s['slug'] ); ?>><?php echo esc_html( $s['name'] ); ?></option>
            <?php endforeach; ?>
        </select>

        <label>Program</label>
        <input type="text" name="tsa_amb_program" value="<?php echo esc_attr( $program ); ?>" placeholder="band (optional)">

        <label>Type</label>
        <select name="tsa_amb_type">
            <?php foreach ( tsa_amb_types() as $k => $lbl ) : ?>
            <option value="<?php echo esc_attr( $k ); ?>" <?php selected( $type, $k ); ?>><?php echo esc_html( $lbl ); ?></option>
            <?php endforeach; ?>
        </select>

        <label>Referral code</label>
        <input type="text" name="tsa_amb_code" value="<?php echo esc_attr( $code ); ?>" placeholder="auto-generated from school + program + name">
        <div class="desc">Leave blank to auto-generate (e.g. <code>DUTCHTOWN-BAND-PAUL</code>). Shared as <code>?ref=CODE</code>.</div>

        <label>Reward type</label>
        <select name="tsa_amb_reward_type">
            <option value="none"      <?php selected( $rtype, 'none' ); ?>>None / manual (track only)</option>
            <option value="per_item"  <?php selected( $rtype, 'per_item' ); ?>>Per-item ($ per shirt sold)</option>
            <option value="pct_sales" <?php selected( $rtype, 'pct_sales' ); ?>>Percentage of attributed sales</option>
        </select>

        <label>Reward rate</label>
        <input type="number" step="0.01" min="0" name="tsa_amb_reward_rate" value="<?php echo esc_attr( $rate ); ?>">
        <div class="desc">$ per shirt (per-item) or a % (sales). Ignored for None/manual.</div>

        <label>Reward notes</label>
        <input type="text" name="tsa_amb_reward_notes" value="<?php echo esc_attr( $notes ); ?>" placeholder="e.g. free shirt + recognition on store page">

        <label>Contact email</label>
        <input type="email" name="tsa_amb_email" value="<?php echo esc_attr( $email ); ?>" placeholder="ambassador@email.com">
    </div>

    <?php if ( $url ) : ?>
    <div style="margin-top:16px;display:flex;gap:20px;align-items:flex-start;flex-wrap:wrap">
        <div>
            <p style="font-weight:600;margin:0 0 4px">Referral link</p>
            <input type="text" readonly value="<?php echo esc_attr( $url ); ?>" style="width:340px;max-width:100%;padding:5px 8px;border:1px solid #ddd;border-radius:4px" onclick="this.select()">
        </div>
        <div>
            <p style="font-weight:600;margin:0 0 4px">QR code</p>
            <div id="tsa-amb-qr" data-url="<?php echo esc_attr( $url ); ?>" style="width:160px;height:160px"></div>
            <a id="tsa-amb-qr-dl" class="button" download="ambassador-qr.png" href="#" style="margin-top:8px">Download QR</a>
        </div>
    </div>
    <script>
    (function(){
        var el=document.getElementById('tsa-amb-qr'); if(!el||typeof QRCode==='undefined') return;
        new QRCode(el,{text:el.getAttribute('data-url'),width:160,height:160});
        setTimeout(function(){ var c=el.querySelector('canvas'),dl=document.getElementById('tsa-amb-qr-dl'); if(c&&dl) dl.href=c.toDataURL('image/png'); },350);
    })();
    </script>
    <?php else : ?>
    <p style="color:#888;font-size:12px;margin-top:12px">Save the ambassador to generate the referral code, link, and QR.</p>
    <?php endif; ?>
    <?php
}

add_action( 'save_post_tsa_ambassador', 'tsa_amb_save' );
function tsa_amb_save( int $post_id ): void {
    if ( ! isset( $_POST['tsa_amb_nonce'] ) || ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_amb_nonce'] ) ), 'tsa_amb_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    $school  = sanitize_title( wp_unslash( $_POST['tsa_amb_school'] ?? '' ) );
    $program = sanitize_title( wp_unslash( $_POST['tsa_amb_program'] ?? '' ) );
    $type    = sanitize_key( $_POST['tsa_amb_type'] ?? 'parent' );
    $rtype   = sanitize_key( $_POST['tsa_amb_reward_type'] ?? 'none' );

    // Code: keep what's typed (normalized), else auto-generate a unique one.
    $code = strtoupper( preg_replace( '/[^A-Za-z0-9\-]/', '', (string) wp_unslash( $_POST['tsa_amb_code'] ?? '' ) ) );
    if ( $code === '' ) {
        $code = tsa_amb_generate_code( $school, $program, get_the_title( $post_id ) );
    }

    update_post_meta( $post_id, '_tsa_amb_school',       $school );
    update_post_meta( $post_id, '_tsa_amb_program',      $program );
    update_post_meta( $post_id, '_tsa_amb_type',         array_key_exists( $type, tsa_amb_types() ) ? $type : 'parent' );
    update_post_meta( $post_id, '_tsa_amb_code',         $code );
    update_post_meta( $post_id, '_tsa_amb_reward_type',  in_array( $rtype, [ 'none', 'per_item', 'pct_sales' ], true ) ? $rtype : 'none' );
    update_post_meta( $post_id, '_tsa_amb_reward_rate',  (float) ( $_POST['tsa_amb_reward_rate'] ?? 0 ) );
    update_post_meta( $post_id, '_tsa_amb_reward_notes', sanitize_text_field( wp_unslash( $_POST['tsa_amb_reward_notes'] ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_amb_email',        sanitize_email( wp_unslash( $_POST['tsa_amb_email'] ?? '' ) ) );

    delete_transient( 'tsa_amb_report_' . $post_id );
}

/* ─────────────────────────────────────────────────────────────────
   REWARD REPORTING (report-only)
───────────────────────────────────────────────────────────────── */
function tsa_ambassador_report( int $id, bool $fresh = false ): array {
    $key = 'tsa_amb_report_' . $id;
    if ( ! $fresh ) { $c = get_transient( $key ); if ( is_array( $c ) ) return $c; }

    $code  = (string) get_post_meta( $id, '_tsa_amb_code', true );
    $rtype = get_post_meta( $id, '_tsa_amb_reward_type', true ) ?: 'none';
    $rate  = (float) get_post_meta( $id, '_tsa_amb_reward_rate', true );
    $r = [ 'orders' => 0, 'units' => 0, 'sales' => 0.0, 'reward' => 0.0, 'type' => $rtype, 'rate' => $rate ];

    if ( ! function_exists( 'wc_get_orders' ) || $code === '' ) { set_transient( $key, $r, 15 * MINUTE_IN_SECONDS ); return $r; }

    $statuses = function_exists( 'tsa_fundraiser_paid_statuses' ) ? tsa_fundraiser_paid_statuses() : [ 'processing', 'completed' ];
    $orders   = wc_get_orders( [ 'limit' => -1, 'status' => $statuses, 'return' => 'objects',
        'meta_key' => '_tsa_ref_code', 'meta_value' => $code ] );

    foreach ( (array) $orders as $o ) {
        $r['orders']++;
        $r['sales'] += (float) $o->get_subtotal();
        foreach ( $o->get_items() as $it ) $r['units'] += (int) $it->get_quantity();
    }
    if ( $rtype === 'per_item' )      $r['reward'] = $r['units'] * $rate;
    elseif ( $rtype === 'pct_sales' ) $r['reward'] = $r['sales'] * $rate / 100;
    $r['sales']  = round( $r['sales'], 2 );
    $r['reward'] = round( $r['reward'], 2 );

    set_transient( $key, $r, 15 * MINUTE_IN_SECONDS );
    return $r;
}

function tsa_amb_report_box( WP_Post $post ): void {
    if ( get_post_status( $post ) === 'auto-draft' ) { echo '<p style="color:#888">Save to see the report.</p>'; return; }
    $r   = tsa_ambassador_report( $post->ID, true );
    $row = function( $l, $v, $b = false ) { return sprintf( '<tr><td style="color:#666;padding:3px 0">%s</td><td style="text-align:right;padding:3px 0%s">%s</td></tr>', esc_html( $l ), $b ? ';font-weight:700' : '', esc_html( $v ) ); };
    echo '<table style="width:100%;font-size:13px">';
    echo $row( 'Attributed orders', (string) $r['orders'] );
    echo $row( 'Shirts sold', (string) $r['units'] );
    echo $row( 'Attributed sales', '$' . number_format( $r['sales'], 2 ) );
    if ( $r['type'] !== 'none' ) echo $row( 'Reward owed', '$' . number_format( $r['reward'], 2 ), true );
    echo '</table>';
    echo '<p style="color:#888;font-size:12px;margin-top:8px">Report-only — pay/issue the reward manually. Refreshes on save.</p>';
}

/* ─────────────────────────────────────────────────────────────────
   QR — offline (vendored qrcodejs) on the editor + a general QR tool
───────────────────────────────────────────────────────────────── */
add_action( 'admin_enqueue_scripts', function () {
    $s = get_current_screen();
    if ( $s && ( $s->post_type === 'tsa_ambassador' || strpos( (string) $s->id, 'tsa-qr-codes' ) !== false ) ) {
        wp_enqueue_script( 'tsa-qrcode', get_stylesheet_directory_uri() . '/assets/js/qrcode.min.js', [], '1.0.0', true );
    }
} );

add_action( 'admin_menu', function () {
    add_submenu_page( 'edit.php?post_type=tsa_ambassador', 'QR Codes', 'QR Codes', 'edit_posts', 'tsa-qr-codes', 'tsa_qr_tool_page' );
} );

function tsa_qr_tool_page(): void {
    if ( ! current_user_can( 'edit_posts' ) ) return;
    ?>
    <div class="wrap">
        <h1>QR Codes</h1>
        <p>Generate a downloadable QR for any URL — a school store, program, fundraiser, drop, or ambassador link. Rendered in your browser (nothing leaves the site).</p>
        <p>
            <input type="text" id="tsa-qr-url" class="regular-text" style="width:480px;max-width:100%" placeholder="https://teeshirtali.com/schools/dutchtown/" value="<?php echo esc_attr( home_url( '/' ) ); ?>">
            <button type="button" class="button button-primary" id="tsa-qr-go">Generate</button>
        </p>
        <div id="tsa-qr-out" style="width:240px;height:240px;margin-top:10px"></div>
        <p><a id="tsa-qr-dl" class="button" download="qr-code.png" href="#" style="display:none">Download PNG</a></p>
    </div>
    <script>
    (function(){
        var out=document.getElementById('tsa-qr-out'), inp=document.getElementById('tsa-qr-url'),
            go=document.getElementById('tsa-qr-go'), dl=document.getElementById('tsa-qr-dl'), qr=null;
        function render(){
            if(typeof QRCode==='undefined'){ out.textContent='QR library failed to load.'; return; }
            out.innerHTML=''; dl.style.display='none';
            var url=(inp.value||'').trim(); if(!url) return;
            qr=new QRCode(out,{text:url,width:240,height:240});
            setTimeout(function(){ var c=out.querySelector('canvas'); if(c){ dl.href=c.toDataURL('image/png'); dl.style.display='inline-block'; } },350);
        }
        if(go) go.addEventListener('click',render);
        render();
    })();
    </script>
    <?php
}

/* Quick "Code" + "Reward" columns on the Ambassadors list. */
add_filter( 'manage_tsa_ambassador_posts_columns', function ( $cols ) {
    $cols['tsa_code']   = 'Code';
    $cols['tsa_school'] = 'School';
    return $cols;
} );
add_action( 'manage_tsa_ambassador_posts_custom_column', function ( $col, $post_id ) {
    if ( $col === 'tsa_code' )   echo '<code>' . esc_html( get_post_meta( $post_id, '_tsa_amb_code', true ) ) . '</code>';
    if ( $col === 'tsa_school' ) echo esc_html( get_post_meta( $post_id, '_tsa_amb_school', true ) );
}, 10, 2 );
