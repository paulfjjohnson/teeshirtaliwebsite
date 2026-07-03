<?php
/**
 * Template Name: School — Drops Calendar
 * Template Post Type: page
 *
 * Location: flatsome-child/schools/dutchtown/page-school-drops.php
 */

defined( 'ABSPATH' ) || exit;

get_header();
?>
<!--- Dutchtown branded chrome loaded via JS body class + include --->
<?php include __DIR__ . '/header.php';

$school_slug = 'dutchtown';
$drops = get_posts( [
    'post_type'      => 'drop',
    'posts_per_page' => 12,
    'meta_key'       => '_drop_date',
    'orderby'        => 'meta_value',
    'order'          => 'ASC',
    'meta_query'     => [ [ 'key' => '_school_slug', 'value' => $school_slug ] ],
] );
?>

<div class="dths-page">


  <section class="dths-coll-hero" style="padding:72px 0 48px;background:radial-gradient(circle at 60% 0%,rgba(89,44,130,.32),transparent 46%),var(--dths-black)">
    <div class="dths-shell">
      <span class="dths-kicker">Drop Calendar</span>
      <h1 class="dths-display" style="font-size:clamp(52px,9vw,112px);line-height:.88;color:#fff;text-transform:uppercase;margin-top:14px">UPCOMING<br>DROPS</h1>
      <p style="color:var(--dths-muted);margin-top:16px;font-size:16px;max-width:480px;line-height:1.65">Limited-edition capsule releases and competition week specials. <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" style="color:#fff">Create a free account</a> for early access.</p>
    </div>
  </section>

  <section class="dths-section" style="padding-top:48px">
    <div class="dths-shell">
      <?php if ( $drops ) : ?>
      <div class="dths-drops-grid">
        <?php foreach ( $drops as $drop ) :
          $drop_date = get_post_meta( $drop->ID, '_drop_date', true );
          $subtitle  = get_post_meta( $drop->ID, '_drop_subtitle', true );
          $coll_slug = get_post_meta( $drop->ID, '_collection_slug', true );
          $page_slug = str_replace( $school_slug . '-', '', $coll_slug );
          $coll_url  = $page_slug ? home_url( '/schools/' . $school_slug . '/' . $page_slug . '/' ) : home_url( '/schools/' . $school_slug . '/' );
          $is_future = $drop_date && ( strtotime( $drop_date ) > time() );
        ?>
        <div class="dths-drop-item">
          <div class="dths-drop-item-glow"></div>
          <?php if ( $drop_date ) : ?>
          <div class="dths-drop-item-date"><?php echo esc_html( date_i18n( 'F j, Y · g:i A', strtotime( $drop_date ) ) ); ?> EDT</div>
          <?php endif; ?>
          <div class="dths-drop-item-name dths-display"><?php echo esc_html( get_the_title( $drop ) ); ?></div>
          <?php if ( $subtitle ) : ?><p class="dths-drop-item-sub"><?php echo esc_html( $subtitle ); ?></p><?php endif; ?>

          <?php if ( $is_future && $drop_date ) : ?>
          <div style="margin-top:20px">
            <div style="font-size:10px;letter-spacing:.24em;text-transform:uppercase;color:rgba(255,255,255,.38);margin-bottom:10px">Drops In</div>
            <div class="dths-countdown" data-target="<?php echo esc_attr( $drop_date ); ?>" style="display:flex;gap:8px">
              <div class="dths-cu"><div class="dths-cn" data-unit="days">00</div><div class="dths-cl">Days</div></div>
              <div class="dths-cu"><div class="dths-cn" data-unit="hours">00</div><div class="dths-cl">Hrs</div></div>
              <div class="dths-cu"><div class="dths-cn" data-unit="minutes">00</div><div class="dths-cl">Min</div></div>
              <div class="dths-cu"><div class="dths-cn" data-unit="seconds">00</div><div class="dths-cl">Sec</div></div>
            </div>
          </div>
          <?php else : ?>
          <div style="margin-top:16px"><span class="dths-live-badge">● LIVE NOW</span></div>
          <?php endif; ?>

          <div style="margin-top:24px;display:flex;gap:10px;flex-wrap:wrap">
            <a href="<?php echo esc_url( $coll_url ); ?>" class="dths-btn-primary">
              <?php echo $is_future ? 'NOTIFY ME →' : 'SHOP NOW →'; ?>
            </a>
          </div>
        </div>
        <?php endforeach; ?>
      </div>
      <?php else : ?>
      <div style="text-align:center;padding:80px 24px">
        <span class="dths-kicker">Stay Tuned</span>
        <div class="dths-display" style="font-size:clamp(36px,6vw,72px);color:#fff;text-transform:uppercase;margin:20px 0 0">DROPS INCOMING</div>
        <p style="color:var(--dths-muted);margin:16px auto 0;font-size:16px;line-height:1.7;max-width:420px">New drops are being scheduled. Create a free account to be the first to know.</p>
        <div style="margin-top:28px">
          <a href="<?php echo esc_url( wc_get_account_endpoint_url( 'dashboard' ) ); ?>" class="dths-btn-primary">CREATE FREE ACCOUNT →</a>
        </div>
      </div>
      <?php endif; ?>
    </div>
  </section>

</div>
<?php include __DIR__ . '/footer.php'; ?>
