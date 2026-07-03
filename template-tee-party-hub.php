<?php
/**
 * Template Name: TSA Tee Party Hub
 *
 * Landing page that lists all Tee Party pages as cards.
 * Active showcases display a live "Active" badge + countdown.
 * Each card links to its individual party page.
 * Assign to: /tee-party/
 */
defined( 'ABSPATH' ) || exit;

get_header();

/* Gather all party pages (pages using the single tee-party template). */
$party_pages = get_posts( [
    'post_type'      => 'page',
    'post_status'    => 'publish',
    'posts_per_page' => 100,
    'meta_query'     => [ [ 'key' => '_wp_page_template', 'value' => 'template-tee-party.php' ] ],
    'orderby'        => 'menu_order title',
    'order'          => 'ASC',
] );

/* Build a status-enriched list and bucket by state. */
$buckets = [ 'live' => [], 'grace' => [], 'upcoming' => [], 'closed' => [] ];
foreach ( $party_pages as $pp ) {
    $st = function_exists( 'tsa_party_status' ) ? tsa_party_status( $pp->ID ) : [ 'status' => 'inactive' ];
    $key = $st['status'];
    if ( $key === 'inactive' ) continue;            // unpublished/never-activated → hide
    if ( ! isset( $buckets[ $key ] ) ) $key = 'closed';
    $buckets[ $key ][] = [ 'page' => $pp, 'st' => $st ];
}

$has_active = ! empty( $buckets['live'] ) || ! empty( $buckets['grace'] );

/* Card renderer */
function tsa_render_party_card( $page, $st ) {
    $status   = $st['status'];
    $url      = get_permalink( $page->ID );
    $theme    = get_post_meta( $page->ID, '_tsa_party_theme', true ) ?: $page->post_title;
    $img_id   = (int) get_post_meta( $page->ID, '_tsa_party_image_id', true );
    $thumb    = $img_id ? wp_get_attachment_image_url( $img_id, 'large' ) : get_the_post_thumbnail_url( $page->ID, 'large' );
    $revealed = (int) ( $st['revealed'] ?? 0 );
    $total    = (int) ( $st['total_designs'] ?? 0 );
    $accent   = get_post_meta( $page->ID, '_tsa_party_color_accent', true ) ?: '#d8a85f';

    $badge = [
        'live'     => [ 'label' => '● Active Now',     'cls' => 'live'     ],
        'grace'    => [ 'label' => '⏳ Closing Soon',  'cls' => 'grace'    ],
        'upcoming' => [ 'label' => 'Coming Soon',      'cls' => 'upcoming' ],
        'closed'   => [ 'label' => 'Ended',            'cls' => 'closed'   ],
    ][ $status ] ?? [ 'label' => '', 'cls' => 'closed' ];
    ?>
    <a class="tsa-hub-card tsa-hub-card--<?php echo esc_attr( $badge['cls'] ); ?>" href="<?php echo esc_url( $url ); ?>">
        <div class="tsa-hub-card__visual">
            <?php if ( $thumb ) : ?>
                <img src="<?php echo esc_url( $thumb ); ?>" alt="<?php echo esc_attr( $theme ); ?>" loading="lazy">
            <?php else : ?>
                <div class="tsa-hub-card__ph">🎉</div>
            <?php endif; ?>
            <span class="tsa-hub-badge tsa-hub-badge--<?php echo esc_attr( $badge['cls'] ); ?>"
                <?php echo $status === 'live' ? 'style="background:' . esc_attr( $accent ) . ';color:#fff"' : ''; ?>>
                <?php echo esc_html( $badge['label'] ); ?>
            </span>
        </div>
        <div class="tsa-hub-card__body">
            <h3 class="tsa-hub-card__title"><?php echo esc_html( $theme ); ?></h3>

            <?php if ( in_array( $status, [ 'live', 'grace', 'upcoming' ], true ) && $st['countdown_iso'] ) : ?>
            <div class="tsa-hub-card__cd" data-end="<?php echo esc_attr( $st['countdown_iso'] ); ?>"
                 data-mode="<?php echo esc_attr( $status ); ?>">
                <span class="tsa-hub-cd__lbl">
                    <?php echo $status === 'upcoming' ? 'Starts in' : ( $status === 'grace' ? 'Checkout closes in' : 'Ends in' ); ?>
                </span>
                <span class="tsa-hub-cd__time" style="color:<?php echo esc_attr( $accent ); ?>">--:--:--</span>
            </div>
            <?php endif; ?>

            <?php if ( $total > 0 && in_array( $status, [ 'live', 'grace' ], true ) ) : ?>
            <div class="tsa-hub-card__meta"><?php echo esc_html( $revealed ); ?> of <?php echo esc_html( $total ); ?> designs revealed</div>
            <?php elseif ( $total > 0 && $status === 'upcoming' ) : ?>
            <div class="tsa-hub-card__meta"><?php echo esc_html( $total ); ?> designs scheduled</div>
            <?php endif; ?>

            <span class="tsa-hub-card__cta">
                <?php echo $status === 'closed' ? 'View Showcase' : ( $status === 'upcoming' ? 'Preview &amp; Get Notified →' : 'Enter Showcase →' ); ?>
            </span>
        </div>
    </a>
    <?php
}
?>

<style>
.tsa-hub{background:var(--surface-page);color:var(--text-primary);min-height:60vh}
.tsa-hub-hero{background:var(--wash-hero);padding:70px 7% 56px;text-align:center}
.tsa-hub-hero .kicker{display:inline-block;font-size:12px;font-weight:700;text-transform:uppercase;letter-spacing:1.5px;color:var(--tsa-rose-text);margin-bottom:12px}
.tsa-hub-hero h1{font-size:clamp(38px,6vw,64px);font-weight:900;letter-spacing:-2px;margin:0 0 14px;color:var(--text-primary)}
.tsa-hub-hero p{font-size:18px;color:var(--text-muted);max-width:600px;margin:0 auto}
.tsa-hub-section{padding:48px 7%}
.tsa-hub-section h2{font-size:24px;font-weight:800;letter-spacing:-.5px;margin:0 0 6px;display:flex;align-items:center;gap:10px;color:var(--text-primary)}
.tsa-hub-section .sub{color:var(--text-muted);font-size:14px;margin:0 0 26px}
.tsa-hub-grid{display:grid;grid-template-columns:repeat(auto-fill,minmax(280px,1fr));gap:24px}
.tsa-hub-card{display:flex;flex-direction:column;background:var(--surface-card);border:1px solid var(--border);border-radius:16px;overflow:hidden;text-decoration:none;color:var(--text-primary);box-shadow:var(--shadow-sm);transition:transform .15s,box-shadow .15s,border-color .15s}
.tsa-hub-card:hover{transform:translateY(-5px);box-shadow:var(--shadow-lg);border-color:var(--brand-rose)}
.tsa-hub-card--closed{opacity:.62}
.tsa-hub-card__visual{position:relative;aspect-ratio:16/10;background:linear-gradient(135deg,var(--tsa-pink-soft),#fff);overflow:hidden}
.tsa-hub-card__visual img{width:100%;height:100%;object-fit:contain;display:block;padding:14px;box-sizing:border-box}
.tsa-hub-card__ph{width:100%;height:100%;display:flex;align-items:center;justify-content:center;font-size:54px}
.tsa-hub-badge{position:absolute;top:12px;left:12px;font-size:12px;font-weight:700;padding:5px 12px;border-radius:30px;letter-spacing:.3px}
.tsa-hub-badge--live{background:var(--status-success);color:#fff;animation:tsaPulse 1.8s infinite}
.tsa-hub-badge--grace{background:var(--tsa-pink);color:var(--tsa-ink)}
.tsa-hub-badge--upcoming{background:var(--surface-tint);color:var(--tsa-rose-text);border:1px solid var(--border-strong)}
.tsa-hub-badge--closed{background:rgba(37,33,36,.08);color:var(--text-muted)}
@keyframes tsaPulse{0%,100%{box-shadow:0 0 0 0 rgba(63,157,107,.5)}50%{box-shadow:0 0 0 8px rgba(63,157,107,0)}}
.tsa-hub-card__body{padding:18px 20px 20px;display:flex;flex-direction:column;gap:10px;flex:1}
.tsa-hub-card__title{font-size:20px;font-weight:800;margin:0;letter-spacing:-.4px;color:var(--text-primary)}
.tsa-hub-card__cd{display:flex;flex-direction:column;gap:2px}
.tsa-hub-cd__lbl{font-size:11px;text-transform:uppercase;letter-spacing:.6px;color:var(--text-muted)}
.tsa-hub-cd__time{font-size:22px;font-weight:800;color:var(--brand-rose);letter-spacing:-.5px;font-variant-numeric:tabular-nums}
.tsa-hub-card__meta{font-size:13px;color:var(--text-muted)}
.tsa-hub-card__cta{margin-top:auto;font-weight:700;font-size:14px;color:var(--brand-rose)}
.tsa-hub-empty{text-align:center;color:var(--text-muted);padding:50px 0;font-size:16px}
.tsa-hub-divider{border:none;border-top:1px solid var(--border);margin:0 7%}
</style>

<div class="tsa-hub">

    <section class="tsa-hub-hero">
        <div class="kicker">Limited-Time Design Showcases</div>
        <h1><?php the_title(); ?></h1>
        <p><?php echo $has_active
            ? 'A showcase is live right now — jump in before the countdown ends. New designs reveal throughout each event.'
            : 'Timed design showcase events. New designs reveal on a schedule — configure any design on any apparel and order before the deadline.'; ?></p>
    </section>

    <?php if ( ! empty( $buckets['live'] ) || ! empty( $buckets['grace'] ) ) : ?>
    <section class="tsa-hub-section">
        <h2>🔥 Happening Now</h2>
        <p class="sub">Active showcases — enter before the countdown runs out.</p>
        <div class="tsa-hub-grid">
            <?php
            foreach ( $buckets['live']  as $row ) tsa_render_party_card( $row['page'], $row['st'] );
            foreach ( $buckets['grace'] as $row ) tsa_render_party_card( $row['page'], $row['st'] );
            ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ( ! empty( $buckets['upcoming'] ) ) : ?>
    <hr class="tsa-hub-divider">
    <section class="tsa-hub-section">
        <h2>⏰ Coming Soon</h2>
        <p class="sub">Get notified when these showcases go live.</p>
        <div class="tsa-hub-grid">
            <?php foreach ( $buckets['upcoming'] as $row ) tsa_render_party_card( $row['page'], $row['st'] ); ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ( ! empty( $buckets['closed'] ) ) : ?>
    <hr class="tsa-hub-divider">
    <section class="tsa-hub-section">
        <h2>Past Showcases</h2>
        <p class="sub">Recently ended events.</p>
        <div class="tsa-hub-grid">
            <?php foreach ( $buckets['closed'] as $row ) tsa_render_party_card( $row['page'], $row['st'] ); ?>
        </div>
    </section>
    <?php endif; ?>

    <?php if ( empty( $buckets['live'] ) && empty( $buckets['grace'] ) && empty( $buckets['upcoming'] ) && empty( $buckets['closed'] ) ) : ?>
    <section class="tsa-hub-section">
        <div class="tsa-hub-empty">No showcases scheduled right now — check back soon!</div>
    </section>
    <?php endif; ?>

    <?php if ( have_posts() ) : while ( have_posts() ) : the_post(); if ( get_the_content() ) : ?>
    <hr class="tsa-hub-divider">
    <section class="tsa-hub-section"><div style="max-width:860px;margin:0 auto;color:var(--text-muted)"><?php the_content(); ?></div></section>
    <?php endif; endwhile; endif; ?>

</div>

<?php get_footer(); ?>

<script>
(function(){
    function tick(){
        document.querySelectorAll('.tsa-hub-card__cd').forEach(function(el){
            var end = new Date(el.dataset.end).getTime();
            var diff = Math.max(0, end - Date.now());
            var t = el.querySelector('.tsa-hub-cd__time');
            if(diff === 0){ t.textContent = '00:00:00'; return; }
            var d = Math.floor(diff/86400000);
            var h = Math.floor(diff%86400000/3600000);
            var m = Math.floor(diff%3600000/60000);
            var s = Math.floor(diff%60000/1000);
            var p = function(n){ return String(n).padStart(2,'0'); };
            t.textContent = (d>0 ? d+'d ' : '') + p(h)+':'+p(m)+':'+p(s);
        });
    }
    tick();
    setInterval(tick, 1000);
})();
</script>
