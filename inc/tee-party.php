<?php
defined( 'ABSPATH' ) || exit;

/* ═══════════════════════════════════════════════════════════════════
   TEE PARTY SYSTEM
   Each party is a WordPress Page using template-tee-party.php.

   Meta keys:
     _tsa_party_active         '1'/'0'
     _tsa_party_theme          string
     _tsa_party_start_date     datetime-local
     _tsa_party_end_date       datetime-local (deadline)
     _tsa_party_grace_minutes  int
     _tsa_party_drop_interval  int (minutes)
     _tsa_party_apparel_mode   'all' | 'specific'
     _tsa_party_garment_ids    JSON int[]
     _tsa_party_schedule       JSON [{ design_id, reveal_at }]
═══════════════════════════════════════════════════════════════════ */


/* ─── Party timezone ────────────────────────────────────────────────
   The zone Tee Party reveal times are interpreted in. Uses the WP timezone
   when it's a real city zone, but if WordPress is left on plain "UTC" we fall
   back to the business zone (Central) instead of revealing everything hours
   early. Override with the TSA_PARTY_TZ constant or the 'tsa_party_timezone'
   filter (e.g. 'America/New_York').
─────────────────────────────────────────────────────────────────── */
function tsa_party_timezone(): DateTimeZone {
    $tz = defined( 'TSA_PARTY_TZ' ) ? (string) TSA_PARTY_TZ : '';
    if ( $tz === '' ) {
        $name = wp_timezone()->getName();
        // Plain UTC / zero-offset → treat as misconfiguration, use Central.
        $tz = in_array( $name, [ 'UTC', 'Z', '+00:00', '00:00' ], true ) ? 'America/Chicago' : $name;
    }
    $tz = apply_filters( 'tsa_party_timezone', $tz );
    try { return new DateTimeZone( $tz ); }
    catch ( \Exception $e ) { return wp_timezone(); }
}

/* ─── Timezone-aware time parsing ───────────────────────────────────
   datetime-local fields are naive ("Y-m-dTH:i"). Parse them in the party
   timezone and return a true UTC timestamp — so the time the admin types
   matches local wall-clock (DST auto-handled). Compare against time() (real
   UTC), never current_time().
─────────────────────────────────────────────────────────────────── */
function tsa_party_ts( $str ) {
    $str = trim( (string) $str );
    if ( $str === '' ) return 0;
    try {
        return ( new DateTimeImmutable( $str, tsa_party_timezone() ) )->getTimestamp();
    } catch ( \Exception $e ) {
        return (int) strtotime( $str );
    }
}

/* ─── 0a. QUANTITY DISCOUNT TIERS ───────────────────────────────────
   "Buy more, save more" tiers for a Tee Party. Stored per-party as JSON
   ( _tsa_party_discount_tiers ), falling back to the standard policy.
   Each tier = [ 'min' => <qty threshold>, 'pct' => <percent off> ].
─────────────────────────────────────────────────────────────────── */
function tsa_party_discount_tiers( int $party_id = 0 ): array {
    $raw   = $party_id ? get_post_meta( $party_id, '_tsa_party_discount_tiers', true ) : '';
    $tiers = $raw ? json_decode( $raw, true ) : null;

    if ( ! is_array( $tiers ) || empty( $tiers ) ) {
        // Standard Tee Party policy (editable per-party in the meta box).
        $tiers = apply_filters( 'tsa_party_default_discount_tiers', [
            [ 'min' => 2, 'pct' => 10 ],
            [ 'min' => 4, 'pct' => 15 ],
            [ 'min' => 6, 'pct' => 20 ],
        ] );
    }

    $clean = [];
    foreach ( $tiers as $t ) {
        $min = max( 1, (int) ( $t['min'] ?? 0 ) );
        $pct = max( 0, min( 100, (float) ( $t['pct'] ?? 0 ) ) );
        if ( $pct > 0 ) {
            $clean[] = [ 'min' => $min, 'pct' => $pct, 'label' => sanitize_text_field( (string) ( $t['label'] ?? '' ) ) ];
        }
    }
    usort( $clean, function( $a, $b ) { return $a['min'] <=> $b['min']; } );
    return $clean;
}

/** Percent off that applies to a given shirt quantity for a party. */
function tsa_party_discount_for_qty( int $qty, int $party_id = 0 ): float {
    $pct = 0.0;
    foreach ( tsa_party_discount_tiers( $party_id ) as $t ) {
        if ( $qty >= $t['min'] ) {
            $pct = (float) $t['pct'];
        }
    }
    return $pct;
}

/** Human-readable label for each tier, e.g. "1–3 shirts" / "6+ shirts". */
function tsa_party_discount_tier_labels( int $party_id = 0 ): array {
    $tiers = tsa_party_discount_tiers( $party_id );
    $out   = [];
    $n     = count( $tiers );
    foreach ( $tiers as $i => $t ) {
        $min  = (int) $t['min'];
        $next = isset( $tiers[ $i + 1 ] ) ? (int) $tiers[ $i + 1 ]['min'] : 0;

        // Auto-derived fallback wording ("2–3 Tees" / "6+ Tees").
        if ( $i === $n - 1 || ! $next ) {
            $auto = $min . '+ Tees';
        } elseif ( $next - 1 > $min ) {
            $auto = $min . '–' . ( $next - 1 ) . ' Tees';
        } else {
            $auto = $min . ' Tees';
        }

        $pct      = $t['pct'];
        $pctLabel = ( floor( $pct ) === (float) $pct ) ? (string) (int) $pct : rtrim( rtrim( number_format( $pct, 2 ), '0' ), '.' );
        $out[]    = [
            'pct'   => $pct,
            'label' => ( $t['label'] ?? '' ) !== '' ? $t['label'] : $auto, // custom label wins
            'off'   => $pctLabel . '% off',
        ];
    }
    return $out;
}

/* ─── 0a2. CONFIGURATOR BANNER = PARTY IDENTITY ─────────────────────
   When a design is launched into the Apparel Configurator from a Tee
   Party ("Configure & Order"), the plugin shortcode asks the theme to
   brand the "Designing for …" banner. Show the party's NAME + its
   per-party colors instead of the underlying store (which is "TSA Main"
   for a non-school party). Cart routing still uses the real store slug.
─────────────────────────────────────────────────────────────────── */
add_filter( 'ac_configurator_party_context', 'tsa_configurator_party_context', 10, 3 );
function tsa_configurator_party_context( $ctx, $party_id, $store_slug ) {
    $party_id = (int) $party_id;
    if ( $party_id < 1 || get_post_type( $party_id ) !== 'page' ) return $ctx;
    if ( get_post_meta( $party_id, '_wp_page_template', true ) !== 'template-tee-party.php' ) return $ctx;

    // Same per-party color meta the Tee Party page itself uses.
    $accent    = get_post_meta( $party_id, '_tsa_party_color_accent',    true ) ?: '#d8a85f';
    $highlight = get_post_meta( $party_id, '_tsa_party_color_highlight', true ) ?: '#fec2c0';
    $text      = function_exists( 'tsa_readable_text' ) ? tsa_readable_text( $accent ) : '#252124';

    return [
        'name'  => get_the_title( $party_id ),
        'color' => [
            'primary'   => $accent,
            'secondary' => $highlight,
            'text'      => $text,
        ],
    ];
}

/* ─── 0b. APPLY DISCOUNT AT CART / CHECKOUT ─────────────────────────
   Totals each party's shirts across the WHOLE cart and applies the
   matching tier % as a negative fee. The party id rides along in the
   configurator cart-item data ( ac_configurator.party_id ), set by the
   Apparel Configurator when launched from a party design.
─────────────────────────────────────────────────────────────────── */
add_action( 'woocommerce_cart_calculate_fees', 'tsa_apply_party_quantity_discounts', 20 );
function tsa_apply_party_quantity_discounts( $cart ): void {
    if ( is_admin() && ! wp_doing_ajax() ) return;
    if ( ! ( $cart instanceof WC_Cart ) ) return;

    $groups = []; // party_id => [ 'qty' => int, 'subtotal' => float ]
    foreach ( $cart->get_cart() as $item ) {
        $pid = (int) ( $item['ac_configurator']['party_id'] ?? 0 );
        if ( $pid < 1 ) continue;
        if ( ! isset( $groups[ $pid ] ) ) $groups[ $pid ] = [ 'qty' => 0, 'subtotal' => 0.0 ];
        $groups[ $pid ]['qty']      += (int) $item['quantity'];
        $groups[ $pid ]['subtotal'] += (float) ( $item['line_subtotal'] ?? 0 );
    }

    foreach ( $groups as $pid => $g ) {
        $pct = tsa_party_discount_for_qty( $g['qty'], $pid );
        if ( $pct <= 0 || $g['subtotal'] <= 0 ) continue;
        $amount = round( $g['subtotal'] * $pct / 100, 2 );
        if ( $amount <= 0 ) continue;
        $title    = get_the_title( $pid ) ?: 'Tee Party';
        $pctLabel = ( floor( $pct ) === (float) $pct ) ? (string) (int) $pct : rtrim( rtrim( number_format( $pct, 2 ), '0' ), '.' );
        $label    = sprintf( '%s — %s%% off (%d shirts)', $title, $pctLabel, $g['qty'] );
        $cart->add_fee( $label, -$amount, false );
    }
}

/* ─── 0. SHARED STATUS HELPER ───────────────────────────────────────
   Returns a normalised status array for any party page. Used by both
   the single party template and the hub listing template.
─────────────────────────────────────────────────────────────────── */
function tsa_party_status( int $page_id ): array {
    $active     = get_post_meta( $page_id, '_tsa_party_active',        true ) === '1';
    $start_date = get_post_meta( $page_id, '_tsa_party_start_date',    true );
    $end_date   = get_post_meta( $page_id, '_tsa_party_end_date',      true );
    $grace      = (int) get_post_meta( $page_id, '_tsa_party_grace_minutes', true );
    $schedule   = json_decode( get_post_meta( $page_id, '_tsa_party_schedule', true ) ?: '[]', true ) ?: [];

    $now       = time();
    $start_ts  = tsa_party_ts( $start_date );
    $end_ts    = tsa_party_ts( $end_date );
    $grace_end = $end_ts ? $end_ts + $grace * 60 : 0;

    $status = 'upcoming';
    if ( $active ) {
        if ( $grace_end && $now > $grace_end )       $status = 'closed';
        elseif ( $end_ts && $now > $end_ts )         $status = 'grace';
        elseif ( $start_ts && $now < $start_ts )     $status = 'upcoming';
        else                                          $status = 'live';
    } else {
        $status = $start_ts && $now < $start_ts ? 'upcoming' : 'inactive';
    }

    // Count revealed designs
    $revealed = 0;
    foreach ( $schedule as $row ) {
        $rt = tsa_party_ts( $row['reveal_at'] ?? '' );
        if ( $rt && $now >= $rt ) $revealed++;
    }

    // The timestamp the front-end card should count down to
    $countdown_to = 0;
    if ( $status === 'live' )        $countdown_to = $end_ts;
    elseif ( $status === 'grace' )   $countdown_to = $grace_end;
    elseif ( $status === 'upcoming' )$countdown_to = $start_ts;

    return [
        'status'         => $status,                       // live | grace | upcoming | closed | inactive
        'start_ts'       => $start_ts,
        'end_ts'         => $end_ts,
        'grace_end_ts'   => $grace_end,
        'countdown_to'   => $countdown_to,
        'countdown_iso'  => $countdown_to ? date( 'c', $countdown_to ) : '',
        'total_designs'  => count( $schedule ),
        'revealed'       => $revealed,
    ];
}


/* ─── 1. META BOX ───────────────────────────────────────────────── */

add_action( 'add_meta_boxes', 'tsa_party_meta_boxes' );
function tsa_party_meta_boxes(): void {
    add_meta_box( 'tsa_party_settings', 'Tee Party Settings',
        'tsa_render_party_meta_box', 'page', 'normal', 'high' );
}

function tsa_render_party_meta_box( WP_Post $post ): void {
    if ( get_post_meta( $post->ID, '_wp_page_template', true ) !== 'template-tee-party.php' ) {
        echo '<p style="color:#888;font-size:13px">Switch the page template to <strong>TSA Tee Party</strong> to enable these settings.</p>';
        return;
    }

    // Needed for the Party Image media picker below.
    wp_enqueue_media();

    wp_nonce_field( 'tsa_party_save', 'tsa_party_nonce' );

    $active       = get_post_meta( $post->ID, '_tsa_party_active',        true );
    $theme        = get_post_meta( $post->ID, '_tsa_party_theme',         true );
    $start_date   = get_post_meta( $post->ID, '_tsa_party_start_date',    true );
    $end_date     = get_post_meta( $post->ID, '_tsa_party_end_date',      true );
    $grace        = get_post_meta( $post->ID, '_tsa_party_grace_minutes', true ) ?: 30;
    $interval     = get_post_meta( $post->ID, '_tsa_party_drop_interval', true ) ?: 60;
    $per_drop     = get_post_meta( $post->ID, '_tsa_party_designs_per_drop', true ) ?: 4;
    $apparel_mode = get_post_meta( $post->ID, '_tsa_party_apparel_mode',  true ) ?: 'all';
    $garment_ids  = json_decode( get_post_meta( $post->ID, '_tsa_party_garment_ids', true ) ?: '[]', true ) ?: [];
    $garment_colors_map = json_decode( get_post_meta( $post->ID, '_tsa_party_garment_colors', true ) ?: '{}', true ) ?: [];
    $schedule     = json_decode( get_post_meta( $post->ID, '_tsa_party_schedule',    true ) ?: '[]', true ) ?: [];
    $party_store  = get_post_meta( $post->ID, '_tsa_party_store_slug', true );
    $released     = get_post_meta( $post->ID, '_tsa_party_released',   true ) === '1';
    $school_recs  = function_exists( 'tsa_school_store_records' ) ? tsa_school_store_records() : [];

    // Colors (defaults = TSA light blush theme; accent gold, highlight pink)
    $c_accent    = get_post_meta( $post->ID, '_tsa_party_color_accent',    true ) ?: '#d8a85f';
    $c_highlight = get_post_meta( $post->ID, '_tsa_party_color_highlight', true ) ?: '#fec2c0';
    $c_bg_top    = get_post_meta( $post->ID, '_tsa_party_color_bg_top',    true ) ?: '#ffffff';
    $c_bg_bottom = get_post_meta( $post->ID, '_tsa_party_color_bg_bottom', true ) ?: '#fff1f0';
    $c_text      = get_post_meta( $post->ID, '_tsa_party_color_text',      true ) ?: '#252124';

    // Party image (hub card + page hero). Falls back to the page Featured Image.
    $img_id  = (int) get_post_meta( $post->ID, '_tsa_party_image_id', true );
    $img_url = $img_id ? wp_get_attachment_image_url( $img_id, 'medium' ) : '';

    // All active garments for apparel picker
    $all_garments = get_posts( [
        'post_type' => 'configurator_garment', 'post_status' => 'publish',
        'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC',
        'meta_query' => [ [ 'key' => '_ac_is_active', 'value' => '1' ] ],
    ] );

    // All designs for the schedule picker. Include drafts/pending too — drop
    // designs are often uploaded unpublished (and "Show in configurator" off) to
    // stay hidden until they drop, but the admin still needs to schedule them.
    $design_cpt     = post_type_exists( 'tsa_design' ) ? 'tsa_design' : 'configurator_design';
    $cat_tax_picker = $design_cpt === 'tsa_design' ? 'tsa_design_category' : 'design_category';
    $design_posts   = get_posts( [ 'post_type' => $design_cpt, 'post_status' => [ 'publish', 'draft', 'pending' ], 'posts_per_page' => 500, 'orderby' => 'title', 'order' => 'ASC' ] );
    // Compact list for the JS thumbnail picker: id, title, thumbnail, category ids.
    $design_data = array_map( function ( $dp ) use ( $cat_tax_picker ) {
        $cids = wp_get_post_terms( $dp->ID, $cat_tax_picker, [ 'fields' => 'ids' ] );
        return [
            'id'   => $dp->ID,
            't'    => $dp->post_title,
            'img'  => get_post_meta( $dp->ID, '_design_preview_url', true ) ?: ( get_the_post_thumbnail_url( $dp->ID, 'thumbnail' ) ?: '' ),
            'cats' => is_wp_error( $cids ) ? [] : array_map( 'intval', $cids ),
        ];
    }, $design_posts );

    // Category list for the picker filter — hierarchical, indented <option>s plus
    // a self+descendants map so picking a parent includes its sub-categories.
    $picker_cats = get_terms( [ 'taxonomy' => $cat_tax_picker, 'hide_empty' => false ] );
    if ( is_wp_error( $picker_cats ) ) $picker_cats = [];
    $cat_by_parent = [];
    foreach ( $picker_cats as $ct ) { $cat_by_parent[ (int) $ct->parent ][] = $ct; }
    $walk_cats = function ( $parent, $depth ) use ( &$walk_cats, &$cat_by_parent ) {
        $out = '';
        foreach ( $cat_by_parent[ $parent ] ?? [] as $ct ) {
            $out .= '<option value="' . (int) $ct->term_id . '">' . str_repeat( '— ', $depth ) . esc_html( $ct->name ) . '</option>';
            $out .= $walk_cats( $ct->term_id, $depth + 1 );
        }
        return $out;
    };
    $picker_cat_options = $walk_cats( 0, 0 );
    $cat_descendants = [];
    foreach ( $picker_cats as $ct ) {
        $ids = [ (int) $ct->term_id ]; $stack = [ (int) $ct->term_id ];
        while ( $stack ) { $p = array_pop( $stack ); foreach ( $cat_by_parent[ $p ] ?? [] as $ch ) { $ids[] = (int) $ch->term_id; $stack[] = (int) $ch->term_id; } }
        $cat_descendants[ (int) $ct->term_id ] = array_values( array_unique( $ids ) );
    }
    ?>
    <style>
    .tsa-pb { font-size:13px }
    .tsa-pb h3 { font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:.5px;color:#555;margin:18px 0 8px;padding-top:14px;border-top:1px solid #eee }
    .tsa-pb h3:first-child { border-top:none;margin-top:0;padding-top:0 }
    .tsa-prow { display:flex;gap:10px;align-items:flex-start;margin-bottom:8px;flex-wrap:wrap }
    .tsa-prow > label { font-weight:600;min-width:150px;padding-top:5px;flex-shrink:0 }
    .tsa-prow .desc { color:#888;font-size:12px;margin-top:2px }
    .tsa-pb input[type=text],.tsa-pb input[type=number],.tsa-pb input[type=datetime-local],.tsa-pb select { padding:4px 7px;border:1px solid #ddd;border-radius:4px;font-size:13px }
    .tsa-pb input[type=text] { width:260px }
    .tsa-sched { width:100%;border-collapse:collapse;margin-top:6px }
    .tsa-sched th { font-size:11px;color:#666;text-align:left;padding:3px 6px;border-bottom:1px solid #eee }
    .tsa-sched td { padding:4px 6px;border-bottom:1px solid #f5f5f5;vertical-align:middle }
    .tsa-sched select { width:100%;font-size:12px;padding:3px }
    .tsa-sched input[type=datetime-local] { font-size:12px;padding:3px;width:190px }
    .tsa-rm { color:#c0392b;background:none;border:none;cursor:pointer;font-size:16px;padding:0 3px;line-height:1;vertical-align:middle }
    .tsa-reveal-now { font-size:11px!important;padding:1px 8px!important;height:auto!important;line-height:1.6!important }
    .tsa-badge { display:inline-block;font-size:11px;font-weight:600;padding:2px 8px;border-radius:10px;white-space:nowrap }
    .tsa-badge--dropped { background:#e8f5e9;color:#1b7a1b }
    .tsa-badge--next    { background:#fff3cd;color:#9a7400 }
    .tsa-badge--pending { background:#eee;color:#888 }
    .tsa-badge--none    { background:transparent;color:#bbb }
    .tsa-gchecks { display:grid;grid-template-columns:repeat(auto-fill,minmax(190px,1fr));gap:3px 14px;max-height:180px;overflow-y:auto;padding:8px;border:1px solid #eee;border-radius:4px;background:#fafafa;margin-top:4px }
    .tsa-gchecks label { font-weight:normal;display:flex;gap:5px;align-items:center;cursor:pointer;font-size:12px }
    .tsa-color-grid { display:flex;gap:18px;flex-wrap:wrap;align-items:flex-end;margin-bottom:14px }
    .tsa-color-field { display:flex;flex-direction:column;gap:4px;font-size:12px }
    .tsa-color-field > span { font-weight:600 }
    .tsa-color-field input[type=color] { width:60px;height:34px;padding:2px;border:1px solid #ddd;border-radius:5px;cursor:pointer }
    .tsa-color-field em { font-size:11px;color:#999;font-style:normal }
    .tsa-color-preview { border-radius:10px;padding:22px;text-align:center;margin-bottom:8px }
    .tsa-color-preview .pv-badge { display:inline-block;font-size:11px;font-weight:700;padding:4px 12px;border-radius:20px;margin-bottom:10px }
    .tsa-color-preview .pv-title { font-size:24px;font-weight:900;letter-spacing:-1px;margin-bottom:10px }
    .tsa-color-preview .pv-btn { display:inline-block;font-size:13px;font-weight:700;padding:8px 18px;border-radius:8px }
    </style>

    <div class="tsa-pb">

        <h3>Status</h3>
        <div class="tsa-prow">
            <label>Active</label>
            <div>
                <input type="checkbox" name="tsa_party_active" value="1" <?php checked( $active, '1' ); ?> />
                <span class="desc">Enables live status, countdown, and revealed designs on the front end.</span>
            </div>
        </div>
        <div class="tsa-prow">
            <label>Party Name</label>
            <input type="text" name="tsa_party_theme" value="<?php echo esc_attr( $theme ); ?>" placeholder="Summer Spirit Drop 2026" />
        </div>
        <div class="tsa-prow">
            <label>School / Store</label>
            <div>
                <select name="tsa_party_store_slug" id="tsa-party-store">
                    <option value="">— General TSA (no school) —</option>
                    <?php foreach ( $school_recs as $sr ) : ?>
                    <option value="<?php echo esc_attr( $sr['slug'] ); ?>" <?php selected( $party_store, $sr['slug'] ); ?>>
                        <?php echo esc_html( $sr['name'] ); ?>
                    </option>
                    <?php endforeach; ?>
                </select>
                <div class="desc">Links this drop to a school: the configurator runs in that store (its colors, cart routing &amp; discount), and when the party closes its designs are released into that school's Design Library. Leave as General for a non-school party.</div>
            </div>
        </div>

        <div class="tsa-prow">
            <label></label>
            <div style="background:#f0f7ff;border:1px solid #c8dff5;border-radius:6px;padding:12px 14px;font-size:12px;color:#1d2b3a;line-height:1.55;max-width:680px">
                <strong>How a school Tee Party works</strong>
                <ol style="margin:6px 0 0;padding-left:18px">
                    <li><strong>During the party</strong> — the scheduled designs appear <em>only on this Tee Party page</em> (on your drop schedule, with the party's quantity discounts). They are <em>not</em> in the school store/library yet — that's what keeps the drop exclusive.</li>
                    <li><strong>When the party closes</strong> (end date + grace minutes) — those designs are automatically released into the linked school's <strong>Design Library</strong> at regular price, becoming part of its permanent catalog.</li>
                    <li>Release also runs the first time the closed party is viewed (cron fallback), or instantly via <em>“Release designs to library now”</em> below. A school must be selected above for release to have a destination.</li>
                </ol>
            </div>
        </div>

        <h3>Schedule</h3>
        <div class="tsa-prow">
            <label>Party Start</label>
            <input type="datetime-local" name="tsa_party_start_date" value="<?php echo esc_attr( $start_date ); ?>" />
        </div>
        <div class="tsa-prow">
            <label>Deadline</label>
            <input type="datetime-local" name="tsa_party_end_date" value="<?php echo esc_attr( $end_date ); ?>" />
        </div>
        <div class="tsa-prow">
            <label>Grace Period</label>
            <div>
                <input type="number" name="tsa_party_grace_minutes" value="<?php echo esc_attr( $grace ); ?>" min="0" max="1440" style="width:65px" /> minutes after deadline
                <div class="desc">Customers can still checkout during this window.</div>
            </div>
        </div>
        <div class="tsa-prow">
            <label>Drop Interval</label>
            <div>
                <input type="number" name="tsa_party_drop_interval" value="<?php echo esc_attr( $interval ); ?>" min="1" max="1440" style="width:65px" /> minutes between drops
                <div class="desc">Used by Auto-Schedule. Individual reveal times can be edited.</div>
            </div>
        </div>

        <h3>Party Image</h3>
        <p style="font-size:12px;color:#888;margin:0 0 10px">Shown on the Tee Parties hub card and at the top of this party's page — a school crest, team logo, or showcase graphic works great. Falls back to the page Featured Image, then a 🎉 emoji.</p>
        <div class="tsa-party-image-field" style="display:flex;align-items:center;gap:14px;margin-bottom:18px">
            <div id="tsa-party-image-preview" style="width:104px;height:65px;border:1px solid #dcdcde;border-radius:8px;background:#f6f7f7;display:flex;align-items:center;justify-content:center;font-size:26px;overflow:hidden">
                <?php if ( $img_url ) : ?>
                    <img src="<?php echo esc_url( $img_url ); ?>" alt="" style="width:100%;height:100%;object-fit:cover" />
                <?php else : ?>
                    <span style="opacity:.5">🎉</span>
                <?php endif; ?>
            </div>
            <div>
                <input type="hidden" name="tsa_party_image_id" id="tsa-party-image-id" value="<?php echo esc_attr( $img_id ?: '' ); ?>" />
                <button type="button" class="button" id="tsa-party-image-select"><?php echo $img_id ? 'Change image' : 'Select image'; ?></button>
                <button type="button" class="button-link" id="tsa-party-image-remove" style="margin-left:8px;color:#b32d2e;<?php echo $img_id ? '' : 'display:none'; ?>">Remove</button>
            </div>
        </div>

        <h3>Page Colors</h3>
        <p style="font-size:12px;color:#888;margin:0 0 10px">Match the showcase page to the school / team / event brand. Defaults to the TSA light blush theme.</p>
        <div class="tsa-color-grid">
            <label class="tsa-color-field">
                <span>Accent</span>
                <input type="color" name="tsa_party_color_accent" value="<?php echo esc_attr( $c_accent ); ?>" />
                <em>Buttons, badge, timers</em>
            </label>
            <label class="tsa-color-field">
                <span>Highlight</span>
                <input type="color" name="tsa_party_color_highlight" value="<?php echo esc_attr( $c_highlight ); ?>" />
                <em>Urgency strip &amp; accents</em>
            </label>
            <label class="tsa-color-field">
                <span>Background Top</span>
                <input type="color" name="tsa_party_color_bg_top" value="<?php echo esc_attr( $c_bg_top ); ?>" />
                <em>Hero gradient start</em>
            </label>
            <label class="tsa-color-field">
                <span>Background Bottom</span>
                <input type="color" name="tsa_party_color_bg_bottom" value="<?php echo esc_attr( $c_bg_bottom ); ?>" />
                <em>Hero gradient end</em>
            </label>
            <label class="tsa-color-field">
                <span>Text</span>
                <input type="color" name="tsa_party_color_text" value="<?php echo esc_attr( $c_text ); ?>" />
                <em>Body text on hero</em>
            </label>
            <button type="button" class="button button-small" id="tsa-reset-colors" style="align-self:flex-end;margin-bottom:2px">Reset to TSA defaults</button>
        </div>
        <div id="tsa-color-preview" class="tsa-color-preview"></div>

        <h3 class="tsa-collapsible">Apparel</h3>
        <div class="tsa-prow">
            <label>Available Apparel</label>
            <div>
                <label style="font-weight:normal;margin-right:14px">
                    <input type="radio" name="tsa_party_apparel_mode" value="all" <?php checked( $apparel_mode, 'all' ); ?> />
                    Full TSA Catalog
                </label>
                <label style="font-weight:normal">
                    <input type="radio" name="tsa_party_apparel_mode" value="specific" <?php checked( $apparel_mode, 'specific' ); ?> />
                    Specific Garments Only
                </label>
            </div>
        </div>
        <div id="tsa-garment-picker" style="<?php echo $apparel_mode !== 'specific' ? 'display:none;' : ''; ?>margin-bottom:12px">
            <p class="description" style="margin:0 0 10px">Check the garments to offer. For each, you can limit which colors customers may pick — <strong>leave every color unchecked to allow them all</strong>.</p>
            <?php foreach ( $all_garments as $g ) :
                $g_checked = in_array( $g->ID, $garment_ids, true );
                $g_colors  = tsa_party_garment_color_list( $g->ID );
                $allowed   = (array) ( $garment_colors_map[ $g->ID ] ?? $garment_colors_map[ (string) $g->ID ] ?? [] );
            ?>
            <div class="tsa-gitem" style="border:1px solid #e0e0e0;border-radius:8px;padding:10px 12px;margin-bottom:8px">
                <label style="font-weight:600;display:block">
                    <input type="checkbox" class="tsa-gtoggle" name="tsa_party_garment_ids[]" value="<?php echo esc_attr( $g->ID ); ?>" data-gid="<?php echo esc_attr( $g->ID ); ?>" <?php checked( $g_checked ); ?> />
                    <?php echo esc_html( $g->post_title ); ?>
                </label>
                <?php if ( $g_colors ) : ?>
                <div class="tsa-gcolors" id="tsa-gcolors-<?php echo (int) $g->ID; ?>" style="display:<?php echo $g_checked ? 'flex' : 'none'; ?>;margin:9px 0 0 22px;flex-wrap:wrap;gap:6px 14px">
                    <span style="width:100%;color:#888;font-size:11px;margin-bottom:2px;display:block">Allowed colors (none checked = all <?php echo count( $g_colors ); ?>):</span>
                    <?php foreach ( $g_colors as $col ) : ?>
                    <label style="font-weight:normal;font-size:12px;display:inline-flex;align-items:center;gap:5px;margin-right:6px">
                        <input type="checkbox" name="tsa_party_garment_colors[<?php echo (int) $g->ID; ?>][]" value="<?php echo esc_attr( $col['name'] ); ?>" <?php checked( in_array( $col['name'], $allowed, true ) ); ?> />
                        <span style="width:13px;height:13px;border-radius:50%;border:1px solid #ccc;background:<?php echo esc_attr( $col['hex'] ?: '#ccc' ); ?>;display:inline-block"></span>
                        <?php echo esc_html( $col['name'] ); ?>
                    </label>
                    <?php endforeach; ?>
                </div>
                <?php endif; ?>
            </div>
            <?php endforeach; ?>
            <?php if ( empty( $all_garments ) ) : ?>
            <p style="color:#888;font-size:12px">No active garments found. Add garments under Configurator → Garments first.</p>
            <?php endif; ?>
        </div>

        <h3>Quantity Discounts</h3>
        <p style="color:#888;font-size:12px;margin:0 0 8px">
            "Buy more, save more" tiers shown to customers on this party page.
            <strong>Buy this many</strong> Tees (or more) → <strong>this % off</strong>. The
            <strong>Label</strong> is the exact wording shown on the card (leave blank to auto-generate). Leave % at 0 to disable a row.
        </p>
        <?php $disc_tiers = tsa_party_discount_tiers( $post->ID ); ?>
        <table class="widefat" style="max-width:560px;margin-bottom:6px">
            <thead><tr>
                <th style="width:140px">Buy this many or more</th>
                <th style="width:80px">% off</th>
                <th>Label (shown to shoppers)</th>
            </tr></thead>
            <tbody>
            <?php for ( $i = 0; $i < 4; $i++ ) :
                $row = $disc_tiers[ $i ] ?? [ 'min' => '', 'pct' => '', 'label' => '' ]; ?>
                <tr>
                    <td><input type="number" min="1" step="1" name="tsa_disc_min[]" value="<?php echo esc_attr( $row['min'] ); ?>" placeholder="e.g. 3" style="width:80px"> Tees</td>
                    <td><input type="number" min="0" max="100" step="0.5" name="tsa_disc_pct[]" value="<?php echo esc_attr( $row['pct'] ); ?>" placeholder="0" style="width:70px"> %</td>
                    <td><input type="text" name="tsa_disc_label[]" value="<?php echo esc_attr( $row['label'] ?? '' ); ?>" placeholder="e.g. up to 4 Tees" class="regular-text" style="width:100%"></td>
                </tr>
            <?php endfor; ?>
            </tbody>
        </table>
        <p style="color:#999;font-size:11px;margin:0 0 18px">
            Tip: the highest qualifying tier wins; blank Labels auto-format as a range. Default — <em>buy 2 → 10%, buy 4 → 15%, buy 6 → 20%</em>: 2–3 Tees get 10%, 4–5 get 15%, 6+ get 20% (a single Tee gets no discount).
        </p>

        <h3 class="tsa-collapsible">Design Schedule</h3>
        <div style="display:flex;gap:10px;align-items:center;margin-bottom:8px;flex-wrap:wrap">
            <button type="button" id="tsa-add-row" class="button button-small">+ Add Design</button>
            <button type="button" id="tsa-auto-sched" class="button button-small">⚡ Auto-Schedule</button>
            <label style="font-size:12px;color:#555;font-weight:600">Designs per drop
                <input type="number" id="tsa-per-drop" name="tsa_party_designs_per_drop" min="1" max="50" value="<?php echo esc_attr( $per_drop ); ?>" style="width:58px;margin-left:4px">
            </label>
            <span style="font-size:12px;color:#888">Auto-Schedule batches this many designs at each reveal time, then advances by the Drop Interval.</span>
        </div>

        <table class="tsa-sched">
            <thead><tr><th style="width:32px">#</th><th>Design</th><th>Reveals At</th><th style="width:90px">Status</th><th style="width:28px"></th></tr></thead>
            <tbody id="tsa-sched-body">
                <?php foreach ( $schedule as $i => $row ) : ?>
                <tr class="tsa-srow">
                    <td style="color:#999;font-size:11px" class="tsa-rnum"><?php echo $i + 1; ?></td>
                    <td>
                        <input type="hidden" name="tsa_schedule_design[]" class="tsa-design-val" value="<?php echo esc_attr( (int) ( $row['design_id'] ?? 0 ) ); ?>" />
                        <button type="button" class="button tsa-design-pick" style="width:100%;text-align:left;height:auto;min-height:30px;display:flex;align-items:center;gap:8px;padding:4px 8px;font-size:12px">Choose design…</button>
                    </td>
                    <td><input type="datetime-local" name="tsa_schedule_reveal[]" value="<?php echo esc_attr( $row['reveal_at'] ?? '' ); ?>" /></td>
                    <td class="tsa-status-cell"></td>
                    <td style="white-space:nowrap">
                        <button type="button" class="button button-small tsa-reveal-now" title="Set reveal time to right now">Reveal&nbsp;Now</button>
                        <button type="button" class="tsa-rm" title="Remove">×</button>
                    </td>
                </tr>
                <?php endforeach; ?>
            </tbody>
        </table>

        <h3>Designs → School Library</h3>
        <p style="font-size:12px;color:#888;margin:0 0 8px">When this party closes (deadline + grace), its scheduled designs are <strong>auto-released</strong> into the linked school's Design Library. Release early with the button.</p>
        <div style="display:flex;align-items:center;gap:10px;flex-wrap:wrap;margin-bottom:6px">
            <button type="button" class="button button-small" id="tsa-party-release" <?php echo $party_store ? '' : 'disabled'; ?>>Release designs to library now</button>
            <span id="tsa-party-release-msg" style="font-size:12px;color:<?php echo $released ? '#1a7f37' : '#888'; ?>">
                <?php
                if ( $released ) {
                    $rat = (int) get_post_meta( $post->ID, '_tsa_party_released_at', true );
                    echo '✓ Released' . ( $rat ? ' on ' . esc_html( date_i18n( 'M j, Y', $rat ) ) : '' );
                } else {
                    echo $party_store ? 'Not yet released.' : 'Link a school above to enable release.';
                }
                ?>
            </span>
            <?php wp_nonce_field( 'tsa_party_release', 'tsa_party_release_nonce' ); ?>
        </div>

        <!-- Template row -->
        <script type="text/x-template" id="tsa-row-tpl">
        <tr class="tsa-srow">
            <td style="color:#999;font-size:11px" class="tsa-rnum"></td>
            <td>
                <input type="hidden" name="tsa_schedule_design[]" class="tsa-design-val" value="" />
                <button type="button" class="button tsa-design-pick" style="width:100%;text-align:left;height:auto;min-height:30px;display:flex;align-items:center;gap:8px;padding:4px 8px;font-size:12px">Choose design…</button>
            </td>
            <td><input type="datetime-local" name="tsa_schedule_reveal[]" /></td>
            <td class="tsa-status-cell"></td>
            <td style="white-space:nowrap">
                <button type="button" class="button button-small tsa-reveal-now" title="Set reveal time to right now">Reveal&nbsp;Now</button>
                <button type="button" class="tsa-rm" title="Remove">×</button>
            </td>
        </tr>
        </script>

        <!-- Design thumbnail picker modal -->
        <div id="tsa-design-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.5)">
            <div style="position:absolute;top:5%;left:50%;transform:translateX(-50%);width:min(780px,92vw);max-height:86vh;background:#fff;border-radius:10px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.35)">
                <div style="padding:13px 16px;border-bottom:1px solid #eee;display:flex;gap:10px;align-items:center">
                    <strong style="font-size:14px;white-space:nowrap">Choose a design</strong>
                    <select id="tsa-design-cat" style="max-width:200px"><option value="">All categories</option><?php echo $picker_cat_options; ?></select>
                    <input type="text" id="tsa-design-search" placeholder="Search designs…" class="regular-text" style="flex:1" autocomplete="off" />
                    <button type="button" class="button" id="tsa-design-close">Close</button>
                </div>
                <div id="tsa-design-grid" style="padding:14px 16px;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px"></div>
            </div>
        </div>

    </div>

    <script>
    (function(){
        // Apparel mode toggle
        document.querySelectorAll('[name="tsa_party_apparel_mode"]').forEach(function(r){
            r.addEventListener('change',function(){ document.getElementById('tsa-garment-picker').style.display = this.value==='specific'?'':'none'; });
        });

        // Collapsible section headers (Apparel, Design Schedule). Clicking the
        // <h3> hides/shows every sibling up to the next <h3>. We stash each
        // element's prior inline display so expanding restores it exactly — so
        // the garment picker stays hidden in "Full Catalog" mode, etc.
        document.querySelectorAll('.tsa-pb h3.tsa-collapsible').forEach(function(h){
            h.style.cursor = 'pointer';
            var car = document.createElement('span');
            car.textContent = ' ▾';
            car.style.cssText = 'font-size:11px;color:#999;font-weight:400';
            h.appendChild(car);
            h.addEventListener('click', function(){
                var collapsed = h.classList.toggle('tsa-collapsed');
                car.textContent = collapsed ? ' ▸' : ' ▾';
                var el = h.nextElementSibling;
                while (el && el.tagName !== 'H3') {
                    if (collapsed) { el.dataset.tsaPrevDisp = el.style.display; el.style.display = 'none'; }
                    else { el.style.display = el.dataset.tsaPrevDisp || ''; }
                    el = el.nextElementSibling;
                }
            });
        });

        // Per-garment: reveal its color allow-list only when the garment is checked
        document.querySelectorAll('.tsa-gtoggle').forEach(function(cb){
            cb.addEventListener('change',function(){
                var blk = document.getElementById('tsa-gcolors-'+this.getAttribute('data-gid'));
                if (blk) blk.style.display = this.checked ? 'flex' : 'none';
            });
        });

        var body = document.getElementById('tsa-sched-body');
        var tpl  = document.getElementById('tsa-row-tpl').textContent;

        function pad(n){ return n.toString().padStart(2,'0'); }
        function nowLocalValue(){
            var d = new Date();
            return d.getFullYear()+'-'+pad(d.getMonth()+1)+'-'+pad(d.getDate())+'T'+pad(d.getHours())+':'+pad(d.getMinutes());
        }

        function renumber(){
            body.querySelectorAll('.tsa-srow').forEach(function(r,i){ var n=r.querySelector('.tsa-rnum'); if(n) n.textContent=i+1; });
        }

        // Compute + paint status badges. The earliest unrevealed row = "Next".
        function refreshStatus(){
            var now  = Date.now();
            var rows = Array.prototype.slice.call(body.querySelectorAll('.tsa-srow'));

            // Find the earliest future reveal time across all rows
            var nextTs = null;
            rows.forEach(function(row){
                var v = row.querySelector('[type=datetime-local]').value;
                if(!v) return;
                var ts = new Date(v).getTime();
                if(ts > now && (nextTs === null || ts < nextTs)) nextTs = ts;
            });

            rows.forEach(function(row){
                var cell = row.querySelector('.tsa-status-cell');
                var v    = row.querySelector('[type=datetime-local]').value;
                if(!v){ cell.innerHTML = '<span class="tsa-badge tsa-badge--none">— no time —</span>'; return; }
                var ts = new Date(v).getTime();
                if(ts <= now){
                    cell.innerHTML = '<span class="tsa-badge tsa-badge--dropped">✓ Dropped</span>';
                } else if(ts === nextTs){
                    cell.innerHTML = '<span class="tsa-badge tsa-badge--next">⏭ Next up</span>';
                } else {
                    cell.innerHTML = '<span class="tsa-badge tsa-badge--pending">Pending</span>';
                }
            });
        }

        // Add row — insert at the TOP so the new row is immediately visible
        // (no scrolling to the bottom of a long schedule). Save sorts by reveal
        // time anyway, so DOM order is purely for editing convenience.
        document.getElementById('tsa-add-row').addEventListener('click',function(){
            var tmp = document.createElement('tbody');
            tmp.innerHTML = tpl;
            body.insertBefore(tmp.firstElementChild, body.firstChild);
            renumber(); refreshStatus();
        });

        // Row click delegation: remove + reveal-now
        body.addEventListener('click',function(e){
            if(e.target.classList.contains('tsa-rm')){
                e.target.closest('tr').remove(); renumber(); refreshStatus();
            }
            if(e.target.classList.contains('tsa-reveal-now')){
                var inp = e.target.closest('tr').querySelector('[type=datetime-local]');
                if(inp){ inp.value = nowLocalValue(); refreshStatus(); }
            }
        });

        // Recompute status whenever any reveal time is edited
        body.addEventListener('change',function(e){
            if(e.target.matches('[type=datetime-local]')) refreshStatus();
        });

        // Auto-schedule — batch `perDrop` consecutive designs into one reveal time,
        // then advance by the Drop Interval for the next batch.
        document.getElementById('tsa-auto-sched').addEventListener('click',function(){
            var sv = document.querySelector('[name="tsa_party_start_date"]').value;
            var iv = parseInt(document.querySelector('[name="tsa_party_drop_interval"]').value)||60;
            var pdEl = document.getElementById('tsa-per-drop');
            var perDrop = Math.max(1, parseInt(pdEl && pdEl.value)||1);
            if(!sv){ alert('Set the Party Start date/time first.'); return; }
            var start = new Date(sv);
            body.querySelectorAll('.tsa-srow').forEach(function(row,i){
                var dropIndex = Math.floor(i / perDrop);   // rows in the same batch share a time
                var dt  = new Date(start.getTime() + dropIndex*iv*60000);
                var val = dt.getFullYear()+'-'+pad(dt.getMonth()+1)+'-'+pad(dt.getDate())+'T'+pad(dt.getHours())+':'+pad(dt.getMinutes());
                var inp = row.querySelector('[type=datetime-local]');
                if(inp) inp.value = val;
            });
            refreshStatus();
        });

        // Initial paint + refresh every 30s so badges flip as times pass
        refreshStatus();
        setInterval(refreshStatus, 30000);

        /* ── Design thumbnail picker (modal) ── */
        var TSA_DESIGNS = <?php echo wp_json_encode( $design_data ); ?>;
        var DMAP = {}; TSA_DESIGNS.forEach(function(d){ DMAP[String(d.id)] = d; });
        var TSA_CAT_DESC = <?php echo wp_json_encode( (object) $cat_descendants ); ?>;
        var dModal  = document.getElementById('tsa-design-modal');
        var dGrid   = document.getElementById('tsa-design-grid');
        var dSearch = document.getElementById('tsa-design-search');
        var dCat    = document.getElementById('tsa-design-cat');
        var dActive = null;
        function dEsc(s){ return String(s||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c]; }); }
        function dBtnHtml(d){
            if(!d) return 'Choose design…';
            var img = d.img
                ? '<img src="'+dEsc(d.img)+'" style="width:26px;height:26px;object-fit:cover;border-radius:4px;flex:none" />'
                : '<span style="width:26px;height:26px;border-radius:4px;background:#f0f0f0;display:inline-flex;align-items:center;justify-content:center;flex:none">🎨</span>';
            return img + '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+dEsc(d.t)+'</span>';
        }
        function dPaint(btn){
            var v = btn.parentNode.querySelector('.tsa-design-val').value;
            btn.innerHTML = dBtnHtml(DMAP[String(v)]);
        }
        function dRender(q){
            q=(q||'').trim().toLowerCase(); dGrid.innerHTML='';
            var cat = dCat ? dCat.value : '';
            var allowed = cat ? (TSA_CAT_DESC[cat] || [parseInt(cat,10)]) : null;
            TSA_DESIGNS.filter(function(d){
                if (q && d.t.toLowerCase().indexOf(q)<0) return false;
                if (allowed && !(d.cats||[]).some(function(c){ return allowed.indexOf(c)>=0; })) return false;
                return true;
            }).forEach(function(d){
                var t=document.createElement('button'); t.type='button';
                t.style.cssText='border:1px solid #ddd;border-radius:8px;background:#fff;padding:8px;cursor:pointer;display:flex;flex-direction:column;gap:6px;align-items:center;text-align:center';
                var vis = d.img
                    ? '<img src="'+dEsc(d.img)+'" style="width:100%;aspect-ratio:1;object-fit:contain;background:#fafafa;border-radius:5px" />'
                    : '<div style="width:100%;aspect-ratio:1;background:#fafafa;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:30px">🎨</div>';
                t.innerHTML = vis + '<span style="font-size:11px;line-height:1.25;color:#333">'+dEsc(d.t)+'</span>';
                t.addEventListener('click',function(){
                    if(dActive){ dActive.parentNode.querySelector('.tsa-design-val').value = d.id; dPaint(dActive); }
                    dClose();
                });
                dGrid.appendChild(t);
            });
            if(!dGrid.children.length) dGrid.innerHTML='<p style="grid-column:1/-1;color:#888;font-size:13px">No designs match.</p>';
        }
        function dOpen(btn){ dActive=btn; dModal.style.display='block'; dSearch.value=''; dRender(''); dSearch.focus(); }
        function dClose(){ dModal.style.display='none'; dActive=null; }
        body.addEventListener('click',function(e){ var b=e.target.closest('.tsa-design-pick'); if(b){ e.preventDefault(); dOpen(b); } });
        dSearch.addEventListener('input',function(){ dRender(this.value); });
        if (dCat) dCat.addEventListener('change',function(){ dRender(dSearch.value); });
        dSearch.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); } });
        document.getElementById('tsa-design-close').addEventListener('click',dClose);
        dModal.addEventListener('click',function(e){ if(e.target===dModal) dClose(); });
        body.querySelectorAll('.tsa-design-pick').forEach(dPaint);

        /* ── Color live preview + reset ── */
        var DEFAULTS = { accent:'#d8a85f', highlight:'#fec2c0', bg_top:'#ffffff', bg_bottom:'#fff1f0', text:'#252124' };
        function colorVal(name){ var el=document.querySelector('[name="tsa_party_color_'+name+'"]'); return el?el.value:DEFAULTS[name]; }
        function paintColorPreview(){
            var a=colorVal('accent'), hl=colorVal('highlight'), bt=colorVal('bg_top'), bb=colorVal('bg_bottom'), tx=colorVal('text');
            var pv=document.getElementById('tsa-color-preview');
            pv.style.background='linear-gradient(160deg,'+bt+','+bb+')';
            pv.innerHTML='<div class="pv-badge" style="background:'+a+';color:'+bt+'">● Active Now</div>'+
                '<div class="pv-title" style="color:'+hl+'">Your Showcase Name</div>'+
                '<div style="color:'+tx+';opacity:.75;font-size:13px;margin-bottom:12px">New designs reveal throughout the showcase.</div>'+
                '<span class="pv-btn" style="background:'+a+';color:'+bt+'">Enter Showcase →</span>';
        }
        document.querySelectorAll('[name^="tsa_party_color_"]').forEach(function(inp){
            inp.addEventListener('input', paintColorPreview);
        });
        var resetBtn=document.getElementById('tsa-reset-colors');
        if(resetBtn) resetBtn.addEventListener('click',function(){
            Object.keys(DEFAULTS).forEach(function(k){
                var el=document.querySelector('[name="tsa_party_color_'+k+'"]'); if(el) el.value=DEFAULTS[k];
            });
            paintColorPreview();
        });
        paintColorPreview();

        /* ── School link: prefill party colors from the school + manual release ── */
        var SCHOOL_COLORS = <?php
            $cmap = [];
            foreach ( $school_recs as $sr ) { if ( ! empty( $sr['color'] ) ) $cmap[ $sr['slug'] ] = [ $sr['color'], $sr['color2'] ?: $sr['color'] ]; }
            echo wp_json_encode( $cmap );
        ?>;
        var storeSel = document.getElementById('tsa-party-store');
        if (storeSel) storeSel.addEventListener('change', function(){
            var c = SCHOOL_COLORS[this.value];
            if (c) {
                var a = document.querySelector('[name="tsa_party_color_accent"]');
                var h = document.querySelector('[name="tsa_party_color_highlight"]');
                if (a) a.value = c[0];
                if (h) h.value = c[1];
                paintColorPreview();
            }
            var rb = document.getElementById('tsa-party-release');
            if (rb) rb.disabled = ! this.value;
        });

        var relBtn = document.getElementById('tsa-party-release');
        if (relBtn) relBtn.addEventListener('click', function(){
            var msg = document.getElementById('tsa-party-release-msg');
            var nonceEl = document.getElementById('tsa_party_release_nonce');
            relBtn.disabled = true; msg.style.color = '#888'; msg.textContent = 'Releasing…';
            var fd = new FormData();
            fd.append('action', 'tsa_party_release');
            fd.append('party_id', <?php echo (int) $post->ID; ?>);
            fd.append('nonce', nonceEl ? nonceEl.value : '');
            fetch(ajaxurl, { method:'POST', body:fd, credentials:'same-origin' })
                .then(function(r){ return r.json(); })
                .then(function(j){
                    relBtn.disabled = false;
                    if (j && j.success) { msg.style.color = '#1a7f37'; msg.textContent = '✓ ' + ((j.data && j.data.message) || 'Released.'); }
                    else { msg.style.color = '#b32d2e'; msg.textContent = (j && j.data && j.data.message) || 'Could not release.'; }
                })
                .catch(function(){ relBtn.disabled = false; msg.style.color = '#b32d2e'; msg.textContent = 'Network error.'; });
        });
    })();

    /* ── Party Image picker (WP media frame) ── */
    jQuery(function($){
        if (typeof wp === 'undefined' || !wp.media) return;
        var frame;
        var $id     = $('#tsa-party-image-id');
        var $select = $('#tsa-party-image-select');
        var $remove = $('#tsa-party-image-remove');
        var $prev   = $('#tsa-party-image-preview');
        $select.on('click', function(e){
            e.preventDefault();
            if (frame) { frame.open(); return; }
            frame = wp.media({ title:'Select party image', button:{ text:'Use this image' }, library:{ type:'image' }, multiple:false });
            frame.on('select', function(){
                var a = frame.state().get('selection').first().toJSON();
                var url = (a.sizes && a.sizes.medium ? a.sizes.medium.url : a.url);
                $id.val(a.id);
                $prev.html('<img src="'+url+'" alt="" style="width:100%;height:100%;object-fit:cover" />');
                $select.text('Change image');
                $remove.show();
            });
            frame.open();
        });
        $remove.on('click', function(e){
            e.preventDefault();
            $id.val('');
            $prev.html('<span style="opacity:.5">🎉</span>');
            $select.text('Select image');
            $remove.hide();
        });
    });
    </script>
    <?php
}


/* ─── 2. SAVE ───────────────────────────────────────────────────── */

add_action( 'save_post_page', 'tsa_save_party_meta' );
function tsa_save_party_meta( int $post_id ): void {
    if ( ! isset( $_POST['tsa_party_nonce'] ) ||
         ! wp_verify_nonce( sanitize_text_field( wp_unslash( $_POST['tsa_party_nonce'] ) ), 'tsa_party_save' ) ) return;
    if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
    if ( ! current_user_can( 'edit_post', $post_id ) ) return;

    update_post_meta( $post_id, '_tsa_party_active',        isset( $_POST['tsa_party_active'] ) ? '1' : '0' );
    update_post_meta( $post_id, '_tsa_party_theme',         sanitize_text_field( wp_unslash( $_POST['tsa_party_theme']         ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_party_start_date',    sanitize_text_field( wp_unslash( $_POST['tsa_party_start_date']    ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_party_end_date',      sanitize_text_field( wp_unslash( $_POST['tsa_party_end_date']      ?? '' ) ) );
    update_post_meta( $post_id, '_tsa_party_grace_minutes', absint( $_POST['tsa_party_grace_minutes'] ?? 30 ) );
    update_post_meta( $post_id, '_tsa_party_drop_interval', absint( $_POST['tsa_party_drop_interval'] ?? 60 ) );
    update_post_meta( $post_id, '_tsa_party_designs_per_drop', max( 1, absint( $_POST['tsa_party_designs_per_drop'] ?? 4 ) ) );
    update_post_meta( $post_id, '_tsa_party_image_id',         absint( $_POST['tsa_party_image_id'] ?? 0 ) );

    // Colors — validate as hex
    $hex = function( $v, $default ) {
        $v = sanitize_text_field( wp_unslash( $v ) );
        return preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ? $v : $default;
    };
    update_post_meta( $post_id, '_tsa_party_color_accent',    $hex( $_POST['tsa_party_color_accent']    ?? '', '#d8a85f' ) );
    update_post_meta( $post_id, '_tsa_party_color_highlight', $hex( $_POST['tsa_party_color_highlight'] ?? '', '#fec2c0' ) );
    update_post_meta( $post_id, '_tsa_party_color_bg_top',    $hex( $_POST['tsa_party_color_bg_top']    ?? '', '#ffffff' ) );
    update_post_meta( $post_id, '_tsa_party_color_bg_bottom', $hex( $_POST['tsa_party_color_bg_bottom'] ?? '', '#fff1f0' ) );
    update_post_meta( $post_id, '_tsa_party_color_text',      $hex( $_POST['tsa_party_color_text']      ?? '', '#252124' ) );

    $mode = in_array( $_POST['tsa_party_apparel_mode'] ?? 'all', [ 'all', 'specific' ], true )
          ? sanitize_key( $_POST['tsa_party_apparel_mode'] ) : 'all';
    update_post_meta( $post_id, '_tsa_party_apparel_mode', $mode );

    // School/store link + auto-release scheduling. When the party closes
    // (deadline + grace), its designs are tagged into the school's Design Library.
    $store_slug = sanitize_title( wp_unslash( $_POST['tsa_party_store_slug'] ?? '' ) );
    update_post_meta( $post_id, '_tsa_party_store_slug', $store_slug );
    wp_clear_scheduled_hook( 'tsa_party_release_event', [ $post_id ] );
    if ( $store_slug && isset( $_POST['tsa_party_active'] ) ) {
        $end_ts = tsa_party_ts( wp_unslash( $_POST['tsa_party_end_date'] ?? '' ) );
        $grace  = absint( $_POST['tsa_party_grace_minutes'] ?? 30 );
        $close  = $end_ts ? $end_ts + $grace * 60 : 0;
        if ( $close ) wp_schedule_single_event( $close, 'tsa_party_release_event', [ $post_id ] );
    }

    $gids = array_values( array_filter( array_map( 'absint', (array)( $_POST['tsa_party_garment_ids'] ?? [] ) ) ) );
    update_post_meta( $post_id, '_tsa_party_garment_ids', wp_json_encode( $gids ) );

    // Per-garment allowed colors: { gid: [names] }. Only stored for selected
    // garments with at least one color ticked; empty/omitted = all colors.
    $gcolors_raw = (array) ( $_POST['tsa_party_garment_colors'] ?? [] );
    $gcolors = [];
    foreach ( $gids as $gid ) {
        $names = isset( $gcolors_raw[ $gid ] ) ? (array) $gcolors_raw[ $gid ] : [];
        $clean = array_values( array_unique( array_filter( array_map( function ( $n ) { return sanitize_text_field( wp_unslash( $n ) ); }, $names ) ) ) );
        if ( $clean ) $gcolors[ $gid ] = $clean;
    }
    update_post_meta( $post_id, '_tsa_party_garment_colors', wp_json_encode( $gcolors ) );

    $design_ids = array_map( 'absint', (array)( $_POST['tsa_schedule_design'] ?? [] ) );
    $reveal_ats = array_map( function($v){ return sanitize_text_field( wp_unslash($v) ); }, (array)( $_POST['tsa_schedule_reveal'] ?? [] ) );
    $schedule   = [];
    foreach ( $design_ids as $i => $did ) {
        if ( ! $did ) continue;
        $schedule[] = [ 'design_id' => $did, 'reveal_at' => $reveal_ats[$i] ?? '' ];
    }
    usort( $schedule, function( $a, $b ) { return strcmp( $a['reveal_at'], $b['reveal_at'] ); } );
    update_post_meta( $post_id, '_tsa_party_schedule', wp_json_encode( $schedule, JSON_UNESCAPED_SLASHES ) );

    // Per-drop activation scheduling. At each future reveal time, publish + switch
    // "Show in configurator" ON for that drop's designs (so they're orderable even
    // with no live viewer). Drops already in the past (e.g. "Reveal Now") are flipped
    // immediately below. Clear old events first so edits don't leave stale ones.
    wp_clear_scheduled_hook( 'tsa_party_drop_event', [ $post_id ] );
    if ( isset( $_POST['tsa_party_active'] ) ) {
        $seen_ts = [];
        foreach ( $schedule as $row ) {
            $rt = tsa_party_ts( $row['reveal_at'] ?? '' );
            if ( $rt && $rt > time() && empty( $seen_ts[ $rt ] ) ) {
                $seen_ts[ $rt ] = true;
                wp_schedule_single_event( $rt, 'tsa_party_drop_event', [ $post_id ] );
            }
        }
        tsa_party_activate_dropped_designs( $post_id ); // flip any already-passed drops now
    }

    // Quantity discount tiers
    $disc_min   = array_map( 'absint', (array) ( $_POST['tsa_disc_min'] ?? [] ) );
    $disc_pct   = array_map( 'floatval', (array) ( $_POST['tsa_disc_pct'] ?? [] ) );
    $disc_label = array_map( 'sanitize_text_field', array_map( 'wp_unslash', (array) ( $_POST['tsa_disc_label'] ?? [] ) ) );
    $tiers      = [];
    foreach ( $disc_min as $i => $min ) {
        $pct = isset( $disc_pct[ $i ] ) ? max( 0, min( 100, (float) $disc_pct[ $i ] ) ) : 0;
        if ( $min >= 1 && $pct > 0 ) {
            $tiers[] = [ 'min' => $min, 'pct' => $pct, 'label' => $disc_label[ $i ] ?? '' ];
        }
    }
    usort( $tiers, function( $a, $b ) { return $a['min'] <=> $b['min']; } );
    update_post_meta( $post_id, '_tsa_party_discount_tiers', wp_json_encode( $tiers, JSON_UNESCAPED_SLASHES ) );
}


/* ─── 3. REST API ───────────────────────────────────────────────── */

add_action( 'rest_api_init', 'tsa_register_party_routes' );
function tsa_register_party_routes(): void {
    register_rest_route( 'tsa/v1', '/party/(?P<id>\d+)', [
        'methods'             => 'GET',
        'callback'            => 'tsa_rest_get_party',
        'permission_callback' => '__return_true',
    ] );
}

/** Unique colors (name + hex) across a garment's styles. For the party editor. */
function tsa_party_garment_color_list( $gid ) {
    $styles = get_post_meta( $gid, '_ac_styles', true );
    if ( ! is_array( $styles ) ) return [];
    $seen = []; $out = [];
    foreach ( $styles as $st ) {
        if ( empty( $st['colors'] ) || ! is_array( $st['colors'] ) ) continue;
        foreach ( $st['colors'] as $c ) {
            $name = trim( (string) ( $c['name'] ?? '' ) );
            if ( $name === '' ) continue;
            $key = strtolower( $name );
            if ( isset( $seen[ $key ] ) ) continue;
            $seen[ $key ] = true;
            $out[] = [ 'name' => $name, 'hex' => $c['hex'] ?? '' ];
        }
    }
    return $out;
}

function tsa_rest_get_party( WP_REST_Request $req ): WP_REST_Response {
    $pid  = absint( $req->get_param( 'id' ) );
    $post = get_post( $pid );
    if ( ! $post || $post->post_type !== 'page' ) return new WP_REST_Response( ['error'=>'Not found'], 404 );

    $end_date    = get_post_meta( $pid, '_tsa_party_end_date',      true );
    $grace       = (int) get_post_meta( $pid, '_tsa_party_grace_minutes', true );
    $schedule    = json_decode( get_post_meta( $pid, '_tsa_party_schedule', true ) ?: '[]', true ) ?: [];
    $apparel_mode= get_post_meta( $pid, '_tsa_party_apparel_mode', true ) ?: 'all';
    $garment_ids = json_decode( get_post_meta( $pid, '_tsa_party_garment_ids', true ) ?: '[]', true ) ?: [];
    $garment_colors = json_decode( get_post_meta( $pid, '_tsa_party_garment_colors', true ) ?: '{}', true ) ?: [];

    $now         = time();
    $end_ts      = tsa_party_ts( $end_date );
    $grace_end   = $end_ts ? $end_ts + $grace * 60 : 0;

    $status = 'upcoming';
    if ( get_post_meta( $pid, '_tsa_party_active', true ) === '1' ) {
        if ( $grace_end && $now > $grace_end )      $status = 'closed';
        elseif ( $end_ts && $now > $end_ts )        $status = 'grace';
        else                                         $status = 'live';
    }

    // Lazy auto-release fallback: if closed + linked to a school + not yet
    // released, release now (covers a missed WP-Cron run).
    if ( $status === 'closed'
        && get_post_meta( $pid, '_tsa_party_store_slug', true )
        && get_post_meta( $pid, '_tsa_party_released', true ) !== '1' ) {
        tsa_party_release_designs( $pid );
    }

    // Per-drop activation (lazy fallback): publish + enable any design whose reveal
    // time has passed, so a just-dropped design is immediately orderable. A cron
    // event scheduled at each drop time does the same without a live viewer.
    tsa_party_activate_dropped_designs( $pid );

    // Drop-alert emails (lazy fallback for a missed WP-Cron run). Both sends are
    // idempotent + locked, so calling them on each poll is safe. Gated to the live
    // window so the "is live" email never fires for an upcoming party.
    if ( in_array( $status, [ 'live', 'grace' ], true ) ) {
        if ( function_exists( 'tsa_party_send_open_email' ) )  tsa_party_send_open_email( $pid );
        if ( function_exists( 'tsa_party_send_drop_emails' ) ) tsa_party_send_drop_emails( $pid );
    }

    $design_cpt = post_type_exists( 'tsa_design' ) ? 'tsa_design' : 'configurator_design';
    $cat_tax    = $design_cpt === 'tsa_design' ? 'tsa_design_category' : 'design_category';
    $designs    = [];
    $next_reveal = null;

    // Identify the party's own school category so the grouped reveal can skip it
    // (it duplicates the page/section title) and instead group designs by their
    // theme sub-category — Football, Color Guard, etc. — mirroring the Collection
    // view. A design tagged only with the school category falls into "More Designs".
    $party_store_slug = get_post_meta( $pid, '_tsa_party_store_slug', true );
    $store_name_lc    = function_exists( 'tsa_store_name' ) ? strtolower( (string) tsa_store_name( $party_store_slug ) ) : '';
    $store_slug_norm  = sanitize_title( (string) $party_store_slug );
    $is_school_cat = function ( $t ) use ( $party_store_slug, $store_slug_norm, $store_name_lc ) {
        return $t->slug === 'schools'
            || ( $party_store_slug && $t->slug === $party_store_slug )
            || ( $store_slug_norm && sanitize_title( $t->name ) === $store_slug_norm )
            || ( $store_name_lc && strtolower( $t->name ) === $store_name_lc );
    };

    foreach ( $schedule as $row ) {
        $did        = absint( $row['design_id'] ?? 0 );
        $reveal_str = $row['reveal_at'] ?? '';
        if ( ! $did ) continue;
        $reveal_ts  = tsa_party_ts( $reveal_str );
        $is_revealed= $reveal_ts && $now >= $reveal_ts;

        if ( ! $is_revealed && $reveal_ts && ( $next_reveal === null || $reveal_ts < $next_reveal ) ) {
            $next_reveal = $reveal_ts;
        }

        $dp      = get_post( $did );
        $preview = get_post_meta( $did, '_design_preview_url', true )
                 ?: ( get_the_post_thumbnail_url( $did, 'medium' ) ?: null );

        // Categories drive the grouped reveal layout. The PRIMARY category (the
        // section a design lands in) is the most specific (deepest) theme category,
        // skipping the school's own category + the 'schools' nav category. Designs
        // with no qualifying category fall into a "More Designs" bucket on the front
        // end. (Same rule the Collection view uses, so the two stay consistent.)
        $cats    = [];
        $primary = null; $best_depth = -1;
        $terms = wp_get_post_terms( $did, $cat_tax, [ 'orderby' => 'term_id', 'order' => 'ASC' ] );
        if ( ! is_wp_error( $terms ) ) {
            foreach ( $terms as $t ) {
                $cats[] = [ 'slug' => $t->slug, 'name' => $t->name ];
                if ( $is_school_cat( $t ) ) continue;
                $depth = count( get_ancestors( $t->term_id, $cat_tax ) );
                if ( $depth > $best_depth ) {
                    $best_depth = $depth;
                    $primary    = [ 'slug' => $t->slug, 'name' => $t->name ];
                }
            }
        }

        $designs[] = [
            'id'          => $did,
            'name'        => $dp ? $dp->post_title : '',
            'thumbnail'   => $preview,
            'reveal_at'   => $reveal_str,
            'reveal_ts'   => $reveal_ts,
            'is_revealed' => $is_revealed,
            'categories'  => $cats,
            'category'    => $primary,
        ];
    }

    return new WP_REST_Response( [
        'page_id'          => $pid,
        'theme'            => get_post_meta( $pid, '_tsa_party_theme', true ),
        'status'           => $status,
        'end_date_iso'     => $end_ts    ? date( 'c', $end_ts )    : null,
        'grace_end_iso'    => $grace_end ? date( 'c', $grace_end ) : null,
        'next_reveal_iso'  => $next_reveal ? date( 'c', $next_reveal ) : null,
        'configurator_url' => home_url( '/configurator/' ),
        'party_garments'   => $apparel_mode === 'specific' && $garment_ids ? implode( ',', $garment_ids ) : '',
        'party_garment_colors' => ( $apparel_mode === 'specific' && ! empty( $garment_colors ) ) ? $garment_colors : (object) [],
        'designs'          => $designs,
        'revealed_count'   => count( array_filter( $designs, function( $d ) { return $d['is_revealed']; } ) ),
        'total_count'      => count( $designs ),
    ], 200 );
}


/* ─── 4. DESIGN RELEASE TO SCHOOL LIBRARY ────────────────────────────
   A school party is an exclusive, discounted drop window. When it closes,
   its scheduled designs are tagged into the linked school's Design Library
   (tsa_design_store term) so they become part of the school's permanent
   catalog at regular price. Fires automatically at close (scheduled in the
   save handler), lazily on first closed view (REST), or manually.
─────────────────────────────────────────────────────────────────── */

function tsa_party_release_designs( int $party_id, bool $force = false ): bool {
    $slug = sanitize_title( get_post_meta( $party_id, '_tsa_party_store_slug', true ) );
    if ( ! $slug ) return false;
    if ( ! $force && get_post_meta( $party_id, '_tsa_party_released', true ) === '1' ) return true;
    if ( ! taxonomy_exists( 'tsa_design_store' ) ) return false;

    if ( ! term_exists( $slug, 'tsa_design_store' ) ) {
        $name = function_exists( 'tsa_store_name' ) ? tsa_store_name( $slug ) : ucwords( str_replace( '-', ' ', $slug ) );
        wp_insert_term( $name, 'tsa_design_store', [ 'slug' => $slug ] );
    }

    $schedule = json_decode( get_post_meta( $party_id, '_tsa_party_schedule', true ) ?: '[]', true ) ?: [];
    foreach ( $schedule as $row ) {
        $did = absint( $row['design_id'] ?? 0 );
        if ( ! $did ) continue;
        // Make the design store-EXCLUSIVE: scope it to THIS store only and drop the
        // global 'tsa' (Main TSA) scope. Merch/school/team/business designs must stay
        // inside their own shop and never appear in the public Design Library, which
        // shows Main-TSA designs only. Replace (append=false) strips any prior scope,
        // including 'tsa'. Re-running release (force) also cleans up already-released
        // designs that still carry 'tsa'.
        wp_set_post_terms( $did, [ $slug ], 'tsa_design_store', false ); // replace → exclusive to this store
        // Drops are uploaded hidden (often a draft, "Show in configurator" OFF) so
        // they stay out of the catalog until they drop. On release they become a
        // permanent library item — publish + activate so they show in the library.
        update_post_meta( $did, '_ac_configurator_active', '1' );
        if ( get_post_status( $did ) !== 'publish' ) {
            wp_update_post( [ 'ID' => $did, 'post_status' => 'publish' ] );
        }
    }
    update_post_meta( $party_id, '_tsa_party_released', '1' );
    update_post_meta( $party_id, '_tsa_party_released_at', time() );
    return true;
}

// Auto-release at close (one-time event scheduled when the party is saved).
add_action( 'tsa_party_release_event', function ( $party_id ) { tsa_party_release_designs( (int) $party_id ); } );

/* ─── Per-drop activation ────────────────────────────────────────────
   A drop's counterpart to the close-release above. Drops are uploaded
   hidden (draft + "Show in configurator" OFF) so they stay out of the
   catalog until their reveal time. When a design drops it must become a
   real, orderable item for the rest of the party — published + activated —
   or its "Configure & Order" link fails the configurator's active-design
   check. Runs from three places: a cron event at each drop time, the REST
   poll (lazy fallback), and immediately on save for already-passed drops.
   Idempotent: each write is guarded so unchanged designs are skipped.
─────────────────────────────────────────────────────────────────── */
function tsa_party_activate_dropped_designs( int $party_id ): void {
    if ( get_post_meta( $party_id, '_tsa_party_active', true ) !== '1' ) return;
    $st = tsa_party_status( $party_id );
    if ( ! in_array( $st['status'], [ 'live', 'grace' ], true ) ) return; // close handles the rest

    $now      = time();
    $schedule = json_decode( get_post_meta( $party_id, '_tsa_party_schedule', true ) ?: '[]', true ) ?: [];
    foreach ( $schedule as $row ) {
        $did = absint( $row['design_id'] ?? 0 );
        if ( ! $did ) continue;
        $rt = tsa_party_ts( $row['reveal_at'] ?? '' );
        if ( ! $rt || $now < $rt ) continue; // not dropped yet

        if ( get_post_meta( $did, '_ac_configurator_active', true ) !== '1' ) {
            update_post_meta( $did, '_ac_configurator_active', '1' );
        }
        $ps = get_post_status( $did );
        if ( $ps && $ps !== 'publish' ) {
            wp_update_post( [ 'ID' => $did, 'post_status' => 'publish' ] );
        }
    }
}

// Activate a drop's designs at its reveal time (events scheduled in the save handler).
add_action( 'tsa_party_drop_event', function ( $party_id ) { tsa_party_activate_dropped_designs( (int) $party_id ); } );

// Manual release (meta-box "Release now" button → AJAX).
add_action( 'wp_ajax_tsa_party_release', function () {
    check_ajax_referer( 'tsa_party_release', 'nonce' );
    $pid = absint( $_POST['party_id'] ?? 0 );
    if ( ! $pid || ! current_user_can( 'edit_post', $pid ) ) wp_send_json_error( [ 'message' => 'Not allowed.' ] );
    if ( tsa_party_release_designs( $pid, true ) ) {
        wp_send_json_success( [ 'message' => 'Released into the store\'s library (now exclusive to that store).' ] );
    }
    wp_send_json_error( [ 'message' => 'Link a school to this party first.' ] );
} );
