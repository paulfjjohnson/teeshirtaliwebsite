<?php
/**
 * TSA Live Search — whole-site instant dropdown.
 *
 * Replaces Flatsome's product-only live search with an AJAX endpoint that
 * matches the results-page scope: products, pages, posts, and designs.
 */
defined( 'ABSPATH' ) || exit;

/* ── AJAX: instant results ───────────────────────────────────────── */
add_action( 'wp_ajax_tsa_live_search',        'tsa_live_search' );
add_action( 'wp_ajax_nopriv_tsa_live_search', 'tsa_live_search' );
function tsa_live_search() {
	$q = isset( $_GET['q'] ) ? sanitize_text_field( wp_unslash( $_GET['q'] ) ) : '';
	if ( mb_strlen( $q ) < 2 ) { wp_send_json( [] ); }

	$exclude = ( function_exists( 'tsa_dash_page_id' ) && tsa_dash_page_id() ) ? [ tsa_dash_page_id() ] : [];

	$posts = get_posts( [
		's'            => $q,
		'post_type'    => [ 'product', 'configurator_garment', 'page', 'post', 'tsa_design' ],
		'post_status'  => 'publish',
		'numberposts'  => 8,
		'post__not_in' => $exclude,
		'orderby'      => 'relevance',
	] );

	$labels = [ 'product' => 'Product', 'configurator_garment' => 'Apparel', 'page' => 'Page', 'post' => 'Article', 'tsa_design' => 'Design' ];
	$out    = [];
	foreach ( $posts as $p ) {
		$type  = $p->post_type;
		$thumb = '';
		if ( 'tsa_design' === $type ) {
			$thumb = get_post_meta( $p->ID, '_design_preview_url', true ) ?: ( get_the_post_thumbnail_url( $p->ID, 'thumbnail' ) ?: '' );
		} else {
			$thumb = get_the_post_thumbnail_url( $p->ID, 'thumbnail' ) ?: '';
		}
		$out[] = [
			'title' => html_entity_decode( get_the_title( $p->ID ), ENT_QUOTES, 'UTF-8' ),
			'type'  => isset( $labels[ $type ] ) ? $labels[ $type ] : ucfirst( $type ),
			'url'   => get_permalink( $p->ID ), // designs resolve to the configurator via post_type_link filter
			'thumb' => $thumb,
		];
	}
	wp_send_json( $out );
}

/* ── Swap Flatsome's live search for ours ────────────────────────── */
add_action( 'wp_enqueue_scripts', function () {
	wp_dequeue_script( 'flatsome-live-search' ); // disable product-only dropdown

	$js = get_stylesheet_directory() . '/assets/js/tsa-live-search.js';
	wp_enqueue_script(
		'tsa-live-search',
		get_stylesheet_directory_uri() . '/assets/js/tsa-live-search.js',
		[],
		file_exists( $js ) ? filemtime( $js ) : '1.0',
		true
	);
	wp_localize_script( 'tsa-live-search', 'tsaLiveSearch', [
		'ajaxUrl' => admin_url( 'admin-ajax.php' ),
	] );
}, 30 );

/* ── Dropdown styles ─────────────────────────────────────────────── */
add_action( 'wp_head', function () {
	?>
	<style id="tsa-live-search-css">
	form.searchform { position: relative; }
	.live-search-results { display: none; }
	.live-search-results.is-open {
		display: block;
		position: absolute; left: 0; right: 0; top: 100%;
		z-index: 1000; margin-top: 6px;
		background: #fff; border: 1px solid rgba(37,33,36,.12);
		border-radius: 10px; box-shadow: 0 14px 34px rgba(37,33,36,.16);
		padding: 6px; max-height: 64vh; overflow: auto;
	}
	.tsa-ls-item {
		display: flex; align-items: center; gap: 10px;
		padding: 8px 10px; border-radius: 8px;
		text-decoration: none; color: var(--tsa-dark,#252124);
	}
	.tsa-ls-item:hover { background: var(--tsa-pink-soft,#fff1f0); }
	.tsa-ls-thumb {
		width: 40px; height: 40px; flex: 0 0 40px;
		border-radius: 6px; overflow: hidden; background: #f4f4f4;
		display: flex; align-items: center; justify-content: center; font-size: 16px;
	}
	.tsa-ls-thumb img { width: 100%; height: 100%; object-fit: cover; }
	.tsa-ls-text { display: flex; flex-direction: column; min-width: 0; }
	.tsa-ls-title { font-size: 13.5px; font-weight: 600; line-height: 1.25; white-space: nowrap; overflow: hidden; text-overflow: ellipsis; }
	.tsa-ls-type { font-size: 10.5px; font-weight: 700; text-transform: uppercase; letter-spacing: .4px; color: var(--tsa-gold-dark,#a8772f); }
	.tsa-ls-empty { padding: 14px; text-align: center; color: var(--tsa-muted,#6d6268); font-size: 13px; }
	</style>
	<?php
}, 99 );
