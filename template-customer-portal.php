<?php
/**
 * Template Name: TSA Customer Portal
 *
 * Branded customer dashboard ("My Studio") — wraps WooCommerce My Account
 * content with the TSA portal UI. Self-contained styling (scoped to
 * .tsa-vault) using the TSA design tokens. SVG icons (no emojis).
 * Assign to: /my-account/, /customer-portal/, /ai-assistant/
 */

defined( 'ABSPATH' ) || exit;

get_header();

/* ════════════════════════════════════════════════════════════════════
   LOGGED-OUT: themed sign-in / create-account gate
   This page is the WooCommerce "My Account" page, so guests get a branded
   login + register here (WooCommerce processes both via the standard
   nonces/hooks) instead of the default unstyled Flatsome form.
═══════════════════════════════════════════════════════════════════════ */
if ( ! is_user_logged_in() ) :
    $acct_url     = wc_get_page_permalink( 'myaccount' );
    $reg_enabled  = get_option( 'woocommerce_enable_myaccount_registration' ) === 'yes';
    $gen_password = get_option( 'woocommerce_registration_generate_password' ) === 'yes';
?>
<style>
/* ════════ TSA Customer Portal — sign-in gate (scoped) ════════ */
.tsa-gate-page{ background:var(--surface-page,#f4f2f3); color:var(--text-primary,#252124); }
.tsa-gate-page *{ box-sizing:border-box; }
.tsa-gate-hero{ position:relative; overflow:hidden; padding:48px 7% 42px; text-align:center;
    background:linear-gradient(120deg,#2a2230,#3c2e44 60%,#46313c); color:#fff; }
.tsa-gate-hero::after{ content:""; position:absolute; inset:0; pointer-events:none;
    background:radial-gradient(120% 140% at 88% -30%, color-mix(in srgb,var(--brand-primary,#fec2c0) 50%,transparent), transparent 55%); }
.tsa-gate-hero__in{ position:relative; z-index:1; max-width:760px; margin:0 auto; }
.tsa-gate-eyebrow{ font-size:12px; font-weight:800; letter-spacing:2px; text-transform:uppercase; color:var(--brand-primary,#fec2c0); margin:0 0 8px; }
.tsa-gate-hero h1{ font-size:clamp(30px,4.5vw,46px); line-height:1; letter-spacing:-1px; margin:0; color:#fff; }
.tsa-gate-hero p{ margin:10px 0 0; color:rgba(255,255,255,.72); font-size:15px; }

.tsa-gate-notices{ max-width:980px; margin:0 auto; padding:18px 7% 0; }
.tsa-gate-notices ul{ list-style:none; margin:0; padding:0; }
.tsa-gate-notices .woocommerce-error li,
.tsa-gate-notices .woocommerce-error,
.tsa-gate-notices .woocommerce-message,
.tsa-gate-notices .woocommerce-info{ margin:0 0 12px; padding:14px 16px; border-radius:12px; font-size:14px; }
.tsa-gate-notices .woocommerce-error{ background:#fde8e8; border:1px solid #f5c2c2; color:#9b2c2c; }
.tsa-gate-notices .woocommerce-message{ background:#e8f6ec; border:1px solid #bfe3c8; color:#1f7a3d; }
.tsa-gate-notices .woocommerce-info{ background:var(--surface-tint,#fff1f0); border:1px solid var(--border,rgba(37,33,36,.12)); color:var(--text-primary,#252124); }

.tsa-gate{ max-width:980px; margin:0 auto; padding:34px 7% 64px; display:grid; grid-template-columns:1fr 1fr; gap:22px; align-items:start; }
.tsa-gate__panel{ background:var(--surface-card,#fff); border:1px solid var(--border,rgba(37,33,36,.10)); border-radius:20px;
    padding:30px; box-shadow:0 10px 30px rgba(37,33,36,.07); }
.tsa-gate__panel h2{ margin:0 0 6px; font-size:22px; font-weight:800; letter-spacing:-.4px; color:var(--text-primary,#252124); }
.tsa-gate__tag{ margin:0 0 20px; color:var(--text-muted,#7a7480); font-size:14px; }
.tsa-gate-row{ margin-bottom:16px; }
.tsa-gate-row label{ display:block; font-size:13px; font-weight:700; margin:0 0 7px; color:var(--text-primary,#252124); }
.tsa-gate input[type=text],.tsa-gate input[type=email],.tsa-gate input[type=password]{
    width:100%; padding:13px 14px; border:1px solid var(--border-strong,rgba(37,33,36,.22)); border-radius:var(--radius-md,12px);
    background:#fff; font-size:15px; color:var(--text-primary,#252124); transition:border-color .15s ease, box-shadow .15s ease; }
.tsa-gate input:focus{ outline:none; border-color:var(--brand-rose,#d98789); box-shadow:0 0 0 3px rgba(217,135,137,.18); }
.tsa-gate-remember{ display:flex; align-items:center; gap:8px; font-size:13px; color:var(--text-muted,#7a7480); margin:0 0 16px; cursor:pointer; }
.tsa-gate-remember input{ width:auto; margin:0; }
.tsa-gate-btn{ display:inline-flex; align-items:center; justify-content:center; width:100%; padding:14px 20px; border:0;
    border-radius:999px; background:var(--brand-rose,#d98789); color:#fff; font-size:15px; font-weight:800; letter-spacing:.2px;
    cursor:pointer; transition:transform .15s ease, filter .15s ease; }
.tsa-gate-btn:hover{ filter:brightness(1.05); transform:translateY(-1px); }
.tsa-gate-lost{ margin:14px 0 0; font-size:13px; }
.tsa-gate-lost a{ color:var(--brand-rose,#d98789); text-decoration:none; font-weight:700; }
.tsa-gate-lost a:hover{ text-decoration:underline; }
.tsa-gate__disabled{ color:var(--text-muted,#7a7480); font-size:14px; margin:0; }
@media (max-width:760px){ .tsa-gate{ grid-template-columns:1fr; } }
@media (prefers-reduced-motion:reduce){ .tsa-gate-page *{ animation:none!important; transition:none!important; } }
</style>

<div class="tsa-gate-page">
    <header class="tsa-gate-hero">
        <div class="tsa-gate-hero__in">
            <p class="tsa-gate-eyebrow">Customer Portal</p>
            <h1>Welcome to your studio</h1>
            <p>Sign in to track orders, manage addresses, and pick up where you left off.</p>
        </div>
    </header>

    <div class="tsa-gate-notices"><?php if ( function_exists( 'wc_print_notices' ) ) wc_print_notices(); ?></div>

    <div class="tsa-gate">
        <!-- Sign In -->
        <div class="tsa-gate__panel">
            <h2>Sign In</h2>
            <p class="tsa-gate__tag">Welcome back — enter your details to continue.</p>
            <form method="post" action="<?php echo esc_url( $acct_url ); ?>" class="woocommerce-form woocommerce-form-login login">
                <?php do_action( 'woocommerce_login_form_start' ); ?>
                <div class="tsa-gate-row">
                    <label for="username"><?php esc_html_e( 'Username or email address', 'woocommerce' ); ?></label>
                    <input type="text" class="woocommerce-Input input-text" name="username" id="username" autocomplete="username" value="<?php echo ! empty( $_POST['username'] ) ? esc_attr( wp_unslash( $_POST['username'] ) ) : ''; ?>" />
                </div>
                <div class="tsa-gate-row">
                    <label for="password"><?php esc_html_e( 'Password', 'woocommerce' ); ?></label>
                    <input type="password" class="woocommerce-Input input-text" name="password" id="password" autocomplete="current-password" />
                </div>
                <?php do_action( 'woocommerce_login_form' ); ?>
                <label class="tsa-gate-remember">
                    <input type="checkbox" name="rememberme" value="forever" /> <span><?php esc_html_e( 'Remember me', 'woocommerce' ); ?></span>
                </label>
                <?php wp_nonce_field( 'woocommerce-login', 'woocommerce-login-nonce' ); ?>
                <input type="hidden" name="redirect" value="<?php echo esc_url( $acct_url ); ?>" />
                <button type="submit" class="tsa-gate-btn woocommerce-button button woocommerce-form-login__submit" name="login" value="<?php esc_attr_e( 'Log in', 'woocommerce' ); ?>"><?php esc_html_e( 'Sign In', 'woocommerce' ); ?></button>
                <p class="tsa-gate-lost"><a href="<?php echo esc_url( wp_lostpassword_url() ); ?>"><?php esc_html_e( 'Lost your password?', 'woocommerce' ); ?></a></p>
                <?php do_action( 'woocommerce_login_form_end' ); ?>
            </form>
        </div>

        <!-- Create Account -->
        <div class="tsa-gate__panel">
            <h2>New here?</h2>
            <p class="tsa-gate__tag">Create a free account to track orders, save addresses, and check out faster.</p>
            <?php if ( $reg_enabled ) : ?>
            <form method="post" action="<?php echo esc_url( $acct_url ); ?>" class="woocommerce-form woocommerce-form-register register" <?php do_action( 'woocommerce_register_form_tag' ); ?>>
                <?php do_action( 'woocommerce_register_form_start' ); ?>
                <div class="tsa-gate-row">
                    <label for="reg_email"><?php esc_html_e( 'Email address', 'woocommerce' ); ?></label>
                    <input type="email" class="woocommerce-Input input-text" name="email" id="reg_email" autocomplete="email" value="<?php echo ! empty( $_POST['email'] ) ? esc_attr( wp_unslash( $_POST['email'] ) ) : ''; ?>" />
                </div>
                <?php if ( ! $gen_password ) : ?>
                <div class="tsa-gate-row">
                    <label for="reg_password"><?php esc_html_e( 'Password', 'woocommerce' ); ?></label>
                    <input type="password" class="woocommerce-Input input-text" name="password" id="reg_password" autocomplete="new-password" />
                </div>
                <?php endif; ?>
                <?php do_action( 'woocommerce_register_form' ); ?>
                <?php wp_nonce_field( 'woocommerce-register', 'woocommerce-register-nonce' ); ?>
                <input type="hidden" name="redirect" value="<?php echo esc_url( $acct_url ); ?>" />
                <button type="submit" class="tsa-gate-btn woocommerce-button button woocommerce-form-register__submit" name="register" value="<?php esc_attr_e( 'Register', 'woocommerce' ); ?>"><?php esc_html_e( 'Create Account', 'woocommerce' ); ?></button>
                <?php do_action( 'woocommerce_register_form_end' ); ?>
            </form>
            <?php else : ?>
            <p class="tsa-gate__disabled">Account registration is currently disabled. Please <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">contact us</a> to set up an account.</p>
            <?php endif; ?>
        </div>
    </div>
</div>
<?php
    get_footer();
    return;
endif;

$current_user = wp_get_current_user();
$first_name   = $current_user->first_name ?: $current_user->display_name;

/* Inline SVG icon set (Lucide-style, 24×24 stroke). */
if ( ! function_exists( 'tsa_vault_icon' ) ) :
function tsa_vault_icon( $name, $size = 20 ) {
    $icons = [
        'dashboard'       => '<rect x="3" y="3" width="7" height="9" rx="1.5"/><rect x="14" y="3" width="7" height="5" rx="1.5"/><rect x="14" y="12" width="7" height="9" rx="1.5"/><rect x="3" y="16" width="7" height="5" rx="1.5"/>',
        'orders'          => '<path d="M21 8a2 2 0 0 0-1-1.73l-7-4a2 2 0 0 0-2 0l-7 4A2 2 0 0 0 3 8v8a2 2 0 0 0 1 1.73l7 4a2 2 0 0 0 2 0l7-4A2 2 0 0 0 21 16Z"/><path d="m3.3 7 8.7 5 8.7-5"/><path d="M12 22V12"/>',
        'downloads'       => '<path d="M21 15v4a2 2 0 0 1-2 2H5a2 2 0 0 1-2-2v-4"/><polyline points="7 10 12 15 17 10"/><line x1="12" x2="12" y1="15" y2="3"/>',
        'edit-address'    => '<path d="M20 10c0 6-8 12-8 12s-8-6-8-12a8 8 0 0 1 16 0Z"/><circle cx="12" cy="10" r="3"/>',
        'payment-methods' => '<rect width="20" height="14" x="2" y="5" rx="2"/><line x1="2" x2="22" y1="10" y2="10"/>',
        'edit-account'    => '<circle cx="12" cy="8" r="4"/><path d="M6 21v-1a4 4 0 0 1 4-4h4a4 4 0 0 1 4 4v1"/>',
        'customer-logout' => '<path d="M9 21H5a2 2 0 0 1-2-2V5a2 2 0 0 1 2-2h4"/><polyline points="16 17 21 12 16 7"/><line x1="21" x2="9" y1="12" y2="12"/>',
        'bag'             => '<path d="M6 2 3 6v14a2 2 0 0 0 2 2h14a2 2 0 0 0 2-2V6l-3-4Z"/><path d="M3 6h18"/><path d="M16 10a4 4 0 0 1-8 0"/>',
        'check'           => '<path d="M21.8 10A10 10 0 1 1 17 3.34"/><path d="m9 11 3 3L22 4"/>',
        'clock'           => '<circle cx="12" cy="12" r="10"/><polyline points="12 6 12 12 16 14"/>',
        'bot'             => '<path d="M12 8V4H8"/><rect width="16" height="12" x="4" y="8" rx="2"/><path d="M2 14h2"/><path d="M20 14h2"/><path d="M15 13v2"/><path d="M9 13v2"/>',
        'arrow-left'      => '<path d="m12 19-7-7 7-7"/><path d="M19 12H5"/>',
        'arrow-right'     => '<path d="M5 12h14"/><path d="m12 5 7 7-7 7"/>',
        'quote'           => '<path d="M21 15a2 2 0 0 1-2 2H7l-4 4V5a2 2 0 0 1 2-2h14a2 2 0 0 1 2 2z"/>',
        'image'           => '<rect width="18" height="18" x="3" y="3" rx="2" ry="2"/><circle cx="9" cy="9" r="2"/><path d="m21 15-3.1-3.1a2 2 0 0 0-2.8 0L6 21"/>',
        'shirt'           => '<path d="M20.4 3.5 16 2a4 4 0 0 1-8 0L3.6 3.5a2 2 0 0 0-1.3 2.2l.5 3.5a1 1 0 0 0 1 .8H6v10a2 2 0 0 0 2 2h8a2 2 0 0 0 2-2V10h2.2a1 1 0 0 0 1-.8l.5-3.5a2 2 0 0 0-1.3-2.2z"/>',
        'mail'            => '<rect width="20" height="16" x="2" y="4" rx="2"/><path d="m22 7-8.97 5.7a1.94 1.94 0 0 1-2.06 0L2 7"/>',
        'sparkles'        => '<path d="M9.94 15.5A2 2 0 0 0 8.5 14.06l-6.14-1.58a.5.5 0 0 1 0-.96L8.5 9.94A2 2 0 0 0 9.94 8.5l1.58-6.14a.5.5 0 0 1 .96 0L14.06 8.5A2 2 0 0 0 15.5 9.94l6.14 1.58a.5.5 0 0 1 0 .96L15.5 14.06a2 2 0 0 0-1.44 1.44l-1.58 6.14a.5.5 0 0 1-.96 0z"/><path d="M20 3v4"/><path d="M22 5h-4"/><path d="M4 17v2"/><path d="M5 18H3"/>',
    ];
    $body = $icons[ $name ] ?? '';
    return '<svg xmlns="http://www.w3.org/2000/svg" width="' . (int) $size . '" height="' . (int) $size . '" viewBox="0 0 24 24" fill="none" stroke="currentColor" stroke-width="2" stroke-linecap="round" stroke-linejoin="round" aria-hidden="true">' . $body . '</svg>';
}
endif;

// WooCommerce endpoint map for nav
$wc_account_url = wc_get_page_permalink( 'myaccount' );
$nav_items = [
    'dashboard'       => [ 'label' => 'Dashboard',       'url' => $wc_account_url ],
    'orders'          => [ 'label' => 'Orders',          'url' => wc_get_endpoint_url( 'orders', '', $wc_account_url ) ],
    'downloads'       => [ 'label' => 'Downloads',       'url' => wc_get_endpoint_url( 'downloads', '', $wc_account_url ) ],
    'edit-address'    => [ 'label' => 'Addresses',       'url' => wc_get_endpoint_url( 'edit-address', '', $wc_account_url ) ],
    'payment-methods' => [ 'label' => 'Payment',         'url' => wc_get_endpoint_url( 'payment-methods', '', $wc_account_url ) ],
    'edit-account'    => [ 'label' => 'Profile & Login', 'url' => wc_get_endpoint_url( 'edit-account', '', $wc_account_url ) ],
    'customer-logout' => [ 'label' => 'Log Out',         'url' => wc_logout_url() ],
];

$current_endpoint = WC()->query->get_current_endpoint() ?: 'dashboard';
$is_dashboard     = ( $current_endpoint === 'dashboard' || $current_endpoint === '' );

// Order stats + recent list
$recent_orders    = wc_get_orders( [ 'customer' => $current_user->ID, 'limit' => 5, 'orderby' => 'date', 'order' => 'DESC' ] );
$order_count      = count( wc_get_orders( [ 'customer' => $current_user->ID, 'limit' => -1, 'return' => 'ids' ] ) );
$completed_orders = count( wc_get_orders( [ 'customer' => $current_user->ID, 'limit' => -1, 'return' => 'ids', 'status' => 'completed' ] ) );

// Capture WooCommerce endpoint content (orders table, address forms, etc.).
// Use woocommerce_account_content() directly so endpoints render regardless of
// whether the page body contains the [woocommerce_my_account] shortcode (the
// old the_content() approach left the panel blank when it didn't).
$wc_content = '';
if ( ! $is_dashboard ) {
    ob_start();
    if ( function_exists( 'woocommerce_account_content' ) ) {
        woocommerce_account_content();
    } else {
        echo do_shortcode( '[woocommerce_my_account]' );
    }
    $wc_content = trim( ob_get_clean() );
}

// Downloadable files + saved cards — used to drive the branded empty states.
$vault_downloads = function_exists( 'wc_get_customer_available_downloads' ) ? wc_get_customer_available_downloads( $current_user->ID ) : [];
$vault_tokens    = class_exists( 'WC_Payment_Tokens' ) ? WC_Payment_Tokens::get_customer_tokens( $current_user->ID ) : [];

// Renders a branded empty state (icon + heading + message + shopping CTAs).
$vault_empty = function ( $icon, $title, $msg, $ctas ) {
    echo '<section class="tsa-vault-card"><div class="tsa-vault-card__body"><div class="tsa-vault-empty">';
    echo '<div class="tsa-vault-empty__ico">' . tsa_vault_icon( $icon, 26 ) . '</div>';
    echo '<h3>' . esc_html( $title ) . '</h3><p>' . esc_html( $msg ) . '</p>';
    echo '<div class="tsa-actions tsa-actions--center">';
    foreach ( $ctas as $c ) { tsa_btn( $c[0], $c[1], $c[2] ?? 'primary' ); }
    echo '</div></div></div></section>';
};
?>

<style>
/* ════════ TSA Customer Portal (scoped) ════════ */
.tsa-vault{ --v-rose:var(--brand-rose,#e98a8c); --v-pink:var(--brand-primary,#fec2c0); --v-gold:var(--brand-accent,#d8a85f);
    --v-ink:var(--text-primary,#252124); --v-muted:var(--text-muted,#7a7480); --v-card:var(--surface-card,#fff);
    --v-page:var(--surface-page,#f4f2f3); --v-tint:var(--surface-tint,#fff1f0); --v-line:var(--border,rgba(37,33,36,.10));
    background:var(--v-page); color:var(--v-ink); }
.tsa-vault *{ box-sizing:border-box; }

/* Hero band */
.tsa-vault-hero{ position:relative; overflow:hidden; padding:44px 7% 38px;
    background:linear-gradient(135deg, var(--v-tint,#fff1f0) 0%, #fff 72%); color:var(--v-ink); border-bottom:1px solid var(--v-line); }
.tsa-vault-hero::after{ content:""; position:absolute; inset:0; pointer-events:none;
    background:radial-gradient(120% 150% at 92% -40%, color-mix(in srgb,var(--v-gold) 26%,transparent), transparent 56%); }
.tsa-vault-hero__inner{ position:relative; z-index:1; max-width:1180px; margin:0 auto; display:flex; align-items:flex-end; justify-content:space-between; gap:20px; flex-wrap:wrap; }
.tsa-vault-eyebrow{ font-size:12px; font-weight:800; letter-spacing:2px; text-transform:uppercase; color:#a9803f; margin:0 0 8px; }
.tsa-vault-hero h1{ font-size:clamp(34px,5vw,52px); line-height:1; letter-spacing:-1.5px; margin:0; color:var(--v-ink); }
.tsa-vault-hero p{ margin:10px 0 0; color:var(--v-muted); font-size:15px; }
.tsa-vault-back{ display:inline-flex; align-items:center; gap:8px; color:#a9803f; font-size:13px; font-weight:800;
    text-decoration:none; padding:10px 18px; border:1.5px solid color-mix(in srgb,var(--v-gold) 60%,transparent); border-radius:999px; transition:all .2s ease; }
.tsa-vault-back:hover{ background:var(--v-gold); border-color:var(--v-gold); color:#fff; }

/* Body grid */
.tsa-vault-body{ max-width:1180px; margin:0 auto; padding:28px 7% 60px; display:grid; grid-template-columns:248px 1fr; gap:24px; align-items:start; }

/* Sidebar */
.tsa-vault-nav{ position:sticky; top:24px; background:var(--v-card); border:1px solid var(--v-line); border-radius:18px; padding:10px; box-shadow:var(--shadow-xs,0 8px 24px rgba(37,33,36,.06)); display:flex; flex-direction:column; gap:2px; }
.tsa-vault-nav a{ display:flex; align-items:center; gap:12px; padding:12px 14px; border-radius:12px; color:var(--v-ink);
    text-decoration:none; font-size:14px; font-weight:600; transition:background .18s ease,color .18s ease; cursor:pointer; }
.tsa-vault-nav a svg{ flex:none; color:var(--v-muted); transition:color .18s ease; }
.tsa-vault-nav a:hover{ background:var(--v-tint); }
.tsa-vault-nav a.active{ background:color-mix(in srgb,var(--v-rose) 16%,transparent); color:var(--v-ink); }
.tsa-vault-nav a.active svg{ color:var(--v-rose); }
.tsa-vault-nav a.active{ box-shadow:inset 3px 0 0 var(--v-rose); }
.tsa-vault-nav__sep{ height:1px; background:var(--v-line); margin:8px 6px; }
.tsa-vault-nav a.is-logout{ color:#b4434a; }
.tsa-vault-nav a.is-logout svg{ color:#b4434a; }
.tsa-vault-nav a.is-logout:hover{ background:color-mix(in srgb,#c5363d 10%,transparent); }

/* Main column */
.tsa-vault-main{ display:flex; flex-direction:column; gap:22px; min-width:0; }

/* Stats */
.tsa-vault-stats{ display:grid; grid-template-columns:repeat(3,1fr); gap:16px; }
.tsa-vault-stat{ background:var(--v-card); border:1px solid var(--v-line); border-radius:18px; padding:20px;
    box-shadow:var(--shadow-xs,0 8px 24px rgba(37,33,36,.06)); transition:transform .2s ease,box-shadow .2s ease; }
.tsa-vault-stat:hover{ transform:translateY(-3px); box-shadow:var(--shadow-sm,0 10px 28px rgba(37,33,36,.08)); }
.tsa-vault-stat__ico{ width:38px; height:38px; border-radius:11px; display:flex; align-items:center; justify-content:center; margin-bottom:14px; }
.tsa-vault-stat--a .tsa-vault-stat__ico{ background:color-mix(in srgb,var(--v-rose) 16%,transparent); color:var(--v-rose); }
.tsa-vault-stat--b .tsa-vault-stat__ico{ background:color-mix(in srgb,#1f9d55 14%,transparent); color:#1f9d55; }
.tsa-vault-stat--c .tsa-vault-stat__ico{ background:color-mix(in srgb,var(--v-gold) 18%,transparent); color:#a9803f; }
.tsa-vault-stat__val{ font-size:34px; font-weight:800; line-height:1; letter-spacing:-1px; color:var(--v-ink); }
.tsa-vault-stat__label{ margin-top:6px; font-size:12px; font-weight:700; letter-spacing:.6px; text-transform:uppercase; color:var(--v-muted); }

/* Cards */
.tsa-vault-card{ background:var(--v-card); border:1px solid var(--v-line); border-radius:18px; box-shadow:var(--shadow-xs,0 8px 24px rgba(37,33,36,.06)); overflow:hidden; }
.tsa-vault-card__head{ display:flex; align-items:center; justify-content:space-between; gap:12px; padding:18px 22px; border-bottom:1px solid var(--v-line); }
.tsa-vault-card__head h3{ margin:0; font-size:16px; font-weight:800; letter-spacing:-.3px; color:var(--v-ink); }
.tsa-vault-card__body{ padding:20px 22px; }
.tsa-vault-card--wc .tsa-vault-card__body{ padding:24px; }

/* Recent orders */
.tsa-vault-order{ display:flex; align-items:center; justify-content:space-between; gap:14px; padding:14px 0; border-top:1px solid var(--v-line); }
.tsa-vault-order:first-child{ border-top:0; }
.tsa-vault-order__id{ font-weight:800; color:var(--v-ink); font-size:14px; text-decoration:none; }
.tsa-vault-order__id:hover{ color:var(--v-rose); }
.tsa-vault-order__meta{ font-size:12px; color:var(--v-muted); margin-top:2px; }
.tsa-vault-order__right{ display:flex; align-items:center; gap:14px; }
.tsa-vault-order__total{ font-weight:800; color:var(--v-ink); font-size:14px; }
.tsa-vault-badge{ font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.4px; padding:5px 11px; border-radius:999px; white-space:nowrap; }
.tsa-vault-empty{ text-align:center; padding:42px 16px; }
.tsa-vault-empty__ico{ width:56px; height:56px; border-radius:50%; display:flex; align-items:center; justify-content:center; margin:0 auto 16px; background:color-mix(in srgb,var(--v-rose) 14%,transparent); color:var(--v-rose); }
.tsa-vault-empty h3{ margin:0 0 8px; font-size:18px; font-weight:800; color:var(--v-ink); }
.tsa-vault-empty p{ color:var(--v-muted); margin:0 auto 18px; font-size:14px; max-width:360px; }

/* Quick actions — icon cards */
.tsa-vault-qa{ display:grid; grid-template-columns:repeat(2,1fr); gap:14px; }
.tsa-vault-qa__item{ display:flex; align-items:center; gap:14px; padding:16px; border:1px solid var(--v-line); border-radius:14px; background:var(--v-card); text-decoration:none; color:var(--v-ink); transition:border-color .2s ease,box-shadow .2s ease,transform .2s ease; cursor:pointer; }
.tsa-vault-qa__item:hover{ border-color:color-mix(in srgb,var(--v-rose) 45%,var(--v-line)); box-shadow:var(--shadow-sm,0 10px 28px rgba(37,33,36,.08)); transform:translateY(-2px); }
.tsa-vault-qa__ico{ width:44px; height:44px; flex:none; border-radius:12px; display:flex; align-items:center; justify-content:center; background:color-mix(in srgb,var(--v-rose) 14%,transparent); color:var(--v-rose); }
.tsa-vault-qa__item:nth-child(even) .tsa-vault-qa__ico{ background:color-mix(in srgb,var(--v-gold) 18%,transparent); color:#a9803f; }
.tsa-vault-qa__txt{ display:flex; flex-direction:column; min-width:0; flex:1; }
.tsa-vault-qa__title{ font-weight:800; font-size:14px; }
.tsa-vault-qa__sub{ font-size:12px; color:var(--v-muted); margin-top:2px; }
.tsa-vault-qa__arrow{ color:var(--v-muted); flex:none; transition:transform .2s ease,color .2s ease; }
.tsa-vault-qa__item:hover .tsa-vault-qa__arrow{ transform:translateX(3px); color:var(--v-rose); }
/* Odd one out spans full width so the grid stays balanced */
.tsa-vault-qa__item:last-child:nth-child(odd){ grid-column:1 / -1; }
@media (max-width:560px){ .tsa-vault-qa{ grid-template-columns:1fr; } }

@media (max-width:900px){
    .tsa-vault-body{ grid-template-columns:1fr; }
    .tsa-vault-nav{ position:static; flex-direction:row; overflow-x:auto; gap:6px; }
    .tsa-vault-nav a{ white-space:nowrap; }
    .tsa-vault-nav__sep{ display:none; }
    .tsa-vault-stats{ grid-template-columns:1fr; }
}
@media (prefers-reduced-motion:reduce){ .tsa-vault *{ animation:none!important; transition:none!important; } }
</style>

<div class="tsa-vault">

    <!-- Hero -->
    <header class="tsa-vault-hero">
        <div class="tsa-vault-hero__inner">
            <div>
                <p class="tsa-vault-eyebrow">Customer Portal</p>
                <h1>My Studio</h1>
                <p>Welcome back, <?php echo esc_html( $first_name ); ?></p>
            </div>
            <a class="tsa-vault-back" href="<?php echo esc_url( home_url( '/' ) ); ?>"><?php echo tsa_vault_icon( 'arrow-left', 16 ); ?> Back to Store</a>
        </div>
    </header>

    <div class="tsa-vault-body">

        <!-- Sidebar -->
        <nav class="tsa-vault-nav" aria-label="Account navigation">
            <?php foreach ( $nav_items as $endpoint => $item ) :
                if ( $endpoint === 'customer-logout' ) echo '<div class="tsa-vault-nav__sep"></div>';
                $cls  = $current_endpoint === $endpoint ? 'active' : '';
                $cls .= $endpoint === 'customer-logout' ? ' is-logout' : ''; ?>
                <a href="<?php echo esc_url( $item['url'] ); ?>" class="<?php echo esc_attr( trim( $cls ) ); ?>"<?php echo $current_endpoint === $endpoint ? ' aria-current="page"' : ''; ?>>
                    <?php echo tsa_vault_icon( $endpoint ); ?><span><?php echo esc_html( $item['label'] ); ?></span>
                </a>
            <?php endforeach; ?>
        </nav>

        <!-- Main -->
        <main class="tsa-vault-main">

            <?php if ( $is_dashboard ) : ?>

            <!-- Stats -->
            <div class="tsa-vault-stats">
                <div class="tsa-vault-stat tsa-vault-stat--a">
                    <div class="tsa-vault-stat__ico"><?php echo tsa_vault_icon( 'bag' ); ?></div>
                    <div class="tsa-vault-stat__val"><?php echo esc_html( $order_count ); ?></div>
                    <div class="tsa-vault-stat__label">Total Orders</div>
                </div>
                <div class="tsa-vault-stat tsa-vault-stat--b">
                    <div class="tsa-vault-stat__ico"><?php echo tsa_vault_icon( 'check' ); ?></div>
                    <div class="tsa-vault-stat__val"><?php echo esc_html( $completed_orders ); ?></div>
                    <div class="tsa-vault-stat__label">Completed</div>
                </div>
                <div class="tsa-vault-stat tsa-vault-stat--c">
                    <div class="tsa-vault-stat__ico"><?php echo tsa_vault_icon( 'clock' ); ?></div>
                    <div class="tsa-vault-stat__val"><?php echo esc_html( max( 0, $order_count - $completed_orders ) ); ?></div>
                    <div class="tsa-vault-stat__label">In Progress</div>
                </div>
            </div>

            <!-- Recent orders -->
            <section class="tsa-vault-card">
                <div class="tsa-vault-card__head">
                    <h3>Recent Orders</h3>
                    <?php if ( $recent_orders ) : ?><a class="tsa-vault-order__id" href="<?php echo esc_url( wc_get_endpoint_url( 'orders', '', $wc_account_url ) ); ?>" style="font-size:13px">View all</a><?php endif; ?>
                </div>
                <div class="tsa-vault-card__body">
                    <?php if ( $recent_orders ) :
                        $badge = [ 'completed'=>['#1f9d55','#e7f6ec'], 'processing'=>['#2b6cb0','#e8f0fb'], 'on-hold'=>['#b7791f','#fcf3e3'], 'pending'=>['#6b7280','#eef0f2'], 'cancelled'=>['#c53030','#fbeaea'], 'refunded'=>['#6b7280','#eef0f2'], 'failed'=>['#c53030','#fbeaea'] ];
                        foreach ( $recent_orders as $o ) :
                            $st = $o->get_status();
                            $bc = $badge[ $st ] ?? [ '#6b7280', '#eef0f2' ]; ?>
                        <div class="tsa-vault-order">
                            <div>
                                <a class="tsa-vault-order__id" href="<?php echo esc_url( $o->get_view_order_url() ); ?>">Order #<?php echo esc_html( $o->get_order_number() ); ?></a>
                                <div class="tsa-vault-order__meta"><?php echo esc_html( wc_format_datetime( $o->get_date_created(), 'M j, Y' ) ); ?> · <?php echo esc_html( $o->get_item_count() ); ?> item<?php echo $o->get_item_count() === 1 ? '' : 's'; ?></div>
                            </div>
                            <div class="tsa-vault-order__right">
                                <span class="tsa-vault-badge" style="color:<?php echo esc_attr( $bc[0] ); ?>;background:<?php echo esc_attr( $bc[1] ); ?>"><?php echo esc_html( wc_get_order_status_name( $st ) ); ?></span>
                                <span class="tsa-vault-order__total"><?php echo wp_kses_post( $o->get_formatted_order_total() ); ?></span>
                            </div>
                        </div>
                        <?php endforeach;
                    else : ?>
                        <div class="tsa-vault-empty">
                            <p>No orders yet — your custom pieces will show up here.</p>
                            <?php tsa_btn( '/configurator/', 'Start Designing', 'primary' ); ?>
                        </div>
                    <?php endif; ?>
                </div>
            </section>

            <!-- Quick actions -->
            <section class="tsa-vault-card">
                <div class="tsa-vault-card__head"><h3>Quick Actions</h3></div>
                <div class="tsa-vault-card__body">
                    <div class="tsa-vault-qa">
                        <?php
                        $quick_actions = [
                            [ '/request-a-quote/',    'quote', 'Request a Quote', 'Get a custom price' ],
                            [ '/design-library/',     'image', 'Browse Designs',  'Explore the library' ],
                            [ '/blank-apparel/',      'shirt', 'Shop Blanks',     'Tees, hoodies & more' ],
                            [ '/contact/',            'mail',  'Contact Us',      'We reply same day' ],
                            [ '/request-a-tee-party/','sparkles', 'Request a Tee Party', 'Host a limited design drop' ],
                        ];
                        foreach ( $quick_actions as $qa ) : ?>
                        <a class="tsa-vault-qa__item" href="<?php echo esc_url( home_url( $qa[0] ) ); ?>">
                            <span class="tsa-vault-qa__ico"><?php echo tsa_vault_icon( $qa[1], 22 ); ?></span>
                            <span class="tsa-vault-qa__txt">
                                <span class="tsa-vault-qa__title"><?php echo esc_html( $qa[2] ); ?></span>
                                <span class="tsa-vault-qa__sub"><?php echo esc_html( $qa[3] ); ?></span>
                            </span>
                            <span class="tsa-vault-qa__arrow"><?php echo tsa_vault_icon( 'arrow-right', 18 ); ?></span>
                        </a>
                        <?php endforeach; ?>
                    </div>
                </div>
            </section>

            <?php endif; // dashboard ?>

            <?php if ( ! $is_dashboard ) :
                // List endpoints get a branded empty state + shopping CTAs when there's
                // nothing to show; form endpoints (addresses, profile) render WC content.
                if ( $current_endpoint === 'orders' && $order_count === 0 ) :
                    $vault_empty( 'bag', 'No orders yet', 'Your custom pieces will show up here once you place your first order.', [ [ '/configurator/', 'Start Designing', 'primary' ], [ '/design-library/', 'Browse Designs', 'outline' ] ] );
                elseif ( $current_endpoint === 'downloads' && empty( $vault_downloads ) ) :
                    $vault_empty( 'downloads', 'No downloads yet', 'Digital files and proofs from your orders will appear here.', [ [ '/design-library/', 'Browse Designs', 'primary' ], [ '/blank-apparel/', 'Shop Blanks', 'outline' ] ] );
                elseif ( $current_endpoint === 'payment-methods' && empty( $vault_tokens ) ) :
                    $vault_empty( 'payment-methods', 'No saved payment methods', 'Save a card at checkout to reorder even faster next time.', [ [ '/configurator/', 'Start a Project', 'primary' ] ] );
                elseif ( $wc_content !== '' ) : ?>
                    <section class="tsa-vault-card tsa-vault-card--wc">
                        <div class="tsa-vault-card__body"><?php echo $wc_content; // phpcs:ignore — WooCommerce-rendered ?></div>
                    </section>
                <?php else :
                    // Fallback: never leave the panel blank.
                    $vault_empty( 'bag', 'Nothing here yet', 'Start a project and your activity will show up in your studio.', [ [ '/configurator/', 'Start Designing', 'primary' ], [ '/design-library/', 'Browse Designs', 'outline' ] ] );
                endif;
            endif; ?>

        </main>
    </div>
</div>

<?php get_footer(); ?>
