<?php
/**
 * Template Name: School — Collection Store
 * Template Post Type: page
 *
 * Location: flatsome-child/schools/dutchtown/page-school-collection.php
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<!--- Dutchtown branded chrome loaded via JS body class + include --->
<?php include __DIR__ . '/header.php';

// ── LIVE STORES: add slug here to flip a store live ───────────
$live_store_slugs = [
    'dutchtown-color-guard',
    'dutchtown-band',
];

// ── School config ──────────────────────────────────────────────
$school_slug = 'dutchtown';

// ── Derive program from page hierarchy ────────────────────────
$post      = get_post();
$prog_slug = $post->post_name;
$cat_slug  = $school_slug . '-' . $prog_slug;

// ── Match WooCommerce category ────────────────────────────────
$term    = get_term_by( 'slug', $cat_slug, 'product_cat' )
        ?: get_term_by( 'slug', $prog_slug, 'product_cat' );
$is_live = in_array( $cat_slug, $live_store_slugs, true );
$tagline = $term ? get_term_meta( $term->term_id, 'tagline', true ) : '';

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

  <!-- ===== COLLECTION HERO ===== -->
  <section class="dths-coll-hero">
    <div class="dths-coll-hero-overlay"></div>
    <div class="dths-shell dths-coll-hero-inner">
      <?php if ( $is_live ) : ?>
      <span class="dths-live-badge"><span class="dths-live-dot"></span> LIVE STORE</span>
      <?php else : ?>
      <span class="dths-kicker">COMING SOON</span>
      <?php endif; ?>
      <h1 class="dths-coll-hero-title dths-display"><?php the_title(); ?></h1>
      <?php if ( $tagline ) : ?>
      <p class="dths-coll-hero-desc"><?php echo esc_html( $tagline ); ?></p>
      <?php elseif ( $term && $term->description ) : ?>
      <p class="dths-coll-hero-desc"><?php echo wp_kses_post( $term->description ); ?></p>
      <?php endif; ?>
    </div>
  </section>

  <?php if ( ! $is_live ) : ?>
  <!-- ===== COMING SOON ===== -->
  <section class="dths-section" style="text-align:center">
    <div class="dths-shell" style="max-width:600px">
      <div style="font-size:64px;margin-bottom:16px">🔒</div>
      <div class="dths-display" style="font-size:clamp(32px,5vw,64px);color:#fff;text-transform:uppercase">
        <?php echo esc_html( strtoupper( get_the_title() ) ); ?><br>ARRIVES SOON
      </div>
      <p style="color:var(--dths-muted);margin-top:16px;font-size:16px;line-height:1.7">
        This store is being built now. Create a free account to get early access when it drops.
      </p>
      <div style="display:flex;gap:12px;justify-content:center;flex-wrap:wrap;margin-top:28px">
        <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="dths-btn-primary">GET NOTIFIED →</a>
        <a href="<?php echo esc_url( home_url( '/schools/dutchtown/' ) ); ?>" class="dths-btn-secondary">← BACK TO DUTCHTOWN</a>
      </div>
    </div>
  </section>

  <?php else : ?>
  <!-- ===== LIVE STORE ===== -->
  <section class="dths-section" style="padding-top:48px">
    <div class="dths-shell">

      <?php if ( $term ) :
        $pids = get_objects_in_term( $term->term_id, 'product_cat' );
        $tags = [];
        foreach ( (array) $pids as $pid ) {
          $ptags = get_the_terms( $pid, 'product_tag' );
          if ( $ptags && ! is_wp_error( $ptags ) ) {
            foreach ( $ptags as $t ) $tags[ $t->slug ] = $t->name;
          }
        }
        if ( ! empty( $tags ) ) : ?>
      <div class="dths-filter-pills" id="dths-filter-pills">
        <button class="dths-pill dths-pill-active" data-filter="all">All Items</button>
        <?php foreach ( $tags as $slug => $name ) : ?>
        <button class="dths-pill dths-pill-inactive" data-filter="<?php echo esc_attr( $slug ); ?>"><?php echo esc_html( $name ); ?></button>
        <?php endforeach; ?>
      </div>
        <?php endif; ?>

        <?php
        $q = new WP_Query( [
          'post_type'      => 'product',
          'posts_per_page' => 12,
          'tax_query'      => [ [
            'taxonomy' => 'product_cat',
            'field'    => 'term_id',
            'terms'    => $term->term_id,
          ] ],
        ] );
        if ( $q->have_posts() ) :
          woocommerce_product_loop_start();
          while ( $q->have_posts() ) : $q->the_post();
            wc_get_template_part( 'content', 'product' );
          endwhile;
          woocommerce_product_loop_end();
          wp_reset_postdata();
        else : ?>
        <div style="text-align:center;padding:80px 24px">
          <div style="font-size:48px;margin-bottom:16px">👕</div>
          <div class="dths-display" style="font-size:36px;color:#fff;text-transform:uppercase">Products Coming Soon</div>
          <p style="color:var(--dths-muted);margin-top:12px;font-size:15px">Check back after the next drop.</p>
        </div>
        <?php endif;
      else : ?>
      <p style="color:var(--dths-muted);text-align:center;padding:60px 0">Store category not found. Check that slug <strong><?php echo esc_html( $cat_slug ); ?></strong> exists in WooCommerce.</p>
      <?php endif; ?>

    </div>
  </section>
  <?php endif; ?>

</div><!-- /.dths-page -->

<?php include __DIR__ . '/footer.php'; ?>
