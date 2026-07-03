<?php
/**
 * Dutchtown Griffins — Standalone Branded Footer
 * Included in all school page templates via:  include __DIR__ . '/footer.php';
 * Replaces TSA Flatsome footer entirely on Dutchtown pages.
 */
?>

<!-- ===== DUTCHTOWN FOOTER ===== -->
<footer class="dths-footer" id="dths-footer" role="contentinfo">
  <div class="dths-footer-inner">
    <div class="dths-shell">

      <!-- Top Grid -->
      <div class="dths-footer-grid">

        <!-- Brand Column -->
        <div class="dths-footer-brand-col">
          <a href="<?php echo esc_url( home_url( '/schools/dutchtown/' ) ); ?>" class="dths-footer-logo" aria-label="Dutchtown Griffins Home">
            <div class="dths-header-mark" style="width:44px;height:44px;font-size:16px">DG</div>
            <div class="dths-header-logo-text">
              <span class="dths-header-school" style="font-size:18px">Dutchtown</span>
              <span class="dths-header-sub">Griffins · Official Merch</span>
            </div>
          </a>
          <p class="dths-footer-desc">
            Premium school spirit merchandise for Dutchtown High School students, parents, and supporters. New drops every two weeks.
          </p>
          <div class="dths-footer-brand-colors">
            <span class="dths-footer-swatch" style="background:#592c82" title="Pantone 268C"></span>
            <span class="dths-footer-swatch" style="background:#c7c9c8" title="Cool Gray 3"></span>
            <span class="dths-footer-swatch" style="background:#09090b;border:1px solid rgba(255,255,255,.15)" title="Black"></span>
            <span class="dths-footer-swatch-label">Pantone 268C · Cool Gray 3</span>
          </div>
        </div>

        <!-- Shop Column -->
        <div class="dths-footer-col">
          <div class="dths-footer-col-heading">Shop</div>
          <ul>
            <li><a href="<?php echo esc_url( home_url( '/schools/dutchtown/color-guard/' ) ); ?>">Color Guard</a></li>
            <li><a href="<?php echo esc_url( home_url( '/schools/dutchtown/band/' ) ); ?>">Marching Band</a></li>
            <li><a href="<?php echo esc_url( home_url( '/schools/dutchtown/programs/' ) ); ?>">All Programs</a></li>
            <li><a href="<?php echo esc_url( home_url( '/schools/dutchtown/drops/' ) ); ?>">Drop Calendar</a></li>
          </ul>
        </div>

        <!-- Customer Portal Column -->
        <div class="dths-footer-col">
          <div class="dths-footer-col-heading">Customer Portal</div>
          <ul>
            <?php if ( class_exists( 'WooCommerce' ) ) : ?>
            <li><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>">Sign In</a></li>
            <li><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>">Create Account</a></li>
            <li><a href="<?php echo esc_url( wc_get_cart_url() ); ?>">My Bag</a></li>
            <li><a href="<?php echo esc_url( wc_get_account_endpoint_url( 'orders' ) ); ?>">Order History</a></li>
            <?php endif; ?>
          </ul>
        </div>

        <!-- Help Column -->
        <div class="dths-footer-col">
          <div class="dths-footer-col-heading">Help</div>
          <ul>
            <li><a href="<?php echo esc_url( home_url( '/sizing-guide/' ) ); ?>">Sizing Guide</a></li>
            <li><a href="<?php echo esc_url( home_url( '/shipping-policy/' ) ); ?>">Shipping Policy</a></li>
            <li><a href="<?php echo esc_url( home_url( '/returns/' ) ); ?>">Returns</a></li>
            <li><a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">FAQ</a></li>
          </ul>
        </div>

      </div><!-- /dths-footer-grid -->

      <!-- Divider -->
      <div class="dths-footer-divider"></div>

      <!-- Bottom Bar -->
      <div class="dths-footer-bottom">
        <div class="dths-footer-copy">
          &copy; <?php echo date( 'Y' ); ?> Dutchtown High School &mdash; All Rights Reserved
        </div>
        <div class="dths-footer-powered">
          <span>Powered by</span>
          <a href="<?php echo esc_url( home_url( '/' ) ); ?>" class="dths-footer-tsa-link" aria-label="Tee Shirt Ali">
            <svg width="14" height="14" viewBox="0 0 14 14" fill="none" aria-hidden="true"><circle cx="7" cy="7" r="6.5" stroke="currentColor" stroke-opacity=".5"/><path d="M4 5h6M7 5v5" stroke="currentColor" stroke-width="1.5" stroke-linecap="round"/></svg>
            TeeShirtAli.com
          </a>
        </div>
        <div class="dths-footer-legal">
          <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy</a>
          <a href="<?php echo esc_url( home_url( '/terms-of-service/' ) ); ?>">Terms</a>
        </div>
      </div>

    </div>
  </div>
</footer>

<?php wp_footer(); ?>
</body>
</html>
