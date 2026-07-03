<?php
/**
 * Product detail page (level 3) — one garment: example image, all colors,
 * sizes, price, and Design & Buy. URL: /apparel/{garment-slug}/
 * Clicking a color swatch swaps the example image to that color's mockup.
 */
defined( 'ABSPATH' ) || exit;
get_header();

while ( have_posts() ) : the_post();
    $gid     = get_the_ID();
    $brand   = get_post_meta( $gid, '_ac_brand', true );
    $styles  = get_post_meta( $gid, '_ac_styles', true ) ?: [];
    $mockups = get_post_meta( $gid, '_ac_mockup_images', true ) ?: [];
    $price   = (float) get_post_meta( $gid, '_ac_base_price', true );
    $colors  = ( ! empty( $styles[0]['colors'] ) && is_array( $styles[0]['colors'] ) ) ? $styles[0]['colors'] : [];
    $sizes   = [];
    foreach ( $colors as $c ) { foreach ( (array) ( $c['sizes_available'] ?? [] ) as $s ) { $sizes[ $s ] = 1; } }
    $hero = get_the_post_thumbnail_url( $gid, 'large' );
    if ( ! $hero && $mockups ) { $first = reset( $mockups ); $hero = $first['front'] ?? ''; }
    $cfg_url = home_url( '/configurator/?garment_id=' . $gid );
    $buyable = $price > 0;
    ?>
    <section class="tsa-pd">
        <div class="tsa-pd__media">
            <img id="tsa-pd-img" src="<?php echo esc_url( $hero ); ?>" alt="<?php echo esc_attr( get_the_title() ); ?>">
        </div>
        <div class="tsa-pd__info">
            <?php if ( $brand ) : ?>
            <div class="tsa-pd__brand"><a href="<?php echo esc_url( home_url( '/blank-apparel/' . sanitize_title( $brand ) . '/' ) ); ?>"><?php echo esc_html( $brand ); ?></a></div>
            <?php endif; ?>
            <h1 class="tsa-pd__title"><?php the_title(); ?></h1>
            <?php if ( $buyable ) : ?><div class="tsa-pd__price">From <?php echo wp_kses_post( wc_price( $price ) ); ?></div><?php endif; ?>

            <?php if ( $colors ) :
                $first_color = $colors[0]['name'] ?? ''; ?>
            <div class="tsa-pd__color" style="font-size:20px;font-weight:700;color:var(--tsa-dark);margin:-6px 0 18px;">Color: <span id="tsa-pd-color-name"><?php echo esc_html( $first_color ); ?></span></div>
            <div class="tsa-pd__label"><?php echo count( $colors ); ?> colors</div>
            <div class="tsa-pd__swatches">
                <?php foreach ( $colors as $i => $c ) :
                    $cn = $c['name'] ?? ''; $front = $mockups[ $cn ]['front'] ?? ''; ?>
                    <button type="button" class="tsa-pd__swatch<?php echo $i === 0 ? ' is-active' : ''; ?>" title="<?php echo esc_attr( $cn ); ?>"
                            style="background:<?php echo esc_attr( $c['hex'] ?? '#888' ); ?>"
                            data-img="<?php echo esc_url( $front ); ?>" data-name="<?php echo esc_attr( $cn ); ?>"></button>
                <?php endforeach; ?>
            </div>
            <?php endif; ?>

            <?php if ( $sizes ) : ?>
            <div class="tsa-pd__label">Sizes</div>
            <div class="tsa-pd__sizes"><?php foreach ( array_keys( $sizes ) as $s ) echo '<span>' . esc_html( $s ) . '</span>'; ?></div>
            <?php endif; ?>

            <?php if ( $buyable ) : ?>
                <a class="tsa-btn tsa-btn-primary tsa-pd__cta" href="<?php echo esc_url( $cfg_url ); ?>">Design &amp; Buy</a>
            <?php else : ?>
                <span class="tsa-btn tsa-pd__cta is-disabled" aria-disabled="true">Pricing coming soon</span>
            <?php endif; ?>
        </div>
    </section>

    <?php
    // Description — shows only if the garment has content (vendor description).
    $desc_html = apply_filters( 'the_content', get_the_content() );
    if ( trim( wp_strip_all_tags( $desc_html ) ) !== '' ) : ?>
    <section class="tsa-section tsa-pd-desc" style="background:#fff;">
        <div style="max-width:760px;margin:0 auto;">
            <h2 style="font-size:22px;margin:0 0 14px;color:var(--tsa-dark,#252124);">Description</h2>
            <div class="tsa-pd-desc__body" style="color:var(--tsa-muted,#6d6268);line-height:1.65;">
                <?php echo wp_kses_post( $desc_html ); ?>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <?php
    // Size Chart (Specs) — manual per-garment table; renders only when filled.
    $chart_raw = trim( (string) get_post_meta( $gid, '_tsa_size_chart', true ) );
    $chart_rows = $chart_raw === '' ? [] : array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', $chart_raw ) ), function ( $l ) { return $l !== ''; } ) );
    if ( $chart_rows ) : ?>
    <section class="tsa-section tsa-pd-specs" style="background:#faf9fa;">
        <div style="max-width:760px;margin:0 auto;">
            <h2 style="font-size:22px;margin:0 0 14px;color:var(--tsa-dark,#252124);">Size Chart</h2>
            <div style="overflow-x:auto;">
                <table style="width:100%;border-collapse:collapse;font-size:14px;color:var(--tsa-dark,#252124);">
                    <?php foreach ( $chart_rows as $ri => $line ) :
                        $cells = array_map( 'trim', explode( '|', $line ) ); ?>
                        <tr>
                            <?php foreach ( $cells as $ci => $cell ) :
                                $is_head = ( $ri === 0 || $ci === 0 );
                                $tag     = $is_head ? 'th' : 'td'; ?>
                                <<?php echo $tag; ?> style="border:1px solid var(--tsa-line,#e5e1e3);padding:8px 12px;text-align:<?php echo $ci === 0 ? 'left' : 'center'; ?>;<?php echo $is_head ? 'background:#f2eef0;font-weight:700;' : ''; ?>"><?php echo esc_html( $cell ); ?></<?php echo $tag; ?>>
                            <?php endforeach; ?>
                        </tr>
                    <?php endforeach; ?>
                </table>
            </div>
        </div>
    </section>
    <?php endif; ?>

    <style>.tsa-pd__swatch.is-active{ outline:2px solid var(--tsa-pink); outline-offset:2px; }</style>
    <script>
    (function(){
        var img=document.getElementById('tsa-pd-img');
        var nameEl=document.getElementById('tsa-pd-color-name');
        var sw=document.querySelectorAll('.tsa-pd__swatch');
        sw.forEach(function(b){
            b.addEventListener('click',function(){
                var u=b.getAttribute('data-img'); if(u&&img) img.src=u;
                var n=b.getAttribute('data-name'); if(n&&nameEl) nameEl.textContent=n;
                sw.forEach(function(x){ x.classList.remove('is-active'); });
                b.classList.add('is-active');
            });
        });
    })();
    </script>
    <?php
endwhile;
get_footer();
