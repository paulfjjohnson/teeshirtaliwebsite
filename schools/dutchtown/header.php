<?php
/**
 * Dutchtown Griffins — Standalone Branded Header
 * Included in all school page templates via:  include __DIR__ . '/header.php';
 * Replaces TSA Flatsome header entirely on Dutchtown pages.
 */
$_dths_nav = [
    [ 'label' => 'Home',        'url' => home_url( '/schools/dutchtown/' ) ],
    [ 'label' => 'Color Guard', 'url' => home_url( '/schools/dutchtown/color-guard/' ) ],
    [ 'label' => 'Band',        'url' => home_url( '/schools/dutchtown/band/' ) ],
    [ 'label' => 'Programs',    'url' => home_url( '/schools/dutchtown/programs/' ) ],
    [ 'label' => 'Drops',       'url' => home_url( '/schools/dutchtown/drops/' ) ],
];
$cart_count = function_exists( 'WC' ) && WC()->cart ? WC()->cart->get_cart_contents_count() : 0;
$current    = trailingslashit( home_url( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) ) );
?>

<!-- ===== DUTCHTOWN HEADER ===== -->
<header class="dths-header" id="dths-header" role="banner">
  <div class="dths-shell dths-header-inner">

    <!-- Logo -->
    <a href="<?php echo esc_url( home_url( '/schools/dutchtown/' ) ); ?>" class="dths-header-logo" aria-label="Dutchtown Griffins Home">
      <div class="dths-header-mark">
        <svg width="18" height="18" viewBox="0 0 18 18" fill="none" aria-hidden="true">
          <path d="M9 1L11.5 6.5H17L12.5 10L14.5 16L9 12.5L3.5 16L5.5 10L1 6.5H6.5L9 1Z" fill="white" opacity="0.9"/>
        </svg>
      </div>
      <div class="dths-header-logo-text">
        <span class="dths-header-school">Dutchtown</span>
        <span class="dths-header-sub">Griffins · Official Merch</span>
      </div>
    </a>

    <!-- Desktop Nav -->
    <nav class="dths-header-nav" aria-label="Primary">
      <?php foreach ( $_dths_nav as $item ) :
        $active = trailingslashit( home_url( parse_url( $_SERVER['REQUEST_URI'], PHP_URL_PATH ) ) ) === trailingslashit( $item['url'] );
      ?>
      <a href="<?php echo esc_url( $item['url'] ); ?>"
         class="dths-header-link<?php echo $active ? ' is-active' : ''; ?>">
        <?php echo esc_html( $item['label'] ); ?>
      </a>
      <?php endforeach; ?>
    </nav>

    <!-- Actions -->
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
        <span class="dths-header-cart-count<?php echo $cart_count > 0 ? ' has-items' : ''; ?>" id="dths-cart-count">
          <?php echo absint( $cart_count ); ?>
        </span>
      </a>

      <!-- Mobile hamburger -->
      <button class="dths-header-hamburger" id="dths-hamburger" aria-label="Open menu" aria-expanded="false">
        <span></span><span></span><span></span>
      </button>
    </div>

  </div>
</header>

<!-- ===== MOBILE NAV OVERLAY ===== -->
<div class="dths-mobile-nav" id="dths-mobile-nav" aria-hidden="true">
  <div class="dths-mobile-nav-inner">
    <div class="dths-mobile-nav-top">
      <a href="<?php echo esc_url( home_url( '/schools/dutchtown/' ) ); ?>" class="dths-header-logo">
        <div class="dths-header-mark">DG</div>
        <div class="dths-header-logo-text">
          <span class="dths-header-school">Dutchtown</span>
          <span class="dths-header-sub">Griffins · Official Merch</span>
        </div>
      </a>
      <button class="dths-mobile-nav-close" id="dths-mobile-close" aria-label="Close menu">✕</button>
    </div>
    <nav class="dths-mobile-nav-links">
      <?php foreach ( $_dths_nav as $item ) :
        $active = $current === trailingslashit( $item['url'] );
      ?>
      <a href="<?php echo esc_url( $item['url'] ); ?>"
         class="dths-mobile-nav-link<?php echo $active ? ' is-active' : ''; ?>">
        <?php echo esc_html( $item['label'] ); ?>
        <span class="dths-mobile-nav-arrow">→</span>
      </a>
      <?php endforeach; ?>
    </nav>
    <div class="dths-mobile-nav-footer">
      <a href="<?php echo esc_url( wc_get_cart_url() ); ?>" class="dths-btn-primary" style="width:100%;justify-content:center">
        🛍 View Bag (<?php echo absint( $cart_count ); ?>)
      </a>
      <?php if ( ! is_user_logged_in() ) : ?>
      <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="dths-btn-secondary" style="width:100%;justify-content:center;margin-top:10px">
        Sign In
      </a>
      <?php endif; ?>
      <div class="dths-mobile-nav-tsa">
        <a href="<?php echo esc_url( home_url( '/' ) ); ?>">← Back to TeeShirtAli.com</a>
      </div>
    </div>
  </div>
</div>
<div class="dths-mobile-nav-backdrop" id="dths-mobile-backdrop"></div>
