<?php
/**
 * Brand page (level 2) — renders the current tsa_brand post via the shared
 * renderer in template-brand-catalog.php. URL: /blank-apparel/{brand}/
 */
defined( 'ABSPATH' ) || exit;

define( 'TSA_BRAND_SINGLE', 1 ); // tells template-brand-catalog.php not to self-render
require_once get_stylesheet_directory() . '/template-brand-catalog.php';

get_header();
while ( have_posts() ) : the_post();
    tsa_render_brand_page( get_the_title() );
endwhile;
get_footer();
