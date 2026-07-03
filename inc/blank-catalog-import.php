<?php
/**
 * Blank Apparel Catalog — Import from S&S Activewear (style-level)
 *
 * Blank Apparel → Import from S&S. Search by style # or brand name; each
 * matching S&S STYLE imports as a buyable `configurator_garment` (colors,
 * sizes & mockups pulled from S&S) and ensures a `tsa_brand` card for its
 * brand. Dedup is per S&S style id (`_ac_ss_style_id`). Base price is left at
 * 0 for the admin to set; until then the product page shows "Pricing coming
 * soon". One-at-a-time imports = a batch (click each style you want).
 *
 * Self-contained S&S access (reuses the shared `ac_ss_username` /
 * `ac_ss_password` options). Writes the plugin's `configurator_garment` CPT
 * directly — no plugin dependency for the import itself. Included via
 * functions.php (after inc/blank-catalog.php).
 */

defined( 'ABSPATH' ) || exit;

/* ─── S&S API helper (theme-local, plugin-independent) ─────────────────── */

const TSA_SS_API_BASE = 'https://api.ssactivewear.com/v2/';
const TSA_SS_CDN      = 'https://cdn.ssactivewear.com/';

/** Basic-auth GET against the S&S API. Returns decoded array or WP_Error. */
function tsa_ss_request( $endpoint ) {
    $username = get_option( 'ac_ss_username', '' );
    $password = get_option( 'ac_ss_password', '' );
    if ( ! $username || ! $password ) {
        return new WP_Error( 'no_credentials', 'S&S API credentials are not configured (set them under Configurator → Settings → S&S).' );
    }

    $response = wp_remote_get( TSA_SS_API_BASE . ltrim( $endpoint, '/' ), [
        'timeout' => 15,
        'headers' => [
            'Authorization' => 'Basic ' . base64_encode( "{$username}:{$password}" ),
            'Accept'        => 'application/json',
        ],
    ] );

    if ( is_wp_error( $response ) ) {
        return new WP_Error( 'http_error', 'Could not reach S&S: ' . $response->get_error_message() );
    }
    $code = wp_remote_retrieve_response_code( $response );
    $body = wp_remote_retrieve_body( $response );
    if ( $code === 401 ) return new WP_Error( 'auth_failed', 'S&S API: invalid credentials (401). Check the account number + API key.' );
    if ( $code !== 200 ) {
        $snippet = mb_substr( trim( wp_strip_all_tags( (string) $body ) ), 0, 200 );
        return new WP_Error( 'api_error', "S&S returned HTTP {$code}. {$snippet}" );
    }
    $data = json_decode( $body, true );
    if ( json_last_error() !== JSON_ERROR_NONE ) {
        return new WP_Error( 'json_error', 'S&S returned non-JSON.' );
    }
    return is_array( $data ) ? $data : [];
}

/** Prepend the S&S CDN to a relative image path. */
function tsa_ss_cdn_url( $path ) {
    $path = trim( (string) $path );
    if ( ! $path ) return '';
    return ( strpos( $path, 'http' ) === 0 ) ? $path : TSA_SS_CDN . ltrim( $path, '/' );
}

/* ─── Search (style-level) + dedup ─────────────────────────────────────── */

/** Search S&S styles → flat list of styles (id, style#, brand, name, image). */
function tsa_ss_search_styles( $query ) {
    $styles = tsa_ss_request( 'styles/?search=' . rawurlencode( $query ) . '&mediatype=json' );
    if ( is_wp_error( $styles ) ) return $styles;
    $out = [];
    foreach ( (array) $styles as $s ) {
        if ( ! is_array( $s ) ) continue;
        $id = (int) ( $s['styleID'] ?? $s['styleId'] ?? 0 );
        if ( ! $id ) continue;
        $img = $s['styleImage'] ?? $s['image'] ?? $s['brandImage'] ?? '';
        $out[] = [
            'id'          => $id,
            'style'       => $s['partNumber'] ?? $s['styleName'] ?? '',
            'brand'       => $s['brandName']  ?? '',
            'name'        => $s['title']      ?? $s['styleName'] ?? '',
            'image'       => tsa_ss_cdn_url( $img ),
            'brand_image' => tsa_ss_cdn_url( $s['brandImage'] ?? '' ),
        ];
        if ( count( $out ) >= 24 ) break;
    }
    return $out;
}

/** True if a configurator_garment already exists for this S&S style id. */
function tsa_ss_style_imported( $style_id ) {
    $q = get_posts( [
        'post_type'   => 'configurator_garment',
        'post_status' => 'any',
        'numberposts' => 1,
        'fields'      => 'ids',
        'meta_query'  => [ [ 'key' => '_ac_ss_style_id', 'value' => (string) (int) $style_id ] ],
    ] );
    return ! empty( $q );
}

/* ─── Fetch + normalize one style into the garment shape ───────────────── */

/**
 * Fetch every SKU (color × size) for one S&S style and normalize it into the
 * configurator garment shape. Mirrors the plugin's AC_SS_API::normalise().
 * Returns [ 'styles'=>[...], 'mockups'=>{...}, 'color_count'=>int,
 *           'brand'=>'', 'style_name'=>'' ] or WP_Error.
 */
function tsa_ss_fetch_style_products( $style_id ) {
    $skus = tsa_ss_request( 'products/?styleid=' . (int) $style_id . '&mediatype=json' );
    if ( is_wp_error( $skus ) ) return $skus;
    if ( empty( $skus ) || ! is_array( $skus ) ) {
        return new WP_Error( 'not_found', 'No products for that style (it may be discontinued).' );
    }

    $color_map  = [];  // color name => [sizes]
    $color_meta = [];  // color name => [hex, front, back]
    $brand = ''; $style_name = '';
    $size_order = [ 'XS','S','M','L','XL','2XL','XXL','3XL','XXXL','4XL','5XL','6XL','YXS','YS','YM','YL','YXL','OSFA' ];

    foreach ( $skus as $sku ) {
        if ( ! is_array( $sku ) ) continue;
        if ( ! $brand )      $brand      = $sku['brandName'] ?? '';
        if ( ! $style_name ) $style_name = $sku['styleName'] ?? '';
        $color = $sku['colorName'] ?? '';
        $size  = $sku['sizeName']  ?? '';
        if ( ! $color || ! $size ) continue;
        if ( ! isset( $color_map[ $color ] ) ) {
            $color_map[ $color ]  = [];
            $hex = $sku['color1'] ?? $sku['colorHex'] ?? '';
            $hex = $hex ? ( $hex[0] === '#' ? $hex : '#' . $hex ) : '#888888';
            if ( ! preg_match( '/^#[0-9a-fA-F]{6}$/', $hex ) ) $hex = '#888888';
            $color_meta[ $color ] = [
                'hex'   => $hex,
                'front' => tsa_ss_cdn_url( $sku['colorFrontImage'] ?? '' ),
                'back'  => tsa_ss_cdn_url( $sku['colorBackImage']  ?? '' ),
            ];
        }
        $color_map[ $color ][] = $size;
    }

    $colors = [];
    foreach ( $color_map as $cname => $sizes ) {
        $sizes = array_values( array_unique( $sizes ) );
        usort( $sizes, function ( $a, $b ) use ( $size_order ) {
            $ia = array_search( strtoupper( $a ), $size_order, true ); $ia = $ia === false ? 999 : $ia;
            $ib = array_search( strtoupper( $b ), $size_order, true ); $ib = $ib === false ? 999 : $ib;
            return $ia <=> $ib;
        } );
        $colors[] = [ 'name' => $cname, 'hex' => $color_meta[ $cname ]['hex'], 'sizes_available' => $sizes ];
    }

    $mockups = [];
    foreach ( $color_meta as $cname => $m ) {
        $entry = [];
        if ( $m['front'] ) $entry['front'] = $m['front'];
        if ( $m['back']  ) $entry['back']  = $m['back'];
        if ( $entry ) $mockups[ $cname ] = $entry;
    }

    return [
        'styles'      => [ [ 'name' => $style_name ?: (string) $style_id, 'colors' => $colors ] ],
        'mockups'     => $mockups,
        'color_count' => count( $color_map ),
        'brand'       => $brand,
        'style_name'  => $style_name,
    ];
}

/** Human description (HTML) for one S&S style, '' if none. Used to fill the
 *  garment content so the apparel-page Description populates on import. */
function tsa_ss_fetch_style_description( $style_id ) {
    $styles = tsa_ss_request( 'styles/?styleid=' . (int) $style_id . '&mediatype=json' );
    if ( is_wp_error( $styles ) || ! is_array( $styles ) ) return '';
    $s = reset( $styles );
    if ( ! is_array( $s ) ) return '';
    $desc = $s['description'] ?? '';
    return is_string( $desc ) ? trim( $desc ) : '';
}

/* ─── Find-or-create the brand card ────────────────────────────────────── */

/** Find-or-create a tsa_brand for $brand; returns post ID. Sideloads logo if new. */
function tsa_ensure_brand_card( $brand, $logo_url = '' ) {
    if ( $brand === '' ) return 0;
    $existing = get_posts( [ 'post_type' => 'tsa_brand', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
        'meta_query' => [ [ 'key' => '_tsa_ss_brand', 'value' => $brand ] ] ] );
    if ( ! $existing ) {
        $existing = get_posts( [ 'post_type' => 'tsa_brand', 'post_status' => 'any', 'numberposts' => 1, 'fields' => 'ids',
            'title' => $brand ] ); // fallback: match by title
    }
    if ( $existing ) return (int) $existing[0];

    $bid = wp_insert_post( [ 'post_type' => 'tsa_brand', 'post_status' => 'publish', 'post_title' => $brand ], true );
    if ( is_wp_error( $bid ) || ! $bid ) return 0;
    update_post_meta( $bid, '_tsa_ss_brand', $brand );
    update_post_meta( $bid, '_tsa_brand_category', 'tees' );
    if ( $logo_url ) {
        require_once ABSPATH . 'wp-admin/includes/file.php';
        require_once ABSPATH . 'wp-admin/includes/media.php';
        require_once ABSPATH . 'wp-admin/includes/image.php';
        $att = media_sideload_image( $logo_url, $bid, $brand, 'id' );
        if ( ! is_wp_error( $att ) ) set_post_thumbnail( $bid, $att );
    }
    return (int) $bid;
}

/** Main store post ID (the public 'tsa' store), or 0. Imported garments are
 *  assigned to it so they appear in the store-scoped configurator picker. */
function tsa_main_store_id() {
    $main = apply_filters( 'tsa_design_main_store_slug', 'tsa' );
    $q = get_posts( [ 'post_type' => 'configurator_store', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids',
        'meta_query' => [ [ 'key' => '_ac_store_slug', 'value' => $main ] ] ] );
    return $q ? (int) $q[0] : 0;
}

/** One-time backfill for existing catalog-imported garments: flag them as
 *  in-catalog (`_tsa_in_catalog`=1) and assign the main store (so "change
 *  apparel" works). Catalog flag is set regardless of store availability. */
add_action( 'admin_init', function () {
    if ( get_option( 'tsa_garment_backfilled_v2' ) ) return;
    $store_id = tsa_main_store_id(); // may be 0
    $garments = get_posts( [ 'post_type' => 'configurator_garment', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids',
        'meta_query' => [ [ 'key' => '_ac_ss_style_id', 'compare' => 'EXISTS' ] ] ] );
    foreach ( $garments as $gid ) {
        if ( get_post_meta( $gid, '_tsa_in_catalog', true ) === '' ) update_post_meta( $gid, '_tsa_in_catalog', '1' );
        if ( $store_id && ! get_post_meta( $gid, '_ac_store_id', true ) ) update_post_meta( $gid, '_ac_store_id', $store_id );
    }
    update_option( 'tsa_garment_backfilled_v2', 1 );
} );

/** v3: blanks are global. The v2 backfill (and old imports) auto-assigned S&S
 *  garments to the main 'tsa' store as a visibility workaround; under the new
 *  model that pins them to tsa-only. Clear `_ac_store_id` for S&S imports that
 *  are *still* on the main store so they become available in every store. Any
 *  garment manually assigned to a real (non-main) store is left untouched. */
add_action( 'admin_init', function () {
    if ( get_option( 'tsa_garment_backfilled_v3' ) ) return;
    $main_id = tsa_main_store_id();
    if ( $main_id ) {
        $garments = get_posts( [ 'post_type' => 'configurator_garment', 'post_status' => 'any', 'numberposts' => -1, 'fields' => 'ids',
            'meta_query' => [ 'relation' => 'AND',
                [ 'key' => '_ac_ss_style_id', 'compare' => 'EXISTS' ],
                [ 'key' => '_ac_store_id',    'value'   => $main_id ],
            ] ] );
        foreach ( $garments as $gid ) update_post_meta( $gid, '_ac_store_id', '' );
    }
    update_option( 'tsa_garment_backfilled_v3', 1 );
} );

/* ─── Admin page ───────────────────────────────────────────────────────── */

add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=tsa_brand',
        'Import from S&S',
        'Import from S&S',
        'edit_posts',
        'tsa-brand-import',
        'tsa_brand_import_page'
    );
} );

function tsa_brand_import_page() {
    if ( ! current_user_can( 'edit_posts' ) ) return;
    $ajax  = admin_url( 'admin-ajax.php' );
    $nonce = wp_create_nonce( 'tsa_brand_import' );
    $configured = get_option( 'ac_ss_username' ) && get_option( 'ac_ss_password' );
    ?>
    <div class="wrap">
        <h1>Import from S&amp;S Activewear</h1>
        <p>Search by <strong>style #</strong> or <strong>brand name</strong>. Each matching style imports as a buyable garment — colors, sizes &amp; mockups are pulled from S&amp;S, and the brand card is created/updated automatically. Set each garment's price afterward.</p>

        <?php if ( ! $configured ) : ?>
            <div class="notice notice-warning"><p><strong>S&amp;S credentials are not set.</strong> Add them under <em>Configurator → Settings → S&amp;S</em> first, then search here.</p></div>
        <?php endif; ?>

        <style>
            .tsa-imp-search{display:flex;gap:8px;align-items:center;margin:14px 0;max-width:560px}
            .tsa-imp-search input[type=text]{flex:1}
            #tsa-imp-status{margin:8px 0;font-size:13px}
            #tsa-imp-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:16px;margin-top:18px;max-width:1000px}
            .tsa-imp-card{border:1px solid #dcdcde;border-radius:8px;background:#fff;overflow:hidden;display:flex;flex-direction:column}
            .tsa-imp-card__img{height:150px;background:#f6f7f7 center/contain no-repeat;border-bottom:1px solid #f0f0f1}
            .tsa-imp-card__b{padding:12px;display:flex;flex-direction:column;gap:6px;flex:1}
            .tsa-imp-card__b h3{margin:0;font-size:14px}
            .tsa-imp-card__b .meta{color:#787c82;font-size:12px}
            .tsa-imp-card__b .act{margin-top:auto}
            .tsa-imp-card .in{color:#1a7f37;font-weight:600;font-size:13px}
            .tsa-imp-card .err{color:#b32d2e;font-size:12px}
        </style>

        <div class="tsa-imp-search">
            <input type="text" id="tsa-imp-q" class="regular-text" placeholder="e.g. 3001, Bella Canvas, Gildan" <?php disabled( ! $configured ); ?>>
            <button type="button" class="button button-primary" id="tsa-imp-go" <?php disabled( ! $configured ); ?>>Search S&amp;S</button>
        </div>
        <div id="tsa-imp-status"></div>
        <div id="tsa-imp-grid"></div>

        <script>
        (function(){
            var AJAX=<?php echo wp_json_encode( $ajax ); ?>, NONCE=<?php echo wp_json_encode( $nonce ); ?>;
            var q=document.getElementById('tsa-imp-q'), go=document.getElementById('tsa-imp-go');
            var grid=document.getElementById('tsa-imp-grid'), status=document.getElementById('tsa-imp-status');
            function esc(s){return String(s==null?'':s).replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];});}

            function card(s){
                var el=document.createElement('div'); el.className='tsa-imp-card';
                var img=s.image?'style="background-image:url(\''+esc(s.image)+'\')"':'';
                var act=s.imported
                    ? '<span class="in">✓ Imported</span>'
                    : '<button type="button" class="button button-primary tsa-imp-add">Import</button>';
                el.innerHTML='<div class="tsa-imp-card__img" '+img+'></div>'+
                    '<div class="tsa-imp-card__b"><h3>'+esc(s.style)+'</h3>'+
                    '<span class="meta">'+esc(s.brand)+'</span>'+
                    '<span class="meta">'+esc(s.name)+'</span>'+
                    '<div class="act">'+act+'</div></div>';
                var btn=el.querySelector('.tsa-imp-add');
                if(btn){ btn.addEventListener('click',function(){ doImport(s,el,btn); }); }
                return el;
            }

            function doImport(s,el,btn){
                btn.disabled=true; btn.textContent='Importing…';
                var fd=new FormData();
                fd.append('action','tsa_brand_ss_import'); fd.append('nonce',NONCE);
                fd.append('style_id',s.id); fd.append('brand',s.brand||'');
                fd.append('image',s.image||''); fd.append('brand_image',s.brand_image||'');
                fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'})
                    .then(function(r){return r.json();})
                    .then(function(j){
                        var actEl=el.querySelector('.act');
                        if(j&&j.success){
                            actEl.innerHTML='<span class="in">✓ Imported — <a href="'+esc(j.data.edit)+'">Set price</a></span>';
                        }else{
                            btn.disabled=false; btn.textContent='Import';
                            actEl.insertAdjacentHTML('beforeend','<div class="err">'+esc((j&&j.data&&j.data.message)||'Failed')+'</div>');
                        }
                    })
                    .catch(function(){ btn.disabled=false; btn.textContent='Import'; });
            }

            function search(){
                var term=q.value.trim();
                if(!term){ status.textContent='Enter a style # or brand name.'; status.style.color='#b32d2e'; return; }
                go.disabled=true; go.textContent='Searching…';
                status.textContent='Searching S&S…'; status.style.color='#787c82'; grid.innerHTML='';
                var fd=new FormData(); fd.append('action','tsa_brand_ss_search'); fd.append('nonce',NONCE); fd.append('q',term);
                fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'})
                    .then(function(r){return r.json();})
                    .then(function(j){
                        go.disabled=false; go.textContent='Search S&S';
                        if(!j||!j.success){ status.textContent='Error: '+((j&&j.data)||'request failed'); status.style.color='#b32d2e'; return; }
                        var list=j.data||[];
                        if(!list.length){ status.textContent='No styles matched.'; status.style.color='#b32d2e'; return; }
                        status.textContent=list.length+' style'+(list.length==1?'':'s')+' found.'; status.style.color='#1a7f37';
                        list.forEach(function(s){ grid.appendChild(card(s)); });
                    })
                    .catch(function(){ go.disabled=false; go.textContent='Search S&S'; status.textContent='Network error.'; status.style.color='#b32d2e'; });
            }
            go.addEventListener('click',search);
            q.addEventListener('keydown',function(e){ if(e.key==='Enter'){ e.preventDefault(); search(); } });
        })();
        </script>
    </div>
    <?php
}

/* ─── AJAX: search → style cards ───────────────────────────────────────── */

add_action( 'wp_ajax_tsa_brand_ss_search', 'tsa_brand_ss_search_ajax' );
function tsa_brand_ss_search_ajax() {
    check_ajax_referer( 'tsa_brand_import', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( 'Permission denied' );

    $q = sanitize_text_field( wp_unslash( $_POST['q'] ?? '' ) );
    if ( $q === '' ) wp_send_json_error( 'Empty search.' );

    $styles = tsa_ss_search_styles( $q );
    if ( is_wp_error( $styles ) ) wp_send_json_error( $styles->get_error_message() );

    $out = [];
    foreach ( $styles as $s ) {
        $out[] = [
            'id'          => $s['id'],
            'style'       => $s['style'],
            'brand'       => $s['brand'],
            'name'        => $s['name'],
            'image'       => $s['image'],
            'brand_image' => $s['brand_image'],
            'imported'    => tsa_ss_style_imported( $s['id'] ),
        ];
    }
    wp_send_json_success( $out );
}

/* ─── AJAX: import ONE style → configurator_garment + ensure brand card ── */

add_action( 'wp_ajax_tsa_brand_ss_import', 'tsa_brand_ss_import_ajax' );
function tsa_brand_ss_import_ajax() {
    check_ajax_referer( 'tsa_brand_import', 'nonce' );
    if ( ! current_user_can( 'edit_posts' ) ) wp_send_json_error( [ 'message' => 'Permission denied' ] );

    $style_id   = (int) ( $_POST['style_id'] ?? 0 );
    $brand      = sanitize_text_field( wp_unslash( $_POST['brand'] ?? '' ) );
    $brand_logo = esc_url_raw( wp_unslash( $_POST['brand_image'] ?? '' ) ); // brand LOGO, not the style photo
    if ( ! $style_id ) wp_send_json_error( [ 'message' => 'Missing style id.' ] );
    if ( tsa_ss_style_imported( $style_id ) ) wp_send_json_error( [ 'message' => 'Already imported.' ] );

    $data = tsa_ss_fetch_style_products( $style_id );
    if ( is_wp_error( $data ) ) wp_send_json_error( [ 'message' => $data->get_error_message() ] );
    if ( ! $brand && $data['brand'] ) $brand = $data['brand'];

    $title = trim( ( $brand ? $brand . ' ' : '' ) . ( $data['style_name'] ?: $style_id ) );
    $desc  = tsa_ss_fetch_style_description( $style_id ); // vendor description → Description tab
    $gid = wp_insert_post( [
        'post_type'    => 'configurator_garment',
        'post_status'  => 'publish',
        'post_title'   => $title,
        'post_content' => $desc !== '' ? wp_kses_post( $desc ) : '',
    ], true );
    if ( is_wp_error( $gid ) || ! $gid ) wp_send_json_error( [ 'message' => 'Could not create garment.' ] );

    update_post_meta( $gid, '_ac_ss_style_id',   (string) $style_id );
    update_post_meta( $gid, '_ac_brand',         $brand );
    update_post_meta( $gid, '_ac_styles',        $data['styles'] );
    update_post_meta( $gid, '_ac_mockup_images', $data['mockups'] );
    update_post_meta( $gid, '_ac_base_price',    0 );
    update_post_meta( $gid, '_ac_is_active',     '1' );
    update_post_meta( $gid, '_ac_allowed_zones', [ 'front_full', 'back_full' ] );
    update_post_meta( $gid, '_tsa_in_catalog', '1' ); // By Catalog visibility (toggle on the garment editor)
    // Leave the store unset: S&S blanks are global and appear in every store's
    // configurator (see the route-garments store filter — blank = available
    // everywhere). Assign a store on the garment editor only to make a blank
    // exclusive to one store.
    update_post_meta( $gid, '_ac_store_id', '' );

    // Featured image: first color's front mockup.
    if ( ! empty( $data['mockups'] ) ) {
        $first = reset( $data['mockups'] );
        $front = $first['front'] ?? '';
        if ( $front ) {
            require_once ABSPATH . 'wp-admin/includes/file.php';
            require_once ABSPATH . 'wp-admin/includes/media.php';
            require_once ABSPATH . 'wp-admin/includes/image.php';
            $att = media_sideload_image( $front, $gid, $title, 'id' );
            if ( ! is_wp_error( $att ) ) set_post_thumbnail( $gid, $att );
        }
    }

    tsa_ensure_brand_card( $brand, $brand_logo );
    update_option( 'tsa_flush_rewrites', 1 ); // functions.php flushes on next init

    // Explicit edit URL (get_edit_post_link can return empty for some CPT cap setups).
    wp_send_json_success( [ 'id' => $gid, 'edit' => admin_url( 'post.php?post=' . $gid . '&action=edit' ) ] );
}

/* ─── Per-garment: pull the S&S description on demand (back-fill) ─────────
   For garments already imported from S&S, a one-click button re-fetches the
   vendor description into the content (Description tab). Reloads the editor so
   the pulled text is visible and survives the next save. */
add_action( 'add_meta_boxes', function () {
    add_meta_box( 'tsa_ss_pull_desc', 'Vendor Description (S&S)', 'tsa_ss_pull_desc_box', 'configurator_garment', 'side', 'default' );
} );
function tsa_ss_pull_desc_box( $post ) {
    $style_id = get_post_meta( $post->ID, '_ac_ss_style_id', true );
    if ( ! $style_id ) {
        echo '<p class="description" style="margin:0">Not an S&amp;S import — type the description in the editor.</p>';
        return;
    }
    $has = trim( (string) $post->post_content ) !== '';
    $url = wp_nonce_url(
        admin_url( 'admin-post.php?action=tsa_pull_ss_desc&post=' . (int) $post->ID ),
        'tsa_pull_ss_desc_' . (int) $post->ID
    );
    echo '<p class="description" style="margin-top:0">' . ( $has
        ? 'A description is set. Pulling will <strong>replace</strong> it with the current S&amp;S text.'
        : 'No description yet — pull the vendor description from S&amp;S.' ) . '</p>';
    echo '<a href="' . esc_url( $url ) . '" class="button button-secondary">⤓ Pull description from S&amp;S</a>';
    echo '<p class="description" style="margin-top:8px">Reloads the editor. Save any other edits first. (S&amp;S style <code>' . esc_html( $style_id ) . '</code>.)</p>';
}

add_action( 'admin_post_tsa_pull_ss_desc', function () {
    $post_id = (int) ( $_GET['post'] ?? 0 );
    if ( ! $post_id || ! current_user_can( 'edit_post', $post_id ) ) wp_die( 'Permission denied.' );
    check_admin_referer( 'tsa_pull_ss_desc_' . $post_id );

    $style_id = get_post_meta( $post_id, '_ac_ss_style_id', true );
    $state = 'err';
    if ( $style_id ) {
        $desc = tsa_ss_fetch_style_description( (int) $style_id );
        if ( $desc !== '' ) {
            wp_update_post( [ 'ID' => $post_id, 'post_content' => wp_kses_post( $desc ) ] );
            $state = 'ok';
        } else {
            $state = 'empty';
        }
    }
    wp_safe_redirect( add_query_arg( 'tsa_desc', $state, admin_url( 'post.php?post=' . $post_id . '&action=edit' ) ) );
    exit;
} );

add_action( 'admin_notices', function () {
    $s = isset( $_GET['tsa_desc'] ) ? sanitize_key( wp_unslash( $_GET['tsa_desc'] ) ) : '';
    if ( ! $s ) return;
    $screen = get_current_screen();
    if ( ! $screen || $screen->id !== 'configurator_garment' ) return;
    if ( $s === 'ok' ) {
        echo '<div class="notice notice-success is-dismissible"><p>Description pulled from S&amp;S.</p></div>';
    } elseif ( $s === 'empty' ) {
        echo '<div class="notice notice-warning is-dismissible"><p>S&amp;S returned no description for this style.</p></div>';
    } else {
        echo '<div class="notice notice-error is-dismissible"><p>Could not pull the description from S&amp;S.</p></div>';
    }
} );
