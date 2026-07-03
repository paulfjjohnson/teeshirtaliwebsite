<?php
/**
 * Template Name: School — Programs Hub
 * Template Post Type: page
 *
 * Location: flatsome-child/schools/dutchtown/page-school-programs.php
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

$school_slug = 'dutchtown';
$all_cats    = tsa_get_school_collections( $school_slug );

$groups = [
    'performance' => [ 'label' => 'Performance Arts',     'match' => [ 'Color Guard', 'Band', 'Cheer', 'Dance', 'Play', 'Fine Arts', 'Orchestra', 'Choir' ] ],
    'athletics'   => [ 'label' => 'Athletics',             'match' => [ 'Football', 'Baseball', 'Softball', 'Basketball', 'Soccer', 'Track', 'Golf', 'Tennis', 'Swimming', 'Wrestling' ] ],
    'clubs'       => [ 'label' => 'Clubs & Student Orgs',  'match' => [ 'Club', 'Student', 'Council', 'JROTC', 'Beta', 'NHS' ] ],
    'events'      => [ 'label' => 'Events & Senior',       'match' => [ 'Senior', 'Graduation', 'Homecoming', 'Prom', 'Event' ] ],
    'faculty'     => [ 'label' => 'Faculty & Fundraisers', 'match' => [ 'Faculty', 'Teacher', 'Staff', 'Booster', 'Fundraiser', 'PTG', 'PTA' ] ],
];

$grouped = array_fill_keys( array_keys( $groups ), [] );
foreach ( $all_cats as $cat ) {
    $placed = false;
    foreach ( $groups as $key => $g ) {
        foreach ( $g['match'] as $m ) {
            if ( stripos( $cat->name, $m ) !== false ) {
                $grouped[ $key ][] = $cat;
                $placed = true;
                break 2;
            }
        }
    }
    if ( ! $placed ) $grouped['clubs'][] = $cat;
}
?>

<div class="dths-page">


  <!-- HERO -->
  <section class="dths-coll-hero" style="padding:72px 0 48px;background:radial-gradient(circle at 30% 0%,rgba(89,44,130,.32),transparent 45%),var(--dths-black)">
    <div class="dths-shell">
      <span class="dths-kicker">School-Wide Ecosystem</span>
      <h1 class="dths-display" style="font-size:clamp(48px,8vw,96px);color:#fff;line-height:.9;text-transform:uppercase;margin-top:14px">THE PROGRAMS<br>HUB</h1>
      <p style="color:var(--dths-muted);margin-top:16px;font-size:16px;max-width:540px;line-height:1.65">Every Dutchtown team, club, and event will have its own dedicated merch store. Color Guard and Band are live now — the rest of the school is rolling in next.</p>
    </div>
  </section>

  <section class="dths-section" style="padding-top:48px">
    <div class="dths-shell">
      <?php foreach ( $groups as $key => $g ) :
        if ( empty( $grouped[ $key ] ) ) continue;
        $live = array_filter( $grouped[$key], function($c) use ($live_store_slugs) { return in_array( $c->slug, $live_store_slugs, true ); } );
        $soon = array_filter( $grouped[$key], function($c) use ($live_store_slugs) { return ! in_array( $c->slug, $live_store_slugs, true ); } );
      ?>
      <div style="margin-bottom:64px">
        <div style="font-size:10px;letter-spacing:.28em;text-transform:uppercase;color:rgba(255,255,255,.38);margin-bottom:12px;padding-bottom:12px;border-bottom:1px solid var(--dths-line)"><?php echo esc_html( $g['label'] ); ?></div>

        <?php if ( ! empty( $live ) ) : ?>
        <div class="dths-display" style="font-size:clamp(28px,4vw,40px);color:#fff;text-transform:uppercase;margin-bottom:20px;margin-top:8px">NOW LIVE</div>
        <div class="dths-prog-grid">
          <?php foreach ( $live as $cat ) :
            $tag       = get_term_meta( $cat->term_id, 'tagline', true ) ?: wp_strip_all_tags( $cat->description );
            $page_slug = str_replace( $school_slug . '-', '', $cat->slug );
            $clean     = str_replace( 'Dutchtown — ', '', $cat->name );
          ?>
          <a class="dths-prog-card dths-prog-live" href="<?php echo esc_url( home_url( '/schools/' . $school_slug . '/' . $page_slug . '/' ) ); ?>">
            <span class="dths-prog-status dths-status-live">● Live</span>
            <div class="dths-prog-name dths-display"><?php echo esc_html( $clean ); ?></div>
            <div class="dths-prog-sub"><?php echo esc_html( $tag ); ?></div>
            <div class="dths-prog-arrow">Shop Now →</div>
          </a>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>

        <?php if ( ! empty( $soon ) ) : ?>
        <div class="dths-display" style="font-size:clamp(28px,4vw,40px);color:#fff;text-transform:uppercase;margin-top:<?php echo empty($live)?'8':'28';?>px;margin-bottom:20px">COMING SOON</div>
        <div class="dths-prog-grid">
          <?php foreach ( $soon as $cat ) :
            $tag   = get_term_meta( $cat->term_id, 'tagline', true ) ?: wp_strip_all_tags( $cat->description );
            $clean = str_replace( 'Dutchtown — ', '', $cat->name );
          ?>
          <div class="dths-prog-card">
            <span class="dths-prog-status dths-status-soon">Soon</span>
            <div class="dths-prog-name dths-display"><?php echo esc_html( $clean ); ?></div>
            <?php if ( $tag ) : ?><div class="dths-prog-sub"><?php echo esc_html( $tag ); ?></div><?php endif; ?>
          </div>
          <?php endforeach; ?>
        </div>
        <?php endif; ?>
      </div>
      <?php endforeach; ?>
    </div>
  </section>

</div>
<?php include __DIR__ . '/footer.php'; ?>
