<?php
/**
 * TSA / Parabellum Tenant Branding layer (white-label, Track 1).
 *
 * Makes the site's brand CONFIG-DRIVEN so the same codebase renders as a
 * different brand per tenant — the prerequisite for testing a domain rebrand.
 *
 *   - Stores brand name, logo, and colors in the `tsa_branding` option.
 *   - Front end: overrides the design tokens (--tsa-pink/-rose/-gold + derived
 *     shades) in wp_head, filters the site title, and swaps the custom logo.
 *   - Admin: Platform -> Branding screen.
 *   - "Hide platform credit" is gated behind the white_label feature.
 *
 * UNSET = TSA reference build (no overrides emitted). Set a value to rebrand.
 */
defined( 'ABSPATH' ) || exit;

/** Branding config merged with defaults. Empty fields => fall back to the TSA reference look. */
function tsa_branding(): array {
    $d = [
        'brand_name'  => '',
        'logo_url'    => '',
        'primary'     => '', // -> --tsa-pink   (primary / blush)
        'accent'      => '', // -> --tsa-gold   (premium accent)
        'rose'        => '', // -> --tsa-rose   (links / accents)
        'hide_credit' => 0,
    ];
    $o = get_option( 'tsa_branding', [] );
    return array_merge( $d, is_array( $o ) ? $o : [] );
}

/** Effective brand name: branding override -> provisioned tenant identity -> raw site title. */
function tsa_brand_name(): string {
    $b = tsa_branding();
    if ( $b['brand_name'] !== '' ) return $b['brand_name'];
    $id = get_option( 'tsa_tenant_identity', [] );
    if ( is_array( $id ) && ! empty( $id['business_name'] ) ) return $id['business_name'];
    return (string) get_option( 'blogname' ); // raw option (unfiltered) — avoids recursion
}

function tsa_brand_logo_url(): string { return (string) tsa_branding()['logo_url']; }

/** Hide the platform/credit branding — only honored when the white_label feature is active. */
function tsa_brand_hide_credit(): bool {
    $b = tsa_branding();
    if ( empty( $b['hide_credit'] ) ) return false;
    return function_exists( 'tsa_feature_active' ) ? tsa_feature_active( 'white_label' ) : true;
}

/* ── hex helpers (derive coherent shades from the 3 brand colors) ── */
function tsa_hex_rgb( $hex ) {
    $hex = ltrim( (string) $hex, '#' );
    if ( strlen( $hex ) !== 6 ) return null;
    return [ hexdec( substr( $hex, 0, 2 ) ), hexdec( substr( $hex, 2, 2 ) ), hexdec( substr( $hex, 4, 2 ) ) ];
}
function tsa_hex_mix( $hex, $target, $t ) {
    $a = tsa_hex_rgb( $hex ); $b = tsa_hex_rgb( $target );
    if ( ! $a || ! $b ) return $hex;
    $mix = array_map( function ( $x, $y ) use ( $t ) { return (int) round( $x + ( $y - $x ) * $t ); }, $a, $b );
    return sprintf( '#%02x%02x%02x', $mix[0], $mix[1], $mix[2] );
}
function tsa_lighten( $hex, $t ) { return tsa_hex_mix( $hex, '#ffffff', $t ); }
function tsa_darken( $hex, $t ) { return tsa_hex_mix( $hex, '#000000', $t ); }

/** Front end: emit token overrides from the branding config (after the design system @ pri 25). */
add_action( 'wp_head', function () {
    $b     = tsa_branding();
    $valid = function ( $h ) { return preg_match( '/^#[0-9a-fA-F]{6}$/', (string) $h ); };
    $css   = [];
    if ( $valid( $b['primary'] ) ) {
        $css[] = '--tsa-pink:' . $b['primary'];
        $css[] = '--tsa-pink-soft:' . tsa_lighten( $b['primary'], 0.88 );
        $css[] = '--tsa-pink-deep:' . tsa_darken( $b['primary'], 0.12 );
    }
    if ( $valid( $b['rose'] ) ) {
        $css[] = '--tsa-rose:' . $b['rose'];
        $css[] = '--tsa-rose-text:' . tsa_darken( $b['rose'], 0.15 );
    }
    if ( $valid( $b['accent'] ) ) {
        $css[] = '--tsa-gold:' . $b['accent'];
        $css[] = '--tsa-gold-soft:' . tsa_lighten( $b['accent'], 0.60 );
    }
    if ( $css ) {
        echo "\n<style id=\"tsa-branding\">:root{" . implode( ';', $css ) . ";}</style>\n";
    }
}, 99 );

/** Brand name: filter the site title so get_bloginfo('name') / <title> reflect the tenant. */
add_filter( 'option_blogname', function ( $name ) {
    $b = tsa_branding();
    return $b['brand_name'] !== '' ? $b['brand_name'] : $name;
} );

/** Brand logo: swap the WP custom-logo markup when a tenant logo URL is set. */
add_filter( 'get_custom_logo', function ( $html ) {
    $url = tsa_brand_logo_url();
    if ( ! $url ) return $html;
    return '<a href="' . esc_url( home_url( '/' ) ) . '" class="custom-logo-link tsa-brand-logo" rel="home">'
         . '<img class="custom-logo" src="' . esc_url( $url ) . '" alt="' . esc_attr( tsa_brand_name() ) . '" /></a>';
}, 99 );

/* ─────────────────────────────────────────────────────────────────
   ADMIN — Platform -> Branding
───────────────────────────────────────────────────────────────── */
add_action( 'admin_menu', function () {
    add_submenu_page( 'tsa-platform', 'Branding', 'Branding', 'manage_options', 'tsa-branding', 'tsa_branding_page' );
}, 21 );

function tsa_branding_page(): void {
    if ( ! current_user_can( 'manage_options' ) ) return;

    if ( ! empty( $_POST['tsa_branding_reset'] ) && check_admin_referer( 'tsa_branding', 'tsa_branding_nonce' ) ) {
        delete_option( 'tsa_branding' );
        echo '<div class="notice notice-success is-dismissible"><p>Branding reset to the TSA default.</p></div>';
    } elseif ( ! empty( $_POST['tsa_branding_save'] ) && check_admin_referer( 'tsa_branding', 'tsa_branding_nonce' ) ) {
        $hex = function ( $v ) { $v = sanitize_text_field( wp_unslash( $v ) ); return preg_match( '/^#[0-9a-fA-F]{6}$/', $v ) ? $v : ''; };
        update_option( 'tsa_branding', [
            'brand_name'  => sanitize_text_field( wp_unslash( $_POST['brand_name'] ?? '' ) ),
            'logo_url'    => esc_url_raw( wp_unslash( $_POST['logo_url'] ?? '' ) ),
            'primary'     => $hex( $_POST['primary'] ?? '' ),
            'accent'      => $hex( $_POST['accent'] ?? '' ),
            'rose'        => $hex( $_POST['rose'] ?? '' ),
            'hide_credit' => ! empty( $_POST['hide_credit'] ) ? 1 : 0,
        ] );
        echo '<div class="notice notice-success is-dismissible"><p>Branding saved. View the site (purge cache) to see the rebrand.</p></div>';
    }

    $b  = tsa_branding();
    $wl = function_exists( 'tsa_feature_active' ) ? tsa_feature_active( 'white_label' ) : true;
    $cv = function ( $v, $d ) { return esc_attr( $v !== '' ? $v : $d ); }; // color value or TSA default
    ?>
    <div class="wrap">
        <h1>Branding</h1>
        <p style="max-width:760px">Rebrand this tenant without editing code. Colors override the design tokens site-wide (<code>--tsa-pink/-rose/-gold</code> + derived shades); name filters the site title; logo swaps the header logo. Leave a field blank to keep the TSA default. Purge LiteSpeed after saving.</p>
        <p style="font-size:12px;color:#666">Effective brand name now: <strong><?php echo esc_html( tsa_brand_name() ); ?></strong> · white_label feature: <code><?php echo $wl ? 'active' : 'off'; ?></code></p>

        <form method="post">
            <?php wp_nonce_field( 'tsa_branding', 'tsa_branding_nonce' ); ?>
            <table class="form-table" role="presentation">
                <tr><th><label for="brand_name">Brand name</label></th>
                    <td><input name="brand_name" id="brand_name" type="text" class="regular-text" value="<?php echo esc_attr( $b['brand_name'] ); ?>" placeholder="Tee Shirt Ali">
                    <p class="description">Blank = WordPress site title / provisioned tenant name.</p></td></tr>
                <tr><th><label for="logo_url">Logo URL</label></th>
                    <td><input name="logo_url" id="logo_url" type="url" class="regular-text" value="<?php echo esc_attr( $b['logo_url'] ); ?>" placeholder="https://…/logo.png">
                    <?php if ( $b['logo_url'] ) : ?><br><img src="<?php echo esc_url( $b['logo_url'] ); ?>" alt="" style="max-height:48px;margin-top:8px;background:#eee;padding:4px;border-radius:4px"><?php endif; ?>
                    <p class="description">Paste a Media Library image URL. (Flatsome's header logo may also need setting in Customizer — see notes.)</p></td></tr>
                <tr><th>Brand colors</th>
                    <td>
                        <label style="margin-right:1.5em">Primary <input type="color" name="primary" value="<?php echo $cv( $b['primary'], '#fec2c0' ); ?>"></label>
                        <label style="margin-right:1.5em">Accent <input type="color" name="accent" value="<?php echo $cv( $b['accent'], '#d8a85f' ); ?>"></label>
                        <label>Rose/links <input type="color" name="rose" value="<?php echo $cv( $b['rose'], '#d98789' ); ?>"></label>
                        <p class="description">Soft/deep shades are auto-derived. Defaults shown = TSA brand.</p>
                    </td></tr>
                <tr><th>White-label</th>
                    <td><label><input type="checkbox" name="hide_credit" value="1" <?php checked( ! empty( $b['hide_credit'] ) ); ?> <?php disabled( ! $wl ); ?>> Hide platform credit / "powered by"</label>
                    <p class="description"><?php echo $wl ? 'Only takes effect where the credit reads <code>tsa_brand_hide_credit()</code>.' : 'Requires the <code>white_label</code> feature (Platform/Studio tier) to be active.'; ?></p></td></tr>
            </table>
            <p class="submit">
                <button class="button button-primary" name="tsa_branding_save" value="1">Save branding</button>
                <button class="button" name="tsa_branding_reset" value="1" onclick="return confirm('Reset branding to the TSA default?');">Reset to default</button>
            </p>
        </form>
    </div>
    <?php
}
