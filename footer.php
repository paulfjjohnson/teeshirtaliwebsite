<?php
/**
 * TSA Custom Footer
 * Overrides Flatsome's footer.php entirely.
 */
?>
</div><!-- /.wrapper (opened in header.php) -->

<footer class="tsa-site-footer">

    <!-- ═══════════════════════════════════════
         MAIN FOOTER COLUMNS
    ═══════════════════════════════════════ -->
    <div class="tsa-footer-inner">

        <!-- Brand Column -->
        <div class="tsa-footer-col tsa-footer-col--brand">
            <div class="tsa-footer-logo">
                <?php
                $logo_id = get_theme_mod( 'custom_logo' );
                if ( $logo_id ) :
                    echo wp_get_attachment_image( $logo_id, 'full', false, [ 'class' => 'tsa-footer-logo__img' ] );
                else : ?>
                    <span class="tsa-footer-logo__text">Tee Shirt Ali</span>
                <?php endif; ?>
            </div>
            <p class="tsa-footer-tagline">Custom Apparel. School Spirit.<br>Creative Merch for Every Community.</p>
            <div class="tsa-footer-social">
                <a href="#" aria-label="Instagram" class="tsa-footer-social__link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/></svg>
                </a>
                <a href="#" aria-label="Facebook" class="tsa-footer-social__link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/></svg>
                </a>
                <a href="#" aria-label="TikTok" class="tsa-footer-social__link">
                    <svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor"><path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/></svg>
                </a>
            </div>
        </div>

        <!-- Services Column -->
        <div class="tsa-footer-col">
            <h4 class="tsa-footer-heading">Services</h4>
            <ul class="tsa-footer-links">
                <li><a href="<?php echo esc_url( home_url( '/dtf-printing/' ) ); ?>">DTF Printing</a></li>
                <li><a href="<?php echo esc_url( home_url( '/graphic-design/' ) ); ?>">Graphic Design</a></li>
                <li><a href="<?php echo esc_url( home_url( '/blank-apparel/' ) ); ?>">Blank Apparel</a></li>
                <li><a href="<?php echo esc_url( home_url( '/brand-catalog/' ) ); ?>">Brand Catalog</a></li>
                <li><a href="<?php echo esc_url( home_url( '/gang-sheet-builder/' ) ); ?>">Gang Sheet Builder</a></li>
                <li><a href="<?php echo esc_url( home_url( '/printing-capabilities/' ) ); ?>">Printing Capabilities</a></li>
                <li><a href="<?php echo esc_url( home_url( '/turnaround-times/' ) ); ?>">Turnaround Times</a></li>
            </ul>
        </div>

        <!-- Stores Column -->
        <div class="tsa-footer-col">
            <h4 class="tsa-footer-heading">Stores & Programs</h4>
            <ul class="tsa-footer-links">
                <li><a href="<?php echo esc_url( home_url( '/schools/' ) ); ?>">School Stores</a></li>
                <li><a href="<?php echo esc_url( home_url( '/team-stores/' ) ); ?>">Team Stores</a></li>
                <li><a href="<?php echo esc_url( home_url( '/fundraisers/' ) ); ?>">Fundraisers</a></li>
                <li><a href="<?php echo esc_url( home_url( '/tee-party/' ) ); ?>">Tee Party Drops</a></li>
                <li><a href="<?php echo esc_url( home_url( '/request-a-tee-party/' ) ); ?>">Request a Tee Party</a></li>
                <li><a href="<?php echo esc_url( home_url( '/design-library/' ) ); ?>">Design Library</a></li>
                <li><a href="<?php echo esc_url( home_url( '/businesses/' ) ); ?>">Business Merch</a></li>
            </ul>
        </div>

        <!-- Get Started Column -->
        <div class="tsa-footer-col">
            <h4 class="tsa-footer-heading">Get Started</h4>
            <ul class="tsa-footer-links">
                <li><a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>">Request a Quote</a></li>
                <li><a href="<?php echo esc_url( home_url( '/customer-portal/' ) ); ?>">Customer Portal</a></li>
                <li><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Shop</a></li>
            </ul>
            <div class="tsa-footer-cta">
                <a href="<?php echo esc_url( home_url( '/request-a-quote/' ) ); ?>" class="tsa-footer-cta__btn">
                    Get a Quote
                </a>
            </div>
        </div>

    </div>

    <!-- ═══════════════════════════════════════
         BOTTOM BAR
    ═══════════════════════════════════════ -->
    <div class="tsa-footer-bottom">
        <div class="tsa-footer-bottom__inner">
            <p class="tsa-footer-bottom__copy">
                &copy; <?php echo date( 'Y' ); ?> Tee Shirt Ali &mdash; All rights reserved.
                Built in Baton Rouge, LA.
            </p>
            <nav class="tsa-footer-bottom__nav" aria-label="Footer links">
                <a href="<?php echo esc_url( home_url( '/about/' ) ); ?>">About</a>
                <a href="<?php echo esc_url( home_url( '/contact/' ) ); ?>">Contact</a>
                <a href="<?php echo esc_url( home_url( '/privacy-policy/' ) ); ?>">Privacy Policy</a>
                <a href="<?php echo esc_url( home_url( '/terms-of-service/' ) ); ?>">Terms of Service</a>
                <a href="<?php echo esc_url( home_url( '/shipping-policy/' ) ); ?>">Shipping</a>
                <a href="<?php echo esc_url( home_url( '/faq/' ) ); ?>">FAQ</a>
            </nav>
        </div>
    </div>

</footer>

<?php wp_footer(); ?>
</body>
</html>
