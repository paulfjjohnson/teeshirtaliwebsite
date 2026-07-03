<?php
/**
 * Template Name: TSA Team Directory
 *
 * Lists all TSA partner team stores grouped by sport.
 * Assign to: /team-stores/
 */

defined( 'ABSPATH' ) || exit;

get_header();

/* ── Team roster ───────────────────────────────────────────────────────────────
 * status:  'live' | 'coming-soon'
 * url:     absolute path — only set for live stores
 * color/2: primary / secondary brand hex
 * sport:   subtitle shown on the card
 * ──────────────────────────────────────────────────────────────────────────── */
$team_groups = [

    'Baseball' => [
        [
            'name'      => 'Muddawgs Baseball',
            'status'    => 'live',
            'sport'     => 'Travel Baseball · Louisiana',
            'color'     => '#0a0a0a',
            'color2'    => '#dc2626',
            'url'       => '/muddawgs-home/',
            'live_pill' => 'bkrd',   // black bg · red text
        ],
    ],

    'Football' => [
        [ 'name' => 'Get Your Team Listed', 'status' => 'coming-soon', 'sport' => 'Football' ],
    ],

    'Basketball' => [
        [ 'name' => 'Get Your Team Listed', 'status' => 'coming-soon', 'sport' => 'Basketball' ],
    ],

    'Soccer' => [
        [ 'name' => 'Get Your Team Listed', 'status' => 'coming-soon', 'sport' => 'Soccer' ],
    ],

    'Softball' => [
        [ 'name' => 'Get Your Team Listed', 'status' => 'coming-soon', 'sport' => 'Softball' ],
    ],

    'Volleyball' => [
        [ 'name' => 'Get Your Team Listed', 'status' => 'coming-soon', 'sport' => 'Volleyball' ],
    ],

];

// Count only real partner teams (non-placeholder entries)
$live_count  = 1;
$total_teams = 1;
?>

<!-- ══════════════════════════════════════
     HERO
══════════════════════════════════════ -->
<section class="tsa-td-hero">
    <div class="tsa-sd-container">
        <div class="tsa-kicker tsa-kicker--gold">Team Spirit Stores</div>
        <h1>Find Your Team</h1>
        <p class="tsa-td-hero__sub">Dedicated online stores for travel teams, rec leagues, and competitive clubs. Gear up your squad — custom merch with no minimums.</p>
        <div class="tsa-sd-stats">
            <div class="tsa-sd-stat">
                <span class="tsa-sd-stat__num"><?php echo $total_teams; ?></span>
                <span class="tsa-sd-stat__label">Partner Teams</span>
            </div>
            <div class="tsa-sd-stat-divider"></div>
            <div class="tsa-sd-stat">
                <span class="tsa-sd-stat__num tsa-sd-stat__num--live"><?php echo $live_count; ?></span>
                <span class="tsa-sd-stat__label">Stores Live Now</span>
            </div>
            <div class="tsa-sd-stat-divider"></div>
            <div class="tsa-sd-stat">
                <span class="tsa-sd-stat__num"><?php echo $total_teams - $live_count; ?></span>
                <span class="tsa-sd-stat__label">More Coming</span>
            </div>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     LIVE STORE SPOTLIGHT — MUDDAWGS
══════════════════════════════════════ -->
<section class="tsa-td-spotlight-wrap">
    <div class="tsa-sd-container">
        <div class="tsa-td-spotlight tsa-td-spotlight--bkrd">
            <div class="tsa-td-spotlight__badge tsa-td-spotlight__badge--bkrd">● Live Now</div>
            <div class="tsa-td-spotlight__body">
                <div class="tsa-td-spotlight__name">Muddawgs Baseball</div>
                <div class="tsa-td-spotlight__sub">Travel Baseball · Louisiana — Custom merch &amp; new drops every season</div>
                <div class="tsa-td-spotlight__tags">
                    <span class="tsa-td-tag tsa-td-tag--live">Custom Jerseys</span>
                    <span class="tsa-td-tag tsa-td-tag--live">Team Gear</span>
                    <span class="tsa-td-tag">Seasonal Drops</span>
                </div>
            </div>
            <a href="<?php echo esc_url( home_url( '/muddawgs-home/' ) ); ?>" class="tsa-td-spotlight__cta"
               style="background:#dc2626;color:#fff">
                Shop Muddawgs →
            </a>
        </div>
    </div>
</section>

<!-- ══════════════════════════════════════
     TEAM GROUPS
══════════════════════════════════════ -->
<section class="tsa-td-directory">
    <div class="tsa-sd-container">

        <?php foreach ( $team_groups as $sport => $teams ) :
            $group_live  = count( array_filter( $teams, function( $t ) { return $t['status'] === 'live'; } ) );
            $group_total = count( $teams );
        ?>
        <div class="tsa-sd-group">
            <div class="tsa-sd-group__header">
                <h2 class="tsa-sd-group__title"><?php echo esc_html( $sport ); ?></h2>
                <span class="tsa-sd-group__count">
                    <?php echo $group_total; ?> <?php echo $group_total === 1 ? 'team' : 'teams'; ?>
                    <?php if ( $group_live > 0 ) : ?>
                    &nbsp;·&nbsp; <span class="tsa-sd-count-live"><?php echo $group_live; ?> live</span>
                    <?php endif; ?>
                </span>
            </div>

            <div class="tsa-sd-grid">
                <?php foreach ( $teams as $team ) :
                    $is_live  = $team['status'] === 'live';
                    $color1   = isset( $team['color']  ) ? $team['color']  : 'var(--tsa-gold)';
                    $color2   = isset( $team['color2'] ) ? $team['color2'] : '#c0c0c0';
                    $initials = '';
                    $words    = explode( ' ', $team['name'] );
                    foreach ( $words as $w ) {
                        if ( ! in_array( strtolower( $w ), [ 'your', 'team', 'here', 'get', 'listed' ], true ) ) {
                            $initials .= strtoupper( substr( $w, 0, 1 ) );
                        }
                        if ( strlen( $initials ) >= 2 ) break;
                    }
                    if ( empty( $initials ) ) $initials = strtoupper( substr( $team['name'], 0, 2 ) );
                ?>

                <?php if ( $is_live ) : ?>
                <a href="<?php echo esc_url( home_url( $team['url'] ) ); ?>" class="tsa-sd-card tsa-sd-card--live">
                    <div class="tsa-sd-card__stripe" style="background:linear-gradient(90deg,<?php echo esc_attr($color1); ?>,<?php echo esc_attr($color2); ?>)"></div>
                    <div class="tsa-sd-card__avatar" style="background:<?php echo esc_attr($color1); ?>;color:#fff">
                        <?php echo esc_html( $initials ); ?>
                    </div>
                    <div class="tsa-sd-card__body">
                        <div class="tsa-sd-card__name"><?php echo esc_html( $team['name'] ); ?></div>
                        <?php if ( isset( $team['sport'] ) ) : ?>
                        <div class="tsa-td-sport-badge"><?php echo esc_html( $team['sport'] ); ?></div>
                        <?php endif; ?>
                    </div>
                    <?php $pill_mod = isset( $team['live_pill'] ) ? ' tsa-live--' . esc_attr( $team['live_pill'] ) : ''; ?>
                    <div class="tsa-sd-card__right">
                        <div class="tsa-sd-card__status tsa-sd-card__status--live<?php echo $pill_mod; ?>">
                            <span class="tsa-sd-dot<?php echo $pill_mod; ?>"></span> Live
                        </div>
                        <div class="tsa-sd-card__arrow">→</div>
                    </div>
                </a>

                <?php else : ?>
                <div class="tsa-sd-card tsa-sd-card--soon">
                    <div class="tsa-sd-card__body">
                        <div class="tsa-sd-card__name"><?php echo esc_html( $team['name'] ); ?></div>
                        <?php if ( isset( $team['sport'] ) ) : ?>
                        <div class="tsa-td-sport-badge"><?php echo esc_html( $team['sport'] ); ?></div>
                        <?php endif; ?>
                    </div>
                    <div class="tsa-sd-card__right">
                        <div class="tsa-sd-card__status tsa-sd-card__status--soon">Soon</div>
                    </div>
                </div>
                <?php endif; ?>

                <?php endforeach; ?>
            </div>
        </div>
        <?php endforeach; ?>

    </div>
</section>

<!-- ══════════════════════════════════════
     CTA — GET YOUR TEAM LISTED
══════════════════════════════════════ -->
<section class="tsa-td-cta">
    <div class="tsa-sd-container">
        <div class="tsa-td-cta__inner">
            <div class="tsa-td-cta__copy">
                <h2>Ready to gear up your team?</h2>
                <p>We set up custom online stores for travel teams, rec leagues, and competitive clubs. No upfront cost, no minimums — just great gear with fast turnaround.</p>
            </div>
            <div class="tsa-td-cta__actions">
                <a href="<?php echo esc_url( home_url( '/request-a-store/?type=team' ) ); ?>" class="tsa-btn tsa-btn-primary">Get Your Team Store</a>
                <a href="<?php echo esc_url( home_url( '/team-stores/' ) ); ?>" class="tsa-btn tsa-btn-outline">How It Works</a>
            </div>
        </div>
    </div>
</section>

<?php get_footer(); ?>
