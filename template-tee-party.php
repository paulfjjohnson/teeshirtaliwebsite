<?php
/**
 * Template Name: TSA Tee Party
 *
 * Design drop event — designs reveal on a schedule, in batches ("drops").
 * The most recent drop is pinned in a Spotlight band; earlier drops fall back
 * into category sections (freshest category first); upcoming drops show as
 * grouped blurred teasers. Each design links to the Apparel Configurator.
 * Polls /tsa/v1/party/{id} every 60s for new reveals.
 */
defined( 'ABSPATH' ) || exit;

get_header();

$page_id       = get_the_ID();
$theme_name    = get_post_meta( $page_id, '_tsa_party_theme',      true ) ?: get_the_title();
$is_active     = get_post_meta( $page_id, '_tsa_party_active',     true ) === '1';
$end_date      = get_post_meta( $page_id, '_tsa_party_end_date',   true );
$grace         = (int) get_post_meta( $page_id, '_tsa_party_grace_minutes', true );
$end_ts        = function_exists( 'tsa_party_ts' ) ? tsa_party_ts( $end_date ) : ( $end_date ? strtotime( $end_date ) : 0 );
$grace_end_ts  = $end_ts ? $end_ts + $grace * 60 : 0;
$now           = time();

$status = 'upcoming';
if ( $is_active ) {
    if ( $grace_end_ts && $now > $grace_end_ts )   $status = 'closed';
    elseif ( $end_ts && $now > $end_ts )           $status = 'grace';
    else                                            $status = 'live';
}

$end_iso       = $end_ts      ? date( 'c', $end_ts )      : '';
$grace_end_iso = $grace_end_ts? date( 'c', $grace_end_ts ): '';
$api_url       = rest_url( "tsa/v1/party/{$page_id}" );
$configurator  = home_url( '/configurator/' );
$party_store_slug = get_post_meta( $page_id, '_tsa_party_store_slug', true ) ?: 'tsa';
$party_garments= '';
$party_garment_colors = [];
if ( get_post_meta( $page_id, '_tsa_party_apparel_mode', true ) === 'specific' ) {
    $gids = json_decode( get_post_meta( $page_id, '_tsa_party_garment_ids', true ) ?: '[]', true ) ?: [];
    if ( $gids ) $party_garments = implode( ',', $gids );
    $party_garment_colors = json_decode( get_post_meta( $page_id, '_tsa_party_garment_colors', true ) ?: '{}', true ) ?: [];
}

// Per-party colors
$c_accent    = get_post_meta( $page_id, '_tsa_party_color_accent',    true ) ?: '#d8a85f';
$c_highlight = get_post_meta( $page_id, '_tsa_party_color_highlight', true ) ?: '#fec2c0';
$c_bg_top    = get_post_meta( $page_id, '_tsa_party_color_bg_top',    true ) ?: '#ffffff';
$c_bg_bottom = get_post_meta( $page_id, '_tsa_party_color_bg_bottom', true ) ?: '#fff1f0';
$c_text      = get_post_meta( $page_id, '_tsa_party_color_text',      true ) ?: '#252124';

// Party image (hero + hub card). Falls back to the page Featured Image.
$party_img_id  = (int) get_post_meta( $page_id, '_tsa_party_image_id', true );
$party_img_url = $party_img_id ? wp_get_attachment_image_url( $party_img_id, 'large' ) : ( get_the_post_thumbnail_url( $page_id, 'large' ) ?: '' );

// Linked store display name + landing ("Shop") URL — used by the closed-state cards
// and CTA to drive traffic into the store (school / team / business alike).
$party_store_name = $party_store_slug
    ? ( function_exists( 'tsa_store_name' ) ? tsa_store_name( $party_store_slug ) : ucwords( str_replace( '-', ' ', $party_store_slug ) ) )
    : '';
$party_store_url = ( $party_store_slug && function_exists( 'tsa_store_url' ) ) ? tsa_store_url( $party_store_slug ) : '';

// Designs orderable in this party so far = the REVEALED drops (collection designs
// are merged in below). Used to scope the configurator's "Change design" picker
// to the party instead of the whole catalog.
$party_design_ids = [];
$party_now_ts = time();
foreach ( ( json_decode( get_post_meta( $page_id, '_tsa_party_schedule', true ) ?: '[]', true ) ?: [] ) as $row ) {
    $did = absint( $row['design_id'] ?? 0 );
    $rt  = function_exists( 'tsa_party_ts' ) ? tsa_party_ts( $row['reveal_at'] ?? '' ) : 0;
    if ( $did && $rt && $rt <= $party_now_ts ) $party_design_ids[] = $did;
}
?>

<style>
/* Per-party color overrides (scoped to this page) */
.tsa-party{ --tp-accent:<?php echo esc_html( $c_accent ); ?>; --tp-highlight:<?php echo esc_html( $c_highlight ); ?>; --tp-bg-top:<?php echo esc_html( $c_bg_top ); ?>; --tp-bg-bottom:<?php echo esc_html( $c_bg_bottom ); ?>; --tp-text:<?php echo esc_html( $c_text ); ?>; }
.tsa-party .tsa-party-hero{ background:linear-gradient(160deg,var(--tp-bg-top),var(--tp-bg-bottom))!important; }
.tsa-party .tsa-party-hero__img{ max-width:340px; margin:0 auto 22px; }
.tsa-party .tsa-party-hero__img img{ display:block; width:100%; height:auto; max-height:220px; object-fit:contain; border-radius:16px; }
.tsa-party .tsa-party-hero h1{ color:var(--tp-text); }
.tsa-party .tsa-party-status{ border-color:var(--tp-accent)!important; color:var(--tp-accent)!important; background:color-mix(in srgb,var(--tp-accent) 14%,transparent)!important; }
.tsa-party .tsa-party-status__dot{ background:var(--tp-accent)!important; }
.tsa-party .tsa-kicker--gold{ color:var(--tp-accent)!important; }
.tsa-party .tsa-btn-primary{ background:var(--tp-accent)!important; color:var(--tp-text)!important; border-color:var(--tp-accent)!important; }
.tsa-party #tsa-next-drop-timer{ color:var(--tp-accent)!important; }
.tsa-party .tsa-party-card .tsa-btn-primary{ background:var(--tp-accent)!important; color:var(--tp-text)!important; }
/* Long store-name CTAs ("Shop the Dutchtown High School Store →") must wrap inside
   the pill instead of overflowing — base .tsa-btn is white-space:nowrap + fixed height. */
.tsa-party .tsa-party-card .tsa-btn,
.tsa-party .tsa-actions .tsa-btn{ white-space:normal!important; line-height:1.25; height:auto!important; min-height:0; padding-top:10px; padding-bottom:10px; text-align:center; }
.tsa-party .tsa-urgency-strip{ display:flex; align-items:center; justify-content:center; gap:10px; width:fit-content; max-width:100%; margin:18px auto 0; text-align:center; background:color-mix(in srgb,var(--tp-highlight) 28%,#fff)!important; color:var(--tp-text)!important; border:1px solid color-mix(in srgb,var(--tp-highlight) 55%,transparent); border-radius:999px; padding:10px 22px; font-weight:800; }
/* "Designs drop soon" empty state */
.tsa-party .tsa-drop-soon{ text-align:center; padding:20px; max-width:480px; margin:0 auto; }
.tsa-party .tsa-drop-soon__emoji{ font-size:52px; line-height:1; margin-bottom:14px; animation:tsa-drop-bounce 2s ease-in-out infinite; }
.tsa-party .tsa-drop-soon__title{ font-size:clamp(24px,4.2vw,34px); font-weight:900; letter-spacing:-.5px; color:var(--tp-text); margin:0 0 8px; }
.tsa-party .tsa-drop-soon__sub{ font-size:16px; line-height:1.55; color:var(--tp-text); opacity:.72; margin:0; }
@keyframes tsa-drop-bounce{ 0%,100%{ transform:translateY(0); } 50%{ transform:translateY(-8px); } }
/* Buy-more-save-more strip */
.tsa-party .tsa-party-savings{ max-width:780px; margin:26px auto 0; text-align:center; }
.tsa-party .tsa-party-savings__head h3{ margin:4px 0 16px; font-size:clamp(20px,2.6vw,26px); color:var(--tp-text); letter-spacing:-.5px; }
.tsa-party .tsa-party-savings__tiers{ display:flex; flex-wrap:wrap; gap:14px; justify-content:center; }
.tsa-party .tsa-savings-tier{ position:relative; flex:1 1 150px; max-width:210px; background:var(--surface-card); border:1px solid color-mix(in srgb,var(--tp-accent) 38%,var(--border)); border-radius:var(--radius-lg,16px); padding:20px 16px; box-shadow:var(--shadow-xs); }
.tsa-party .tsa-savings-tier__pct{ display:block; font-size:28px; font-weight:900; color:var(--tp-accent); letter-spacing:-1px; line-height:1; }
.tsa-party .tsa-savings-tier__qty{ display:block; margin-top:7px; font-size:12px; font-weight:700; color:var(--tp-text); text-transform:uppercase; letter-spacing:.6px; opacity:.85; }
.tsa-party .tsa-party-savings__note{ margin:15px 0 0; font-size:13px; color:var(--text-muted); }

/* ── Readability: keep text legible on any per-party background ── */
/* Hero secondary text follows the party's own (readable) text color */
.tsa-party .tsa-party-hero #tsa-party-subline,
.tsa-party .tsa-party-hero #tsa-cd-label,
.tsa-party .tsa-party-hero #tsa-next-drop-wrap > div:first-child,
.tsa-party .tsa-party-hero .tsa-countdown__num,
.tsa-party .tsa-party-hero .tsa-countdown__label,
.tsa-party .tsa-party-hero #tsa-next-drop-timer{ color:var(--tp-text)!important; -webkit-text-fill-color:var(--tp-text)!important; }
.tsa-party .tsa-party-hero #tsa-party-subline{ opacity:.85; }
.tsa-party .tsa-party-hero #tsa-cd-label,
.tsa-party .tsa-party-hero #tsa-next-drop-wrap > div:first-child,
.tsa-party .tsa-party-hero .tsa-countdown__label{ opacity:.7; }
/* Status / live pill — translucent in the party ink, always legible */
.tsa-party .tsa-party-status{ color:var(--tp-text)!important; -webkit-text-fill-color:var(--tp-text)!important; border-color:color-mix(in srgb,var(--tp-text) 38%,transparent)!important; background:color-mix(in srgb,var(--tp-text) 9%,transparent)!important; }
/* Kickers: darken the accent so light accents stay readable on the light section */
.tsa-party .tsa-kicker--gold{ color:color-mix(in srgb,var(--tp-accent) 62%,#000)!important; -webkit-text-fill-color:color-mix(in srgb,var(--tp-accent) 62%,#000)!important; }
/* Lower (light-bg) showcase subline */
.tsa-party #tsa-drop-subline{ color:var(--text-primary)!important; opacity:.72; max-width:600px; margin-left:auto; margin-right:auto; }
/* Showcase headline was inheriting a light color (invisible on the light
   section) — make it legible, and trim the section's dead space. */
.tsa-party #tsa-drop-headline{ color:var(--text-primary)!important; -webkit-text-fill-color:var(--text-primary)!important; font-size:clamp(24px,4vw,32px); letter-spacing:-.5px; margin:6px 0 10px; }
.tsa-party .tsa-party-products{ padding-top:44px!important; padding-bottom:44px!important; }
.tsa-party .tsa-party-products .tsa-section-head{ margin-bottom:16px!important; }
.tsa-party .tsa-party-savings{ margin-top:22px!important; }
.tsa-party #tsa-party-stage{ margin-top:16px!important; }
.tsa-party .tsa-spotlight__wait{ padding:16px 12px!important; }

/* ── Grouped reveal layout: Spotlight batch + Coming-up + Category sections ── */
.tsa-party .tsa-spotlight{ background:color-mix(in srgb,var(--tp-accent) 9%,#fff); border:1px solid color-mix(in srgb,var(--tp-accent) 40%,var(--border)); border-radius:var(--radius-lg,16px); padding:18px; margin-top:24px; }
.tsa-party .tsa-spotlight__head{ display:flex; align-items:center; justify-content:space-between; gap:10px; flex-wrap:wrap; margin-bottom:14px; }
.tsa-party .tsa-spotlight__badge{ display:inline-flex; align-items:center; gap:8px; font-weight:800; font-size:15px; color:var(--tp-text); }
.tsa-party .tsa-spotlight__badge .dot{ width:9px; height:9px; border-radius:50%; background:var(--tp-accent); flex-shrink:0; }
.tsa-party .tsa-spotlight__next{ font-size:13px; color:var(--tp-text); opacity:.85; }
.tsa-party .tsa-spotlight__next b{ color:color-mix(in srgb,var(--tp-accent) 62%,#000); font-weight:800; }
.tsa-party .tsa-spotlight__grid{ display:grid; grid-template-columns:repeat(auto-fit,minmax(220px,340px)); gap:14px; align-items:stretch; justify-content:center; }
.tsa-party .tsa-spotlight__wait{ text-align:center; padding:26px 10px; color:var(--tp-text); }
.tsa-party .tsa-spotlight__wait-num{ font-size:30px; font-weight:900; letter-spacing:-1px; color:color-mix(in srgb,var(--tp-accent) 62%,#000); }

.tsa-party .tsa-comingup{ margin-top:28px; }
.tsa-party .tsa-comingup__label{ font-size:12px; text-transform:uppercase; letter-spacing:.8px; color:var(--text-muted); margin-bottom:12px; }
.tsa-party .tsa-comingup__groups{ display:flex; gap:22px; flex-wrap:wrap; }
.tsa-party .tsa-drop-group{ flex:1 1 250px; }
.tsa-party .tsa-drop-group__head{ display:inline-flex; align-items:center; gap:6px; font-size:12px; font-weight:800; padding:3px 11px; border-radius:999px; margin-bottom:9px; background:var(--surface-tint); border:1px solid var(--border); color:var(--text-muted); }
.tsa-party .tsa-drop-group--next .tsa-drop-group__head{ background:color-mix(in srgb,var(--tp-highlight) 30%,#fff); border-color:color-mix(in srgb,var(--tp-highlight) 55%,transparent); color:var(--tp-text); }
.tsa-party .tsa-drop-group__tiles{ display:grid; grid-template-columns:repeat(auto-fill,minmax(92px,1fr)); gap:8px; }
.tsa-party .tsa-teaser{ position:relative; aspect-ratio:1; border-radius:10px; overflow:hidden; background:var(--surface-tint); }

.tsa-party .tsa-cat-section{ margin-top:32px; }
.tsa-party .tsa-cat-section__head{ display:flex; align-items:baseline; gap:10px; margin-bottom:14px; flex-wrap:wrap; }
.tsa-party .tsa-cat-section__name{ font-size:clamp(19px,2.4vw,22px); font-weight:800; letter-spacing:-.4px; color:var(--text-primary); }
.tsa-party .tsa-cat-section__count{ font-size:13px; color:var(--text-muted); }
.tsa-party .tsa-cat-section__badge{ font-size:11px; font-weight:800; text-transform:uppercase; letter-spacing:.5px; padding:2px 10px; border-radius:999px; background:color-mix(in srgb,var(--tp-accent) 16%,#fff); color:color-mix(in srgb,var(--tp-accent) 62%,#000); }
.tsa-party .tsa-cat-section__grid{ display:grid; grid-template-columns:repeat(auto-fill,minmax(200px,1fr)); gap:20px; align-items:stretch; }
</style>

<div class="tsa-party">

<!-- ══ HERO ══════════════════════════════════════════════════════ -->
<section class="tsa-party-hero">

    <?php if ( $party_img_url ) : ?>
    <div class="tsa-party-hero__img"><img src="<?php echo esc_url( $party_img_url ); ?>" alt="<?php echo esc_attr( $theme_name ); ?>"></div>
    <?php endif; ?>

    <div class="tsa-party-status" id="tsa-party-status-badge"
        <?php if ( $status !== 'live' ) echo 'style="border-color:var(--border-strong);color:var(--text-muted);background:var(--surface-tint);"'; ?>>
        <?php if ( $status === 'live' ) : ?>
            <span class="tsa-party-status__dot"></span> Showcase Is Live
        <?php elseif ( $status === 'grace' ) : ?>
            ⏳ Showcase ended — checkout still open
        <?php elseif ( $status === 'closed' ) : ?>
            🔒 Showcase Closed
        <?php else : ?>
            Showcase Coming Soon
        <?php endif; ?>
    </div>

    <h1><?php echo esc_html( $theme_name ); ?></h1>

    <p id="tsa-party-subline">
        <?php if ( $status === 'live' ) : ?>
            New designs reveal throughout the showcase. Configure any design on any apparel — or keep adding to your cart till the deadline.
        <?php elseif ( $status === 'grace' ) : ?>
            The showcase has ended but you still have time to checkout. Cart closes soon.
        <?php elseif ( $status === 'closed' ) : ?>
            This showcase has ended. Check back for the next one.
        <?php else : ?>
            Sign up to get notified when the next showcase goes live. Limited designs. Limited time.
        <?php endif; ?>
    </p>

    <!-- Party deadline countdown -->
    <?php if ( $end_iso && in_array( $status, ['live','grace'], true ) ) : ?>
    <div style="margin-bottom:16px">
        <div style="font-size:11px;text-transform:uppercase;letter-spacing:.8px;color:var(--text-muted);margin-bottom:8px" id="tsa-cd-label">
            <?php echo $status === 'grace' ? 'Checkout closes in' : 'Party ends in'; ?>
        </div>
        <div class="tsa-countdown" id="tsa-deadline-cd"
             data-end="<?php echo esc_attr( $status === 'grace' ? $grace_end_iso : $end_iso ); ?>">
            <div class="tsa-countdown__block"><span class="tsa-countdown__num" id="cd-days">--</span><span class="tsa-countdown__label">Days</span></div>
            <div class="tsa-countdown__block"><span class="tsa-countdown__num" id="cd-hours">--</span><span class="tsa-countdown__label">Hours</span></div>
            <div class="tsa-countdown__block"><span class="tsa-countdown__num" id="cd-mins">--</span><span class="tsa-countdown__label">Mins</span></div>
            <div class="tsa-countdown__block"><span class="tsa-countdown__num" id="cd-secs">--</span><span class="tsa-countdown__label">Secs</span></div>
        </div>
    </div>

    <?php $tsa_party_fmt = 'l F jS \a\t g:ia'; // e.g. Sunday June 28th at 12:00pm ?>
    <div class="tsa-party-deadline-dates" style="margin-bottom:24px;color:var(--tp-text)">
        <?php if ( $end_ts ) : ?>
        <div style="margin-bottom:10px">
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;opacity:.7;margin-bottom:2px">Party ends</div>
            <strong style="font-size:clamp(18px,2.6vw,22px);font-weight:800;letter-spacing:-.2px"><?php echo esc_html( wp_date( $tsa_party_fmt, $end_ts ) ); ?></strong>
        </div>
        <?php endif; ?>
        <?php if ( $grace_end_ts && $grace > 0 ) : ?>
        <div>
            <div style="font-size:12px;text-transform:uppercase;letter-spacing:1px;opacity:.7;margin-bottom:2px">Extended shopping till</div>
            <strong style="font-size:clamp(18px,2.6vw,22px);font-weight:800;letter-spacing:-.2px"><?php echo esc_html( wp_date( $tsa_party_fmt, $grace_end_ts ) ); ?></strong>
        </div>
        <?php endif; ?>
    </div>
    <?php endif; ?>

    <div class="tsa-actions tsa-actions--center">
        <?php if ( in_array( $status, ['live','grace'], true ) ) : ?>
            <a href="#party-designs" class="tsa-btn tsa-btn-primary">See the Showcase</a>
            <a href="/cart/" class="tsa-btn tsa-btn-outline">View Cart</a>
        <?php elseif ( $status === 'closed' ) : ?>
            <?php if ( $party_store_url ) : ?>
                <?php tsa_btn( $party_store_url, 'Shop the ' . $party_store_name . ' Store →', 'primary' ); ?>
                <?php tsa_btn( '/tee-party/', 'All Parties', 'outline' ); ?>
            <?php else : ?>
                <?php tsa_btn( '/tee-party/', 'All Parties', 'primary' ); ?>
            <?php endif; ?>
        <?php else : ?>
            <?php tsa_btn( '/tee-party/', 'All Parties', 'outline' ); ?>
        <?php endif; ?>
    </div>

    <?php /* Drop alerts: capture email so subscribers are notified at open + each drop. */ ?>
    <?php if ( function_exists( 'tsa_party_subscribe_form' ) && $status !== 'closed' ) : ?>
        <div class="tsa-party-subscribe-wrap" style="margin-top:8px">
            <?php if ( ! in_array( $status, ['live','grace'], true ) ) : ?>
                <p style="font:13px system-ui;color:var(--tp-text,#252124);opacity:.82;text-align:center;margin:0 0 4px">Get an email the moment designs drop.</p>
            <?php endif; ?>
            <?php echo tsa_party_subscribe_form( $page_id, in_array( $status, ['live','grace'], true ) ? 'compact' : 'block' ); ?>
        </div>
    <?php endif; ?>

</section>


<!-- ══ DESIGN SHOWCASE ═══════════════════════════════════════════ -->
<section id="party-designs" class="tsa-section tsa-party-products">

    <div class="tsa-section-head">
        <div class="tsa-kicker tsa-kicker--gold" id="tsa-drop-kicker">
            <?php echo in_array( $status, ['live','grace'], true ) ? 'The Showcase' : 'Designs'; ?>
        </div>
        <h2 id="tsa-drop-headline"><?php echo in_array( $status, ['live','grace'], true ) ? 'Live Designs' : 'The Designs'; ?></h2>
        <p id="tsa-drop-subline">
            <?php if ( $status === 'live' ) : ?>
            New designs drop in batches throughout the showcase. Click any design to configure it on your choice of apparel.
            <?php endif; ?>
        </p>
    </div>

    <?php if ( $status === 'live' ) : ?>
    <div class="tsa-urgency-strip">
        <span>🔥</span> Designs only available during the party window — configure and add to cart before the deadline.
    </div>
    <?php endif; ?>

    <!-- Buy more, save more -->
    <?php
    $disc_labels = function_exists( 'tsa_party_discount_tier_labels' ) ? tsa_party_discount_tier_labels( $page_id ) : [];
    if ( $disc_labels && $status !== 'closed' ) : ?>
    <div class="tsa-party-savings" aria-label="Quantity discounts">
        <div class="tsa-party-savings__head">
            <span class="tsa-kicker tsa-kicker--gold">Buy More, Save More</span>
            <h3>The more shirts you order, the more you save</h3>
        </div>
        <div class="tsa-party-savings__tiers">
            <?php foreach ( $disc_labels as $t ) : ?>
            <div class="tsa-savings-tier">
                <span class="tsa-savings-tier__pct"><?php echo esc_html( $t['off'] ); ?></span>
                <span class="tsa-savings-tier__qty"><?php echo esc_html( $t['label'] ); ?></span>
            </div>
            <?php endforeach; ?>
        </div>
        <p class="tsa-party-savings__note">Your discount applies to <strong>every design in this party</strong> — the new drops and the full collection alike. Mix &amp; match; your best tier is based on your total shirts.</p>
    </div>
    <?php endif; ?>

    <!-- Reveal stage — populated and updated by JS -->
    <div id="tsa-party-stage" style="margin-top:24px">
        <div id="tsa-grid-loading" style="text-align:center;color:#888;padding:40px">Loading designs…</div>
        <div id="tsa-spotlight"  class="tsa-spotlight"  hidden></div>
        <div id="tsa-comingup"   class="tsa-comingup"   hidden></div>
        <div id="tsa-categories" class="tsa-categories" hidden></div>
    </div>

</section>


<!-- ══ THE COLLECTION — linked school's existing library designs (base) ══ -->
<?php
$coll_designs = [];
if ( $party_store_slug && $party_store_slug !== 'tsa' && taxonomy_exists( 'tsa_design_store' ) ) {
    $coll_cpt   = post_type_exists( 'tsa_design' ) ? 'tsa_design' : 'configurator_design';
    $sched_rows = json_decode( get_post_meta( $page_id, '_tsa_party_schedule', true ) ?: '[]', true ) ?: [];
    $coll_excl  = [];
    foreach ( $sched_rows as $row ) { $did = absint( $row['design_id'] ?? 0 ); if ( $did ) $coll_excl[] = $did; }
    $coll_designs = get_posts( [
        'post_type'    => $coll_cpt,
        'post_status'  => 'publish',
        // Show the store's whole collection — the cards are grouped into category
        // tiles, so a 24-cap silently hid every category beyond the newest 24
        // designs. Filterable in case a store ever grows large enough to paginate.
        'numberposts'  => (int) apply_filters( 'tsa_party_collection_limit', -1, $party_store_slug ),
        'post__not_in' => $coll_excl ?: [ 0 ],
        'orderby'      => 'date',
        'order'        => 'DESC',
        'tax_query'    => [ [ 'taxonomy' => 'tsa_design_store', 'field' => 'slug', 'terms' => $party_store_slug ] ],
    ] );
}
if ( $coll_designs && function_exists( 'tsa_featured_designs_assets' ) ) :
    $coll_name = function_exists( 'tsa_store_name' ) ? tsa_store_name( $party_store_slug ) : ucwords( str_replace( '-', ' ', $party_store_slug ) );
    tsa_featured_designs_assets();
?>
<section class="tsa-party-collection" style="background:#fbf9fa;border-top:1px solid var(--border,rgba(0,0,0,.08));padding:52px 6%">
    <div style="max-width:1200px;margin:0 auto">
        <span style="display:inline-flex;align-items:center;gap:8px;font-size:12px;font-weight:800;text-transform:uppercase;letter-spacing:1.5px;color:var(--tp-accent,#d8a85f)"><span style="width:8px;height:8px;border-radius:50%;background:currentColor"></span> The Collection</span>
        <h2 style="font-size:clamp(26px,4vw,34px);font-weight:900;letter-spacing:-.5px;margin:10px 0 6px;color:#252124"><?php echo esc_html( $coll_name ); ?> &mdash; Ready-to-Order Designs</h2>
        <p style="color:#6d6268;margin:0 0 26px;max-width:600px;font-size:16px;line-height:1.55">Past favorites and full-collection designs, available any time. Pick one to customize while you wait for the next drop.</p>
        <?php
        // Party context suffix — identical for every Collection card. During the
        // live window these designs ride the full party (banner + quantity
        // discount + the same apparel/color restriction as the drops). Outside
        // the window they're ordinary regular-price library items.
        // Merge this store's collection designs into the party's allowed set
        // (drops were collected at the top) so the configurator "Change" picker
        // shows drops + collection.
        $party_design_ids = array_values( array_unique( array_merge( $party_design_ids, wp_list_pluck( $coll_designs, 'ID' ) ) ) );

        $party_suffix = '';
        if ( in_array( $status, ['live','grace'], true ) ) {
            $party_suffix = '&party_id=' . (int) $page_id;
            if ( $party_garments )                  $party_suffix .= '&party_garments=' . rawurlencode( $party_garments );
            if ( ! empty( $party_garment_colors ) )  $party_suffix .= '&party_colors='  . rawurlencode( wp_json_encode( $party_garment_colors ) );
            if ( $party_design_ids )                 $party_suffix .= '&party_designs='  . rawurlencode( implode( ',', $party_design_ids ) );
        }

        // Group the collection by its primary design category (skipping the
        // 'schools' nav category). Uncategorised designs fall into a trailing
        // "More Designs" group.
        // Designs are tagged with their school (a top-level category, e.g.
        // "Dutchtown") AND a shared theme (also top-level, e.g. "Spirit Designs",
        // "Scribbled Rectangles"). Group by the THEME — drop the school's own
        // category (it duplicates the section title) and the 'schools' nav cat.
        $coll_groups = []; $coll_nocat = [];
        $coll_store_lc   = strtolower( (string) $coll_name );
        $coll_store_slug = sanitize_title( (string) $party_store_slug );
        $is_school_cat = function ( $t ) use ( $party_store_slug, $coll_store_slug, $coll_store_lc ) {
            return $t->slug === 'schools'
                || $t->slug === $party_store_slug
                || sanitize_title( $t->name ) === $coll_store_slug
                || strtolower( $t->name ) === $coll_store_lc;
        };
        foreach ( $coll_designs as $cd ) {
            $terms  = wp_get_post_terms( $cd->ID, 'tsa_design_category' );
            $picked = ''; $best_depth = -1;
            if ( ! is_wp_error( $terms ) ) {
                foreach ( $terms as $t ) {
                    if ( $is_school_cat( $t ) ) continue;
                    $depth = count( get_ancestors( $t->term_id, 'tsa_design_category' ) );
                    if ( $depth > $best_depth ) { $best_depth = $depth; $picked = $t->name; }
                }
            }
            if ( $picked === '' ) $coll_nocat[] = $cd; else $coll_groups[ $picked ][] = $cd;
        }
        ksort( $coll_groups );
        if ( $coll_nocat ) $coll_groups['More Designs'] = $coll_nocat;

        // ── Drill-down landing: one card per category (mirrors the Design
        // Library). Click → reveal that category's designs + a back button. ──
        ?>
        <div class="tsa-dl-cat-grid" id="tsa-coll-cat-top">
            <?php foreach ( $coll_groups as $cat_name => $cat_designs ) :
                $cat_key  = sanitize_title( $cat_name );
                $cfirst   = reset( $cat_designs );
                $cat_prev = $cfirst ? ( get_post_meta( $cfirst->ID, '_design_preview_url', true ) ?: ( get_the_post_thumbnail_url( $cfirst->ID, 'medium' ) ?: '' ) ) : '';
                $cat_cnt  = count( $cat_designs );
            ?>
            <button type="button" class="tsa-dl-cat-card" data-coll-cat="<?php echo esc_attr( $cat_key ); ?>">
                <span class="tsa-dl-cat-card__visual tsa-autocontrast">
                    <?php if ( $cat_prev ) : ?><img src="<?php echo esc_url( $cat_prev ); ?>" alt="<?php echo esc_attr( $cat_name ); ?>" loading="lazy"><?php else : ?><span class="tsa-dl-cat-card__noimg">&#127912;</span><?php endif; ?>
                </span>
                <span class="tsa-dl-cat-card__body">
                    <span class="tsa-dl-cat-card__name"><?php echo esc_html( $cat_name ); ?> <span class="tsa-dl-cat-card__chev">&rsaquo;</span></span>
                    <span class="tsa-dl-cat-card__count"><?php echo (int) $cat_cnt; ?> design<?php echo $cat_cnt === 1 ? '' : 's'; ?></span>
                </span>
            </button>
            <?php endforeach; ?>
        </div>

        <?php foreach ( $coll_groups as $cat_name => $cat_designs ) : $cat_key = sanitize_title( $cat_name ); ?>
        <div class="tsa-coll-group" data-coll-group="<?php echo esc_attr( $cat_key ); ?>" hidden>
            <button type="button" class="tsa-coll-back" style="display:inline-flex;align-items:center;gap:6px;background:none;border:0;color:var(--tp-accent,#d8a85f);font-weight:800;font-size:14px;cursor:pointer;padding:0;margin:6px 0 16px">&larr; All categories</button>
            <h3 style="font-size:21px;font-weight:900;color:#252124;letter-spacing:-.3px;margin:0 0 16px"><?php echo esc_html( $cat_name ); ?></h3>
            <div class="tsa-fd-grid">
                <?php foreach ( $cat_designs as $cd ) :
                    $cd_url  = home_url( '/configurator/?design_id=' . (int) $cd->ID . '&store=' . rawurlencode( $party_store_slug ) . $party_suffix );
                    $cd_prev = get_post_meta( $cd->ID, '_design_preview_url', true ) ?: ( get_the_post_thumbnail_url( $cd->ID, 'medium' ) ?: '' );
                    $cd_name = get_the_title( $cd );
                    ?>
                <article class="tsa-fd-card">
                    <a class="tsa-fd-card__img" href="<?php echo esc_url( $cd_url ); ?>" aria-label="<?php echo esc_attr( $cd_name ); ?>">
                        <?php if ( $cd_prev ) : ?><img src="<?php echo esc_url( $cd_prev ); ?>" alt="<?php echo esc_attr( $cd_name ); ?>" loading="lazy"><?php else : ?><span class="tsa-fd-card__ph">&#127912;</span><?php endif; ?>
                    </a>
                    <div class="tsa-fd-card__body">
                        <div class="tsa-fd-card__name"><?php echo esc_html( $cd_name ); ?></div>
                        <div class="tsa-fd-card__actions">
                            <a class="tsa-fd-btn tsa-fd-btn--primary" href="<?php echo esc_url( $cd_url ); ?>">Configure &amp; Order</a>
                            <button type="button" class="tsa-fd-btn tsa-fd-btn--ghost tsa-fd-cz" data-id="<?php echo (int) $cd->ID; ?>" data-title="<?php echo esc_attr( $cd_name ); ?>" data-preview="<?php echo esc_url( $cd_prev ); ?>">Customize It</button>
                        </div>
                    </div>
                </article>
                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

        <script>
        (function(){
            var topGrid = document.getElementById('tsa-coll-cat-top');
            if (!topGrid) return;
            var section = topGrid.closest('.tsa-party-collection');
            var groups  = document.querySelectorAll('.tsa-coll-group');
            function toTop(){ if (section) section.scrollIntoView({ behavior:'smooth', block:'start' }); }
            topGrid.addEventListener('click', function(e){
                var c = e.target.closest('.tsa-dl-cat-card'); if (!c) return;
                var cat = c.getAttribute('data-coll-cat');
                topGrid.hidden = true;
                groups.forEach(function(g){ g.hidden = (g.getAttribute('data-coll-group') !== cat); });
                toTop();
            });
            document.querySelectorAll('.tsa-coll-back').forEach(function(b){
                b.addEventListener('click', function(){
                    groups.forEach(function(g){ g.hidden = true; });
                    topGrid.hidden = false;
                    toTop();
                });
            });
        })();
        </script>
    </div>
</section>
<?php endif; ?>


<!-- ══ HOW IT WORKS ══════════════════════════════════════════════ -->
<section class="tsa-section" style="background:var(--surface-tint);border-top:1px solid var(--border)">
    <div class="tsa-section-head">
        <h2 style="color:var(--text-primary)">How Tee Parties Work</h2>
        <p style="color:var(--text-muted)">Designs drop in batches — configure any design on any apparel and build your cart throughout the showcase.</p>
    </div>
    <div class="tsa-how-grid" style="max-width:900px;margin:0 auto">
        <?php
        $steps = [
            [ '1', 'Showcase Opens',    'The showcase kicks off. Designs drop in batches on a schedule — each drop brings several new designs.' ],
            [ '2', 'Batches Drop',      'Watch the latest drop land in the spotlight, then settle into its categories. Click any design to launch the configurator.' ],
            [ '3', 'Configure & Add',   'Pick your apparel, color, style, and sizes. Add to cart — then keep watching for the next drop.' ],
            [ '4', 'Checkout by Deadline','When the countdown hits zero, the showcase closes. All orders are printed and shipped together.' ],
        ];
        foreach ( $steps as $s ) : ?>
        <div class="tsa-how-step" style="background:var(--surface-card);border:1px solid var(--border);box-shadow:var(--shadow-xs)">
            <div class="tsa-how-step__num"><?php echo esc_html( $s[0] ); ?></div>
            <h4 style="color:var(--text-primary)"><?php echo esc_html( $s[1] ); ?></h4>
            <p style="color:var(--text-muted)"><?php echo esc_html( $s[2] ); ?></p>
        </div>
        <?php endforeach; ?>
    </div>
</section>


<!-- ══ CTA ═══════════════════════════════════════════════════════ -->
<section class="tsa-section" style="text-align:center;padding:80px 7%;border-top:1px solid var(--border)">
    <h2 style="color:var(--text-primary);font-size:clamp(32px,5vw,52px);letter-spacing:-1.5px;margin:0 0 16px">Want to Host a Tee Party?</h2>
    <p style="color:var(--text-muted);font-size:18px;max-width:540px;margin:0 auto 28px;line-height:1.55">
        We run custom design showcases for schools, teams, churches, and organizations. Limited designs, online ordering, zero inventory risk.
    </p>
    <div class="tsa-actions tsa-actions--center">
        <?php tsa_btn( '/request-a-tee-party/', 'Request a Tee Party', 'primary' ); ?>
        <?php tsa_btn( '/contact/', 'Ask a Question', 'outline' ); ?>
    </div>
</section>

</div><!-- .tsa-party -->

<?php get_footer(); ?>

<script>
(function(){
    var API            = <?php echo wp_json_encode( $api_url ); ?>;
    var CONFIGURATOR   = <?php echo wp_json_encode( $configurator ); ?>;
    var PARTY_GARMENTS = <?php echo wp_json_encode( $party_garments ); ?>;
    var PARTY_COLORS   = <?php echo wp_json_encode( ! empty( $party_garment_colors ) ? $party_garment_colors : (object) [] ); ?>;
    var PARTY_COLORS_Q = (PARTY_COLORS && Object.keys(PARTY_COLORS).length) ? JSON.stringify(PARTY_COLORS) : '';
    var PARTY_STORE    = <?php echo wp_json_encode( $party_store_slug ); ?>;
    var PAGE_ID        = <?php echo (int) $page_id; ?>;
    var STATUS         = <?php echo wp_json_encode( $status ); ?>;
    var STORE_NAME     = <?php echo wp_json_encode( $party_store_name ); ?>;
    var STORE_URL      = <?php echo wp_json_encode( $party_store_url ); ?>;  // store landing ("Shop") URL, '' if none
    var PARTY_IS_SCHOOL= <?php echo ( $party_store_slug && $party_store_slug !== 'tsa' ) ? 'true' : 'false'; ?>;
    var currentStatus  = STATUS;   // refreshed by the poll so the stage can flip to the closed state
    var PARTY_DESIGNS  = <?php echo wp_json_encode( implode( ',', $party_design_ids ) ); ?>;  // drops + collection ids — scopes the configurator picker

    var loadingEl  = document.getElementById('tsa-grid-loading');
    var spotEl     = document.getElementById('tsa-spotlight');
    var comingEl   = document.getElementById('tsa-comingup');
    var catsEl     = document.getElementById('tsa-categories');

    var allDesigns = [];     // latest server snapshot of scheduled designs
    var nextRevealTs = null; // earliest upcoming reveal time (seconds)
    var lastSig = '';        // render signature — avoids rebuilds (and image flicker)

    function esc(s){ return String(s||'').replace(/&/g,'&amp;').replace(/</g,'&lt;').replace(/>/g,'&gt;').replace(/"/g,'&quot;'); }

    /* ── Deadline / grace countdown ── */
    var cdEl = document.getElementById('tsa-deadline-cd');
    if (cdEl) {
        var endTime = new Date(cdEl.dataset.end).getTime();
        var cdInterval = setInterval(function(){
            var diff = Math.max(0, endTime - Date.now());
            if (diff === 0) { clearInterval(cdInterval); return; }
            document.getElementById('cd-days').textContent  = Math.floor(diff/86400000);
            document.getElementById('cd-hours').textContent = Math.floor((diff%86400000)/3600000);
            document.getElementById('cd-mins').textContent  = Math.floor((diff%3600000)/60000);
            document.getElementById('cd-secs').textContent  = Math.floor((diff%60000)/1000);
        }, 1000);
    }

    // A design counts as revealed once the server says so OR the viewer's own
    // clock passes its reveal time — so drops land exactly when the on-screen
    // countdown hits zero, independent of small server/client clock drift.
    function isRevealed(d){
        return d.is_revealed || (d.reveal_ts && (d.reveal_ts * 1000) <= Date.now());
    }

    /* ── Group designs into drops, keyed by reveal time ── */
    function buildDrops(){
        var map = {}; // ts -> [designs]
        (allDesigns || []).forEach(function(d){
            var ts = d.reveal_ts || 0;
            (map[ts] = map[ts] || []).push(d);
        });
        return map;
    }

    /* ── Configurator card (uniform; square contained art + auto-contrast) ── */
    function buildCard(d){
        // Closed party = the showcase has ended. The designs now live in the store,
        // so closed cards become a LINK to that store's landing page ("Shop") — this
        // drives traffic into the store, which is the whole point of the drop. If the
        // party has no store (Main TSA), fall back to a static "ended" label.
        var isClosed = (currentStatus === 'closed');
        var configUrl = CONFIGURATOR + '?design_id=' + d.id + '&store=' + encodeURIComponent(PARTY_STORE)
                  + '&party_id=' + PAGE_ID
                  + (PARTY_GARMENTS ? '&party_garments=' + encodeURIComponent(PARTY_GARMENTS) : '')
                  + (PARTY_COLORS_Q ? '&party_colors=' + encodeURIComponent(PARTY_COLORS_Q) : '')
                  + (PARTY_DESIGNS ? '&party_designs=' + encodeURIComponent(PARTY_DESIGNS) : '');
        var closedLabel = STORE_NAME ? 'Shop the ' + esc(STORE_NAME) + ' Store →' : 'Showcase ended';
        var actionHtml = isClosed
            ? ( STORE_URL
                ? '<a href="'+esc(STORE_URL)+'" class="tsa-btn tsa-btn-primary" style="text-align:center;text-decoration:none;margin-top:auto">' + closedLabel + '</a>'
                : '<div class="tsa-pc-closed" style="text-align:center;margin-top:auto;font-size:13px;font-weight:700;color:var(--text-muted);background:var(--surface-tint);border:1px solid var(--border);border-radius:10px;padding:10px 12px">' + closedLabel + '</div>' )
            : '<a href="'+esc(configUrl)+'" class="tsa-btn tsa-btn-primary" style="text-align:center;text-decoration:none;margin-top:auto">Configure &amp; Order →</a>';

        var card = document.createElement('div');
        card.className = 'tsa-party-card' + (isClosed ? ' tsa-party-card--closed' : '');
        card.dataset.id = d.id;
        card.style.cssText = 'height:100%;background:var(--surface-card);border:1px solid var(--border);box-shadow:var(--shadow-sm);border-radius:12px;overflow:hidden;display:flex;flex-direction:column';

        var visual = d.thumbnail
            ? '<div class="tsa-pc-visual" style="background:#fff;line-height:0;transition:background .25s ease"><img src="'+esc(d.thumbnail)+'" alt="'+esc(d.name)+'" style="width:100%;aspect-ratio:1;object-fit:contain;padding:12px;box-sizing:border-box;display:block"></div>'
            : '<div style="width:100%;aspect-ratio:1;background:var(--surface-tint);display:flex;align-items:center;justify-content:center;font-size:40px">🎨</div>';

        card.innerHTML = visual +
            '<div style="padding:14px 16px;flex:1;display:flex;flex-direction:column;gap:10px">' +
                '<div style="font-weight:700;font-size:15px;color:var(--text-primary)">'+esc(d.name)+'</div>' +
                actionHtml +
            '</div>';

        var vis = card.querySelector('.tsa-pc-visual');
        var im  = vis && vis.querySelector('img');
        if (im){
            if (im.complete && im.naturalWidth) pickBgForImage(im, vis);
            else im.addEventListener('load', function(){ pickBgForImage(im, vis); });
        }
        return card;
    }

    /* ── Auto-contrast: white/very-light art gets a dark tile so it stays visible.
       Samples a downscaled copy on a canvas; falls back to white silently if the
       image can't be read (e.g. cross-origin without CORS). ── */
    var TSA_LIGHT_BG = '#ffffff';
    var TSA_DARK_BG  = '#544e48';
    function pickBgForImage(imgEl, container){
        try {
            var c = document.createElement('canvas');
            var w = c.width = 28, h = c.height = 28;
            var ctx = c.getContext('2d');
            ctx.drawImage(imgEl, 0, 0, w, h);
            var data = ctx.getImageData(0, 0, w, h).data;
            var opaque = 0, nearWhite = 0, lumSum = 0;
            for (var p = 0; p < data.length; p += 4){
                if (data[p+3] < 128) continue;
                opaque++;
                var r = data[p], g = data[p+1], b = data[p+2];
                if (r > 232 && g > 232 && b > 232) nearWhite++;
                lumSum += (0.2126*r + 0.7152*g + 0.0722*b) / 255;
            }
            if (!opaque) return;
            var whiteFrac = nearWhite / opaque;
            var avgLum    = lumSum / opaque;
            container.style.background = (whiteFrac > 0.05 || avgLum > 0.86) ? TSA_DARK_BG : TSA_LIGHT_BG;
        } catch (e) { /* canvas tainted — keep white */ }
    }

    /* ── Compact blurred teaser tile (for upcoming drops) ── */
    function buildTeaser(d){
        var t = document.createElement('div');
        t.className = 'tsa-teaser';
        t.dataset.id = d.id;
        var blurred = d.thumbnail
            ? '<img src="'+esc(d.thumbnail)+'" alt="" aria-hidden="true" style="width:100%;height:100%;object-fit:cover;filter:blur(16px) saturate(1.15);transform:scale(1.18)">'
            : '';
        t.innerHTML = blurred +
            '<div style="position:absolute;inset:0;display:flex;align-items:center;justify-content:center;background:rgba(37,33,36,.34);color:#fff;font-size:20px">🔒</div>';
        return t;
    }

    function plural(n){ return n === 1 ? '' : 's'; }
    function timeLabel(ts){ return new Date(ts*1000).toLocaleTimeString([], {hour:'2-digit', minute:'2-digit'}); }

    /* ── Full render of the stage (signature-gated to avoid churn) ── */
    function render(){
        var drops    = buildDrops();
        var allTs    = Object.keys(drops).map(Number).filter(function(t){ return t > 0; }).sort(function(a,b){ return a-b; });
        var nowMs    = Date.now();
        var revealedTs = allTs.filter(function(ts){ return ts*1000 <= nowMs; });
        var upcomingTs = allTs.filter(function(ts){ return ts*1000 >  nowMs; });
        var spotlightTs = revealedTs.length ? revealedTs[revealedTs.length-1] : null;
        nextRevealTs = upcomingTs.length ? upcomingTs[0] : null;

        // Skip rebuild when nothing structural changed (poll-safe). Includes the
        // design id set so adding/removing a design from a batch mid-party re-renders.
        var sig = JSON.stringify({ st: currentStatus, s: spotlightTs, u: upcomingTs, r: revealedTs, ids: (allDesigns||[]).map(function(d){ return d.id; }).sort() });
        if (sig === lastSig) return;
        lastSig = sig;

        if (loadingEl) loadingEl.style.display = 'none';

        /* ─ Spotlight: the most-recent revealed drop batch ─ */
        if (spotlightTs !== null) {
            var batch = drops[spotlightTs] || [];
            var head  = '<div class="tsa-spotlight__head">' +
                '<span class="tsa-spotlight__badge"><span class="dot"></span> Just Dropped · ' + batch.length + ' new design' + plural(batch.length) + '</span>' +
                (nextRevealTs ? '<span class="tsa-spotlight__next">Next drop in <b id="tsa-spot-next-timer">--:--</b></span>' : '') +
                '</div>';
            spotEl.innerHTML = head + '<div class="tsa-spotlight__grid" id="tsa-spot-grid"></div>';
            var sg = spotEl.querySelector('#tsa-spot-grid');
            batch.forEach(function(d){ sg.appendChild(buildCard(d)); });
            spotEl.hidden = false;
        } else if (nextRevealTs) {
            // Nothing revealed yet — count down to the first drop.
            spotEl.innerHTML = '<div class="tsa-spotlight__wait">' +
                '<div style="font-size:13px;text-transform:uppercase;letter-spacing:.8px;opacity:.7;margin-bottom:6px">First drop reveals in</div>' +
                '<div class="tsa-spotlight__wait-num"><span id="tsa-spot-next-timer">--:--</span></div>' +
                '</div>';
            spotEl.hidden = false;
        } else {
            spotEl.hidden = true;
        }

        /* ─ Coming up: remaining drops, grouped by drop time ─ */
        if (upcomingTs.length) {
            var groups = '<div class="tsa-comingup__label">Coming up</div><div class="tsa-comingup__groups">';
            upcomingTs.forEach(function(ts, i){
                var batch = drops[ts] || [];
                var isNext = (i === 0);
                var headTxt = (isNext ? 'Next drop · ' : '') + timeLabel(ts) + ' · ' + batch.length + ' design' + plural(batch.length);
                groups += '<div class="tsa-drop-group' + (isNext ? ' tsa-drop-group--next' : '') + '">' +
                    '<div class="tsa-drop-group__head">' + (isNext ? '🔥 ' : '') + esc(headTxt) + '</div>' +
                    '<div class="tsa-drop-group__tiles" data-ts="' + ts + '"></div></div>';
            });
            groups += '</div>';
            comingEl.innerHTML = groups;
            upcomingTs.forEach(function(ts){
                var holder = comingEl.querySelector('.tsa-drop-group__tiles[data-ts="' + ts + '"]');
                (drops[ts] || []).forEach(function(d){ holder.appendChild(buildTeaser(d)); });
            });
            comingEl.hidden = false;
        } else {
            comingEl.hidden = true;
        }

        /* ─ Category sections: all PRIOR-drop designs, grouped + freshest-first ─ */
        var priorTs = revealedTs.filter(function(ts){ return ts !== spotlightTs; });
        var demotedTopTs = priorTs.length ? priorTs[priorTs.length-1] : null;
        var buckets = {}; // slug -> { name, freshest, designs:[] }
        var MORE = '__more';
        priorTs.forEach(function(ts){
            (drops[ts] || []).forEach(function(d){
                var cat = d.category;
                var slug = cat && cat.slug ? cat.slug : MORE;
                var name = cat && cat.name ? cat.name : 'More Designs';
                if (!buckets[slug]) buckets[slug] = { name:name, freshest:0, designs:[] };
                buckets[slug].designs.push(d);
                if (ts > buckets[slug].freshest) buckets[slug].freshest = ts;
            });
        });
        var slugs = Object.keys(buckets).sort(function(a,b){
            if (a === MORE) return 1; if (b === MORE) return -1;           // "More" always last
            if (buckets[b].freshest !== buckets[a].freshest) return buckets[b].freshest - buckets[a].freshest; // freshest first
            return buckets[a].name.localeCompare(buckets[b].name);
        });

        if (slugs.length) {
            catsEl.innerHTML = '';
            slugs.forEach(function(slug){
                var b = buckets[slug];
                var sec = document.createElement('div');
                sec.className = 'tsa-cat-section';
                var isLatest = (b.freshest === demotedTopTs);
                sec.innerHTML = '<div class="tsa-cat-section__head">' +
                    '<span class="tsa-cat-section__name">' + esc(b.name) + '</span>' +
                    '<span class="tsa-cat-section__count">' + b.designs.length + ' design' + plural(b.designs.length) + '</span>' +
                    (isLatest ? '<span class="tsa-cat-section__badge">Latest drop</span>' : '') +
                    '</div><div class="tsa-cat-section__grid"></div>';
                var grid = sec.querySelector('.tsa-cat-section__grid');
                // Newest designs first within a category.
                b.designs.sort(function(x,y){ return (y.reveal_ts||0) - (x.reveal_ts||0); });
                b.designs.forEach(function(d){ grid.appendChild(buildCard(d)); });
                catsEl.appendChild(sec);
            });
            catsEl.hidden = false;
        } else {
            catsEl.hidden = true;
        }

        // Nothing at all to show yet.
        if (spotEl.hidden && comingEl.hidden && catsEl.hidden && loadingEl) {
            loadingEl.style.display = '';
            loadingEl.innerHTML =
                '<div class="tsa-drop-soon">' +
                    '<div class="tsa-drop-soon__emoji">🎉</div>' +
                    '<div class="tsa-drop-soon__title">Designs Drop Soon!</div>' +
                    '<div class="tsa-drop-soon__sub">The first reveal is moments away — check back to snag yours before the deadline.</div>' +
                '</div>';
        }
    }

    /* ── 1s tick: flip due drops + update the next-drop timer ── */
    function tick(){
        render(); // signature-gated, cheap when unchanged
        var timer = document.getElementById('tsa-spot-next-timer');
        if (!timer) return;
        if (!nextRevealTs) { timer.textContent = '--:--'; return; }
        var diff = Math.max(0, nextRevealTs * 1000 - Date.now());
        var h = Math.floor(diff/3600000);
        var m = Math.floor((diff%3600000)/60000);
        var s = Math.floor((diff%60000)/1000);
        timer.textContent = (h ? h+':' : '') + (h ? String(m).padStart(2,'0') : m) + ':' + String(s).padStart(2,'0');
    }
    setInterval(tick, 1000);

    /* ── Fetch party data (initial + 60s safety poll) ── */
    function fetchParty(){
        fetch(API).then(function(r){ return r.json(); }).then(function(data){
            allDesigns = data.designs || [];
            currentStatus = data.status || STATUS;
            render();
            if (currentStatus === 'closed') {
                var b = document.getElementById('tsa-party-status-badge');
                if (b) b.innerHTML = '🔒 Showcase Closed';
            }
        }).catch(function(){ if (loadingEl && lastSig === '') loadingEl.textContent = 'Could not load designs. Refresh to retry.'; });
    }

    fetchParty();

    // Safety poll — catches admin "Reveal Now", new designs, and schedule edits.
    if (STATUS === 'live' || STATUS === 'grace') {
        setInterval(fetchParty, 60000);
    }

})();
</script>
