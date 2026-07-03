<?php
/**
 * TSA WooCommerce Shop Archive Override
 *
 * Replaces the default Flatsome shop archive with TSA-branded layout.
 * File must live at: tsa-child/woocommerce/archive-product.php
 */

defined( 'ABSPATH' ) || exit;

get_header( 'shop' );

$shop_page_id    = wc_get_page_id( 'shop' );
$shop_title      = $shop_page_id ? get_the_title( $shop_page_id ) : __( 'Shop', 'tsa-child' );
$shop_desc       = $shop_page_id ? wc_format_content( get_post_field( 'post_content', $shop_page_id ) ) : '';
$current_cat     = is_product_category() ? get_queried_object() : null;
?>

<!-- ══════════════════════════════════════
     SHOP HEADER
══════════════════════════════════════ -->
<section class="tsa-shop-hero" style="padding:72px 0 48px; background: linear-gradient(135deg, var(--tsa-pink-soft) 0%, #fff 100%); text-align:center;">
    <div class="tsa-container" style="max-width:860px; margin:0 auto; padding:0 24px;">
        <?php if ( $current_cat ) : ?>
            <div class="tsa-kicker"><?php esc_html_e( 'Category', 'tsa-child' ); ?></div>
            <h1><?php echo esc_html( $current_cat->name ); ?></h1>
            <?php if ( $current_cat->description ) : ?>
                <p style="color:var(--tsa-muted); font-size:18px; line-height:1.55; margin:0; max-width:600px; margin:0 auto;">
                    <?php echo wp_kses_post( $current_cat->description ); ?>
                </p>
            <?php endif; ?>
        <?php else : ?>
            <div class="tsa-kicker"><?php esc_html_e( 'Apparel & Gear', 'tsa-child' ); ?></div>
            <h1><?php echo esc_html( $shop_title ); ?></h1>
            <?php if ( $shop_desc ) : ?>
                <p style="color:var(--tsa-muted); font-size:18px; line-height:1.55; max-width:600px; margin:16px auto 0;">
                    <?php echo wp_kses_post( $shop_desc ); ?>
                </p>
            <?php endif; ?>
        <?php endif; ?>
    </div>
</section>

<!-- ══════════════════════════════════════
     BREADCRUMB
══════════════════════════════════════ -->
<?php if ( function_exists( 'woocommerce_breadcrumb' ) ) : ?>
<div class="tsa-breadcrumb">
    <div class="tsa-container" style="max-width:1320px; margin:0 auto; padding:0 24px;">
        <?php woocommerce_breadcrumb( [
            'delimiter'   => '<span class="sep">/</span>',
            'wrap_before' => '',
            'wrap_after'  => '',
            'before'      => '',
            'after'       => '',
        ] ); ?>
    </div>
</div>
<?php endif; ?>

<!-- ══════════════════════════════════════
     MAIN SHOP CONTENT
══════════════════════════════════════ -->
<div class="tsa-shop-wrap" style="max-width:1320px; margin:0 auto; padding:48px 24px 80px;">

    <?php if ( have_posts() ) : ?>

    <!-- Toolbar: results count + sort -->
    <div class="tsa-shop-toolbar">

        <div class="tsa-shop-toolbar__left">
            <?php woocommerce_result_count(); ?>
        </div>

        <div class="tsa-shop-toolbar__right">
            <?php woocommerce_catalog_ordering(); ?>
        </div>
    </div>

    <!-- Product grid -->
    <ul class="tsa-product-grid tsa-product-grid--shop" style="margin-top:32px;">
        <?php while ( have_posts() ) : the_post(); ?>
        <?php
        global $product;
        if ( ! $product ) continue;
        $img        = has_post_thumbnail() ? get_the_post_thumbnail_url( null, 'woocommerce_single' ) : '';
        $gallery    = $product->get_gallery_image_ids();
        $back_img   = ! empty( $gallery ) ? wp_get_attachment_image_url( $gallery[0], 'woocommerce_single' ) : '';
        $name       = get_the_title();
        $permalink  = get_permalink();
        $price_html = $product->get_price_html();
        $on_sale    = $product->is_on_sale();
        $cats       = get_the_terms( get_the_ID(), 'product_cat' );
        $cat_slugs  = is_array( $cats ) ? implode( ' ', wp_list_pluck( $cats, 'slug' ) ) : '';
        $badge_html = '';
        if ( $on_sale )         $badge_html = '<span class="tsa-product-card__badge tsa-product-card__badge--sale">Sale</span>';
        elseif ( $product->is_featured() ) $badge_html = '<span class="tsa-product-card__badge tsa-product-card__badge--new">New</span>';
        ?>
        <li data-category="<?php echo esc_attr( $cat_slugs ); ?>">
            <article class="tsa-product-card <?php echo $on_sale ? 'tsa-product-card--sale' : ''; ?>">
                <a href="<?php echo esc_url( $permalink ); ?>" class="tsa-product-card__img-wrap">
                    <?php if ( $img ) : ?>
                        <img class="tsa-pc-front" src="<?php echo esc_url( $img ); ?>"
                             alt="<?php echo esc_attr( $name ); ?>"
                             loading="lazy" />
                    <?php else : ?>
                        <div style="height:100%; background:var(--tsa-pink-soft); display:flex; align-items:center; justify-content:center; font-size:48px;">👕</div>
                    <?php endif; ?>
                    <?php if ( $back_img ) : ?>
                        <img class="tsa-pc-back" src="<?php echo esc_url( $back_img ); ?>" alt="" aria-hidden="true" loading="lazy" />
                    <?php endif; ?>
                    <?php echo $badge_html; ?>
                </a>
                <div class="tsa-product-card__body">
                    <?php if ( $cats && ! is_wp_error( $cats ) ) : ?>
                    <div class="tsa-product-card__cat"><?php echo esc_html( $cats[0]->name ); ?></div>
                    <?php endif; ?>
                    <h3 class="tsa-product-card__name">
                        <a href="<?php echo esc_url( $permalink ); ?>"><?php echo esc_html( $name ); ?></a>
                    </h3>
                    <div class="tsa-product-card__price"><?php echo wp_kses_post( $price_html ); ?></div>
                    <a href="<?php echo esc_url( $permalink ); ?>" class="tsa-btn tsa-btn-dark tsa-btn-sm" style="width:100%; margin-top:12px;">
                        <?php echo $product->is_type( 'variable' ) ? esc_html__( 'Select Options', 'tsa-child' ) : esc_html__( 'Shop Now', 'tsa-child' ); ?>
                    </a>
                </div>
            </article>
        </li>
        <?php endwhile; ?>
    </ul>

    <!-- Pagination -->
    <div class="tsa-shop-pagination" style="margin-top:48px;">
        <?php the_posts_pagination( [
            'prev_text' => '← Previous',
            'next_text' => 'Next →',
        ] ); ?>
    </div>

    <?php else : ?>

    <div style="text-align:center; padding:80px 0;">
        <div style="font-size:64px; margin-bottom:24px;">🛍️</div>
        <h2><?php esc_html_e( 'No products found', 'tsa-child' ); ?></h2>
        <p style="color:var(--tsa-muted); font-size:17px; margin:12px 0 28px; line-height:1.55;">
            <?php esc_html_e( 'We couldn\'t find any products matching your selection. Try browsing all our gear.', 'tsa-child' ); ?>
        </p>
        <a href="<?php echo esc_url( wc_get_page_permalink( 'shop' ) ); ?>" class="tsa-btn tsa-btn-primary">
            <?php esc_html_e( 'View All Products', 'tsa-child' ); ?>
        </a>
    </div>

    <?php endif; ?>
</div>

<?php get_footer( 'shop' ); ?>
