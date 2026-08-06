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
                    <span class="tsa-footer-logo__text"><?php echo esc_html( function_exists( 'tsa_biz' ) ? tsa_biz( 'name' ) : 'Tee Shirt Ali' ); ?></span>
                <?php endif; ?>
            </div>
            <p class="tsa-footer-tagline"><?php
                $tsa_tag = function_exists( 'tsa_biz' ) ? tsa_biz( 'tagline' ) : '';
                echo $tsa_tag !== '' ? esc_html( $tsa_tag ) : 'Custom Apparel. School Spirit.<br>Creative Merch for Every Community.'; // phpcs:ignore
            ?></p>
            <?php
            // Social icons — shown only for the URLs set in Business Profile.
            $tsa_socials = [
                'instagram' => [ 'Instagram', '<path d="M12 2.163c3.204 0 3.584.012 4.85.07 3.252.148 4.771 1.691 4.919 4.919.058 1.265.069 1.645.069 4.849 0 3.205-.012 3.584-.069 4.849-.149 3.225-1.664 4.771-4.919 4.919-1.266.058-1.644.07-4.85.07-3.204 0-3.584-.012-4.849-.07-3.26-.149-4.771-1.699-4.919-4.92-.058-1.265-.07-1.644-.07-4.849 0-3.204.013-3.583.07-4.849.149-3.227 1.664-4.771 4.919-4.919 1.266-.057 1.645-.069 4.849-.069zm0-2.163c-3.259 0-3.667.014-4.947.072-4.358.2-6.78 2.618-6.98 6.98-.059 1.281-.073 1.689-.073 4.948 0 3.259.014 3.668.072 4.948.2 4.358 2.618 6.78 6.98 6.98 1.281.058 1.689.072 4.948.072 3.259 0 3.668-.014 4.948-.072 4.354-.2 6.782-2.618 6.979-6.98.059-1.28.073-1.689.073-4.948 0-3.259-.014-3.667-.072-4.947-.196-4.354-2.617-6.78-6.979-6.98-1.281-.059-1.69-.073-4.949-.073zm0 5.838c-3.403 0-6.162 2.759-6.162 6.162s2.759 6.163 6.162 6.163 6.162-2.759 6.162-6.163c0-3.403-2.759-6.162-6.162-6.162zm0 10.162c-2.209 0-4-1.79-4-4 0-2.209 1.791-4 4-4s4 1.791 4 4c0 2.21-1.791 4-4 4zm6.406-11.845c-.796 0-1.441.645-1.441 1.44s.645 1.44 1.441 1.44c.795 0 1.439-.645 1.439-1.44s-.644-1.44-1.439-1.44z"/>' ],
                'facebook'  => [ 'Facebook', '<path d="M24 12.073c0-6.627-5.373-12-12-12s-12 5.373-12 12c0 5.99 4.388 10.954 10.125 11.854v-8.385H7.078v-3.47h3.047V9.43c0-3.007 1.792-4.669 4.533-4.669 1.312 0 2.686.235 2.686.235v2.953H15.83c-1.491 0-1.956.925-1.956 1.874v2.25h3.328l-.532 3.47h-2.796v8.385C19.612 23.027 24 18.062 24 12.073z"/>' ],
                'tiktok'    => [ 'TikTok', '<path d="M12.525.02c1.31-.02 2.61-.01 3.91-.02.08 1.53.63 3.09 1.75 4.17 1.12 1.11 2.7 1.62 4.24 1.79v4.03c-1.44-.05-2.89-.35-4.2-.97-.57-.26-1.1-.59-1.62-.93-.01 2.92.01 5.84-.02 8.75-.08 1.4-.54 2.79-1.35 3.94-1.31 1.92-3.58 3.17-5.91 3.21-1.43.08-2.86-.31-4.08-1.03-2.02-1.19-3.44-3.37-3.65-5.71-.02-.5-.03-1-.01-1.49.18-1.9 1.12-3.72 2.58-4.96 1.66-1.44 3.98-2.13 6.15-1.72.02 1.48-.04 2.96-.04 4.44-.99-.32-2.15-.23-3.02.37-.63.41-1.11 1.04-1.36 1.75-.21.51-.15 1.07-.14 1.61.24 1.64 1.82 3.02 3.5 2.87 1.12-.01 2.19-.66 2.77-1.61.19-.33.4-.67.41-1.06.1-1.79.06-3.57.07-5.36.01-4.03-.01-8.05.02-12.07z"/>' ],
                'x'         => [ 'X', '<path d="M18.244 2.25h3.308l-7.227 8.26 8.502 11.24H16.17l-5.214-6.817L4.99 21.75H1.68l7.73-8.835L1.254 2.25H8.08l4.713 6.231zm-1.161 17.52h1.833L7.084 4.126H5.117z"/>' ],
                'youtube'   => [ 'YouTube', '<path d="M23.498 6.186a3.016 3.016 0 0 0-2.122-2.136C19.505 3.545 12 3.545 12 3.545s-7.505 0-9.377.505A3.017 3.017 0 0 0 .502 6.186C0 8.07 0 12 0 12s0 3.93.502 5.814a3.016 3.016 0 0 0 2.122 2.136c1.871.505 9.376.505 9.376.505s7.505 0 9.377-.505a3.015 3.015 0 0 0 2.122-2.136C24 15.93 24 12 24 12s0-3.93-.502-5.814zM9.545 15.568V8.432L15.818 12z"/>' ],
            ];
            $tsa_social_out = '';
            foreach ( $tsa_socials as $tsa_sk => $tsa_sv ) {
                $tsa_url = function_exists( 'tsa_biz' ) ? tsa_biz( $tsa_sk ) : '';
                if ( ! $tsa_url ) { continue; }
                $tsa_social_out .= '<a href="' . esc_url( $tsa_url ) . '" aria-label="' . esc_attr( $tsa_sv[0] ) . '" class="tsa-footer-social__link" target="_blank" rel="noopener">'
                    . '<svg width="20" height="20" viewBox="0 0 24 24" fill="currentColor">' . $tsa_sv[1] . '</svg></a>';
            }
            if ( $tsa_social_out ) {
                echo '<div class="tsa-footer-social">' . $tsa_social_out . '</div>'; // phpcs:ignore WordPress.Security.EscapeOutput
            }
            ?>
        </div>

        <!-- Services Column -->
        <div class="tsa-footer-col">
            <h4 class="tsa-footer-heading">Services</h4>
            <ul class="tsa-footer-links">
                <li><a href="<?php echo esc_url( home_url( '/dtf-printing/' ) ); ?>">DTF Printing</a></li>
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-graphic-design.php', '/graphic-design/' ) ); ?>">Graphic Design</a></li>
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-blank-apparel.php', '/blank-apparel/' ) ); ?>">Shop by Brand</a></li>
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-gang-sheet-builder.php', '/gang-sheet-builder/' ) ); ?>">Gang Sheet Builder</a></li>
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-printing-capabilities.php', '/printing-capabilities/' ) ); ?>">Printing Capabilities</a></li>
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-turnaround-times.php', '/turnaround-times/' ) ); ?>">Turnaround Times</a></li>
            </ul>
        </div>

        <!-- Stores Column -->
        <div class="tsa-footer-col">
            <h4 class="tsa-footer-heading">Stores & Programs</h4>
            <ul class="tsa-footer-links">
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-school-directory.php', '/schools/' ) ); ?>">School Stores</a></li>
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-team-directory.php', '/team-stores/' ) ); ?>">Team Stores</a></li>
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-fundraiser.php', '/fundraisers/' ) ); ?>">Fundraisers</a></li>
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-tee-party-hub.php', '/tee-party/' ) ); ?>">Tee Party Drops</a></li>
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-tee-party-request.php', '/request-a-tee-party/' ) ); ?>">Request a Tee Party</a></li>
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-design-library.php', '/design-library/' ) ); ?>">Design Library</a></li>
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-business-directory.php', '/businesses/' ) ); ?>">Business Merch</a></li>
            </ul>
        </div>

        <!-- Get Started Column -->
        <div class="tsa-footer-col">
            <h4 class="tsa-footer-heading">Get Started</h4>
            <ul class="tsa-footer-links">
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-quote.php', '/request-a-quote/' ) ); ?>">Request a Quote</a></li>
                <li><a href="<?php echo esc_url( tsa_tpl_page_url( 'template-customer-portal.php', '/customer-portal/' ) ); ?>">Customer Portal</a></li>
                <li><a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>">Shop</a></li>
            </ul>
            <div class="tsa-footer-cta">
                <a href="<?php echo esc_url( tsa_tpl_page_url( 'template-quote.php', '/request-a-quote/' ) ); ?>" class="tsa-footer-cta__btn">
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
                <a href="<?php echo esc_url( tsa_tpl_page_url( 'template-about.php', '/about/' ) ); ?>">About</a>
                <a href="<?php echo esc_url( tsa_tpl_page_url( 'template-contact.php', '/contact/' ) ); ?>">Contact</a>
                <a href="<?php echo esc_url( tsa_tpl_page_url( 'template-privacy-policy.php', '/privacy-policy/' ) ); ?>">Privacy Policy</a>
                <a href="<?php echo esc_url( tsa_tpl_page_url( 'template-terms.php', '/terms-of-service/' ) ); ?>">Terms of Service</a>
                <a href="<?php echo esc_url( tsa_tpl_page_url( 'template-shipping-policy.php', '/shipping-policy/' ) ); ?>">Shipping</a>
                <a href="<?php echo esc_url( tsa_tpl_page_url( 'template-faq.php', '/faq/' ) ); ?>">FAQ</a>
            </nav>
        </div>
    </div>

</footer>

<?php wp_footer(); ?>
</body>
</html>
