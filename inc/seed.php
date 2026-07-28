<?php
/**
 * TSA Site Seeder — stand up a fresh tenant's core pages in one step.
 *
 * Provisioning (inc/provisioning.php) brands + entitles a tenant and stamps its
 * first store, but it does NOT create the site "chrome" — the ~30 top-level
 * pages (Home, Quote, Configurator, Services, policies, …) that each need a
 * WordPress Page with the correct page-template assigned. That was the manual,
 * error-prone step (a page on the wrong template silently breaks its feature).
 *
 * This module makes it one command:
 *     wp tsa seed            (single-site: seeds the current site)
 *     wp tsa seed --dry-run  (preview only)
 * and hooks `tsa_provision_seed_subsite` so a Multisite subsite auto-seeds in
 * the same pipeline. Also exposed as a Platform admin button for CLI-less hosts.
 *
 * FULLY IDEMPOTENT: pages are matched by slug, so re-running never duplicates.
 * Existing pages are left as-is except to ensure the right template — your
 * content edits are never overwritten. Only singleton pages are seeded;
 * per-store / per-party / directory pages remain owned by the school + party
 * engines (tsa_sb_ensure_* / the tee-party CPT) to avoid double ownership.
 */
defined( 'ABSPATH' ) || exit;

/**
 * The core-page manifest. Filterable so a tenant/tier can add or drop pages.
 * Each: slug, title, template, and optional flags:
 *   front  => true  set as the static front page
 *   primary=> true  add to the primary nav menu (in listed order)
 *   footer => true  add to the footer nav menu (in listed order)
 */
function tsa_seed_pages(): array {
	$pages = [
		// slug, title, template, [flags]
		[ 'home',                  'Home',                     'template-homepage.php',            [ 'front' => true ] ],
		[ 'configurator',          'Design Studio',            'template-configurator.php',        [ 'primary' => true ] ],
		[ 'design-library',        'Design Library',           'template-design-library.php',      [ 'primary' => true ] ],
		[ 'gang-sheet-builder',    'Gang Sheet Builder',       'template-gang-sheet-builder.php',  [ 'primary' => true ] ],
		[ 'blank-apparel',         'Blank Apparel',            'template-blank-apparel.php',       [] ],
		[ 'brands',                'Brand Catalog',            'template-brand-catalog.php',       [] ],

		// Services
		[ 'services',              'Services',                 'template-services-hub.php',        [ 'primary' => true ] ],
		[ 'custom-apparel',        'Custom Apparel',           'template-custom-apparel.php',      [] ],
		[ 'graphic-design',        'Graphic Design',           'template-graphic-design.php',      [] ],
		[ 'promo-products',        'Promotional Products',     'template-promo-products.php',      [] ],
		[ 'event-merch',           'Event Merchandise',        'template-event-merch.php',         [] ],
		[ 'spirit-wear',           'Spirit Wear',              'template-spirit-wear.php',         [] ],
		[ 'printing-capabilities', 'Printing Capabilities',    'template-printing-capabilities.php', [] ],

		// Engage / requests
		[ 'quote',                 'Request a Quote',          'template-quote.php',               [ 'primary' => true ] ],
		[ 'request-a-store',       'Request a Store',          'template-request-store.php',       [] ],
		[ 'tee-parties',           'Tee Parties',              'template-tee-party-hub.php',        [ 'primary' => true ] ],
		[ 'tee-party-request',     'Host a Tee Party',         'template-tee-party-request.php',   [] ],
		[ 'start-a-fundraiser',    'Start a Fundraiser',       'template-fundraiser-form.php',     [] ],
		[ 'fundraisers',           'Fundraisers',              'template-fundraiser.php',          [] ],

		// Account / owner
		[ 'customer-portal',       'My Account',               'template-customer-portal.php',     [] ],
		[ 'dashboard',             'Command Center',           'template-dashboard.php',           [] ],

		// Company / info
		[ 'about',                 'About',                    'template-about.php',               [ 'primary' => true ] ],
		[ 'contact',               'Contact',                  'template-contact.php',             [ 'primary' => true ] ],
		[ 'faq',                   'FAQ',                      'template-faq.php',                 [ 'footer' => true ] ],
		[ 'help',                  'Help Center',              'template-help.php',                [] ],
		[ 'sizing-guide',          'Sizing Guide',             'template-sizing-guide.php',        [ 'footer' => true ] ],
		[ 'turnaround-times',      'Turnaround Times',         'template-turnaround-times.php',    [ 'footer' => true ] ],

		// Policies (footer)
		[ 'shipping-policy',       'Shipping Policy',          'template-shipping-policy.php',     [ 'footer' => true ] ],
		[ 'returns',               'Returns',                  'template-returns.php',             [ 'footer' => true ] ],
		[ 'privacy-policy',        'Privacy Policy',           'template-privacy-policy.php',      [ 'footer' => true ] ],
		[ 'terms',                 'Terms',                    'template-terms.php',               [ 'footer' => true ] ],
	];

	// Normalize into associative rows.
	$out = [];
	foreach ( $pages as $p ) {
		$out[] = [
			'slug'     => $p[0],
			'title'    => $p[1],
			'template' => $p[2],
			'flags'    => $p[3] ?? [],
		];
	}
	return apply_filters( 'tsa_seed_pages', $out );
}

/** Find an existing top-level page by slug (any status), or 0. */
function tsa_seed_find_page( string $slug ): int {
	$ids = get_posts( [
		'post_type'   => 'page',
		'post_status' => 'any',
		'numberposts' => 1,
		'fields'      => 'ids',
		'name'        => $slug,
		'post_parent' => 0,
	] );
	return $ids ? (int) $ids[0] : 0;
}

/**
 * Seed the current site's core pages, front page, WooCommerce account page,
 * menus and permalinks. Idempotent.
 *
 * @param array $opts dry_run(bool)
 * @return array ok, created[], updated[], steps[], errors[]
 */
function tsa_seed_site( array $opts = [] ): array {
	$dry    = ! empty( $opts['dry_run'] );
	$result = [ 'ok' => false, 'created' => [], 'updated' => [], 'steps' => [], 'errors' => [] ];
	$theme_dir = get_stylesheet_directory();

	$ids        = [];   // slug => page id
	$primary    = [];   // ordered page ids for primary menu
	$footer     = [];   // ordered page ids for footer menu
	$front_id   = 0;
	$portal_id  = 0;

	foreach ( tsa_seed_pages() as $p ) {
		$slug = $p['slug'];
		$tpl  = $p['template'];

		// Skip a page whose template file isn't shipped in this theme.
		if ( ! file_exists( $theme_dir . '/' . $tpl ) ) {
			$result['steps'][] = "Skipped '{$slug}' — template {$tpl} not found in theme.";
			continue;
		}

		$pid = tsa_seed_find_page( $slug );
		if ( $pid ) {
			// Exists — ensure the template only; never touch title/content.
			if ( get_post_meta( $pid, '_wp_page_template', true ) !== $tpl ) {
				if ( ! $dry ) { update_post_meta( $pid, '_wp_page_template', $tpl ); }
				$result['updated'][] = $slug;
				$result['steps'][]   = "Page '{$slug}' (#{$pid}) — template set to {$tpl}.";
			} else {
				$result['steps'][] = "Page '{$slug}' (#{$pid}) — already correct.";
			}
		} else {
			if ( $dry ) {
				$result['created'][] = $slug;
				$result['steps'][]   = "Would create page '{$slug}' on {$tpl}.";
				$pid = 0;
			} else {
				$pid = wp_insert_post( [
					'post_type'    => 'page',
					'post_status'  => 'publish',
					'post_title'   => $p['title'],
					'post_name'    => $slug,
					'post_parent'  => 0,
				], true );
				if ( is_wp_error( $pid ) || ! $pid ) {
					$result['errors'][] = "Failed to create '{$slug}': " . ( is_wp_error( $pid ) ? $pid->get_error_message() : 'unknown' );
					continue;
				}
				update_post_meta( $pid, '_wp_page_template', $tpl );
				$result['created'][] = $slug;
				$result['steps'][]   = "Created page '{$slug}' (#{$pid}) on {$tpl}.";
			}
		}

		if ( $pid ) { $ids[ $slug ] = (int) $pid; }
		if ( ! empty( $p['flags']['front'] ) && $pid )   { $front_id  = (int) $pid; }
		if ( 'customer-portal' === $slug && $pid )        { $portal_id = (int) $pid; }
		if ( ! empty( $p['flags']['primary'] ) && $pid ) { $primary[] = (int) $pid; }
		if ( ! empty( $p['flags']['footer'] ) && $pid )  { $footer[]  = (int) $pid; }
	}

	// ── Static front page ──
	if ( $front_id && ! $dry ) {
		if ( 'page' !== get_option( 'show_on_front' ) || (int) get_option( 'page_on_front' ) !== $front_id ) {
			update_option( 'show_on_front', 'page' );
			update_option( 'page_on_front', $front_id );
			$result['steps'][] = "Front page set to 'home' (#{$front_id}).";
		}
	} elseif ( $front_id ) {
		$result['steps'][] = "Would set front page to 'home' (#{$front_id}).";
	}

	// ── WooCommerce My Account → Customer Portal (the redirect keys off this) ──
	if ( $portal_id && function_exists( 'wc_get_page_id' ) ) {
		if ( (int) get_option( 'woocommerce_myaccount_page_id' ) !== $portal_id ) {
			if ( ! $dry ) { update_option( 'woocommerce_myaccount_page_id', $portal_id ); }
			$result['steps'][] = "WooCommerce 'My Account' page → Customer Portal (#{$portal_id}).";
		}
	}

	// ── Menus ──
	tsa_seed_menu( 'TSA Primary', 'tsa-primary', $primary, $result, $dry );
	tsa_seed_menu( 'TSA Footer',  'tsa-footer-nav', $footer, $result, $dry );

	// ── Pretty permalinks (many templates rely on /slug/ paths) ──
	if ( '' === (string) get_option( 'permalink_structure' ) ) {
		if ( ! $dry ) {
			update_option( 'permalink_structure', '/%postname%/' );
			flush_rewrite_rules( false );
		}
		$result['steps'][] = 'Permalinks set to post-name.';
	} elseif ( ! $dry ) {
		flush_rewrite_rules( false );
	}

	$result['ok'] = empty( $result['errors'] );
	return $result;
}

/** Ensure a nav menu exists, contains the given pages (in order), and is assigned to $location. */
function tsa_seed_menu( string $name, string $location, array $page_ids, array &$result, bool $dry ): void {
	if ( ! $page_ids ) { return; }

	$menu = wp_get_nav_menu_object( $name );
	if ( ! $menu ) {
		if ( $dry ) { $result['steps'][] = "Would create menu '{$name}' + assign to {$location}."; return; }
		$mid = wp_create_nav_menu( $name );
		if ( is_wp_error( $mid ) ) { $result['errors'][] = "Menu '{$name}': " . $mid->get_error_message(); return; }
		$menu = wp_get_nav_menu_object( $mid );
		$result['steps'][] = "Menu '{$name}' created.";
	}

	$have = [];
	foreach ( (array) wp_get_nav_menu_items( $menu->term_id ) as $it ) {
		if ( 'page' === $it->object ) { $have[] = (int) $it->object_id; }
	}
	$added = 0;
	foreach ( $page_ids as $pid ) {
		if ( in_array( (int) $pid, $have, true ) ) { continue; }
		if ( $dry ) { $added++; continue; }
		wp_update_nav_menu_item( $menu->term_id, 0, [
			'menu-item-object'    => 'page',
			'menu-item-object-id' => (int) $pid,
			'menu-item-type'      => 'post_type',
			'menu-item-status'    => 'publish',
		] );
		$added++;
	}
	if ( $added ) { $result['steps'][] = ( $dry ? 'Would add ' : 'Added ' ) . $added . " item(s) to '{$name}'."; }

	if ( ! $dry ) {
		$loc = get_theme_mod( 'nav_menu_locations', [] );
		if ( (int) ( $loc[ $location ] ?? 0 ) !== (int) $menu->term_id ) {
			$loc[ $location ] = (int) $menu->term_id;
			set_theme_mod( 'nav_menu_locations', $loc );
			$result['steps'][] = "Menu '{$name}' assigned to {$location}.";
		}
	}
}

/* ─── Auto-seed a Multisite subsite during provisioning ────────────── */
add_action( 'tsa_provision_seed_subsite', function ( $blog_id, $answers, &$result ) {
	$seed = tsa_seed_site();
	foreach ( $seed['steps'] as $s ) {
		if ( is_array( $result ) ) { $result['steps'][] = 'seed: ' . $s; }
	}
	foreach ( $seed['errors'] as $e ) {
		if ( is_array( $result ) ) { $result['errors'][] = 'seed: ' . $e; }
	}
}, 10, 3 );

/* ─── WP-CLI: wp tsa seed [--dry-run] ──────────────────────────────── */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'tsa seed', function ( $args, $assoc ) {
		$r = tsa_seed_site( [ 'dry_run' => isset( $assoc['dry-run'] ) ] );
		foreach ( $r['steps'] as $s ) { WP_CLI::log( '  - ' . $s ); }
		foreach ( $r['errors'] as $e ) { WP_CLI::warning( $e ); }
		WP_CLI::log( sprintf( '%d created, %d updated.', count( $r['created'] ), count( $r['updated'] ) ) );
		$r['ok'] ? WP_CLI::success( isset( $assoc['dry-run'] ) ? 'Dry run complete.' : 'Site seeded.' )
		         : WP_CLI::error( 'Seeding finished with errors.', false );
	} );
}

/* ─── Platform admin button (for hosts without WP-CLI) ─────────────── */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'tsa-platform', 'Seed Core Pages', 'Seed Core Pages',
		'manage_options', 'tsa-seed', 'tsa_seed_admin_page'
	);
}, 21 );

function tsa_seed_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$result = null;
	if ( ! empty( $_POST['tsa_seed_go'] ) && check_admin_referer( 'tsa_seed', 'tsa_seed_nonce' ) ) {
		$result = tsa_seed_site( [ 'dry_run' => ! empty( $_POST['tsa_seed_dry'] ) ] );
	}
	?>
	<div class="wrap">
		<h1>Seed Core Pages</h1>
		<p style="max-width:760px;color:#50575e">Create this site's core pages (Home, Quote, Configurator, Services, policies, …) each on the correct template, wire the front page, WooCommerce account page, and primary/footer menus. Safe to run repeatedly — existing pages are matched by slug and never overwritten, only their template is corrected.</p>
		<form method="post">
			<?php wp_nonce_field( 'tsa_seed', 'tsa_seed_nonce' ); ?>
			<p><label><input type="checkbox" name="tsa_seed_dry" value="1"> Dry run (preview only — makes no changes)</label></p>
			<p>
				<button type="submit" name="tsa_seed_go" value="1" class="button button-primary" onclick="return this.form.tsa_seed_dry.checked || confirm('Create/repair core pages on this site now?');">Run seeder</button>
			</p>
		</form>
		<?php if ( $result ) : ?>
			<h2><?php echo esc_html( sprintf( '%d created, %d updated', count( $result['created'] ), count( $result['updated'] ) ) ); ?></h2>
			<?php if ( $result['errors'] ) : ?>
				<div class="notice notice-error"><p><?php echo esc_html( implode( ' · ', $result['errors'] ) ); ?></p></div>
			<?php endif; ?>
			<pre style="background:#fff;border:1px solid #dcdcde;border-radius:6px;padding:12px 14px;max-width:860px;max-height:420px;overflow:auto;white-space:pre-wrap"><?php echo esc_html( implode( "\n", $result['steps'] ) ); ?></pre>
		<?php endif; ?>
	</div>
	<?php
}
