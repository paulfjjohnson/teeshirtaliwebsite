<?php
/**
 * Generic premium store chrome — the store-type-agnostic version of the
 * hardcoded Dutchtown header/footer. Every store (school / team / business /
 * event) gets the same premium `.dths-page` look, branded from its own store
 * record: logo image + name + tagline + colors.
 *
 * Pairs with the brandable schools/dutchtown/css/dutchtown-scoped.css (now
 * variable-driven) and js/dutchtown.js (adds body.dths-active + hamburger).
 */

defined( 'ABSPATH' ) || exit;

/* ── Color helpers (guarded — theme may define elsewhere) ─────────────────── */
if ( ! function_exists( 'tsa_hex_to_rgb' ) ) {
	function tsa_hex_to_rgb( string $hex ): string {
		$hex = ltrim( trim( $hex ), '#' );
		if ( strlen( $hex ) === 3 ) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
		if ( strlen( $hex ) !== 6 || ! ctype_xdigit( $hex ) ) return '89,44,130';
		return hexdec( substr( $hex, 0, 2 ) ) . ',' . hexdec( substr( $hex, 2, 2 ) ) . ',' . hexdec( substr( $hex, 4, 2 ) );
	}
}
if ( ! function_exists( 'tsa_hex_lighten' ) ) {
	function tsa_hex_lighten( string $hex, float $pct ): string {
		$hex = ltrim( trim( $hex ), '#' );
		if ( strlen( $hex ) === 3 ) $hex = $hex[0].$hex[0].$hex[1].$hex[1].$hex[2].$hex[2];
		if ( strlen( $hex ) !== 6 || ! ctype_xdigit( $hex ) ) return '#8b5cf6';
		$out = '#';
		for ( $i = 0; $i < 3; $i++ ) {
			$c    = hexdec( substr( $hex, $i * 2, 2 ) );
			$c    = (int) round( $c + ( 255 - $c ) * $pct );
			$out .= str_pad( dechex( max( 0, min( 255, $c ) ) ), 2, '0', STR_PAD_LEFT );
		}
		return $out;
	}
}

/** True if $slug is a real store: a store record, or a design-store taxonomy term. */
function tsa_store_exists( string $slug ): bool {
	$slug = sanitize_title( $slug );
	if ( $slug === '' || $slug === 'schools' || $slug === 'tsa' ) return false;
	if ( function_exists( 'tsa_sb_find_store_by_slug' ) && tsa_sb_find_store_by_slug( $slug ) ) return true;
	if ( taxonomy_exists( 'tsa_design_store' ) && term_exists( $slug, 'tsa_design_store' ) ) return true;
	$f = get_posts( [ 'post_type' => 'configurator_store', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_ac_store_slug', 'meta_value' => $slug ] );
	return ! empty( $f );
}

/**
 * Resolve the store slug for a store page, robustly, in priority order:
 *   1. explicit _tsa_store_id / _tsa_store_slug on the page (or its parent)
 *   2. a URL path segment that matches a REAL store  (…/schools/<store>/…)
 *   3. the page's own slug, then its parent's slug — only if it's a real store
 * Returns '' when no real store can be identified (caller shows a clear notice
 * rather than silently rendering the wrong store, e.g. "Schools").
 */
function tsa_resolve_store_slug( int $page_id = 0 ): string {
	$parent_id = $page_id ? wp_get_post_parent_id( $page_id ) : 0;

	// 1. Explicit meta on page or parent.
	foreach ( array_filter( [ $page_id, $parent_id ] ) as $pid ) {
		$sid = (int) get_post_meta( $pid, '_tsa_store_id', true );
		if ( $sid ) {
			$s = sanitize_title( get_post_meta( $sid, '_ac_store_slug', true ) );
			if ( $s ) return $s;
		}
		$s = sanitize_title( (string) get_post_meta( $pid, '_tsa_store_slug', true ) );
		if ( $s && tsa_store_exists( $s ) ) return $s;
	}

	// 2. URL path segment that resolves to a real store.
	$path = trim( (string) wp_parse_url( $_SERVER['REQUEST_URI'] ?? '', PHP_URL_PATH ), '/' );
	foreach ( array_filter( explode( '/', $path ) ) as $seg ) {
		$seg = sanitize_title( $seg );
		if ( tsa_store_exists( $seg ) ) return $seg;
	}

	// 3. Page slug, then parent slug.
	foreach ( array_filter( [ $page_id, $parent_id ] ) as $pid ) {
		$s = sanitize_title( get_post_field( 'post_name', $pid ) );
		if ( $s && tsa_store_exists( $s ) ) return $s;
	}

	// 4. URL convention fallback: the segment right after a known store-section
	//    base (…/schools/<store>/…). Trusted even when the store record/term
	//    lookup is imperfect — the design query is by slug anyway — so a store
	//    never dead-ends just because its landing page lacks a _tsa_store_id.
	$segs     = array_values( array_filter( explode( '/', $path ) ) );
	$bases    = apply_filters( 'tsa_store_section_bases', [ 'schools', 'teams', 'stores', 'shops', 'business', 'events' ] );
	$reserved = [ 'programs', 'drops' ]; // structural child slugs — never a store
	foreach ( $segs as $i => $seg ) {
		if ( in_array( sanitize_title( $seg ), $bases, true ) && isset( $segs[ $i + 1 ] ) ) {
			$cand = sanitize_title( $segs[ $i + 1 ] );
			if ( ! in_array( $cand, $reserved, true ) ) return $cand;
		}
	}
	return '';
}

/**
 * Resolve everything a store template/chrome needs from a store id or slug.
 * Robust to stores created without a full record (falls back to slug + palette).
 */
function tsa_store_context( $store ): array {
	$store_id = 0;
	$slug     = '';

	if ( is_numeric( $store ) ) {
		$store_id = (int) $store;
	} else {
		$slug = sanitize_title( (string) $store );
		if ( function_exists( 'tsa_sb_find_store_by_slug' ) ) {
			$store_id = (int) tsa_sb_find_store_by_slug( $slug );
		}
		if ( ! $store_id ) {
			$found = get_posts( [ 'post_type' => 'configurator_store', 'post_status' => 'publish', 'numberposts' => 1, 'fields' => 'ids', 'meta_key' => '_ac_store_slug', 'meta_value' => $slug ] );
			$store_id = $found ? (int) $found[0] : 0;
		}
	}
	if ( $store_id && ! $slug ) {
		$slug = sanitize_title( get_post_meta( $store_id, '_ac_store_slug', true ) );
	}

	// Lazily fire any due scheduled drops for this store (throttled to once / 10 min
	// per store), so drops go live on page views even if WP-Cron is unreliable.
	if ( $store_id && function_exists( 'tsa_store_activate_drops' ) ) {
		$tk = 'tsa_sd_seen_' . $store_id;
		if ( ! get_transient( $tk ) ) {
			set_transient( $tk, 1, 10 * MINUTE_IN_SECONDS );
			tsa_store_activate_drops( $store_id );
		}
	}

	$name    = $store_id ? get_the_title( $store_id ) : ucwords( str_replace( '-', ' ', $slug ) );
	$tagline = $store_id ? ( get_post_meta( $store_id, '_tsa_store_tagline', true ) ?: '' ) : '';
	$type    = $store_id ? ( get_post_meta( $store_id, '_tsa_store_type', true ) ?: 'school' ) : 'school';

	// Logo image (store featured image), else '' → chrome uses initials.
	$logo_id  = $store_id ? get_post_thumbnail_id( $store_id ) : 0;
	$logo_url = $logo_id ? wp_get_attachment_image_url( $logo_id, 'medium' ) : '';

	// Colors: store record → school palette → default purple.
	$primary   = $store_id ? get_post_meta( $store_id, '_tsa_school_primary', true ) : '';
	$secondary = $store_id ? get_post_meta( $store_id, '_tsa_school_secondary', true ) : '';
	if ( ! $primary && function_exists( 'tsa_get_school_colors' ) ) {
		$pal       = tsa_get_school_colors( $name );
		$primary   = $pal['primary'] ?? '';
		$secondary = $secondary ?: ( $pal['secondary'] ?? '' );
	}
	// Neutral TSA brand fallback (gold/ink — see assets/css/tsa-tokens.css) when a
	// store has no colors of its own. NOT Dutchtown's purple — that's one specific
	// school's brand, not a sensible default for every unbranded store.
	$primary   = $primary   ?: '#d8a85f';
	$secondary = $secondary ?: '#252124';

	$home_url = function_exists( 'tsa_store_url' ) && $slug ? tsa_store_url( $slug ) : home_url( '/schools/' . $slug . '/' );
	if ( ! $home_url ) $home_url = home_url( '/schools/' . $slug . '/' );

	// Base path for program / hub / drops children (derive from home_url path).
	$base = trailingslashit( wp_parse_url( $home_url, PHP_URL_PATH ) ?: ( '/schools/' . $slug . '/' ) );

	// Programs: the store record's curated list drives name/status/ordering (live/
	// coming-soon), but its entries carry no design count of their own — merge in
	// real counts/images from the store's actual design inventory, matched by slug.
	// A store with no curated list at all falls back to the fully design-derived list.
	$programs = ( $store_id && function_exists( 'tsa_school_programs' ) ) ? tsa_school_programs( $store_id ) : [];
	if ( $slug && function_exists( 'tsa_store_programs_from_designs' ) ) {
		$live = tsa_store_programs_from_designs( $slug );
		if ( $programs ) {
			$live_by_slug = [];
			foreach ( $live as $lp ) { $live_by_slug[ $lp['slug'] ] = $lp; }
			foreach ( $programs as &$p ) {
				$match = $live_by_slug[ $p['slug'] ] ?? null;
				$p['count'] = $match['count'] ?? 0;
				if ( empty( $p['image'] ) && $match ) $p['image'] = $match['image'];
			}
			unset( $p );
		} else {
			$programs = $live;
		}
	}

	// Primary nav stays lean: individual programs live in the Programs hub, not the
	// top bar (a store can have 15+ programs — they don't belong in the header).
	$nav = [
		[ 'label' => 'Home',     'url' => $home_url ],
		[ 'label' => 'Programs', 'url' => home_url( $base . 'programs/' ) ],
		[ 'label' => 'Drops',    'url' => home_url( $base . 'drops/' ) ],
	];

	// Ticker/marquee: store's own phrases (one per line, set via the Store Builder),
	// else the generic default so stores that haven't set one still get a ticker.
	$ticker_raw = $store_id ? get_post_meta( $store_id, '_tsa_school_ticker', true ) : '';
	$ticker     = $ticker_raw ? array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $ticker_raw ) ) ) ) : [];
	if ( ! $ticker ) {
		$ticker = [ strtoupper( $name ) . ' PRIDE', 'OFFICIAL MERCH', 'DROPS ON SCHEDULE', 'PERFORMANCE FIRST', 'BUILT IN GA', 'TEE SHIRT ALI' ];
	}

	return [
		'id'        => $store_id,
		'slug'      => $slug,
		'name'      => $name,
		'tagline'   => $tagline,
		'type'      => $type,
		'logo_url'  => $logo_url,
		'home_url'  => $home_url,
		'base'      => $base,
		'primary'   => $primary,
		'secondary' => $secondary,
		'text'      => function_exists( 'tsa_readable_text' ) ? tsa_readable_text( $primary ) : '#ffffff',
		'programs'  => $programs,
		'nav'       => $nav,
		'ticker'    => $ticker,
	];
}

/**
 * Destination for a program card — the Programs hub drill-down (…/programs/?program=slug).
 * One consistent pattern for every program across the site: category card → the
 * program's designs, in the premium chrome, with no per-program pages required.
 */
function tsa_store_program_url( array $ctx, string $pslug ): string {
	return add_query_arg( 'program', sanitize_title( $pslug ), home_url( $ctx['base'] . 'programs/' ) );
}

/** Inline <style> that rebrands the premium CSS variables for this store. */
function tsa_store_brand_style( array $ctx ): void {
	$primary = $ctx['primary'];
	$bright  = tsa_hex_lighten( $primary, .32 );
	printf(
		'<style id="tsa-store-brand">body.dths-active,.dths-page{--dths-purple:%1$s;--dths-purple-mid:%1$s;--dths-purple-bright:%2$s;--dths-purple-bright-rgb:%3$s;--dths-silver:%4$s;}</style>',
		esc_attr( $primary ),
		esc_attr( $bright ),
		esc_attr( tsa_hex_to_rgb( $bright ) ),
		esc_attr( $ctx['secondary'] )
	);
}

/** Initials for the logo fallback mark (e.g. "Dutchtown Griffins" → "DG"). */
function tsa_store_initials( string $name ): string {
	$parts = preg_split( '/\s+/', trim( $name ) );
	$ini   = '';
	foreach ( $parts as $p ) { if ( $p !== '' ) { $ini .= strtoupper( $p[0] ); } if ( strlen( $ini ) >= 2 ) break; }
	return $ini ?: 'TS';
}

/**
 * Premium store header — generic port of schools/dutchtown/header.php.
 * Call AFTER get_header(); the enqueued CSS+JS suppress Flatsome's chrome.
 */
function tsa_store_chrome_header( array $ctx ): void {
	$cart_count = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
	$current    = trailingslashit( home_url( wp_parse_url( $_SERVER['REQUEST_URI'] ?? '/', PHP_URL_PATH ) ) );
	$mark       = $ctx['logo_url']
		? '<img src="' . esc_url( $ctx['logo_url'] ) . '" alt="" style="width:100%;height:100%;object-fit:contain">'
		: esc_html( tsa_store_initials( $ctx['name'] ) );
	$sub = $ctx['tagline'] ?: 'Official Merch';
	?>
	<div class="dths-topbar" style="background:#050506;border-bottom:1px solid var(--dths-line)">
	  <div class="dths-shell" style="display:flex;justify-content:flex-end;align-items:center;padding:7px 0">
	    <a href="<?php echo esc_url( home_url( '/' ) ); ?>" style="color:var(--dths-muted);text-decoration:none;font-size:11.5px;font-weight:700;letter-spacing:.03em">&larr; Back to TeeShirtAli.com</a>
	  </div>
	</div>
	<header class="dths-header" id="dths-header" role="banner">
	  <div class="dths-shell dths-header-inner">
	    <a href="<?php echo esc_url( $ctx['home_url'] ); ?>" class="dths-header-logo" aria-label="<?php echo esc_attr( $ctx['name'] ); ?> Home">
	      <div class="dths-header-mark"><?php echo $mark; // logo img or initials ?></div>
	      <div class="dths-header-logo-text">
	        <span class="dths-header-school"><?php echo esc_html( $ctx['name'] ); ?></span>
	        <span class="dths-header-sub"><?php echo esc_html( $sub ); ?></span>
	      </div>
	    </a>
	    <nav class="dths-header-nav" aria-label="Primary">
	      <?php foreach ( $ctx['nav'] as $item ) :
	        $active = $current === trailingslashit( $item['url'] ); ?>
	      <a href="<?php echo esc_url( $item['url'] ); ?>" class="dths-header-link<?php echo $active ? ' is-active' : ''; ?>"><?php echo esc_html( $item['label'] ); ?></a>
	      <?php endforeach; ?>
	    </nav>
	    <div class="dths-header-actions">
	      <?php if ( is_user_logged_in() ) : ?>
	      <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="dths-header-account" aria-label="Customer Portal">
	        <svg width="17" height="17" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2"><path d="M20 21v-2a4 4 0 00-4-4H8a4 4 0 00-4 4v2"/><circle cx="12" cy="7" r="4"/></svg>
	        <span class="dths-header-account-name"><?php echo esc_html( wp_get_current_user()->display_name ); ?></span>
	      </a>
	      <?php else : ?>
	      <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="dths-header-signin">Sign In</a>
	      <?php endif; ?>
	      <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="dths-header-cart" aria-label="Shopping cart">
	        <svg width="18" height="18" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" aria-hidden="true"><path d="M6 2L3 6v14a2 2 0 002 2h14a2 2 0 002-2V6l-3-4z"/><line x1="3" y1="6" x2="21" y2="6"/><path d="M16 10a4 4 0 01-8 0"/></svg>
	        <span class="dths-header-cart-label">Bag</span>
	        <span class="dths-header-cart-count<?php echo $cart_count > 0 ? ' has-items' : ''; ?>" id="dths-cart-count"><?php echo absint( $cart_count ); ?></span>
	      </a>
	      <button class="dths-header-hamburger" id="dths-hamburger" aria-label="Open menu" aria-expanded="false"><span></span><span></span><span></span></button>
	    </div>
	  </div>
	</header>

	<div class="dths-mobile-nav" id="dths-mobile-nav" aria-hidden="true">
	  <div class="dths-mobile-nav-inner">
	    <div class="dths-mobile-nav-top">
	      <a href="<?php echo esc_url( $ctx['home_url'] ); ?>" class="dths-header-logo">
	        <div class="dths-header-mark"><?php echo $mark; ?></div>
	        <div class="dths-header-logo-text">
	          <span class="dths-header-school"><?php echo esc_html( $ctx['name'] ); ?></span>
	          <span class="dths-header-sub"><?php echo esc_html( $sub ); ?></span>
	        </div>
	      </a>
	      <button class="dths-mobile-nav-close" id="dths-mobile-close" aria-label="Close menu">✕</button>
	    </div>
	    <nav class="dths-mobile-nav-links">
	      <?php foreach ( $ctx['nav'] as $item ) :
	        $active = $current === trailingslashit( $item['url'] ); ?>
	      <a href="<?php echo esc_url( $item['url'] ); ?>" class="dths-mobile-nav-link<?php echo $active ? ' is-active' : ''; ?>"><?php echo esc_html( $item['label'] ); ?><span class="dths-mobile-nav-arrow">→</span></a>
	      <?php endforeach; ?>
	    </nav>
	    <div class="dths-mobile-nav-footer">
	      <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="dths-btn-primary" style="width:100%;justify-content:center">🛍 View Bag (<?php echo absint( $cart_count ); ?>)</a>
	      <?php if ( ! is_user_logged_in() ) : ?>
	      <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="dths-btn-secondary" style="width:100%;justify-content:center;margin-top:10px">Sign In</a>
	      <?php endif; ?>
	      <div class="dths-mobile-nav-tsa"><a href="<?php echo esc_url( home_url( '/' ) ); ?>">← Back to TeeShirtAli.com</a></div>
	    </div>
	  </div>
	</div>
	<div class="dths-mobile-nav-backdrop" id="dths-mobile-backdrop"></div>
	<?php
}

/**
 * Premium store footer — generic port of schools/dutchtown/footer.php.
 * Emits wp_footer() and closes body/html, so templates must NOT call get_footer().
 */
function tsa_store_chrome_footer( array $ctx ): void {
	$b = $ctx['base'];
	?>
	<footer class="dths-footer" id="dths-footer" role="contentinfo">
	  <div class="dths-footer-inner"><div class="dths-shell">
	    <div class="dths-footer-grid">
	      <div class="dths-footer-brand-col">
	        <a href="<?php echo esc_url( $ctx['home_url'] ); ?>" class="dths-footer-logo" aria-label="<?php echo esc_attr( $ctx['name'] ); ?> Home">
	          <div class="dths-header-mark" style="width:44px;height:44px;font-size:16px"><?php echo $ctx['logo_url'] ? '<img src="' . esc_url( $ctx['logo_url'] ) . '" alt="" style="width:100%;height:100%;object-fit:contain">' : esc_html( tsa_store_initials( $ctx['name'] ) ); ?></div>
	          <div class="dths-header-logo-text">
	            <span class="dths-header-school" style="font-size:18px"><?php echo esc_html( $ctx['name'] ); ?></span>
	            <span class="dths-header-sub"><?php echo esc_html( $ctx['tagline'] ?: 'Official Merch' ); ?></span>
	          </div>
	        </a>
	        <p class="dths-footer-desc">Premium spirit merchandise for <?php echo esc_html( $ctx['name'] ); ?> — new drops on a schedule, printed and shipped by Tee Shirt Ali.</p>
	        <div class="dths-footer-brand-colors">
	          <span class="dths-footer-swatch" style="background:<?php echo esc_attr( $ctx['primary'] ); ?>"></span>
	          <span class="dths-footer-swatch" style="background:<?php echo esc_attr( $ctx['secondary'] ); ?>"></span>
	          <span class="dths-footer-swatch" style="background:#09090b;border:1px solid rgba(255,255,255,.15)"></span>
	        </div>
	      </div>
	      <div class="dths-footer-col">
	        <div class="dths-footer-col-heading">Shop</div>
	        <ul>
	          <?php foreach ( array_slice( $ctx['nav'], 1 ) as $item ) : ?>
	          <li><a href="<?php echo esc_url( $item['url'] ); ?>"><?php echo esc_html( $item['label'] ); ?></a></li>
	          <?php endforeach; ?>
	        </ul>
	      </div>
	      <?php if ( class_exists( 'WooCommerce' ) ) : ?>
	      <div class="dths-footer-col">
	        <div class="dths-footer-col-heading">Customer Portal</div>
	        <ul>
	          <li><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>">Sign In</a></li>
	          <li><a href="<?php echo esc_url( wc_get_cart_url() ); ?>">My Bag</a></li>
	          <li><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">Order History</a></li>
	        </ul>
	      </div>
	      <?php endif; ?>
	      <div class="dths-footer-col">
	        <div class="dths-footer-col-heading">Help</div>
	        <ul>
	          <li><a href="<?php echo esc_url( home_url( '/sizing-guide/' ) ); ?>">Sizing Guide</a></li>
	          <li><a href="<?php echo esc_url( home_url( '/shipping-policy/' ) ); ?>">Shipping</a></li>
	          <li><a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">FAQ</a></li>
	        </ul>
	      </div>
	    </div>
	    <div class="dths-footer-divider"></div>
	    <div class="dths-footer-bottom">
	      <div class="dths-footer-copy">&copy; <?php echo esc_html( date( 'Y' ) ); ?> <?php echo esc_html( $ctx['name'] ); ?> &mdash; All Rights Reserved</div>
	      <div class="dths-footer-powered"><span>Powered by</span> <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dths-footer-tsa-link">TeeShirtAli.com</a></div>
	      <div class="dths-footer-legal">
	        <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy</a>
	        <a href="<?php echo esc_url( home_url( '/terms-of-service/' ) ); ?>">Terms</a>
	      </div>
	    </div>
	  </div></div>
	</footer>
	<?php
	wp_footer();
	echo '</body></html>';
}
