<?php
/**
 * Related Products — TSA static grid override.
 *
 * Renders related products with the SAME tsa-product-card markup as the
 * category archive (woocommerce/archive-product.php), so cards match exactly:
 * uncropped artwork (object-fit: contain), aligned Shop Now buttons, static
 * grid — no Flatsome slider, no cropped thumbnails.
 *
 * Child-theme path: tsa-child/woocommerce/single-product/related.php
 */

defined( 'ABSPATH' ) || exit;

if ( empty( $related_products ) ) {
	return;
}
?>
<section class="related related-products-wrapper product-section tsa-related">

	<h3 class="product-section-title product-section-title-related uppercase">
		<?php esc_html_e( 'Related products', 'woocommerce' ); ?>
	</h3>

	<ul class="tsa-product-grid tsa-product-grid--related" style="display:grid;grid-template-columns:repeat(auto-fill,minmax(220px,1fr));gap:24px 20px;list-style:none;margin:0;padding:0;max-width:none;">
		<?php
		foreach ( $related_products as $related_product ) :
			$pid       = $related_product->get_id();
			$url       = get_permalink( $pid );
			$name      = get_the_title( $pid );
			$img       = get_the_post_thumbnail_url( $pid, 'woocommerce_single' );
			if ( ! $img ) {
				$img = function_exists( 'wc_placeholder_img_src' ) ? wc_placeholder_img_src( 'woocommerce_single' ) : '';
			}
			// Hover image = first gallery image (the design shown on a shirt/hat).
			$gallery   = $related_product->get_gallery_image_ids();
			$back_img  = ! empty( $gallery ) ? wp_get_attachment_image_url( $gallery[0], 'woocommerce_single' ) : '';
			$cats      = get_the_terms( $pid, 'product_cat' );
			$cat_name  = ( $cats && ! is_wp_error( $cats ) ) ? $cats[0]->name : '';
			?>
			<li>
				<article class="tsa-product-card">
					<a href="<?php echo esc_url( $url ); ?>" class="tsa-product-card__img-wrap">
						<?php if ( $img ) : ?>
							<img class="tsa-pc-front" src="<?php echo esc_url( $img ); ?>" alt="<?php echo esc_attr( $name ); ?>" loading="lazy" />
						<?php endif; ?>
						<?php if ( $back_img ) : ?>
							<img class="tsa-pc-back" src="<?php echo esc_url( $back_img ); ?>" alt="" aria-hidden="true" loading="lazy" />
						<?php endif; ?>
					</a>
					<div class="tsa-product-card__body">
						<?php if ( $cat_name ) : ?>
						<div class="tsa-product-card__cat"><?php echo esc_html( $cat_name ); ?></div>
						<?php endif; ?>
						<h3 class="tsa-product-card__name">
							<a href="<?php echo esc_url( $url ); ?>"><?php echo esc_html( $name ); ?></a>
						</h3>
						<div class="tsa-product-card__price"><?php echo wp_kses_post( $related_product->get_price_html() ); ?></div>
						<a href="<?php echo esc_url( $url ); ?>" class="tsa-btn tsa-btn-dark tsa-btn-sm" style="width:100%;">
							<?php esc_html_e( 'Shop Now', 'tsa-child' ); ?>
						</a>
					</div>
				</article>
			</li>
		<?php endforeach; ?>
	</ul>

</section>
