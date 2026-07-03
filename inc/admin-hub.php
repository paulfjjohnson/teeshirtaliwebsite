<?php
/**
 * TSA Command Center — front-end admin launcher.
 *
 * Extends the owner Dashboard (template-dashboard.php) into a hub that links to
 * every TSA admin tool, grouped and branded. Plus a discreet owner-only footer
 * icon that opens the hub. Security is the real WP gate (tsa_dash_can():
 * logged-in + manage_woocommerce) — the icon just renders for owners only, so
 * it's invisible to the public. This file adds NO new public surface.
 */

defined( 'ABSPATH' ) || exit;

/**
 * The tool catalog. Filterable so any module can register its own card:
 *   add_filter( 'tsa_admin_hub_tools', fn($g)=>$g );
 * Each tool: [ label, description, emoji, admin URL ].
 */
function tsa_admin_hub_tools(): array {
	$tools = [
		'Orders & Ops' => [
			[ 'Orders',        'View & manage all orders',                 '🧾', admin_url( 'admin.php?page=wc-orders' ) ],
			[ 'Requests',      'Quote, contact, store & party requests',   '📥', admin_url( 'edit.php?post_type=tsa_request' ) ],
			[ 'Reports',       'Sales, store & savings reports',           '📊', admin_url( 'admin.php?page=tsa-reports' ) ],
		],
		'Stores' => [
			[ 'Stores',        'School / team / business store records',   '🏬', admin_url( 'edit.php?post_type=configurator_store' ) ],
			[ 'Store Builder', 'Provision a new store + its pages',         '🏗️', admin_url( 'admin.php?page=tsa-store-builder' ) ],
			[ 'Store Settings','Cart routing & per-store delivery',         '⚙️', admin_url( 'options-general.php?page=tsa-stores' ) ],
		],
		'Drops & Marketing' => [
			[ 'Tee Parties',   'Timed design drops (party pages)',         '🎉', admin_url( 'edit.php?post_type=page' ) ],
			[ 'Alerts',        'SMS / drop-alert settings',                '🔔', admin_url( 'options-general.php?page=tsa-alerts' ) ],
			[ 'Ambassadors',   'Ambassador program',                       '🤝', admin_url( 'edit.php?post_type=tsa_ambassador' ) ],
			[ 'QR Codes',      'Ambassador QR generator',                  '🔳', admin_url( 'edit.php?post_type=tsa_ambassador&page=tsa-qr-codes' ) ],
			[ 'Fundraisers',   'Fundraiser campaigns',                     '❤️', admin_url( 'edit.php?post_type=tsa_fundraiser' ) ],
		],
		'Catalog' => [
			[ 'Designs',       'Design library (art catalog)',            '🎨', admin_url( 'edit.php?post_type=tsa_design' ) ],
			[ 'Blank Apparel', 'Blank garment catalog + S&S import',      '👕', admin_url( 'edit.php?post_type=tsa_brand' ) ],
		],
		'Settings' => [
			[ 'Platform',      'Feature toggles & provisioning',          '🧩', admin_url( 'admin.php?page=tsa-platform' ) ],
			[ 'Branding',      'Tenant branding',                         '🖌️', admin_url( 'admin.php?page=tsa-branding' ) ],
			[ 'Form Emails',   'Where form submissions are routed',       '✉️', admin_url( 'options-general.php?page=tsa-form-emails' ) ],
			[ 'Help',          'TSA documentation',                       '🆘', admin_url( 'admin.php?page=tsa-help' ) ],
		],
	];
	return (array) apply_filters( 'tsa_admin_hub_tools', $tools );
}

/** Render the launcher grid. Called from the Dashboard template. Owner-only. */
function tsa_admin_hub_launcher(): void {
	if ( ! function_exists( 'tsa_dash_can' ) || ! tsa_dash_can() ) return;
	?>
	<style>
	.tsa-hub{margin:24px 0 30px;padding:22px 24px 24px;border-radius:16px;background:rgba(255,255,255,.025);border:1px solid var(--tsa-line,rgba(255,255,255,.1))}
	.tsa-hub__head{margin-bottom:14px}
	.tsa-hub__title{font-size:20px;font-weight:800;margin:0;color:var(--tsa-text,#f5f5f5)}
	.tsa-hub__sub{margin:2px 0 0;font-size:13px;color:var(--tsa-muted,#8a8a8a)}
	.tsa-hub__group{margin-top:22px}
	.tsa-hub__group-label{font-size:11px;font-weight:800;letter-spacing:.14em;text-transform:uppercase;color:var(--tsa-muted,#8a8a8a);margin-bottom:10px}
	.tsa-hub__grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(210px,1fr));gap:12px}
	.tsa-hub__card{display:flex;align-items:center;gap:12px;padding:13px 14px;border-radius:12px;background:rgba(255,255,255,.06);border:1px solid var(--tsa-line,rgba(255,255,255,.1));text-decoration:none;transition:transform .12s,border-color .12s,background .12s}
	.tsa-hub__card:hover{transform:translateY(-2px);border-color:var(--tsa-gold,#d8a85f);background:rgba(255,255,255,.07)}
	.tsa-hub__icon{font-size:22px;line-height:1;flex-shrink:0}
	.tsa-hub__card-title{display:block;font-size:14px;font-weight:700;color:var(--tsa-text,#f5f5f5)}
	.tsa-hub__card-desc{display:block;font-size:12px;color:var(--tsa-muted,#8a8a8a);margin-top:1px;line-height:1.3}
	</style>
	<div class="tsa-hub">
		<div class="tsa-hub__head">
			<h2 class="tsa-hub__title">Command Center</h2>
			<p class="tsa-hub__sub">Every TSA tool, one place. Opens in the admin.</p>
		</div>
		<?php foreach ( tsa_admin_hub_tools() as $group => $tools ) :
			if ( empty( $tools ) ) continue; ?>
			<div class="tsa-hub__group">
				<div class="tsa-hub__group-label"><?php echo esc_html( $group ); ?></div>
				<div class="tsa-hub__grid">
					<?php foreach ( $tools as $t ) :
						$label = $t[0] ?? ''; $desc = $t[1] ?? ''; $icon = $t[2] ?? '•'; $url = $t[3] ?? '#'; ?>
					<a class="tsa-hub__card" href="<?php echo esc_url( $url ); ?>">
						<span class="tsa-hub__icon"><?php echo esc_html( $icon ); ?></span>
						<span class="tsa-hub__card-body">
							<span class="tsa-hub__card-title"><?php echo esc_html( $label ); ?></span>
							<span class="tsa-hub__card-desc"><?php echo esc_html( $desc ); ?></span>
						</span>
					</a>
					<?php endforeach; ?>
				</div>
			</div>
		<?php endforeach; ?>
	</div>
	<?php
}

/** Discreet owner-only footer icon → the Command Center. Invisible to everyone else. */
add_action( 'wp_footer', function () {
	if ( ! function_exists( 'tsa_dash_can' ) || ! tsa_dash_can() ) return;
	if ( ! function_exists( 'tsa_dash_page_id' ) || ! tsa_dash_page_id() ) return;
	if ( is_page_template( 'template-dashboard.php' ) ) return; // already on the hub
	$url = get_permalink( tsa_dash_page_id() );
	if ( ! $url ) return;
	?>
	<a href="<?php echo esc_url( $url ); ?>" class="tsa-hub-fab" title="TSA Command Center" aria-label="TSA Command Center">&#9881;</a>
	<style>
	.tsa-hub-fab{position:fixed;bottom:16px;left:16px;z-index:9998;width:34px;height:34px;border-radius:50%;background:rgba(18,18,20,.55);color:rgba(255,255,255,.6);display:flex;align-items:center;justify-content:center;font-size:16px;text-decoration:none;border:1px solid rgba(255,255,255,.14);opacity:.35;transition:opacity .2s,background .2s,color .2s}
	.tsa-hub-fab:hover{opacity:1;background:rgba(18,18,20,.95);color:#fff}
	</style>
	<?php
}, 99 );
