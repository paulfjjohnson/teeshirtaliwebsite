<?php
/**
 * Single fundraiser campaign page — renders the [tsa_fundraiser] layout
 * (school-colored hero, goal meter, story, shop-to-support CTA, share).
 */
defined( 'ABSPATH' ) || exit;

get_header();

while ( have_posts() ) :
    the_post();
    echo '<section class="tsa-section" style="padding:48px 7%">';
    echo function_exists( 'tsa_fr_render' ) ? tsa_fr_render( get_the_ID() ) : '';
    echo '</section>';
endwhile;

get_footer();
