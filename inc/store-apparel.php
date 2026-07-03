<?php
/**
 * Per-store Apparel & Colors — the Tee Party apparel picker, on the store editor.
 *
 * A store is a "permanent party": it defines which garments it offers and, per
 * garment, which colors. The Apparel Configurator restricts to these whenever it
 * is opened for the store (?store=<slug>), reusing the same front-end props the
 * Tee Party uses — so no React rebuild. (Scheduled design drops = Step B.)
 *
 * Meta on configurator_store:
 *   _ac_store_apparel_mode    'all' | 'specific'
 *   _ac_store_garment_ids     JSON int[]
 *   _ac_store_garment_colors  JSON { garment_id: [color names] }  (none = all)
 */

defined( 'ABSPATH' ) || exit;

/** Colors for a garment: [ ['name','hex'], ... ]. Reuses the party helper if present. */
function tsa_store_garment_colors( int $gid ): array {
	if ( function_exists( 'tsa_party_garment_color_list' ) ) return tsa_party_garment_color_list( $gid );
	$styles = get_post_meta( $gid, '_ac_styles', true ) ?: [];
	$out = [];
	$seen = [];
	foreach ( (array) $styles as $st ) {
		foreach ( (array) ( $st['colors'] ?? [] ) as $c ) {
			$n = $c['name'] ?? '';
			if ( $n !== '' && empty( $seen[ $n ] ) ) {
				$seen[ $n ] = 1;
				$out[]      = [ 'name' => $n, 'hex' => $c['hex'] ?? '' ];
			}
		}
	}
	return $out;
}

add_action( 'add_meta_boxes_configurator_store', function () {
	add_meta_box( 'ac_store_apparel', 'Store Apparel & Colors', 'tsa_store_apparel_meta_box', 'configurator_store', 'normal', 'high' );
	add_meta_box( 'ac_store_drops', 'Store Design Drops', 'tsa_store_drops_meta_box', 'configurator_store', 'normal', 'high' );
} );

function tsa_store_apparel_meta_box( $post ): void {
	$mode    = get_post_meta( $post->ID, '_ac_store_apparel_mode', true ) ?: 'all';
	$gids    = json_decode( get_post_meta( $post->ID, '_ac_store_garment_ids', true ) ?: '[]', true ) ?: [];
	$gids    = array_map( 'intval', (array) $gids );
	$gcolors = json_decode( get_post_meta( $post->ID, '_ac_store_garment_colors', true ) ?: '{}', true ) ?: [];

	$all = get_posts( [
		'post_type' => 'configurator_garment', 'post_status' => 'publish',
		'posts_per_page' => 200, 'orderby' => 'title', 'order' => 'ASC',
		'meta_query' => [ [ 'key' => '_ac_is_active', 'value' => '1' ] ],
	] );

	wp_nonce_field( 'tsa_store_apparel', 'tsa_store_apparel_nonce' );
	?>
	<style>
	.tsa-sa .desc{ color:#888;font-size:12px }
	.tsa-sa-mode label{ margin-right:16px;font-weight:normal }
	.tsa-gitem{ border:1px solid #e0e0e0;border-radius:8px;padding:10px 12px;margin-bottom:8px }
	.tsa-gitem > label{ font-weight:600;display:block }
	.tsa-gcolors{ margin:9px 0 0 22px;flex-wrap:wrap;gap:6px 14px }
	.tsa-gcolors label{ font-weight:normal;font-size:12px;display:inline-flex;align-items:center;gap:5px;margin-right:6px }
	.tsa-sw{ width:13px;height:13px;border-radius:50%;border:1px solid #ccc;display:inline-block }
	</style>
	<div class="tsa-sa">
		<p class="desc" style="margin:0 0 12px">Sets which apparel and colors customers see when they configure a design <strong>in this store</strong>. This is the store's standing "party" apparel.</p>

		<p class="tsa-sa-mode">
			<label><input type="radio" name="ac_store_apparel_mode" value="all" <?php checked( $mode, 'all' ); ?> /> Full TSA Catalog</label>
			<label><input type="radio" name="ac_store_apparel_mode" value="specific" <?php checked( $mode, 'specific' ); ?> /> Specific Garments Only</label>
		</p>

		<div id="tsa-sa-picker" style="<?php echo $mode !== 'specific' ? 'display:none;' : ''; ?>margin-top:10px">
			<p class="desc" style="margin:0 0 10px">Check the garments to offer. For each, limit which colors customers may pick — <strong>leave every color unchecked to allow them all</strong>.</p>
			<?php if ( empty( $all ) ) : ?>
				<p class="desc">No active garments found. Add garments under Configurator → Garments first.</p>
			<?php else : foreach ( $all as $g ) :
				$checked = in_array( (int) $g->ID, $gids, true );
				$colors  = tsa_store_garment_colors( (int) $g->ID );
				$allowed = (array) ( $gcolors[ $g->ID ] ?? $gcolors[ (string) $g->ID ] ?? [] );
			?>
			<div class="tsa-gitem">
				<label><input type="checkbox" class="tsa-sa-gtoggle" name="ac_store_garment_ids[]" value="<?php echo esc_attr( $g->ID ); ?>" data-gid="<?php echo esc_attr( $g->ID ); ?>" <?php checked( $checked ); ?> /> <?php echo esc_html( $g->post_title ); ?></label>
				<?php if ( $colors ) : ?>
				<div class="tsa-gcolors" id="tsa-sa-colors-<?php echo (int) $g->ID; ?>" style="display:<?php echo $checked ? 'flex' : 'none'; ?>">
					<span style="width:100%;color:#888;font-size:11px;margin-bottom:2px;display:block">Allowed colors (none checked = all <?php echo count( $colors ); ?>):</span>
					<?php foreach ( $colors as $col ) : ?>
					<label>
						<input type="checkbox" name="ac_store_garment_colors[<?php echo (int) $g->ID; ?>][]" value="<?php echo esc_attr( $col['name'] ); ?>" <?php checked( in_array( $col['name'], $allowed, true ) ); ?> />
						<span class="tsa-sw" style="background:<?php echo esc_attr( $col['hex'] ?: '#ccc' ); ?>"></span>
						<?php echo esc_html( $col['name'] ); ?>
					</label>
					<?php endforeach; ?>
				</div>
				<?php endif; ?>
			</div>
			<?php endforeach; endif; ?>
		</div>
	</div>
	<script>
	(function(){
		var box = document.getElementById('ac_store_apparel');
		if ( ! box ) return;
		box.querySelectorAll('input[name=ac_store_apparel_mode]').forEach(function(r){
			r.addEventListener('change', function(){
				document.getElementById('tsa-sa-picker').style.display = ( this.value === 'specific' ) ? '' : 'none';
			});
		});
		box.querySelectorAll('.tsa-sa-gtoggle').forEach(function(cb){
			cb.addEventListener('change', function(){
				var el = document.getElementById('tsa-sa-colors-' + this.dataset.gid);
				if ( el ) el.style.display = this.checked ? 'flex' : 'none';
			});
		});
	})();
	</script>
	<?php
}

add_action( 'save_post_configurator_store', function ( $post_id ) {
	if ( ! isset( $_POST['tsa_store_apparel_nonce'] ) || ! wp_verify_nonce( $_POST['tsa_store_apparel_nonce'], 'tsa_store_apparel' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$mode = ( ( $_POST['ac_store_apparel_mode'] ?? 'all' ) === 'specific' ) ? 'specific' : 'all';
	update_post_meta( $post_id, '_ac_store_apparel_mode', $mode );

	$gids = array_values( array_unique( array_filter( array_map( 'absint', (array) ( $_POST['ac_store_garment_ids'] ?? [] ) ) ) ) );
	update_post_meta( $post_id, '_ac_store_garment_ids', wp_json_encode( $gids ) );

	$raw     = (array) ( $_POST['ac_store_garment_colors'] ?? [] );
	$gcolors = [];
	foreach ( $raw as $gid => $cols ) {
		$gid = absint( $gid );
		if ( ! $gid || ! in_array( $gid, $gids, true ) ) continue; // only keep colors for selected garments
		$clean = array_values( array_filter( array_map( 'sanitize_text_field', (array) $cols ) ) );
		if ( $clean ) $gcolors[ $gid ] = $clean;
	}
	update_post_meta( $post_id, '_ac_store_garment_colors', wp_json_encode( $gcolors ) );
} );

/* ─── Store Design Drops — schedule designs to go live in the store ───────────
   Meta: _ac_store_schedule  JSON [{ design_id, reveal_ts }]. At/after reveal_ts a
   design is tagged to the store, published + activated (store-scoped, drops global
   'tsa'), so it appears in the store's design grid + configurator. Mirrors the Tee
   Party drop scheduler, but permanent (no close/release). */

/** Shared data for the admin thumbnail design picker (same as the Tee Party):
 *  [ designs[{id,t,img,cats}], category <option> html, { catId: [descendant ids] } ]. */
function tsa_design_picker_data(): array {
	$cpt = post_type_exists( 'tsa_design' ) ? 'tsa_design' : 'configurator_design';
	$tax = $cpt === 'tsa_design' ? 'tsa_design_category' : 'design_category';
	$posts = get_posts( [ 'post_type' => $cpt, 'post_status' => [ 'publish', 'draft', 'pending' ], 'posts_per_page' => 800, 'orderby' => 'title', 'order' => 'ASC' ] );
	$data = array_map( function ( $dp ) use ( $tax ) {
		$cids = wp_get_post_terms( $dp->ID, $tax, [ 'fields' => 'ids' ] );
		return [ 'id' => $dp->ID, 't' => $dp->post_title, 'img' => get_post_meta( $dp->ID, '_design_preview_url', true ) ?: ( get_the_post_thumbnail_url( $dp->ID, 'thumbnail' ) ?: '' ), 'cats' => is_wp_error( $cids ) ? [] : array_map( 'intval', $cids ) ];
	}, $posts );
	$cats = get_terms( [ 'taxonomy' => $tax, 'hide_empty' => false ] );
	if ( is_wp_error( $cats ) ) $cats = [];
	$by_parent = [];
	foreach ( $cats as $ct ) { $by_parent[ (int) $ct->parent ][] = $ct; }
	$walk = function ( $parent, $depth ) use ( &$walk, &$by_parent ) {
		$out = '';
		foreach ( $by_parent[ $parent ] ?? [] as $ct ) {
			$out .= '<option value="' . (int) $ct->term_id . '">' . str_repeat( '— ', $depth ) . esc_html( $ct->name ) . '</option>';
			$out .= $walk( $ct->term_id, $depth + 1 );
		}
		return $out;
	};
	$options = $walk( 0, 0 );
	$desc = [];
	foreach ( $cats as $ct ) {
		$ids = [ (int) $ct->term_id ]; $stack = [ (int) $ct->term_id ];
		while ( $stack ) { $p = array_pop( $stack ); foreach ( $by_parent[ $p ] ?? [] as $ch ) { $ids[] = (int) $ch->term_id; $stack[] = (int) $ch->term_id; } }
		$desc[ (int) $ct->term_id ] = array_values( array_unique( $ids ) );
	}
	return [ $data, $options, $desc ];
}

function tsa_store_drops_meta_box( $post ): void {
	$sched = json_decode( get_post_meta( $post->ID, '_ac_store_schedule', true ) ?: '[]', true ) ?: [];
	list( $design_data, $cat_options, $cat_desc ) = tsa_design_picker_data();

	wp_nonce_field( 'tsa_store_drops', 'tsa_store_drops_nonce' );
	?>
	<style>
	.tsa-sd table{ width:100%;border-collapse:collapse;margin-top:6px }
	.tsa-sd th{ font-size:11px;color:#666;text-align:left;padding:3px 6px;border-bottom:1px solid #eee }
	.tsa-sd td{ padding:5px 6px;border-bottom:1px solid #f5f5f5;vertical-align:middle }
	.tsa-sd input[type=datetime-local]{ font-size:13px;padding:4px }
	.tsa-sd-pick{ width:100%;max-width:360px;text-align:left;height:auto;min-height:32px;display:flex;align-items:center;gap:8px;padding:4px 8px;font-size:12px }
	.tsa-sd-rm{ color:#c0392b;background:none;border:none;cursor:pointer;font-size:16px }
	</style>
	<div class="tsa-sd">
		<p class="desc" style="color:#888;font-size:12px;margin:0 0 8px">Schedule designs to go live in this store. <strong>Leave the date blank to drop it live immediately.</strong> Pick from the thumbnail library — same picker as a Tee Party.</p>
		<table>
			<thead><tr><th>Design</th><th>Reveal date/time (blank = now)</th><th></th></tr></thead>
			<tbody id="tsa-sd-rows">
				<?php
				$rows = $sched ?: [ [ 'design_id' => 0, 'reveal_ts' => 0 ] ];
				foreach ( $rows as $row ) :
					$did = (int) ( $row['design_id'] ?? 0 );
					$ts  = (int) ( $row['reveal_ts'] ?? 0 );
					$dtv = $ts ? wp_date( 'Y-m-d\TH:i', $ts ) : '';
				?>
				<tr>
					<td>
						<input type="hidden" name="ac_store_drop_design[]" class="tsa-sd-val" value="<?php echo esc_attr( $did ); ?>" />
						<button type="button" class="button tsa-sd-pick">Choose design…</button>
					</td>
					<td><input type="datetime-local" name="ac_store_drop_reveal[]" value="<?php echo esc_attr( $dtv ); ?>" /></td>
					<td><button type="button" class="tsa-sd-rm" title="Remove">&times;</button></td>
				</tr>
				<?php endforeach; ?>
			</tbody>
		</table>
		<p><button type="button" class="button" id="tsa-sd-add">+ Add drop</button></p>
		<template id="tsa-sd-tpl">
			<tr>
				<td>
					<input type="hidden" name="ac_store_drop_design[]" class="tsa-sd-val" value="" />
					<button type="button" class="button tsa-sd-pick">Choose design…</button>
				</td>
				<td><input type="datetime-local" name="ac_store_drop_reveal[]" value="" /></td>
				<td><button type="button" class="tsa-sd-rm" title="Remove">&times;</button></td>
			</tr>
		</template>

		<!-- Design thumbnail picker modal (same structure as the Tee Party) -->
		<div id="tsa-sd-modal" style="display:none;position:fixed;inset:0;z-index:100000;background:rgba(0,0,0,.5)">
			<div style="position:absolute;top:5%;left:50%;transform:translateX(-50%);width:min(780px,92vw);max-height:86vh;background:#fff;border-radius:10px;display:flex;flex-direction:column;overflow:hidden;box-shadow:0 20px 60px rgba(0,0,0,.35)">
				<div style="padding:13px 16px;border-bottom:1px solid #eee;display:flex;gap:10px;align-items:center">
					<strong style="font-size:14px;white-space:nowrap">Choose a design</strong>
					<select id="tsa-sd-cat" style="max-width:200px"><option value="">All categories</option><?php echo $cat_options; ?></select>
					<input type="text" id="tsa-sd-search" placeholder="Search designs…" class="regular-text" style="flex:1" autocomplete="off" />
					<button type="button" class="button" id="tsa-sd-close">Close</button>
				</div>
				<div id="tsa-sd-grid" style="padding:14px 16px;overflow-y:auto;display:grid;grid-template-columns:repeat(auto-fill,minmax(120px,1fr));gap:12px"></div>
			</div>
		</div>
	</div>
	<script>
	(function(){
		var box = document.getElementById('ac_store_drops');
		if ( ! box ) return;
		var rows = box.querySelector('#tsa-sd-rows'), tpl = box.querySelector('#tsa-sd-tpl');
		var DESIGNS = <?php echo wp_json_encode( $design_data ); ?>;
		var DMAP = {}; DESIGNS.forEach(function(d){ DMAP[String(d.id)] = d; });
		var CAT_DESC = <?php echo wp_json_encode( (object) $cat_desc ); ?>;
		var modal = box.querySelector('#tsa-sd-modal'), grid = box.querySelector('#tsa-sd-grid'),
		    search = box.querySelector('#tsa-sd-search'), cat = box.querySelector('#tsa-sd-cat'), active = null;
		function esc(s){ return String(s||'').replace(/[&<>"]/g,function(c){return {'&':'&amp;','<':'&lt;','>':'&gt;','"':'&quot;'}[c];}); }
		function btnHtml(d){
			if(!d) return 'Choose design…';
			var img = d.img ? '<img src="'+esc(d.img)+'" style="width:26px;height:26px;object-fit:cover;border-radius:4px;flex:none" />' : '<span style="width:26px;height:26px;border-radius:4px;background:#f0f0f0;display:inline-flex;align-items:center;justify-content:center;flex:none">🎨</span>';
			return img + '<span style="overflow:hidden;text-overflow:ellipsis;white-space:nowrap">'+esc(d.t)+'</span>';
		}
		function paint(btn){ var v = btn.parentNode.querySelector('.tsa-sd-val').value; btn.innerHTML = btnHtml(DMAP[String(v)]); }
		function render(q){
			q=(q||'').trim().toLowerCase(); grid.innerHTML='';
			var c = cat ? cat.value : '';
			var allowed = c ? (CAT_DESC[c] || [parseInt(c,10)]) : null;
			DESIGNS.filter(function(d){
				if(q && d.t.toLowerCase().indexOf(q)<0) return false;
				if(allowed && !(d.cats||[]).some(function(x){return allowed.indexOf(x)>=0;})) return false;
				return true;
			}).forEach(function(d){
				var t=document.createElement('button'); t.type='button';
				t.style.cssText='border:1px solid #ddd;border-radius:8px;background:#fff;padding:8px;cursor:pointer;display:flex;flex-direction:column;gap:6px;align-items:center;text-align:center';
				var vis = d.img ? '<img src="'+esc(d.img)+'" style="width:100%;aspect-ratio:1;object-fit:contain;background:#fafafa;border-radius:5px" />' : '<div style="width:100%;aspect-ratio:1;background:#fafafa;border-radius:5px;display:flex;align-items:center;justify-content:center;font-size:30px">🎨</div>';
				t.innerHTML = vis + '<span style="font-size:11px;line-height:1.25;color:#333">'+esc(d.t)+'</span>';
				t.addEventListener('click',function(){ if(active){ active.parentNode.querySelector('.tsa-sd-val').value = d.id; paint(active); } close(); });
				grid.appendChild(t);
			});
			if(!grid.children.length) grid.innerHTML='<p style="grid-column:1/-1;color:#888;font-size:13px">No designs match.</p>';
		}
		function open(btn){ active=btn; modal.style.display='block'; search.value=''; render(''); search.focus(); }
		function close(){ modal.style.display='none'; active=null; }
		box.addEventListener('click',function(e){
			var pick = e.target.closest('.tsa-sd-pick'); if(pick){ e.preventDefault(); open(pick); return; }
			if(e.target.classList.contains('tsa-sd-rm')){
				var tr = e.target.closest('tr');
				if(rows.children.length>1) tr.remove();
				else { tr.querySelector('.tsa-sd-val').value=''; paint(tr.querySelector('.tsa-sd-pick')); tr.querySelector('input[type=datetime-local]').value=''; }
			}
		});
		box.querySelector('#tsa-sd-add').addEventListener('click',function(){ rows.appendChild( tpl.content.cloneNode(true) ); rows.querySelectorAll('.tsa-sd-pick').forEach(paint); });
		search.addEventListener('input',function(){ render(this.value); });
		search.addEventListener('keydown',function(e){ if(e.key==='Enter') e.preventDefault(); });
		if(cat) cat.addEventListener('change',function(){ render(search.value); });
		box.querySelector('#tsa-sd-close').addEventListener('click',close);
		modal.addEventListener('click',function(e){ if(e.target===modal) close(); });
		rows.querySelectorAll('.tsa-sd-pick').forEach(paint);
	})();
	</script>
	<?php
}

add_action( 'save_post_configurator_store', function ( $post_id ) {
	if ( ! isset( $_POST['tsa_store_drops_nonce'] ) || ! wp_verify_nonce( $_POST['tsa_store_drops_nonce'], 'tsa_store_drops' ) ) return;
	if ( defined( 'DOING_AUTOSAVE' ) && DOING_AUTOSAVE ) return;
	if ( ! current_user_can( 'edit_post', $post_id ) ) return;

	$dids  = (array) ( $_POST['ac_store_drop_design'] ?? [] );
	$revs  = (array) ( $_POST['ac_store_drop_reveal'] ?? [] );
	$sched = [];
	foreach ( $dids as $i => $did ) {
		$did = absint( $did );
		if ( ! $did ) continue;
		$rev = sanitize_text_field( $revs[ $i ] ?? '' );
		$ts  = $rev ? ( function_exists( 'tsa_party_ts' ) ? (int) tsa_party_ts( $rev ) : (int) strtotime( $rev ) ) : time();
		if ( ! $ts ) $ts = time();
		$sched[] = [ 'design_id' => $did, 'reveal_ts' => $ts ];
	}
	update_post_meta( $post_id, '_ac_store_schedule', wp_json_encode( $sched, JSON_UNESCAPED_SLASHES ) );

	// (Re)schedule cron for future reveals + activate anything already due.
	wp_clear_scheduled_hook( 'tsa_store_drop_event', [ $post_id ] );
	foreach ( $sched as $row ) {
		if ( $row['reveal_ts'] > time() ) {
			wp_schedule_single_event( $row['reveal_ts'], 'tsa_store_drop_event', [ $post_id ] );
		}
	}
	tsa_store_activate_drops( $post_id );
} );

/**
 * Activate all past-due drops for a store: tag the design to the store (drop the
 * global 'tsa' scope), publish + activate. Idempotent — safe to run repeatedly.
 */
function tsa_store_activate_drops( int $store_id ): void {
	$slug = sanitize_title( get_post_meta( $store_id, '_ac_store_slug', true ) );
	if ( ! $slug || ! taxonomy_exists( 'tsa_design_store' ) ) return;
	$sched = json_decode( get_post_meta( $store_id, '_ac_store_schedule', true ) ?: '[]', true ) ?: [];
	if ( ! $sched ) return;

	if ( ! term_exists( $slug, 'tsa_design_store' ) ) {
		$name = get_the_title( $store_id ) ?: ucwords( str_replace( '-', ' ', $slug ) );
		wp_insert_term( $name, 'tsa_design_store', [ 'slug' => $slug ] );
	}

	$now = time();
	foreach ( $sched as $row ) {
		$did = absint( $row['design_id'] ?? 0 );
		$rev = (int) ( $row['reveal_ts'] ?? 0 );
		if ( ! $did || $rev > $now ) continue;

		$terms = wp_get_post_terms( $did, 'tsa_design_store', [ 'fields' => 'slugs' ] );
		if ( is_wp_error( $terms ) ) $terms = [];
		$want  = array_values( array_diff( array_unique( array_merge( $terms, [ $slug ] ) ), [ 'tsa' ] ) );
		sort( $terms ); $want_sorted = $want; sort( $want_sorted );
		if ( $want_sorted !== $terms ) wp_set_post_terms( $did, $want, 'tsa_design_store', false );

		if ( get_post_meta( $did, '_ac_configurator_active', true ) !== '1' ) update_post_meta( $did, '_ac_configurator_active', '1' );
		if ( get_post_status( $did ) !== 'publish' ) wp_update_post( [ 'ID' => $did, 'post_status' => 'publish' ] );
	}
}
add_action( 'tsa_store_drop_event', function ( $sid ) { tsa_store_activate_drops( (int) $sid ); } );
