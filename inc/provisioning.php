<?php
/**
 * TSA / Parabellum Provisioning API (idea #3, sub-project 2.1).
 *
 * One entry point — tsa_provision_tenant($answers) — that turns a set of
 * buyer answers into a configured tenant:
 *   validate → resolve target site → run migrations → apply tier →
 *   record identity → stamp the first store (reusing the proven school
 *   stamp engine) → fire a 'tenant.provisioned' event.
 *
 * IDEMPOTENT: re-running with the same slug updates rather than duplicates
 * (subsite reused; tsa_apply_tier overwrites; tsa_sb_build_school is itself
 * idempotent by slug).
 *
 * RUNS TODAY ON SINGLE-SITE (provisions in place — fully headless-testable
 * via `wp tsa provision`). On Multisite it finds/creates a subsite and runs
 * the same pipeline there. SEEDING the new subsite (theme/plugins/content) is
 * intentionally left to the `tsa_provision_seed_subsite` action so the chosen
 * clone-from-template approach slots in later with zero pipeline changes.
 *
 * Depends on inc/platform.php (tsa_tiers/tsa_apply_tier/tsa_report_event) and
 * inc/school-builder.php (tsa_sb_build_school).
 */
defined( 'ABSPATH' ) || exit;

/**
 * Provision (or update) a tenant from buyer answers.
 *
 * @param array $answers {
 *   @type string business_name  Required. Display name.
 *   @type string slug           Tenant/subsite slug (defaults from name).
 *   @type string store_type     school|team|business (default school).
 *   @type string tier           starter|pro|platform. Required.
 *   @type string admin_email    Subsite admin (Multisite only).
 *   ...plus school fields passed through to the stamp engine
 *      (mascot, level, status, spotlight, primary, secondary, logo_id,
 *       tagline, pickups, shipping, contact_email, programs).
 * }
 * @return array Structured result: ok, slug, blog_id, tier, verb, steps[], errors[], multisite.
 */
function tsa_provision_tenant( array $answers ): array {
	$result = [
		'ok'        => false,
		'slug'      => '',
		'blog_id'   => 0,            // 0 = current site (single-site / in-place)
		'tier'      => '',
		'verb'      => '',           // Created|Updated (from the stamp engine)
		'steps'     => [],
		'errors'    => [],
		'multisite' => is_multisite(),
	];

	// ── 1. Validate ──────────────────────────────────────────────
	$name = sanitize_text_field( $answers['business_name'] ?? '' );
	$slug = sanitize_title( $answers['slug'] ?? $name );
	$type = sanitize_key( $answers['store_type'] ?? 'school' );
	$tier = sanitize_key( $answers['tier'] ?? '' );

	if ( ! $name || ! $slug ) {
		$result['errors'][] = 'business_name and slug are required.';
		return $result;
	}
	$tiers = function_exists( 'tsa_tiers' ) ? tsa_tiers() : [];
	if ( ! isset( $tiers[ $tier ] ) ) {
		$result['errors'][] = "Unknown tier '{$tier}'.";
		return $result;
	}

	// Store type must be (a) included in the chosen tier and (b) actually built.
	$types        = function_exists( 'tsa_store_types' ) ? tsa_store_types() : [];
	$type_feature = $types[ $type ]['feature'] ?? '';
	$catalog      = function_exists( 'tsa_feature_catalog' ) ? tsa_feature_catalog() : [];
	$type_life    = $catalog[ $type_feature ]['lifecycle'] ?? 'ga';
	if ( $type_feature && ! in_array( $type_feature, (array) $tiers[ $tier ]['features'], true ) ) {
		$result['errors'][] = "Tier '{$tier}' does not include the '{$type}' store type.";
		return $result;
	}
	if ( in_array( $type_life, [ 'planned', 'deprecated' ], true ) ) {
		$result['errors'][] = "Store type '{$type}' isn't built yet (coming soon) — provision a school for now.";
		return $result;
	}

	$result['slug'] = $slug;
	$result['tier'] = $tier;

	// ── 2. Resolve target site (Multisite subsite find-or-create; else current) ──
	$blog_id  = 0;
	$switched = false;
	if ( is_multisite() ) {
		$blog_id = tsa_provision_find_or_create_subsite(
			$slug, $name, sanitize_email( $answers['admin_email'] ?? get_option( 'admin_email' ) ), $result
		);
		if ( ! $blog_id ) {
			return $result; // error already recorded
		}
		switch_to_blog( $blog_id );
		$switched = true;
		// Hand seeding (clone-from-template vs from-scratch) to whatever is hooked.
		// Default: nothing — a blueprint clone is assumed to have seeded theme/plugins/content.
		do_action( 'tsa_provision_seed_subsite', $blog_id, $answers, $result );
	}
	$result['blog_id'] = (int) $blog_id;

	try {
		// ── 3a. Migrations (per-tenant, idempotent) ──
		if ( function_exists( 'tsa_run_migrations' ) ) {
			tsa_run_migrations();
			$result['steps'][] = 'Migrations run (db v' . get_option( 'tsa_platform_db_version', '0' ) . ').';
		}

		// ── 3b. Apply the tier (writes entitlements) ──
		$applied           = tsa_apply_tier( $tier );
		$result['steps'][] = sprintf( "Tier '%s' applied — %d features entitled.", $tier, count( $applied ) );

		// ── 3c. Record tenant identity ──
		update_option( 'tsa_tenant_identity', [
			'business_name' => $name,
			'slug'          => $slug,
			'store_type'    => $type,
			'tier'          => $tier,
			'provisioned'   => current_time( 'mysql' ),
		] );
		$result['steps'][] = 'Tenant identity recorded.';

		// ── 3d. Stamp the first store (reuse the proven engine) ──
		if ( $type === 'school' && function_exists( 'tsa_sb_build_school' ) ) {
			$build           = tsa_sb_build_school( tsa_provision_map_school_fields( $answers, $name, $slug ) );
			$result['verb']  = $build['verb'] ?? '';
			foreach ( (array) ( $build['steps'] ?? [] ) as $s ) {
				$result['steps'][] = wp_strip_all_tags( $s );
			}
			if ( ( $build['verb'] ?? '' ) === 'Error' ) {
				$result['errors'][] = 'First-store stamp failed.';
			}
		} else {
			$result['steps'][] = sprintf( "No stamp engine for '%s' yet — tier applied, first store skipped.", $type );
		}

		// ── 3e. Event toward the (future) control plane ──
		tsa_report_event( 'tenant.provisioned', [
			'slug'       => $slug,
			'tier'       => $tier,
			'store_type' => $type,
			'blog_id'    => (int) $blog_id,
		] );
		$result['steps'][] = "Event 'tenant.provisioned' fired.";

		$result['ok'] = empty( $result['errors'] );
	} catch ( \Throwable $e ) {
		$result['errors'][] = 'Exception: ' . $e->getMessage();
	} finally {
		if ( $switched ) {
			restore_current_blog();
		}
	}

	return $result;
}

/** Map buyer answers → the school stamp engine's $f shape (sanitized, with brand-guide-friendly defaults). */
function tsa_provision_map_school_fields( array $a, string $name, string $slug ): array {
	$hex = function ( $v, $d ) {
		$v = (string) $v;
		return preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ? $v : $d;
	};
	$status = $a['status'] ?? 'coming-soon';
	return [
		'name'          => $name,
		'slug'          => $slug,
		'mascot'        => sanitize_text_field( $a['mascot'] ?? '' ),
		'level'         => sanitize_text_field( $a['level'] ?? 'High Schools' ),
		'status'        => in_array( $status, [ 'live', 'coming-soon', 'hidden' ], true ) ? $status : 'coming-soon',
		'spotlight'     => ! empty( $a['spotlight'] ) ? 1 : 0,
		'primary'       => $hex( $a['primary'] ?? '', '#592C82' ),
		'secondary'     => $hex( $a['secondary'] ?? '', '#C7C9C8' ),
		'logo_id'       => absint( $a['logo_id'] ?? 0 ),
		'tagline'       => sanitize_text_field( $a['tagline'] ?? '' ),
		'pickups'       => sanitize_textarea_field( $a['pickups'] ?? '' ),
		'shipping'      => ! empty( $a['shipping'] ) ? 1 : 0,
		'contact_email' => sanitize_email( $a['contact_email'] ?? '' ),
		'programs'      => is_array( $a['programs'] ?? null ) ? $a['programs'] : [],
	];
}

/** Find (idempotent) or create a Multisite subsite for a tenant slug. Returns blog_id or 0 on failure. */
function tsa_provision_find_or_create_subsite( string $slug, string $name, string $admin_email, array &$result ): int {
	if ( ! is_multisite() ) {
		return 0;
	}

	$existing = get_id_from_blogname( $slug ); // handles subdir + subdomain installs
	if ( $existing ) {
		$result['steps'][] = "Subsite '{$slug}' already exists (#{$existing}) — reusing.";
		return (int) $existing;
	}

	if ( ! function_exists( 'wpmu_create_blog' ) ) {
		require_once ABSPATH . 'wp-admin/includes/ms.php';
	}
	$network = get_network();
	if ( ! $network ) {
		$result['errors'][] = 'No network context for subsite creation.';
		return 0;
	}

	if ( is_subdomain_install() ) {
		$domain = $slug . '.' . preg_replace( '#^www\.#', '', $network->domain );
		$path   = $network->path;
	} else {
		$domain = $network->domain;
		$path   = trailingslashit( $network->path . $slug );
	}

	$user_id = get_current_user_id();
	if ( ! $user_id && $admin_email ) {
		$u       = get_user_by( 'email', $admin_email );
		$user_id = $u ? $u->ID : 1;
	}
	$user_id = $user_id ?: 1;

	$blog_id = wpmu_create_blog( $domain, $path, $name, $user_id, [ 'public' => 1 ], $network->id );
	if ( is_wp_error( $blog_id ) ) {
		$result['errors'][] = 'wpmu_create_blog: ' . $blog_id->get_error_message();
		return 0;
	}
	$result['steps'][] = "Subsite '{$slug}' created (#{$blog_id}).";
	return (int) $blog_id;
}

/**
 * Reverse a provision so tests are repeatable (sub-project 2.3).
 *
 * Multisite: archives the subsite (or deletes it with delete_site).
 * Single-site / in-place: clears the tier/entitlements/identity (back to the
 * fail-open reference build); with purge_store also TRASHES (recoverable) the
 * store record + /schools/{slug}/ pages and deletes the design-store term.
 *
 * @param array $opts reset_tier(bool=true) purge_store(bool=false) delete_site(bool=false)
 * @return array ok, slug, steps[], errors[], multisite
 */
function tsa_deprovision_tenant( string $slug, array $opts = [] ): array {
	$slug   = sanitize_title( $slug );
	$result = [ 'ok' => false, 'slug' => $slug, 'steps' => [], 'errors' => [], 'multisite' => is_multisite() ];
	if ( ! $slug ) {
		$result['errors'][] = 'A slug is required.';
		return $result;
	}

	$reset_tier  = $opts['reset_tier']  ?? true;
	$purge_store = $opts['purge_store'] ?? false;
	$delete_site = $opts['delete_site'] ?? false;

	// ── Multisite: operate on the subsite, not the current site. ──
	if ( is_multisite() ) {
		$blog_id = get_id_from_blogname( $slug );
		if ( ! $blog_id ) {
			$result['errors'][] = "No subsite '{$slug}'.";
			return $result;
		}
		if ( ! function_exists( 'wpmu_delete_blog' ) ) {
			require_once ABSPATH . 'wp-admin/includes/ms.php';
		}
		if ( $delete_site ) {
			wpmu_delete_blog( $blog_id, true );
			$result['steps'][] = "Subsite '{$slug}' (#{$blog_id}) deleted.";
		} else {
			update_blog_status( $blog_id, 'archived', '1' );
			$result['steps'][] = "Subsite '{$slug}' (#{$blog_id}) archived (reversible).";
		}
		tsa_report_event( 'tenant.deprovisioned', [ 'slug' => $slug, 'blog_id' => (int) $blog_id, 'deleted' => (bool) $delete_site ] );
		$result['ok'] = true;
		return $result;
	}

	// ── Single-site / in-place. ──
	if ( $reset_tier ) {
		delete_option( 'tsa_entitlements' );
		delete_option( 'tsa_tier' );
		delete_option( 'tsa_tenant_identity' );
		$result['steps'][] = 'Tier + entitlements + identity cleared (back to fail-open reference build).';
	}
	if ( $purge_store ) {
		$sid = function_exists( 'tsa_sb_find_store_by_slug' ) ? tsa_sb_find_store_by_slug( $slug ) : 0;
		if ( $sid ) {
			wp_trash_post( $sid );
			$result['steps'][] = "Store record #{$sid} trashed.";
		}
		foreach ( [ "schools/{$slug}", "schools/{$slug}/programs", "schools/{$slug}/drops" ] as $path ) {
			$p = get_page_by_path( $path );
			if ( $p ) {
				wp_trash_post( $p->ID );
				$result['steps'][] = "Page /{$path}/ trashed.";
			}
		}
		if ( taxonomy_exists( 'tsa_design_store' ) ) {
			$t = get_term_by( 'slug', $slug, 'tsa_design_store' );
			if ( $t ) {
				wp_delete_term( $t->term_id, 'tsa_design_store' );
				$result['steps'][] = "Design-store term '{$slug}' deleted.";
			}
		}
		$del = get_option( 'tsa_store_delivery', [] );
		if ( is_array( $del ) && isset( $del[ $slug ] ) ) {
			unset( $del[ $slug ] );
			update_option( 'tsa_store_delivery', $del );
			$result['steps'][] = 'Cart/delivery routing removed.';
		}
	}
	if ( ! $result['steps'] ) {
		$result['steps'][] = 'Nothing to remove.';
	}
	tsa_report_event( 'tenant.deprovisioned', [ 'slug' => $slug, 'purge_store' => (bool) $purge_store ] );
	$result['ok'] = true;
	return $result;
}

/* ─────────────────────────────────────────────────────────────────
   HEADLESS TEST SURFACE — WP-CLI: `wp tsa provision --name=... --tier=...`
   Build-2.1 deliverable: provision a tenant from the shell, no UI yet
   (the network-admin wizard is sub-project 2.2).
───────────────────────────────────────────────────────────────── */
if ( defined( 'WP_CLI' ) && WP_CLI ) {
	WP_CLI::add_command( 'tsa provision', function ( $args, $assoc ) {
		$answers = [
			'business_name' => $assoc['name'] ?? '',
			'slug'          => $assoc['slug'] ?? '',
			'store_type'    => $assoc['type'] ?? 'school',
			'tier'          => $assoc['tier'] ?? 'pro',
			'mascot'        => $assoc['mascot'] ?? '',
			'level'         => $assoc['level'] ?? 'High Schools',
			'status'        => $assoc['status'] ?? 'coming-soon',
			'primary'       => $assoc['primary'] ?? '',
			'secondary'     => $assoc['secondary'] ?? '',
			'tagline'       => $assoc['tagline'] ?? '',
			'contact_email' => $assoc['email'] ?? '',
			'admin_email'   => $assoc['admin_email'] ?? '',
		];
		$r = tsa_provision_tenant( $answers );
		WP_CLI::log( sprintf( 'Tenant: %s · tier %s · blog #%d · %s',
			$r['slug'] ?: '(none)', $r['tier'] ?: '(none)', $r['blog_id'], $r['verb'] ?: '—' ) );
		foreach ( $r['steps'] as $s ) {
			WP_CLI::log( '  - ' . $s );
		}
		foreach ( $r['errors'] as $e ) {
			WP_CLI::warning( $e );
		}
		$r['ok'] ? WP_CLI::success( 'Provisioned.' ) : WP_CLI::error( 'Provisioning finished with errors.', false );
	} );

	// wp tsa deprovision --slug=dutchtown [--purge-store] [--delete-site] [--keep-tier]
	WP_CLI::add_command( 'tsa deprovision', function ( $args, $assoc ) {
		$r = tsa_deprovision_tenant( $assoc['slug'] ?? '', [
			'reset_tier'  => ! isset( $assoc['keep-tier'] ),
			'purge_store' => isset( $assoc['purge-store'] ),
			'delete_site' => isset( $assoc['delete-site'] ),
		] );
		foreach ( $r['steps'] as $s ) {
			WP_CLI::log( '  - ' . $s );
		}
		foreach ( $r['errors'] as $e ) {
			WP_CLI::warning( $e );
		}
		$r['ok'] ? WP_CLI::success( 'Deprovisioned.' ) : WP_CLI::error( 'Deprovision failed.', false );
	} );

	// wp tsa setup — one command: seed core pages → provision tenant → business profile.
	// Chains the whole customer stand-up. Add --skip-seed to provision an already-seeded site.
	WP_CLI::add_command( 'tsa setup', function ( $args, $assoc ) {
		// 1. Seed the core pages (unless the site is already seeded).
		if ( ! isset( $assoc['skip-seed'] ) && function_exists( 'tsa_seed_site' ) ) {
			WP_CLI::log( '== Seeding core pages ==' );
			$s = tsa_seed_site();
			foreach ( $s['steps'] as $line )  { WP_CLI::log( '  - ' . $line ); }
			foreach ( $s['errors'] as $line ) { WP_CLI::warning( $line ); }
			WP_CLI::log( sprintf( '  %d created, %d updated.', count( $s['created'] ), count( $s['updated'] ) ) );
		}

		// 2. Provision the tenant (tier + entitlements + first store).
		WP_CLI::log( '== Provisioning tenant ==' );
		$answers = [
			'business_name' => $assoc['name'] ?? '',
			'slug'          => $assoc['slug'] ?? '',
			'store_type'    => $assoc['type'] ?? 'school',
			'tier'          => $assoc['tier'] ?? 'pro',
			'mascot'        => $assoc['mascot'] ?? '',
			'level'         => $assoc['level'] ?? 'High Schools',
			'status'        => $assoc['status'] ?? 'coming-soon',
			'primary'       => $assoc['primary'] ?? '',
			'secondary'     => $assoc['secondary'] ?? '',
			'tagline'       => $assoc['tagline'] ?? '',
			'contact_email' => $assoc['email'] ?? '',
			'admin_email'   => $assoc['admin_email'] ?? '',
		];
		$r = tsa_provision_tenant( $answers );
		foreach ( $r['steps'] as $line )  { WP_CLI::log( '  - ' . $line ); }
		foreach ( $r['errors'] as $line ) { WP_CLI::warning( $line ); }

		// 3. Persist the business profile (identity shown on Contact/footer/policies).
		if ( function_exists( 'tsa_business_profile_ingest' ) ) {
			tsa_business_profile_ingest( [
				'biz_name'      => $assoc['name'] ?? '',
				'contact_email' => $assoc['email'] ?? '',
				'legal_name'    => $assoc['legal-name'] ?? '',
				'tagline'       => $assoc['tagline'] ?? '',
				'phone'         => $assoc['phone'] ?? '',
				'city'          => $assoc['city'] ?? '',
				'state'         => $assoc['state'] ?? '',
				'service_area'  => $assoc['service-area'] ?? '',
				'hours'         => $assoc['hours'] ?? '',
				'instagram'     => $assoc['instagram'] ?? '',
				'facebook'      => $assoc['facebook'] ?? '',
				'tiktok'        => $assoc['tiktok'] ?? '',
				'x'             => $assoc['x'] ?? '',
				'youtube'       => $assoc['youtube'] ?? '',
			] );
			WP_CLI::log( '  - Business profile saved.' );
		}

		WP_CLI::log( sprintf( 'Tenant: %s · tier %s · blog #%d · %s',
			$r['slug'] ?: '(none)', $r['tier'] ?: '(none)', $r['blog_id'], $r['verb'] ?: '—' ) );
		$r['ok'] ? WP_CLI::success( 'Setup complete. Next: branding, SMTP, form-email routing, then purge cache.' )
		         : WP_CLI::error( 'Setup finished with errors (see above).', false );
	} );
}

/* ─────────────────────────────────────────────────────────────────
   ONBOARDING WIZARD (sub-project 2.2) — admin UI over tsa_provision_tenant().
   Registered as a Platform submenu so it works on single-site today (the
   in-place test path). On Multisite move this to network_admin_menu /
   'manage_network' — a one-line change when the network is live.
───────────────────────────────────────────────────────────────── */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'tsa-platform', 'Provision Tenant', 'Provision Tenant',
		'manage_options', 'tsa-provision', 'tsa_prov_wizard_page'
	);
}, 20 );

function tsa_prov_wizard_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) return;

	$a       = [];
	$result  = null;
	$preview = false;

	if ( ! empty( $_POST['tsa_prov_action'] ) && check_admin_referer( 'tsa_prov', 'tsa_prov_nonce' ) ) {
		$a = [
			'business_name' => sanitize_text_field( wp_unslash( $_POST['biz_name'] ?? '' ) ),
			'slug'          => sanitize_title( wp_unslash( $_POST['slug'] ?? '' ) ),
			'store_type'    => sanitize_key( $_POST['store_type'] ?? 'school' ),
			'tier'          => sanitize_key( $_POST['tier'] ?? 'pro' ),
			'mascot'        => sanitize_text_field( wp_unslash( $_POST['mascot'] ?? '' ) ),
			'level'         => sanitize_text_field( wp_unslash( $_POST['level'] ?? 'High Schools' ) ),
			'status'        => sanitize_key( $_POST['status'] ?? 'coming-soon' ),
			'spotlight'     => ! empty( $_POST['spotlight'] ) ? 1 : 0,
			'primary'       => sanitize_text_field( wp_unslash( $_POST['primary'] ?? '' ) ),
			'secondary'     => sanitize_text_field( wp_unslash( $_POST['secondary'] ?? '' ) ),
			'tagline'       => sanitize_text_field( wp_unslash( $_POST['tagline'] ?? '' ) ),
			'pickups'       => sanitize_textarea_field( wp_unslash( $_POST['pickups'] ?? '' ) ),
			'shipping'      => ! empty( $_POST['shipping'] ) ? 1 : 0,
			'contact_email' => sanitize_email( wp_unslash( $_POST['contact_email'] ?? '' ) ),
			'admin_email'   => sanitize_email( wp_unslash( $_POST['admin_email'] ?? '' ) ),
			// Business Profile fields (persisted via tsa_business_profile_ingest on provision).
			'legal_name'    => sanitize_text_field( wp_unslash( $_POST['legal_name'] ?? '' ) ),
			'phone'         => sanitize_text_field( wp_unslash( $_POST['phone'] ?? '' ) ),
			'city'          => sanitize_text_field( wp_unslash( $_POST['city'] ?? '' ) ),
			'state'         => sanitize_text_field( wp_unslash( $_POST['state'] ?? '' ) ),
			'service_area'  => sanitize_text_field( wp_unslash( $_POST['service_area'] ?? '' ) ),
			'hours'         => sanitize_text_field( wp_unslash( $_POST['hours'] ?? '' ) ),
			'instagram'     => esc_url_raw( wp_unslash( $_POST['instagram'] ?? '' ) ),
			'facebook'      => esc_url_raw( wp_unslash( $_POST['facebook'] ?? '' ) ),
			'tiktok'        => esc_url_raw( wp_unslash( $_POST['tiktok'] ?? '' ) ),
			'x'             => esc_url_raw( wp_unslash( $_POST['x'] ?? '' ) ),
			'youtube'       => esc_url_raw( wp_unslash( $_POST['youtube'] ?? '' ) ),
		];
		if ( $_POST['tsa_prov_action'] === 'provision' ) {
			$result = tsa_provision_tenant( $a );
			// Fold the Business Profile into the same intake — persist its fields.
			// ($_POST passed raw; ingest wp_unslash()es each field itself.)
			if ( function_exists( 'tsa_business_profile_ingest' ) ) {
				tsa_business_profile_ingest( $_POST ); // phpcs:ignore WordPress.Security.NonceVerification
			}
		} else {
			$preview = true;
		}
	}

	$dep_result = null;
	if ( ! empty( $_POST['tsa_dep_action'] ) && check_admin_referer( 'tsa_dep', 'tsa_dep_nonce' ) ) {
		if ( ! empty( $_POST['tsa_dep_confirm'] ) ) {
			$dep_result = tsa_deprovision_tenant( sanitize_title( wp_unslash( $_POST['dep_slug'] ?? '' ) ), [
				'purge_store' => ! empty( $_POST['dep_purge_store'] ),
				'delete_site' => ! empty( $_POST['dep_delete_site'] ),
			] );
		} else {
			$dep_result = [ 'ok' => false, 'slug' => '', 'steps' => [], 'errors' => [ 'Tick the confirm box to deprovision.' ], 'multisite' => is_multisite() ];
		}
	}

	$val      = function ( $k, $d = '' ) use ( $a ) { return esc_attr( $a[ $k ] ?? $d ); };
	// Business Profile prefill: submitted value → saved profile → default.
	$bp       = function_exists( 'tsa_business_profile' ) ? tsa_business_profile() : [];
	$bval     = function ( $k, $d = '' ) use ( $a, $bp ) {
		return esc_attr( $a[ $k ] ?? ( $bp[ $k ] ?? $d ) );
	};
	$tiers    = tsa_tiers();
	$types    = tsa_store_types();
	$catalog  = tsa_feature_catalog();
	$sel_type = $a['store_type'] ?? 'school';
	$sel_tier = $a['tier'] ?? 'pro';
	?>
	<div class="wrap">
		<h1>Provision Tenant</h1>
		<p style="max-width:760px">Stamp a new customer from one form — applies the tier's entitlements and builds the first store. Idempotent: re-running the same slug updates rather than duplicates.<?php echo is_multisite() ? '' : ' <strong>Single-site:</strong> provisions in place (test mode); on Multisite it creates a subsite.'; ?></p>

		<?php if ( $result ) :
			$cls = $result['ok'] ? 'notice-success' : 'notice-error'; ?>
			<div class="notice <?php echo esc_attr( $cls ); ?>">
				<p><strong><?php echo $result['ok'] ? '&#10003; Provisioned' : '&#9888; Provisioning had errors'; ?></strong> — tenant <code><?php echo esc_html( $result['slug'] ); ?></code>, tier <code><?php echo esc_html( $result['tier'] ); ?></code><?php echo $result['blog_id'] ? ', blog #' . (int) $result['blog_id'] : ' (in place)'; ?>.</p>
				<ul style="list-style:disc;margin-left:1.5em">
					<?php foreach ( $result['steps'] as $s ) echo '<li>' . esc_html( $s ) . '</li>'; ?>
					<?php foreach ( $result['errors'] as $e ) echo '<li style="color:#b32d2e">' . esc_html( $e ) . '</li>'; ?>
				</ul>
				<?php if ( $result['ok'] && $result['slug'] && ! $result['blog_id'] ) : ?>
					<p><a class="button" href="<?php echo esc_url( home_url( '/schools/' . $result['slug'] . '/' ) ); ?>" target="_blank">View /schools/<?php echo esc_html( $result['slug'] ); ?>/ &#8599;</a>
					<a class="button" href="<?php echo esc_url( admin_url( 'admin.php?page=tsa-platform' ) ); ?>">Open Platform (verify tier)</a></p>
				<?php endif; ?>
			</div>
		<?php endif; ?>

		<?php if ( $preview ) : ?>
			<div class="notice notice-info">
				<p><strong>Preview</strong> — nothing created yet. Review, then click <em>Provision</em>.</p>
				<p><strong><?php echo esc_html( $a['business_name'] ); ?></strong> (slug <code><?php echo esc_html( $a['slug'] ); ?></code>) &middot;
					<?php echo esc_html( $types[ $sel_type ]['label'] ?? $sel_type ); ?> store &middot;
					tier <strong><?php echo esc_html( $tiers[ $sel_tier ]['name'] ?? $sel_tier ); ?></strong> ($<?php echo esc_html( $tiers[ $sel_tier ]['price'] ?? '?' ); ?>/mo)</p>
				<p style="font-size:12px;color:#555">Entitles: <?php echo esc_html( implode( ', ', $tiers[ $sel_tier ]['features'] ?? [] ) ); ?></p>
				<p style="font-size:12px;color:#555">Creates: store record, /schools/<?php echo esc_html( $a['slug'] ); ?>/ landing, design-store term, cart routing + delivery, programs/drops pages (if any).</p>
			</div>
		<?php endif; ?>

		<form method="post">
			<?php wp_nonce_field( 'tsa_prov', 'tsa_prov_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="biz_name">Business name</label></th>
					<td><input name="biz_name" id="biz_name" type="text" class="regular-text" value="<?php echo $val( 'business_name' ); ?>" required>
					<p class="description">e.g. Dutchtown High School</p></td></tr>
				<tr><th><label for="slug">Slug</label></th>
					<td><input name="slug" id="slug" type="text" class="regular-text" value="<?php echo $val( 'slug' ); ?>">
					<p class="description">URL key. Leave blank to auto-generate from the name.</p></td></tr>
				<tr><th>Store type</th>
					<td><?php foreach ( $types as $tk => $tv ) :
						$feat    = $tv['feature'] ?? '';
						$life    = $catalog[ $feat ]['lifecycle'] ?? 'ga';
						$planned = in_array( $life, [ 'planned', 'deprecated' ], true ); ?>
						<label style="margin-right:1.2em"><input type="radio" name="store_type" value="<?php echo esc_attr( $tk ); ?>" <?php checked( $sel_type, $tk ); disabled( $planned ); ?>> <?php echo esc_html( $tv['label'] ); ?><?php echo $planned ? ' <em>(soon)</em>' : ''; ?></label>
					<?php endforeach; ?></td></tr>
				<tr><th>Tier</th>
					<td><?php foreach ( $tiers as $sk => $sv ) : ?>
						<label style="display:block;margin:.2em 0"><input type="radio" name="tier" value="<?php echo esc_attr( $sk ); ?>" <?php checked( $sel_tier, $sk ); ?>> <strong><?php echo esc_html( $sv['name'] ); ?></strong> — $<?php echo esc_html( $sv['price'] ); ?>/mo <span style="color:#777;font-size:12px">(<?php echo count( $sv['features'] ); ?> features)</span></label>
					<?php endforeach; ?></td></tr>
				<tr><th><label for="mascot">Mascot</label></th><td><input name="mascot" id="mascot" type="text" class="regular-text" value="<?php echo $val( 'mascot' ); ?>"></td></tr>
				<tr><th>Brand colors</th>
					<td>Primary <input name="primary" type="text" value="<?php echo $val( 'primary', '#592C82' ); ?>" placeholder="#592C82" style="width:110px">
					&nbsp; Secondary <input name="secondary" type="text" value="<?php echo $val( 'secondary', '#C7C9C8' ); ?>" placeholder="#C7C9C8" style="width:110px"></td></tr>
				<tr><th><label for="tagline">Tagline</label></th><td><input name="tagline" id="tagline" type="text" class="regular-text" value="<?php echo $val( 'tagline' ); ?>"></td></tr>
				<tr><th><label for="pickups">Pickup locations</label></th>
					<td><textarea name="pickups" id="pickups" rows="3" class="large-text"><?php echo esc_textarea( $a['pickups'] ?? '' ); ?></textarea>
					<p class="description">One per line. <label style="margin-left:.5em"><input type="checkbox" name="shipping" value="1" <?php checked( ! empty( $a['shipping'] ) ); ?>> also offer shipping</label></p></td></tr>
				<tr><th><label for="contact_email">Contact email</label></th><td><input name="contact_email" id="contact_email" type="email" class="regular-text" value="<?php echo $val( 'contact_email' ); ?>"></td></tr>

				<tr><td colspan="2" style="padding-top:1.4em"><h2 style="margin:0 0 .2em">Business profile</h2><p class="description" style="margin:0">Used across the Contact page, footer &amp; policies. Fill in once here and the seeded site shows this customer's details.</p></td></tr>
				<tr><th><label for="legal_name">Legal name</label></th><td><input name="legal_name" id="legal_name" type="text" class="regular-text" value="<?php echo $bval( 'legal_name' ); ?>"><p class="description">Full legal entity name (for policies/terms). Display name comes from the business name above.</p></td></tr>
				<tr><th><label for="phone">Phone</label></th><td><input name="phone" id="phone" type="text" class="regular-text" value="<?php echo $bval( 'phone' ); ?>"></td></tr>
				<tr><th>Location</th><td>City <input name="city" type="text" value="<?php echo $bval( 'city' ); ?>" style="width:180px"> &nbsp; State <input name="state" type="text" value="<?php echo $bval( 'state' ); ?>" style="width:90px"></td></tr>
				<tr><th><label for="service_area">Service area</label></th><td><input name="service_area" id="service_area" type="text" class="regular-text" value="<?php echo $bval( 'service_area' ); ?>" placeholder="e.g. Serving Ascension Parish &amp; nationwide"></td></tr>
				<tr><th><label for="hours">Hours</label></th><td><input name="hours" id="hours" type="text" class="regular-text" value="<?php echo $bval( 'hours' ); ?>" placeholder="e.g. Mon–Fri 9–5"></td></tr>
				<tr><th>Social links</th><td>
					<input name="instagram" type="url" value="<?php echo $bval( 'instagram' ); ?>" placeholder="Instagram URL" class="regular-text" style="margin-bottom:4px"><br>
					<input name="facebook" type="url" value="<?php echo $bval( 'facebook' ); ?>" placeholder="Facebook URL" class="regular-text" style="margin-bottom:4px"><br>
					<input name="tiktok" type="url" value="<?php echo $bval( 'tiktok' ); ?>" placeholder="TikTok URL" class="regular-text" style="margin-bottom:4px"><br>
					<input name="x" type="url" value="<?php echo $bval( 'x' ); ?>" placeholder="X / Twitter URL" class="regular-text" style="margin-bottom:4px"><br>
					<input name="youtube" type="url" value="<?php echo $bval( 'youtube' ); ?>" placeholder="YouTube URL" class="regular-text">
					<p class="description">Only the links you set appear in the footer.</p></td></tr>

				<tr><th>Status</th>
					<td><?php $st = $a['status'] ?? 'coming-soon'; foreach ( [ 'live' => 'Live', 'coming-soon' => 'Coming soon', 'hidden' => 'Hidden' ] as $sv2 => $lbl ) : ?>
						<label style="margin-right:1em"><input type="radio" name="status" value="<?php echo esc_attr( $sv2 ); ?>" <?php checked( $st, $sv2 ); ?>> <?php echo esc_html( $lbl ); ?></label>
					<?php endforeach; ?>
					&nbsp; <label><input type="checkbox" name="spotlight" value="1" <?php checked( ! empty( $a['spotlight'] ) ); ?>> Spotlight on homepage</label></td></tr>
				<?php if ( is_multisite() ) : ?>
				<tr><th><label for="admin_email">Subsite admin email</label></th><td><input name="admin_email" id="admin_email" type="email" class="regular-text" value="<?php echo $val( 'admin_email' ); ?>"></td></tr>
				<?php endif; ?>
			</table>
			<p class="submit">
				<button class="button" name="tsa_prov_action" value="preview">Preview</button>
				<button class="button button-primary" name="tsa_prov_action" value="provision">Provision</button>
			</p>
		</form>

		<hr style="margin:2em 0">
		<h2 style="color:#b32d2e">Danger zone — deprovision / reset</h2>
		<p style="max-width:760px">Reverse a provision so tests are repeatable. <?php echo is_multisite()
			? 'Archives the subsite (or deletes it, with the box below).'
			: 'Clears the tier/entitlements/identity (back to the fail-open reference build); tick <em>purge store</em> to also trash the store record, <code>/schools/{slug}/</code> pages, and the design-store term (recoverable from Trash).'; ?></p>
		<?php if ( $dep_result ) : ?>
			<div class="notice <?php echo $dep_result['ok'] ? 'notice-success' : 'notice-error'; ?>">
				<?php if ( $dep_result['steps'] ) : ?><ul style="list-style:disc;margin-left:1.5em"><?php foreach ( $dep_result['steps'] as $s ) echo '<li>' . esc_html( $s ) . '</li>'; ?></ul><?php endif; ?>
				<?php foreach ( $dep_result['errors'] as $e ) echo '<p style="color:#b32d2e">' . esc_html( $e ) . '</p>'; ?>
			</div>
		<?php endif; ?>
		<form method="post" onsubmit="return confirm('Deprovision this tenant? Records are trashed/archived (recoverable).');">
			<?php wp_nonce_field( 'tsa_dep', 'tsa_dep_nonce' ); ?>
			<table class="form-table" role="presentation">
				<tr><th><label for="dep_slug">Tenant slug</label></th><td><input name="dep_slug" id="dep_slug" type="text" class="regular-text" required></td></tr>
				<tr><th>Options</th><td>
					<?php if ( is_multisite() ) : ?>
						<label><input type="checkbox" name="dep_delete_site" value="1"> Delete subsite (otherwise archive)</label>
					<?php else : ?>
						<label><input type="checkbox" name="dep_purge_store" value="1"> Also purge store record + pages + term (to Trash)</label>
					<?php endif; ?>
				</td></tr>
				<tr><th>Confirm</th><td><label><input type="checkbox" name="tsa_dep_confirm" value="1"> Yes, deprovision this tenant</label></td></tr>
			</table>
			<p class="submit"><button class="button" style="border-color:#b32d2e;color:#b32d2e" name="tsa_dep_action" value="deprovision">Deprovision</button></p>
		</form>
		<script>
		(function(){
			var n=document.getElementById('biz_name'), s=document.getElementById('slug'), touched=false;
			if(!n||!s) return;
			s.addEventListener('input',function(){ touched=true; });
			n.addEventListener('input',function(){
				if(touched && s.value) return;
				s.value = n.value.toLowerCase().replace(/\b(high|middle|elementary|primary|school|of|and|the)\b/g,' ').replace(/[^a-z0-9]+/g,'-').replace(/^-+|-+$/g,'');
			});
		})();
		</script>
	</div>
	<?php
}
