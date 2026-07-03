<?php
/**
 * Template Name: School — Dutchtown Home
 * Template Post Type: page
 *
 * Location: flatsome-child/schools/dutchtown/page-school-dutchtown.php
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<!--- Dutchtown branded chrome loaded via JS body class + include --->
<?php include __DIR__ . '/header.php';

// ── LIVE STORES ────────────────────────────────────────────────
$live_store_slugs = [
    'dutchtown-color-guard',
    'dutchtown-band',
];

$school      = 'dutchtown';
$all_cats    = tsa_get_school_collections( $school, false );
$live_cats   = array_values( array_filter( $all_cats, function($c) use ($live_store_slugs) { return in_array( $c->slug, $live_store_slugs, true ); } ) );
$coming_soon = array_values( array_filter( $all_cats, function($c) use ($live_store_slugs) { return ! in_array( $c->slug, $live_store_slugs, true ); } ) );
$next_drop   = tsa_get_next_drop( $school );

$featured = new WP_Query( [
    'post_type'      => 'product',
    'posts_per_page' => 8,
    'tax_query'      => [
        'relation' => 'AND',
        [ 'taxonomy' => 'product_visibility', 'field' => 'name', 'terms' => 'featured' ],
        ! empty( $live_cats ) ? [ 'taxonomy' => 'product_cat', 'field' => 'slug', 'terms' => array_map( function($c) { return $c->slug; }, $live_cats ) ] : [],
    ],
] );

// ── Nav ────────────────────────────────────────────────────────
$nav_items = [
    [ 'label' => 'Home',        'url' => home_url( '/schools/dutchtown/' ) ],
    [ 'label' => 'Color Guard', 'url' => home_url( '/schools/dutchtown/color-guard/' ) ],
    [ 'label' => 'Band',        'url' => home_url( '/schools/dutchtown/band/' ) ],
    [ 'label' => 'Programs',    'url' => home_url( '/schools/dutchtown/programs/' ) ],
    [ 'label' => 'Drops',       'url' => home_url( '/schools/dutchtown/drops/' ) ],
];
?>

<div class="dths-page">

  <!-- ===== HERO ===== -->
  <section class="dths-hero dths-grain">
    <div class="dths-hero-overlay"></div>
    <div class="dths-shell dths-hero-inner">
      <span class="dths-kicker">NEW · Color Guard + Band · F/W Collection</span>
      <h1 class="dths-hero-h1 dths-display">
        BUILT FOR THE<br>
        <span class="dths-hero-gradient">DUTCHTOWN ROAR.</span>
      </h1>
      <p class="dths-hero-sub">Premium drops powered by Tee Shirt Ali — performance-grade gear engineered for the Griffins, built for game day, finals week, and every roar in between.</p>
      <div class="dths-hero-actions">
        <?php foreach ( array_slice( $live_cats, 0, 2 ) as $i => $cat ) :
          $page_slug = str_replace( $school . '-', '', $cat->slug );
        ?>
        <a href="<?php echo esc_url( home_url( '/schools/' . $school . '/' . $page_slug . '/' ) ); ?>"
           class="<?php echo $i === 0 ? 'dths-btn-primary' : 'dths-btn-secondary'; ?>">
          SHOP <?php echo esc_html( strtoupper( str_replace( 'Dutchtown — ', '', $cat->name ) ) ); ?> &nbsp;→
        </a>
        <?php endforeach; ?>
      </div>
      <div class="dths-stats-grid">
        <div class="dths-stat dths-glass"><div class="dths-stat-k">Programs</div><div class="dths-stat-v dths-display"><?php echo count( $live_cats ); ?></div><div class="dths-stat-s">Active stores</div></div>
        <div class="dths-stat dths-glass"><div class="dths-stat-k">Styles</div><div class="dths-stat-v dths-display">12+</div><div class="dths-stat-s">Available now</div></div>
        <div class="dths-stat dths-glass"><div class="dths-stat-k">Drops</div><div class="dths-stat-v dths-display">Bi-weekly</div><div class="dths-stat-s">Schedule</div></div>
        <div class="dths-stat dths-glass"><div class="dths-stat-k">Griffin</div><div class="dths-stat-v dths-display">268C</div><div class="dths-stat-s">Pantone purple</div></div>
      </div>
    </div>
  </section>

  <!-- ===== MARQUEE ===== -->
  <div class="dths-marquee-wrap" aria-hidden="true">
    <div class="dths-marquee-track">
      <?php
      $dths_store_id = function_exists( 'tsa_sb_find_store_by_slug' ) ? tsa_sb_find_store_by_slug( $school ) : 0;
      $dths_ticker   = $dths_store_id ? get_post_meta( $dths_store_id, '_tsa_school_ticker', true ) : '';
      $items = $dths_ticker
          ? array_values( array_filter( array_map( 'trim', preg_split( '/\r\n|\r|\n/', (string) $dths_ticker ) ) ) )
          : [ 'GRIFFIN PRIDE', 'PERFORMANCE FIRST', 'DROPS WEEKLY', 'BUILT IN GA', 'TEE SHIRT ALI', 'DTHS · 268C' ];
      for ( $r = 0; $r < 2; $r++ ) foreach ( $items as $item ) :
      ?><span class="dths-marquee-item dths-display"><?php echo esc_html( strtoupper( $item ) ); ?><span class="dths-marquee-dot">✦</span></span><?php
      endforeach; ?>
    </div>
  </div>

  <!-- ===== NEXT DROP COUNTDOWN ===== -->
  <?php if ( $next_drop ) :
    $drop_date = get_post_meta( $next_drop->ID, '_drop_date', true );
    $drop_sub  = get_post_meta( $next_drop->ID, '_drop_subtitle', true );
    $coll_slug = get_post_meta( $next_drop->ID, '_collection_slug', true );
    $page_slug = str_replace( $school . '-', '', $coll_slug );
    $coll_url  = $page_slug ? home_url( '/schools/' . $school . '/' . $page_slug . '/' ) : home_url( '/schools/' . $school . '/drops/' );
  ?>
  <section class="dths-section" style="background:radial-gradient(circle at 80% 50%,rgba(139,92,246,.18),transparent 45%)">
    <div class="dths-shell">
      <div class="dths-drop-card dths-glass">
        <div class="dths-drop-glow"></div>
        <div class="dths-drop-grid">
          <div>
            <span class="dths-kicker">NEXT DROP</span>
            <div class="dths-drop-name dths-display"><?php echo esc_html( get_the_title( $next_drop ) ); ?></div>
            <?php if ( $drop_sub ) : ?><p class="dths-drop-sub"><?php echo esc_html( $drop_sub ); ?></p><?php endif; ?>
            <a href="<?php echo esc_url( $coll_url ); ?>" class="dths-btn-primary" style="margin-top:24px;display:inline-flex">NOTIFY ME · SHOP NOW &nbsp;→</a>
          </div>
          <div class="dths-cd-wrap">
            <div class="dths-cd-label">Drop In</div>
            <div class="dths-cd-units dths-countdown" data-target="<?php echo esc_attr( $drop_date ); ?>">
              <div class="dths-cu"><div class="dths-cn" data-unit="days">00</div><div class="dths-cl">Days</div></div>
              <div class="dths-cu"><div class="dths-cn" data-unit="hours">00</div><div class="dths-cl">Hrs</div></div>
              <div class="dths-cu"><div class="dths-cn" data-unit="minutes">00</div><div class="dths-cl">Min</div></div>
              <div class="dths-cu"><div class="dths-cn" data-unit="seconds">00</div><div class="dths-cl">Sec</div></div>
            </div>
          </div>
        </div>
      </div>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===== COLLECTIONS ===== -->
  <section class="dths-section">
    <div class="dths-shell">
      <div class="dths-section-split">
        <div>
          <span class="dths-kicker">Programs</span>
          <div class="dths-section-h2 dths-display">THE COLLECTIONS</div>
        </div>
        <p class="dths-section-desc">
          Color Guard and Band are live now.
          <a href="<?php echo esc_url( home_url( '/schools/dutchtown/programs/' ) ); ?>">See all programs →</a>
        </p>
      </div>

      <div class="dths-collections-grid">
        <?php foreach ( $live_cats as $cat ) :
          $tagline   = get_term_meta( $cat->term_id, 'tagline', true );
          $thumb_id  = get_term_meta( $cat->term_id, 'thumbnail_id', true );
          $thumb_url = $thumb_id ? wp_get_attachment_image_url( $thumb_id, 'large' ) : '';
          $page_slug = str_replace( $school . '-', '', $cat->slug );
          $store_url = home_url( '/schools/' . $school . '/' . $page_slug . '/' );
          $clean_name = str_replace( 'Dutchtown — ', '', $cat->name );
        ?>
        <a class="dths-coll-card dths-card-hover" href="<?php echo esc_url( $store_url ); ?>">
          <?php if ( $thumb_url ) : ?>
          <img class="dths-coll-img" src="<?php echo esc_url( $thumb_url ); ?>" alt="<?php echo esc_attr( $clean_name ); ?>"/>
          <?php else : ?>
          <div class="dths-coll-placeholder">
            <div class="dths-coll-placeholder-letter"><?php echo esc_html( strtoupper( substr( $clean_name, 0, 2 ) ) ); ?></div>
          </div>
          <?php endif; ?>
          <div class="dths-coll-overlay"></div>
          <div class="dths-coll-arrow">→</div>
          <div class="dths-coll-body">
            <div class="dths-coll-sup">Dutchtown Griffins · <span class="dths-live-inline">● Live</span></div>
            <div class="dths-coll-name dths-display"><?php echo esc_html( $clean_name ); ?></div>
            <?php if ( $tagline ) : ?><div class="dths-coll-tag"><?php echo esc_html( $tagline ); ?></div><?php endif; ?>
          </div>
        </a>
        <?php endforeach; ?>
      </div>

      <!-- ECOSYSTEM PREVIEW -->
      <?php if ( ! empty( $coming_soon ) ) : ?>
      <div style="margin-top:56px">
        <div style="display:flex;align-items:flex-end;justify-content:space-between;gap:16px;margin-bottom:20px;flex-wrap:wrap">
          <div>
            <div style="font-size:11px;letter-spacing:.26em;color:rgba(255,255,255,.38);text-transform:uppercase">On Deck</div>
            <div class="dths-display" style="font-size:clamp(22px,3vw,32px);color:#fff;margin-top:4px">THE FULL SCHOOL-WIDE ECOSYSTEM</div>
          </div>
          <a class="dths-btn-ghost" href="<?php echo esc_url( home_url( '/schools/dutchtown/programs/' ) ); ?>">VIEW ALL PROGRAMS &nbsp;→</a>
        </div>
        <div class="dths-eco-grid">
          <?php foreach ( array_slice( $coming_soon, 0, 10 ) as $cat ) :
            $clean_name = str_replace( 'Dutchtown — ', '', $cat->name );
          ?>
          <a class="dths-eco-tile" href="<?php echo esc_url( home_url( '/schools/dutchtown/programs/' ) ); ?>">
            <div class="dths-eco-tile-inner">
              <span class="dths-eco-tile-soon">Soon</span>
              <span class="dths-eco-tile-name dths-display"><?php echo esc_html( $clean_name ); ?></span>
            </div>
          </a>
          <?php endforeach; ?>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>

  <!-- ===== FEATURED PRODUCTS ===== -->
  <?php if ( $featured->have_posts() ) : ?>
  <section class="dths-section">
    <div class="dths-shell">
      <div class="dths-section-split">
        <div>
          <span class="dths-kicker">FEATURED · LIMITED</span>
          <div class="dths-section-h2 dths-display">THIS WEEK'S HEAT</div>
        </div>
        <a class="dths-btn-ghost" href="<?php echo esc_url( home_url( '/schools/dutchtown/color-guard/' ) ); ?>">VIEW ALL →</a>
      </div>
      <?php woocommerce_product_loop_start(); ?>
      <?php while ( $featured->have_posts() ) : $featured->the_post(); wc_get_template_part( 'content', 'product' ); endwhile; wp_reset_postdata(); ?>
      <?php woocommerce_product_loop_end(); ?>
    </div>
  </section>
  <?php endif; ?>

  <!-- ===== VALUE PROPS ===== -->
  <section class="dths-section">
    <div class="dths-shell">
      <div class="dths-vp-grid">
        <?php foreach ( [
          [ '✨', 'Designed for Performance', 'Premium fabrics, embroidered Griffin crests, and drop-quality printwork built to perform on and off the competition floor.' ],
          [ '⚡', 'Custom Personalization',    'Add your name and number on select pieces straight from the product page. Your identity, your gear.' ],
          [ '🚀', 'Fast Fulfilment',           'Ships in 5–7 days via Tee Shirt Ali\'s production network. Built in Georgia, delivered to your door.' ],
        ] as $vp ) : ?>
        <div class="dths-vp-card dths-glass">
          <div class="dths-vp-icon"><?php echo $vp[0]; ?></div>
          <div class="dths-vp-title dths-display"><?php echo esc_html( $vp[1] ); ?></div>
          <p class="dths-vp-text"><?php echo esc_html( $vp[2] ); ?></p>
        </div>
        <?php endforeach; ?>
      </div>
    </div>
  </section>

  <!-- ===== FINAL CTA ===== -->
  <section class="dths-section" style="padding-bottom:112px">
    <div class="dths-shell">
      <div class="dths-final-card">
        <div class="dths-final-glow"></div>
        <div class="dths-final-body">
          <span class="dths-kicker">JOIN THE PRIDE</span>
          <div class="dths-final-h2 dths-display">ENTER THE<br>GRIFFINS CIRCLE.</div>
          <p class="dths-final-sub">Get early access to drops, exclusive personalization, and members-only capsules.</p>
          <div class="dths-final-actions">
            <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="dths-btn-primary">CREATE YOUR ACCOUNT &nbsp;→</a>
            <a href="<?php echo esc_url( home_url( '/schools/dutchtown/drops/' ) ); ?>" class="dths-btn-secondary">SEE UPCOMING DROPS</a>
          </div>
        </div>
      </div>
    </div>
  </section>

</div><!-- /.dths-page -->

<?php include __DIR__ . '/footer.php'; ?>
