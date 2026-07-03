<?php
/**
 * TSA Cart UI — off-canvas mini-cart drawer + themed cart.
 *
 * Replaces Flatsome's header cart dropdown with a right-side slide-in drawer
 * (TSA-themed). The drawer body is a WooCommerce fragment, so it live-updates
 * on every cart change. Quantity steppers + remove use one AJAX endpoint.
 */
defined( 'ABSPATH' ) || exit;

add_action( 'wp_enqueue_scripts', function () {
    if ( ! class_exists( 'WooCommerce' ) ) return;
    $v   = wp_get_theme()->get( 'Version' );
    $css = get_stylesheet_directory() . '/assets/css/tsa-cart.css';
    $js  = get_stylesheet_directory() . '/assets/js/tsa-cart.js';
    wp_enqueue_style( 'tsa-cart', get_stylesheet_directory_uri() . '/assets/css/tsa-cart.css', [ 'tsa-child' ], file_exists( $css ) ? filemtime( $css ) : $v );
    wp_enqueue_script( 'tsa-cart', get_stylesheet_directory_uri() . '/assets/js/tsa-cart.js', [ 'jquery' ], file_exists( $js ) ? filemtime( $js ) : $v, true );
    // "Continue shopping" target: keep the customer in the store they're buying
    // from (one-store-per-cart); else fall through to the Design Library — never
    // the default WooCommerce shop page.
    $tsa_cont_store = '';
    if ( function_exists( 'tsa_cart_store_key' ) ) {
        $sk = tsa_cart_store_key();
        if ( $sk && function_exists( 'tsa_store_url' ) ) $tsa_cont_store = (string) tsa_store_url( $sk );
    }
    wp_localize_script( 'tsa-cart', 'tsaCart', [
        'ajaxUrl'     => admin_url( 'admin-ajax.php' ),
        'nonce'       => wp_create_nonce( 'tsa_cart_nonce' ),
        'cartUrl'     => function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : '',
        'continueUrl' => $tsa_cont_store ? esc_url_raw( $tsa_cont_store ) : '',
        'shopUrl'     => esc_url_raw( home_url( '/design-library/' ) ),
    ] );
}, 20 );

/**
 * WooCommerce's own empty-cart "Return to shop" button (distinct from the JS
 * mini-cart drawer's "Continue shopping" link above) still pointed at the
 * default shop page. Reuse the same store-aware resolution so both buttons
 * agree — the customer lands back on the store they were buying from, not a
 * generic shop page.
 */
add_filter( 'woocommerce_return_to_shop_redirect', function ( $url ) {
    if ( ! function_exists( 'tsa_cart_store_key' ) || ! function_exists( 'tsa_store_url' ) ) return $url;
    $sk = tsa_cart_store_key();
    if ( ! $sk ) return $url;
    $store_url = tsa_store_url( $sk );
    return $store_url ? (string) $store_url : $url;
} );

/* Checkout page: add a title + "Back to cart" link. Flatsome hides the page
   title on Checkout (Cart keeps it), so the page looks bare with no way back.
   Sits at the very top of the checkout form, above the coupon notice. */
add_action( 'woocommerce_before_checkout_form', 'tsa_checkout_page_head', 5 );
function tsa_checkout_page_head() {
    $cart_url = function_exists( 'wc_get_cart_url' ) ? wc_get_cart_url() : home_url( '/cart/' );
    echo '<div class="tsa-checkout-head">'
       . '<a class="tsa-checkout-back" href="' . esc_url( $cart_url ) . '">&larr; Back to cart</a>'
       . '<h1 class="tsa-checkout-title">Checkout</h1>'
       . '</div>';
}

/** Drawer shell in the footer. The body is replaced by the WC fragment. */
add_action( 'wp_footer', function () {
    if ( ! class_exists( 'WooCommerce' ) ) return;
    ?>
    <div class="tsa-cart-overlay" id="tsa-cart-overlay" hidden></div>
    <aside class="tsa-cart-drawer" id="tsa-cart-drawer" aria-hidden="true" aria-label="Shopping cart" role="dialog">
        <div class="tsa-cart-drawer__head">
            <span class="tsa-cart-drawer__title">Your cart</span>
            <button type="button" class="tsa-cart-drawer__close" id="tsa-cart-close" aria-label="Close cart">&times;</button>
        </div>
        <?php echo tsa_cart_drawer_body(); // phpcs:ignore WordPress.Security.EscapeOutput ?>
    </aside>
    <?php
}, 30 );

/** Render the drawer body (line items + subtotal + actions). Also the fragment.
 *  Wrapped in a try/catch so a single misbehaving cart item (e.g. a configurator
 *  line whose product/meta isn't a clean WC_Product) can never fatal the whole
 *  page — the footer drawer must always fail safe. The real error is logged. */
function tsa_cart_drawer_body(): string {
    ob_start();
    echo '<div class="tsa-cart-drawer__body">';

    try {
        $cart = ( function_exists( 'WC' ) && WC()->cart ) ? WC()->cart : null;

        if ( ! $cart || $cart->is_empty() ) {
            echo '<div class="tsa-cart-empty"><p>Your cart is empty.</p>'
               . '<a href="' . esc_url( home_url( '/design-library/' ) ) . '" class="tsa-cart-btn tsa-cart-btn--primary">Start shopping</a></div>';
        } else {
            // Configurator/gang-sheet items get their price from a
            // before_calculate_totals hook. Force totals so get_price() + the
            // line subtotals reflect that custom price — otherwise the mini-cart
            // shows the base $0.00 on non-cart pages (where totals haven't run).
            $cart->calculate_totals();
            echo '<ul class="tsa-cart-lines">';
            foreach ( $cart->get_cart() as $key => $item ) {
                $product = $item['data'] ?? null;
                if ( ! $product instanceof WC_Product || ! $product->exists() || (int) $item['quantity'] <= 0 ) continue;
                $thumb = $product->get_image( 'woocommerce_thumbnail' );
                // Configurator items: show the design-on-garment mockup composite.
                if ( function_exists( 'tsa_configurator_cart_image' ) ) {
                    $thumb = tsa_configurator_cart_image( $item, $thumb );
                }
                $line  = $cart->get_product_subtotal( $product, $item['quantity'] );
                $meta  = wc_get_formatted_cart_item_data( $item, true );
                echo '<li class="tsa-cart-line" data-key="' . esc_attr( $key ) . '">';
                echo '<div class="tsa-cart-line__thumb">' . $thumb . '</div>';
                echo '<div class="tsa-cart-line__info">';
                echo '<div class="tsa-cart-line__name">' . esc_html( $product->get_name() ) . '</div>';
                if ( $meta ) echo '<div class="tsa-cart-line__meta">' . wp_kses_post( $meta ) . '</div>';
                echo '<div class="tsa-cart-line__row">';
                echo '<div class="tsa-cart-qty"><button type="button" class="tsa-cart-qty__btn" data-d="-1" aria-label="Decrease">−</button>'
                   . '<span class="tsa-cart-qty__v">' . (int) $item['quantity'] . '</span>'
                   . '<button type="button" class="tsa-cart-qty__btn" data-d="1" aria-label="Increase">+</button></div>';
                echo '<span class="tsa-cart-line__price">' . $line . '</span>';
                echo '</div></div>';
                echo '<button type="button" class="tsa-cart-line__remove" aria-label="Remove item">&times;</button>';
                echo '</li>';
            }
            echo '</ul>';
            echo '<div class="tsa-cart-foot">';
            // TEMP diagnostic (admin-only): which store key this cart resolves to,
            // so we can fix per-store shipping correctly. Remove once sorted.
            if ( current_user_can( 'manage_woocommerce' ) && function_exists( 'tsa_cart_store_key' ) ) {
                $tsa_sk = tsa_cart_store_key();
                echo '<div style="font-size:11px;color:#b3261e;padding:4px 6px;background:#fff1f0;border-radius:6px;margin-bottom:8px">[admin] cart store key: <strong>' . esc_html( $tsa_sk !== '' ? $tsa_sk : '(general / none)' ) . '</strong></div>';
            }
            echo '<div class="tsa-cart-subtotal"><span>Subtotal</span><strong>' . $cart->get_cart_subtotal() . '</strong></div>';
            echo '<div class="tsa-cart-actions">'
               . '<a href="' . esc_url( wc_get_cart_url() ) . '" class="tsa-cart-btn tsa-cart-btn--ghost">View cart</a>'
               . '<a href="' . esc_url( wc_get_checkout_url() ) . '" class="tsa-cart-btn tsa-cart-btn--primary">Checkout</a>'
               . '</div></div>';
        }
    } catch ( \Throwable $e ) {
        error_log( 'TSA cart drawer failed: ' . $e->getMessage() . ' @ ' . $e->getFile() . ':' . $e->getLine() );
        // Discard any partial markup from the failed render, show a safe fallback.
        ob_end_clean();
        ob_start();
        echo '<div class="tsa-cart-drawer__body"><div class="tsa-cart-empty">'
           . '<p>Your cart is being updated.</p>'
           . '<a href="' . esc_url( wc_get_cart_url() ) . '" class="tsa-cart-btn tsa-cart-btn--primary">View cart</a>';
        // Admins only: show the real error inline so we don't need debug.log.
        if ( current_user_can( 'manage_woocommerce' ) ) {
            echo '<div style="margin-top:16px;padding:12px;background:#fff1f0;border:1px solid #e3b3af;border-radius:8px;text-align:left;font-size:12px;line-height:1.5;color:#7a1f1a;word-break:break-word;">'
               . '<strong>Cart drawer error (admin-only):</strong><br>'
               . esc_html( $e->getMessage() ) . '<br>'
               . '<span style="opacity:.7">' . esc_html( basename( $e->getFile() ) . ':' . $e->getLine() ) . '</span>'
               . '</div>';
        }
        echo '</div></div>';
        return ob_get_clean();
    }

    echo '</div>';
    return ob_get_clean();
}

/** Keep the drawer body in sync via the WC fragment system. */
add_filter( 'woocommerce_add_to_cart_fragments', function ( $fragments ) {
    $fragments['.tsa-cart-drawer__body'] = tsa_cart_drawer_body();
    return $fragments;
} );

/** Qty stepper + remove (qty<=0 removes). Returns refreshed fragments. */
add_action( 'wp_ajax_tsa_cart_set_qty',        'tsa_cart_set_qty' );
add_action( 'wp_ajax_nopriv_tsa_cart_set_qty', 'tsa_cart_set_qty' );
function tsa_cart_set_qty() {
    check_ajax_referer( 'tsa_cart_nonce', 'nonce' );
    if ( ! function_exists( 'WC' ) || ! WC()->cart ) wp_send_json_error();
    $key = isset( $_POST['key'] ) ? sanitize_text_field( wp_unslash( $_POST['key'] ) ) : '';
    $qty = isset( $_POST['qty'] ) ? (int) $_POST['qty'] : -1;
    if ( $key === '' || ! WC()->cart->get_cart_item( $key ) ) wp_send_json_error();

    if ( $qty <= 0 ) WC()->cart->remove_cart_item( $key );
    else             WC()->cart->set_quantity( $key, $qty, true );
    WC()->cart->calculate_totals();

    ob_start(); woocommerce_mini_cart(); $mini = ob_get_clean();
    $fragments = apply_filters( 'woocommerce_add_to_cart_fragments', [
        'div.widget_shopping_cart_content' => '<div class="widget_shopping_cart_content">' . $mini . '</div>',
    ] );
    wp_send_json_success( [ 'fragments' => $fragments, 'cart_hash' => WC()->cart->get_cart_hash() ] );
}
