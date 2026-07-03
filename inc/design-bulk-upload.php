<?php
/**
 * TSA Design Library — Bulk Upload (AJAX, per-file progress)
 *
 * Design Library → Bulk Upload. Select many design images; each is uploaded
 * one at a time via AJAX so the page shows a live queue (filename + status)
 * and a progress bar. Each file becomes a `tsa_design`:
 *   - title = cleaned filename, image = featured + preview + print file
 *   - batch Store / Categories (multi) / Print Method / Status applied to all
 *
 * Included via functions.php (after inc/design-library.php registers the CPT).
 */

defined( 'ABSPATH' ) || exit;

add_action( 'admin_menu', function () {
    add_submenu_page(
        'edit.php?post_type=tsa_design',
        'Bulk Upload Designs',
        'Bulk Upload',
        'edit_tsa_designs',
        'tsa-design-bulk',
        'tsa_design_bulk_page'
    );
} );

function tsa_design_bulk_page() {
    if ( ! current_user_can( 'edit_tsa_designs' ) ) return;

    $cats    = get_terms( [ 'taxonomy' => 'tsa_design_category', 'hide_empty' => false, 'orderby' => 'name' ] );
    $methods = get_terms( [ 'taxonomy' => 'tsa_design_method',   'hide_empty' => false, 'orderby' => 'name' ] );

    // Ordered parent → child list for the category checkboxes
    $cat_list = [];
    if ( ! is_wp_error( $cats ) ) {
        foreach ( $cats as $c ) { if ( ! $c->parent ) {
            $cat_list[] = $c;
            foreach ( $cats as $cc ) { if ( (int) $cc->parent === (int) $c->term_id ) $cat_list[] = $cc; }
        } }
    }

    $ajax  = admin_url( 'admin-ajax.php' );
    $nonce = wp_create_nonce( 'tsa_bulk_upload' );
    ?>
    <div class="wrap">
        <h1>Bulk Upload Designs</h1>
        <p>Select multiple image files (PNG, JPG, SVG, GIF). Each becomes a design — the <strong>filename becomes the title</strong>, and the image is set as the preview, print file, and thumbnail. The batch settings apply to every file; fine-tune individual designs afterward.</p>

        <style>
            .tsa-bulk-grid{display:grid;grid-template-columns:1fr 1fr;gap:24px;max-width:920px;margin-top:14px}
            .tsa-bulk-cats{max-height:230px;overflow:auto;border:1px solid #dcdcde;border-radius:6px;padding:10px 12px;background:#fff}
            .tsa-bulk-cats label{display:block;font-size:13px;margin:2px 0}
            .tsa-bulk-cats label.child{padding-left:18px;color:#50575e}
            .tsa-bulk-cats label.parent{font-weight:600;margin-top:6px}
            #tsa-bulk-progress{display:none;max-width:920px;margin:16px 0}
            .tsa-bulk-track{height:10px;border-radius:6px;background:#e2e4e7;overflow:hidden}
            #tsa-bulk-bar{height:100%;width:0;background:#2271b1;transition:width .2s}
            #tsa-bulk-summary{font-size:13px;color:#50575e;margin-top:6px}
            #tsa-bulk-queue{list-style:none;margin:14px 0 0;padding:0;max-width:920px}
            #tsa-bulk-queue li{display:flex;justify-content:space-between;align-items:center;gap:12px;padding:8px 12px;border:1px solid #f0f0f1;border-bottom:none;background:#fff;font-size:13px}
            #tsa-bulk-queue li:last-child{border-bottom:1px solid #f0f0f1;border-radius:0 0 6px 6px}
            #tsa-bulk-queue li:first-child{border-radius:6px 6px 0 0}
            #tsa-bulk-queue .n{font-weight:500;overflow:hidden;text-overflow:ellipsis;white-space:nowrap}
            #tsa-bulk-queue .s{flex:none;font-weight:600}
            #tsa-bulk-queue .s.queued{color:#787c82}
            #tsa-bulk-queue .s.uploading{color:#2271b1}
            #tsa-bulk-queue .s.done{color:#1a7f37}
            #tsa-bulk-queue .s.err{color:#b32d2e}
        </style>

        <table class="form-table" role="presentation"><tbody>
            <tr>
                <th scope="row"><label for="tsa-bulk-files">Design files</label></th>
                <td><input type="file" id="tsa-bulk-files" multiple accept="image/png,image/jpeg,image/svg+xml,image/gif">
                    <p class="description">Hold Ctrl/Cmd or Shift to select many. Selected files appear below.</p></td>
            </tr>
            <tr>
                <th scope="row"><label for="tsa-bulk-store">Store</label></th>
                <td><input type="text" id="tsa-bulk-store" class="regular-text" value="tsa">
                    <p class="description"><code>tsa</code> = Main TSA library (public). Or a store slug like <code>dutchtown</code>.</p></td>
            </tr>
            <tr>
                <th scope="row">Categories</th>
                <td>
                    <div class="tsa-bulk-cats">
                        <?php if ( empty( $cat_list ) ) : ?>
                            <em>No categories yet.</em>
                        <?php else : foreach ( $cat_list as $c ) : ?>
                            <label class="<?php echo $c->parent ? 'child' : 'parent'; ?>">
                                <input type="checkbox" class="tsa-bulk-cat" value="<?php echo esc_attr( $c->slug ); ?>">
                                <?php echo esc_html( $c->name ); ?>
                            </label>
                        <?php endforeach; endif; ?>
                    </div>
                    <p class="description">Check one or more — all selected categories are applied to every uploaded design.</p>
                </td>
            </tr>
            <tr>
                <th scope="row"><label for="tsa-bulk-method">Print method</label></th>
                <td><select id="tsa-bulk-method">
                    <option value="">— None —</option>
                    <?php if ( ! is_wp_error( $methods ) ) foreach ( $methods as $m ) : ?>
                        <option value="<?php echo esc_attr( $m->slug ); ?>" <?php selected( 'dtf-transfer', $m->slug ); ?>><?php echo esc_html( $m->name ); ?></option>
                    <?php endforeach; ?>
                </select></td>
            </tr>
            <tr>
                <th scope="row">Status</th>
                <td>
                    <label><input type="radio" name="tsa_bulk_status" value="publish" checked> Published</label> &nbsp;&nbsp;
                    <label><input type="radio" name="tsa_bulk_status" value="draft"> Draft</label>
                </td>
            </tr>
            <tr>
                <th scope="row">Featured</th>
                <td><label><input type="checkbox" id="tsa-bulk-featured"> Mark all uploaded designs as Featured</label></td>
            </tr>
            <tr>
                <th scope="row"><label for="tsa-bulk-new">New status</label></th>
                <td><select id="tsa-bulk-new">
                    <option value="">Auto (by date added)</option>
                    <option value="always">Always New</option>
                    <option value="never">Not New</option>
                </select></td>
            </tr>
            <tr>
                <th scope="row">Configurator</th>
                <td><label><input type="checkbox" id="tsa-bulk-active" checked> Active in configurator <span class="description">(available to use on apparel)</span></label></td>
            </tr>
            <tr>
                <th scope="row">Placement zones</th>
                <td>
                    <label><input type="checkbox" class="tsa-bulk-zone" value="front_full" checked> Front</label> &nbsp;&nbsp;
                    <label><input type="checkbox" class="tsa-bulk-zone" value="front_pocket" checked> Front Pocket</label> &nbsp;&nbsp;
                    <label><input type="checkbox" class="tsa-bulk-zone" value="back_full" checked> Back Full</label>
                    <p class="description">Where the design may be placed in the configurator. Applied to every uploaded design.</p>
                </td>
            </tr>
        </tbody></table>

        <p><button type="button" class="button button-primary" id="tsa-bulk-go">Upload &amp; Create Designs</button></p>

        <div id="tsa-bulk-progress">
            <div class="tsa-bulk-track"><div id="tsa-bulk-bar"></div></div>
            <div id="tsa-bulk-summary"></div>
        </div>
        <ul id="tsa-bulk-queue"></ul>

        <script>
        (function(){
            var AJAX = <?php echo wp_json_encode( $ajax ); ?>, NONCE = <?php echo wp_json_encode( $nonce ); ?>;
            var input = document.getElementById('tsa-bulk-files');
            var btn   = document.getElementById('tsa-bulk-go');
            var queue = document.getElementById('tsa-bulk-queue');
            var prog  = document.getElementById('tsa-bulk-progress');
            var bar   = document.getElementById('tsa-bulk-bar');
            var summ  = document.getElementById('tsa-bulk-summary');

            function esc(s){ return String(s||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
            function setStatus(i,cls,txt){ var li=document.getElementById('tbi-'+i); if(!li)return; var s=li.querySelector('.s'); s.className='s '+cls; s.innerHTML=txt; }

            function renderQueue(){
                queue.innerHTML='';
                Array.prototype.forEach.call(input.files,function(f,i){
                    var li=document.createElement('li'); li.id='tbi-'+i;
                    li.innerHTML='<span class="n">'+esc(f.name)+'</span><span class="s queued">queued</span>';
                    queue.appendChild(li);
                });
                summ.textContent = input.files.length ? input.files.length+' file(s) selected' : '';
                prog.style.display = input.files.length ? 'block' : 'none';
                bar.style.width='0';
            }
            input.addEventListener('change', renderQueue);

            btn.addEventListener('click', async function(){
                var files = input.files;
                if(!files.length){ alert('Choose some design files first.'); return; }
                var store  = document.getElementById('tsa-bulk-store').value || 'tsa';
                var method = document.getElementById('tsa-bulk-method').value;
                var statusEl = document.querySelector('input[name=tsa_bulk_status]:checked');
                var status = statusEl ? statusEl.value : 'publish';
                var cats = Array.prototype.map.call(document.querySelectorAll('.tsa-bulk-cat:checked'),function(c){return c.value;});
                var active = document.getElementById('tsa-bulk-active').checked ? '1' : '0';
                var zones = Array.prototype.map.call(document.querySelectorAll('.tsa-bulk-zone:checked'),function(z){return z.value;});

                btn.disabled=true; prog.style.display='block';
                var ok=0, fail=0;
                for(var i=0;i<files.length;i++){
                    setStatus(i,'uploading','Uploading…');
                    var fd=new FormData();
                    fd.append('action','tsa_bulk_design_upload');
                    fd.append('nonce',NONCE);
                    fd.append('store',store);
                    fd.append('method',method);
                    fd.append('status',status);
                    fd.append('featured', document.getElementById('tsa-bulk-featured').checked ? '1' : '0');
                    fd.append('new_override', document.getElementById('tsa-bulk-new').value);
                    cats.forEach(function(c){ fd.append('categories[]',c); });
                    fd.append('active', active);
                    zones.forEach(function(z){ fd.append('zones[]', z); });
                    fd.append('file',files[i]);
                    try{
                        var r=await fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'});
                        var j=await r.json();
                        if(j&&j.success){ ok++; setStatus(i,'done','✓ Created'); }
                        else { fail++; setStatus(i,'err','✗ '+esc((j&&j.data&&j.data.message)||'Failed')); }
                    }catch(e){ fail++; setStatus(i,'err','✗ Network error'); }
                    bar.style.width = Math.round(((i+1)/files.length)*100)+'%';
                    summ.textContent = (i+1)+' of '+files.length+' processed — '+ok+' created'+(fail?', '+fail+' failed':'');
                }
                btn.disabled=false;
                summ.innerHTML += ' &middot; <a href="edit.php?post_type=tsa_design">View all designs &rarr;</a>';
            });
        })();
        </script>

        <p class="description" style="max-width:640px;margin-top:18px">
            <strong>Tip:</strong> very large files can hit PHP limits (<code>upload_max_filesize</code>, <code>post_max_size</code>). Files upload one at a time, so the batch size itself isn't limited — only individual file size.
        </p>
    </div>
    <?php
}

/* ── AJAX: create ONE design from ONE uploaded file + batch settings ── */
add_action( 'wp_ajax_tsa_bulk_design_upload', 'tsa_bulk_design_ajax' );
function tsa_bulk_design_ajax() {
    check_ajax_referer( 'tsa_bulk_upload', 'nonce' );
    if ( ! current_user_can( 'edit_tsa_designs' ) ) {
        wp_send_json_error( [ 'message' => 'Permission denied' ] );
    }
    if ( empty( $_FILES['file'] ) || (int) $_FILES['file']['error'] !== UPLOAD_ERR_OK ) {
        wp_send_json_error( [ 'message' => 'No file / upload error' ] );
    }

    require_once ABSPATH . 'wp-admin/includes/file.php';
    require_once ABSPATH . 'wp-admin/includes/media.php';
    require_once ABSPATH . 'wp-admin/includes/image.php';

    $store  = sanitize_key( wp_unslash( $_POST['store'] ?? 'tsa' ) ) ?: 'tsa';
    $method = sanitize_key( wp_unslash( $_POST['method'] ?? '' ) );
    $status = ( ( $_POST['status'] ?? 'publish' ) === 'draft' ) ? 'draft' : 'publish';
    $cats   = isset( $_POST['categories'] ) ? array_filter( array_map( 'sanitize_key', (array) wp_unslash( $_POST['categories'] ) ) ) : [];

    $fname = (string) $_FILES['file']['name'];
    $title = ucwords( trim( preg_replace( '/[\-_]+/', ' ', preg_replace( '/\.[^.]+$/', '', $fname ) ) ) );
    if ( $title === '' ) $title = 'Untitled Design';

    $post_id = wp_insert_post( [ 'post_type' => 'tsa_design', 'post_status' => $status, 'post_title' => $title ], true );
    if ( is_wp_error( $post_id ) || ! $post_id ) {
        wp_send_json_error( [ 'message' => 'Could not create design' ] );
    }

    $att = media_handle_upload( 'file', $post_id );
    if ( is_wp_error( $att ) ) {
        wp_delete_post( $post_id, true );
        wp_send_json_error( [ 'message' => $att->get_error_message() ] );
    }

    $url = wp_get_attachment_url( $att );
    set_post_thumbnail( $post_id, $att );
    update_post_meta( $post_id, '_design_preview_url',    $url );
    update_post_meta( $post_id, '_design_preview_id',     $att );
    update_post_meta( $post_id, '_design_print_file_url', $url );
    update_post_meta( $post_id, '_design_print_file_id',  $att );
    update_post_meta( $post_id, '_design_store_slug',     $store );

    // Store term (create if missing)
    $st = get_term_by( 'slug', $store, 'tsa_design_store' );
    if ( ! $st ) { $r = wp_insert_term( strtoupper( $store ), 'tsa_design_store', [ 'slug' => $store ] ); $stid = is_wp_error( $r ) ? 0 : (int) $r['term_id']; }
    else { $stid = (int) $st->term_id; }
    if ( $stid ) wp_set_post_terms( $post_id, [ $stid ], 'tsa_design_store', false );

    // Categories (multi)
    if ( $cats ) {
        $cat_ids = [];
        foreach ( $cats as $slug ) { $t = get_term_by( 'slug', $slug, 'tsa_design_category' ); if ( $t ) $cat_ids[] = (int) $t->term_id; }
        if ( $cat_ids ) wp_set_post_terms( $post_id, $cat_ids, 'tsa_design_category', false );
    }
    // Print method
    if ( $method ) { $mt = get_term_by( 'slug', $method, 'tsa_design_method' ); if ( $mt ) wp_set_post_terms( $post_id, [ (int) $mt->term_id ], 'tsa_design_method', false ); }

    // Featured / New (batch)
    update_post_meta( $post_id, '_design_featured', ( ( $_POST['featured'] ?? '0' ) === '1' ) ? '1' : '0' );
    $ov = sanitize_key( $_POST['new_override'] ?? '' );
    update_post_meta( $post_id, '_design_new_override', in_array( $ov, [ 'always', 'never' ], true ) ? $ov : '' );
    if ( function_exists( 'tsa_design_recompute_new_until' ) ) tsa_design_recompute_new_until( $post_id );

    // Configurator availability + placement zones (batch)
    update_post_meta( $post_id, '_ac_configurator_active', ( ( $_POST['active'] ?? '1' ) === '1' ) ? '1' : '0' );
    $allowed = [ 'front_full', 'front_pocket', 'back_full' ];
    $zones   = isset( $_POST['zones'] )
        ? array_values( array_intersect( $allowed, array_map( 'sanitize_key', (array) wp_unslash( $_POST['zones'] ) ) ) )
        : [];
    update_post_meta( $post_id, '_ac_allowed_zones', $zones );

    wp_send_json_success( [ 'title' => $title, 'id' => $post_id ] );
}
