<?php
/**
 * TSA Business Profile — one place that answers "who is this customer?".
 *
 * The branding layer (inc/branding.php) makes NAME / LOGO / COLORS config-driven,
 * but the customer's business details (contact email, phone, location, service
 * area, hours, socials) were hardcoded across the chrome templates — so a cloned
 * + seeded tenant still showed the origin shop's info until each file was edited.
 *
 * This stores those details in the `tsa_business_profile` option and exposes a
 * single getter, tsa_biz($key), that the templates read. Values fall back
 * gracefully: name → branding → tenant identity → site title; email → the
 * routed contact recipient. UNSET fields keep the reference build's defaults,
 * so adding this file changes nothing until the profile is filled in.
 *
 * Admin: Platform → Business Profile (also prefilled/kept in sync with the
 * provisioning wizard via the tsa_platform_event hook).
 */
defined( 'ABSPATH' ) || exit;

/** The profile, merged with defaults. */
function tsa_business_profile(): array {
	$d = [
		'display_name' => '', 'legal_name' => '', 'tagline' => '',
		'email'        => '', 'phone'      => '',
		'city'         => '', 'state'      => '', 'service_area' => '', 'hours' => '',
		'instagram'    => '', 'facebook'   => '', 'tiktok' => '', 'x' => '', 'youtube' => '',
	];
	$o = get_option( 'tsa_business_profile', [] );
	return array_merge( $d, is_array( $o ) ? $o : [] );
}

/**
 * Single getter for template use. Special keys resolve smart fallbacks:
 *   name     → display_name → tsa_brand_name() → site title
 *   email    → profile email → routed contact recipient → admin email
 *   location → "City, State" (blank if neither set)
 * All other keys return the stored value, or $default when empty.
 */
function tsa_biz( string $key, string $default = '' ): string {
	$p = tsa_business_profile();
	switch ( $key ) {
		case 'name':
			if ( $p['display_name'] !== '' ) { return $p['display_name']; }
			if ( function_exists( 'tsa_brand_name' ) ) { return tsa_brand_name(); }
			return (string) get_option( 'blogname' );
		case 'email':
			if ( $p['email'] !== '' ) { return $p['email']; }
			$r = function_exists( 'tsa_form_recipient' ) ? tsa_form_recipient( 'contact' ) : get_option( 'admin_email' );
			if ( is_array( $r ) ) { $r = reset( $r ); }
			return (string) $r;
		case 'location':
			$loc = trim( $p['city'] . ( ( $p['city'] && $p['state'] ) ? ', ' : '' ) . $p['state'] );
			return $loc !== '' ? $loc : $default;
		default:
			return ( isset( $p[ $key ] ) && $p[ $key ] !== '' ) ? (string) $p[ $key ] : $default;
	}
}

/* ─── Keep in sync with provisioning: adopt the business name on provision. ── */
add_action( 'tsa_platform_event', function ( $type, $data ) {
	if ( 'tenant.provisioned' !== $type ) { return; }
	$profile = get_option( 'tsa_business_profile', [] );
	$profile = is_array( $profile ) ? $profile : [];
	if ( empty( $profile['display_name'] ) ) {
		$id = get_option( 'tsa_tenant_identity', [] );
		if ( is_array( $id ) && ! empty( $id['business_name'] ) ) {
			$profile['display_name'] = sanitize_text_field( $id['business_name'] );
			update_option( 'tsa_business_profile', $profile );
		}
	}
}, 10, 2 );

/* ─── Admin: Platform → Business Profile ───────────────────────────── */
add_action( 'admin_menu', function () {
	add_submenu_page(
		'tsa-platform', 'Business Profile', 'Business Profile',
		'manage_options', 'tsa-business-profile', 'tsa_biz_admin_page'
	);
}, 22 );

function tsa_biz_admin_page(): void {
	if ( ! current_user_can( 'manage_options' ) ) { return; }
	$saved = false;

	$fields = [
		'display_name' => [ 'Display name', 'text',     'What the site calls the business (falls back to your Branding name).' ],
		'legal_name'   => [ 'Legal name',   'text',     'Full legal entity name, for policies/terms.' ],
		'tagline'      => [ 'Tagline',       'text',     'Short line under the footer logo.' ],
		'email'        => [ 'Public email',  'email',    'Shown on Contact (falls back to your routed contact recipient).' ],
		'phone'        => [ 'Phone',         'text',     'Shown on Contact when set.' ],
		'city'         => [ 'City',          'text',     '' ],
		'state'        => [ 'State',         'text',     '' ],
		'service_area' => [ 'Service area',  'text',     'e.g. "Serving Ascension Parish & nationwide".' ],
		'hours'        => [ 'Hours',         'text',     'e.g. "Mon–Fri 9–5".' ],
		'instagram'    => [ 'Instagram URL', 'url',      '' ],
		'facebook'     => [ 'Facebook URL',  'url',      '' ],
		'tiktok'       => [ 'TikTok URL',    'url',      '' ],
		'x'            => [ 'X / Twitter URL','url',     '' ],
		'youtube'      => [ 'YouTube URL',   'url',      '' ],
	];

	if ( ! empty( $_POST['tsa_biz_save'] ) && check_admin_referer( 'tsa_biz', 'tsa_biz_nonce' ) ) {
		$profile = [];
		foreach ( $fields as $k => $f ) {
			$raw = wp_unslash( $_POST[ $k ] ?? '' );
			$profile[ $k ] = 'email' === $f[1] ? sanitize_email( $raw )
				: ( 'url' === $f[1] ? esc_url_raw( $raw ) : sanitize_text_field( $raw ) );
		}
		update_option( 'tsa_business_profile', $profile );
		$saved = true;
	}

	$p = tsa_business_profile();
	?>
	<div class="wrap">
		<h1>Business Profile</h1>
		<p style="max-width:760px;color:#50575e">Answer these once and the Contact page, footer, and policies use them automatically — so a cloned/seeded site shows this customer's details instead of hardcoded placeholders. Leave a field blank to keep the theme's default.</p>
		<?php if ( $saved ) : ?><div class="notice notice-success is-dismissible"><p>Business profile saved.</p></div><?php endif; ?>
		<form method="post">
			<?php wp_nonce_field( 'tsa_biz', 'tsa_biz_nonce' ); ?>
			<table class="form-table" role="presentation"><tbody>
				<?php foreach ( $fields as $k => $f ) : ?>
					<tr>
						<th scope="row"><label for="tsa-biz-<?php echo esc_attr( $k ); ?>"><?php echo esc_html( $f[0] ); ?></label></th>
						<td>
							<input type="<?php echo esc_attr( 'email' === $f[1] || 'url' === $f[1] ? $f[1] : 'text' ); ?>"
							       id="tsa-biz-<?php echo esc_attr( $k ); ?>" name="<?php echo esc_attr( $k ); ?>"
							       class="regular-text" value="<?php echo esc_attr( $p[ $k ] ); ?>">
							<?php if ( $f[2] ) : ?><p class="description"><?php echo esc_html( $f[2] ); ?></p><?php endif; ?>
						</td>
					</tr>
				<?php endforeach; ?>
			</tbody></table>
			<p style="color:#666;font-size:12px">Effective name now: <strong><?php echo esc_html( tsa_biz( 'name' ) ); ?></strong> · Effective email: <strong><?php echo esc_html( tsa_biz( 'email' ) ); ?></strong></p>
			<p class="submit"><button type="submit" name="tsa_biz_save" value="1" class="button button-primary">Save profile</button></p>
		</form>
	</div>
	<?php
}
