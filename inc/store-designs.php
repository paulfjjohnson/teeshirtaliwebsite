<?php
/**
 * Store design grid — the bridge between the design system and storefronts.
 *
 * Renders a store's `tsa_design` designs (optionally filtered to one program
 * category) as cards that link into the store-branded configurator. This is the
 * single source of truth for "show a store's designs", reused by the store home
 * and per-program collection templates so every store type (school / team /
 * business / event) surfaces designs the same way.
 *
 * Mirrors the Design Library REST scoping (inc/design-library.php) and reuses the
 * `.tsa-dl-card*` markup/CSS so styling and the click-through fix come for free.
 */

defined( 'ABSPATH' ) || exit;

/**
 * Query a store's designs.
 *
 * @param string $store_slug Store taxonomy slug (e.g. 'dutchtown').
 * @param string $cat        Optional program/category slug (e.g. 'band').
 * @return WP_Query
 */
function tsa_store_designs_query( string $store_slug, string $cat = '' ): WP_Query {
	$store_slug = sanitize_title( $store_slug );

	$tax_query = [ 'relation' => 'AND' ];

	// Scope to the store. Default: that store ONLY (school/team/business designs
	// stay in their own shop). Filterable to also include Main-TSA shared art.
	$store_terms = [ $store_slug ];
	if ( apply_filters( 'tsa_store_grid_include_main', false, $store_slug ) ) {
		$store_terms[] = apply_filters( 'tsa_design_main_store_slug', 'tsa' );
	}
	$tax_query[] = [
		'taxonomy' => 'tsa_design_store',
		'field'    => 'slug',
		'terms'    => array_values( array_unique( $store_terms ) ),
	];

	$cat = sanitize_title( $cat );
	if ( $cat ) {
		$tax_query[] = [
			'taxonomy'         => 'tsa_design_category',
			'field'            => 'slug',
			'terms'            => [ $cat ],
			'include_children' => true,
			'operator'         => 'IN',
		];
	}

	return new WP_Query( [
		'post_type'      => 'tsa_design',
		'post_status'    => 'publish',
		'posts_per_page' => (int) apply_filters( 'tsa_store_grid_limit', 48, $store_slug, $cat ),
		'orderby'        => 'date',
		'order'          => 'DESC',
		'tax_query'      => $tax_query,
		'no_found_rows'  => true,
	] );
}

/**
 * Derive a store's programs from its design data: the distinct design categories
 * that actually have published designs tagged to the store. Each is returned as a
 * live, shoppable program — so a store's home/hub populate straight from real
 * inventory, no per-store program setup required.
 *
 * @return array List of [ 'name', 'slug', 'status' => 'live', 'count' ].
 */
function tsa_store_programs_from_designs( string $store_slug ): array {
	$store_slug = sanitize_title( $store_slug );
	if ( ! $store_slug ) return [];

	$ids = get_posts( [
		'post_type'      => 'tsa_design',
		'post_status'    => 'publish',
		'fields'         => 'ids',
		'posts_per_page' => -1,
		'no_found_rows'  => true,
		'tax_query'      => [ [ 'taxonomy' => 'tsa_design_store', 'field' => 'slug', 'terms' => [ $store_slug ] ] ],
	] );
	if ( ! $ids ) return [];

	$terms = wp_get_object_terms( $ids, 'tsa_design_category' );
	if ( is_wp_error( $terms ) || ! $terms ) return [];

	// Tally store-scoped counts per category and capture a fallback preview image
	// (first design in the category) for cards that have no uploaded category image.
	$counts   = [];
	$previews = [];
	foreach ( $ids as $id ) {
		$prev = get_post_meta( $id, '_design_preview_url', true );
		if ( ! $prev ) {
			$tid_thumb = get_post_thumbnail_id( $id );
			$prev      = $tid_thumb ? wp_get_attachment_image_url( $tid_thumb, 'medium' ) : '';
		}
		foreach ( (array) wp_get_object_terms( $id, 'tsa_design_category', [ 'fields' => 'ids' ] ) as $tid ) {
			$counts[ $tid ] = ( $counts[ $tid ] ?? 0 ) + 1;
			if ( empty( $previews[ $tid ] ) && $prev ) $previews[ $tid ] = $prev;
		}
	}

	$programs = [];
	foreach ( $terms as $t ) {
		// Configurable image: the category term's uploaded thumbnail wins; otherwise
		// fall back to a design preview from that category.
		$img    = '';
		$thumb  = get_term_meta( $t->term_id, 'thumbnail_id', true );
		if ( $thumb ) $img = wp_get_attachment_image_url( (int) $thumb, 'large' );
		if ( ! $img ) $img = $previews[ $t->term_id ] ?? '';

		$programs[] = [
			'name'    => $t->name,
			'slug'    => $t->slug,
			'status'  => 'live',
			'count'   => (int) ( $counts[ $t->term_id ] ?? 0 ),
			'image'   => $img,
			'tagline' => (string) get_term_meta( $t->term_id, 'tagline', true ),
		];
	}
	usort( $programs, function ( $a, $b ) { return $b['count'] <=> $a['count'] ?: strcasecmp( $a['name'], $b['name'] ); } );
	return $programs;
}

/**
 * Render a store's design grid (cards → store-branded configurator).
 *
 * Echoes a complete block: the grid when designs exist, or a generic empty state.
 *
 * @param string $store_slug Store taxonomy slug.
 * @param string $cat        Optional program/category slug.
 * @param array  $opts       'empty_html' => custom empty-state markup.
 * @return int Number of designs rendered.
 */
function tsa_render_store_design_grid( string $store_slug, string $cat = '', array $opts = [] ): int {
	$store_slug = sanitize_title( $store_slug );
	if ( ! $store_slug ) return 0;

	$q     = tsa_store_designs_query( $store_slug, $cat );
	$count = (int) $q->post_count;

	if ( ! $count ) {
		$empty = $opts['empty_html'] ?? '<div class="tsa-store-grid-empty" style="text-align:center;padding:64px 24px;color:var(--text-muted,#888)"><div style="font-size:40px;margin-bottom:10px">👕</div><p style="margin:0;font-weight:600">No designs in this collection yet.</p><p style="margin:6px 0 0;font-size:14px">Check back after the next drop.</p></div>';
		echo $empty; // trusted markup
		wp_reset_postdata();
		return 0;
	}

	echo '<div class="tsa-dl-grid tsa-store-grid">';
	while ( $q->have_posts() ) {
		$q->the_post();
		$id      = get_the_ID();
		$title   = get_the_title( $id );
		$preview = get_post_meta( $id, '_design_preview_url', true );
		if ( ! $preview ) {
			$thumb_id = get_post_thumbnail_id( $id );
			$preview  = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'medium' ) : '';
		}
		$cats = wp_get_post_terms( $id, 'tsa_design_category', [ 'fields' => 'names' ] );
		$cfg  = home_url( '/configurator/?design_id=' . $id . '&store=' . rawurlencode( $store_slug ) );

		$visual = $preview
			? '<img src="' . esc_url( $preview ) . '" alt="' . esc_attr( $title ) . '" class="tsa-dl-card__img" loading="lazy">'
			: '<div class="tsa-dl-card__no-preview">🎨</div>';

		$tags = '';
		if ( ! is_wp_error( $cats ) && $cats ) {
			foreach ( array_slice( $cats, 0, 2 ) as $cn ) {
				$tags .= '<span class="tsa-dl-card__tag">' . esc_html( $cn ) . '</span>';
			}
		}

		echo '<article class="tsa-dl-card">'
			. '<div class="tsa-dl-card__visual tsa-autocontrast">'
				. '<a href="' . esc_url( $cfg ) . '" class="tsa-dl-card__imglink" aria-label="' . esc_attr( 'Use ' . $title . ' in the configurator' ) . '" style="display:block;line-height:0;text-decoration:none">' . $visual . '</a>'
				. '<div class="tsa-dl-card__overlay">'
					. '<a href="' . esc_url( $cfg ) . '" class="tsa-dl-card__action tsa-dl-card__action--primary">Configure &amp; Order</a>'
					. '<button type="button" class="tsa-dl-card__action tsa-dl-card__action--secondary tsa-fd-cz" data-id="' . esc_attr( $id ) . '" data-title="' . esc_attr( $title ) . '" data-preview="' . esc_attr( $preview ) . '">Customize It</button>'
				. '</div>'
			. '</div>'
			. '<div class="tsa-dl-card__body">'
				. '<h3 class="tsa-dl-card__title"><a href="' . esc_url( $cfg ) . '" style="color:inherit;text-decoration:none">' . esc_html( $title ) . '</a></h3>'
				. ( $tags ? '<div class="tsa-dl-card__tags">' . $tags . '</div>' : '' )
			. '</div>'
		. '</article>';
	}
	echo '</div>';

	// Print the shared "Customize It" request modal once per request (reuses the
	// global tsa_design_customize AJAX handler + the featured-designs modal).
	static $modal_done = false;
	if ( ! $modal_done ) {
		$modal_done = true;
		if ( function_exists( 'tsa_featured_designs_styles' ) ) tsa_featured_designs_styles();
		if ( function_exists( 'tsa_featured_designs_modal' ) )  tsa_featured_designs_modal();
	}

	wp_reset_postdata();
	return $count;
}
