<?php
/**
 * Featured Designs — homepage sales section.
 *
 * Shortcode: [tsa_featured_designs per_category="8"]
 *
 * Renders published tsa_design posts flagged _design_featured = 1, GROUPED BY
 * their category (the container heading IS the design category — Option A,
 * category rows). Each card mirrors the Design Library cards with two buttons:
 *   • "Use in Configurator" → /configurator/?design_id=ID (preloads the design)
 *   • "Customize It"         → request modal (tsa_design_customize AJAX, global)
 *
 * Self-contained styles + modal + JS (printed once) so it can be dropped into
 * the homepage via the page builder or a template, with no extra CSS upload.
 */

defined( 'ABSPATH' ) || exit;

add_shortcode( 'tsa_featured_designs', 'tsa_featured_designs_shortcode' );
function tsa_featured_designs_shortcode( $atts ) {
	$atts = shortcode_atts( [ 'per_category' => 8 ], $atts, 'tsa_featured_designs' );

	$cpt = post_type_exists( 'tsa_design' ) ? 'tsa_design' : 'configurator_design';
	$tax = $cpt === 'tsa_design' ? 'tsa_design_category' : 'design_category';

	$designs = get_posts( [
		'post_type'   => $cpt,
		'post_status' => 'publish',
		'numberposts' => -1,
		'orderby'     => 'date',
		'order'       => 'DESC',
		'meta_query'  => [ [ 'key' => '_design_featured', 'value' => '1' ] ],
	] );
	if ( ! $designs ) {
		return '';
	}

	$per    = max( 1, (int) $atts['per_category'] );
	$groups = []; // category name => [ 'items' => [ [id,title,preview], ... ] ]
	foreach ( $designs as $d ) {
		$terms = get_the_terms( $d->ID, $tax );
		$cat   = ( $terms && ! is_wp_error( $terms ) ) ? $terms[0] : null;
		$name  = $cat ? $cat->name : __( 'Featured Designs', 'tsa-child' );
		if ( ! isset( $groups[ $name ] ) ) {
			$groups[ $name ] = [ 'slug' => $cat ? $cat->slug : '', 'items' => [] ];
		}
		if ( count( $groups[ $name ]['items'] ) >= $per ) {
			continue;
		}
		$preview = get_post_meta( $d->ID, '_design_preview_url', true ) ?: ( get_the_post_thumbnail_url( $d->ID, 'medium' ) ?: '' );
		$groups[ $name ]['items'][] = [
			'id'      => $d->ID,
			'title'   => get_the_title( $d->ID ),
			'preview' => $preview,
		];
	}
	if ( ! $groups ) {
		return '';
	}

	$lib = home_url( '/design-library/' );

	ob_start();
	tsa_featured_designs_assets();
	?>
	<section class="tsa-fd">
		<?php
		foreach ( $groups as $name => $grp ) :
			$all_url = ! empty( $grp['slug'] ) ? add_query_arg( 'cat', $grp['slug'], $lib ) : $lib;
			?>
		<div class="tsa-fd-cat">
			<div class="tsa-fd-cat__head">
				<h2 class="tsa-fd-cat__title"><?php echo esc_html( $name ); ?></h2>
				<a class="tsa-fd-cat__all" href="<?php echo esc_url( $all_url ); ?>">View all <?php echo esc_html( $name ); ?> &rarr;</a>
			</div>
			<div class="tsa-fd-grid">
				<?php
				foreach ( $grp['items'] as $it ) :
					$cfg = home_url( '/configurator/?design_id=' . (int) $it['id'] );
					?>
				<article class="tsa-fd-card">
					<a class="tsa-fd-card__img" href="<?php echo esc_url( $cfg ); ?>" aria-label="<?php echo esc_attr( $it['title'] ); ?>">
						<?php if ( $it['preview'] ) : ?>
						<img src="<?php echo esc_url( $it['preview'] ); ?>" alt="<?php echo esc_attr( $it['title'] ); ?>" loading="lazy">
						<?php else : ?>
						<span class="tsa-fd-card__ph">&#127912;</span>
						<?php endif; ?>
					</a>
					<div class="tsa-fd-card__body">
						<div class="tsa-fd-card__name"><?php echo esc_html( $it['title'] ); ?></div>
						<div class="tsa-fd-card__actions">
							<a class="tsa-fd-btn tsa-fd-btn--primary" href="<?php echo esc_url( $cfg ); ?>">Use in Configurator</a>
							<button type="button" class="tsa-fd-btn tsa-fd-btn--ghost tsa-fd-cz"
								data-id="<?php echo (int) $it['id']; ?>"
								data-title="<?php echo esc_attr( $it['title'] ); ?>"
								data-preview="<?php echo esc_url( $it['preview'] ); ?>">Customize It</button>
						</div>
					</div>
				</article>
				<?php endforeach; ?>
			</div>
		</div>
		<?php endforeach; ?>
	</section>
	<?php
	return ob_get_clean();
}

/** Print the shared card styles + Customize-It modal once per request.
 *  Reusable so other surfaces (e.g. the Tee Party collection band) can render
 *  the same .tsa-fd-* cards and Customize-It buttons. */
function tsa_featured_designs_assets() {
	static $printed = false;
	if ( $printed ) {
		return;
	}
	$printed = true;
	tsa_featured_designs_styles();
	tsa_featured_designs_modal();
	tsa_featured_designs_contrast();
}

/**
 * Auto-contrast for design thumbnails: if a preview is white / very light it
 * would vanish on the card's white background, so darken that card's image
 * backdrop. Same-origin images only; tainted images silently keep white.
 * Mirrors the Tee Party reveal-stage behaviour for the static .tsa-fd-card grid.
 */
function tsa_featured_designs_contrast() {
	?>
	<script>
	(function(){
		var LIGHT = '#ffffff', DARK = '#544e48';
		function pick(img){
			var box = img.closest('.tsa-fd-card__img') || img.closest('.tsa-autocontrast'); if(!box) return;
			try{
				var c=document.createElement('canvas'), w=c.width=28, h=c.height=28, x=c.getContext('2d');
				x.drawImage(img,0,0,w,h);
				var d=x.getImageData(0,0,w,h).data, opaque=0, nearWhite=0, lum=0;
				for(var p=0;p<d.length;p+=4){
					if(d[p+3]<128) continue;
					opaque++;
					var r=d[p], g=d[p+1], b=d[p+2];
					if(r>232 && g>232 && b>232) nearWhite++;
					lum+=(0.2126*r + 0.7152*g + 0.0722*b)/255;
				}
				if(!opaque) return;
				box.style.background = (nearWhite/opaque>0.05 || lum/opaque>0.86) ? DARK : LIGHT;
			}catch(e){ /* tainted — keep white */ }
		}
		function run(){
			document.querySelectorAll('.tsa-fd-card__img img, .tsa-autocontrast img').forEach(function(img){
				if(img.dataset.czContrast) return;
				img.dataset.czContrast='1';
				if(img.complete && img.naturalWidth) pick(img);
				else img.addEventListener('load', function(){ pick(img); });
			});
		}
		if(document.readyState==='loading') document.addEventListener('DOMContentLoaded', run);
		else run();
	})();
	</script>
	<?php
}

function tsa_featured_designs_styles() {
	?>
	<style>
	.tsa-fd{max-width:1200px;margin:0 auto;padding:8px 6% 40px}
	.tsa-fd-cat{margin-bottom:36px}
	.tsa-fd-cat__head{display:flex;align-items:baseline;justify-content:space-between;gap:12px;margin-bottom:16px}
	.tsa-fd-cat__title{font-size:24px;font-weight:900;letter-spacing:-.5px;margin:0;color:var(--tsa-dark,#252124)}
	.tsa-fd-cat__all{font-size:13px;font-weight:700;color:var(--tsa-gold-dark,#a8772f);text-decoration:none;white-space:nowrap}
	.tsa-fd-grid{display:grid;grid-template-columns:repeat(4,1fr);gap:20px}
	.tsa-fd-card{background:#fff;border:1px solid var(--tsa-line,rgba(37,33,36,.12));border-radius:16px;overflow:hidden;display:flex;flex-direction:column;transition:transform .2s,box-shadow .2s}
	.tsa-fd-card:hover{transform:translateY(-3px);box-shadow:0 16px 36px rgba(37,33,36,.1)}
	.tsa-fd-card__img{display:block;aspect-ratio:1;background:#fff;overflow:hidden}
	.tsa-fd-card__img img{width:100%;height:100%;object-fit:contain;padding:14px;box-sizing:border-box;display:block}
	.tsa-fd-card__ph{display:flex;align-items:center;justify-content:center;height:100%;font-size:40px}
	.tsa-fd-card__body{padding:12px 14px 14px;display:flex;flex-direction:column;flex:1}
	.tsa-fd-card__name{font-size:15px;font-weight:800;color:var(--tsa-dark,#252124);margin-bottom:10px;min-height:2.4em;line-height:1.2}
	.tsa-fd-card__actions{margin-top:auto;display:flex;flex-direction:column;gap:8px}
	.tsa-fd-btn{display:block;text-align:center;border-radius:999px;padding:10px;font-size:12px;font-weight:900;text-transform:uppercase;letter-spacing:.4px;cursor:pointer;border:0;text-decoration:none;transition:opacity .2s}
	.tsa-fd-btn--primary{background:var(--tsa-dark,#252124);color:#fff}
	.tsa-fd-btn--primary:hover{opacity:.85;color:#fff}
	.tsa-fd-btn--ghost{background:#fff;color:var(--tsa-dark,#252124);border:1.5px solid var(--tsa-line,rgba(37,33,36,.18))}
	.tsa-fd-btn--ghost:hover{background:var(--tsa-pink-soft,#fff1f0)}
	@media(max-width:1100px){.tsa-fd-grid{grid-template-columns:repeat(3,1fr)}}
	@media(max-width:760px){.tsa-fd-grid{grid-template-columns:repeat(2,1fr)}}
	@media(max-width:460px){.tsa-fd-grid{grid-template-columns:1fr}}
	.tsa-fd-modal[hidden]{display:none}
	.tsa-fd-modal{position:fixed;inset:0;z-index:100000;display:flex;align-items:center;justify-content:center}
	.tsa-fd-modal__bd{position:absolute;inset:0;background:rgba(37,33,36,.5)}
	.tsa-fd-modal__box{position:relative;background:#fff;border-radius:16px;width:min(440px,92vw);max-height:90vh;overflow:auto;padding:22px}
	.tsa-fd-modal__x{position:absolute;top:10px;right:14px;background:none;border:0;font-size:24px;cursor:pointer;color:#888}
	.tsa-fd-modal__head{display:flex;gap:12px;align-items:center;margin-bottom:14px}
	.tsa-fd-modal__img{width:54px;height:54px;object-fit:contain;background:#faf7f8;border-radius:10px}
	.tsa-fd-modal__eyebrow{font-size:11px;text-transform:uppercase;letter-spacing:.5px;color:var(--tsa-gold-dark,#a8772f);font-weight:700}
	.tsa-fd-modal__title{font-size:18px;font-weight:900;margin:2px 0 0;color:var(--tsa-dark,#252124)}
	.tsa-fd-modal label{display:block;font-size:12px;font-weight:700;margin:12px 0 5px;color:var(--tsa-dark,#252124)}
	.tsa-fd-modal input,.tsa-fd-modal textarea{width:100%;border:1px solid var(--tsa-line,rgba(37,33,36,.2));border-radius:10px;padding:10px 12px;font-size:14px;box-sizing:border-box;font-family:inherit}
	.tsa-fd-modal__msg{font-size:13px;margin:10px 0 0}
	.tsa-fd-modal__submit{margin-top:14px;width:100%;background:var(--tsa-dark,#252124);color:#fff;border:0;border-radius:999px;padding:13px;font-size:13px;font-weight:900;text-transform:uppercase;letter-spacing:.4px;cursor:pointer}
	</style>
	<?php
}

function tsa_featured_designs_modal() {
	$email = is_user_logged_in() ? wp_get_current_user()->user_email : '';
	$nonce = wp_create_nonce( 'tsa_design_customize' );
	$ajax  = admin_url( 'admin-ajax.php' );
	?>
	<div class="tsa-fd-modal" id="tsa-fd-modal" hidden>
		<div class="tsa-fd-modal__bd" data-close></div>
		<div class="tsa-fd-modal__box" role="dialog" aria-modal="true" aria-labelledby="tsa-fd-modal-title">
			<button type="button" class="tsa-fd-modal__x" data-close aria-label="Close">&times;</button>
			<div class="tsa-fd-modal__head">
				<img id="tsa-fd-modal-img" src="" alt="" class="tsa-fd-modal__img">
				<div>
					<div class="tsa-fd-modal__eyebrow">Customize this design</div>
					<h3 id="tsa-fd-modal-title" class="tsa-fd-modal__title"></h3>
				</div>
			</div>
			<form id="tsa-fd-modal-form">
				<input type="hidden" name="design_id" id="tsa-fd-modal-id" value="">
				<label for="tsa-fd-modal-email">Email</label>
				<input type="email" id="tsa-fd-modal-email" name="email" required value="<?php echo esc_attr( $email ); ?>">
				<label for="tsa-fd-modal-desc">What customization do you need?</label>
				<textarea id="tsa-fd-modal-desc" name="description" rows="4" required placeholder="e.g. change the colors, add a name, adjust the wording&hellip;"></textarea>
				<div class="tsa-fd-modal__msg" id="tsa-fd-modal-msg"></div>
				<button type="submit" class="tsa-fd-modal__submit" id="tsa-fd-modal-submit">Send Request</button>
			</form>
		</div>
	</div>
	<script>
	(function(){
		var AJAX=<?php echo wp_json_encode( $ajax ); ?>, NONCE=<?php echo wp_json_encode( $nonce ); ?>;
		var modal=document.getElementById('tsa-fd-modal'); if(!modal) return;
		var form=document.getElementById('tsa-fd-modal-form'),
			mImg=document.getElementById('tsa-fd-modal-img'),
			mTitle=document.getElementById('tsa-fd-modal-title'),
			mId=document.getElementById('tsa-fd-modal-id'),
			mMsg=document.getElementById('tsa-fd-modal-msg'),
			mBtn=document.getElementById('tsa-fd-modal-submit');
		function openModal(id,title,preview){
			mId.value=id; mTitle.textContent=title||'';
			if(preview){ mImg.src=preview; mImg.style.display=''; } else { mImg.style.display='none'; }
			mMsg.textContent=''; mBtn.disabled=false; mBtn.style.display=''; mBtn.textContent='Send Request';
			modal.hidden=false; document.body.style.overflow='hidden';
		}
		function closeModal(){ modal.hidden=true; document.body.style.overflow=''; }
		document.addEventListener('click',function(e){
			var cz=e.target.closest('.tsa-fd-cz');
			if(cz){ e.preventDefault(); openModal(cz.getAttribute('data-id'),cz.getAttribute('data-title'),cz.getAttribute('data-preview')); return; }
			if(e.target.closest('[data-close]')){ closeModal(); }
		});
		document.addEventListener('keydown',function(e){ if(e.key==='Escape'&&!modal.hidden) closeModal(); });
		form.addEventListener('submit',function(e){
			e.preventDefault();
			mBtn.disabled=true; mBtn.textContent='Sending…'; mMsg.textContent='';
			var fd=new FormData(form); fd.append('action','tsa_design_customize'); fd.append('nonce',NONCE);
			fetch(AJAX,{method:'POST',body:fd,credentials:'same-origin'})
				.then(function(r){return r.json();})
				.then(function(j){
					if(j&&j.success){ mMsg.style.color='#1a7f37'; mMsg.textContent=(j.data&&j.data.message)||'Request sent — we’ll be in touch.'; form.reset(); mBtn.style.display='none'; }
					else { mMsg.style.color='#b3261e'; mMsg.textContent=(j&&j.data&&j.data.message)||'Could not send. Please try again.'; mBtn.disabled=false; mBtn.textContent='Send Request'; }
				})
				.catch(function(){ mMsg.style.color='#b3261e'; mMsg.textContent='Connection error. Please try again.'; mBtn.disabled=false; mBtn.textContent='Send Request'; });
		});
	}());
	</script>
	<?php
}
